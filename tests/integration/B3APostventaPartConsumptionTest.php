<?php

declare(strict_types=1);

/**
 * B3A - Consumo de repuestos en intervenciones de Postventa.
 *
 * Corre contra lteco_db_poo_test con fixtures sintéticos y cleanup explícito.
 */

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $rel = substr($clase, strlen($prefijo));
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

require_once dirname(__DIR__, 2) . '/lteco-panel/includes/helpers.php';
require_once __DIR__ . '/../Support/PanelTestDb.php';
require_once __DIR__ . '/../Support/PanelTestFixtures.php';

use Lteco\Application\Postventa\PostventaIntervencionService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\PostventaRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  OK {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  FAIL {$nombre}\n";
};

function b3aCleanup(PDO $pdo, string $marker): void
{
    $ventasStmt = $pdo->prepare('SELECT IdVenta FROM venta WHERE Observaciones LIKE ? OR NumeroFactura LIKE ?');
    $ventasStmt->execute([$marker . '%', $marker . '%']);
    $ventas = array_map('intval', $ventasStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($ventas !== []) {
        $in = implode(',', array_fill(0, count($ventas), '?'));
        $histStmt = $pdo->prepare("SELECT IdHistorialTecnico FROM postventa_historial_tecnico WHERE IdVenta IN ({$in})");
        $histStmt->execute($ventas);
        $historial = array_map('intval', $histStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($historial !== []) {
            $hin = implode(',', array_fill(0, count($historial), '?'));
            $pdo->prepare("DELETE FROM postventa_repuesto_usado WHERE IdHistorialTecnico IN ({$hin})")->execute($historial);
            $pdo->prepare("DELETE FROM postventa_historial_tecnico WHERE IdHistorialTecnico IN ({$hin})")->execute($historial);
        }
        $pdo->prepare("DELETE FROM service_vehiculo WHERE IdVenta IN ({$in})")->execute($ventas);
        $pdo->prepare("DELETE FROM garantia WHERE IdVenta IN ({$in})")->execute($ventas);
        $pdo->prepare("DELETE FROM venta_detalle WHERE Venta_IdVenta IN ({$in})")->execute($ventas);
        $pdo->prepare("DELETE FROM venta WHERE IdVenta IN ({$in})")->execute($ventas);
    }

    $prodStmt = $pdo->prepare('SELECT IdProducto FROM producto WHERE Slug LIKE ?');
    $prodStmt->execute(['b1-%-' . $marker . '%']);
    $productos = array_map('intval', $prodStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($productos !== []) {
        $in = implode(',', array_fill(0, count($productos), '?'));
        $vehStmt = $pdo->prepare("SELECT IdVehiculo FROM vehiculo WHERE IdProducto IN ({$in})");
        $vehStmt->execute($productos);
        $vehiculos = $vehStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($vehiculos !== []) {
            $vin = implode(',', array_fill(0, count($vehiculos), '?'));
            $pdo->prepare("DELETE FROM postventa_historial_tecnico WHERE IdVehiculo IN ({$vin})")->execute($vehiculos);
            $pdo->prepare("DELETE FROM service_vehiculo WHERE IdVehiculo IN ({$vin})")->execute($vehiculos);
            $pdo->prepare("DELETE FROM garantia WHERE IdVehiculo IN ({$vin})")->execute($vehiculos);
        }
        $pdo->prepare("DELETE FROM repuesto WHERE IdProducto IN ({$in})")->execute($productos);
        $pdo->prepare("DELETE FROM vehiculo WHERE IdProducto IN ({$in})")->execute($productos);
        $pdo->prepare("DELETE FROM producto WHERE IdProducto IN ({$in})")->execute($productos);
    }

    $pdo->prepare('DELETE FROM cliente WHERE Correo LIKE ?')->execute(['cliente-b1-' . $marker . '%@example.test']);
    $pdo->prepare('DELETE FROM usuario WHERE Usuario LIKE ?')->execute(['b1_%_' . $marker . '%']);
    $pdo->prepare("DELETE FROM panel_idempotency_key WHERE OperationType = 'panel.postventa.intervencion.crear' AND ResultId LIKE ?")->execute([$marker . '%']);
}

function b3aStock(PDO $pdo, int $idProducto): int
{
    $stmt = $pdo->prepare('SELECT Stock FROM producto WHERE IdProducto = ?');
    $stmt->execute([$idProducto]);
    return (int) $stmt->fetchColumn();
}

$pdo = PanelTestDb::connect();
$marker = 'B3A_' . bin2hex(random_bytes(4));
b3aCleanup($pdo, $marker);

try {
    $fixtures = new PanelTestFixtures($pdo);
    $clienteId = $fixtures->cliente($marker);
    $usuarioId = $fixtures->usuario('Administrador', $marker);
    $vehiculo = $fixtures->vehiculo($marker, 0);
    $repuesto = $fixtures->repuesto($marker, 5);

    $pdo->prepare("
        INSERT INTO venta (Cliente_IdCliente, FechaVenta, MetodoPago, TipoCliente, Total, GananciaEstimada, Moneda, Observaciones, EstadoVenta)
        VALUES (?, NOW(), 'Efectivo', 'Final', 1000.00, 100.00, 'UYU', ?, 'Confirmada')
    ")->execute([$clienteId, $marker . '-venta']);
    $ventaId = (int) $pdo->lastInsertId();
    $pdo->prepare("
        INSERT INTO venta_detalle (Venta_IdVenta, Producto_IdProducto, Cantidad, PrecioUnitario, CostoUnitario, Subtotal, GananciaLinea)
        VALUES (?, ?, 1, 1000.00, 100.00, 1000.00, 900.00)
    ")->execute([$ventaId, $vehiculo['idProducto']]);
    $pdo->prepare("
        INSERT INTO service_vehiculo (IdVehiculo, IdVenta, IdCliente, NumeroService, FechaProgramada, Estado, Observaciones)
        VALUES (?, ?, ?, 1, CURDATE(), 'Pendiente', ?)
    ")->execute([$vehiculo['idVehiculo'], $ventaId, $clienteId, $marker]);
    $serviceId = (int) $pdo->lastInsertId();

    $repo = new PostventaRepository(new Connection($pdo));
    $service = new PostventaIntervencionService($repo);
    $operation = 'panel.postventa.intervencion.crear';
    $postPayload = [
        'id_vehiculo' => $vehiculo['idVehiculo'],
        'id_venta' => (string) $ventaId,
        'id_cliente' => (string) $clienteId,
        'id_service' => (string) $serviceId,
        'diagnostico' => $marker . ' consumo normal',
        'solucion' => '',
        'tecnico' => 'B3A Test',
        'estado' => 'Abierta',
        'observaciones' => '',
        'id_repuesto' => (string) $repuesto['idRepuesto'],
        'cantidad_repuesto' => '1',
        'idempotency_key' => bin2hex(random_bytes(16)),
    ];
    $_POST = $postPayload;
    $requestHash = panelIdempotencyRequestHash($operation, $_POST);
    $redirect = '/postventa/detalle.php?id=' . rawurlencode($vehiculo['idVehiculo']);

    echo "B3A caso A/B - consumo normal e idempotencia\n";
    $pdo->beginTransaction();
    $claim = panelIdempotencyClaim($pdo, $operation, $postPayload['idempotency_key'], $requestHash, $usuarioId);
    $check('primer claim permite ejecutar', $claim === null);
    $historialId = $service->guardarIntervencion(
        $vehiculo['idVehiculo'],
        $ventaId,
        $clienteId,
        $serviceId,
        $postPayload['diagnostico'],
        null,
        'B3A Test',
        'Abierta',
        null,
        $repuesto['idRepuesto'],
        1,
        $usuarioId
    );
    panelIdempotencyComplete($pdo, $postPayload['idempotency_key'], 'postventa_intervencion', $marker . ':' . $historialId, $redirect);
    $pdo->commit();

    $stockLuegoConsumo = b3aStock($pdo, $repuesto['idProducto']);
    $check('stock 5 -> 4 luego de consumir 1', $stockLuegoConsumo === 4);

    $pdo->beginTransaction();
    $cached = panelIdempotencyClaim($pdo, $operation, $postPayload['idempotency_key'], $requestHash, $usuarioId);
    $pdo->commit();
    $check('segundo claim retorna resultado completado', is_array($cached) && (string) $cached['RedirectUrl'] === $redirect);
    $check('reintento no baja stock a 3', b3aStock($pdo, $repuesto['idProducto']) === 4);
    $histCount = (int) $pdo->query('SELECT COUNT(*) FROM postventa_historial_tecnico WHERE IdVenta = ' . $ventaId)->fetchColumn();
    $usoCount = (int) $pdo->query('SELECT COUNT(*) FROM postventa_repuesto_usado WHERE IdProducto = ' . (int) $repuesto['idProducto'])->fetchColumn();
    $check('reintento no crea segunda intervención', $histCount === 1);
    $check('reintento no crea segundo uso de repuesto', $usoCount === 1);

    echo "\nB3A caso C - edición\n";
    $root = dirname(__DIR__, 2);
    $supportsEdit = false;
    foreach ([
        $root . '/lteco-panel/postventa/intervencion_editar.php',
        $root . '/lteco-panel/postventa/intervencion_actualizar.php',
    ] as $path) {
        $supportsEdit = $supportsEdit || is_file($path);
    }
    $check('no existe endpoint de edición de intervención', $supportsEdit === false);
    $check('servicio no expone método de edición', !method_exists($service, 'editarIntervencion') && !method_exists($service, 'actualizarIntervencion'));

    echo "\nB3A caso D - stock insuficiente\n";
    $hashInsuficientePayload = $postPayload;
    $hashInsuficientePayload['idempotency_key'] = bin2hex(random_bytes(16));
    $hashInsuficientePayload['diagnostico'] = $marker . ' stock insuficiente';
    $hashInsuficientePayload['cantidad_repuesto'] = '99';
    $_POST = $hashInsuficientePayload;
    $hashInsuficiente = panelIdempotencyRequestHash($operation, $_POST);
    $stockAntesInsuficiente = b3aStock($pdo, $repuesto['idProducto']);
    $histAntesInsuficiente = (int) $pdo->query('SELECT COUNT(*) FROM postventa_historial_tecnico WHERE IdVenta = ' . $ventaId)->fetchColumn();
    $usoAntesInsuficiente = (int) $pdo->query('SELECT COUNT(*) FROM postventa_repuesto_usado WHERE IdProducto = ' . (int) $repuesto['idProducto'])->fetchColumn();
    $rechazado = false;
    try {
        $pdo->beginTransaction();
        panelIdempotencyClaim($pdo, $operation, $hashInsuficientePayload['idempotency_key'], $hashInsuficiente, $usuarioId);
        $service->guardarIntervencion(
            $vehiculo['idVehiculo'],
            $ventaId,
            $clienteId,
            $serviceId,
            $hashInsuficientePayload['diagnostico'],
            null,
            'B3A Test',
            'Abierta',
            null,
            $repuesto['idRepuesto'],
            99,
            $usuarioId
        );
        $pdo->commit();
    } catch (RuntimeException $e) {
        $rechazado = $e->getMessage() === 'No hay stock suficiente del repuesto seleccionado.';
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    $check('stock insuficiente se rechaza', $rechazado);
    $check('stock insuficiente conserva stock', b3aStock($pdo, $repuesto['idProducto']) === $stockAntesInsuficiente);
    $check('stock insuficiente no deja historial parcial', (int) $pdo->query('SELECT COUNT(*) FROM postventa_historial_tecnico WHERE IdVenta = ' . $ventaId)->fetchColumn() === $histAntesInsuficiente);
    $check('stock insuficiente no deja uso parcial', (int) $pdo->query('SELECT COUNT(*) FROM postventa_repuesto_usado WHERE IdProducto = ' . (int) $repuesto['idProducto'])->fetchColumn() === $usoAntesInsuficiente);
    $check('stock insuficiente no deja stock negativo', b3aStock($pdo, $repuesto['idProducto']) >= 0);
} finally {
    $_POST = [];
    b3aCleanup($pdo, $marker);
    echo "\n[B3A cleanup ejecutado]\n";
}

if ($fallos !== []) {
    echo "\nFALLO B3A: " . count($fallos) . " checks fallaron\n";
    foreach ($fallos as $f) {
        echo ' - ' . $f . "\n";
    }
    exit(1);
}

echo "\nOK B3A: {$ok} checks pasaron\n";
