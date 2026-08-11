<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/telegram.php';

use Lteco\Application\Operations\OperationalHealthService;

$root = dirname(__DIR__, 2);
$stateDir = dirname(__DIR__) . '/storage/operational';
$service = new OperationalHealthService(
    $pdo,
    $root,
    rtrim((string)configEnv('LTECO_BACKUP_DIR', '/opt/backups/ltecobike'), '/') . '/backup-status.json',
    $stateDir . '/ecommerce-heartbeat.json',
    $stateDir,
    max(60, (int)configEnv('LTECO_OPERATIONAL_HEARTBEAT_MAX_AGE_SECONDS', 180)),
    max(3600, (int)configEnv('LTECO_OPERATIONAL_BACKUP_MAX_AGE_SECONDS', 93600)),
    max(1, min(50, (int)configEnv('LTECO_OPERATIONAL_DISK_MIN_FREE_PERCENT', 10))),
    max(300, (int)configEnv('LTECO_OPERATIONAL_ALERT_COOLDOWN_SECONDS', 3600)),
    (string)configEnv('LTECO_OPERATIONAL_STOREFRONT_HEALTH_URL', 'http://web/public-web/')
);

if (in_array('--record-heartbeat', $argv, true)) {
    $service->recordHeartbeat('ecommerce_worker');
    echo "heartbeat: OK\n";
    exit(0);
}

$result = $service->evaluate();

if (in_array('--alert', $argv, true)) {
    $service->alertOnTransition($result, static function (string $message): bool {
        if (!telegramIsConfigured()) {
            return false;
        }
        $ok = false;
        foreach (telegramChatIds() as $chatId) {
            $sent = telegramSendMessage($chatId, $message);
            $ok = $ok || !empty($sent['ok']);
        }
        return $ok;
    });
}

foreach ($result['checks'] as $name => $check) {
    echo (empty($check['ok']) ? 'FAIL ' : 'OK ') . $name . ' ' . (string)($check['message'] ?? '') . PHP_EOL;
}

exit($result['ok'] ? 0 : 1);
