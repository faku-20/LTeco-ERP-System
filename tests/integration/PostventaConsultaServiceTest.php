<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Postventa\PostventaConsultaService.
 *
 * Igual que PostventaServiceTest: TOCA la base de desarrollo pero TODO ocurre
 * dentro de UNA transacción que SIEMPRE hace ROLLBACK (la base queda intacta).
 * Correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/PostventaConsultaServiceTest.php
 *
 * Congela el CONTRATO DE COLUMNAS que las vistas postventa/index.php y
 * postventa/detalle.php consumen, ahora servido por el read service + repo en vez
 * del SQL inline. La extracción es verbatim (mismo SQL), así que el riesgo real es
 * que falte/renombre una clave que el HTML usa: eso es lo que este test bloquea.
 *
 * El servicio es de SOLO LECTURA y transaction-agnostic: este test es el dueño de
 * la transacción.
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
use Lteco\Infrastructure\Repository\PostventaConsultaRepository;
use Lteco\Application\Postventa\PostventaConsultaService;

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
$tieneClaves = static function (array $row, array $claves): bool {
    foreach ($claves as $k) {
        if (!array_key_exists($k, $row)) {
            return false;
        }
    }
    return true;
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
    // Una moto real con venta no anulada (la vista de postventa parte de esto).
    $base = $pdo->query("
        SELECT v.IdVehiculo, ve.IdVenta
        FROM vehiculo v
        INNER JOIN venta_detalle vd ON vd.Producto_IdProducto = v.IdProducto
        INNER JOIN venta ve ON ve.IdVenta = vd.Venta_IdVenta
        WHERE COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
        ORDER BY ve.IdVenta DESC
        LIMIT 1
    ")->fetch();

    if (!$base) {
        throw new RuntimeException('La DB de desarrollo no tiene una moto vendida (vehiculo+venta_detalle+venta) para el test de lectura.');
    }
    $idVehiculo = (string) $base['IdVehiculo'];

    $conn = new Connection($pdo);
    $repo = new PostventaConsultaRepository($conn);
    $service = new PostventaConsultaService($repo);

    // === 1) listado(): estructura y contrato de columnas del listado =========
    $listado = $service->listado(['q' => '', 'estado_service' => '', 'garantia' => ''], 0);
    $check('listado devuelve items/recordatoriosWA/metricas', $tieneClaves($listado, ['items', 'recordatoriosWA', 'metricas']));
    $check('listado items es array', is_array($listado['items']));
    $check('listado recordatoriosWA es array', is_array($listado['recordatoriosWA']));

    $itemBuscado = null;
    foreach ($listado['items'] as $it) {
        if ((string) ($it['IdVehiculo'] ?? '') === $idVehiculo) {
            $itemBuscado = $it;
            break;
        }
    }
    $check('listado incluye la moto base', $itemBuscado !== null);
    if ($itemBuscado !== null) {
        $check('item del listado expone las columnas que usa la vista', $tieneClaves($itemBuscado, [
            'IdVehiculo', 'NumeroMotor', 'Modelo', 'Color',
            'IdVenta', 'FechaVenta', 'NombreApellido', 'Telefono',
            'EstadoGarantia', 'VencimientoGarantia',
            'ProximoService', 'EstadoProximoService',
            'CantPendiente', 'CantVencido', 'CantRealizado', 'CantCancelado',
        ]));
    }

    // === 2) métricas: 4 claves int ==========================================
    $m = $listado['metricas'];
    $check('métricas tiene las 4 claves', $tieneClaves($m, [
        'ServicesVencidos', 'ProximosServices', 'GarantiasVigentes', 'VehiculosSeguimiento',
    ]));
    $check('métricas son enteros', is_int($m['ServicesVencidos']) && is_int($m['VehiculosSeguimiento']));

    // === 3) detalle(): vehículo + services + historiales =====================
    $detalle = $service->detalle($idVehiculo, 0);
    $check('detalle no es null para la moto base', $detalle !== null);
    if ($detalle !== null) {
        $check('detalle expone las claves que usa la vista', $tieneClaves($detalle, [
            'vehiculo', 'services', 'historialPorService',
            'historialTecnico', 'repuestosUsadosPorHistorial', 'repuestosDisponibles',
        ]));
        $check('detalle.vehiculo expone columnas de la cabecera', $tieneClaves($detalle['vehiculo'], [
            'IdVehiculo', 'NumeroMotor', 'Modelo', 'Color',
            'IdVenta', 'NombreApellido', 'Telefono', 'EstadoGarantia',
        ]));
        $check('detalle.services es array', is_array($detalle['services']));
        $check('detalle.repuestosDisponibles es array', is_array($detalle['repuestosDisponibles']));
    }

    // === 4) detalle de vehículo inexistente -> null ==========================
    $check('detalle de id inexistente devuelve null', $service->detalle('__no_existe__', 0) === null);
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
