<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutPanelStatusService;
use App\Services\PanelApiClient;
use App\Services\ServiceRequestSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class CheckoutPanelStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_panel_order_updates_customer_order_without_exposing_panel_data():void
    {
        $this->configurePanelClient();
        config()->set('app.timezone','America/Montevideo');
        $order=Order::query()->create(['user_id'=>User::factory()->create()->id,'status'=>'pickup_coordination_pending','payment_method'=>'cash','panel_order_id'=>98]);
        $payload=[
            'panel_order_id'=>98,'order_uuid'=>$order->public_uuid,'order_number'=>'WEB-TEST',
            'status'=>'Pagado','payment_status'=>'Aprobado','panel_sale_id'=>77,
            'paid_at'=>'2026-07-25T22:00:00Z','delivered_at'=>null,'updated_at'=>'2026-07-25T22:00:00Z',
        ];
        Http::fake(['*'=>Http::response(['data'=>$payload],200)]);

        $synced=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);

        self::assertSame('paid',$synced->status);self::assertSame(77,$synced->panel_sale_id);
        self::assertNotNull($synced->paid_at);
        self::assertSame(strtotime($payload['paid_at']),$synced->paid_at->getTimestamp());
        self::assertSame(1,$synced->lock_version);
        $version=$synced->lock_version;
        $unchanged=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($synced);
        self::assertSame($version,$unchanged->lock_version);
    }

    public function test_operational_panel_states_remain_distinct_for_the_customer():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create(['user_id'=>User::factory()->create()->id,'status'=>'paid','payment_method'=>'cash','panel_order_id'=>98]);

        $states=['Preparando'=>'preparing','Listo'=>'ready_for_pickup','ReembolsoPendiente'=>'refund_pending','Reembolsado'=>'refunded'];
        $sequence=Http::sequence();
        foreach($states as$panel=>$local){
            $sequence->push(['data'=>[
                'panel_order_id'=>98,'order_uuid'=>$order->public_uuid,'order_number'=>'WEB-STATE',
                'status'=>$panel,'payment_status'=>'Aprobado','panel_sale_id'=>77,
                'paid_at'=>'2026-07-25T22:00:00Z','delivered_at'=>null,'updated_at'=>'2026-07-25T22:00:00Z',
            ]],200);
        }
        Http::fake(['*'=>$sequence]);
        foreach($states as$local){
            $order=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);
            self::assertSame($local,$order->status);
            self::assertSame('WEB-STATE',$order->panel_order_number);
        }
    }

    public function test_repeated_panel_status_sync_is_idempotent():void
    {
        $this->configurePanelClient();
        config()->set('app.timezone','America/Montevideo');
        $order=Order::query()->create(['user_id'=>User::factory()->create()->id,'status'=>'pickup_coordination_pending','payment_method'=>'cash','panel_order_id'=>98]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'Pagado',[
            'panel_sale_id'=>77,
            'paid_at'=>'2026-07-25T22:00:00Z',
        ])],200)]);

        $service=new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner()));
        $order=$service->sync($order);
        $version=$order->lock_version;
        for($i=0;$i<5;$i++){
            $order=$service->sync($order);
            self::assertSame($version,$order->lock_version);
            self::assertSame('paid',$order->status);
            self::assertSame(strtotime('2026-07-25T22:00:00Z'),$order->paid_at?->getTimestamp());
        }
    }

    public function test_unknown_panel_status_degrades_without_overwriting_local_state():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create([
            'user_id'=>User::factory()->create()->id,
            'status'=>'paid',
            'payment_method'=>'cash',
            'panel_order_id'=>98,
            'panel_order_number'=>'WEB-TEST',
            'panel_sale_id'=>77,
            'lock_version'=>3,
        ]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'EstadoNuevoSinContrato',[
            'panel_sale_id'=>77,
            'paid_at'=>null,
        ])],200)]);

        $synced=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);

        self::assertSame('paid',$synced->status);
        self::assertSame(3,$synced->lock_version);
    }

    public function test_invalid_panel_status_payload_is_rejected():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create(['user_id'=>User::factory()->create()->id,'status'=>'paid','payment_method'=>'cash','panel_order_id'=>98]);
        Http::fake(['*'=>Http::response(['data'=>['order_uuid'=>$order->public_uuid,'panel_order_id'=>98]],200)]);

        $this->expectException(RuntimeException::class);

        (new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);
    }

    public function test_out_of_order_panel_status_does_not_revert_paid_order():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create([
            'user_id'=>User::factory()->create()->id,
            'status'=>'paid',
            'payment_method'=>'cash',
            'panel_order_id'=>98,
            'panel_order_number'=>'WEB-TEST',
            'panel_sale_id'=>77,
            'lock_version'=>4,
        ]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'PagoEnRevision',[
            'panel_sale_id'=>77,
            'paid_at'=>null,
        ])],200)]);

        $synced=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);

        self::assertSame('paid',$synced->status);
        self::assertSame(4,$synced->lock_version);
    }

    public function test_terminal_storefront_state_is_not_revived_by_stale_panel_status():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create([
            'user_id'=>User::factory()->create()->id,
            'status'=>'delivered',
            'payment_method'=>'cash',
            'panel_order_id'=>98,
            'panel_order_number'=>'WEB-TEST',
            'panel_sale_id'=>77,
            'lock_version'=>5,
            'delivered_at'=>'2026-07-26 12:00:00',
        ]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'Preparando',[
            'panel_sale_id'=>77,
            'delivered_at'=>null,
        ])],200)]);

        $synced=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);

        self::assertSame('delivered',$synced->status);
        self::assertSame(5,$synced->lock_version);
    }

    public function test_ready_for_pickup_order_is_not_reverted_to_paid_by_stale_panel_status():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create([
            'user_id'=>User::factory()->create()->id,
            'status'=>'ready_for_pickup',
            'payment_method'=>'cash',
            'panel_order_id'=>98,
            'panel_order_number'=>'WEB-TEST',
            'panel_sale_id'=>77,
            'lock_version'=>6,
        ]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'Pagado',[
            'panel_sale_id'=>77,
            'paid_at'=>null,
        ])],200)]);

        $synced=(new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner())))->sync($order);

        self::assertSame('ready_for_pickup',$synced->status);
        self::assertSame(6,$synced->lock_version);
    }

    public function test_refund_pending_order_can_advance_to_refunded_once():void
    {
        $this->configurePanelClient();
        $order=Order::query()->create([
            'user_id'=>User::factory()->create()->id,
            'status'=>'refund_pending',
            'payment_method'=>'cash',
            'panel_order_id'=>98,
            'panel_order_number'=>'WEB-TEST',
            'panel_sale_id'=>77,
            'lock_version'=>6,
        ]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'Reembolsado',[
            'panel_sale_id'=>77,
            'paid_at'=>null,
        ])],200)]);

        $service=new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner()));
        $synced=$service->sync($order);
        $version=$synced->lock_version;
        $unchanged=$service->sync($synced);

        self::assertSame('refunded',$synced->status);
        self::assertSame(7,$synced->lock_version);
        self::assertSame($version,$unchanged->lock_version);
    }

    public function test_delivered_timestamp_is_normalized_without_repeat_version_increment():void
    {
        $this->configurePanelClient();
        config()->set('app.timezone','America/Montevideo');
        $order=Order::query()->create(['user_id'=>User::factory()->create()->id,'status'=>'ready_for_pickup','payment_method'=>'cash','panel_order_id'=>98]);
        Http::fake(['*'=>Http::response(['data'=>$this->panelPayload($order,'Entregado',[
            'panel_sale_id'=>77,
            'paid_at'=>'2026-07-25T22:00:00Z',
            'delivered_at'=>'2026-07-26T15:00:00Z',
        ])],200)]);

        $service=new CheckoutPanelStatusService(new PanelApiClient(new ServiceRequestSigner()));
        $synced=$service->sync($order);
        $version=$synced->lock_version;
        $unchanged=$service->sync($synced);

        self::assertSame('delivered',$synced->status);
        self::assertSame(strtotime('2026-07-26T15:00:00Z'),$synced->delivered_at?->getTimestamp());
        self::assertSame($version,$unchanged->lock_version);
    }

    /** @param array<string,mixed> $overrides */
    private function panelPayload(Order $order,string $status,array $overrides=[]):array
    {
        return array_merge([
            'panel_order_id'=>98,
            'order_uuid'=>$order->public_uuid,
            'order_number'=>'WEB-TEST',
            'status'=>$status,
            'payment_status'=>'Aprobado',
            'panel_sale_id'=>null,
            'paid_at'=>null,
            'delivered_at'=>null,
            'updated_at'=>'2026-07-25T22:00:00Z',
        ],$overrides);
    }

    private function configurePanelClient():void
    {
        config()->set('storefront_api.panel.base_url','https://panel.example.test/api/storefront/v1');
        config()->set('storefront_api.panel.key_id','storefront-current');
        config()->set('storefront_api.panel.secret','test-secret');
    }
}
