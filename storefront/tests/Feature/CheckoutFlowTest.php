<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Cart;
use App\Models\CartItem;
use Tests\TestCase;

final class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_a_verified_account(): void
    {
        $this->get('/comprar')->assertRedirect('/ingresar');
    }

    public function test_verified_customer_can_create_a_cash_reservation(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        config()->set('storefront_privacy.blind_index_key', 'stable-test-index-key');
        $variantId = str_repeat('a', 64);
        $variant = [
            'variant_id' => $variantId, 'model' => 'Q8-500W', 'battery_ah' => 20,
            'color' => 'Beige', 'availability' => ['available' => true, 'quantity' => 1],
            'price' => ['currency' => 'UYU', 'gross' => '67000.00'],
        ];
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cart=Cart::query()->create(['user_id'=>$user->id,'status'=>'active','currency'=>'UYU']);
        CartItem::query()->create(['cart_id'=>$cart->id,'variant_id'=>$variantId,'quantity'=>1,'model'=>'Q8-500W','battery_ah'=>20,'color'=>'Beige','expected_gross'=>'67000.00','currency'=>'UYU','catalog_version'=>'test']);
        // La respuesta de reserva necesita el UUID creado durante el request;
        // un fake dinámico conserva el contrato sin fijar IDs internos.
        Http::fake(function ($request) use ($variant, $variantId) {
            if (str_ends_with($request->url(), '/catalog')) return Http::response(['data' => [$variant]], 200);
            $payload = $request->data();
            if (str_ends_with($request->url(), '/orders')) return Http::response(['data' => [
                'panel_order_id' => 321, 'order_uuid' => $payload['order_uuid'],
                'order_number' => 'WEB-TEST', 'status' => 'PagoEnRevision',
            ]], 201);
            return Http::response(['data' => [
                'reservation_id' => '8e13db28-92d9-46d0-ad51-998fd505f6e4',
                'order_uuid' => $payload['order_uuid'], 'status' => 'active', 'payment_method' => 'cash',
                'expires_at' => now()->addDay()->toISOString(), 'currency' => 'UYU',
                'subtotal' => '67000.00', 'discount' => '3350.00', 'total' => '63650.00',
                'items' => [[
                    'vehicle_id' => 'V0230', 'product_id' => 85, 'variant_id' => $variantId,
                    'model' => 'Q8-500W', 'battery_ah' => 20, 'color' => 'Beige',
                    'gross' => '67000.00', 'vat_rate' => '22.00',
                ]],
            ]], 201);
        });

        $response = $this->actingAs($user)->post('/comprar', [
            'customer_type' => 'consumer',
            'phone' => '092000086', 'cedula' => '52248878',
            'address_line1' => 'Av. Italia 1234', 'city' => 'Montevideo',
            'department' => 'Montevideo', 'payment_method' => 'cash',
            'accept_terms' => '1',
        ]);

        $order = $user->orders()->first();
        $response->assertRedirect(route('orders.show', ['order' => $order->public_uuid]));
        self::assertSame('pickup_coordination_pending', $order->fresh()->status);
        self::assertSame(321, $order->fresh()->panel_order_id);
        self::assertSame('WEB-TEST', $order->fresh()->panel_order_number);
        self::assertSame('converted',$cart->fresh()->status);
        self::assertDatabaseHas('customer_profiles', ['user_id' => $user->id, 'customer_type' => 'consumer']);
        self::assertDatabaseHas('customer_addresses', ['user_id' => $user->id, 'is_primary' => true]);
    }

    public function test_verified_customer_can_complete_a_simulated_card_payment(): void
    {
        config()->set('storefront.payment_simulator.enabled', true);
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        config()->set('storefront_privacy.blind_index_key', 'stable-test-index-key');
        $variantId = str_repeat('b', 64);
        $variant = [
            'variant_id' => $variantId, 'model' => 'SL-500W', 'battery_ah' => 20,
            'color' => 'Azul', 'availability' => ['available' => true, 'quantity' => 1],
            'price' => ['currency' => 'UYU', 'gross' => '65000.00'],
        ];
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::query()->create(['user_id' => $user->id, 'status' => 'active', 'currency' => 'UYU']);
        CartItem::query()->create([
            'cart_id' => $cart->id, 'variant_id' => $variantId, 'quantity' => 1,
            'model' => 'SL-500W', 'battery_ah' => 20, 'color' => 'Azul',
            'expected_gross' => '65000.00', 'currency' => 'UYU', 'catalog_version' => 'test',
        ]);

        Http::fake(function ($request) use ($variant, $variantId) {
            if (str_ends_with($request->url(), '/catalog')) {
                return Http::response(['data' => [$variant]], 200);
            }
            $payload = $request->data();
            if (str_ends_with($request->url(), '/reservations')) {
                return Http::response(['data' => [
                    'reservation_id' => '8e13db28-92d9-46d0-ad51-998fd505f6e4',
                    'order_uuid' => $payload['order_uuid'], 'status' => 'active', 'payment_method' => 'card',
                    'expires_at' => now()->addMinutes(30)->toISOString(), 'currency' => 'UYU',
                    'subtotal' => '65000.00', 'discount' => '0.00', 'total' => '65000.00',
                    'items' => [[
                        'vehicle_id' => 'TEST-SL-AZUL', 'product_id' => 2200, 'variant_id' => $variantId,
                        'model' => 'SL-500W', 'battery_ah' => 20, 'color' => 'Azul',
                        'gross' => '65000.00', 'vat_rate' => '22.00',
                    ]],
                ]], 201);
            }
            if (str_ends_with($request->url(), '/orders')) {
                return Http::response(['data' => [
                    'panel_order_id' => 654, 'order_uuid' => $payload['order_uuid'],
                    'order_number' => 'WEB-CARD', 'status' => 'PagoEnRevision',
                ]], 201);
            }
            if (str_ends_with($request->url(), '/simulate-payment')) {
                $uuid = basename(dirname($request->url()));
                return Http::response(['data' => [
                    'panel_order_id' => 654, 'order_uuid' => $uuid,
                    'order_number' => 'WEB-CARD', 'status' => 'Pagado',
                    'payment_status' => 'Aprobado', 'panel_sale_id' => 987,
                    'paid_at' => now()->toISOString(),
                ]], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->post('/comprar', [
            'customer_type' => 'consumer',
            'phone' => '092000086', 'cedula' => '52248878',
            'address_line1' => 'Av. Italia 1234', 'city' => 'Montevideo',
            'department' => 'Montevideo', 'payment_method' => 'card',
            'accept_terms' => '1',
        ]);

        $order = $user->orders()->first();
        $response->assertRedirect(route('orders.show', ['order' => $order->public_uuid]));
        self::assertSame('paid', $order->fresh()->status);
        self::assertSame(987, $order->fresh()->panel_sale_id);
        self::assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'gateway' => 'card_simulator',
            'status' => 'approved',
        ]);
    }

    public function test_disabled_payment_simulator_rejects_card_before_panel_side_effects(): void
    {
        config()->set('storefront.payment_simulator.enabled', false);
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        config()->set('storefront_privacy.blind_index_key', 'stable-test-index-key');

        $variantId = str_repeat('c', 64);
        $variant = [
            'variant_id' => $variantId, 'model' => 'Q8-500W', 'battery_ah' => 20,
            'color' => 'Rojo', 'availability' => ['available' => true, 'quantity' => 1],
            'price' => ['currency' => 'UYU', 'gross' => '67000.00'],
        ];
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::query()->create(['user_id' => $user->id, 'status' => 'active', 'currency' => 'UYU']);
        CartItem::query()->create([
            'cart_id' => $cart->id, 'variant_id' => $variantId, 'quantity' => 1,
            'model' => 'Q8-500W', 'battery_ah' => 20, 'color' => 'Rojo',
            'expected_gross' => '67000.00', 'currency' => 'UYU', 'catalog_version' => 'test',
        ]);

        Http::fake(function ($request) use ($variant) {
            if (str_ends_with($request->url(), '/catalog')) {
                return Http::response(['data' => [$variant]], 200);
            }

            return Http::response(['error' => ['code' => 'unexpected_side_effect']], 500);
        });

        $response = $this->actingAs($user)->post('/comprar', [
            'customer_type' => 'consumer',
            'phone' => '092000086',
            'cedula' => '52248878',
            'address_line1' => 'Av. Italia 1234',
            'city' => 'Montevideo',
            'department' => 'Montevideo',
            'payment_method' => 'card',
            'accept_terms' => '1',
        ]);

        $response->assertSessionHasErrors('payment_method');
        self::assertSame(0, Order::query()->count());
        self::assertSame('active', $cart->fresh()->status);
        self::assertSame(0, PaymentAttempt::query()->where('gateway', 'card_simulator')->count());
        Http::assertSentCount(0);
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/simulate-payment'));
    }

    public function test_customer_can_cancel_only_their_own_active_order(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        Http::fake(['*' => Http::response(['data' => ['status' => 'released']], 200)]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::query()->create([
            'user_id' => $owner->id,
            'status' => 'pickup_coordination_pending',
            'payment_method' => 'cash',
            'panel_reservation_id' => '8e13db28-92d9-46d0-ad51-998fd505f6e4',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($other)->post(route('orders.cancel', ['order' => $order->public_uuid]))
            ->assertNotFound();
        self::assertSame('pickup_coordination_pending', $order->fresh()->status);

        $this->actingAs($owner)->post(route('orders.cancel', ['order' => $order->public_uuid]))
            ->assertRedirect(route('orders.show', ['order' => $order->public_uuid]));
        self::assertSame('cancelled', $order->fresh()->status);
    }

    public function test_paid_order_receipt_uses_customer_receipt_layout(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Facundo',
            'last_name' => 'Pérez',
            'email_verified_at' => now(),
        ]);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'status' => 'paid',
            'payment_method' => 'card',
            'currency' => 'UYU',
            'subtotal' => '65000.00',
            'discount' => '0.00',
            'total' => '65000.00',
            'panel_order_number' => 'WEB-TEST-RECEIPT',
            'panel_sale_id' => 987,
            'paid_at' => now(),
            'billing_snapshot_encrypted' => [
                'customer_type' => 'consumer',
                'name' => 'Facundo Pérez',
                'email' => 'facu@example.test',
                'phone' => '092467494',
                'cedula' => '52248878',
                'address' => ['line1' => null],
            ],
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'vehicle_id' => 'TEST-SL-AZUL',
            'product_id' => 2200,
            'model' => 'SL-500W',
            'battery_ah' => 20,
            'color' => 'Azul',
            'gross' => '65000.00',
            'vat_included' => '11721.31',
            'currency' => 'UYU',
            'vehicle_snapshot' => ['vehicle_id' => 'TEST-SL-AZUL'],
        ]);

        $this->actingAs($user)
            ->get(route('orders.receipt', ['order' => $order->public_uuid]))
            ->assertOk()
            ->assertSee('purchase-receipt', false)
            ->assertSee('Comprobante de compra')
            ->assertSee('Detalle de productos')
            ->assertSee('SL-500W-20Ah-Azul')
            ->assertDontSee('Venta interna')
            ->assertDontSee('Tarjeta simulada')
            ->assertDontSee('Comprobante interno')
            ->assertDontSee('ID vehículo')
            ->assertDontSee('Tipo de cambio')
            ->assertDontSee('customer-auth__form', false);
    }
}
