<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Vehiculo\VehiculoListadoService.
 *
 * Igual que VentaListadoRepositoryTest / PostventaConsultaServiceTest: TOCA la base
 * de desarrollo pero TODO ocurre dentro de UNA transacción que SIEMPRE hace
 * ROLLBACK. Correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VehiculoListadoServiceTest.php
 *
 * Congela el CONTRATO DE COLUMNAS que vehiculos/index.php consume del listado
 * (incluida la subquery ImagenPrincipal) y el comportamiento de los filtros, ahora
 * servidos por el read service + repo en vez del SQL inline. Solo lectura.
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

use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VehiculoListadoRepository;
use Lteco\Application\Vehiculo\VehiculoListadoService;

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
    $existeDisponible = $pdo->query("
        SELECT v.IdVehiculo
        FROM vehiculo v
        INNER JOIN producto p ON p.IdProducto = v.IdProducto
        WHERE p.Estado = 'Disponible'
        LIMIT 1
    ")->fetchColumn();

    if ($existeDisponible === false) {
        throw new RuntimeException('La DB de desarrollo no tiene un vehículo Disponible para el test de listado.');
    }

    $conn = new Connection($pdo);
    $repo = new VehiculoListadoRepository($conn);
    $service = new VehiculoListadoService($repo);

    // === 1) listar() sin filtros (modo superadmin): contrato de columnas =====
    $todos = $service->listar(['estado' => '', 'web' => '', 'destacado' => '', 'q' => ''], true);
    $check('listar devuelve array', is_array($todos));
    $check('listar devuelve al menos una fila', count($todos) >= 1);
    if ($todos !== []) {
        $check('fila expone las columnas que usa la vista', $tieneClaves($todos[0], [
            'IdVehiculo', 'Modelo', 'NumeroMotor', 'Color', 'ClienteReservaId', 'SeniaReserva',
            'IdProducto', 'Estado', 'Stock', 'PrecioVenta', 'Moneda', 'TipoCambioImportacion',
            'MostrarEnWeb', 'DestacadoWeb', 'OrdenWeb', 'Slug', 'DescripcionWeb',
            'ClienteReservaNombre', 'ImagenPrincipal',
        ]));
    }

    // === 2) filtro por estado: solo devuelve ese estado ======================
    $disponibles = $service->listar(['estado' => 'Disponible', 'web' => '', 'destacado' => '', 'q' => ''], true);
    $check('filtro estado=Disponible devuelve al menos una fila', count($disponibles) >= 1);
    $soloDisponibles = true;
    foreach ($disponibles as $row) {
        if ((string) $row['Estado'] !== 'Disponible') {
            $soloDisponibles = false;
            break;
        }
    }
    $check('filtro estado=Disponible no devuelve otros estados', $soloDisponibles);

    // === 3) modo no-superadmin (sin gestión web): mismo contrato de columnas =
    $sinWeb = $service->listar(['estado' => '', 'web' => 'visible', 'destacado' => '1', 'q' => ''], false);
    $check('listar (no superadmin) devuelve array', is_array($sinWeb));
    $check('listar (no superadmin) ignora filtros web/destacado (no recorta a 0 indebidamente)', count($sinWeb) === count($todos));

    // === 4) búsqueda inexistente -> vacío ====================================
    $vacio = $service->listar(['estado' => '', 'web' => '', 'destacado' => '', 'q' => '___no_existe_zzz___'], true);
    $check('búsqueda sin match devuelve vacío', $vacio === []);
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
