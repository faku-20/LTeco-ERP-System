<?php

declare(strict_types=1);

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

require_once __DIR__ . '/../Support/PanelTestDb.php';
require_once __DIR__ . '/../Support/PanelTestFixtures.php';
require_once dirname(__DIR__, 2) . '/lteco-panel/includes/helpers.php';

use Lteco\Application\Venta\VentaLineasService;
use Lteco\Application\Venta\VentaAnulacionService;
use Lteco\Application\Venta\VentaPersistenceService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaAnulacionRepository;
use Lteco\Infrastructure\Repository\VentaRepository;

try {
    $pdo = PanelTestDb::connect();
} catch (Throwable $e) {
    fwrite(STDERR, 'B1PanelSafetyNetTest aborted: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
$fixtures = new PanelTestFixtures($pdo);
$failures = [];
$ok = 0;
$check = static function (string $label, bool $condition) use (&$failures, &$ok): void {
    if ($condition) {
        $ok++;
        echo "  OK {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL {$label}\n";
};

$suffix = bin2hex(random_bytes(4));
$pdo->beginTransaction();
try {
    $clienteId = $fixtures->cliente($suffix);
    $vendedorId = $fixtures->usuario('Vendedor', $suffix);
    $vehiculo = $fixtures->vehiculo($suffix, 1);
    $repuesto = $fixtures->repuesto($suffix, 2);

    $conn = new Connection($pdo);
    $persistence = new VentaPersistenceService(new VentaRepository($conn));
    $lineas = new VentaLineasService($conn);
    $anulacion = new VentaAnulacionService(new VentaAnulacionRepository($conn));

    $idVenta = $persistence->crearCabecera([
        'clienteId' => $clienteId,
        'metodoPago' => 'Efectivo',
        'tipoCliente' => 'Final',
        'usuarioVendedorId' => $vendedorId,
        'moneda' => 'UYU',
        'observaciones' => 'B1 safety net',
        'tipoCambio' => 1.0,
    ]);
    $persistence->asignarNumeroFactura($idVenta, 'B1-' . $suffix);

    $lineas->registrarVehiculo([
        'idVenta' => $idVenta,
        'idProducto' => $vehiculo['idProducto'],
        'precioUnitario' => 1000.0,
        'costoUnitario' => 100.0,
        'subtotal' => 1000.0,
        'gananciaLinea' => 900.0,
        'moneda' => 'UYU',
        'idVehiculo' => $vehiculo['idVehiculo'],
        'clienteId' => $clienteId,
    ]);
    $lineas->registrarRepuesto([
        'idVenta' => $idVenta,
        'idProducto' => $repuesto['idProducto'],
        'idRepuesto' => $repuesto['idRepuesto'],
        'cantidad' => 1,
        'precioUnitario' => 100.0,
        'costoUnitario' => 10.0,
        'subtotal' => 100.0,
        'gananciaLinea' => 90.0,
        'moneda' => 'UYU',
        'nuevoStock' => 1,
        'nuevoEstado' => 'Disponible',
    ]);

    $check('venta creada', $idVenta > 0);
    $check('detalle venta creado', (int) $pdo->query('SELECT COUNT(*) FROM venta_detalle WHERE Venta_IdVenta = ' . $idVenta)->fetchColumn() === 2);
    $check('vehiculo vendido', (string) $pdo->query('SELECT Estado FROM producto WHERE IdProducto = ' . (int) $vehiculo['idProducto'])->fetchColumn() === 'Vendido');
    $check('repuesto descuenta stock', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $repuesto['idProducto'])->fetchColumn() === 1);
    $check('garantia creada', (int) $pdo->query('SELECT COUNT(*) FROM garantia WHERE IdVenta = ' . $idVenta)->fetchColumn() === 1);
    $check('services creados', (int) $pdo->query('SELECT COUNT(*) FROM service_vehiculo WHERE IdVenta = ' . $idVenta)->fetchColumn() === 4);

    $beforeStock = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $vehiculo['idProducto'])->fetchColumn();
    $anulacion->anular($idVenta, 'B1 rollback test', $vendedorId, 'b1_test_runner');
    $check('venta anulada', (string) $pdo->query('SELECT EstadoVenta FROM venta WHERE IdVenta = ' . $idVenta)->fetchColumn() === 'Anulada');
    $check('stock vehiculo restaurado', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $vehiculo['idProducto'])->fetchColumn() >= $beforeStock);
    $check('garantia anulada', (int) $pdo->query("SELECT COUNT(*) FROM garantia WHERE IdVenta = {$idVenta} AND Estado = 'Anulada'")->fetchColumn() === 1);
    $check('services cancelados', (int) $pdo->query("SELECT COUNT(*) FROM service_vehiculo WHERE IdVenta = {$idVenta} AND Estado = 'Cancelado'")->fetchColumn() === 4);
} finally {
    $pdo->rollBack();
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "B1PanelSafetyNetTest OK ({$ok} assertions)\n";
