<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyVehicleColumn = Schema::hasColumn('cart_items', 'vehicle_id');
        if (!Schema::hasColumn('cart_items', 'variant_id')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->char('variant_id', 64)->nullable()->after('cart_id');
            });
        }
        if ($legacyVehicleColumn) {
            if (!Schema::hasIndex('cart_items', 'cart_items_cart_id_standalone_index')) {
                Schema::table('cart_items', function (Blueprint $table): void {
                    $table->index('cart_id', 'cart_items_cart_id_standalone_index');
                });
            }
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->dropUnique(['cart_id', 'vehicle_id']);
                $table->dropColumn(['vehicle_id', 'product_id']);
            });
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->unique(['cart_id', 'variant_id']);
            });
        }
    }

    public function down(): void
    {
        // No se revierte automáticamente: variant_id no puede convertirse con
        // seguridad en una unidad física antes de crear la reserva.
    }
};
