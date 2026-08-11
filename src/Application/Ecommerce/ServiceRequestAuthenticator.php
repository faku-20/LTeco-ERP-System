<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

final class ServiceRequestAuthenticator
{
    /** @param array<string,array{secret:string,scopes:list<string>}> $keys */
    public function __construct(
        private ServiceNonceStore $nonces,
        private array $keys,
        private int $clockSkewSeconds = 300,
        private int $nonceTtlSeconds = 600,
        private int $requestsPerMinute = 120,
    ) {}

    /**
     * @param array<string,string> $headers lowercase names
     * @return array{key_id:string,correlation_id:string}
     */
    public function authenticate(
        string $method,
        string $pathWithQuery,
        string $rawBody,
        array $headers,
        string $requiredScope,
        ?int $nowUnix = null,
    ): array {
        if (strlen($rawBody) > 262144) {
            throw new StorefrontApiException(413, 'body_too_large', 'El cuerpo supera el límite permitido.');
        }

        $keyId = trim($headers['x-lteco-key-id'] ?? '');
        $timestamp = trim($headers['x-lteco-timestamp'] ?? '');
        $nonce = trim($headers['x-lteco-nonce'] ?? '');
        $signature = strtolower(trim($headers['x-lteco-signature'] ?? ''));
        $correlationId = trim($headers['x-correlation-id'] ?? '');

        if ($keyId === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            throw new StorefrontApiException(401, 'authentication_required', 'Faltan credenciales de servicio.');
        }
        if (!isset($this->keys[$keyId]) || $this->keys[$keyId]['secret'] === '') {
            throw new StorefrontApiException(401, 'invalid_credentials', 'Credenciales de servicio inválidas.');
        }
        if (!ctype_digit($timestamp)) {
            throw new StorefrontApiException(401, 'invalid_timestamp', 'Timestamp de servicio inválido.');
        }

        $nowUnix ??= time();
        $timestampUnix = (int) $timestamp;
        if (abs($nowUnix - $timestampUnix) > $this->clockSkewSeconds) {
            throw new StorefrontApiException(401, 'expired_request', 'La solicitud firmada venció.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $signature) !== 1 || strlen($nonce) > 128) {
            throw new StorefrontApiException(401, 'invalid_credentials', 'Credenciales de servicio inválidas.');
        }

        $canonical = strtoupper($method) . "\n"
            . $pathWithQuery . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $rawBody);
        $expected = hash_hmac('sha256', $canonical, $this->keys[$keyId]['secret']);
        if (!hash_equals($expected, $signature)) {
            throw new StorefrontApiException(401, 'invalid_signature', 'Firma de servicio inválida.');
        }
        if (!in_array($requiredScope, $this->keys[$keyId]['scopes'], true)) {
            throw new StorefrontApiException(403, 'insufficient_scope', 'La credencial no permite esta operación.');
        }

        // La expiración no forma parte del camino crítico de cada request. Una
        // limpieza oportunista evita que 20 lecturas concurrentes compitan por
        // el mismo DELETE; la PK sigue bloqueando cualquier replay.
        if (random_int(1, 100) === 1) {
            $this->nonces->purgeExpired($nowUnix);
        }
        $windowStart = intdiv($nowUnix, 60) * 60;
        if (!$this->nonces->consumeRateSlot($keyId, $windowStart, $this->requestsPerMinute)) {
            throw new StorefrontApiException(429, 'rate_limited', 'Se superó el límite de solicitudes.', true);
        }
        if (!$this->nonces->remember($keyId, $nonce, $nowUnix + $this->nonceTtlSeconds)) {
            throw new StorefrontApiException(409, 'replay_detected', 'La solicitud ya fue procesada.');
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $correlationId) !== 1) {
            $correlationId = self::uuidV4();
        }

        return ['key_id' => $keyId, 'correlation_id' => strtolower($correlationId)];
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
