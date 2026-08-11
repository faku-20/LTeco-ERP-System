<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ServiceRequestSigner;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class VerifyServiceHmac
{
    public function __construct(
        private readonly ServiceRequestSigner $signer,
        private readonly CacheRepository $cache,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $keyId = (string) $request->header('X-Lteco-Key-Id', '');
        $timestampRaw = (string) $request->header('X-Lteco-Timestamp', '');
        $nonce = (string) $request->header('X-Lteco-Nonce', '');
        $signature = (string) $request->header('X-Lteco-Signature', '');
        $correlationId = (string) $request->header('X-Correlation-Id', '');

        if (! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        $secret = $this->secretFor($keyId);

        if ($secret === null) {
            return $this->error('unknown_service_key', 401, $correlationId);
        }

        if (! ctype_digit($timestampRaw)) {
            return $this->error('invalid_service_timestamp', 401, $correlationId);
        }

        if (! Str::isUuid($nonce)) {
            return $this->error('invalid_service_nonce', 401, $correlationId);
        }

        if (preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            return $this->error('invalid_service_signature', 401, $correlationId);
        }

        $timestamp = (int) $timestampRaw;
        $tolerance = (int) config('storefront_api.timestamp_tolerance_seconds', 300);

        if (abs(time() - $timestamp) > $tolerance) {
            return $this->error('expired_service_timestamp', 401, $correlationId);
        }

        $rawBody = $request->getContent();
        $maxBody = (int) config('storefront_api.max_body_bytes', 262144);

        if (strlen($rawBody) > $maxBody) {
            return $this->error('request_body_too_large', 413, $correlationId);
        }

        $expected = $this->signer->signature(
            $secret,
            $request->getMethod(),
            $request->getRequestUri(),
            $timestamp,
            $nonce,
            $rawBody,
        );

        if (! $this->signer->verify($expected, $signature)) {
            return $this->error('invalid_service_signature', 401, $correlationId);
        }

        $nonceKey = 'storefront-api-nonce:' . $keyId . ':' . $nonce;
        $nonceTtl = (int) config('storefront_api.nonce_ttl_seconds', 600);

        if (! $this->cache->add($nonceKey, true, $nonceTtl)) {
            return $this->error('replay_detected', 409, $correlationId);
        }

        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('service_key_id', $keyId);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }

    private function secretFor(string $keyId): ?string
    {
        $pairs = [
            (string) config('storefront_api.incoming.key_id') => config('storefront_api.incoming.secret'),
            (string) config('storefront_api.incoming.previous_key_id') => config('storefront_api.incoming.previous_secret'),
        ];

        $secret = $pairs[$keyId] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function error(string $code, int $status, string $correlationId): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => 'La solicitud de servicio no pudo validarse.',
                'correlation_id' => $correlationId,
                'retryable' => false,
            ],
        ], $status);
    }
}
