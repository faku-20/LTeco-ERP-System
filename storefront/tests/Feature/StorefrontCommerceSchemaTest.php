<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StorefrontCommerceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_storefront_owned_tables_exist(): void
    {
        foreach ([
            'customer_profiles', 'customer_addresses', 'carts', 'cart_items',
            'orders', 'order_items', 'payment_attempts', 'pickup_coordinations',
            'consent_records', 'privacy_requests', 'outbox_events',
            'inbox_events', 'security_audit_events',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), $table . ' no existe');
        }
        self::assertTrue(Schema::hasColumn('orders', 'panel_order_number'));
    }

    public function test_new_user_receives_public_uuid_and_normalized_email(): void
    {
        $user = User::factory()->create(['email' => 'CLIENTE@Example.com']);

        self::assertNotEmpty($user->public_uuid);
        self::assertSame('cliente@example.com', $user->email);
        self::assertSame('cliente@example.com', $user->email_normalized);
    }

    public function test_sensitive_profile_values_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $profile = CustomerProfile::query()->create([
            'user_id' => $user->id,
            'customer_type' => 'consumer',
            'phone_encrypted' => '099123456',
            'cedula_encrypted' => '12345678',
            'cedula_blind_index' => hash_hmac('sha256', '12345678', 'test-index-key'),
        ]);

        $stored = DB::table('customer_profiles')->where('id', $profile->id)->first();

        self::assertNotSame('099123456', $stored->phone_encrypted);
        self::assertNotSame('12345678', $stored->cedula_encrypted);
        self::assertSame('099123456', $profile->fresh()->phone_encrypted);
        self::assertSame('12345678', $profile->fresh()->cedula_encrypted);
    }

    public function test_order_totals_and_snapshots_keep_exact_values(): void
    {
        $user = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'status' => 'draft',
            'currency' => 'UYU',
            'subtotal' => '67000.00',
            'discount' => '0.00',
            'vat_included' => '12081.97',
            'total' => '67000.00',
            'billing_snapshot_encrypted' => ['cedula' => '12345678'],
            'terms_snapshot' => ['version' => 'terms-v1'],
        ]);

        $fresh = $order->fresh();

        self::assertSame('67000.00', $fresh->total);
        self::assertSame('12345678', $fresh->billing_snapshot_encrypted['cedula']);
        self::assertSame('terms-v1', $fresh->terms_snapshot['version']);
    }
}
