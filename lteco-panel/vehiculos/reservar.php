<?php
$pageTitle = "Reservar vehículo | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";
require_once dirname(__DIR__, 2) . "/shared/vehiculo_logic.php";

try {
    $idVehiculo = requestIdString('id');
} catch (Throwable $e) {
    redirectVehiculosError('ID de vehículo no recibido.', ['action' => 'reservar']);
}

$mensaje = '';
$errores = [];
$clienteReservaId = null;
$seniaReserva = null;

$conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
$repo = new Lteco\Infrastructure\Repository\VehiculoRepository($conexion);
$service = new Lteco\Application\Vehiculo\VehiculoEstadoService($repo);
$clienteCrud = new \Lteco\Application\Cliente\ClienteCrudService(
    new \Lteco\Infrastructure\Repository\ClienteCrudRepository($conexion)
);
$vehiculo = $service->datosReserva($idVehiculo);

if (!$vehiculo) {
    redirectVehiculosError('Vehículo no encontrado.', ['idVehiculo' => $idVehiculo, 'action' => 'reservar']);
}

if ($vehiculo['Estado'] === 'Vendido') {
    redirectVehiculosError('Este vehículo ya está vendido.', ['idVehiculo' => $idVehiculo, 'action' => 'reservar']);
}

$clientes = $clienteCrud->listarParaSelector();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        logPanelError('reservar_csrf', $e, ['idVehiculo' => $idVehiculo] + vehicleActionContext());
        $errores[] = 'La sesión del formulario venció. Volvé a intentar.';
    }

    $clienteReservaId = !empty($_POST['cliente_reserva_id']) ? (int)$_POST['cliente_reserva_id'] : null;
    $seniaReserva = ($_POST['senia_reserva'] ?? '') !== '' ? decimalNoNegativo($_POST['senia_reserva']) : null;

    if (!$clienteReservaId) {
        $errores[] = 'Tenés que seleccionar un cliente.';
    } else {
        if (!$clienteCrud->existe($clienteReservaId)) {
            $errores[] = 'El cliente seleccionado no existe.';
        }
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $resultado = $service->reservar($idVehiculo, $vehiculo, $clienteReservaId, $seniaReserva);

            registrarAuditoria(
                $pdo,
                'RESERVAR_VEHICULO',
                'Vehículos',
                'Vehículo reservado: ' . (string)($vehiculo['Modelo'] ?? $idVehiculo),
                [
                    'id_vehiculo' => $idVehiculo,
                    'id_producto' => (int)$vehiculo['IdProducto'],
                    'cliente_reserva_id' => $clienteReservaId,
                    'senia_reserva' => $seniaReserva,
                    'estado' => 'Reservado',
                    'mostrar_en_web' => $resultado['mostrarEnWeb'],
                    'destacado_web' => $resultado['destacadoWeb'],
                ]
            );

            $pdo->commit();
            redirectVehiculosSuccess('Reserva guardada correctamente.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logPanelError('reservar', $e, ['idVehiculo' => $idVehiculo] + vehicleActionContext());
            $errores[] = 'Error al reservar. Revisá el log del sistema.';
        }
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <h1>Reservar vehículo</h1>
        <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Volver</a>
    </div>

    <div class="section-box">
        <?php if ($mensaje): ?><div class="notice"><?= h($mensaje, '') ?></div><?php endif; ?>
        <?php if ($errores): ?><div class="notice notice--error"><ul class="notice-list"><?php foreach ($errores as $error): ?><li><?= h($error, '') ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <p><strong>ID:</strong> <?= h($vehiculo['IdVehiculo'], '') ?></p>
        <p><strong>Modelo:</strong> <?= h($vehiculo['Modelo']) ?></p>
        <p><strong>Número de motor:</strong> <?= h($vehiculo['NumeroMotor']) ?></p>
        <p><strong>Color:</strong> <?= h($vehiculo['Color']) ?></p>
        <p><strong>Estado actual:</strong> <?= h($vehiculo['Estado']) ?></p>

        <form method="POST">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="cliente_reserva_id" required>
                        <option value="">-- Seleccionar cliente --</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= h($cliente['IdCliente'], '') ?>" <?= (string)$clienteReservaId === (string)$cliente['IdCliente'] ? 'selected' : '' ?>>
                                <?= h($cliente['NombreApellido']) ?> - <?= h($cliente['Telefono']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Seña</label>
                    <input type="number" step="0.01" min="0" name="senia_reserva" placeholder="Ej: 5000" value="<?= $seniaReserva !== null ? h((string)$seniaReserva, '') : '' ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Guardar reserva</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
