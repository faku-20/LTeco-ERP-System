<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CartFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function catalog(string $id, int $quantity = 2): array
    {
        return [
            'variant_id' => $id,
            'model' => 'SL-500W',
            'battery_ah' => 20,
            'color' => 'Rosa',
            'availability' => ['available' => $quantity > 0, 'quantity' => $quantity],
            'price' => ['currency' => 'UYU', 'gross' => '65000.00'],
            'version' => 'test-catalog',
        ];
    }

    private function fakeCatalog(string $id, int $quantity = 2): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.test/api/storefront/v1');
        config()->set('storefront_api.panel.secret', 'secret');
        Http::fake(['*' => Http::response(['data' => [$this->catalog($id, $quantity)]], 200)]);
    }

    public function test_verified_customer_can_add_update_and_remove_persistent_cart_item(): void
    {
        $id = str_repeat('b', 64);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->fakeCatalog($id);

        $this->actingAs($user)->post('/carrito', ['variant_id' => $id])->assertRedirect('/carrito');
        $item = CartItem::query()->firstOrFail();
        self::assertSame(1, $item->quantity);

        $this->actingAs($user)->patch(route('cart.update', $item), ['quantity' => 2])->assertRedirect();
        self::assertSame(2, $item->fresh()->quantity);

        $this->actingAs($user)->delete(route('cart.destroy', $item))->assertRedirect();
        self::assertDatabaseCount('cart_items', 0);
    }

    public function test_customer_cannot_change_another_accounts_cart(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::query()->create(['user_id' => $owner->id, 'status' => 'active']);
        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'variant_id' => str_repeat('c', 64),
            'quantity' => 1,
            'model' => 'Q8',
            'color' => 'Negro',
            'expected_gross' => 1,
            'currency' => 'UYU',
            'catalog_version' => 'test',
        ]);

        $this->actingAs($other)->delete(route('cart.destroy', $item))->assertNotFound();
    }

    public function test_guest_cart_persists_with_hashed_cookie_and_can_be_updated(): void
    {
        $id = str_repeat('d', 64);
        $this->fakeCatalog($id);

        $response = $this->post('/carrito', ['variant_id' => $id]);
        $response->assertRedirect('/carrito')->assertCookie(config('storefront_cart.cookie_name'));
        $cookie = $response->getCookie(config('storefront_cart.cookie_name'))->getValue();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cookie);
        self::assertDatabaseHas('carts', [
            'user_id' => null,
            'guest_token_hash' => hash('sha256', $cookie),
        ]);

        $item = CartItem::query()->firstOrFail();
        $this->withCookie(config('storefront_cart.cookie_name'), $cookie)
            ->patch(route('cart.update', $item), ['quantity' => 2])
            ->assertRedirect();
        self::assertSame(2, $item->fresh()->quantity);
    }

    public function test_guest_cart_is_merged_when_customer_registers(): void
    {
        config()->set('storefront_auth.registration_enabled', true);
        $id = str_repeat('e', 64);
        $this->fakeCatalog($id, 3);

        $cartResponse = $this->post('/carrito', ['variant_id' => $id, 'quantity' => 2]);
        $cookie = $cartResponse->getCookie(config('storefront_cart.cookie_name'))->getValue();
        $guestCart = Cart::query()->whereNull('user_id')->firstOrFail();

        $this->withCookie(config('storefront_cart.cookie_name'), $cookie)->post('/registro', [
            'first_name' => 'Facundo',
            'last_name' => 'Pérez',
            'email' => 'guest-merge@example.com',
            'password' => 'PasswordSeguro10',
            'password_confirmation' => 'PasswordSeguro10',
            'accept_privacy' => '1',
        ])->assertRedirect(route('catalogo'));

        $user = User::query()->where('email', 'guest-merge@example.com')->firstOrFail();
        $userCart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();

        self::assertSame('merged', $guestCart->fresh()->status);
        self::assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'variant_id' => $id,
            'quantity' => 2,
        ]);
    }

    public function test_guest_cart_is_merged_when_customer_logs_in(): void
    {
        $id = str_repeat('f', 64);
        $user = User::factory()->create([
            'email' => 'merge-login@example.com',
            'password' => 'PasswordSeguro10',
        ]);
        $this->fakeCatalog($id, 2);

        $cartResponse = $this->post('/carrito', ['variant_id' => $id]);
        $cookie = $cartResponse->getCookie(config('storefront_cart.cookie_name'))->getValue();

        $this->withCookie(config('storefront_cart.cookie_name'), $cookie)->post('/ingresar', [
            'email' => $user->email,
            'password' => 'PasswordSeguro10',
        ])->assertRedirect(route('account.dashboard'));

        $userCart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        self::assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'variant_id' => $id,
            'quantity' => 1,
        ]);
    }

    public function test_cart_page_does_not_require_live_catalog(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->get('/carrito')->assertOk()->assertSee('Tu carrito');
        Http::assertNothingSent();
    }
}
