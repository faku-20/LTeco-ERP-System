<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CheckoutPanelStatusService
{
    public function __construct(private readonly PanelApiClient $panel) {}

    public function sync(Order $order):Order
    {
        $response=$this->panel->orderStatus($order->public_uuid);
        if($response->status()!==200||$response->json('data.order_uuid')!==$order->public_uuid)throw new RuntimeException('No fue posible consultar el estado del pedido en el panel.');
        $data=$response->json('data');
        if(!is_array($data)||!isset($data['panel_order_id'],$data['status']))throw new RuntimeException('La respuesta del panel no tiene el contrato esperado.');
        $panelStatus=trim((string)$data['status']);
        if($panelStatus==='')throw new RuntimeException('La respuesta del panel no tiene estado.');
        $localStatus=CheckoutPanelOrderState::localForPanel($panelStatus);
        if($localStatus===null){
            Log::warning('storefront.panel_status.unknown',[
                'order_id'=>$order->id,
                'order_uuid'=>$order->public_uuid,
                'panel_status'=>$panelStatus,
                'known_panel_statuses'=>CheckoutPanelOrderState::knownPanelStatuses(),
            ]);
        }
        $paidAt=$this->panelTimestamp($data['paid_at']??null,'paid_at');
        $deliveredAt=$this->panelTimestamp($data['delivered_at']??null,'delivered_at');

        return DB::transaction(function()use($order,$data,$localStatus,$paidAt,$deliveredAt):Order{
            $locked=Order::query()->lockForUpdate()->findOrFail($order->id);
            $changes=[];$panelOrderId=(int)$data['panel_order_id'];$panelOrderNumber=trim((string)($data['order_number']??''));
            if($panelOrderId<=0)throw new RuntimeException('La respuesta del panel no tiene identificador valido.');
            if($panelOrderId!==$locked->panel_order_id)$changes['panel_order_id']=$panelOrderId;
            if($panelOrderNumber!==''&&$panelOrderNumber!==$locked->panel_order_number)$changes['panel_order_number']=$panelOrderNumber;
            if($localStatus!==null&&CheckoutPanelOrderState::shouldApply((string)$locked->status,$localStatus))$changes['status']=$localStatus;
            if(!empty($data['panel_sale_id'])&&(int)$data['panel_sale_id']!==$locked->panel_sale_id)$changes['panel_sale_id']=(int)$data['panel_sale_id'];
            if($paidAt!==null&&$locked->paid_at?->getTimestamp()!==$paidAt->getTimestamp())$changes['paid_at']=$this->storefrontTimestamp($paidAt);
            if($deliveredAt!==null&&$locked->delivered_at?->getTimestamp()!==$deliveredAt->getTimestamp())$changes['delivered_at']=$this->storefrontTimestamp($deliveredAt);
            if($changes===[])return$locked;
            $changes['lock_version']=$locked->lock_version+1;
            $locked->forceFill($changes)->save();return$locked->fresh();
        });
    }

    private function panelTimestamp(mixed $value,string $field):?CarbonImmutable
    {
        if($value===null||$value==='')return null;
        try{
            return CarbonImmutable::parse((string)$value)->utc();
        }catch(\Throwable $exception){
            throw new RuntimeException("La respuesta del panel contiene {$field} invalido.",0,$exception);
        }
    }

    private function storefrontTimestamp(CarbonImmutable $timestamp):string
    {
        return $timestamp->utc()->format('Y-m-d H:i:s');
    }
}
