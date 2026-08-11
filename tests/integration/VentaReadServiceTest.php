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

use Lteco\Application\Venta\VentaQueryService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaReadRepository;

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
$service = new VentaQueryService(new VentaReadRepository(new Connection($pdo)));

$idVenta = (int) ($pdo->query('SELECT IdVenta FROM venta ORDER BY IdVenta LIMIT 1')->fetchColumn() ?: 0);
if ($idVenta <= 0) {
    echo "SKIP - no hay ventas para comparar.\n";
    exit(0);
}

echo "Comparando lectura POO con SQL legacy para venta #{$idVenta}.\n";

$stmt = $pdo->prepare("
    SELECT
        v.*,
        c.NombreApellido,
        c.Telefono,
        c.Correo,
        c.TipoFiscal,
        c.Cedula,
        c.Direccion,
        c.RUT
    FROM venta v
    LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
    WHERE v.IdVenta = ?
");
$stmt->execute([$idVenta]);
$legacyVenta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$venta = $service->ventaConCliente($idVenta);
$check('venta+cliente coincide con SQL legacy', $venta === $legacyVenta);

$stmt = $pdo->prepare("
    SELECT
        vd.*,
        p.IdProducto,
        p.Nombre,
        p.TipoProducto,
        p.Estado,
        vh.IdVehiculo,
        vh.NumeroMotor,
        vh.Modelo,
        vh.Color
    FROM venta_detalle vd
    INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
    LEFT JOIN vehiculo vh ON vh.IdProducto = p.IdProducto
    WHERE vd.Venta_IdVenta = ?
    ORDER BY vd.IdVentaDetalle ASC
");
$stmt->execute([$idVenta]);
$legacyDetalles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$detalles = $service->detalles($idVenta);
$check('detalles coinciden con SQL legacy', $detalles === $legacyDetalles);

$legacyPostventa = ['garantiaFin' => null, 'serviceDates' => []];
foreach ($legacyDetalles as $detalle) {
    if (($detalle['TipoProducto'] ?? '') !== 'Moto' || empty($detalle['IdVehiculo'])) {
        continue;
    }

    $stmt = $pdo->prepare('SELECT FechaFin FROM garantia WHERE IdVehiculo = ? AND IdVenta = ? LIMIT 1');
    $stmt->execute([$detalle['IdVehiculo'], $idVenta]);
    $value = $stmt->fetchColumn();
    $legacyPostventa['garantiaFin'] = $value !== false ? (string) $value : null;

    $stmt = $pdo->prepare(
        'SELECT FechaProgramada
         FROM service_vehiculo
         WHERE IdVehiculo = ? AND IdVenta = ?
         ORDER BY NumeroService ASC'
    );
    $stmt->execute([$detalle['IdVehiculo'], $idVenta]);
    $legacyPostventa['serviceDates'] = array_map(
        static fn (array $row): string => (string) $row['FechaProgramada'],
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    );
    break;
}
$check(
    'garantia y services coinciden con SQL legacy',
    $service->garantiaYServices($idVenta, $detalles) === $legacyPostventa
);

$stmt = $pdo->prepare("
    SELECT
        v.IdVenta,
        v.Moneda,
        v.NumeroFactura,
        v.Total,
        v.EstadoVenta,
        c.NombreApellido,
        c.Telefono
    FROM venta v
    LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
    WHERE v.IdVenta = ?
    LIMIT 1
");
$stmt->execute([$idVenta]);
$legacyWhatsappVenta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$stmt = $pdo->prepare("
    SELECT vh.Modelo, vh.NumeroMotor
    FROM venta_detalle vd
    INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
    LEFT JOIN vehiculo vh ON vh.IdProducto = p.IdProducto
    WHERE vd.Venta_IdVenta = ?
      AND p.TipoProducto = 'Moto'
    ORDER BY vd.IdVentaDetalle ASC
    LIMIT 1
");
$stmt->execute([$idVenta]);
$legacyWhatsappVehiculo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$check(
    'datos de WhatsApp coinciden con SQL legacy',
    $service->datosWhatsapp($idVenta) === [
        'venta' => $legacyWhatsappVenta,
        'vehiculo' => $legacyWhatsappVehiculo,
    ]
);

if ($failures === []) {
    echo sprintf("OK - %d aserciones read-only pasaron.\n", $passed);
    exit(0);
}

echo sprintf("FALLO - %d ok, %d fallaron:\n", $passed, count($failures));
foreach ($failures as $failure) {
    echo ' - ' . $failure . "\n";
}
exit(1);
