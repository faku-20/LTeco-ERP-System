<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Postventa\PostventaIntervencionService.
 *
 * Igual que PostventaServiceTest: TOCA la base de datos de desarrollo pero TODO
 * ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base queda
 * intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/PostventaIntervencionServiceTest.php
 *
 * Congela los efectos en DB del guardado de intervención técnica extraído desde
 * lteco-panel/postventa/intervencion_guardar.php hacia el servicio +
 * PostventaRepository:
 *   - guardarIntervencion sin repuesto: inserta en postventa_historial_tecnico,
 *     devuelve el IdHistorialTecnico, normaliza estado inválido a 'Abierta',
 *     deriva FechaInicioReparacion/FechaCierre según el estado.
 *   - guardarIntervencion con repuesto: descuenta stock del producto y registra
 *     en postventa_repuesto_usado.
 *   - stock insuficiente / repuesto inexistente: lanzan RuntimeException con el
 *     mismo texto que el legacy (que mensajeErrorSeguro mostraba tal cual).
 *
 * El servicio es TRANSACTION-AGNOSTIC: este test es el dueño de la transacción
 * (necesaria además por el SELECT ... FOR UPDATE del repuesto).
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
use Lteco\Infrastructure\Repository\PostventaRepository;
use Lteco\Application\Postventa\PostventaIntervencionService;

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
    $idVehiculo = (string) $pdo->query('SELECT IdVehiculo FROM vehiculo ORDER BY IdVehiculo LIMIT 1')->fetchColumn();
    $clienteId  = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();
    $ventaId    = (int) $pdo->query("SELECT IdVenta FROM venta WHERE COALESCE(EstadoVenta,'Confirmada') <> 'Anulada' ORDER BY IdVenta LIMIT 1")->fetchColumn();
    // Repuesto con stock suficiente para el caso de consumo.
    $rep = $pdo->query("
        SELECT r.IdRepuesto, r.IdProducto, p.Stock, p.Costo, p.Moneda
        FROM repuesto r
        INNER JOIN producto p ON p.IdProducto = r.IdProducto
        WHERE p.Stock >= 2
        ORDER BY r.IdRepuesto
        LIMIT 1
    ")->fetch();

    if ($idVehiculo === '' || $clienteId === 0 || $ventaId === 0 || $rep === false) {
        throw new RuntimeException('La DB de desarrollo no tiene vehículo / cliente / venta / repuesto con stock para el test.');
    }

    $histRow = static fn (int $id): array => $pdo->query('SELECT * FROM postventa_historial_tecnico WHERE IdHistorialTecnico = ' . $id)->fetch();
    $stockProducto = static function (int $idProducto) use ($pdo): int {
        $stmt = $pdo->prepare('SELECT Stock FROM producto WHERE IdProducto = ?');
        $stmt->execute([$idProducto]);
        return (int) $stmt->fetchColumn();
    };

    $conn = new Connection($pdo);
    $repo = new PostventaRepository($conn);
    $service = new PostventaIntervencionService($repo);

    // === 1) guardarIntervencion sin repuesto, estado 'Abierta' ===============
    $id1 = $service->guardarIntervencion(
        $idVehiculo, $ventaId, $clienteId, 0,
        'Diagnóstico de prueba', 'Solución aplicada', 'Téc Test',
        'Abierta', 'Obs test', 0, 0, 7
    );
    $row = $histRow($id1);
    $check('sin repuesto devuelve IdHistorial > 0', $id1 > 0);
    $check('sin repuesto inserta diagnóstico', $row['Diagnostico'] === 'Diagnóstico de prueba');
    $check('sin repuesto inserta solución', $row['SolucionAplicada'] === 'Solución aplicada');
    $check('sin repuesto inserta técnico tal cual', $row['Tecnico'] === 'Téc Test');
    $check('sin repuesto estado Abierta', $row['Estado'] === 'Abierta');
    $check('sin repuesto IdCliente seteado', (int) $row['IdCliente'] === $clienteId);
    $check('sin repuesto IdService NULL (era 0)', $row['IdService'] === null);
    $check('sin repuesto IdUsuarioRegistra=7', (int) $row['IdUsuarioRegistra'] === 7);
    $check('Abierta no setea FechaInicioReparacion', $row['FechaInicioReparacion'] === null);
    $check('Abierta no setea FechaCierre', $row['FechaCierre'] === null);

    // === 2) estado inválido -> normaliza a 'Abierta' =========================
    $id2 = $service->guardarIntervencion(
        $idVehiculo, $ventaId, $clienteId, 0,
        'Diag2', null, null,
        'EstadoQueNoExiste', null, 0, 0, null
    );
    $row = $histRow($id2);
    $check('estado inválido normaliza a Abierta', $row['Estado'] === 'Abierta');
    $check('idUsuario null inserta NULL', $row['IdUsuarioRegistra'] === null);
    $check('tecnico null inserta NULL', $row['Tecnico'] === null);

    // === 3) estado 'Cerrada' -> setea inicio y cierre ========================
    $id3 = $service->guardarIntervencion(
        $idVehiculo, $ventaId, $clienteId, 0,
        'Diag3', null, 'T', 'Cerrada', null, 0, 0, 7
    );
    $row = $histRow($id3);
    $check('Cerrada setea FechaInicioReparacion', $row['FechaInicioReparacion'] !== null);
    $check('Cerrada setea FechaCierre', $row['FechaCierre'] !== null);

    // === 4) con repuesto -> descuenta stock + registra uso ===================
    $idRepuesto = (int) $rep['IdRepuesto'];
    $idProducto = (int) $rep['IdProducto'];
    $stockAntes = $stockProducto($idProducto);
    $id4 = $service->guardarIntervencion(
        $idVehiculo, $ventaId, $clienteId, 0,
        'Diag4 con repuesto', null, 'T', 'Abierta', null, $idRepuesto, 2, 7
    );
    $check('con repuesto descuenta stock en 2', $stockProducto($idProducto) === $stockAntes - 2);
    $usoStmt = $pdo->prepare('SELECT * FROM postventa_repuesto_usado WHERE IdHistorialTecnico = ?');
    $usoStmt->execute([$id4]);
    $uso = $usoStmt->fetch();
    $check('con repuesto registra postventa_repuesto_usado', $uso !== false);
    $check('uso guarda IdRepuesto', (int) $uso['IdRepuesto'] === $idRepuesto);
    $check('uso guarda Cantidad=2', (int) $uso['Cantidad'] === 2);
    $check('uso guarda Moneda del producto', (string) $uso['Moneda'] === (string) $rep['Moneda']);

    // === 5) stock insuficiente -> RuntimeException ===========================
    try {
        $service->guardarIntervencion(
            $idVehiculo, $ventaId, $clienteId, 0,
            'Diag5', null, 'T', 'Abierta', null, $idRepuesto, 999999, 7
        );
        $check('stock insuficiente lanza excepción', false);
    } catch (RuntimeException $e) {
        $check('stock insuficiente lanza excepción', true);
        $check('stock insuficiente mensaje', $e->getMessage() === 'No hay stock suficiente del repuesto seleccionado.');
    }

    // === 6) repuesto inexistente -> RuntimeException =========================
    try {
        $service->guardarIntervencion(
            $idVehiculo, $ventaId, $clienteId, 0,
            'Diag6', null, 'T', 'Abierta', null, 999999999, 1, 7
        );
        $check('repuesto inexistente lanza excepción', false);
    } catch (RuntimeException $e) {
        $check('repuesto inexistente lanza excepción', true);
        $check('repuesto inexistente mensaje', $e->getMessage() === 'Repuesto no encontrado.');
    }
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
