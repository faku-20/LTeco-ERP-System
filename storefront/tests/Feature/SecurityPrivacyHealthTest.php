<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PrivacyRequest;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\Http;

final class SecurityPrivacyHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_include_security_and_correlation_headers():void
    {
        $response=$this->get('/');$response->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('X-Frame-Options','DENY')->assertHeader('Referrer-Policy','strict-origin-when-cross-origin');
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/',(string)$response->headers->get('X-Correlation-Id'));self::assertStringContainsString("frame-ancestors 'none'",(string)$response->headers->get('Content-Security-Policy'));
    }

    public function test_guest_cart_response_is_never_publicly_cached(): void
    {
        $this->get('/carrito')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_customer_can_submit_a_traceable_privacy_request():void
    {
        config()->set('storefront_api.panel.secret',str_repeat('a',64));config()->set('storefront_api.panel.allow_insecure',true);Http::fake(['*/privacy-requests'=>Http::response(['data'=>['status'=>'submitted']],201)]);
        $user=User::factory()->create(['email_verified_at'=>now()]);$this->actingAs($user)->post(route('account.privacy.store'),['type'=>'suppression','details'=>'Solicito revisar mis datos.'])->assertRedirect();
        $request=PrivacyRequest::query()->firstOrFail();self::assertSame('submitted',$request->status);self::assertSame($user->id,$request->user_id);self::assertNotNull($request->due_at);self::assertSame(1,SecurityAuditEvent::query()->where('action','privacy.requested')->count());self::assertSame('processed',OutboxEvent::query()->firstOrFail()->status);Http::assertSent(fn($request)=>str_ends_with($request->url(),'/privacy-requests')&&$request->hasHeader('X-Lteco-Signature'));
    }

    public function test_data_export_requires_password_and_contains_only_customer_data():void
    {
        $user=User::factory()->create(['email_verified_at'=>now(),'password'=>'StrongPassword10']);
        $this->actingAs($user)->post(route('account.privacy.export'),['current_password'=>'wrong'])->assertSessionHasErrors('current_password');
        $response=$this->actingAs($user)->post(route('account.privacy.export'),['current_password'=>'StrongPassword10']);$response->assertOk()->assertHeader('Content-Type','application/json; charset=UTF-8')->assertHeader('Cache-Control','no-store, private');
        $json=(string)$response->streamedContent();self::assertStringContainsString($user->public_uuid,$json);self::assertStringNotContainsString('password',$json);self::assertStringNotContainsString('blind_index',$json);
    }

    public function test_health_endpoints_do_not_expose_infrastructure_details():void
    {
        $this->get('/health/live')->assertOk()->assertExactJson(['status'=>'ok']);$response=$this->get('/health/ready');$response->assertOk()->assertExactJson(['status'=>'ok']);self::assertStringNotContainsString('database',(string)$response->getContent());
    }

    public function test_mail_check_is_read_only_without_explicit_destination():void
    {
        config()->set('mail.default','array');config()->set('mail.from.address','cuentas@mail.ltecobike.uy');
        $this->artisan('storefront:mail-check')->expectsOutput('Correo: configuración OK. No se envió ningún mensaje.')->assertSuccessful();
    }
}
