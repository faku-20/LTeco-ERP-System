<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\VerifyServiceHmac;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignCorrelationId;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {
            $middleware->append([AssignCorrelationId::class,AddSecurityHeaders::class]);
            $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
            if ($trustedProxies !== '') {
                $middleware->trustProxies(
                    at: $trustedProxies === '*'
                        ? '*'
                        : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
                    headers: Request::HEADER_X_FORWARDED_FOR
                        | Request::HEADER_X_FORWARDED_HOST
                        | Request::HEADER_X_FORWARDED_PORT
                        | Request::HEADER_X_FORWARDED_PROTO
                        | Request::HEADER_X_FORWARDED_PREFIX,
                );
            }

            $middleware->alias([
                'service.hmac' => VerifyServiceHmac::class,
            ]);

            $middleware->redirectGuestsTo(
                fn (Request $request): string => route(
                    'login'
                ),
            );

            $middleware->redirectUsersTo(
                fn (Request $request): string => route(
                    'account.dashboard'
                ),
            );
        },
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            $exceptions->shouldRenderJsonWhen(
                fn (Request $request): bool =>
                    $request->is('api/*')
                    || $request->is('internal/*'),
            );
        },
    )
    ->create();
