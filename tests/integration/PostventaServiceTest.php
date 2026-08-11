<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Postventa\PostventaService.
 *
 * Igual que VehiculoEstadoServiceTest: TOCA la base de datos de desarrollo pero
 * TODO ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base
 * queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/PostventaServiceTest.php
 *
 * Congela los efectos en DB de las operaciones de postventa sobre un service (S2)
 * extraídas desde lteco-panel/vehiculos/service_realizar.php y
 * lteco-panel/postventa/{service_cancelar,service_observacion_agregar,
 * marcar_notificado_wa}.php hacia el servicio + PostventaRepository:
 *   - realizarService:    valida garantía/estado/venta, marca Realizado + historial.
 *   - cancelarService:    cancela (sólo Pendiente/Vencido) + historial.
 *   - agregarObservacion: anexa nota técnica sin cambiar estado + historial.
 *   - marcarNotificadoWa: registra el evento WA en el historial.
 *
 * REQUIERE la migración 2026_06_14_service_historial_evento_notificacion_wa.sql,
 * que agrega 'NOTIFICACION_WA' al enum de service_historial.TipoEvento. Sin ella,
 * con STRICT_TRANS_TABLES el INSERT del evento WA fallaba (Data truncated).
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
             ORDER BY v.IdVehiculo LIMIT 5'
        )->fetchAll(),
        'IdVehiculo'
    );
    $clienteId = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();
    $ventaId   = (int) $pdo->query("SELECT IdVenta FROM venta WHERE COALESCE(EstadoVenta,'Confirmada') <> 'Anulada' ORDER BY IdVenta LIMIT 1")->fetchColumn();

    if (count($veh) < 5 || $clienteId === 0 || $ventaId === 0) {
        throw new RuntimeException('La DB de desarrollo no tiene 5 vehículos limpios / cliente / venta base para el test.');
    }
    [$vehA, $vehB, $vehC, $vehD, $vehE] = $veh;

    $crearGarantia = static function (string $idVehiculo, string $estado) use ($pdo, $clienteId, $ventaId): void {
        $pdo->prepare(
            'INSERT INTO garantia (IdVehiculo, IdVenta, IdCliente, FechaInicio, FechaFin, Estado, Observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $idVehiculo, $ventaId, $clienteId,
            date('Y-m-d'), date('Y-m-d', strtotime('+12 months')), $estado, 'test',
        ]);
    };
    $crearService = static function (string $idVehiculo, int $numero, string $estado, ?string $obs) use ($pdo, $clienteId, $ventaId): int {
        $pdo->prepare(
            'INSERT INTO service_vehiculo (IdVehiculo, IdVenta, IdCliente, NumeroService, FechaProgramada, Estado, Observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$idVehiculo, $ventaId, $clienteId, $numero, date('Y-m-d'), $estado, $obs]);
        return (int) $pdo->lastInsertId();
    };
    $servRow = static fn (int $id): array => $pdo->query('SELECT * FROM service_vehiculo WHERE IdService = ' . $id)->fetch();
    $histCount = static function (int $idService, string $tipo, ?string $detalle = null, ?string $usuario = null) use ($pdo): int {
        $sql = 'SELECT COUNT(*) FROM service_historial WHERE IdService = ? AND TipoEvento = ?';
        $args = [$idService, $tipo];
        if ($detalle !== null) { $sql .= ' AND Detalle = ?'; $args[] = $detalle; }
        if ($usuario !== null) { $sql .= ' AND Usuario = ?'; $args[] = $usuario; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        return (int) $stmt->fetchColumn();
    };

    $conn = new Connection($pdo);
    $repo = new PostventaRepository($conn);
    $service = new PostventaService($repo);

    $hoy = date('Y-m-d');
    $notificaciones = $service->servicesParaNotificar();
    $check('servicesParaNotificar devuelve próximos', is_array($notificaciones['proximos'] ?? null));
    $check('servicesParaNotificar devuelve vencidos', is_array($notificaciones['vencidos'] ?? null));

    // === 1) realizarService() con observación ================================
    $crearGarantia($vehA, 'Vigente');
    $s1 = $crearService($vehA, 1, 'Pendiente', null);
    $r = $service->realizarService($s1, $vehA, 'Cambio de aceite', 'tester');
    $row = $servRow($s1);
    $check('realizar success=true', $r['success'] === true);
    $check('realizar mensaje OK', $r['mensaje'] === 'Service marcado como realizado.');
    $check('realizar deja Estado=Realizado', $row['Estado'] === 'Realizado');
    $check('realizar setea FechaRealizada=hoy', $row['FechaRealizada'] === $hoy);
    $check('realizar guarda Observaciones', $row['Observaciones'] === 'Cambio de aceite');
    $check('realizar historial REALIZADO con detalle+usuario', $histCount($s1, 'REALIZADO', 'Cambio de aceite', 'tester') === 1);

    // === 2) realizarService() con observación vacía -> texto por defecto =====
    // num=2 (el num=1 ya quedó Realizado, así que el chequeo de previos da 0).
    $s2 = $crearService($vehA, 2, 'Pendiente', null);
    $service->realizarService($s2, $vehA, '', 'sys');
    $row = $servRow($s2);
    $check('realizar vacío usa "Service realizado." en Observaciones', $row['Observaciones'] === 'Service realizado.');
    $check('realizar vacío usa "Service realizado." en historial', $histCount($s2, 'REALIZADO', 'Service realizado.') === 1);

    // === 3) realizar sobre uno ya realizado -> bloquea ======================
    $r = $service->realizarService($s1, $vehA, 'otra', 'tester');
    $check('realizar ya-realizado success=false', $r['success'] === false);
    $check('realizar ya-realizado mensaje', $r['mensaje'] === 'No se puede realizar el service: ya está realizado o cancelado.');

    // === 4) realizar con garantía no vigente -> bloquea =====================
    $crearGarantia($vehB, 'Vencida');
    $s4 = $crearService($vehB, 1, 'Pendiente', null);
    $r = $service->realizarService($s4, $vehB, 'x', 'tester');
    $check('realizar garantía vencida success=false', $r['success'] === false);
    $check('realizar garantía vencida mensaje', $r['mensaje'] === 'No se puede realizar el service: la garantía no está vigente.');
    $check('realizar garantía vencida no toca el service', $servRow($s4)['Estado'] === 'Pendiente');

    // === 5) realizar con un service anterior pendiente -> bloquea ===========
    $crearGarantia($vehC, 'Vigente');
    $crearService($vehC, 1, 'Pendiente', null);          // anterior sin realizar
    $s5b = $crearService($vehC, 2, 'Pendiente', null);   // el que intentamos realizar
    $r = $service->realizarService($s5b, $vehC, 'x', 'tester');
    $check('realizar con previo pendiente success=false', $r['success'] === false);
    $check('realizar con previo pendiente mensaje', $r['mensaje'] === 'Hay un service anterior pendiente de realizar. Los services deben completarse en orden.');

    // === 6) realizar con venta anulada -> bloquea ==========================
    $crearGarantia($vehD, 'Vigente');
    $s6 = $crearService($vehD, 1, 'Pendiente', null);
    $pdo->prepare('UPDATE venta SET EstadoVenta = ? WHERE IdVenta = ?')->execute(['Anulada', $ventaId]);
    $r = $service->realizarService($s6, $vehD, 'x', 'tester');
    $check('realizar venta anulada success=false', $r['success'] === false);
    $check('realizar venta anulada mensaje', $r['mensaje'] === 'No se puede realizar el service: la venta asociada está anulada.');

    // === 7) cancelarService() ===============================================
    $s7 = $crearService($vehE, 1, 'Pendiente', 'obs previa');
    $r = $service->cancelarService($s7, $vehE, 'Cliente canceló', 'tester');
    $row = $servRow($s7);
    $check('cancelar success=true', $r['success'] === true);
    $check('cancelar mensaje OK', $r['mensaje'] === 'Service cancelado correctamente.');
    $check('cancelar deja Estado=Cancelado', $row['Estado'] === 'Cancelado');
    $check('cancelar conserva obs previa', strpos($row['Observaciones'], 'obs previa') === 0);
    $check('cancelar anexa marca [CANCELADO', strpos($row['Observaciones'], '[CANCELADO') !== false);
    $check('cancelar anexa motivo', strpos($row['Observaciones'], 'Cliente canceló') !== false);
    $check('cancelar historial CANCELADO con motivo', $histCount($s7, 'CANCELADO', 'Cliente canceló', 'tester') === 1);

    // === 8) cancelar sobre uno ya cancelado -> bloquea =====================
    $r = $service->cancelarService($s7, $vehE, 'otra', 'tester');
    $check('cancelar ya-cancelado success=false', $r['success'] === false);
    $check('cancelar ya-cancelado mensaje', $r['mensaje'] === 'No se canceló el service. Puede que ya esté realizado o cancelado.');

    // === 9) agregarObservacion() ===========================================
    $s9 = $crearService($vehE, 2, 'Pendiente', null);
    $r = $service->agregarObservacion($s9, $vehE, 'Nota tecnica', 'tester');
    $row = $servRow($s9);
    $check('observacion success=true', $r['success'] === true);
    $check('observacion mensaje OK', $r['mensaje'] === 'Observación técnica agregada.');
    $check('observacion no cambia estado', $row['Estado'] === 'Pendiente');
    $check('observacion anexa marca [NOTA', strpos($row['Observaciones'], '[NOTA') !== false);
    $check('observacion anexa nota', strpos($row['Observaciones'], 'Nota tecnica') !== false);
    $check('observacion historial NOTA', $histCount($s9, 'NOTA', 'Nota tecnica', 'tester') === 1);

    // === 10) agregarObservacion sobre service inexistente -> bloquea ========
    $r = $service->agregarObservacion(999999999, $vehE, 'x', 'tester');
    $check('observacion inexistente success=false', $r['success'] === false);
    $check('observacion inexistente mensaje', $r['mensaje'] === 'No se actualizó la observación. Verificá el service.');

    // === 11) marcarNotificadoWa() registra el evento WA en el historial ======
    $s11 = $crearService($vehE, 3, 'Pendiente', null);
    $service->marcarNotificadoWa($s11, $vehE, 'tester');
    $check(
        'marcarNotificadoWa registra historial NOTIFICACION_WA',
        $histCount($s11, 'NOTIFICACION_WA', 'Recordatorio WhatsApp enviado', 'tester') === 1
    );
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
