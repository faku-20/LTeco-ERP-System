<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VisitBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('storefront_api.panel.base_url','https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id','storefront-current');
        config()->set('storefront_api.panel.secret','test-secret');
    }

    public function test_any_visitor_can_open_the_booking_form(): void
    {
        $this->get('/agenda?modelo=Q8-500W')
            ->assertOk()
            ->assertSee('Agendá tu visita')
            ->assertSee('Belvedere')
            ->assertSee('value="Q8-500W" selected',false);
    }

    public function test_valid_booking_is_sent_signed_to_the_panel(): void
    {
        Http::fake(function(Request$request){
            return Http::response(['data'=>[
                'visit_id'=>15,
                'request_uuid'=>$request['request_uuid'],
                'status'=>'pending_confirmation',
                'preferred_at'=>now('America/Montevideo')->addDays(2)->setTime(11,30)->toAtomString(),
                'duplicate'=>false,
            ]],201);
        });

        $this->post('/agenda',[
            'full_name'=>'María Pérez',
            'phone'=>'099 123 456',
            'email'=>'maria@example.test',
            'model'=>'SL-500W',
            'preferred_date'=>now('America/Montevideo')->addDays(2)->format('Y-m-d'),
            'preferred_time'=>'11:30',
            'comments'=>'Quiero conocer el modelo.',
            'accept_privacy'=>'1',
        ])->assertRedirect('/agenda')->assertSessionHas('status');

        Http::assertSent(function(Request$request):bool{
            return $request->url()==='https://panel.example.test/api/storefront/v1/visits'
                &&$request->method()==='POST'
                &&$request->hasHeader('X-Lteco-Signature')
                &&$request->hasHeader('Idempotency-Key')
                &&$request['model']==='SL-500W'
                &&$request['phone']==='099 123 456';
        });
    }

    public function test_invalid_schedule_never_reaches_the_panel(): void
    {
        Http::fake();
        $this->from('/agenda')->post('/agenda',[
            'full_name'=>'María Pérez',
            'phone'=>'099123456',
            'preferred_date'=>now('America/Montevideo')->subDay()->format('Y-m-d'),
            'preferred_time'=>'03:00',
            'accept_privacy'=>'1',
        ])->assertRedirect('/agenda')->assertSessionHasErrors(['preferred_date','preferred_time']);
        Http::assertNothingSent();
    }

    public function test_honeypot_is_accepted_without_calling_the_panel(): void
    {
        Http::fake();
        $this->from('/agenda')->post('/agenda',['website'=>'spam.example'])
            ->assertRedirect('/agenda')->assertSessionHas('status');
        Http::assertNothingSent();
    }
}
