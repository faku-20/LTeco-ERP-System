<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/app_config.php';

function b5BackupDir(): string
{
    $dir = rtrim(str_replace('\\', '/', (string)configEnv('LTECO_BACKUP_DIR', '/opt/backups/ltecobike')), '/');
    return $dir !== '' ? $dir : '/opt/backups/ltecobike';
}

function b5WriteStatus(array $status): void
{
    $dir = b5BackupDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $status['recorded_at'] = date(DATE_ATOM);
    file_put_contents($dir . '/backup-status.json', json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    @chmod($dir . '/backup-status.json', 0640);
}

function b5Run(array $command, ?string $stdoutFile = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => $stdoutFile !== null ? ['file', $stdoutFile, 'w'] : ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo ejecutar comando.');
    }
    fclose($pipes[0]);
    $stdout = $stdoutFile === null ? (stream_get_contents($pipes[1]) ?: '') : '';
    if ($stdoutFile === null) {
        fclose($pipes[1]);
    }
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    $code = proc_close($process);
    return [$code, $stdout, $stderr];
}

try {
    $started = microtime(true);
    $dir = b5BackupDir();
    if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
        throw new RuntimeException('No se pudo crear backup dir.');
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Backup dir no escribible: ' . $dir);
    }
    @chmod($dir, 0770);

    $host = (string)configEnv('LTECO_DB_BACKUP_HOST', (string)configEnv('LTECO_DB_HOST', 'host.docker.internal'));
    $name = (string)configEnv('LTECO_DB_NAME', '');
    $user = (string)configEnv('LTECO_DB_BACKUP_USER', (string)configEnv('LTECO_DB_USER', ''));
    $pass = (string)configEnv('LTECO_DB_BACKUP_PASS', (string)configEnv('LTECO_DB_PASS', ''));
    if ($name === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Faltan variables LTECO_DB_NAME/LTECO_DB_BACKUP_USER/LTECO_DB_BACKUP_PASS.');
    }

    $timestamp = date('Y-m-d_H-i-s');
    $tmpSql = $dir . '/.lteco_db_' . $timestamp . '.sql.tmp';
    $sql = $dir . '/lteco_db_' . $timestamp . '.sql';
    $gz = $sql . '.gz';
    putenv('MYSQL_PWD=' . $pass);
    [$code, , $stderr] = b5Run([
        'mysqldump',
        '--ssl=0',
        '-h', $host,
        '-u', $user,
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        $name,
    ], $tmpSql);
    putenv('MYSQL_PWD');
    if ($code !== 0) {
        @unlink($tmpSql);
        throw new RuntimeException('mysqldump falló: ' . trim($stderr));
    }
    if (!is_file($tmpSql) || filesize($tmpSql) <= 0 || !str_contains((string)file_get_contents($tmpSql, false, null, 0, 4096), 'MariaDB dump')) {
        @unlink($tmpSql);
        throw new RuntimeException('Dump inválido o vacío.');
    }
    $dump = (string)file_get_contents($tmpSql);
    $dump = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/i', '', $dump) ?? $dump;
    $dump = preg_replace('#/\*!50017\s+DEFINER=[^*]+ \*/#i', '', $dump) ?? $dump;
    file_put_contents($tmpSql, $dump);
    rename($tmpSql, $sql);
    [$gzipCode, , $gzipStderr] = b5Run(['gzip', '-f', $sql]);
    if ($gzipCode !== 0) {
        @unlink($sql);
        throw new RuntimeException('gzip falló: ' . trim($gzipStderr));
    }
    [$testCode, , $testStderr] = b5Run(['gzip', '-t', $gz]);
    if ($testCode !== 0) {
        throw new RuntimeException('gzip inválido: ' . trim($testStderr));
    }
    $checksum = hash_file('sha256', $gz);
    $size = filesize($gz);
    if ($checksum === false || $size === false || $size <= 0) {
        throw new RuntimeException('No se pudo verificar checksum/tamaño.');
    }
    file_put_contents($gz . '.sha256', $checksum . '  ' . basename($gz) . PHP_EOL);
    @chmod($gz, 0640);
    @chmod($gz . '.sha256', 0640);

    $deleted = 0;
    foreach (glob($dir . '/lteco_db_*.sql.gz') ?: [] as $candidate) {
        $base = basename($candidate);
        if (preg_match('/^lteco_db_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $base) !== 1) {
            continue;
        }
        if (filemtime($candidate) !== false && filemtime($candidate) < time() - 14 * 86400) {
            @unlink($candidate . '.sha256');
            if (@unlink($candidate)) {
                $deleted++;
            }
        }
    }

    $status = [
        'ok' => true,
        'db' => $name,
        'file' => $gz,
        'size_bytes' => $size,
        'sha256' => $checksum,
        'duration_seconds' => round(microtime(true) - $started, 3),
        'retention_days' => 14,
        'deleted_old_files' => $deleted,
        'error' => null,
    ];
    b5WriteStatus($status);
    echo 'Backup creado: ' . $gz . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    b5WriteStatus([
        'ok' => false,
        'db' => (string)configEnv('LTECO_DB_NAME', ''),
        'file' => null,
        'size_bytes' => 0,
        'sha256' => null,
        'duration_seconds' => null,
        'retention_days' => 14,
        'error' => $e->getMessage(),
    ]);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
