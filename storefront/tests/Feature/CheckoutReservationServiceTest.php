<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutReservationService;
use App\Services\PanelApiClient;
use App\Services\ServiceRequestSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CheckoutReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_order_persists_the_physical_unit_and_pickup_coordination(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        $variant = str_repeat('a', 64);
        $user = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'status' => 'reservation_pending',
            'payment_method' => 'cash',
        ]);
        Http::fake(['*' => Http::response(['data' => [
            'reservation_id' => '8e13db28-92d9-46d0-ad51-998fd505f6e4',
            'order_uuid' => $order->public_uuid,
            'status' => 'active',
            'payment_method' => 'cash',
            'expires_at' => now()->addDay()->toISOString(),
            'currency' => 'UYU',
            'subtotal' => '67000.00',
            'discount' => '3350.00',
            'total' => '63650.00',
            'items' => [[
                'vehicle_id' => 'V0230', 'product_id' => 85, 'variant_id' => $variant,
                'model' => 'Q8-500W', 'battery_ah' => 20, 'color' => 'Beige',
                'gross' => '67000.00', 'vat_rate' => '22.00',
            ]],
        ]], 201)]);

        $reserved = (new CheckoutReservationService(new PanelApiClient(new ServiceRequestSigner())))
            ->reserve($order, [$variant]);

        self::assertSame('pickup_coordination_pending', $reserved->status);
        self::assertSame('63650.00', $reserved->total);
        self::assertSame('V0230', $reserved->items->first()->vehicle_id);
        self::assertSame('pending_contact', $reserved->pickupCoordinations->first()->status);
        self::assertDatabaseHas('outbox_events', ['aggregate_uuid' => $order->public_uuid, 'status' => 'processed']);
    }
}
