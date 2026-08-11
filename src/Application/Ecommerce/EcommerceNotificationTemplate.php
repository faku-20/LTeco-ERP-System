<?php
declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

final class EcommerceNotificationTemplate
{
    /** @param array<string,mixed> $notification @return array{subject:string,title:string,message:string,button:string}|null */
    public static function build(array $notification): ?array
    {
        $type=(string)($notification['Tipo']??'');$number=(string)($notification['NumeroPedido']??'tu pedido');
        $total=isset($notification['Total'])?trim((string)($notification['Moneda']??'UYU')).' '.number_format((float)$notification['Total'],2,',','.') : '';
        $expires=!empty($notification['ExpiraEn'])?date('d/m/Y H:i',strtotime((string)$notification['ExpiraEn'])):'';
        $customer=trim((string)($notification['Nombre']??'').' '.(string)($notification['Apellido']??''));
        $phone=trim((string)($notification['Telefono']??''));
        $payment=(string)($notification['ProveedorPago']??'');
        $items=trim((string)($notification['ItemsResumen']??''));
        $isCash=$payment==='cash';
        $internalKind=$isCash?'Nueva reserva web':'Nueva venta web con tarjeta';
        $internalPayment=$isCash?'efectivo coordinado':(in_array($payment, ['card','card_simulated','getnet'], true)?'tarjeta':($payment!==''?$payment:'a confirmar'));
        $internalFollowup=$isCash?'. Reserva válida hasta '.$expires.'.':'. Venta confirmada automáticamente.';
        return match($type){
            'ReservaCreada'=>['subject'=>'Reserva confirmada · '.$number,'title'=>'Confirmamos tu reserva','message'=>'Tu reserva '.$number.' quedó confirmada por '.$total.'. Para continuar con el pago y coordinar la visita, contactanos por WhatsApp al +598 92 000 086. Reserva válida hasta '.$expires.'.','button'=>'Ver reserva'],
            'PedidoWebInterno'=>['subject'=>$internalKind.' · '.$number,'title'=>$internalKind,'message'=>'Pedido '.$number.' por '.$total.'. Cliente: '.($customer!==''?$customer:'Sin nombre').($phone!==''?' · Tel: '.$phone:'').'. Pago: '.$internalPayment.($items!==''?'. Unidad: '.$items:'').$internalFollowup,'button'=>'Abrir en panel'],
            'ReservaVencida'=>['subject'=>'Tu reserva venció · '.$number,'title'=>'La reserva venció','message'=>'La reserva del pedido '.$number.' venció y la unidad volvió a quedar disponible. Podés iniciar una compra nueva desde la tienda.','button'=>'Volver a la tienda'],
            'PagoConfirmado'=>['subject'=>'Compra confirmada · '.$number,'title'=>'Tu compra fue confirmada','message'=>'El pago de '.$number.' quedó registrado y el comprobante de compra ya está disponible. Te avisaremos cuando comience la preparación y coordinación de retiro.','button'=>'Ver comprobante'],
            'PedidoPreparando'=>['subject'=>'Estamos preparando tu moto · '.$number,'title'=>'Pedido en preparación','message'=>'Tu pedido '.$number.' ya está en preparación.','button'=>'Ver pedido'],
            'PedidoListo'=>['subject'=>'Tu moto está pronta · '.$number,'title'=>'Pedido listo para retirar','message'=>'Tu pedido '.$number.' está listo. Recordá coordinar previamente el retiro por WhatsApp.','button'=>'Ver pedido'],
            'PedidoEntregado'=>['subject'=>'Entrega confirmada · '.$number,'title'=>'Tu moto fue entregada','message'=>'Registramos la entrega de '.$number.'. Desde hoy están activas la garantía y la postventa.','button'=>'Ver comprobante'],
            'PedidoCancelado'=>['subject'=>'Pedido cancelado · '.$number,'title'=>'Cancelación registrada','message'=>'El pedido '.$number.' quedó cancelado. Si no reconocés esta gestión, comunicate con nosotros.','button'=>'Ver pedido'],
            'ReembolsoSolicitado'=>['subject'=>'Reembolso en proceso · '.$number,'title'=>'Solicitud registrada','message'=>'Registramos el reembolso de '.$number.'. Mantendremos la trazabilidad hasta completarlo.','button'=>'Ver pedido'],
            'PrivacidadResuelta'=>['subject'=>'Actualización de tu solicitud de privacidad','title'=>'Tu solicitud fue revisada','message'=>'Actualizamos la solicitud de privacidad que realizaste desde tu cuenta. Ingresá para consultar el estado y la respuesta.','button'=>'Ver mi cuenta'],
            'RecordatorioService'=>['subject'=>'Próximo service LTecobike','title'=>'Recordatorio de service','message'=>'Tu service #'.(int)($notification['NumeroService']??0).' para '.($notification['Modelo']??'tu moto').' está programado para '.(!empty($notification['FechaProgramada'])?date('d/m/Y',strtotime((string)$notification['FechaProgramada'])):'la fecha acordada').'.','button'=>'Contactar a LTecobike'],
            default=>null,
        };
    }
}
