<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Vehiculo\VehiculoCrearService.
 *
 * Igual que el resto de la suite de integración: TOCA la base de datos de
 * desarrollo pero TODO ocurre dentro de UNA transacción que SIEMPRE hace
 * ROLLBACK, así la base queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VehiculoCrearServiceTest.php
 *
 * Congela los efectos en DB de la creación de vehículo extraída desde
 * lteco-panel/vehiculos/crear.php hacia el servicio + VehiculoRepository:
 *   - crear (interno, no-web): reserva la secuencia, inserta producto 'Moto' y
 *     vehiculo, devuelve IdVehiculo con formato Vxxxx + IdProducto, slug NULL.
 *   - crear (web, slug automático): genera slug a partir de modelo + IdVehiculo.
 *   - crear (web, slug manual): respeta el slug manual.
 *   - estado 'Vendido' setea FechaVenta; otros estados la dejan NULL.
 *   - slug en conflicto lanza RuntimeException con el mismo texto que el legacy.
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
use Lteco\Application\Vehiculo\VehiculoCrearService;

// El servicio usa el helper global \slugify (definido en lteco-panel/includes/
// helpers.php). Ese archivo no se puede cargar acá porque arrastra app_config.php
// (configureRuntime/headers). Replicamos slugify EXACTO para el runtime del test.
if (!function_exists('slugify')) {
    function slugify(string $texto): string
    {
        $texto = trim(mb_strtolower($texto, 'UTF-8'));
        if ($texto === '') {
            return '';
        }
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ];
        $texto = strtr($texto, $reemplazos);
        $texto = preg_replace('/[^a-z0-9]+/u', '-', $texto) ?? '';
        $texto = trim($texto, '-');
        $texto = preg_replace('/-+/', '-', $texto) ?? '';
        return $texto;
    }
}

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
    $empresaRut = (string) $pdo->query('SELECT RUT FROM empresa ORDER BY Nombre ASC, RUT ASC LIMIT 1')->fetchColumn();
    if ($empresaRut === '') {
        throw new RuntimeException('La DB de desarrollo no tiene empresa para el test.');
    }

    $productoRow = static fn (int $id): array => $pdo->query('SELECT * FROM producto WHERE IdProducto = ' . $id)->fetch();
    $vehiculoRow = static function (string $id) use ($pdo): array {
        $stmt = $pdo->prepare('SELECT * FROM vehiculo WHERE IdVehiculo = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    };

    $conn = new Connection($pdo);
    $repo = new VehiculoRepository($conn);
    $service = new VehiculoCrearService($repo);

    // Sufijo único para no chocar con NumeroMotor/Slug existentes.
    $sufijo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    // Datos base reutilizables.
    $baseDatos = static fn (array $over): array => array_merge([
        'puedeGestionarCatalogoWeb' => false,
        'modelo'                    => 'Modelo Test',
        'slugManual'                => '',
        'descripcion'               => 'Desc interna',
        'descripcionWeb'            => '',
        'costo'                     => 1000.0,
        'gastoTotal'                => 1200.0,
        'precioVenta'               => 2500.0,
        'precioDistribuidor'        => 2000.0,
        'moneda'                    => 'USD',
        'stock'                     => 1,
        'estado'                    => 'Disponible',
        'mostrarEnWeb'              => 0,
        'destacadoWeb'              => 0,
        'ordenWeb'                  => 0,
        'textoBotonWeb'             => 'Consultar',
        'empresaRut'                => $empresaRut,
        'numeroMotor'               => 'MOTOR-' . $sufijo,
        'color'                     => 'Negro',
        'numeroImportacion'         => null,
        'tipoCambioImportacion'     => null,
    ], $over);

    // === 1) crear interno (no-web), estado Disponible ========================
    $r1 = $service->crear($baseDatos(['numeroMotor' => 'INT-' . $sufijo]));
    $check('crear devuelve IdVehiculo Vxxxx', (bool) preg_match('/^V\d{4}$/', $r1['idVehiculo']));
    $check('crear devuelve IdProducto > 0', $r1['idProducto'] > 0);
    $p1 = $productoRow($r1['idProducto']);
    $v1 = $vehiculoRow($r1['idVehiculo']);
    $check('producto tipo Moto', $p1['TipoProducto'] === 'Moto');
    $check('producto Nombre = modelo', $p1['Nombre'] === 'Modelo Test');
    $check('interno slug NULL', $p1['Slug'] === null);
    $check('interno descripcion web NULL aunque se mande', $p1['DescripcionWeb'] === null || $p1['DescripcionWeb'] === '');
    $check('producto PrecioVenta', (float) $p1['PrecioVenta'] === 2500.0);
    $check('producto PrecioDistribuidor', (float) $p1['PrecioDistribuidor'] === 2000.0);
    $check('producto Estado Disponible', $p1['Estado'] === 'Disponible');
    $check('producto Empresa_RUT', (string) $p1['Empresa_RUT'] === $empresaRut);
    $check('vehiculo NumeroMotor', $v1['NumeroMotor'] === 'INT-' . $sufijo);
    $check('vehiculo IdProducto enlazado', (int) $v1['IdProducto'] === $r1['idProducto']);
    $check('Disponible deja FechaVenta NULL', $v1['FechaVenta'] === null);
    $check('vehiculo FechaIngreso seteada (CURDATE)', $v1['FechaIngreso'] === date('Y-m-d'));

    // === 2) crear web con slug automático ===================================
    $r2 = $service->crear($baseDatos([
        'puedeGestionarCatalogoWeb' => true,
        'numeroMotor'               => 'WEBAUTO-' . $sufijo,
        'modelo'                    => 'Moto Web',
        'slugManual'                => '',
        'descripcionWeb'            => 'Texto web',
    ]));
    $p2 = $productoRow($r2['idProducto']);
    $check('web slug automático = modelo-IdVehiculo', $p2['Slug'] === slugify('Moto Web' . '-' . $r2['idVehiculo']));
    $check('web descripcion web persistida', $p2['DescripcionWeb'] === 'Texto web');

    // === 3) crear web con slug manual =======================================
    $slugManual = 'moto-manual-' . strtolower($sufijo);
    $r3 = $service->crear($baseDatos([
        'puedeGestionarCatalogoWeb' => true,
        'numeroMotor'               => 'WEBMAN-' . $sufijo,
        'slugManual'                => $slugManual,
        'descripcionWeb'            => 'x',
    ]));
    $p3 = $productoRow($r3['idProducto']);
    $check('web respeta slug manual', $p3['Slug'] === $slugManual);

    // === 4) estado Vendido setea FechaVenta =================================
    $r4 = $service->crear($baseDatos([
        'numeroMotor' => 'VEND-' . $sufijo,
        'estado'      => 'Vendido',
        'stock'       => 0,
    ]));
    $v4 = $vehiculoRow($r4['idVehiculo']);
    $check('Vendido setea FechaVenta (CURDATE)', $v4['FechaVenta'] === date('Y-m-d'));

    // === 5) slug en conflicto lanza RuntimeException =========================
    try {
        $service->crear($baseDatos([
            'puedeGestionarCatalogoWeb' => true,
            'numeroMotor'               => 'DUP-' . $sufijo,
            'slugManual'                => $slugManual, // ya usado en el caso 3
            'descripcionWeb'            => 'x',
        ]));
        $check('slug duplicado lanza excepción', false);
    } catch (RuntimeException $e) {
        $check('slug duplicado lanza excepción', true);
        $check('slug duplicado mensaje', $e->getMessage() === 'El slug generado ya existe. Ajustá el slug manualmente.');
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
