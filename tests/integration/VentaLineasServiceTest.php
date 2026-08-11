<?php

declare(strict_types=1);

/**
 * Test de CARACTERIZACIÓN del happy-path de líneas de venta (Wave 1).
 *
 * Congela el comportamiento ACTUAL de los writes cross-aggregate que hoy viven
 * inline en lteco-panel/ventas/guardar.php (y duplicados en
 * lteco-panel/distribuidores/nueva_venta.php) ANTES de extraerlos a un writer
 * compartido (Lteco\Application\Venta\VentaLineasService).
 *
 * Cubre, para una venta de 1 moto + 1 repuesto:
 *   - Línea moto:    venta_detalle + producto('Vendido',0,0,0) + vehiculo(FechaVenta)
 *                    + garantia(Vigente,+12m) + 4 service_vehiculo(Pendiente,+3/6/9/12m)
 *   - Línea repuesto: venta_detalle + producto(stock--, estado normalizado)
 *
 * NO cubre gasto (depende del cálculo comercial post-loop; se difiere a la ola Gastos).
 *
 * Corre DENTRO del contenedor ltecobike_panel; TODO en una transacción con
 * ROLLBACK final, la DB queda intacta.
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VentaLineasServiceTest.php
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

use Lteco\Infrastructure\Db\Connection;
use Lteco\Application\Venta\VentaPersistenceService;
use Lteco\Infrastructure\Repository\VentaRepository;
use Lteco\Application\Venta\VentaLineasService; // <-- RED: aún no existe

$fallos = [];
$ok = 0;
$check = static function (string $n, bool $c) use (&$fallos, &$ok): void {
    if ($c) { $ok++; echo "  \xE2\x9C\x93 {$n}\n"; }
    else { $fallos[] = $n; echo "  \xE2\x9C\x97 {$n}\n"; }
};
$money = static fn (float $a, float $b): bool => abs($a - $b) < 0.005;

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . (getenv('LTECO_DB_NAME') ?: 'lteco_db') . ';charset=utf8mb4',
    getenv('LTECO_DB_USER') ?: 'lteco_user',
    getenv('LTECO_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

echo "Conectado. Transacción abierta (ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    // Fixtures reales (Disponibles): moto V0052 (producto 2), repuesto producto 14.
    $idVehiculo = 'V0052';
    $idProductoMoto = (int) $pdo->query("SELECT IdProducto FROM vehiculo WHERE IdVehiculo='V0052'")->fetchColumn();
    $idProductoRep = 14;
    $clienteId = 1;
    $vendedorId = 17;

    $stockRepInicial = max(2, (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto=' . $idProductoRep)->fetchColumn());
    $pdo->exec('UPDATE producto SET Stock=' . $stockRepInicial . ", Estado='Disponible' WHERE IdProducto=" . $idProductoRep);

    $conn = new Connection($pdo);
    $persistence = new VentaPersistenceService(new VentaRepository($conn));
    $idVenta = $persistence->crearCabecera([
        'clienteId' => $clienteId,
        'metodoPago' => 'Tarjeta',
        'tipoTarjeta' => "Cr\xC3\xA9dito",
        'tipoCliente' => 'Final',
        'usuarioVendedorId' => $vendedorId,
        'moneda' => 'UYU',
        'observaciones' => 'TEST-LINEAS-CARACTERIZACION',
        'tipoCambio' => 44.0,
    ]);

    $lineas = new VentaLineasService($conn); // <-- RED: clase inexistente

    // === Línea MOTO =========================================================
    $lineas->registrarVehiculo([
        'idVenta' => $idVenta,
        'idVehiculo' => $idVehiculo,
        'idProducto' => $idProductoMoto,
        'clienteId' => $clienteId,
        'precioUnitario' => 63000.00,
        'costoUnitario' => 10000.00,
        'subtotal' => 63000.00,
        'gananciaLinea' => 53000.00,
        'moneda' => 'UYU',
    ]);

    $detMoto = $pdo->query('SELECT * FROM venta_detalle WHERE Venta_IdVenta=' . (int) $idVenta . ' AND Producto_IdProducto=' . $idProductoMoto)->fetch();
    $check('moto: venta_detalle creado', (bool) $detMoto);
    $check('moto: detalle cantidad=1', $detMoto && (int) $detMoto['Cantidad'] === 1);
    $check('moto: detalle subtotal', $detMoto && $money((float) $detMoto['Subtotal'], 63000.00));

    $prodMoto = $pdo->query('SELECT * FROM producto WHERE IdProducto=' . $idProductoMoto)->fetch();
    $check('moto: producto Estado=Vendido', $prodMoto['Estado'] === 'Vendido');
    $check('moto: producto Stock=0', (int) $prodMoto['Stock'] === 0);
    $check('moto: producto MostrarEnWeb=0', (int) $prodMoto['MostrarEnWeb'] === 0);
    $check('moto: producto DestacadoWeb=0', (int) $prodMoto['DestacadoWeb'] === 0);

    $veh = $pdo->query("SELECT * FROM vehiculo WHERE IdVehiculo='" . $idVehiculo . "'")->fetch();
    $check('moto: vehiculo FechaVenta=hoy', $veh['FechaVenta'] === date('Y-m-d'));
    $check('moto: vehiculo reserva limpiada', $veh['ClienteReservaId'] === null && $veh['FechaReserva'] === null && $veh['SeniaReserva'] === null);

    $gar = $pdo->query("SELECT * FROM garantia WHERE IdVehiculo='" . $idVehiculo . "' AND IdVenta=" . (int) $idVenta)->fetchAll();
    $check('moto: 1 garantía creada', count($gar) === 1);
    $check('moto: garantía Vigente', $gar && $gar[0]['Estado'] === 'Vigente');
    $check('moto: garantía IdCliente', $gar && (int) $gar[0]['IdCliente'] === $clienteId);
    $check('moto: garantía FechaFin=+12m', $gar && $gar[0]['FechaFin'] === date('Y-m-d', strtotime('+12 months')));

    $srv = $pdo->query("SELECT * FROM service_vehiculo WHERE IdVehiculo='" . $idVehiculo . "' AND IdVenta=" . (int) $idVenta . ' ORDER BY NumeroService')->fetchAll();
    $check('moto: 4 services creados', count($srv) === 4);
    $check('moto: services NumeroService 1..4', array_map(static fn ($r) => (int) $r['NumeroService'], $srv) === [1, 2, 3, 4]);
    $check('moto: services Pendiente', $srv && array_unique(array_map(static fn ($r) => $r['Estado'], $srv)) === ['Pendiente']);
    $check('moto: service#1 FechaProgramada=+3m', $srv && $srv[0]['FechaProgramada'] === date('Y-m-d', strtotime('+3 months')));
    $check('moto: service#4 FechaProgramada=+12m', $srv && $srv[3]['FechaProgramada'] === date('Y-m-d', strtotime('+12 months')));

    // === Idempotencia (la dueñan ahora Vehiculo/Postventa Repository) ========
    // Reaplicar los efectos para la misma (IdVehiculo, IdVenta) NO debe duplicar
    // garantía ni services.
    $lineas->aplicarEfectosVehiculo($idVehiculo, (int) $idVenta, $clienteId, $idProductoMoto);
    $garDup = (int) $pdo->query("SELECT COUNT(*) FROM garantia WHERE IdVehiculo='" . $idVehiculo . "' AND IdVenta=" . (int) $idVenta)->fetchColumn();
    $srvDup = (int) $pdo->query("SELECT COUNT(*) FROM service_vehiculo WHERE IdVehiculo='" . $idVehiculo . "' AND IdVenta=" . (int) $idVenta)->fetchColumn();
    $check('idempotente: garantía sigue en 1 tras reaplicar', $garDup === 1);
    $check('idempotente: services siguen en 4 tras reaplicar', $srvDup === 4);

    // === Línea REPUESTO =====================================================
    $nuevoStock = $stockRepInicial - 2;
    $lineas->registrarRepuesto([
        'idVenta' => $idVenta,
        'idProducto' => $idProductoRep,
        'cantidad' => 2,
        'precioUnitario' => 100.00,
        'costoUnitario' => 0.00,
        'subtotal' => 200.00,
        'gananciaLinea' => 200.00,
        'moneda' => 'UYU',
        'nuevoStock' => $nuevoStock,
        'nuevoEstado' => 'Disponible',
    ]);

    $detRep = $pdo->query('SELECT * FROM venta_detalle WHERE Venta_IdVenta=' . (int) $idVenta . ' AND Producto_IdProducto=' . $idProductoRep)->fetch();
    $check('repuesto: venta_detalle creado', (bool) $detRep);
    $check('repuesto: detalle cantidad=2', $detRep && (int) $detRep['Cantidad'] === 2);
    $check('repuesto: detalle subtotal=200', $detRep && $money((float) $detRep['Subtotal'], 200.00));

    $prodRep = $pdo->query('SELECT * FROM producto WHERE IdProducto=' . $idProductoRep)->fetch();
    $check('repuesto: stock decrementado', (int) $prodRep['Stock'] === $nuevoStock);
    $check('repuesto: estado=Disponible', $prodRep['Estado'] === 'Disponible');

    // Conteos globales del happy-path (sin gasto, diferido a ola Gastos)
    $nDet = (int) $pdo->query('SELECT COUNT(*) FROM venta_detalle WHERE Venta_IdVenta=' . (int) $idVenta)->fetchColumn();
    $check('total venta_detalle = 2', $nDet === 2);
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado \xE2\x80\x94 base de datos intacta]\n";
}

echo "\n";
if (empty($fallos)) {
    echo sprintf("OK \xE2\x80\x94 %d aserciones de caracterizaci\xC3\xB3n pasaron.\n", $ok);
    exit(0);
}
echo sprintf("FALL\xC3\x93 \xE2\x80\x94 %d ok, %d fallaron:\n", $ok, count($fallos));
foreach ($fallos as $f) {
    echo ' - ' . $f . "\n";
}
exit(1);
