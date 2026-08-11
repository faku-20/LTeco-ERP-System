<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeploymentRuntimePermissionsTest extends TestCase
{
    #[Test]
    public function storefront_runtime_uses_the_laravel_storage_owner(): void
    {
        $dockerfilePath = '/repo/docker/Dockerfile.storefront-php';

        if (! is_file($dockerfilePath)) {
            $dockerfilePath = dirname(__DIR__, 4) . '/docker/Dockerfile.storefront-php';
        }

        $dockerfile = file_get_contents($dockerfilePath);

        self::assertIsString($dockerfile);
        self::assertMatchesRegularExpression(
            '/^USER www-data\R+RUN php artisan package:discover --ansi$/m',
            $dockerfile,
        );
    }
}
