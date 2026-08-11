<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/PanelTestGuard.php';

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  OK {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  FAIL {$nombre}\n";
};

$db = (string)getenv('LTECO_TEST_DB_NAME');
putenv('LTECO_DB_USER=' . (string)getenv('LTECO_TEST_DB_USER'));
PanelTestGuard::assertSafeForMutation($db);
$root = dirname(__DIR__, 2);
$envPrefix = sprintf(
    'LTECO_ENV=testing LTECO_DB_HOST=%s LTECO_DB_NAME=%s LTECO_DB_USER=%s LTECO_DB_PASS=%s LTECO_STOREFRONT_API_CURRENT_SECRET=b5-secret LTECO_N8N_WEBHOOK_TOKEN=b5-token ',
    escapeshellarg((string)getenv('LTECO_TEST_DB_HOST')),
    escapeshellarg($db),
    escapeshellarg((string)getenv('LTECO_TEST_DB_USER')),
    escapeshellarg((string)getenv('LTECO_TEST_DB_PASSWORD')),
);

echo "B5 migrations/preflight\n";
$list = (string)shell_exec($envPrefix . 'php ' . escapeshellarg($root . '/lteco-panel/scripts/panel_migrate.php') . ' --list 2>&1');
$check('migration re-run/list no tiene pendientes', str_contains($list, 'No pending migrations.'));

$preflightOutput = (string)shell_exec($envPrefix . 'php ' . escapeshellarg($root . '/lteco-panel/scripts/panel_preflight.php') . ' 2>&1');
$check('preflight panel OK en DB test', str_contains($preflightOutput, 'OK db_connection') && !str_contains($preflightOutput, 'FAIL '));

echo "\nB5 backup failure/status\n";
$badDir = sys_get_temp_dir() . '/lteco-b5-backup-' . bin2hex(random_bytes(4));
mkdir($badDir, 0700, true);
$bad = (string)shell_exec($envPrefix . ' LTECO_BACKUP_DIR=' . escapeshellarg($badDir) . ' LTECO_DB_BACKUP_USER=invalid_b5 LTECO_DB_BACKUP_PASS=invalid php ' . escapeshellarg($root . '/lteco-panel/scripts/backup_cli.php') . ' 2>&1');
$statusFile = $badDir . '/backup-status.json';
$status = is_file($statusFile) ? json_decode((string)file_get_contents($statusFile), true) : null;
$check('backup con credencial invalida falla cerrado', str_contains($bad, 'ERROR:') && is_array($status) && ($status['ok'] ?? true) === false);

$latest = trim((string)shell_exec('ls -t /opt/backups/ltecobike/lteco_db_*.sql.gz 2>/dev/null | head -1'));
$check('backup real tiene gzip', $latest !== '' && is_file($latest));
if ($latest !== '') {
    $gzipOk = shell_exec('gzip -t ' . escapeshellarg($latest) . ' 2>&1; echo $?');
    $shaFile = $latest . '.sha256';
    $check('backup real gzip valido', trim((string)$gzipOk) === '0');
    $check('backup real tiene checksum', is_file($shaFile) && trim((string)file_get_contents($shaFile)) !== '');
}

if ($fallos !== []) {
    echo "\nFALLO B5: " . count($fallos) . " checks fallaron\n";
    foreach ($fallos as $f) {
        echo ' - ' . $f . "\n";
    }
    exit(1);
}

echo "\nOK B5: {$ok} checks pasaron\n";
