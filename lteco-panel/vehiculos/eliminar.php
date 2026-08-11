<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";

requiereLogin();
requiereAdmin();

try {
    requirePost();
    verifyCsrfOrFail();

    $idVehiculo = trim((string)($_POST['id'] ?? ''));
    if ($idVehiculo === '') {
        throw new RuntimeException('ID de vehículo no recibido.');
    }

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\VehiculoRepository($conexion);
    $service = new Lteco\Application\Vehiculo\VehiculoEstadoService($repo);
    $data = $service->datosOcultar($idVehiculo);

    if (!$data) {
        throw new RuntimeException('Vehículo no encontrado.');
    }

    $service->ocultar((int)$data['IdProducto']);

    registrarAuditoria($pdo, 'OCULTAR_VEHICULO', 'Vehículos', 'Vehículo ocultado: ' . (string)($data['Modelo'] ?? $idVehiculo), [
        'id_vehiculo' => $idVehiculo,
        'id_producto' => (int)$data['IdProducto'],
        'numero_motor' => $data['NumeroMotor'] ?? null,
    ]);

    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'success', 'Vehículo ocultado correctamente.');
} catch (Throwable $e) {
    logPanelError('ocultar_vehiculo', $e, vehicleActionContext());
    redirectErrorSeguro(panelBaseUrl('vehiculos/index.php'), $e, 'No se pudo ocultar el vehículo.');
}
