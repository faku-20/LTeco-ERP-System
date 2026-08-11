<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requiereModulo('ventas');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/whatsapp.php';

try {
    requirePost();
    verifyCsrfOrFail();

    $idVenta = isset($_POST['id_venta']) ? (int)$_POST['id_venta'] : 0;
    if ($idVenta <= 0) {
        throw new RuntimeException('ID de venta inválido.');
    }

    requierePuedeVerRegistro('venta', $idVenta);

    $ventaQuery = new \Lteco\Application\Venta\VentaQueryService(
        new \Lteco\Infrastructure\Repository\VentaReadRepository(
            \Lteco\Infrastructure\Db\Connection::desdeGlobal()
        )
    );
    $datosWhatsapp = $ventaQuery->datosWhatsapp($idVenta);
    $venta = $datosWhatsapp['venta'];

    if (!$venta) {
        throw new RuntimeException('Venta no encontrada.');
    }

    if (($venta['EstadoVenta'] ?? 'Confirmada') === 'Anulada') {
        throw new RuntimeException('No se puede reenviar WhatsApp de una venta anulada.');
    }

    if (empty($venta['Telefono'])) {
        redirectWithFlash(
            panelBaseUrl('ventas/detalle.php?id=' . $idVenta),
            'error',
            'El cliente no tiene teléfono registrado.'
        );
    }

    $detallesVenta = $ventaQuery->detalles($idVenta);
    $datosPostventa = $ventaQuery->garantiaYServices($idVenta, $detallesVenta);

    $fmtFechaWa = static function (?string $fecha): string {
        if (!$fecha) {
            return 'No aplica';
        }
        try {
            return (new DateTimeImmutable($fecha))->format('d/m/Y');
        } catch (Throwable) {
            return 'No aplica';
        }
    };

    $waGarantiaHasta = $fmtFechaWa($datosPostventa['garantiaFin'] ?? null);
    $waServices = array_map(
        static fn($fecha) => $fmtFechaWa((string)$fecha),
        $datosPostventa['serviceDates'] ?? []
    );
    $waServices = array_pad(array_slice($waServices, 0, 4), 4, 'No aplica');

    $cfg = whatsappObtenerConfig($pdo);

    if (empty($cfg['tpl_venta'])) {
        redirectWithFlash(
            panelBaseUrl('ventas/detalle.php?id=' . $idVenta),
            'error',
            'El template de venta de WhatsApp no está configurado.'
        );
    }

    $mensajeTextoGratis = \Lteco\Support\VentaView::mensajePostventa(
        $venta,
        $datosPostventa['garantiaFin'] ?? null,
        $datosPostventa['serviceDates'] ?? []
    );

    $okTextoGratis = enviarWhatsAppTextoGratisConPdo(
        $pdo,
        $venta['Telefono'],
        $mensajeTextoGratis,
        $idVenta,
        'compra_confirmada_cliente'
    );

    if ($okTextoGratis) {
        redirectWithFlash(
            panelBaseUrl('ventas/detalle.php?id=' . $idVenta),
            'success',
            'Mensaje de WhatsApp reenviado correctamente como respuesta dentro de la ventana gratuita.'
        );
    }

    $ok = enviarWhatsAppTemplate(
        $venta['Telefono'],
        $cfg['tpl_venta'],
        [
            $venta['NombreApellido'] ?? 'Cliente',
            $venta['NumeroFactura'] ?: ('Venta #' . $idVenta),
            $waGarantiaHasta,
            $waServices[0],
            $waServices[1],
            $waServices[2],
            $waServices[3],
        ],
        'venta',
        $idVenta
    );

    if ($ok) {
        redirectWithFlash(
            panelBaseUrl('ventas/detalle.php?id=' . $idVenta),
            'success',
            'Mensaje de WhatsApp reenviado correctamente.'
        );
    } else {
        $detalle = whatsappResumenUltimoError($pdo, 'venta', $idVenta, $cfg['tpl_venta']);
        if ($detalle !== '') {
            logPanelError('whatsapp_reenviar_meta', $detalle, [
                'id_venta' => $idVenta,
                'template' => $cfg['tpl_venta'],
                'telefono_normalizado' => whatsappFormatearTelefono($venta['Telefono']),
            ]);
        }

        redirectWithFlash(
            panelBaseUrl('ventas/detalle.php?id=' . $idVenta),
            'error',
            'No se pudo enviar el mensaje de WhatsApp.' . ($detalle !== '' ? ' Meta respondió: ' . $detalle : ' Revisá credenciales y template.')
        );
    }
} catch (Throwable $e) {
    $idVenta = isset($idVenta) && $idVenta > 0 ? $idVenta : 0;
    logPanelError('whatsapp_reenviar', $e);
    $mensaje = ($e instanceof RuntimeException) ? $e->getMessage() : 'Error al reenviar el mensaje de WhatsApp.';
    if ($idVenta > 0) {
        redirectWithFlash(panelBaseUrl('ventas/detalle.php?id=' . $idVenta), 'error', $mensaje);
    } else {
        redirectWithFlash(panelBaseUrl('ventas/index.php'), 'error', $mensaje);
    }
}
