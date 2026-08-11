<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PanelApiClient;
use App\Services\ServiceRequestSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class PanelApiClientTest extends TestCase
{
    public function test_catalog_uses_signed_panel_endpoint(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $response = (new PanelApiClient(new ServiceRequestSigner()))->catalog('W/"catalog-v1"');

        $this->assertTrue($response->successful());
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://panel.example.test/api/storefront/v1/catalog'
                && $request->hasHeader('X-Lteco-Key-Id', 'storefront-current')
                && $request->hasHeader('X-Lteco-Signature')
                && $request->hasHeader('X-Lteco-Nonce')
                && $request->hasHeader('If-None-Match', 'W/"catalog-v1"');
        });
    }

    public function test_get_retries_server_error_with_a_fresh_nonce(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        Http::fakeSequence()->push(['error' => ['code' => 'temporary']], 503)->push(['data' => []], 200);

        $response = (new PanelApiClient(new ServiceRequestSigner()))->commercialTerms();

        $this->assertSame(200, $response->status());
        $nonces = [];
        Http::assertSent(function (Request $request) use (&$nonces): bool {
            $nonces[] = $request->header('X-Lteco-Nonce')[0] ?? '';
            return true;
        });
        $this->assertCount(2, $nonces);
        $this->assertNotSame($nonces[0], $nonces[1]);
    }

    public function test_plain_http_requires_an_explicit_local_override(): void
    {
        config()->set('storefront_api.panel.base_url', 'http://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.secret', 'test-secret');
        config()->set('storefront_api.panel.allow_insecure', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe usar HTTPS');

        (new PanelApiClient(new ServiceRequestSigner()))->catalog();
    }

    public function test_cash_reservation_during_business_hours_is_signed_with_four_hour_ttl(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-29 15:00:00', 'America/Montevideo'));
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        Http::fake(['*' => Http::response(['data' => ['status' => 'active']], 201)]);

        $response = (new PanelApiClient(new ServiceRequestSigner()))->createReservation(
            'de9859c7-7e4c-4d24-a78f-2640bda7a38f',
            [str_repeat('a', 64)],
            'cash',
            'a7c19e0c-1a42-4b7f-b63c-ddef241196b6',
        );

        $this->assertSame(201, $response->status());
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://panel.example.test/api/storefront/v1/reservations'
                && $request->hasHeader('Idempotency-Key', 'a7c19e0c-1a42-4b7f-b63c-ddef241196b6')
                && $request['payment_method'] === 'cash'
                && $request['ttl_seconds'] === 14400;
        });
        Carbon::setTestNow();
    }

    public function test_cash_reservation_after_hours_is_signed_until_noon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-29 02:00:00', 'America/Montevideo'));
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        Http::fake(['*' => Http::response(['data' => ['status' => 'active']], 201)]);

        (new PanelApiClient(new ServiceRequestSigner()))->createReservation(
            'de9859c7-7e4c-4d24-a78f-2640bda7a38f',
            [str_repeat('a', 64)],
            'cash',
            'a7c19e0c-1a42-4b7f-b63c-ddef241196b6',
        );

        Http::assertSent(fn (Request $request): bool => $request['ttl_seconds'] === 36000);
        Carbon::setTestNow();
    }
}
