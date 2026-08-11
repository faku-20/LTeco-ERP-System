<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Distribuidor\DistribuidorStockService.
 *
 * Igual que los otros integration tests: TOCA la base de datos de desarrollo pero
 * TODO ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base
 * queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/DistribuidorStockServiceTest.php
 *
 * Congela los efectos en DB del núcleo de asignación de stock que se extrajo de
 * lteco-panel/distribuidores/asignar_stock.php (y del helper distribuidorUpsertStock):
 *   - upsertStock(): inserta fila nueva en distribuidor_stock o suma Cantidad y
 *     pisa PrecioVenta/PrecioMinimo si ya existe (semántica Cantidad + ?).
 *   - asignarStock(): upsert + descuento de producto (GREATEST(0, Stock - ?)) +
 *     paso a 'Sin stock' cuando el stock se agota.
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
use Lteco\Application\Distribuidor\DistribuidorStockService;

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

echo "Conectado a {$db}@{$host}. Abriendo transacción (se hará ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    $idDistribuidor = (int) $pdo->query('SELECT IdDistribuidor FROM distribuidor ORDER BY IdDistribuidor LIMIT 1')->fetchColumn();

    $repuesto = $pdo->query(
        'SELECT r.IdRepuesto, p.IdProducto
         FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
         ORDER BY r.IdRepuesto LIMIT 1'
    )->fetch();

    $idVehiculo = (string) $pdo->query('SELECT IdVehiculo FROM vehiculo ORDER BY IdVehiculo LIMIT 1')->fetchColumn();

    if ($idDistribuidor === 0 || !$repuesto || $idVehiculo === '') {
        throw new RuntimeException('La DB de desarrollo no tiene distribuidor/repuesto/vehículo base para el test.');
    }

    $idRepuesto = (int) $repuesto['IdRepuesto'];
    $idProducto = (int) $repuesto['IdProducto'];

    // Partimos de un estado conocido (todo dentro de la transacción que se revierte).
    $pdo->prepare('DELETE FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = ? AND IdRepuesto = ?')
        ->execute([$idDistribuidor, 'Repuesto', $idRepuesto]);
    $pdo->prepare('DELETE FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = ? AND IdVehiculo = ?')
        ->execute([$idDistribuidor, 'Vehiculo', $idVehiculo]);
    $pdo->prepare("UPDATE producto SET Stock = 10, Estado = 'Disponible' WHERE IdProducto = ?")->execute([$idProducto]);

    $conn = new Connection($pdo);
    $repo = new DistribuidorStockRepository($conn);
    $service = new DistribuidorStockService($repo);

    $stockRow = static function (string $tipo, $idVeh, $idRep) use ($pdo, $idDistribuidor): ?array {
        if ($tipo === 'Vehiculo') {
            $stmt = $pdo->prepare("SELECT * FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = 'Vehiculo' AND IdVehiculo = ? LIMIT 1");
            $stmt->execute([$idDistribuidor, $idVeh]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = 'Repuesto' AND IdRepuesto = ? LIMIT 1");
            $stmt->execute([$idDistribuidor, $idRep]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };
    $prod = static fn (): array => $pdo->query('SELECT Stock, Estado FROM producto WHERE IdProducto = ' . $idProducto)->fetch();

    // === 1) upsertStock(): inserta fila nueva (repuesto) =====================
    $service->upsertStock($idDistribuidor, 'Repuesto', null, $idRepuesto, 3, 150.0, 120.0);
    $r = $stockRow('Repuesto', null, $idRepuesto);
    $check('upsert inserta fila de stock',           $r !== null);
    $check('upsert insert Cantidad = 3',             (int) $r['Cantidad'] === 3);
    $check('upsert insert PrecioVenta = 150',        abs((float) $r['PrecioVenta'] - 150.0) < 0.005);
    $check('upsert insert PrecioMinimo = 120',       abs((float) $r['PrecioMinimo'] - 120.0) < 0.005);

    // === 2) upsertStock(): suma Cantidad y pisa precios si ya existe =========
    $service->upsertStock($idDistribuidor, 'Repuesto', null, $idRepuesto, 2, 200.0, 180.0);
    $r = $stockRow('Repuesto', null, $idRepuesto);
    $check('upsert update suma Cantidad (3+2=5)',    (int) $r['Cantidad'] === 5);
    $check('upsert update pisa PrecioVenta = 200',   abs((float) $r['PrecioVenta'] - 200.0) < 0.005);
    $check('upsert update pisa PrecioMinimo = 180',  abs((float) $r['PrecioMinimo'] - 180.0) < 0.005);

    // === 3) upsertStock(): rama vehículo inserta con IdVehiculo ==============
    $service->upsertStock($idDistribuidor, 'Vehiculo', $idVehiculo, null, 1, 999.0, 900.0);
    $rv = $stockRow('Vehiculo', $idVehiculo, null);
    $check('upsert vehículo inserta fila',           $rv !== null);
    $check('upsert vehículo Cantidad = 1',           (int) $rv['Cantidad'] === 1);
    $check('upsert vehículo guarda IdVehiculo',      (string) $rv['IdVehiculo'] === $idVehiculo);

    // === 4) asignarStock(): descuenta producto sin agotar ====================
    // Stock actual 10, asignamos 4 => Stock 6, sigue Disponible.
    $service->asignarStock($idDistribuidor, 'Repuesto', null, $idRepuesto, 4, 200.0, 180.0, $idProducto, 10);
    $p = $prod();
    $check('asignar descuenta Stock producto (10-4=6)', (int) $p['Stock'] === 6);
    $check('asignar no toca Estado si queda stock',     $p['Estado'] === 'Disponible');
    $rr = $stockRow('Repuesto', null, $idRepuesto);
    $check('asignar suma al stock del distribuidor (5+4=9)', (int) $rr['Cantidad'] === 9);

    // === 5) asignarStock(): agota stock => 'Sin stock' =======================
    // Stock actual 6, asignamos 6 => GREATEST(0, 0) = 0 y Estado 'Sin stock'.
    $service->asignarStock($idDistribuidor, 'Repuesto', null, $idRepuesto, 6, 200.0, 180.0, $idProducto, 6);
    $p = $prod();
    $check('asignar agota Stock a 0',                (int) $p['Stock'] === 0);
    $check('asignar marca producto Sin stock',       $p['Estado'] === 'Sin stock');

    // === 6) descuento insuficiente falla y no deja negativo ==================
    $sinStockFallo = false;
    try {
        $service->asignarStock($idDistribuidor, 'Repuesto', null, $idRepuesto, 3, 200.0, 180.0, $idProducto, 0);
    } catch (RuntimeException $e) {
        $sinStockFallo = str_contains($e->getMessage(), 'No hay suficiente stock interno');
    }
    $check('descuento insuficiente falla', $sinStockFallo);
    $check('descuento insuficiente conserva stock en 0', (int) $prod()['Stock'] === 0);
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
