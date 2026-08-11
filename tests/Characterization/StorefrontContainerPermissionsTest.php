<?php

declare(strict_types=1);

final class StorefrontContainerPermissionsTest
{
    public static function run(): void
    {
        $dockerfile = (string) file_get_contents(dirname(__DIR__, 2) . '/docker/Dockerfile.storefront-php');
        Assert::isTrue(
            'Storefront container permissions',
            'normaliza lectura del código para php-fpm',
            str_contains($dockerfile, 'chown -R root:www-data /var/www/html')
                && str_contains($dockerfile, 'chmod -R g+rX,o-rwx /var/www/html'),
        );
        Assert::isTrue(
            'Storefront container permissions',
            'mantiene escritura acotada a runtime',
            str_contains($dockerfile, 'chown -R www-data:www-data')
                && str_contains($dockerfile, 'bootstrap/cache'),
        );
        $bootstrap = (string) file_get_contents(dirname(__DIR__, 2) . '/storefront/bootstrap/app.php');
        Assert::isTrue(
            'Storefront proxy configuration',
            'confía forwarded proto solo cuando se configura el proxy',
            str_contains($bootstrap, "env('TRUSTED_PROXIES'")
                && str_contains($bootstrap, "\$trustedProxies === '*'")
                && str_contains($bootstrap, 'Request::HEADER_X_FORWARDED_PROTO'),
        );
    }
}
