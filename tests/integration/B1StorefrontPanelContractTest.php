<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/PanelTestDb.php';
require_once __DIR__ . '/../Support/PanelTestGuard.php';

$failures = [];
$ok = 0;
$check = static function (string $label, bool $condition) use (&$failures, &$ok): void {
    if ($condition) {
        $ok++;
        echo "  OK {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL {$label}\n";
};

try {
    $pdo = PanelTestDb::connect();
} catch (Throwable $e) {
    fwrite(STDERR, 'B1StorefrontPanelContractTest aborted: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$keyId = 'b1-contract';
$secret = str_repeat('b1-secret-', 8);
$port = 18100 + random_int(0, 300);
$basePath = '/lteco-panel/api/storefront/v1';
$uuid = '11111111-2222-4333-8444-555555555555';
$path = $basePath . '/order-simulated-payment.php?uuid=' . $uuid;
$nonce = bin2hex(random_bytes(16));
$timestamp = (string) time();
$body = '';
$signature = hash_hmac(
    'sha256',
    "POST\n{$path}\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body),
    $secret
);
$correlationId = '11111111-2222-4333-8444-555555555555';

$count = static function (PDO $pdo, string $table): int {
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
};

$before = [
    'venta' => $count($pdo, 'venta'),
    'venta_detalle' => $count($pdo, 'venta_detalle'),
    'ecommerce_pago' => $count($pdo, 'ecommerce_pago'),
    'ecommerce_auditoria' => $count($pdo, 'ecommerce_auditoria'),
];

$env = array_merge($_ENV, [
    'LTECO_ENV' => 'testing',
    'LTECO_TEST_DB_ALLOW' => '1',
    'LTECO_DB_HOST' => (string) getenv('LTECO_TEST_DB_HOST'),
    'LTECO_DB_NAME' => (string) getenv('LTECO_TEST_DB_NAME'),
    'LTECO_DB_USER' => (string) getenv('LTECO_TEST_DB_USER'),
    'LTECO_DB_PASS' => (string) getenv('LTECO_TEST_DB_PASSWORD'),
    'LTECO_FORCE_HTTPS' => '0',
    'LTECO_STOREFRONT_API_CURRENT_KEY_ID' => $keyId,
    'LTECO_STOREFRONT_API_CURRENT_SECRET' => $secret,
    'LTECO_STOREFRONT_PAYMENT_SIMULATOR_ENABLED' => '0',
]);

$cmd = sprintf('php -S 127.0.0.1:%d -t /var/www/html', $port);
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$server = proc_open($cmd, $descriptors, $pipes, null, $env);
if (!is_resource($server)) {
    fwrite(STDERR, 'No se pudo iniciar PHP built-in server para contrato B1.' . PHP_EOL);
    exit(2);
}
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

try {
    $ready = false;
    for ($i = 0; $i < 30; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($socket)) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('El servidor local de contrato B1 no inició.');
    }

    $handle = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Lteco-Key-Id: ' . $keyId,
            'X-Lteco-Timestamp: ' . $timestamp,
            'X-Lteco-Nonce: ' . $nonce,
            'X-Lteco-Signature: ' . $signature,
            'X-Correlation-Id: ' . $correlationId,
        ],
    ]);
    $response = curl_exec($handle);
    if ($response === false) {
        throw new RuntimeException('curl fallo contra endpoint local: ' . curl_error($handle));
    }
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $json = json_decode((string) $response, true);
    $check('B0 endpoint responde 403 con HMAC valida y simulator disabled', $status === 403);
    $check('B0 error code payment_simulator_disabled', is_array($json) && (($json['error']['code'] ?? '') === 'payment_simulator_disabled'));

    $after = [
        'venta' => $count($pdo, 'venta'),
        'venta_detalle' => $count($pdo, 'venta_detalle'),
        'ecommerce_pago' => $count($pdo, 'ecommerce_pago'),
        'ecommerce_auditoria' => $count($pdo, 'ecommerce_auditoria'),
    ];
    $check('B0 disabled no crea efectos comerciales', $before === $after);
    $check('B0 autentica mediante nonce real en DB test', (int) $pdo->query("SELECT COUNT(*) FROM storefront_api_nonce WHERE KeyId = " . $pdo->quote($keyId) . ' AND Nonce = ' . $pdo->quote($nonce))->fetchColumn() === 1);

    $pdo->prepare('DELETE FROM storefront_api_nonce WHERE KeyId = ?')->execute([$keyId]);
    $pdo->prepare('DELETE FROM storefront_api_rate_window WHERE KeyId = ?')->execute([$keyId]);
} catch (Throwable $e) {
    $failures[] = $e->getMessage();
    echo 'FAIL ' . $e->getMessage() . "\n";
} finally {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_terminate($server);
    proc_close($server);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "B1StorefrontPanelContractTest OK ({$ok} assertions)\n";
