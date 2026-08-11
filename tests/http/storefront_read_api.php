<?php

declare(strict_types=1);

/** @return array<string,string> */
function testEnvironment(): array
{
    $values = [];
    $lines = file(dirname(__DIR__, 2) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), "\"'");
    }
    return $values;
}

$environment = testEnvironment();
$base = rtrim($environment['LTECO_PANEL_TEST_BASE_URL'] ?? 'http://127.0.0.1:8081', '/');
$secret = $environment['LTECO_STOREFRONT_API_CURRENT_SECRET'] ?? '';
$keyId = $environment['LTECO_STOREFRONT_API_CURRENT_KEY_ID'] ?? 'storefront-current';
if ($secret === '') {
    fwrite(STDERR, "Falta LTECO_STOREFRONT_API_CURRENT_SECRET.\n");
    exit(1);
}

/** @return array{status:int,headers:array<string,string>,json:array<string,mixed>|null} */
function apiRequest(string $base, string $secret, string $keyId, string $path, string $nonce, ?string $signature = null, ?string $etag = null, ?int $timestamp = null, string $method = 'GET', string $body = '', ?string $idempotencyKey = null): array
{
    $timestamp ??= time();
    $signature ??= hash_hmac('sha256', "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body), $secret);
    $headers = [
        'X-Forwarded-Proto: https',
        'X-Lteco-Key-Id: ' . $keyId,
        'X-Lteco-Timestamp: ' . $timestamp,
        'X-Lteco-Nonce: ' . $nonce,
        'X-Lteco-Signature: ' . $signature,
        'X-Correlation-Id: 3b90852d-0715-4ef5-84b6-b58e9f8544be',
        'Accept: application/json',
    ];
    if ($body !== '') $headers[] = 'Content-Type: application/json';
    if ($idempotencyKey !== null) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    if ($etag !== null) $headers[] = 'If-None-Match: ' . $etag;
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true,
    ]]);
    $body = file_get_contents($base . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    $parsedHeaders = [];
    foreach ($responseHeaders as $line) {
        if (!str_contains($line, ':')) continue;
        [$name, $value] = explode(':', $line, 2);
        $parsedHeaders[strtolower(trim($name))] = trim($value);
    }
    return [
        'status' => (int) ($statusMatch[1] ?? 0),
        'headers' => $parsedHeaders,
        'json' => $body !== false && $body !== '' ? json_decode($body, true) : null,
    ];
}

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};

$catalogPath = '/lteco-panel/api/storefront/v1/catalog';
$catalog = apiRequest($base, $secret, $keyId, $catalogPath, 'catalog-' . bin2hex(random_bytes(8)));
$check($catalog['status'] === 200, 'catálogo responde 200');
$check(isset($catalog['json']['data']) && is_array($catalog['json']['data']), 'catálogo usa sobre data');
$check(!str_contains(json_encode($catalog['json']), 'IdVehiculo'), 'catálogo no expone IdVehiculo');
$check(isset($catalog['headers']['etag']), 'catálogo devuelve ETag');

$etag = $catalog['headers']['etag'] ?? null;
$cached = apiRequest($base, $secret, $keyId, $catalogPath, 'etag-' . bin2hex(random_bytes(8)), null, $etag);
$check($cached['status'] === 304, 'If-None-Match responde 304');

$replayNonce = 'replay-' . bin2hex(random_bytes(8));
$timestamp = time();
$replaySignature = hash_hmac('sha256', "GET\n{$catalogPath}\n{$timestamp}\n{$replayNonce}\n" . hash('sha256', ''), $secret);
// Las dos llamadas ocurren en el mismo segundo; apiRequest usa el mismo timestamp canónico.
$first = apiRequest($base, $secret, $keyId, $catalogPath, $replayNonce, $replaySignature, null, $timestamp);
$second = apiRequest($base, $secret, $keyId, $catalogPath, $replayNonce, $replaySignature, null, $timestamp);
$check($first['status'] === 200, 'primera solicitud con nonce responde 200');
$check($second['status'] === 409 && ($second['json']['error']['code'] ?? '') === 'replay_detected', 'replay responde 409');

$bad = apiRequest($base, $secret, $keyId, $catalogPath, 'bad-' . bin2hex(random_bytes(8)), str_repeat('0', 64));
$check($bad['status'] === 401 && ($bad['json']['error']['code'] ?? '') === 'invalid_signature', 'firma inválida responde 401');

$termsPath = '/lteco-panel/api/storefront/v1/commercial-terms';
$terms = apiRequest($base, $secret, $keyId, $termsPath, 'terms-' . bin2hex(random_bytes(8)));
$check($terms['status'] === 200, 'términos responde 200');
$check(($terms['json']['data']['currency'] ?? '') === 'UYU', 'términos usa UYU');
$check(preg_match('/^\d+\.\d{2}$/', (string) ($terms['json']['data']['cash_discount_pct'] ?? '')) === 1, 'términos devuelve descuento decimal configurable');

function testUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/** @return CurlHandle */
function concurrentReservationHandle(string $base, string $secret, string $keyId, string $path, string $body, string $idempotencyKey): CurlHandle
{
    $timestamp = time();
    $nonce = testUuid();
    $signature = hash_hmac('sha256', "POST\n{$path}\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body), $secret);
    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'X-Forwarded-Proto: https',
            'X-Lteco-Key-Id: ' . $keyId,
            'X-Lteco-Timestamp: ' . $timestamp,
            'X-Lteco-Nonce: ' . $nonce,
            'X-Lteco-Signature: ' . $signature,
            'X-Correlation-Id: ' . testUuid(),
            'Idempotency-Key: ' . $idempotencyKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    return $handle;
}

$variantId = (string) ($catalog['json']['data'][0]['variant_id'] ?? '');
if ($variantId !== '') {
    $reservationPath = '/lteco-panel/api/storefront/v1/reservations';
    $reservationBody = json_encode([
        'order_uuid' => testUuid(),
        'variant_ids' => [$variantId],
        'payment_method' => 'cash',
        'ttl_seconds' => 86400,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $reservationKey = testUuid();
    $reservation = apiRequest($base, $secret, $keyId, $reservationPath, testUuid(), null, null, null, 'POST', $reservationBody, $reservationKey);
    $reservationError = (string) ($reservation['json']['error']['code'] ?? 'sin-código');
    $check($reservation['status'] === 201, 'reserva responde 201 (HTTP ' . $reservation['status'] . ', ' . $reservationError . ')');
    $check(($reservation['json']['data']['status'] ?? '') === 'active', 'reserva queda activa (' . $reservationError . ')');
    $check((float) ($reservation['json']['data']['total'] ?? 0) <= (float) ($reservation['json']['data']['subtotal'] ?? 0), 'efectivo aplica descuento configurable');
    $repeat = apiRequest($base, $secret, $keyId, $reservationPath, testUuid(), null, null, null, 'POST', $reservationBody, $reservationKey);
    $check($repeat['status'] === 201 && $repeat['json'] === $reservation['json'], 'reserva repetida devuelve la misma respuesta (HTTP ' . $repeat['status'] . ')');

    $reservationId = (string) ($reservation['json']['data']['reservation_id'] ?? '');
    if ($reservationId !== '') {
        $orderPath = '/lteco-panel/api/storefront/v1/orders';
        $orderBody = json_encode([
            'order_uuid' => $reservation['json']['data']['order_uuid'],
            'reservation_id' => $reservationId,
            'customer' => [
                'first_name' => 'Prueba', 'last_name' => 'HTTP',
                'email' => 'prueba-http@example.test', 'phone' => '092000086',
                'cedula' => '52248878', 'customer_type' => 'consumer',
            ],
            'billing_address' => [
                'line1' => 'Dirección de prueba 1234', 'city' => 'Montevideo',
                'department' => 'Montevideo', 'postal_code' => '11300',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $orderKey = testUuid();
        $panelOrder = apiRequest($base, $secret, $keyId, $orderPath, testUuid(), null, null, null, 'POST', $orderBody, $orderKey);
        $check($panelOrder['status'] === 201 && (int) ($panelOrder['json']['data']['panel_order_id'] ?? 0) > 0, 'pedido web se registra en el panel');
        $panelOrderAgain = apiRequest($base, $secret, $keyId, $orderPath, testUuid(), null, null, null, 'POST', $orderBody, $orderKey);
        $check($panelOrderAgain['status'] === 201 && $panelOrderAgain['json'] === $panelOrder['json'], 'registro del pedido en panel es idempotente');
        $statusPath = $orderPath . '/' . $reservation['json']['data']['order_uuid'];
        $orderStatus = apiRequest($base, $secret, $keyId, $statusPath, testUuid());
        $check($orderStatus['status'] === 200 && ($orderStatus['json']['data']['status'] ?? '') === 'PagoEnRevision', 'estado del pedido vuelve del panel a la cuenta web');

        $releasePath = $reservationPath . '/' . $reservationId;
        $releaseKey = testUuid();
        $released = apiRequest($base, $secret, $keyId, $releasePath, testUuid(), null, null, null, 'DELETE', '', $releaseKey);
        $check($released['status'] === 200 && ($released['json']['data']['status'] ?? '') === 'released', 'liberación responde 200');
        $releasedAgain = apiRequest($base, $secret, $keyId, $releasePath, testUuid(), null, null, null, 'DELETE', '', $releaseKey);
        $check($releasedAgain['status'] === 200 && $releasedAgain['json'] === $released['json'], 'liberación repetida es idempotente');
    }

    // Dos clientes intentan reservar la misma unidad al mismo tiempo. El bloqueo
    // transaccional del panel debe conceder exactamente una de las solicitudes.
    $concurrentBodies = [];
    $handles = [];
    $multi = curl_multi_init();
    for ($i = 0; $i < 2; $i++) {
        $concurrentBodies[$i] = json_encode([
            'order_uuid' => testUuid(),
            'variant_ids' => [$variantId],
            'payment_method' => 'cash',
            'ttl_seconds' => 86400,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $handles[$i] = concurrentReservationHandle(
            $base, $secret, $keyId, $reservationPath, $concurrentBodies[$i], testUuid(),
        );
        curl_multi_add_handle($multi, $handles[$i]);
    }
    do {
        $result = curl_multi_exec($multi, $running);
        if ($running > 0) curl_multi_select($multi, 1.0);
    } while ($running > 0 && $result === CURLM_OK);

    $concurrent = [];
    foreach ($handles as $handle) {
        $concurrent[] = [
            'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'json' => json_decode((string) curl_multi_getcontent($handle), true),
        ];
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);
    $statuses = array_column($concurrent, 'status');
    sort($statuses);
    $check($statuses === [201, 409], 'concurrencia concede una sola reserva (' . implode(', ', $statuses) . ')');

    foreach ($concurrent as $response) {
        $winnerId = (string) ($response['json']['data']['reservation_id'] ?? '');
        if ($response['status'] === 201 && $winnerId !== '') {
            $cleanup = apiRequest(
                $base, $secret, $keyId, $reservationPath . '/' . $winnerId,
                testUuid(), null, null, null, 'DELETE', '', testUuid(),
            );
            $check($cleanup['status'] === 200, 'limpieza de reserva concurrente responde 200');
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "FALLÓ: {$failure}\n");
    exit(1);
}
echo "OK — API HTTP de lectura verificada.\n";
