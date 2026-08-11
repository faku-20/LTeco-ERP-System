<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requiereLogin();
requiereSuperadmin();

try {
    requirePost();
    verifyCsrfOrFail();

    $idVehiculo = trim((string)($_POST['id'] ?? ''));
    $direccion = trim((string)($_POST['direccion'] ?? ''));

    if ($idVehiculo === '') {
        throw new RuntimeException('ID de vehículo inválido.');
    }

    if (!in_array($direccion, ['up', 'down'], true)) {
        throw new RuntimeException('Dirección de orden inválida.');
    }

    $pdo->beginTransaction();
    $service = new \Lteco\Application\Vehiculo\VehiculoEstadoService(
        new \Lteco\Infrastructure\Repository\VehiculoRepository(
            \Lteco\Infrastructure\Db\Connection::desdeGlobal()
        )
    );
    $movido = $service->moverOrdenWeb($idVehiculo, $direccion);

    if (!$movido) {
        $pdo->rollBack();
        redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'info', 'Ese vehículo ya está en el extremo del orden web.');
    }
    $pdo->commit();

    registrarAuditoria(
        $pdo,
        'MOVER_ORDEN_VEHICULO_WEB',
        'Vehículos',
        'Orden web de vehículo actualizado.',
        [
            'id_vehiculo' => $idVehiculo,
            'direccion' => $direccion,
        ]
    );

    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'success', 'Orden web actualizado correctamente.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectErrorSeguro(panelBaseUrl('vehiculos/index.php'), $e, 'No se pudo reordenar el vehículo.');
}
