<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\ServiceRequestAuthenticator;
use Lteco\Application\Ecommerce\StorefrontApiException;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\StorefrontApiNonceRepository;

define('LTECO_DB_THROW_ON_CONNECT', true);
$storefrontApiBootstrapError = null;
try {
    require_once dirname(__DIR__, 2) . '/shared/db.php';
} catch (Throwable $e) {
    $storefrontApiBootstrapError = $e;
}

/** @return array<string,string> */
function storefrontApiHeaders(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (!str_starts_with($key, 'HTTP_')) continue;
        $name = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$name] = trim((string) $value);
    }
    if (isset($_SERVER['CONTENT_TYPE'])) $headers['content-type'] = trim((string) $_SERVER['CONTENT_TYPE']);
    return $headers;
}

/** @return array<string,array{secret:string,scopes:list<string>}> */
function storefrontApiKeys(): array
{
    $keys = [];
    $currentId = trim((string) configEnv('LTECO_STOREFRONT_API_CURRENT_KEY_ID', 'storefront-current'));
    $currentSecret = (string) configEnv('LTECO_STOREFRONT_API_CURRENT_SECRET', '');
    if ($currentId !== '' && $currentSecret !== '') {
        $keys[$currentId] = ['secret' => $currentSecret, 'scopes' => ['storefront.catalog.read', 'storefront.reservations.write', 'storefront.orders.write', 'storefront.orders.read','storefront.payments.write','storefront.privacy.write','storefront.privacy.read','storefront.visits.write']];
    }
    $previousId = trim((string) configEnv('LTECO_STOREFRONT_API_PREVIOUS_KEY_ID', ''));
    $previousSecret = (string) configEnv('LTECO_STOREFRONT_API_PREVIOUS_SECRET', '');
    if ($previousId !== '' && $previousSecret !== '') {
        $keys[$previousId] = ['secret' => $previousSecret, 'scopes' => ['storefront.catalog.read', 'storefront.reservations.write', 'storefront.orders.write', 'storefront.orders.read','storefront.payments.write','storefront.privacy.write','storefront.privacy.read','storefront.visits.write']];
    }
    return $keys;
}

function storefrontApiCorrelationId(): string
{
    return $GLOBALS['storefront_api_correlation_id'] ?? '00000000-0000-4000-8000-000000000000';
}

function storefrontApiPrepareCorrelationId(): void
{
    $candidate = trim((string) ($_SERVER['HTTP_X_CORRELATION_ID'] ?? ''));
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $candidate) !== 1) {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $candidate = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
    $GLOBALS['storefront_api_correlation_id'] = strtolower($candidate);
}

function storefrontApiEtagMatches(string $header, string $etag): bool
{
    foreach (explode(',', $header) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '*') return true;
        if (str_starts_with($candidate, 'W/')) $candidate = substr($candidate, 2);
        if ($candidate === '"' . $etag . '"') return true;
    }
    return false;
}

/** @param array<string,mixed> $payload */
function storefrontApiJson(array $payload, int $status = 200, ?string $etag = null): never
{
    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($etag !== null) {
        $quoted = 'W/"' . $etag . '"';
        header('ETag: ' . $quoted);
        header('Cache-Control: private, max-age=30');
        if (storefrontApiEtagMatches((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $etag)) {
            http_response_code(304);
            exit;
        }
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Correlation-Id: ' . storefrontApiCorrelationId());
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

/** @return array{payload:array<string,mixed>,raw:string,hash:string} */
function storefrontApiJsonBody(): array
{
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        throw new StorefrontApiException(400, 'invalid_content_type', 'Se requiere Content-Type application/json.');
    }
    $raw = isset($GLOBALS['storefront_api_raw_body'])
        ? (string) $GLOBALS['storefront_api_raw_body']
        : (string) file_get_contents('php://input');
    try {
        $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new StorefrontApiException(400, 'invalid_json', 'El cuerpo JSON no es válido.');
    }
    if (!is_array($payload) || array_is_list($payload)) {
        throw new StorefrontApiException(400, 'invalid_json', 'El cuerpo JSON debe ser un objeto.');
    }
    return ['payload' => $payload, 'raw' => $raw, 'hash' => hash('sha256', $raw)];
}

function storefrontApiIdempotencyKey(): string
{
    $key = strtolower(trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')));
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $key) !== 1) {
        throw new StorefrontApiException(400, 'idempotency_key_required', 'Se requiere una Idempotency-Key UUID válida.');
    }
    return $key;
}

function storefrontPaymentSimulatorEnabled(): bool
{
    $enabled = strtolower(trim((string) configEnv('LTECO_STOREFRONT_PAYMENT_SIMULATOR_ENABLED', '')));

    return in_array($enabled, ['1', 'true', 'yes', 'on'], true)
        && !appIsProduction();
}

function storefrontApiRun(string $requiredScope, callable $handler, array $allowedMethods = ['GET']): never
{
    global $pdo, $storefrontApiBootstrapError;
    storefrontApiPrepareCorrelationId();
    try {
        if ($storefrontApiBootstrapError instanceof Throwable) throw $storefrontApiBootstrapError;
        if (!in_array((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $allowedMethods, true)) {
            throw new StorefrontApiException(405, 'method_not_allowed', 'Método no permitido.');
        }
        $keys = storefrontApiKeys();
        if ($keys === []) {
            throw new StorefrontApiException(503, 'service_not_configured', 'La integración no está configurada.', true);
        }
        $auth = new ServiceRequestAuthenticator(
            new StorefrontApiNonceRepository(new Connection($pdo)),
            $keys,
            300,
            600,
            max(10, (int) configEnv('LTECO_STOREFRONT_API_RATE_PER_MINUTE', 120)),
        );
        $body = (string) file_get_contents('php://input');
        $GLOBALS['storefront_api_raw_body'] = $body;
        $identity = $auth->authenticate(
            (string) $_SERVER['REQUEST_METHOD'],
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $body,
            storefrontApiHeaders(),
            $requiredScope,
        );
        $GLOBALS['storefront_api_correlation_id'] = $identity['correlation_id'];
        $handler();
        throw new StorefrontApiException(500, 'empty_response', 'El endpoint no produjo una respuesta.', true);
    } catch (StorefrontApiException $e) {
        if ($e->status === 429) header('Retry-After: 60');
        storefrontApiJson(['error' => [
            'code' => $e->errorCode,
            'message' => $e->getMessage(),
            'correlation_id' => storefrontApiCorrelationId(),
            'retryable' => $e->retryable,
        ]], $e->status);
    } catch (Throwable $e) {
        error_log('[STOREFRONT_API] ' . get_class($e) . ': ' . $e->getMessage());
        storefrontApiJson(['error' => [
            'code' => 'service_unavailable',
            'message' => 'El servicio no está disponible temporalmente.',
            'correlation_id' => storefrontApiCorrelationId(),
            'retryable' => true,
        ]], 503);
    }
}
