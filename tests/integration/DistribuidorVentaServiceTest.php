<?php

declare(strict_types=1);

/**
 * Integración C2 completa. Todo ocurre dentro de una transacción con rollback.
 *
 * Verifica preparación con lock, persistencia legacy de venta/detalle,
 * descuento de stock de distribuidor, efectos de vehículo y
 * facturación del remito.
 */

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $relativo = substr($clase, strlen($prefijo));
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

use Lteco\Application\Distribuidor\DistribuidorVentaService;
use Lteco\Application\Venta\VentaLineasService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\DistribuidorVentaRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $cond) use (&$fallos, &$ok): void {
    if ($cond) {
        $ok++;
        echo "  \xE2\x9C\x93 {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  \xE2\x9C\x97 {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$db   = getenv('LTECO_DB_NAME') ?: 'lteco_db';
$user = getenv('LTECO_DB_USER') ?: 'lteco_user';
$pass = getenv('LTECO_DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$tablaExiste = static function (string $tabla) use ($pdo, $db): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$db, $tabla]);
    return (int) $stmt->fetchColumn() > 0;
};

echo "Conectado a {$db}@{$host}. Abriendo transacción (se hará ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    $idDistribuidor = (int) $pdo->query('SELECT IdDistribuidor FROM distribuidor ORDER BY IdDistribuidor LIMIT 1')->fetchColumn();
    $idCliente = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();
    $idUsuario = (int) $pdo->query('SELECT IdUsuario FROM usuario ORDER BY IdUsuario LIMIT 1')->fetchColumn();
    $repuesto = $pdo->query(
        'SELECT r.IdRepuesto, p.IdProducto
         FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
         ORDER BY r.IdRepuesto LIMIT 1'
    )->fetch();
    $vehiculo = $pdo->query(
        'SELECT v.IdVehiculo, p.IdProducto
         FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
         ORDER BY v.IdVehiculo LIMIT 1'
    )->fetch();

    if ($idDistribuidor <= 0 || $idCliente <= 0 || $idUsuario <= 0 || !$repuesto || !$vehiculo) {
        throw new RuntimeException('Faltan datos base de distribuidor/cliente/usuario/repuesto/vehículo.');
    }

    $conn = new Connection($pdo);
    $service = new DistribuidorVentaService(
        new DistribuidorVentaRepository($conn),
        new VentaLineasService($conn)
    );

    $idRepuesto = (int) $repuesto['IdRepuesto'];
    $idProductoRep = (int) $repuesto['IdProducto'];
    $idVehiculo = (string) $vehiculo['IdVehiculo'];
    $idProductoVeh = (int) $vehiculo['IdProducto'];

    $pdo->prepare("UPDATE distribuidor SET ComisionPct = 6.67 WHERE IdDistribuidor = ?")
        ->execute([$idDistribuidor]);
    $pdo->prepare("UPDATE producto SET Stock = 20, Estado = 'Disponible' WHERE IdProducto = ?")
        ->execute([$idProductoRep]);
    $pdo->prepare("UPDATE producto SET Stock = 1, Estado = 'Disponible' WHERE IdProducto = ?")
        ->execute([$idProductoVeh]);
    $pdo->prepare('DELETE FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = ? AND IdRepuesto = ?')
        ->execute([$idDistribuidor, 'Repuesto', $idRepuesto]);
    $pdo->prepare('DELETE FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = ? AND IdVehiculo = ?')
        ->execute([$idDistribuidor, 'Vehiculo', $idVehiculo]);

    $pdo->prepare("
        INSERT INTO distribuidor_stock
            (IdDistribuidor, TipoItem, IdVehiculo, IdRepuesto, Cantidad, PrecioVenta, PrecioMinimo)
        VALUES (?, 'Repuesto', NULL, ?, 5, 1234.56, 789.12)
    ")->execute([$idDistribuidor, $idRepuesto]);
    $idStockRep = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO distribuidor_stock
            (IdDistribuidor, TipoItem, IdVehiculo, IdRepuesto, Cantidad, PrecioVenta, PrecioMinimo)
        VALUES (?, 'Vehiculo', ?, NULL, 1, 63000, 12000)
    ")->execute([$idDistribuidor, $idVehiculo]);
    $idStockVeh = (int) $pdo->lastInsertId();

    if ($tablaExiste('remito')) {
        $pdo->prepare("
            INSERT INTO remito
                (IdDistribuidor, IdPedido, TipoItem, IdVehiculo, IdRepuesto, Cantidad, Estado)
            VALUES (?, 0, 'Repuesto', NULL, ?, 5, 'Pendiente')
        ")->execute([$idDistribuidor, $idRepuesto]);
    }

    // Repuesto: cantidad > 1, ambas comisiones e IVA incluido.
    $preparada = $service->prepararVenta($idStockRep, $idDistribuidor, 3);
    $venta = $service->registrarVenta(
        $preparada,
        $idCliente,
        'Efectivo',
        $idUsuario,
        null,
        2.5,
        22.0
    );

    $check('repuesto crea IdVenta', (int) $venta['idVenta'] > 0);
    $check('repuesto subtotal legacy', abs((float) $venta['subtotal'] - 3703.68) < 0.005);
    $check('repuesto ganancia legacy', abs((float) $venta['ganancia'] - 328.81) < 0.005);

    $stmtVenta = $pdo->prepare('SELECT * FROM venta WHERE IdVenta = ?');
    $stmtVenta->execute([$venta['idVenta']]);
    $filaVenta = $stmtVenta->fetch();
    $check('cabecera distribuidor vendedor', (int) $filaVenta['DistribuidorVendedorId'] === $idDistribuidor);
    $check('cabecera usuario vendedor', (int) $filaVenta['UsuarioVendedorId'] === $idUsuario);
    $check('cabecera comisión distribuidor', abs((float) $filaVenta['ComisionDistribuidor'] - 247.04) < 0.005);
    $check('cabecera comisión vendedor', abs((float) $filaVenta['ComisionVendedor'] - 92.59) < 0.005);

    $stmtDetalle = $pdo->prepare('SELECT * FROM venta_detalle WHERE Venta_IdVenta = ?');
    $stmtDetalle->execute([$venta['idVenta']]);
    $detalle = $stmtDetalle->fetch();
    $check('detalle cantidad legacy', (int) $detalle['Cantidad'] === 3);
    $check('detalle costo usa precioMinimo', abs((float) $detalle['CostoUnitario'] - 789.12) < 0.005);
    $check('detalle moneda queda default UYU', $detalle['Moneda'] === 'UYU');

    $stockRep = (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockRep)->fetchColumn();
    $stockProductoRep = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . $idProductoRep)->fetchColumn();
    $check('descuenta distribuidor_stock repuesto 5-3', $stockRep === 2);
    $check('venta distribuidor repuesto no descuenta stock central', $stockProductoRep === 20);

    $service->registrarComisiones(
        $venta,
        2.5,
        $tablaExiste('distribuidor_comision'),
        $tablaExiste('gasto')
    );
    if ($tablaExiste('distribuidor_comision')) {
        $stmtComision = $pdo->prepare('SELECT BaseComision, Porcentaje, Monto, Estado FROM distribuidor_comision WHERE IdVenta = ?');
        $stmtComision->execute([$venta['idVenta']]);
        $filaComision = $stmtComision->fetch();
        $check(
            'registra comisión distribuidor',
            $filaComision
                && abs((float) $filaComision['BaseComision'] - 3703.68) < 0.005
                && abs((float) $filaComision['Porcentaje'] - 6.67) < 0.005
                && abs((float) $filaComision['Monto'] - 247.04) < 0.005
                && $filaComision['Estado'] === 'Pendiente'
        );
    }
    if ($tablaExiste('gasto')) {
        $stmtGastos = $pdo->prepare("SELECT Concepto, Monto FROM gasto WHERE IdVenta = ? AND Categoria = 'Comisiones' ORDER BY IdGasto");
        $stmtGastos->execute([$venta['idVenta']]);
        $gastosComision = $stmtGastos->fetchAll();
        $check('registra dos gastos de comisión', count($gastosComision) === 2);
        $check(
            'gastos preservan montos de ambas comisiones',
            abs(array_sum(array_map('floatval', array_column($gastosComision, 'Monto'))) - (247.04 + 92.59)) < 0.01
        );
    }

    if ($tablaExiste('remito')) {
        $service->facturarRemito($venta, 'FAC-C2', true);
        $stmtRemito = $pdo->prepare("SELECT * FROM remito WHERE IdDistribuidor = ? AND TipoItem = 'Repuesto' AND IdRepuesto = ? ORDER BY FechaEmision ASC LIMIT 1");
        $stmtRemito->execute([$idDistribuidor, $idRepuesto]);
        $remito = $stmtRemito->fetch();
        $check('remito queda Facturado', $remito['Estado'] === 'Facturado');
        $check('remito vincula venta', (int) $remito['IdVenta'] === (int) $venta['idVenta']);
        $check('remito conserva número factura', $remito['NumeroFactura'] === 'FAC-C2');
    }

    // Stock insuficiente conserva mensaje legacy.
    $stockInsuficiente = false;
    try {
        $service->prepararVenta($idStockRep, $idDistribuidor, 99);
    } catch (RuntimeException $e) {
        $stockInsuficiente = $e->getMessage() === 'No tenés suficiente stock. Disponible: 2.';
    }
    $check('stock insuficiente conserva mensaje', $stockInsuficiente);

    // Vehículo: persiste y aplica efectos compartidos.
    $preparadaVeh = $service->prepararVenta($idStockVeh, $idDistribuidor, 1);
    $ventaVeh = $service->registrarVenta(
        $preparadaVeh,
        $idCliente,
        'Efectivo',
        $idUsuario,
        'vehículo C2',
        0.0,
        22.0
    );
    $stockVeh = (int) $pdo->query('SELECT Cantidad FROM distribuidor_stock WHERE IdStock = ' . $idStockVeh)->fetchColumn();
    $productoVeh = $pdo->query('SELECT Stock, Estado FROM producto WHERE IdProducto = ' . $idProductoVeh)->fetch();
    $check('vehículo descuenta stock asignado', $stockVeh === 0);
    $check('vehículo queda vendido y sin stock', (int) $productoVeh['Stock'] === 0 && $productoVeh['Estado'] === 'Vendido');
    $check('vehículo crea venta', (int) $ventaVeh['idVenta'] > 0);
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado \xE2\x80\x94 la base de datos no fue modificada]\n";
}

echo "\n";
if ($fallos === []) {
    echo sprintf("OK \xE2\x80\x94 %d aserciones de integraci\xC3\xB3n pasaron.\n", $ok);
    exit(0);
}

echo sprintf("FALL\xC3\x93 \xE2\x80\x94 %d ok, %d fallaron:\n", $ok, count($fallos));
foreach ($fallos as $fallo) {
    echo ' - ' . $fallo . "\n";
}
exit(1);
