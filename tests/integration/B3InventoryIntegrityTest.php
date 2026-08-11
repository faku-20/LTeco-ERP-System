<?php

declare(strict_types=1);

/**
 * BATCH 3 - Integridad de inventario.
 *
 * Ejecuta contra la DB de test aislada. Los escenarios no concurrentes corren
 * dentro de transacción con rollback; los concurrentes usan fixtures marcados y
 * cleanup explícito porque necesitan commits visibles entre procesos.
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

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, sprintf(
            "B3 fatal: %s in %s:%d\n",
            (string) $error['message'],
            (string) $error['file'],
            (int) $error['line']
        ));
    }
});

use Lteco\Application\Distribuidor\DistribuidorStockService;
use Lteco\Application\Distribuidor\DistribuidorVentaService;
use Lteco\Application\Inventario\InventarioReconciliadorService;
use Lteco\Application\Vehiculo\VehiculoCrearService;
use Lteco\Application\Vehiculo\VehiculoEditarService;
use Lteco\Application\Vehiculo\VehiculoEstadoService;
use Lteco\Application\Venta\VentaAnulacionService;
use Lteco\Application\Venta\VentaLineasService;
use Lteco\Application\Venta\VentaPersistenceService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\DistribuidorStockRepository;
use Lteco\Infrastructure\Repository\DistribuidorVentaRepository;
use Lteco\Infrastructure\Repository\VehiculoRepository;
use Lteco\Infrastructure\Repository\VentaAnulacionRepository;
use Lteco\Infrastructure\Repository\VentaRepository;

function b3Pdo(): PDO
{
    return PanelTestDb::connect();
}

function b3TableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function b3Cleanup(PDO $pdo, string $marker): void
{
    $ventasStmt = $pdo->prepare('SELECT IdVenta FROM venta WHERE Observaciones LIKE ? OR NumeroFactura LIKE ?');
    $ventasStmt->execute([$marker . '%', $marker . '%']);
    $ventas = array_map('intval', $ventasStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($ventas !== []) {
        $in = implode(',', array_fill(0, count($ventas), '?'));
        foreach (['service_vehiculo', 'garantia', 'distribuidor_comision', 'gasto'] as $tabla) {
            if (b3TableExists($pdo, $tabla)) {
                $pdo->prepare("DELETE FROM {$tabla} WHERE IdVenta IN ({$in})")->execute($ventas);
            }
        }
        $pdo->prepare("DELETE FROM venta_detalle WHERE Venta_IdVenta IN ({$in})")->execute($ventas);
        $pdo->prepare("DELETE FROM venta WHERE IdVenta IN ({$in})")->execute($ventas);
    }

    $distStmt = $pdo->prepare('SELECT IdDistribuidor FROM distribuidor WHERE Correo LIKE ?');
    $distStmt->execute(['dist-b3-' . $marker . '%@example.test']);
    $distribuidores = array_map('intval', $distStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($distribuidores !== []) {
        $in = implode(',', array_fill(0, count($distribuidores), '?'));
        if (b3TableExists($pdo, 'remito')) {
            $pdo->prepare("DELETE FROM remito WHERE IdDistribuidor IN ({$in})")->execute($distribuidores);
        }
        $pdo->prepare("DELETE FROM distribuidor_stock WHERE IdDistribuidor IN ({$in})")->execute($distribuidores);
        $pdo->prepare("DELETE FROM distribuidor WHERE IdDistribuidor IN ({$in})")->execute($distribuidores);
    }

    $prodStmt = $pdo->prepare('SELECT IdProducto FROM producto WHERE Slug LIKE ?');
    $prodStmt->execute(['b1-%-' . $marker . '%']);
    $productos = array_map('intval', $prodStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($productos !== []) {
        $in = implode(',', array_fill(0, count($productos), '?'));
        if (b3TableExists($pdo, 'service_vehiculo')) {
            $idsVeh = $pdo->prepare("SELECT IdVehiculo FROM vehiculo WHERE IdProducto IN ({$in})");
            $idsVeh->execute($productos);
            $vehiculos = $idsVeh->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if ($vehiculos !== []) {
                $vin = implode(',', array_fill(0, count($vehiculos), '?'));
                $pdo->prepare("DELETE FROM service_vehiculo WHERE IdVehiculo IN ({$vin})")->execute($vehiculos);
                $pdo->prepare("DELETE FROM garantia WHERE IdVehiculo IN ({$vin})")->execute($vehiculos);
            }
        }
        $pdo->prepare("DELETE FROM distribuidor_stock WHERE IdRepuesto IN (SELECT IdRepuesto FROM repuesto WHERE IdProducto IN ({$in})) OR IdVehiculo IN (SELECT IdVehiculo FROM vehiculo WHERE IdProducto IN ({$in}))")
            ->execute([...$productos, ...$productos]);
        $pdo->prepare("DELETE FROM repuesto WHERE IdProducto IN ({$in})")->execute($productos);
        $pdo->prepare("DELETE FROM vehiculo WHERE IdProducto IN ({$in})")->execute($productos);
        $pdo->prepare("DELETE FROM producto WHERE IdProducto IN ({$in})")->execute($productos);
    }

    $pdo->prepare('DELETE FROM cliente WHERE Correo LIKE ?')->execute(['cliente-b1-' . $marker . '%@example.test']);
    $pdo->prepare('DELETE FROM usuario WHERE Usuario LIKE ?')->execute(['b1_%_' . $marker . '%']);
}

function b3Services(PDO $pdo): array
{
    $conn = new Connection($pdo);
    $lineas = new VentaLineasService($conn);

    return [
        new DistribuidorStockService(new DistribuidorStockRepository($conn)),
        new DistribuidorVentaService(new DistribuidorVentaRepository($conn), $lineas),
        new VentaAnulacionService(new VentaAnulacionRepository($conn)),
        $lineas,
        new VentaPersistenceService(new VentaRepository($conn)),
        new VehiculoRepository($conn),
        new InventarioReconciliadorService($conn),
    ];
}

function b3Distribuidor(PDO $pdo, string $suffix): int
{
    $pdo->prepare('INSERT INTO distribuidor (Nombre, Contacto, Telefono, Correo, Activo) VALUES (?, ?, ?, ?, 1)')
        ->execute(['Distribuidor B3 ' . $suffix, 'B3 Test', '092000002', 'dist-b3-' . $suffix . '@example.test']);

    return (int) $pdo->lastInsertId();
}

function b3CrearVentaRepuestoCentral(PDO $pdo, VentaLineasService $lineas, VentaPersistenceService $persistence, int $idCliente, int $idProducto, string $marker, int $cantidad): int
{
    $idVenta = $persistence->crearCabecera([
        'clienteId' => $idCliente,
        'metodoPago' => 'Efectivo',
        'tipoCliente' => 'Final',
        'usuarioVendedorId' => null,
        'moneda' => 'UYU',
        'observaciones' => $marker . '-central-repuesto',
        'tipoCambio' => 1.0,
    ]);
    $lineas->registrarRepuesto([
        'idVenta' => $idVenta,
        'idProducto' => $idProducto,
        'cantidad' => $cantidad,
        'precioUnitario' => 100.00,
        'costoUnitario' => 10.00,
        'subtotal' => 100.00 * $cantidad,
        'gananciaLinea' => 90.00 * $cantidad,
        'moneda' => 'UYU',
        'nuevoStock' => 6 - $cantidad,
        'nuevoEstado' => normalizarEstadoRepuesto('Disponible', 6 - $cantidad),
    ]);

    return $idVenta;
}

function b3WorkerSale(array $argv): void
{
    $pdo = b3Pdo();
    [, , $idStock, $idDistribuidor, $idCliente, $idUsuario, $marker] = $argv;
    [, $ventaService] = b3Services($pdo);
    try {
        $pdo->beginTransaction();
        $preparada = $ventaService->prepararVenta((int) $idStock, (int) $idDistribuidor, 1);
        usleep(300000);
        $venta = $ventaService->registrarVenta($preparada, (int) $idCliente, 'Efectivo', (int) $idUsuario, (string) $marker . '-concurrent-sale', 0.0, 22.0);
        $pdo->commit();
        echo (int) $venta['idVenta'] . PHP_EOL;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo '0|' . $e->getMessage() . PHP_EOL;
    }
}

function b3WorkerAssign(array $argv): void
{
    $pdo = b3Pdo();
    [, , $idDistribuidor, $idRepuesto, $idProducto] = $argv;
    [$stockService] = b3Services($pdo);
    try {
        $pdo->beginTransaction();
        $item = $stockService->itemDisponible('Repuesto', null, (int) $idRepuesto);
        if (!$item) {
            throw new RuntimeException('Item no disponible.');
        }
        usleep(300000);
        $stockService->asignarStock((int) $idDistribuidor, 'Repuesto', null, (int) $idRepuesto, 1, 100.00, 10.00, (int) $idProducto, (int) $item['Stock']);
        $pdo->commit();
        echo '1' . PHP_EOL;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo '0|' . $e->getMessage() . PHP_EOL;
    }
}

if (($argv[1] ?? '') === '--worker-sale') {
    b3WorkerSale($argv);
    exit(0);
}
if (($argv[1] ?? '') === '--worker-assign') {
    b3WorkerAssign($argv);
    exit(0);
}

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

$marker = 'B3_' . bin2hex(random_bytes(4));
$pdo = b3Pdo();
b3Cleanup($pdo, $marker);
$fixtures = new PanelTestFixtures($pdo);
[$stockService, $ventaDistribuidorService, $anulacionService, $lineas, $persistence, $vehiculoRepo, $reconciliador] = b3Services($pdo);

try {
    echo "B3 modelo de stock y anulación\n";
    $pdo->beginTransaction();
    try {
        $idDistribuidor = b3Distribuidor($pdo, $marker . '-modelo');
        $idCliente = $fixtures->cliente($marker . '-modelo');
        $idUsuario = $fixtures->usuario('Administrador', $marker . '-modelo');
        $rep = $fixtures->repuesto($marker . '-modelo', 10);
        $vehDist = $fixtures->vehiculo($marker . '-dist', 1);

        $stockService->asignarStock($idDistribuidor, 'Repuesto', null, $rep['idRepuesto'], 4, 100.00, 10.00, $rep['idProducto'], 10);
        $idStockRep = (int) $pdo->query('SELECT IdStock FROM distribuidor_stock WHERE IdDistribuidor = ' . $idDistribuidor . " AND TipoItem = 'Repuesto' AND IdRepuesto = " . (int) $rep['idRepuesto'])->fetchColumn();
        $check('asignar repuesto consume stock central', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $rep['idProducto'])->fetchColumn() === 6);
        $check('asignar repuesto crea stock distribuidor', (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockRep)->fetchColumn() === 4);

        $preparada = $ventaDistribuidorService->prepararVenta($idStockRep, $idDistribuidor, 3);
        $venta = $ventaDistribuidorService->registrarVenta($preparada, $idCliente, 'Efectivo', $idUsuario, $marker . '-dist-repuesto', 0.0, 22.0);
        $check('venta distribuidor repuesto consume solo stock distribuidor', (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockRep)->fetchColumn() === 1);
        $check('venta distribuidor repuesto no toca stock central asignado', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $rep['idProducto'])->fetchColumn() === 6);

        $anulacionService->anular((int) $venta['idVenta'], $marker . '-anula-repuesto', $idUsuario, 'B3 Test');
        $check('anulación distribuidor repuesto restaura distribuidor_stock', (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockRep)->fetchColumn() === 4);
        $check('anulación distribuidor repuesto no restaura stock central', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $rep['idProducto'])->fetchColumn() === 6);

        $idVentaCentral = b3CrearVentaRepuestoCentral($pdo, $lineas, $persistence, $idCliente, $rep['idProducto'], $marker, 2);
        $check('venta central repuesto descuenta stock central', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $rep['idProducto'])->fetchColumn() === 4);
        $anulacionService->anular($idVentaCentral, $marker . '-anula-central', $idUsuario, 'B3 Test');
        $check('anulación central repuesto restaura stock central', (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $rep['idProducto'])->fetchColumn() === 6);

        $stockService->asignarStock($idDistribuidor, 'Vehiculo', $vehDist['idVehiculo'], null, 1, 1000.00, 100.00, $vehDist['idProducto'], 1);
        $idStockVeh = (int) $pdo->query('SELECT IdStock FROM distribuidor_stock WHERE IdDistribuidor = ' . $idDistribuidor . " AND TipoItem = 'Vehiculo' AND IdVehiculo = " . $pdo->quote($vehDist['idVehiculo']))->fetchColumn();
        $preparadaVeh = $ventaDistribuidorService->prepararVenta($idStockVeh, $idDistribuidor, 1);
        $ventaVeh = $ventaDistribuidorService->registrarVenta($preparadaVeh, $idCliente, 'Efectivo', $idUsuario, $marker . '-dist-vehiculo', 0.0, 22.0);
        $prodVehVendido = $pdo->query('SELECT Stock, Estado FROM producto WHERE IdProducto = ' . (int) $vehDist['idProducto'])->fetch();
        $check('venta distribuidor vehículo marca vendido', (int) $prodVehVendido['Stock'] === 0 && $prodVehVendido['Estado'] === 'Vendido');
        $anulacionService->anular((int) $ventaVeh['idVenta'], $marker . '-anula-vehiculo', $idUsuario, 'B3 Test');
        $prodVehAnulado = $pdo->query('SELECT Stock, Estado FROM producto WHERE IdProducto = ' . (int) $vehDist['idProducto'])->fetch();
        $check('anulación vehículo distribuidor devuelve unidad al distribuidor', (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockVeh)->fetchColumn() === 1);
        $check('anulación vehículo distribuidor no devuelve stock central', (int) $prodVehAnulado['Stock'] === 0 && $prodVehAnulado['Estado'] === 'Sin stock');

        $vehCentral = $fixtures->vehiculo($marker . '-central', 1);
        $idVentaVehCentral = $persistence->crearCabecera([
            'clienteId' => $idCliente,
            'metodoPago' => 'Efectivo',
            'tipoCliente' => 'Final',
            'usuarioVendedorId' => null,
            'moneda' => 'UYU',
            'observaciones' => $marker . '-central-vehiculo',
            'tipoCambio' => 1.0,
        ]);
        $lineas->registrarVehiculo([
            'idVenta' => $idVentaVehCentral,
            'idProducto' => $vehCentral['idProducto'],
            'idVehiculo' => $vehCentral['idVehiculo'],
            'clienteId' => $idCliente,
            'precioUnitario' => 1000.00,
            'costoUnitario' => 100.00,
            'subtotal' => 1000.00,
            'gananciaLinea' => 900.00,
            'moneda' => 'UYU',
        ]);
        $prodCentralVendido = $pdo->query('SELECT Stock, Estado FROM producto WHERE IdProducto = ' . (int) $vehCentral['idProducto'])->fetch();
        $check('venta real de vehículo sigue marcando Vendido', (int) $prodCentralVendido['Stock'] === 0 && $prodCentralVendido['Estado'] === 'Vendido');

        $estadoService = new VehiculoEstadoService($vehiculoRepo);
        $crearService = new VehiculoCrearService($vehiculoRepo);
        $editarService = new VehiculoEditarService($vehiculoRepo);
        foreach ([
            'cambio estado manual' => static fn() => $estadoService->cambiarEstado($vehCentral['idVehiculo'], ['IdProducto' => $vehCentral['idProducto'], 'MostrarEnWeb' => 0, 'DestacadoWeb' => 0], 'Vendido'),
            'creación manual' => static fn() => $crearService->crear(['estado' => 'Vendido']),
            'edición manual' => static fn() => $editarService->editar(['estado' => 'Vendido']),
        ] as $nombre => $fn) {
            $bloqueado = false;
            try {
                $fn();
            } catch (RuntimeException $e) {
                $bloqueado = strpos($e->getMessage(), 'venta registrada') !== false;
            }
            $check('bloquea Vendido manual: ' . $nombre, $bloqueado);
        }

        $limpio = $reconciliador->ejecutar();
        $erroresLimpios = array_filter($limpio, static fn(array $r): bool => $r['severity'] === 'ERROR' && $r['count'] > 0);
        $check('reconciliador no detecta errores en fixtures consistentes', $erroresLimpios === []);

        $inconsistente = $fixtures->vehiculo($marker . '-inconsistente', 0);
        $pdo->prepare("UPDATE producto SET Estado = 'Vendido', Stock = 0 WHERE IdProducto = ?")->execute([$inconsistente['idProducto']]);
        $resultado = $reconciliador->ejecutar();
        $check('reconciliador detecta vehículo vendido sin venta', ($resultado['vehiculos_vendidos_sin_venta']['count'] ?? 0) >= 1);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "[B3 rollback modelo ejecutado]\n";
    }

    echo "\nB3 concurrencia\n";
    $idDistribuidor = b3Distribuidor($pdo, $marker . '-conc');
    $idCliente = $fixtures->cliente($marker . '-conc');
    $idUsuario = $fixtures->usuario('Administrador', $marker . '-conc');
    $repVenta = $fixtures->repuesto($marker . '-conc-sale', 0);
    $pdo->prepare("UPDATE producto SET Stock = 0, Estado = 'Sin stock' WHERE IdProducto = ?")->execute([$repVenta['idProducto']]);
    $pdo->prepare("INSERT INTO distribuidor_stock (IdDistribuidor, TipoItem, IdVehiculo, IdRepuesto, Cantidad, PrecioVenta, PrecioMinimo) VALUES (?, 'Repuesto', NULL, ?, 1, 100.00, 10.00)")
        ->execute([$idDistribuidor, $repVenta['idRepuesto']]);
    $idStockVenta = (int) $pdo->lastInsertId();

    $script = __FILE__;
    $envPrefix = '';
    foreach (['LTECO_ENV', 'LTECO_TEST_DB_ALLOW', 'LTECO_TEST_DB_HOST', 'LTECO_TEST_DB_NAME', 'LTECO_TEST_DB_USER', 'LTECO_TEST_DB_PASSWORD'] as $name) {
        $envPrefix .= $name . '=' . escapeshellarg((string) getenv($name)) . ' ';
    }
    $cmdSale = $envPrefix . 'php ' . escapeshellarg($script) . ' --worker-sale ' . $idStockVenta . ' ' . $idDistribuidor . ' ' . $idCliente . ' ' . $idUsuario . ' ' . escapeshellarg($marker);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p1 = proc_open($cmdSale, $descriptors, $pipes1);
    usleep(100000);
    $p2 = proc_open($cmdSale, $descriptors, $pipes2);
    if (!is_resource($p1) || !is_resource($p2)) {
        throw new RuntimeException('No se pudieron iniciar workers de venta B3.');
    }
    $out1 = trim(stream_get_contents($pipes1[1]));
    $err1 = trim(stream_get_contents($pipes1[2]));
    $code1 = proc_close($p1);
    $out2 = trim(stream_get_contents($pipes2[1]));
    $err2 = trim(stream_get_contents($pipes2[2]));
    $code2 = proc_close($p2);
    if ($err1 !== '') {
        echo "worker sale 1 stderr: {$err1}\n";
    }
    if ($err2 !== '') {
        echo "worker sale 2 stderr: {$err2}\n";
    }
    $ventasCreadas = array_values(array_filter([(int) $out1, (int) $out2], static fn(int $id): bool => $id > 0));
    $check('workers venta distribuidor terminan controlados', $code1 === 0 && $code2 === 0);
    $check('última unidad distribuidor se vende una sola vez', count($ventasCreadas) === 1);
    $check('stock distribuidor no queda negativo', (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockVenta)->fetchColumn() === 0);

    $repAssign = $fixtures->repuesto($marker . '-conc-assign', 1);
    $cmdAssign = $envPrefix . 'php ' . escapeshellarg($script) . ' --worker-assign ' . $idDistribuidor . ' ' . (int) $repAssign['idRepuesto'] . ' ' . (int) $repAssign['idProducto'];
    $a1 = proc_open($cmdAssign, $descriptors, $pipesA1);
    usleep(100000);
    $a2 = proc_open($cmdAssign, $descriptors, $pipesA2);
    if (!is_resource($a1) || !is_resource($a2)) {
        throw new RuntimeException('No se pudieron iniciar workers de asignación B3.');
    }
    $outA1 = trim(stream_get_contents($pipesA1[1]));
    $errA1 = trim(stream_get_contents($pipesA1[2]));
    $codeA1 = proc_close($a1);
    $outA2 = trim(stream_get_contents($pipesA2[1]));
    $errA2 = trim(stream_get_contents($pipesA2[2]));
    $codeA2 = proc_close($a2);
    if ($errA1 !== '') {
        echo "worker assign 1 stderr: {$errA1}\n";
    }
    if ($errA2 !== '') {
        echo "worker assign 2 stderr: {$errA2}\n";
    }
    $assignOk = array_values(array_filter([(int) $outA1, (int) $outA2], static fn(int $v): bool => $v === 1));
    $stockCentralAssign = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $repAssign['idProducto'])->fetchColumn();
    $stockDistribuidorAssign = (int) $pdo->query('SELECT COALESCE(SUM(Cantidad), 0) FROM distribuidor_stock WHERE IdDistribuidor = ' . $idDistribuidor . " AND TipoItem = 'Repuesto' AND IdRepuesto = " . (int) $repAssign['idRepuesto'])->fetchColumn();
    $check('workers asignación terminan controlados', $codeA1 === 0 && $codeA2 === 0);
    $check('última unidad central se asigna una sola vez', count($assignOk) === 1);
    $check('asignación concurrente deja stock central cero', $stockCentralAssign === 0);
    $check('asignación concurrente deja una unidad asignada', $stockDistribuidorAssign === 1);
} finally {
    b3Cleanup($pdo, $marker);
    echo "[B3 cleanup ejecutado]\n";
}

if ($fallos !== []) {
    echo "\nFALLO B3: " . count($fallos) . " checks fallaron\n";
    foreach ($fallos as $f) {
        echo ' - ' . $f . "\n";
    }
    exit(1);
}

echo "\nOK B3: {$ok} checks pasaron\n";
