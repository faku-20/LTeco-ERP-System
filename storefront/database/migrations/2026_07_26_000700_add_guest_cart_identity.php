<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('carts', 'user_id')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('carts', 'guest_token_hash')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->char('guest_token_hash', 64)->nullable()->unique()->after('user_id');
                $table->timestamp('guest_expires_at')->nullable()->index()->after('expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('carts', 'guest_token_hash')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->dropUnique(['guest_token_hash']);
                $table->dropIndex(['guest_expires_at']);
                $table->dropColumn(['guest_token_hash', 'guest_expires_at']);
            });
        }

        if (Schema::hasColumn('carts', 'user_id')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }
    }
};
