<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CheckoutPanelOrderService
{
    public function __construct(private readonly PanelApiClient $panel) {}

    public function sync(Order $order): Order
    {
        $order->loadMissing('user');
        if ($order->panel_order_id !== null) return $order;
        if ($order->panel_reservation_id === null) throw new RuntimeException('El pedido todavía no tiene reserva.');
        $billing = $order->billing_snapshot_encrypted;
        if (!is_array($billing) || !is_array($billing['address'] ?? null)) {
            throw new RuntimeException('El pedido no tiene los datos de facturación requeridos.');
        }
        $event = OutboxEvent::query()->firstOrCreate(
            ['aggregate_type'=>'order','aggregate_uuid'=>$order->public_uuid,'event_type'=>'panel.order.create'],
            ['payload'=>['reservation_id'=>$order->panel_reservation_id],'status'=>'pending','attempts'=>0,'available_at'=>now()],
        );
        $response = $this->panel->createOrder([
            'order_uuid' => $order->public_uuid,
            'reservation_id' => $order->panel_reservation_id,
            'customer' => [
                'first_name' => $order->user->first_name,
                'last_name' => $order->user->last_name,
                'email' => $order->user->email,
                'phone' => (string) ($billing['phone'] ?? ''),
                'cedula' => $billing['cedula'] ?? null,
                'rut' => $billing['rut'] ?? null,
                'legal_name' => $billing['legal_name'] ?? null,
                'customer_type' => $billing['customer_type'] ?? 'consumer',
            ],
            'billing_address' => $billing['address'],
        ], $event->idempotency_key);
        if ($response->status() !== 201 || $response->json('data.order_uuid') !== $order->public_uuid) {
            $code=(string)$response->json('error.code','panel_unavailable');
            $event->forceFill(['status'=>'pending','attempts'=>$event->attempts+1,'last_error'=>$code,'available_at'=>now()->addMinute()])->save();
            throw new RuntimeException('No fue posible registrar el pedido en el panel: '.$code);
        }
        $panelOrderId=(int)$response->json('data.panel_order_id',0);
        $panelOrderNumber=trim((string)$response->json('data.order_number',''));
        if($panelOrderId<=0||$panelOrderNumber==='')throw new RuntimeException('El panel devolvió un pedido inválido.');

        return DB::transaction(function()use($order,$event,$panelOrderId,$panelOrderNumber):Order{
            $locked=Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->forceFill(['panel_order_id'=>$panelOrderId,'panel_order_number'=>$panelOrderNumber,'lock_version'=>$locked->lock_version+1])->save();
            $event->forceFill(['status'=>'processed','attempts'=>$event->attempts+1,'processed_at'=>now(),'last_error'=>null])->save();
            return $locked->fresh();
        });
    }
}
