<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";
require_once dirname(__DIR__, 2) . "/shared/vehiculo_logic.php";

$estadosPermitidos = ['Disponible', 'Reservado', 'Vendido', 'Oculto', 'Sin stock'];

try {
    requirePost();
    verifyCsrfOrFail();
    $idVehiculo = requestIdString('id');
    $nuevoEstado = trim((string)($_POST['estado'] ?? ''));

    if (!in_array($nuevoEstado, $estadosPermitidos, true)) {
        redirectVehiculosError('Estado no válido.', ['idVehiculo' => $idVehiculo, 'action' => 'cambiar_estado', 'estado' => $nuevoEstado]);
    }

    $pdo->beginTransaction();

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\VehiculoRepository($conexion);
    $service = new Lteco\Application\Vehiculo\VehiculoEstadoService($repo);
    $vehiculo = $service->datosEstado($idVehiculo);

    if (!$vehiculo) {
        throw new RuntimeException('Vehículo no encontrado.');
    }

    $resultado = $service->cambiarEstado($idVehiculo, $vehiculo, $nuevoEstado);

    registrarAuditoria(
        $pdo,
        'CAMBIAR_ESTADO_VEHICULO',
        'Vehículos',
        'Estado de vehículo actualizado: ' . $nuevoEstado,
        [
            'id_vehiculo' => $idVehiculo,
            'id_producto' => (int)$vehiculo['IdProducto'],
            'estado' => $nuevoEstado,
            'stock' => $resultado['stock'],
            'mostrar_en_web' => $resultado['mostrarEnWeb'],
            'destacado_web' => $resultado['destacadoWeb'],
        ]
    );

    $pdo->commit();
    redirectVehiculosSuccess('Estado actualizado correctamente.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectVehiculosError('No se pudo cambiar el estado.', ['idVehiculo' => $_POST['id'] ?? null, 'action' => 'cambiar_estado', 'estado' => $_POST['estado'] ?? null, 'exception' => $e->getMessage()]);
}
