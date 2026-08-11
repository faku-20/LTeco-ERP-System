<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Infrastructure\Repository\VentaRepository.
 *
 * A diferencia de tests/run.php (cálculo puro, sin DB), este test SÍ toca la
 * base de datos de desarrollo. Está pensado para correr DENTRO del contenedor
 * ltecobike_panel, que es donde vive el runtime real de la app:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VentaRepositoryTest.php
 *
 * Garantía de limpieza: TODO ocurre dentro de UNA transacción que SIEMPRE hace
 * ROLLBACK (incluso si una aserción falla o se lanza una excepción), por lo que
 * la base de datos queda EXACTAMENTE igual que antes de correr el test.
 *
 * Red de seguridad de la Fase 1 (migración POO). Congela tres modos de falla
 * del flujo de Ventas ANTES de extraer el SQL de lteco-panel/ventas/guardar.php:
 *   1. Mapeo de columnas — insertVenta/insertDetalle persisten lo que reciben.
 *   2. Filtro 'Anulada'  — listarActivas() excluye ventas anuladas.
 *   3. Alcance Vendedor  — listarPorVendedor() sólo devuelve ventas del vendedor.
 *
 * Diseño (acordado en D1/D2 del plan-eng-review):
 *   - VentaRepository es TRANSACTION-AGNOSTIC: recibe una Connection inyectada y
 *     opera sobre el $pdo del llamador. NO abre/cierra transacciones propias.
 *   - Alcance Fase 1: sólo SQL de venta + venta_detalle.
 */

// --- Autoloader PSR-4 Lteco\ -> src/ -----------------------------------------
// No incluye shared/app_config.php a propósito: el test no debe levantar la app
// (headers, configureRuntime, redirects). Sólo carga clases bajo src/.
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

use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaRepository;
use Lteco\Application\Venta\VentaPersistenceService;

// --- Mini-arnés de aserciones (self-contained, sin dependencias) -------------
$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $cond) use (&$fallos, &$ok): void {
    if ($cond) {
        $ok++;
        echo "  \xE2\x9C\x93 {$nombre}\n";
    } else {
        $fallos[] = $nombre;
        echo "  \xE2\x9C\x97 {$nombre}\n";
    }
};
$money = static fn (float $a, float $b): bool => abs($a - $b) < 0.005;

// --- Conexión a la DB de desarrollo ------------------------------------------
// Toma la config del entorno del contenedor (env_file .env -> LTECO_DB_*).
// Si se corre desde el host (sin esas vars) cae a 127.0.0.1, que apunta a la
// misma base lteco_db (la DB vive en el host, el contenedor la ve por
// host.docker.internal). En ambos casos es la MISMA base de desarrollo.
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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

echo "Conectado a {$db}@{$host}. Abriendo transacción (se hará ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    // FKs: necesitamos un cliente y un producto válidos ya existentes.
    $clienteId  = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();
    $productoId = (int) $pdo->query('SELECT IdProducto FROM producto ORDER BY IdProducto LIMIT 1')->fetchColumn();
    $vendedorA  = 4; // ids de usuario reales (rango 4..17)
    $vendedorB  = 5;

    if ($clienteId === 0 || $productoId === 0) {
        throw new RuntimeException('La DB de desarrollo no tiene cliente/producto base para el test.');
    }

    $conn = new Connection($pdo);
    $repo = new VentaRepository($conn);
    $persistence = new VentaPersistenceService($repo);

    // === 1) Mapeo de columnas =================================================
    $idVentaA = $persistence->crearCabecera([
        'clienteId'        => $clienteId,
        'metodoPago'       => 'Tarjeta',
        'tipoTarjeta'      => "Cr\xC3\xA9dito",
        'tipoCliente'      => 'Final',
        'usuarioVendedorId'=> $vendedorA,
        'moneda'           => 'UYU',
        'observaciones'    => 'TEST-INTEGRACION-VENTAREPO',
        'tipoCambio'       => 40.0,
    ]);
    $check('crearCabecera devuelve un IdVenta > 0', $idVentaA > 0);

    $row = $pdo->query('SELECT * FROM venta WHERE IdVenta = ' . (int) $idVentaA)->fetch();
    $check('mapea Cliente_IdCliente', (int) $row['Cliente_IdCliente'] === $clienteId);
    $check('mapea MetodoPago',        $row['MetodoPago'] === 'Tarjeta');
    $check('mapea EstadoVenta',       $row['EstadoVenta'] === 'Confirmada');
    $check('mapea UsuarioVendedorId', (int) $row['UsuarioVendedorId'] === $vendedorA);
    $check('mapea Total inicial',      $money((float) $row['Total'], 0.00));
    $check('mapea Ganancia inicial',   $money((float) $row['GananciaEstimada'], 0.00));
    $check('mapea TipoTarjeta',        $row['TipoTarjeta'] === "Cr\xC3\xA9dito");
    $check('mapea Moneda',             $row['Moneda'] === 'UYU');
    $check('mapea TipoCambioAplicado', $money((float) $row['TipoCambioAplicado'], 40.00));
    $check('mapea Observaciones',       $row['Observaciones'] === 'TEST-INTEGRACION-VENTAREPO');

    $persistence->asignarNumeroFactura($idVentaA, 'F-2026-000001');
    $numeroFactura = $pdo->query('SELECT NumeroFactura FROM venta WHERE IdVenta = ' . (int) $idVentaA)->fetchColumn();
    $check('asigna número de factura si estaba vacío', $numeroFactura === 'F-2026-000001');

    $persistence->asignarNumeroFactura($idVentaA, 'F-2026-NO-REEMPLAZAR');
    $numeroFactura = $pdo->query('SELECT NumeroFactura FROM venta WHERE IdVenta = ' . (int) $idVentaA)->fetchColumn();
    $check('no reemplaza número de factura existente', $numeroFactura === 'F-2026-000001');

    $idDetalle = $persistence->agregarDetalle([
        'ventaId'       => $idVentaA,
        'productoId'    => $productoId,
        'cantidad'      => 1,
        'precioUnitario'=> 63000.00,
        'costoUnitario' => 10000.00,
        'subtotal'      => 63000.00,
        'gananciaLinea' => 53000.00,
        'moneda'        => 'UYU',
    ]);
    $check('agregarDetalle devuelve un IdVentaDetalle > 0', $idDetalle > 0);

    $det = $pdo->query('SELECT * FROM venta_detalle WHERE IdVentaDetalle = ' . (int) $idDetalle)->fetch();
    $check('detalle enlaza a la venta correcta', (int) $det['Venta_IdVenta'] === $idVentaA);
    $check('detalle mapea Producto_IdProducto',  (int) $det['Producto_IdProducto'] === $productoId);
    $check('detalle mapea Cantidad',             (int) $det['Cantidad'] === 1);
    $check('detalle mapea PrecioUnitario',       $money((float) $det['PrecioUnitario'], 63000.00));
    $check('detalle mapea CostoUnitario',        $money((float) $det['CostoUnitario'], 10000.00));
    $check('detalle mapea Subtotal',             $money((float) $det['Subtotal'], 63000.00));
    $check('detalle mapea GananciaLinea',        $money((float) $det['GananciaLinea'], 53000.00));
    $check('detalle mapea Moneda',               $det['Moneda'] === 'UYU');

    $persistence->cerrarVenta($idVentaA, [
        'total' => 63000.00,
        'ganancia' => 42189.34,
        'montoPagado' => 60000.00,
        'saldoPendiente' => 3000.00,
        'estadoVenta' => 'Pendiente',
        'subtotalBruto' => 63000.00,
        'descuentoAplicado' => 0.00,
        'recargoAplicado' => 0.00,
        'comisionTarjeta' => 3150.00,
        'comisionDistribuidor' => 0.00,
        'comisionVendedor' => 6300.00,
        'montoIVA' => 11360.66,
        'totalSinIVA' => 51639.34,
        'tipoCambio' => 41.75,
    ]);
    $rowCerrada = $pdo->query('SELECT * FROM venta WHERE IdVenta = ' . (int) $idVentaA)->fetch();
    $check('cierre mapea Total',                 $money((float) $rowCerrada['Total'], 63000.00));
    $check('cierre mapea GananciaEstimada',     $money((float) $rowCerrada['GananciaEstimada'], 42189.34));
    $check('cierre mapea MontoPagado',          $money((float) $rowCerrada['MontoPagado'], 60000.00));
    $check('cierre mapea SaldoPendiente',       $money((float) $rowCerrada['SaldoPendiente'], 3000.00));
    $check('cierre mapea EstadoVenta',           $rowCerrada['EstadoVenta'] === 'Pendiente');
    $check('cierre mapea ComisionTarjeta',      $money((float) $rowCerrada['ComisionTarjeta'], 3150.00));
    $check('cierre mapea ComisionVendedor',     $money((float) $rowCerrada['ComisionVendedor'], 6300.00));
    $check('cierre mapea MontoIVA',             $money((float) $rowCerrada['MontoIVA'], 11360.66));
    $check('cierre mapea TotalSinIVA',          $money((float) $rowCerrada['TotalSinIVA'], 51639.34));
    $check('cierre mapea TipoCambioAplicado',   $money((float) $rowCerrada['TipoCambioAplicado'], 41.75));

    // === 1b) Sentinela '__NOW__' -> NOW() (lo usa guardar.php para FechaVenta) =
    // guardar.php pasa 'FechaVenta' => '__NOW__'; el repo debe traducirlo a la
    // expresión SQL NOW() (no insertar el string literal). Preserva el uso de la
    // hora del servidor MySQL, igual que el INSERT original (guardar.php:268).
    $idVentaNow = $repo->insertVenta([
        'Cliente_IdCliente' => $clienteId,
        'FechaVenta'        => '__NOW__',
        'EstadoVenta'       => 'Confirmada',
        'UsuarioVendedorId' => $vendedorA,
        'Total'             => 500.00,
        'Observaciones'     => 'TEST-NOW',
    ]);
    $segDif = (int) $pdo->query(
        'SELECT ABS(TIMESTAMPDIFF(SECOND, FechaVenta, NOW())) FROM venta WHERE IdVenta = ' . (int) $idVentaNow
    )->fetchColumn();
    $check("'__NOW__' se resuelve a NOW() (FechaVenta ~ ahora)", $segDif < 5);

    // === 2) Filtro 'Anulada' ==================================================
    $idVentaAnulada = $repo->insertVenta([
        'Cliente_IdCliente' => $clienteId,
        'EstadoVenta'       => 'Anulada',
        'UsuarioVendedorId' => $vendedorA,
        'Total'             => 1000.00,
        'Observaciones'     => 'TEST-ANULADA',
    ]);
    $idsActivas = array_map(
        static fn (array $r): int => (int) $r['IdVenta'],
        $repo->listarActivas()
    );
    $check('listarActivas() incluye la venta confirmada', in_array($idVentaA, $idsActivas, true));
    $check('listarActivas() EXCLUYE la venta anulada',     !in_array($idVentaAnulada, $idsActivas, true));

    // === 3) Alcance Vendedor ==================================================
    $idVentaB = $repo->insertVenta([
        'Cliente_IdCliente' => $clienteId,
        'EstadoVenta'       => 'Confirmada',
        'UsuarioVendedorId' => $vendedorB,
        'Total'             => 2000.00,
        'Observaciones'     => 'TEST-VENDEDOR-B',
    ]);
    $idsDeA = array_map(
        static fn (array $r): int => (int) $r['IdVenta'],
        $repo->listarPorVendedor($vendedorA)
    );
    $check('listarPorVendedor(A) incluye venta del vendedor A', in_array($idVentaA, $idsDeA, true));
    $check('listarPorVendedor(A) EXCLUYE venta del vendedor B',  !in_array($idVentaB, $idsDeA, true));
} finally {
    // SIEMPRE: deshace todo lo insertado. La DB de desarrollo queda intacta.
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado \xE2\x80\x94 la base de datos no fue modificada]\n";
}

echo "\n";
if (empty($fallos)) {
    echo sprintf("OK \xE2\x80\x94 %d aserciones de integraci\xC3\xB3n pasaron.\n", $ok);
    exit(0);
}

echo sprintf("FALL\xC3\x93 \xE2\x80\x94 %d ok, %d fallaron:\n", $ok, count($fallos));
foreach ($fallos as $f) {
    echo ' - ' . $f . "\n";
}
exit(1);
