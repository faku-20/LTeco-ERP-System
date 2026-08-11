<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                $this->normalizedEmail($request).'|'.$request->ip(),
            );
        });

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perHour(6)->by(
                $this->normalizedEmail($request).'|'.$request->ip(),
            );
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            return Limit::perHour(6)->by(
                $this->normalizedEmail($request).'|'.$request->ip(),
            );
        });

        RateLimiter::for('verification', function (Request $request): Limit {
            return Limit::perMinute(6)->by(
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
            );
        });

        RateLimiter::for('cart', function (Request $request): Limit {
            $guest = (string) $request->cookie(
                (string) config('storefront_cart.cookie_name', 'ltecobike_guest_cart'),
            );
            $guestKey = $guest !== '' ? hash('sha256', $guest) : 'none';
            $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(30)->by(
                $userKey.'|'.$guestKey.'|'.$request->ip(),
            );
        });

        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(6)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));

        RateLimiter::for('account', fn (Request $request): Limit => Limit::perMinute(12)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));

        RateLimiter::for('contact', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email', '')));
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone', '')) ?: 'none';

            return Limit::perHour(5)->by($email.'|'.$phone.'|'.$request->ip());
        });

        RateLimiter::for('visit', fn (Request $request): Limit => Limit::perHour(3)->by(
            preg_replace('/\D+/', '', (string) $request->input('phone', ''))
            .'|'.$request->ip(),
        ));

        RateLimiter::for('privacy', fn (Request $request): Limit => Limit::perHour(3)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));

        RateLimiter::for('order-cancel', fn (Request $request): Limit => Limit::perHour(6)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));

        VerifyEmail::toMailUsing(
            function (object $notifiable, string $verificationUrl): MailMessage {
                return (new MailMessage())
                    ->subject('Verificá tu correo de LTecobike')
                    ->greeting('¡Hola!')
                    ->line('Confirmá tu correo electrónico para activar tu cuenta.')
                    ->action('Verificar correo electrónico', $verificationUrl)
                    ->line('Si no creaste esta cuenta, podés ignorar este mensaje.');
            },
        );
    }

    private function normalizedEmail(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email', '')));
    }
}
