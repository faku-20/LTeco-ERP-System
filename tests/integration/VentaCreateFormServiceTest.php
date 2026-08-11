<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Lteco\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use Lteco\Application\Venta\VentaCommercialService;
use Lteco\Application\Venta\VentaCreateFormService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaCommercialRepository;
use Lteco\Infrastructure\Repository\VentaCreateFormRepository;

$failures = [];
$passed = 0;
$check = static function (string $name, bool $condition) use (&$failures, &$passed): void {
    if ($condition) {
        $passed++;
        echo "  [OK] {$name}\n";
        return;
    }

    $failures[] = $name;
    echo "  [FAIL] {$name}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$db = getenv('LTECO_DB_NAME') ?: 'lteco_db';
$user = getenv('LTECO_DB_USER') ?: 'lteco_user';
$pass = getenv('LTECO_DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$connection = new Connection($pdo);
$repository = new VentaCreateFormRepository($connection);
$service = new VentaCreateFormService(
    $repository,
    new VentaCommercialService(new VentaCommercialRepository($connection))
);

echo "Conectado a {$db}@{$host}. Comparando carga POO con consultas legacy.\n\n";

$legacyDistribuidores = $pdo->query(
    'SELECT IdDistribuidor, Nombre, ComisionPct FROM distribuidor WHERE Activo = 1 ORDER BY Nombre ASC'
)->fetchAll();
$check('distribuidores activos preservan filas y orden', $repository->distribuidoresActivos() === $legacyDistribuidores);

$legacyConfig = $pdo->query('
    SELECT DescuentoContado, RecargoTarjeta, ComisionDistribuidor, ComisionVendedor, TasaIVA
    FROM configuracion
    ORDER BY IdConfiguracion DESC
    LIMIT 1
')->fetch() ?: [];

$legacyClientes = $pdo->query('
    SELECT IdCliente, NombreApellido, Telefono, Correo, TipoFiscal, Cedula, Direccion, RUT
    FROM cliente
    ORDER BY NombreApellido ASC
')->fetchAll();
$check('clientes generales preservan filas y orden', $repository->clientes(false, 0) === $legacyClientes);

$vendedorId = (int)($pdo->query(
    'SELECT UsuarioVendedorId FROM venta WHERE UsuarioVendedorId IS NOT NULL ORDER BY IdVenta LIMIT 1'
)->fetchColumn() ?: 0);
$legacyClientesVendedor = $pdo->prepare('
    SELECT c.IdCliente, c.NombreApellido, c.Telefono, c.Correo, c.TipoFiscal, c.Cedula, c.Direccion, c.RUT
    FROM cliente c
    WHERE EXISTS (
        SELECT 1 FROM venta v
        WHERE v.Cliente_IdCliente = c.IdCliente
          AND v.UsuarioVendedorId = ?
    )
    ORDER BY c.NombreApellido ASC
');
$legacyClientesVendedor->execute([$vendedorId]);
$clientesVendedorEsperados = $legacyClientesVendedor->fetchAll();
$check(
    'clientes de vendedor preservan alcance y orden',
    $repository->clientes(true, $vendedorId) === $clientesVendedorEsperados
);

$legacyVehiculos = $pdo->query("
    SELECT v.IdVehiculo, v.Modelo, v.NumeroMotor, v.ClienteReservaId,
           p.IdProducto, p.PrecioVenta, p.PrecioDistribuidor, p.GastoTotal,
           p.Moneda, p.Estado, v.TipoCambioImportacion
    FROM vehiculo v
    INNER JOIN producto p ON p.IdProducto = v.IdProducto
    WHERE p.Estado IN ('Disponible','Reservado')
      AND v.FechaVenta IS NULL
      AND NOT EXISTS (
          SELECT 1
          FROM venta_detalle vd_check
          INNER JOIN venta ve_check ON ve_check.IdVenta = vd_check.Venta_IdVenta
          WHERE vd_check.Producto_IdProducto = p.IdProducto
            AND ve_check.EstadoVenta <> 'Anulada'
      )
    ORDER BY v.IdVehiculo ASC
")->fetchAll();
$check('vehículos preservan disponibilidad y orden', $repository->vehiculosDisponibles() === $legacyVehiculos);

$legacyRepuestos = $pdo->query('
    SELECT r.IdRepuesto, p.IdProducto, p.Nombre, p.PrecioVenta, p.PrecioDistribuidor,
           p.GastoTotal, p.Moneda, p.Stock, r.TipoCambioImportacion
    FROM repuesto r
    INNER JOIN producto p ON p.IdProducto = r.IdProducto
    WHERE p.Stock > 0
    ORDER BY p.Nombre ASC
')->fetchAll();
$check('repuestos preservan stock y orden', $repository->repuestosConStock() === $legacyRepuestos);

$datos = $service->cargar($vendedorId, true, 22.0);
$comisionUsuario = 0.0;
if ($vendedorId > 0) {
    $stmtComision = $pdo->prepare('SELECT ComisionPct FROM usuario WHERE IdUsuario = ? LIMIT 1');
    $stmtComision->execute([$vendedorId]);
    $filaComision = $stmtComision->fetch();
    if ($filaComision && (float)$filaComision['ComisionPct'] > 0) {
        $comisionUsuario = (float)$filaComision['ComisionPct'];
    }
}
$configEsperada = [
    'DescuentoContado' => isset($legacyConfig['DescuentoContado']) ? (float)$legacyConfig['DescuentoContado'] : 0.0,
    'RecargoTarjeta' => isset($legacyConfig['RecargoTarjeta']) ? (float)$legacyConfig['RecargoTarjeta'] : 0.0,
    'ComisionDistribuidor' => isset($legacyConfig['ComisionDistribuidor'])
        ? (float)$legacyConfig['ComisionDistribuidor']
        : 0.0,
    'ComisionVendedor' => $comisionUsuario > 0
        ? $comisionUsuario
        : (isset($legacyConfig['ComisionVendedor']) ? (float)$legacyConfig['ComisionVendedor'] : 0.0),
    'TasaIVA' => isset($legacyConfig['TasaIVA']) ? (float)$legacyConfig['TasaIVA'] : 22.0,
];
$check('servicio compone las cinco secciones', array_keys($datos) === [
    'distribuidoresActivos',
    'configComercial',
    'clientes',
    'vehiculos',
    'repuestos',
]);
$check('servicio preserva configuración, comisión y defaults legacy', $datos['configComercial'] === $configEsperada);
$check('servicio aplica alcance de vendedor', $datos['clientes'] === $clientesVendedorEsperados);

echo "\n";
if ($failures === []) {
    echo sprintf("OK - %d aserciones de integración pasaron.\n", $passed);
    exit(0);
}

echo sprintf("FALLO - %d ok, %d fallaron:\n", $passed, count($failures));
foreach ($failures as $failure) {
    echo ' - ' . $failure . "\n";
}
exit(1);
