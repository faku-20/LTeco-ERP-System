<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Distribuidor\DistribuidorPedidoService.
 *
 * Igual que los otros integration tests: TOCA la base de datos de desarrollo pero
 * TODO ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base
 * queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/DistribuidorPedidoServiceTest.php
 *
 * Congela los efectos en DB del pedido auto-aprobado que se extrajo de
 * lteco-panel/distribuidores/nuevo_pedido.php:
 *   - registrarPedidoAprobado(): INSERT distribuidor_pedido (Estado 'Aprobado',
 *     FechaResolucion NOW()), movimiento de stock interno→distribuidor (reusa
 *     DistribuidorStockService::asignarStock) y, si la tabla existe, remito
 *     'Pendiente'.
 *   - validación de stock insuficiente => RuntimeException.
 *   - resolución de precioBase (PrecioDistribuidor>0 ? PD : PV).
 *
 * El servicio es TRANSACTION-AGNOSTIC: este test es el dueño de la transacción.
 */

// --- Autoloader PSR-4 Lteco\ -> src/ -----------------------------------------
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
use Lteco\Infrastructure\Repository\DistribuidorStockRepository;
use Lteco\Infrastructure\Repository\DistribuidorPedidoRepository;
use Lteco\Application\Distribuidor\DistribuidorStockService;
use Lteco\Application\Distribuidor\DistribuidorPedidoService;

// --- Mini-arnés de aserciones ------------------------------------------------
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

// --- Conexión a la DB de desarrollo ------------------------------------------
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

$tablaExiste = static function (string $tabla) use ($pdo, $db): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$db, $tabla]);
    return (int) $stmt->fetchColumn() > 0;
};

echo "Conectado a {$db}@{$host}. Abriendo transacción (se hará ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    $idDistribuidor = (int) $pdo->query('SELECT IdDistribuidor FROM distribuidor ORDER BY IdDistribuidor LIMIT 1')->fetchColumn();
    $idUsuario = (int) $pdo->query('SELECT IdUsuario FROM usuario ORDER BY IdUsuario LIMIT 1')->fetchColumn();

    $repuesto = $pdo->query(
        'SELECT r.IdRepuesto, p.IdProducto, p.PrecioVenta, p.PrecioDistribuidor
         FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
         ORDER BY r.IdRepuesto LIMIT 1'
    )->fetch();

    $vehiculo = $pdo->query(
        'SELECT v.IdVehiculo, p.IdProducto, p.PrecioVenta
         FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
         ORDER BY v.IdVehiculo LIMIT 1'
    )->fetch();

    if ($idDistribuidor === 0 || $idUsuario === 0 || !$repuesto || !$vehiculo) {
        throw new RuntimeException('La DB de desarrollo no tiene distribuidor/usuario/repuesto/vehículo base para el test.');
    }

    $idRepuesto     = (int) $repuesto['IdRepuesto'];
    $idProductoRep  = (int) $repuesto['IdProducto'];
    $idVehiculo     = (string) $vehiculo['IdVehiculo'];
    $idProductoVeh  = (int) $vehiculo['IdProducto'];
    $hayRemito      = $tablaExiste('remito');

    // precioBase esperado del repuesto: PrecioDistribuidor>0 ? PD : PV.
    $precioBaseRep = (float) ($repuesto['PrecioDistribuidor'] ?? 0) > 0
        ? (float) $repuesto['PrecioDistribuidor']
        : (float) $repuesto['PrecioVenta'];

    // Estado conocido (todo dentro de la transacción que se revierte).
    $pdo->prepare('DELETE FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = ? AND IdRepuesto = ?')
        ->execute([$idDistribuidor, 'Repuesto', $idRepuesto]);
    $pdo->prepare('DELETE FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = ? AND IdVehiculo = ?')
        ->execute([$idDistribuidor, 'Vehiculo', $idVehiculo]);
    $pdo->prepare("UPDATE producto SET Stock = 10, Estado = 'Disponible' WHERE IdProducto = ?")->execute([$idProductoRep]);
    $pdo->prepare("UPDATE producto SET Stock = 5, Estado = 'Disponible' WHERE IdProducto = ?")->execute([$idProductoVeh]);

    $conn = new Connection($pdo);
    $pedidoRepo = new DistribuidorPedidoRepository($conn);
    $stockService = new DistribuidorStockService(new DistribuidorStockRepository($conn));
    $service = new DistribuidorPedidoService($pedidoRepo, $stockService);

    $ultimoPedido = static fn (): ?array => $pdo->query(
        'SELECT * FROM distribuidor_pedido ORDER BY IdPedido DESC LIMIT 1'
    )->fetch() ?: null;
    $stockRep = static function () use ($pdo, $idDistribuidor, $idRepuesto): ?array {
        $stmt = $pdo->prepare("SELECT * FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = 'Repuesto' AND IdRepuesto = ? LIMIT 1");
        $stmt->execute([$idDistribuidor, $idRepuesto]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };
    $prodStock = static fn (int $idProducto): int => (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . $idProducto)->fetchColumn();

    // === 1) registrarPedidoAprobado(): repuesto, cantidad 3 ==================
    $idPedido = $service->registrarPedidoAprobado(
        $idDistribuidor, 'Repuesto', null, $idRepuesto, 3, 'obs de prueba', $idUsuario, $hayRemito
    );
    $check('registrar devuelve IdPedido > 0', $idPedido > 0);

    $p = $ultimoPedido();
    $check('pedido Estado = Aprobado',            $p['Estado'] === 'Aprobado');
    $check('pedido FechaResolucion seteada',      $p['FechaResolucion'] !== null);
    $check('pedido Cantidad = 3',                 (int) $p['Cantidad'] === 3);
    $check('pedido TipoItem = Repuesto',          $p['TipoItem'] === 'Repuesto');
    $check('pedido IdRepuesto correcto',          (int) $p['IdRepuesto'] === $idRepuesto);
    $check('pedido IdUsuarioSolicita correcto',   (int) $p['IdUsuarioSolicita'] === $idUsuario);

    $s = $stockRep();
    $check('stock distribuidor creado (Cantidad 3)', $s !== null && (int) $s['Cantidad'] === 3);
    $check('stock distribuidor precio = precioBase', $s !== null && abs((float) $s['PrecioVenta'] - $precioBaseRep) < 0.005);
    $check('producto interno descontado (10-3=7)',   $prodStock($idProductoRep) === 7);

    if ($hayRemito) {
        $stmtR = $pdo->prepare("SELECT * FROM remito WHERE IdPedido = ? LIMIT 1");
        $stmtR->execute([$idPedido]);
        $r = $stmtR->fetch(PDO::FETCH_ASSOC) ?: null;
        $check('remito creado Pendiente', $r !== null && $r['Estado'] === 'Pendiente');
    } else {
        echo "  (tabla remito ausente: se omiten aserciones de remito)\n";
    }

    // === 2) stock insuficiente => RuntimeException ===========================
    $excStock = false;
    try {
        $service->registrarPedidoAprobado($idDistribuidor, 'Repuesto', null, $idRepuesto, 9999, null, $idUsuario, $hayRemito);
    } catch (RuntimeException $e) {
        $excStock = (strpos($e->getMessage(), 'Stock insuficiente') !== false);
    }
    $check('stock insuficiente lanza RuntimeException con mensaje', $excStock);

    // === 3) rama vehículo: cantidad 1, descuenta producto ====================
    $idPedidoV = $service->registrarPedidoAprobado(
        $idDistribuidor, 'Vehiculo', $idVehiculo, null, 1, null, $idUsuario, $hayRemito
    );
    $pv = $ultimoPedido();
    $check('pedido vehículo TipoItem = Vehiculo', $pv['TipoItem'] === 'Vehiculo');
    $check('pedido vehículo IdVehiculo correcto', (string) $pv['IdVehiculo'] === $idVehiculo);
    $check('producto vehículo descontado (5-1=4)', $prodStock($idProductoVeh) === 4);
} finally {
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
