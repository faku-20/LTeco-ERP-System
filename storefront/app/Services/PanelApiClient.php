<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class PanelApiClient
{
    public function __construct(
        private readonly ServiceRequestSigner $signer,
    ) {
    }

    public function get(string $path, array $query = [], ?string $etag = null): Response
    {
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $pathWithQuery = $path . ($queryString !== '' ? '?' . $queryString : '');
        $lastResponse = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $request = $this->request('GET', $this->canonicalPath($pathWithQuery), '');
                if ($etag !== null && trim($etag) !== '') $request = $request->withHeaders(['If-None-Match' => trim($etag)]);
                $lastResponse = $request->get($path, $query);
                if ($lastResponse->status() < 500 || $attempt === 2) return $lastResponse;
            } catch (ConnectionException $e) {
                if ($attempt === 2) throw $e;
            }
            usleep(($attempt === 0 ? 200 : 500) * 1000 + random_int(0, 50) * 1000);
        }
        throw new RuntimeException('No se obtuvo respuesta del panel.');
    }

    public function catalog(?string $etag = null): Response { return $this->get('/catalog', [], $etag); }

    public function commercialTerms(?string $etag = null): Response { return $this->get('/commercial-terms', [], $etag); }

    /** @param list<string> $variantIds */
    public function createReservation(string $orderUuid, array $variantIds, string $paymentMethod, string $idempotencyKey): Response
    {
        return $this->post('/reservations', [
            'order_uuid' => $orderUuid,
            'variant_ids' => array_values($variantIds),
            'payment_method' => $paymentMethod,
            'ttl_seconds' => $this->reservationTtlSeconds($paymentMethod),
        ], $idempotencyKey);
    }

    private function reservationTtlSeconds(string $paymentMethod): int
    {
        if ($paymentMethod !== 'cash') {
            return 1800;
        }

        $now = Carbon::now('America/Montevideo');
        $dayStart = $now->copy()->setTime(9, 0);
        $dayEnd = $now->copy()->setTime(20, 0);
        if ($now->greaterThanOrEqualTo($dayStart) && $now->lessThan($dayEnd)) {
            return 4 * 60 * 60;
        }

        $deadline = $now->copy()->setTime(12, 0);
        if ($now->greaterThanOrEqualTo($dayEnd)) {
            $deadline->addDay();
        }
        if ($now->greaterThanOrEqualTo($deadline)) {
            $deadline->addDay();
        }

        return (int) max(4 * 60 * 60, $now->diffInSeconds($deadline));
    }

    public function releaseReservation(string $reservationId, string $idempotencyKey): Response
    {
        return $this->delete('/reservations/' . rawurlencode($reservationId), $idempotencyKey);
    }

    /** @param array<string,mixed> $payload */
    public function createOrder(array $payload, string $idempotencyKey): Response
    {
        return $this->post('/orders', $payload, $idempotencyKey);
    }

    public function orderStatus(string $orderUuid): Response
    {
        return $this->get('/orders/'.rawurlencode($orderUuid));
    }

    public function simulateCardPayment(string $orderUuid, string $idempotencyKey): Response
    {
        return $this->post('/orders/'.rawurlencode($orderUuid).'/simulate-payment', [], $idempotencyKey);
    }

    /** @param array<string,mixed> $payload */
    public function createPrivacyRequest(array$payload,string$idempotencyKey):Response
    {
        return $this->post('/privacy-requests',$payload,$idempotencyKey);
    }

    public function privacyRequestStatus(string$uuid):Response
    {
        return$this->get('/privacy-requests/'.rawurlencode($uuid));
    }

    /** @param array<string,mixed> $payload */
    public function createVisit(array$payload,string$idempotencyKey):Response
    {
        return$this->post('/visits',$payload,$idempotencyKey);
    }

    public function post(string $path, array $payload, string $idempotencyKey): Response
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $this->canonicalPath($path), $body, $idempotencyKey)
            ->withBody($body, 'application/json')
            ->post($path);
    }

    public function delete(string $path, string $idempotencyKey): Response
    {
        return $this->request('DELETE', $this->canonicalPath($path), '', $idempotencyKey)->delete($path);
    }

    private function request(
        string $method,
        string $pathWithQuery,
        string $body,
        ?string $idempotencyKey = null,
    ): PendingRequest {
        $secret = (string) config('storefront_api.panel.secret');
        $baseUrl = rtrim((string) config('storefront_api.panel.base_url'), '/');

        if ($secret === '') {
            throw new RuntimeException('PANEL_API_SECRET no está configurado.');
        }
        if (strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) !== 'https'
            && ! (bool) config('storefront_api.panel.allow_insecure', false)) {
            throw new RuntimeException('PANEL_API_BASE_URL debe usar HTTPS fuera del desarrollo local.');
        }

        $timestamp = time();
        $nonce = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        $headers = [
            'X-Lteco-Key-Id' => (string) config('storefront_api.panel.key_id'),
            'X-Lteco-Timestamp' => (string) $timestamp,
            'X-Lteco-Nonce' => $nonce,
            'X-Lteco-Signature' => $this->signer->signature(
                $secret,
                $method,
                $pathWithQuery,
                $timestamp,
                $nonce,
                $body,
            ),
            'X-Correlation-Id' => $correlationId,
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $forwardedProto = trim((string) config('storefront_api.panel.forwarded_proto'));
        if ($forwardedProto !== '') {
            $headers['X-Forwarded-Proto'] = $forwardedProto;
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders($headers)
            ->connectTimeout((int) config('storefront_api.connect_timeout_seconds', 2))
            ->timeout((int) config('storefront_api.timeout_seconds', 5));
    }

    private function canonicalPath(string $relativePathWithQuery): string
    {
        $basePath = (string) parse_url(
            (string) config('storefront_api.panel.base_url'),
            PHP_URL_PATH,
        );

        return rtrim($basePath, '/') . '/' . ltrim($relativePathWithQuery, '/');
    }
}
