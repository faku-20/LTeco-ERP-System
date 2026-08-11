<?php

declare(strict_types=1);

namespace Lteco\Application\Operations;

use Lteco\Application\Inventario\InventarioReconciliadorService;
use Lteco\Infrastructure\Db\Connection;
use PDO;
use Throwable;

final class OperationalHealthService
{
    public function __construct(
        private PDO $pdo,
        private string $projectRoot,
        private string $backupStatusPath,
        private string $heartbeatPath,
        private string $stateDir,
        private int $heartbeatMaxAgeSeconds = 180,
        private int $backupMaxAgeSeconds = 93600,
        private int $diskMinFreePercent = 10,
        private int $alertCooldownSeconds = 3600,
        private ?string $storefrontUrl = null,
    ) {
    }

    /** @return array{ok:bool,checks:array<string,array<string,mixed>>,problems:list<string>} */
    public function evaluate(): array
    {
        $checks = [
            'panel' => $this->checkPanel(),
            'db' => $this->checkDb(),
            'storefront' => $this->checkStorefront(),
            'worker_heartbeat' => $this->checkHeartbeat(),
            'backup' => $this->checkBackup(),
            'disk' => $this->checkDisk(),
            'inventory_reconciliation' => $this->checkInventoryReconciliation(),
        ];

        $problems = [];
        foreach ($checks as $name => $check) {
            if (empty($check['ok'])) {
                $problems[] = $name . ': ' . (string)($check['message'] ?? 'fail');
            }
        }

        return ['ok' => $problems === [], 'checks' => $checks, 'problems' => $problems];
    }

    public function recordHeartbeat(string $source = 'ecommerce_worker'): void
    {
        $dir = dirname($this->heartbeatPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = [
            'ok' => true,
            'source' => preg_replace('/[^A-Za-z0-9_.:-]/', '', $source) ?: 'worker',
            'time' => date('c'),
            'timestamp' => time(),
        ];

        @file_put_contents(
            $this->heartbeatPath,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL,
            LOCK_EX
        );
        @chmod($this->heartbeatPath, 0640);
    }

    /** @param callable(string):bool $sender */
    public function alertOnTransition(array $result, callable $sender): bool
    {
        $problemKeys = [];
        foreach ($result['checks'] ?? [] as $name => $check) {
            if (empty($check['ok'])) {
                $problemKeys[] = (string)$name;
            }
        }

        sort($problemKeys);
        $fingerprint = hash('sha256', implode('|', $problemKeys));
        $state = $this->readAlertState();
        $now = time();
        $sameProblem = ($state['fingerprint'] ?? '') === $fingerprint;
        $lastSent = (int)($state['last_sent_at'] ?? 0);

        if ($problemKeys === []) {
            if (($state['fingerprint'] ?? '') !== 'healthy') {
                $this->writeAlertState(['fingerprint' => 'healthy', 'last_sent_at' => $now]);
            }
            return false;
        }

        if ($sameProblem && ($now - $lastSent) < $this->alertCooldownSeconds) {
            return false;
        }

        $message = "<b>ERP alerta operacional</b>\n"
            . 'Estado: problemático' . "\n"
            . 'Checks: ' . $this->sanitizeMessage(implode(', ', $problemKeys));

        $sent = $sender($message);
        if ($sent) {
            $this->writeAlertState(['fingerprint' => $fingerprint, 'last_sent_at' => $now]);
        }

        return $sent;
    }

    /** @return array{ok:bool,message:string} */
    private function checkPanel(): array
    {
        return ['ok' => is_dir($this->projectRoot . '/lteco-panel'), 'message' => 'panel files'];
    }

    /** @return array{ok:bool,message:string} */
    private function checkDb(): array
    {
        try {
            $ok = (string)$this->pdo->query('SELECT 1')->fetchColumn() === '1';
            return ['ok' => $ok, 'message' => $ok ? 'connected' : 'select failed'];
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'connection failed'];
        }
    }

    /** @return array{ok:bool,message:string} */
    private function checkStorefront(): array
    {
        $url = $this->storefrontUrl;
        if ($url === null || trim($url) === '') {
            $url = 'http://web/public-web/';
        }
        if (str_starts_with($url, 'file://')) {
            $path = substr($url, 7);
            return ['ok' => is_file($path), 'message' => is_file($path) ? 'file ok' : 'file missing'];
        }

        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 4, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $m) === 1) {
                $status = (int)$m[1];
                break;
            }
        }

        return [
            'ok' => $status >= 200 && $status < 400,
            'message' => $status > 0 ? 'http ' . $status : 'unreachable',
        ];
    }

    /** @return array{ok:bool,message:string,age_seconds?:int} */
    private function checkHeartbeat(): array
    {
        $data = $this->readJsonFile($this->heartbeatPath);
        $timestamp = is_array($data) ? (int)($data['timestamp'] ?? 0) : 0;
        if ($timestamp <= 0 && is_file($this->heartbeatPath)) {
            $timestamp = (int)@filemtime($this->heartbeatPath);
        }
        if ($timestamp <= 0) {
            return ['ok' => false, 'message' => 'missing'];
        }
        $age = time() - $timestamp;
        return [
            'ok' => $age <= $this->heartbeatMaxAgeSeconds,
            'message' => $age <= $this->heartbeatMaxAgeSeconds ? 'recent' : 'stale',
            'age_seconds' => $age,
        ];
    }

    /** @return array{ok:bool,message:string,age_seconds?:int} */
    private function checkBackup(): array
    {
        $status = $this->readJsonFile($this->backupStatusPath);
        if (!is_array($status)) {
            return ['ok' => false, 'message' => 'status missing'];
        }
        if (empty($status['ok'])) {
            return ['ok' => false, 'message' => 'last backup failed'];
        }

        $file = (string)($status['file'] ?? '');
        if ($file === '' || !is_file($file)) {
            return ['ok' => false, 'message' => 'file missing'];
        }

        $timestamp = (int)($status['timestamp'] ?? 0);
        if ($timestamp <= 0) {
            $time = strtotime((string)($status['time'] ?? ''));
            $timestamp = $time !== false ? $time : (int)@filemtime($file);
        }
        $age = time() - $timestamp;

        return [
            'ok' => $age <= $this->backupMaxAgeSeconds,
            'message' => $age <= $this->backupMaxAgeSeconds ? 'recent' : 'stale',
            'age_seconds' => $age,
        ];
    }

    /** @return array{ok:bool,message:string,free_percent?:float} */
    private function checkDisk(): array
    {
        $path = is_dir(dirname($this->backupStatusPath)) ? dirname($this->backupStatusPath) : $this->projectRoot;
        $total = (float)@disk_total_space($path);
        $free = (float)@disk_free_space($path);
        if ($total <= 0.0) {
            return ['ok' => false, 'message' => 'unknown'];
        }
        $freePercent = ($free / $total) * 100.0;
        return [
            'ok' => $freePercent >= $this->diskMinFreePercent,
            'message' => $freePercent >= $this->diskMinFreePercent ? 'enough' : 'low',
            'free_percent' => round($freePercent, 2),
        ];
    }

    /** @return array{ok:bool,message:string,errors:int,warnings:int} */
    private function checkInventoryReconciliation(): array
    {
        try {
            $result = (new InventarioReconciliadorService(new Connection($this->pdo)))->ejecutar();
            $errors = 0;
            $warnings = 0;
            foreach ($result as $row) {
                if (($row['severity'] ?? '') === 'ERROR') {
                    $errors += (int)($row['count'] ?? 0);
                } elseif (($row['severity'] ?? '') === 'WARN') {
                    $warnings += (int)($row['count'] ?? 0);
                }
            }
            return [
                'ok' => $errors === 0 && $warnings === 0,
                'message' => $errors === 0 && $warnings === 0 ? 'clean' : 'inconsistent',
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'failed', 'errors' => 1, 'warnings' => 0];
        }
    }

    /** @return mixed */
    private function readJsonFile(string $path): mixed
    {
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /** @return array<string,mixed> */
    private function readAlertState(): array
    {
        $state = $this->readJsonFile($this->stateDir . '/operational-alert-state.json');
        return is_array($state) ? $state : [];
    }

    /** @param array<string,mixed> $state */
    private function writeAlertState(array $state): void
    {
        if (!is_dir($this->stateDir)) {
            @mkdir($this->stateDir, 0775, true);
        }
        @file_put_contents(
            $this->stateDir . '/operational-alert-state.json',
            json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL,
            LOCK_EX
        );
    }

    private function sanitizeMessage(string $message): string
    {
        return preg_replace('/(password|passwd|clave|token|secret|authorization|cookie|mfa|recovery)[^,\n]*/i', '$1 [REDACTED]', $message)
            ?? 'alerta operacional';
    }
}
