<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_available(): void
    {
        $this
            ->get('/ingresar')
            ->assertOk()
            ->assertSee('Ingresar')
            ->assertSee('Correo electrónico')
            ->assertSee('Contraseña');
    }

    public function test_registration_is_disabled_by_default(): void
    {
        $this
            ->get('/registro')
            ->assertNotFound();

        $this
            ->post('/registro', [])
            ->assertNotFound();
    }

    public function test_customer_can_register_when_enabled(): void
    {
        config()->set(
            'storefront_auth.registration_enabled',
            true,
        );

        Notification::fake();

        $response = $this->post(
            '/registro',
            [
                'first_name' => 'Facundo',
                'last_name' => 'Pérez',
                'email' => 'cliente@example.com',
                'password' => 'PasswordSeguro10',
                'password_confirmation' => (
                    'PasswordSeguro10'
                ),
                'accept_privacy' => '1',
            ],
        );

        $response->assertRedirect(route('catalogo'))
            ->assertSessionHas('status', 'Creamos tu cuenta. Revisá tu correo para verificarla y poder finalizar la compra.');

        $user = User::query()
            ->where(
                'email',
                'cliente@example.com',
            )
            ->firstOrFail();

        self::assertSame(
            'Facundo',
            $user->first_name,
        );

        self::assertSame(
            'Pérez',
            $user->last_name,
        );

        self::assertNull(
            $user->email_verified_at,
        );
        self::assertSame(1,$user->consents()->where('purpose','account_privacy')->count());

        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
        );
    }

    public function test_verified_customer_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'PasswordSeguro10',
        ]);

        $this
            ->post(
                '/ingresar',
                [
                    'email' => 'CLIENTE@example.com',
                    'password' => 'PasswordSeguro10',
                ],
            )
            ->assertRedirect(
                route('account.dashboard')
            );

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'PasswordSeguro10',
        ]);

        $this
            ->from('/ingresar')
            ->post(
                '/ingresar',
                [
                    'email' => 'cliente@example.com',
                    'password' => 'ContraseñaIncorrecta10',
                ],
            )
            ->assertRedirect('/ingresar')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unverified_customer_cannot_open_account(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        $this
            ->actingAs($user)
            ->get('/cuenta')
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1(
                    $user->getEmailForVerification()
                ),
            ],
        );

        $this
            ->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(
                route('account.dashboard')
            );

        self::assertTrue(
            $user->fresh()->hasVerifiedEmail()
        );
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()
            ->unverified()
            ->create();

        $this
            ->actingAs($user)
            ->post('/email/verificacion-notificacion')
            ->assertRedirect()
            ->assertSessionHas(
                'status',
                'verification-link-sent',
            );

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
        );
    }

    public function test_customer_can_logout(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post('/salir')
            ->assertRedirect(
                route('catalogo')
            );

        $this->assertGuest();
    }
}
