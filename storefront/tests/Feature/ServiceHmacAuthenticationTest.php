<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ServiceRequestSigner;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ServiceHmacAuthenticationTest extends TestCase
{
    private const SECRET = 'local-test-secret-with-enough-entropy';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('storefront_api.incoming.key_id', 'panel-test');
        config()->set('storefront_api.incoming.secret', self::SECRET);
        config()->set('storefront_api.incoming.previous_key_id', null);
        config()->set('storefront_api.incoming.previous_secret', null);
    }

    public function test_valid_signed_request_reaches_internal_api(): void
    {
        $headers = $this->signedHeaders('GET', '/internal/v1/ping', '');

        $this->withHeaders([...$headers, 'Accept' => 'application/json'])
            ->get('/internal/v1/ping')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertHeader('X-Correlation-Id', $headers['X-Correlation-Id']);
    }

    public function test_unsigned_request_is_rejected(): void
    {
        $this->getJson('/internal/v1/ping')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unknown_service_key');
    }

    public function test_repeated_nonce_is_rejected(): void
    {
        $headers = $this->signedHeaders('GET', '/internal/v1/ping', '');

        $this->withHeaders([...$headers, 'Accept' => 'application/json'])
            ->get('/internal/v1/ping')
            ->assertOk();

        $this->withHeaders([...$headers, 'Accept' => 'application/json'])
            ->get('/internal/v1/ping')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'replay_detected');
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $headers = $this->signedHeaders(
            'GET',
            '/internal/v1/ping',
            '',
            time() - 301,
        );

        $this->withHeaders([...$headers, 'Accept' => 'application/json'])
            ->get('/internal/v1/ping')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'expired_service_timestamp');
    }

    public function test_body_tampering_is_rejected(): void
    {
        $headers = $this->signedHeaders('POST', '/internal/v1/panel-events', '{"a":1}');

        $this->withHeaders($headers)
            ->postJson('/internal/v1/panel-events', ['a' => 2])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_service_signature');
    }

    private function signedHeaders(
        string $method,
        string $path,
        string $body,
        ?int $timestamp = null,
    ): array {
        $timestamp ??= time();
        $nonce = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        $signature = app(ServiceRequestSigner::class)->signature(
            self::SECRET,
            $method,
            $path,
            $timestamp,
            $nonce,
            $body,
        );

        return [
            'X-Lteco-Key-Id' => 'panel-test',
            'X-Lteco-Timestamp' => (string) $timestamp,
            'X-Lteco-Nonce' => $nonce,
            'X-Lteco-Signature' => $signature,
            'X-Correlation-Id' => $correlationId,
        ];
    }
}
