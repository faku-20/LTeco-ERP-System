<?php
declare(strict_types=1);

use Lteco\Application\Ecommerce\EcommerceNotificationTemplate;

final class EcommerceTransactionalNotificationTest
{
    public static function run(): void
    {
        $base = ['NumeroPedido' => 'WEB-123', 'Total' => 67000, 'Moneda' => 'UYU', 'ExpiraEn' => '2026-07-26 10:30:00'];
        foreach (['ReservaCreada', 'ReservaVencida', 'PagoConfirmado', 'PedidoPreparando', 'PedidoListo', 'PedidoEntregado', 'PedidoCancelado', 'ReembolsoSolicitado'] as $type) {
            $template = EcommerceNotificationTemplate::build($base + ['Tipo' => $type]);
            Assert::isTrue('Correos ecommerce', $type . ' tiene plantilla', is_array($template) && $template['subject'] !== '' && $template['message'] !== '');
        }

        $delivered = EcommerceNotificationTemplate::build($base + ['Tipo' => 'PedidoEntregado']);
        Assert::same('Correos ecommerce', 'entrega enlaza comprobante', 'Ver comprobante', $delivered['button'] ?? null);
        Assert::same('Correos ecommerce', 'tipo desconocido no se envía', null, EcommerceNotificationTemplate::build(['Tipo' => 'NoPermitido']));

        $root = dirname(__DIR__, 2);
        $panel = (string) file_get_contents($root . '/lteco-panel/ecommerce/ver.php');
        $panelService = (string) file_get_contents($root . '/src/Application/Ecommerce/PedidoPanelService.php');
        $orders = (string) file_get_contents($root . '/src/Application/Ecommerce/StorefrontOrderService.php');
        $reservation = (string) file_get_contents($root . '/src/Infrastructure/Repository/EcommerceReservationRepository.php');
        $migration = (string) file_get_contents($root . '/database/migrations/2026_07_28_storefront_reservation_concurrency.sql');
        $cron = (string) file_get_contents($root . '/lteco-panel/cron/ecommerce.php');

        Assert::isTrue('Gestión ecommerce', 'detalle muestra auditoría', str_contains($panel, 'Historial y auditoría'));
        Assert::isTrue('Gestión ecommerce', 'correo fallido admite reintento', str_contains($panel, 'reintentar_correo'));
        Assert::isTrue('Gestión ecommerce', 'alta encola reserva', str_contains($orders, "'ReservaCreada'"));
        Assert::isTrue('Gestión ecommerce', 'vencimiento encola aviso', str_contains($reservation, "'ReservaVencida'"));
        Assert::isTrue('Gestión ecommerce', 'envío local protegido', str_contains($cron, 'LTECO_ECOMMERCE_MAIL_ENABLED'));
        Assert::isTrue('Gestión ecommerce', 'correo tiene diagnóstico sin envío', str_contains($cron, '--mail-check'));
        Assert::isTrue('Gestión ecommerce', 'prueba real exige destino explícito', str_contains($cron, '--send-test='));
        Assert::isTrue('Gestión ecommerce', 'entrega activa postventa para todas las unidades', str_contains($panelService, 'foreach ($vehiculos as $idVehiculo)'));
        Assert::isTrue('Gestión ecommerce', 'pago web confirmado envía WhatsApp al cliente', str_contains($panelService, 'enviarWhatsappConfirmacionVentaWeb'));
        Assert::isTrue('Gestión ecommerce', 'WhatsApp web usa texto gratis con ventana 24h', str_contains($panelService, 'enviarWhatsAppTextoGratisConPdo'));
        Assert::isTrue('Gestión ecommerce', 'WhatsApp helper controla ventana gratuita', str_contains((string) file_get_contents($root . '/src/Presentation/Panel/Support/whatsapp.php'), 'whatsappTieneVentanaTextoGratuita'));
        Assert::isTrue('Gestión ecommerce', 'cancelación del panel libera la reserva storefront', str_contains($panelService, "SET Estado='released',LiberadaEn=NOW() WHERE ReservationId=? AND Estado='active'"));
        Assert::isTrue('Concurrencia ecommerce', 'reserva descubre candidatos por variante persistida', str_contains($reservation, 'WHERE StorefrontVariantId IN ({$placeholders})'));
        Assert::isTrue('Concurrencia ecommerce', 'reserva bloquea solo productos candidatos', str_contains($reservation, 'WHERE p.IdProducto IN ({$productPlaceholders})'));
        Assert::isFalse('Concurrencia ecommerce', 'reserva no bloquea el catálogo completo', str_contains($reservation, "ORDER BY p.OrdenWeb,v.IdVehiculo FOR UPDATE"));
        Assert::isTrue('Concurrencia ecommerce', 'variante persistida tiene índice dirigido', str_contains($migration, 'idx_vehiculo_storefront_variant'));
        Assert::isTrue('Concurrencia ecommerce', 'deadlock transitorio reintenta antes de responder', str_contains($reservation, 'if ($attempt < 2)'));

        $releaseOrderPos = strpos($reservation, 'SELECT IdPedido,IdCuenta,Correo,Estado,NumeroPedido FROM ecommerce_pedido');
        $releaseItemsPos = strpos($reservation, "SELECT i.* FROM storefront_reservation_item");
        Assert::isTrue('Concurrencia ecommerce', 'liberación bloquea pedido antes que inventario', $releaseOrderPos !== false && $releaseItemsPos !== false && $releaseOrderPos < $releaseItemsPos);

        $paymentReservationPos = strpos($panelService, "SELECT Estado FROM storefront_reservation");
        $paymentOrderPos = strpos($panelService, "SELECT * FROM ecommerce_pedido WHERE IdPedido=? FOR UPDATE");
        Assert::isTrue('Concurrencia ecommerce', 'pago bloquea reserva antes que pedido', $paymentReservationPos !== false && $paymentOrderPos !== false && $paymentReservationPos < $paymentOrderPos);
    }
}
