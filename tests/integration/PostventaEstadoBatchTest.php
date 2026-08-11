<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN del batch de estados de postventa (S3).
 *
 * Igual que PostventaServiceTest: TOCA la base de datos de desarrollo pero TODO
 * ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base queda
 * intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/PostventaEstadoBatchTest.php
 *
 * Congela los efectos en DB de la actualización masiva de estados extraída desde
 * lteco-panel/postventa/actualizar_estados.php hacia
 * Lteco\Application\Postventa\PostventaService::actualizarEstados() +
 * PostventaRepository::{marcarGarantiasVencidas, actualizarEstadosServices}:
 *   - Garantía Vigente con FechaFin pasada -> Vencida (sólo si incluirGarantias).
 *   - Service Pendiente con FechaProgramada pasada (<14 días) -> Vencido.
 *   - Service Vencido con FechaProgramada futura, sin realizar -> Pendiente.
 *   - Service Pendiente/Vencido con +14 días vencido -> Cancelado + nota.
 *   - Scoping por vendedor: sólo afecta services de las ventas del vendedor.
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
use Lteco\Infrastructure\Repository\PostventaRepository;
use Lteco\Application\Postventa\PostventaService;

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
    // Vehículos "limpios" (sin services ni garantía) para controlar cada caso.
    $veh = array_column(
        $pdo->query(
            'SELECT v.IdVehiculo FROM vehiculo v
             WHERE NOT EXISTS (SELECT 1 FROM service_vehiculo s WHERE s.IdVehiculo = v.IdVehiculo)
               AND NOT EXISTS (SELECT 1 FROM garantia g WHERE g.IdVehiculo = v.IdVehiculo)
             ORDER BY v.IdVehiculo LIMIT 6'
        )->fetchAll(),
        'IdVehiculo'
    );
    $clienteId  = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();
    $ventaId    = (int) $pdo->query("SELECT IdVenta FROM venta WHERE COALESCE(EstadoVenta,'Confirmada') <> 'Anulada' ORDER BY IdVenta LIMIT 1")->fetchColumn();
    $usuarioId  = (int) $pdo->query('SELECT IdUsuario FROM usuario ORDER BY IdUsuario LIMIT 1')->fetchColumn();

    if (count($veh) < 6 || $clienteId === 0 || $ventaId === 0 || $usuarioId === 0) {
        throw new RuntimeException('La DB de desarrollo no tiene 6 vehículos limpios / cliente / venta / usuario base para el test.');
    }
    [$vehA, $vehB, $vehC, $vehD, $vehE, $vehF] = $veh;

    // Fechas relativas a hoy.
    $ayer       = date('Y-m-d', strtotime('-1 day'));
    $manana     = date('Y-m-d', strtotime('+1 day'));
    $hace5      = date('Y-m-d', strtotime('-5 days'));   // vencido pero < 14 días
    $hace20     = date('Y-m-d', strtotime('-20 days'));  // vencido y > 14 días

    $crearGarantia = static function (string $idVehiculo, string $estado, string $fechaFin) use ($pdo, $clienteId, $ventaId): int {
        $pdo->prepare(
            'INSERT INTO garantia (IdVehiculo, IdVenta, IdCliente, FechaInicio, FechaFin, Estado, Observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$idVehiculo, $ventaId, $clienteId, date('Y-m-d'), $fechaFin, $estado, 'test']);
        return (int) $pdo->lastInsertId();
    };
    $crearService = static function (string $idVehiculo, int $numero, string $estado, string $fechaProg, ?string $fechaReal = null, ?string $obs = null) use ($pdo, $clienteId, $ventaId): int {
        $pdo->prepare(
            'INSERT INTO service_vehiculo (IdVehiculo, IdVenta, IdCliente, NumeroService, FechaProgramada, FechaRealizada, Estado, Observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$idVehiculo, $ventaId, $clienteId, $numero, $fechaProg, $fechaReal, $estado, $obs]);
        return (int) $pdo->lastInsertId();
    };
    $servRow = static fn (int $id): array => $pdo->query('SELECT * FROM service_vehiculo WHERE IdService = ' . $id)->fetch();
    $garRow  = static fn (int $id): array => $pdo->query('SELECT * FROM garantia WHERE IdGarantia = ' . $id)->fetch();

    $conn    = new Connection($pdo);
    $repo    = new PostventaRepository($conn);
    $service = new PostventaService($repo);

    // === 1) incluirGarantias=false NO toca la garantía vencida ===============
    $gFalse = $crearGarantia($vehD, 'Vigente', $ayer);
    $service->actualizarEstados(0, false);
    $check('garantía: incluirGarantias=false la deja Vigente', $garRow($gFalse)['Estado'] === 'Vigente');

    // === 2) incluirGarantias=true: Vigente con FechaFin pasada -> Vencida ====
    $gTrue = $crearGarantia($vehA, 'Vigente', $ayer);
    $gFutura = $crearGarantia($vehB, 'Vigente', $manana); // no debe cambiar (otro vehículo: uq vehículo+venta)
    $service->actualizarEstados(0, true);
    $check('garantía vencida pasa a Vencida', $garRow($gTrue)['Estado'] === 'Vencida');
    $check('garantía con FechaFin futura sigue Vigente', $garRow($gFutura)['Estado'] === 'Vigente');

    // === 3) Service Pendiente con fecha pasada (<14 días) -> Vencido =========
    $sVenc = $crearService($vehB, 1, 'Pendiente', $hace5);
    // === 4) Service Vencido con fecha futura, sin realizar -> Pendiente ======
    $sCorr = $crearService($vehB, 2, 'Vencido', $manana, null);
    // === 5) Service Pendiente con +14 días vencido -> Cancelado + nota =======
    $sCanc = $crearService($vehC, 1, 'Pendiente', $hace20);
    // Guardas: estos NO deben cambiar.
    $sReal = $crearService($vehC, 2, 'Realizado', $hace20, $hace20);
    $sFut  = $crearService($vehC, 3, 'Pendiente', $manana);

    $service->actualizarEstados(0, true);

    $check('service pendiente vencido -> Vencido', $servRow($sVenc)['Estado'] === 'Vencido');
    $check('service vencido con fecha futura -> Pendiente', $servRow($sCorr)['Estado'] === 'Pendiente');

    $canc = $servRow($sCanc);
    $check('service +14d vencido -> Cancelado', $canc['Estado'] === 'Cancelado');
    $check(
        'service auto-cancelado anexa la nota',
        strpos((string) $canc['Observaciones'], '[Auto-cancelado: no realizado dentro del plazo de 2 semanas]') !== false
    );

    $check('service Realizado no se toca', $servRow($sReal)['Estado'] === 'Realizado');
    $check('service Pendiente con fecha futura no se toca', $servRow($sFut)['Estado'] === 'Pendiente');

    // === 6) Scoping por vendedor =============================================
    // La venta base queda asociada a $usuarioId; un id distinto no debe afectar.
    $pdo->prepare('UPDATE venta SET UsuarioVendedorId = ? WHERE IdVenta = ?')->execute([$usuarioId, $ventaId]);
    $otroVendedor = $usuarioId + 100000; // id inexistente -> no matchea

    $sScope = $crearService($vehE, 1, 'Pendiente', $hace5);
    $service->actualizarEstados($otroVendedor, false);
    $check('scoping: otro vendedor no toca el service', $servRow($sScope)['Estado'] === 'Pendiente');

    $service->actualizarEstados($usuarioId, false);
    $check('scoping: vendedor dueño sí actualiza el service', $servRow($sScope)['Estado'] === 'Vencido');

    // Garantía scoped: con incluirGarantias=true y el vendedor dueño, vence.
    $gScope = $crearGarantia($vehF, 'Vigente', $ayer);
    $service->actualizarEstados($usuarioId, true);
    $check('scoping: garantía del vendedor dueño pasa a Vencida', $garRow($gScope)['Estado'] === 'Vencida');
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
