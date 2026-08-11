<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de
 * Lteco\Application\Distribuidor\DistribuidorPedidoService::resolverPedido().
 *
 * Igual que los otros integration tests: TOCA la base de datos de desarrollo pero
 * TODO ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base
 * queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/DistribuidorPedidoResolucionServiceTest.php
 *
 * Congela los efectos en DB de la resolución admin de pedidos que se extrajo de
 * lteco-panel/distribuidores/pedidos_admin.php:
 *   - resolverPedido('aprobar'): lock del producto, movimiento de stock
 *     interno→distribuidor (reusa DistribuidorStockService::asignarStock),
 *     remito 'Pendiente' si la tabla existe, y UPDATE distribuidor_pedido a
 *     'Aprobado' con FechaResolucion NOW().
 *   - resolverPedido('rechazar'): SOLO UPDATE distribuidor_pedido a 'Rechazado'
 *     (sin tocar stock ni remito).
 *   - producto no encontrado en stock interno => RuntimeException con mensaje propio.
 *   - stock insuficiente => RuntimeException con mensaje propio (con cantidades).
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

    // Helper: inserta un pedido en estado 'Pendiente' y devuelve su IdPedido.
    $crearPendiente = static function (string $tipoItem, ?string $idVeh, ?int $idRep, int $cantidad) use ($pdo, $idDistribuidor, $idUsuario): int {
        $pdo->prepare(
            "INSERT INTO distribuidor_pedido (IdDistribuidor, TipoItem, IdVehiculo, IdRepuesto, Cantidad, Estado, Observaciones, IdUsuarioSolicita, FechaPedido)
             VALUES (?, ?, ?, ?, ?, 'Pendiente', 'pendiente de prueba', ?, NOW())"
        )->execute([$idDistribuidor, $tipoItem, $idVeh, $idRep, $cantidad, $idUsuario]);
        return (int) $pdo->lastInsertId();
    };

    $conn = new Connection($pdo);
    $pedidoRepo = new DistribuidorPedidoRepository($conn);
    $stockService = new DistribuidorStockService(new DistribuidorStockRepository($conn));
    $service = new DistribuidorPedidoService($pedidoRepo, $stockService);

    $pedidoPorId = static function (int $id) use ($pdo): ?array {
        $stmt = $pdo->prepare('SELECT * FROM distribuidor_pedido WHERE IdPedido = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };
    $stockRep = static function () use ($pdo, $idDistribuidor, $idRepuesto): ?array {
        $stmt = $pdo->prepare("SELECT * FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = 'Repuesto' AND IdRepuesto = ? LIMIT 1");
        $stmt->execute([$idDistribuidor, $idRepuesto]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };
    $prodStock = static fn (int $idProducto): int => (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . $idProducto)->fetchColumn();

    // === 0) lecturas de pendientes ==========================================
    $idPedidoLectura = $crearPendiente('Repuesto', null, $idRepuesto, 2);
    $pendiente = $service->buscarPendiente($idPedidoLectura);
    $check('buscarPendiente devuelve el pedido', $pendiente !== null && (int) $pendiente['IdPedido'] === $idPedidoLectura);
    $check('buscarPendiente incluye nombre distribuidor', trim((string) ($pendiente['DistribuidorNombre'] ?? '')) !== '');
    $idsPendientes = array_map(
        static fn (array $row): int => (int) $row['IdPedido'],
        $service->listarPendientes()
    );
    $check('listarPendientes incluye el pedido', in_array($idPedidoLectura, $idsPendientes, true));

    // === 1) resolverPedido('aprobar'): repuesto, cantidad 3 =================
    $idPedido = $crearPendiente('Repuesto', null, $idRepuesto, 3);
    $estado = $service->resolverPedido($idPedido, 'aprobar', $idDistribuidor, 'Repuesto', null, $idRepuesto, 3, $hayRemito);
    $check('aprobar devuelve Estado = Aprobado', $estado === 'Aprobado');

    $p = $pedidoPorId($idPedido);
    $check('pedido Estado = Aprobado',            $p['Estado'] === 'Aprobado');
    $check('pedido FechaResolucion seteada',      $p['FechaResolucion'] !== null);

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

    // === 2) resolverPedido('rechazar'): NO toca stock =======================
    $stockProductoAntes = $prodStock($idProductoRep);
    $idPedidoRech = $crearPendiente('Repuesto', null, $idRepuesto, 2);
    $estadoR = $service->resolverPedido($idPedidoRech, 'rechazar', $idDistribuidor, 'Repuesto', null, $idRepuesto, 2, $hayRemito);
    $check('rechazar devuelve Estado = Rechazado', $estadoR === 'Rechazado');
    $pr = $pedidoPorId($idPedidoRech);
    $check('pedido rechazado Estado = Rechazado', $pr['Estado'] === 'Rechazado');
    $check('pedido rechazado FechaResolucion seteada', $pr['FechaResolucion'] !== null);
    $check('rechazar NO descuenta stock interno', $prodStock($idProductoRep) === $stockProductoAntes);

    // === 3) producto no encontrado => RuntimeException con mensaje propio ====
    $excNoEncontrado = false;
    $idPedidoBogus = $crearPendiente('Repuesto', null, 999999999, 1);
    try {
        $service->resolverPedido($idPedidoBogus, 'aprobar', $idDistribuidor, 'Repuesto', null, 999999999, 1, $hayRemito);
    } catch (RuntimeException $e) {
        $excNoEncontrado = (strpos($e->getMessage(), 'no fue encontrado en stock interno') !== false);
    }
    $check('producto no encontrado lanza RuntimeException con mensaje propio', $excNoEncontrado);

    // === 4) stock insuficiente => RuntimeException con mensaje propio ========
    $excStock = false;
    $idPedidoExceso = $crearPendiente('Repuesto', null, $idRepuesto, 9999);
    try {
        $service->resolverPedido($idPedidoExceso, 'aprobar', $idDistribuidor, 'Repuesto', null, $idRepuesto, 9999, $hayRemito);
    } catch (RuntimeException $e) {
        $excStock = (strpos($e->getMessage(), 'No hay stock interno suficiente para aprobar el pedido') !== false);
    }
    $check('stock insuficiente lanza RuntimeException con mensaje propio', $excStock);

    // === 5) rama vehículo aprobar: cantidad 1, descuenta producto ============
    $idPedidoV = $crearPendiente('Vehiculo', $idVehiculo, null, 1);
    $estadoV = $service->resolverPedido($idPedidoV, 'aprobar', $idDistribuidor, 'Vehiculo', $idVehiculo, null, 1, $hayRemito);
    $check('aprobar vehículo devuelve Aprobado', $estadoV === 'Aprobado');
    $pvp = $pedidoPorId($idPedidoV);
    $check('pedido vehículo Estado = Aprobado', $pvp['Estado'] === 'Aprobado');
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
