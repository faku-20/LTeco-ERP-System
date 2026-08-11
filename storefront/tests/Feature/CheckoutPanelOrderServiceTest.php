<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutPanelOrderService;
use App\Services\PanelApiClient;
use App\Services\ServiceRequestSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CheckoutPanelOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_order_is_registered_in_panel_without_account_credentials(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id', 'storefront-current');
        config()->set('storefront_api.panel.secret', 'test-secret');
        $user=User::factory()->create(['first_name'=>'Ana','last_name'=>'Pérez','email'=>'ana@example.test']);
        $order=Order::query()->create([
            'user_id'=>$user->id,'status'=>'pickup_coordination_pending','payment_method'=>'cash',
            'panel_reservation_id'=>'8e13db28-92d9-46d0-ad51-998fd505f6e4',
            'billing_snapshot_encrypted'=>[
                'phone'=>'092000086','cedula'=>'52248878','customer_type'=>'consumer',
                'address'=>['line1'=>'Av. Italia 1234','city'=>'Montevideo','department'=>'Montevideo','country'=>'UY'],
            ],
        ]);
        Http::fake(['*'=>Http::response(['data'=>[
            'panel_order_id'=>123,'order_uuid'=>$order->public_uuid,
            'order_number'=>'WEB-PRUEBA','status'=>'PagoEnRevision',
        ]],201)]);

        $synced=(new CheckoutPanelOrderService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);

        self::assertSame(123,$synced->panel_order_id);
        self::assertDatabaseHas('outbox_events',['event_type'=>'panel.order.create','status'=>'processed']);
        Http::assertSent(function($request):bool{
            $payload=$request->data();
            return str_ends_with($request->url(),'/orders')
                && ($payload['customer']['email']??null)==='ana@example.test'
                && !array_key_exists('password',$payload['customer']);
        });
    }
}
