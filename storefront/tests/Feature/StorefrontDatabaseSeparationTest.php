<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CatalogoMoto;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StorefrontDatabaseSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_schema_has_required_fields(): void
    {
        self::assertTrue(
            Schema::hasTable('users')
        );

        foreach (
            [
                'first_name',
                'last_name',
                'email',
                'email_verified_at',
                'password',
                'remember_token',
            ]
            as $column
        ) {
            self::assertTrue(
                Schema::hasColumn(
                    'users',
                    $column,
                ),
                'Missing users.' . $column,
            );
        }

        self::assertFalse(
            Schema::hasColumn('users', 'name')
        );
    }

    public function test_user_requires_email_verification(): void
    {
        self::assertInstanceOf(
            MustVerifyEmail::class,
            new User(),
        );
    }

    public function test_user_factory_uses_first_and_last_name(): void
    {
        $user = User::factory()->make();

        self::assertNotEmpty($user->first_name);
        self::assertNotEmpty($user->last_name);
        self::assertNotEmpty($user->full_name);
    }

    public function test_catalog_model_uses_read_only_connection(): void
    {
        self::assertSame(
            'catalog',
            (new CatalogoMoto())->getConnectionName(),
        );
    }
}
