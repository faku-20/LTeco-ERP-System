<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$connection = new \Lteco\Infrastructure\Db\Connection($pdo);
$ventaAnulacion = new \Lteco\Application\Venta\VentaAnulacionService(
    new \Lteco\Infrastructure\Repository\VentaAnulacionRepository($connection)
);

try {
    requirePost();
    verifyCsrfOrFail();

    $idVenta = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $motivo = trim((string)($_POST['motivo'] ?? ''));

    if ($idVenta <= 0) {
        throw new RuntimeException('ID de venta no válido.');
    }

    if ($motivo === '') {
        throw new RuntimeException('Debés ingresar un motivo de anulación.');
    }

    $usuario = usuarioActual();
    $idUsuario = (int)($usuario['IdUsuario'] ?? 0);
    $usuarioAnulacion = trim((string)($usuario['Usuario'] ?? $usuario['NombreCompleto'] ?? ''));
    $idempotencyKey = panelIdempotencyRequestKey();
    $idempotencyHash = panelIdempotencyRequestHash('panel.venta.anular', $_POST);

    $pdo->beginTransaction();
    $idempotencyRow = panelIdempotencyClaim($pdo, 'panel.venta.anular', $idempotencyKey, $idempotencyHash, $idUsuario);
    if ($idempotencyRow !== null) {
        $pdo->commit();
        redirect((string)($idempotencyRow['RedirectUrl'] ?: (panelBaseUrl('ventas/detalle.php') . '?id=' . $idVenta)));
    }

    $resultado = $ventaAnulacion->anular(
        $idVenta,
        $motivo,
        $idUsuario,
        $usuarioAnulacion
    );

    registrarAuditoria(
        $pdo,
        'ANULAR_VENTA',
        'Ventas',
        'Venta #' . $idVenta . ' anulada. Motivo: ' . $motivo,
        array_merge([
            'id_venta' => $idVenta,
            'motivo' => $motivo,
        ], $resultado->paraAuditoria())
    );

    $redirectUrl = panelBaseUrl('ventas/detalle.php') . '?id=' . $idVenta . '&ok=anulada';
    panelIdempotencyComplete($pdo, $idempotencyKey, 'venta_anulacion', $idVenta, $redirectUrl);

    $pdo->commit();

    redirect($redirectUrl);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $idVenta = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $destino = $idVenta > 0 ? panelBaseUrl('ventas/detalle.php') . '?id=' . $idVenta : panelBaseUrl('ventas/index.php');
    logPanelError('venta_anular', $e, ['id_venta' => $_POST['id'] ?? null]);
    $separador = str_contains($destino, '?') ? '&' : '?';
    redirect($destino . $separador . 'error=' . urlencode(mensajeErrorSeguro($e, 'No se pudo anular la venta.')));
}
