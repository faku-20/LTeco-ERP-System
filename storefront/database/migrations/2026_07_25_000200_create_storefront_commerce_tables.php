<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('customer_type', 16);
            $table->string('legal_name', 190)->nullable();
            $table->text('phone_encrypted');
            $table->text('cedula_encrypted')->nullable();
            $table->char('cedula_blind_index', 64)->nullable()->unique();
            $table->text('rut_encrypted')->nullable();
            $table->char('rut_blind_index', 64)->nullable()->unique();
            $table->unsignedBigInteger('panel_customer_id')->nullable()->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->default('billing');
            $table->text('line1_encrypted');
            $table->text('line2_encrypted')->nullable();
            $table->text('city_encrypted');
            $table->text('department_encrypted');
            $table->text('postal_code_encrypted')->nullable();
            $table->char('country', 2)->default('UY');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'type', 'is_primary']);
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->char('currency', 3)->default('UYU');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->char('variant_id', 64);
            $table->string('model', 100);
            $table->unsignedSmallInteger('battery_ah')->nullable();
            $table->string('color', 80);
            $table->decimal('expected_gross', 15, 2);
            $table->char('currency', 3)->default('UYU');
            $table->string('catalog_version', 80);
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->string('payment_method', 24)->nullable()->index();
            $table->char('currency', 3)->default('UYU');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('vat_included', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('commercial_terms_version', 80)->nullable();
            $table->text('billing_snapshot_encrypted')->nullable();
            $table->json('terms_snapshot')->nullable();
            $table->uuid('panel_reservation_id')->nullable()->unique();
            $table->unsignedBigInteger('panel_sale_id')->nullable()->unique();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_id', 40);
            $table->unsignedBigInteger('product_id');
            $table->string('model', 100);
            $table->unsignedSmallInteger('battery_ah')->nullable();
            $table->string('color', 80);
            $table->decimal('gross', 15, 2);
            $table->decimal('vat_included', 15, 2);
            $table->char('currency', 3)->default('UYU');
            $table->json('vehicle_snapshot');
            $table->timestamps();
            $table->unique(['order_id', 'vehicle_id']);
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->string('status', 32)->index();
            $table->string('gateway_reference', 190)->nullable()->index();
            $table->string('card_brand', 40)->nullable();
            $table->char('card_last_four', 4)->nullable();
            $table->unsignedSmallInteger('installments')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('UYU');
            $table->json('sanitized_payload')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('pickup_coordinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending_contact')->index();
            $table->timestamp('agreed_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->unsignedBigInteger('panel_user_id')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 80);
            $table->string('document_version', 80);
            $table->char('document_hash', 64);
            $table->timestamp('accepted_at');
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_summary', 255)->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'accepted_at']);
        });

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->string('status', 32)->default('submitted')->index();
            $table->text('request_details_encrypted')->nullable();
            $table->json('resolution_manifest')->nullable();
            $table->timestamp('due_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_panel_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_panel_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('aggregate_type', 80);
            $table->uuid('aggregate_uuid');
            $table->string('event_type', 120);
            $table->json('payload');
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['aggregate_type', 'aggregate_uuid']);
        });

        Schema::create('inbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->char('body_hash', 64);
            $table->string('event_type', 120);
            $table->uuid('aggregate_uuid')->nullable()->index();
            $table->unsignedInteger('event_version')->default(0);
            $table->timestamp('processed_at');
            $table->timestamps();
        });

        Schema::create('security_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 32);
            $table->string('actor_reference', 100)->nullable();
            $table->string('action', 120)->index();
            $table->string('object_type', 80);
            $table->string('object_reference', 100)->nullable();
            $table->json('fields')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_summary', 255)->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'security_audit_events',
            'inbox_events',
            'outbox_events',
            'privacy_requests',
            'consent_records',
            'pickup_coordinations',
            'payment_attempts',
            'order_items',
            'orders',
            'cart_items',
            'carts',
            'customer_addresses',
            'customer_profiles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
