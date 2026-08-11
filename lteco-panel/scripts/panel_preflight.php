<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/app_config.php';

$checks = [];
$failures = 0;
$check = static function (string $name, bool $ok, string $detail = '') use (&$checks, &$failures): void {
    $checks[] = [$name, $ok, $detail];
    if (!$ok) {
        $failures++;
    }
};

try {
    $host = (string)configEnv('LTECO_DB_HOST', 'host.docker.internal');
    $db = (string)configEnv('LTECO_DB_NAME', '');
    $user = (string)configEnv('LTECO_DB_USER', '');
    $pass = (string)configEnv('LTECO_DB_PASS', (string)configEnv('LTECO_DB_PASSWORD', ''));
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $check('db_connection', $pdo->query('SELECT 1')->fetchColumn() === 1 || $pdo->query('SELECT 1')->fetchColumn() === '1', $db);

    $requiredTables = [
        'producto', 'vehiculo', 'venta', 'venta_detalle', 'usuario', 'cliente',
        'schema_migrations', 'panel_idempotency_key', 'login_attempts',
        'n8n_webhook_setting', 'automation_event', 'notificacion_whatsapp',
    ];
    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})");
    $stmt->execute($requiredTables);
    $existing = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    $missing = array_values(array_filter($requiredTables, static fn(string $table): bool => !isset($existing[$table])));
    $check('schema_required_tables', $missing === [], $missing ? implode(',', $missing) : 'ok');

    $count = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    $check('schema_migrations_ledger', $count > 0, (string)$count);
} catch (Throwable $e) {
    $check('db_connection', false, $e->getMessage());
}

$dirs = [
    'storage/logs' => dirname(__DIR__, 2) . '/storage/logs',
    'uploads/vehiculos' => dirname(__DIR__) . '/uploads/vehiculos',
];
foreach ($dirs as $name => $path) {
    $check('writable_' . $name, is_dir($path) && is_writable($path), $path);
}

$secrets = [
    'LTECO_DB_NAME' => (string)configEnv('LTECO_DB_NAME', ''),
    'LTECO_DB_USER' => (string)configEnv('LTECO_DB_USER', ''),
    'LTECO_STOREFRONT_API_CURRENT_SECRET' => (string)configEnv('LTECO_STOREFRONT_API_CURRENT_SECRET', ''),
    'LTECO_N8N_WEBHOOK_TOKEN' => (string)configEnv('LTECO_N8N_WEBHOOK_TOKEN', ''),
];
foreach ($secrets as $key => $value) {
    $check('secret_' . $key, trim($value) !== '', $key);
}

foreach ($checks as [$name, $ok, $detail]) {
    echo ($ok ? 'OK ' : 'FAIL ') . $name . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
}

exit($failures === 0 ? 0 : 1);
