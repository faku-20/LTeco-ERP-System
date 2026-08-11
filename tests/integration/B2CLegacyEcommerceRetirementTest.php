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
    fwrite(STDERR, 'B2CLegacyEcommerceRetirementTest aborted: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

$count = static function (PDO $pdo, string $table): int {
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
};

$before = [
    'ecommerce_pedido' => $count($pdo, 'ecommerce_pedido'),
    'ecommerce_pago' => $count($pdo, 'ecommerce_pago'),
    'ecommerce_ocupacion_unidad' => $count($pdo, 'ecommerce_ocupacion_unidad'),
    'storefront_reservation' => $count($pdo, 'storefront_reservation'),
    'venta' => $count($pdo, 'venta'),
];

$port = 18400 + random_int(0, 300);
$env = array_merge($_ENV, [
    'LTECO_ENV' => 'testing',
    'LTECO_TEST_DB_ALLOW' => '1',
    'LTECO_DB_HOST' => (string) getenv('LTECO_TEST_DB_HOST'),
    'LTECO_DB_NAME' => (string) getenv('LTECO_TEST_DB_NAME'),
    'LTECO_DB_USER' => (string) getenv('LTECO_TEST_DB_USER'),
    'LTECO_DB_PASS' => (string) getenv('LTECO_TEST_DB_PASSWORD'),
    'LTECO_FORCE_HTTPS' => '0',
    'LTECO_PUBLIC_BASE_URL' => '/public-web',
    'LTECO_STOREFRONT_PUBLIC_URL' => 'https://storefront.example.test',
]);

$cmd = sprintf('php -S 127.0.0.1:%d -t /var/www/html', $port);
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$server = proc_open($cmd, $descriptors, $pipes, null, $env);
if (!is_resource($server)) {
    fwrite(STDERR, 'No se pudo iniciar PHP built-in server para B2C.' . PHP_EOL);
    exit(2);
}
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$request = static function (string $method, string $path) use ($port): array {
    $handle = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $method === 'POST' ? 'accion=legacy&vehiculo=B2C-TEST' : null,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => ['Accept: text/plain'],
    ]);
    $raw = curl_exec($handle);
    if ($raw === false) {
        $error = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException('curl fallo contra endpoint B2C: ' . $error);
    }
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    return [
        'status' => $status,
        'headers' => substr((string) $raw, 0, $headerSize),
        'body' => substr((string) $raw, $headerSize),
    ];
};

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
        throw new RuntimeException('El servidor local B2C no inició.');
    }

    $cartGet = $request('GET', '/public-web/carrito.php');
    $checkoutGet = $request('GET', '/public-web/checkout.php');
    $cartPost = $request('POST', '/public-web/carrito.php');
    $checkoutPost = $request('POST', '/public-web/checkout.php');
    $webhookPost = $request('POST', '/public-web/webhook-mercadopago.php');

    $check('GET carrito redirige al Storefront', $cartGet['status'] === 302 && str_contains($cartGet['headers'], 'Location: https://storefront.example.test/carrito'));
    $check('GET checkout redirige al Storefront', $checkoutGet['status'] === 302 && str_contains($checkoutGet['headers'], 'Location: https://storefront.example.test/comprar'));
    $check('POST carrito legacy devuelve 410', $cartPost['status'] === 410 && str_contains($cartPost['body'], 'legacy fue retirada'));
    $check('POST checkout legacy devuelve 410', $checkoutPost['status'] === 410 && str_contains($checkoutPost['body'], 'legacy fue retirada'));
    $check('POST webhook MercadoPago legacy devuelve 410', $webhookPost['status'] === 410 && str_contains($webhookPost['body'], 'legacy_mercadopago_webhook_retired'));

    $after = [
        'ecommerce_pedido' => $count($pdo, 'ecommerce_pedido'),
        'ecommerce_pago' => $count($pdo, 'ecommerce_pago'),
        'ecommerce_ocupacion_unidad' => $count($pdo, 'ecommerce_ocupacion_unidad'),
        'storefront_reservation' => $count($pdo, 'storefront_reservation'),
        'venta' => $count($pdo, 'venta'),
    ];
    $check('endpoints legacy retirados no mutan tablas comerciales', $before === $after);
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

echo "B2CLegacyEcommerceRetirementTest OK ({$ok} assertions)\n";
