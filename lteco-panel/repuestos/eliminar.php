<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Repuesto\RepuestoCrudService(
    new \Lteco\Infrastructure\Repository\RepuestoCrudRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

try {
    requirePost();
    verifyCsrfOrFail();

    $idRepuesto = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($idRepuesto <= 0) {
        throw new RuntimeException('ID de repuesto no válido.');
    }

    $data = $service->repuestoParaOcultar($idRepuesto);

    if (!$data) {
        throw new RuntimeException('Repuesto no encontrado.');
    }

    $service->ocultar((int)$data['IdProducto']);

    registrarAuditoria($pdo, 'OCULTAR_REPUESTO', 'Repuestos', 'Repuesto ocultado: ' . (string)$data['Nombre'], [
        'id_repuesto' => $idRepuesto,
        'id_producto' => (int)$data['IdProducto'],
    ]);

    redirectWithFlash(panelBaseUrl('repuestos/index.php'), 'success', 'Repuesto ocultado correctamente.');
} catch (Throwable $e) {
    logPanelError('ocultar_repuesto', $e, vehicleActionContext());
    redirectErrorSeguro(panelBaseUrl('repuestos/index.php'), $e, 'No se pudo ocultar el repuesto.');
}
