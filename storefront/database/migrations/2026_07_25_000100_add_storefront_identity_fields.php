<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
            $table->string('email_normalized')->nullable()->unique()->after('email');
            $table->string('account_status', 32)->default('active')->after('password');
            $table->timestamp('claimed_at')->nullable()->after('email_verified_at');
        });

        DB::table('users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'public_uuid' => (string) Str::uuid(),
                        'email_normalized' => mb_strtolower(trim((string) $user->email)),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_uuid']);
            $table->dropUnique(['email_normalized']);
            $table->dropColumn([
                'public_uuid',
                'email_normalized',
                'account_status',
                'claimed_at',
            ]);
        });
    }
};
