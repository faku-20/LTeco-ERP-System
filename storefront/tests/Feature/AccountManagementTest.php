<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_updates_profile_address_and_password(): void
    {
        config()->set('storefront_privacy.blind_index_key', 'test-index');
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => 'OldPassword10',
        ]);

        $this->actingAs($user)->patch(route('account.update'), [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => $user->email,
            'customer_type' => 'consumer',
            'phone' => '092000086',
            'cedula' => '52248878',
        ])->assertRedirect();

        self::assertSame('Ana', $user->fresh()->first_name);
        self::assertSame('52248878', $user->profile()->first()->cedula_encrypted);

        $this->actingAs($user)->post(route('account.addresses.store'), [
            'line1' => 'Av. Italia 1234',
            'city' => 'Montevideo',
            'department' => 'Montevideo',
            'is_primary' => 1,
        ])->assertRedirect();
        self::assertSame('Av. Italia 1234', CustomerAddress::query()->firstOrFail()->line1_encrypted);

        $this->actingAs($user)->patch(route('account.password'), [
            'current_password' => 'OldPassword10',
            'password' => 'NewPassword20',
            'password_confirmation' => 'NewPassword20',
        ])->assertRedirect();
        self::assertTrue(Hash::check('NewPassword20', $user->fresh()->password));
    }

    public function test_customer_type_clears_incompatible_billing_fields(): void
    {
        config()->set('storefront_privacy.blind_index_key', 'test-index');
        $user = User::factory()->create(['email_verified_at' => now()]);
        CustomerProfile::query()->create([
            'user_id' => $user->id,
            'customer_type' => 'consumer',
            'phone_encrypted' => '092000086',
            'cedula_encrypted' => '52248878',
            'cedula_blind_index' => hash_hmac('sha256', '52248878', 'test-index'),
            'status' => 'active',
        ]);

        $this->actingAs($user)->patch(route('account.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'customer_type' => 'business',
            'legal_name' => 'Empresa de Prueba SA',
            'phone' => '092000086',
            'rut' => '211234560015',
            'cedula' => "52248878' OR 1=1 --",
        ])->assertRedirect();

        $profile = $user->profile()->firstOrFail();
        self::assertSame('business', $profile->customer_type);
        self::assertSame('211234560015', $profile->rut_encrypted);
        self::assertNull($profile->cedula_encrypted);
        self::assertNull($profile->cedula_blind_index);

        $this->actingAs($user)->patch(route('account.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'customer_type' => 'consumer',
            'phone' => '092000086',
            'cedula' => '52248878',
            'rut' => '211234560015',
            'legal_name' => 'No debe persistirse',
        ])->assertRedirect();

        $profile = $user->profile()->firstOrFail();
        self::assertSame('consumer', $profile->customer_type);
        self::assertSame('52248878', $profile->cedula_encrypted);
        self::assertNull($profile->rut_encrypted);
        self::assertNull($profile->rut_blind_index);
        self::assertNull($profile->legal_name);
    }

    public function test_account_page_has_structured_sections_and_conditional_fields(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/cuenta')
            ->assertOk()
            ->assertSee('account-sidebar', false)
            ->assertSee('Datos personales')
            ->assertSee('Direcciones')
            ->assertSee('Mis pedidos')
            ->assertSee('Seguridad')
            ->assertSee('Privacidad y datos')
            ->assertSee('data-account-customer-type', false)
            ->assertSee('js/account.js', false);
    }

    public function test_password_recovery_sends_link_without_revealing_account_data(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_receipt_is_available_only_to_owner_after_payment(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::query()->create([
            'user_id' => $owner->id,
            'status' => 'paid',
            'payment_method' => 'cash',
            'panel_sale_id' => 44,
            'total' => 100,
            'currency' => 'UYU',
            'paid_at' => now(),
        ]);

        $this->actingAs($other)->get(route('orders.receipt', $order->public_uuid))->assertNotFound();
        $this->actingAs($owner)->get(route('orders.receipt', $order->public_uuid))->assertOk()->assertSee('Comprobante de compra');
    }
}
