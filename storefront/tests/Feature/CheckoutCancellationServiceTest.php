<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PickupCoordination;
use App\Models\User;
use App\Services\CheckoutCancellationService;
use App\Services\PanelApiClient;
use App\Services\ServiceRequestSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CheckoutCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
    }

    public function test_cancellation_releases_panel_reservation_and_closes_pickup(): void
    {
        Http::fake(['*' => Http::response(['data' => ['status' => 'released']], 200)]);
        $order = $this->reservedOrder(now()->addDay());

        $cancelled = $this->service()->cancel($order);

        self::assertSame('cancelled', $cancelled->status);
        self::assertSame(1, $cancelled->lock_version);
        self::assertSame('cancelled', $cancelled->pickupCoordinations->first()->status);
        self::assertDatabaseHas('outbox_events', [
            'aggregate_uuid' => $order->public_uuid,
            'event_type' => 'panel.reservation.release',
            'status' => 'processed',
        ]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/reservations/'.$order->panel_reservation_id));
    }

    public function test_expiry_command_releases_only_expired_active_reservations(): void
    {
        Http::fake(['*' => Http::response(['data' => ['status' => 'released']], 200)]);
        $expired = $this->reservedOrder(now()->subMinute());
        $future = $this->reservedOrder(now()->addHour());

        $this->artisan('storefront:expire-reservations')->assertSuccessful();

        self::assertSame('expired', $expired->fresh()->status);
        self::assertSame('pickup_coordination_pending', $future->fresh()->status);
        Http::assertSentCount(1);
    }

    private function reservedOrder($expiresAt): Order
    {
        $order = Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'pickup_coordination_pending',
            'payment_method' => 'cash',
            'panel_reservation_id' => fake()->uuid(),
            'expires_at' => $expiresAt,
        ]);
        PickupCoordination::query()->create([
            'order_id' => $order->id,
            'status' => 'pending_contact',
            'reservation_expires_at' => $expiresAt,
        ]);
        return $order;
    }

    private function service(): CheckoutCancellationService
    {
        return new CheckoutCancellationService(new PanelApiClient(new ServiceRequestSigner()));
    }
}
