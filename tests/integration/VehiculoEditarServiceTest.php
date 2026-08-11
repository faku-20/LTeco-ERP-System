<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Vehiculo\VehiculoEditarService.
 *
 * Igual que VehiculoEstadoServiceTest: TOCA la base de datos de desarrollo pero
 * TODO ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base
 * queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VehiculoEditarServiceTest.php
 *
 * Congela los efectos en DB del guardado del formulario principal de
 * lteco-panel/vehiculos/editar.php que se extrajo al servicio + repositorio:
 *   - editar(): UPDATE producto (datos + publicación) + UPDATE vehiculo.
 *   - derivación de FechaVenta para 'Vendido'.
 *   - regla CASE WHEN = 'Reservado' que limpia/conserva la reserva.
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
use Lteco\Infrastructure\Repository\VehiculoRepository;
use Lteco\Application\Vehiculo\VehiculoEditarService;

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
    $fila = $pdo->query(
        'SELECT v.IdVehiculo, v.IdProducto
         FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
         ORDER BY v.IdVehiculo LIMIT 1'
    )->fetch();
    $clienteId = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();

    if (!$fila || $clienteId === 0) {
        throw new RuntimeException('La DB de desarrollo no tiene vehículo/cliente base para el test.');
    }

    $idVehiculo = (string) $fila['IdVehiculo'];
    $idProducto = (int) $fila['IdProducto'];

    $conn = new Connection($pdo);
    $repo = new VehiculoRepository($conn);
    $service = new VehiculoEditarService($repo);

    $prodRow = static fn (): array => $pdo->query('SELECT * FROM producto WHERE IdProducto = ' . $idProducto)->fetch();
    $vehRow  = static fn (): array => $pdo->query("SELECT * FROM vehiculo WHERE IdVehiculo = " . $pdo->quote($idVehiculo))->fetch();

    // Datos base comunes a los casos.
    $base = [
        'idVehiculo'            => $idVehiculo,
        'idProducto'            => $idProducto,
        'modelo'                => 'Modelo Editado',
        'slug'                  => 'slug-editado',
        'descripcion'           => 'desc interna',
        'descripcionWeb'        => 'desc web',
        'costo'                 => 100.0,
        'gastoTotal'            => 25.0,
        'precioVenta'           => 1500.0,
        'precioDistribuidor'    => 1200.0,
        'moneda'                => 'USD',
        'stock'                 => 1,
        'estado'                => 'Disponible',
        'mostrarEnWeb'          => 1,
        'destacadoWeb'          => 0,
        'ordenWeb'              => 5,
        'textoBotonWeb'         => 'Consultar',
        'numeroMotor'           => 'MOTOR-EDIT-1',
        'color'                 => 'Rojo',
        'numeroImportacion'     => null,
        'tipoCambioImportacion' => null,
        'fechaVentaActual'      => null,
    ];

    // === 1) editar() en estado Disponible ====================================
    $service->editar($base);
    $p = $prodRow();
    $v = $vehRow();
    $check('editar() actualiza Nombre del producto', $p['Nombre'] === 'Modelo Editado');
    $check('editar() actualiza Slug',                $p['Slug'] === 'slug-editado');
    $check('editar() actualiza PrecioVenta',         abs((float) $p['PrecioVenta'] - 1500.0) < 0.005);
    $check('editar() actualiza Estado producto',     $p['Estado'] === 'Disponible');
    $check('editar() actualiza MostrarEnWeb',        (int) $p['MostrarEnWeb'] === 1);
    $check('editar() actualiza OrdenWeb',            (int) $p['OrdenWeb'] === 5);
    $check('editar() actualiza NumeroMotor',         $v['NumeroMotor'] === 'MOTOR-EDIT-1');
    $check('editar() actualiza Color',               $v['Color'] === 'Rojo');
    $check('editar() Disponible deja FechaVenta NULL', $v['FechaVenta'] === null);

    // === 2) color/slug/descripcion vacíos => NULL ============================
    $vacios = array_merge($base, ['color' => '', 'slug' => '', 'descripcion' => '', 'descripcionWeb' => '']);
    $service->editar($vacios);
    $p = $prodRow();
    $v = $vehRow();
    $check('editar() Color vacío => NULL',          $v['Color'] === null);
    $check('editar() Slug vacío => NULL',           $p['Slug'] === null);
    $check('editar() Descripcion vacía => NULL',    $p['Descripcion'] === null);
    $check('editar() DescripcionWeb vacía => NULL', $p['DescripcionWeb'] === null);

    // === 3) Reservado conserva los datos de reserva existentes ===============
    // Primero sembramos una reserva manual.
    $pdo->prepare('UPDATE vehiculo SET ClienteReservaId = ?, FechaReserva = NOW(), SeniaReserva = 999 WHERE IdVehiculo = ?')
        ->execute([$clienteId, $idVehiculo]);
    $reservado = array_merge($base, ['estado' => 'Reservado']);
    $service->editar($reservado);
    $v = $vehRow();
    $check('editar() Reservado conserva ClienteReservaId', (int) $v['ClienteReservaId'] === $clienteId);
    $check('editar() Reservado conserva SeniaReserva',     abs((float) $v['SeniaReserva'] - 999.0) < 0.005);
    $check('editar() Reservado conserva FechaReserva',     $v['FechaReserva'] !== null);
    $check('editar() Reservado deja FechaVenta NULL',      $v['FechaVenta'] === null);

    // === 4) No-Reservado limpia la reserva ===================================
    $service->editar(array_merge($base, ['estado' => 'Disponible']));
    $v = $vehRow();
    $check('editar() Disponible limpia ClienteReservaId', $v['ClienteReservaId'] === null);
    $check('editar() Disponible limpia SeniaReserva',     $v['SeniaReserva'] === null);
    $check('editar() Disponible limpia FechaReserva',     $v['FechaReserva'] === null);

    // === 5) Vendido deriva FechaVenta ========================================
    // Sin FechaVenta previa => usa la fecha de hoy.
    $service->editar(array_merge($base, ['estado' => 'Vendido', 'fechaVentaActual' => null]));
    $v = $vehRow();
    $check('editar() Vendido sin fecha previa setea hoy', $v['FechaVenta'] === date('Y-m-d'));

    // Con FechaVenta previa => la respeta.
    $service->editar(array_merge($base, ['estado' => 'Vendido', 'fechaVentaActual' => '2020-01-15']));
    $v = $vehRow();
    $check('editar() Vendido respeta FechaVenta previa', $v['FechaVenta'] === '2020-01-15');
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
