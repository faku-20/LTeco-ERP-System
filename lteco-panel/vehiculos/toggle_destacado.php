<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereSuperadmin();
require_once __DIR__ . "/../includes/helpers.php";
require_once dirname(__DIR__, 2) . "/shared/vehiculo_logic.php";

try {
    requirePost();
    verifyCsrfOrFail();
    $idVehiculo = requestIdString('id');

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\VehiculoRepository($conexion);
    $service = new Lteco\Application\Vehiculo\VehiculoEstadoService($repo);
    $data = $service->datosPublicacion($idVehiculo);

    if (!$data) {
        redirectVehiculosError('Vehículo no encontrado.', ['idVehiculo' => $idVehiculo, 'action' => 'toggle_destacado']);
    }

    $resultado = $service->toggleDestacado($data);

    if (!$resultado['success']) {
        redirectVehiculosError($resultado['mensaje'], [
            'idVehiculo' => $idVehiculo,
            'action' => $resultado['accion'],
            'faltantes' => $resultado['faltantes'] ?? [],
        ]);
    }

    $mensaje = $resultado['mensaje'];
    $nuevoValor = $resultado['nuevoValor'];
    registrarAuditoria($pdo, $nuevoValor === 1 ? 'DESTACAR_VEHICULO_WEB' : 'QUITAR_DESTACADO_WEB', 'Vehículos', $mensaje, ['id_vehiculo' => $idVehiculo, 'id_producto' => (int)$data['IdProducto']]);
    redirectVehiculosSuccess($mensaje);
} catch (Throwable $e) {
    redirectVehiculosError('No se pudo actualizar el destacado.', ['idVehiculo' => $_POST['id'] ?? null, 'action' => 'toggle_destacado', 'exception' => $e->getMessage()]);
}
