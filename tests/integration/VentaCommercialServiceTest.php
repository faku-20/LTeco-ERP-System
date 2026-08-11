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
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaCommercialRepository;

$failures = [];
$passed = 0;
$check = static function (string $name, bool $condition) use (&$failures, &$passed): void {
    if ($condition) {
        $passed++;
        echo "  \xE2\x9C\x93 {$name}\n";
        return;
    }

    $failures[] = $name;
    echo "  \xE2\x9C\x97 {$name}\n";
};
$money = static fn (float $a, float $b): bool => abs($a - $b) < 0.00001;

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

$service = new VentaCommercialService(
    new VentaCommercialRepository(new Connection($pdo))
);

echo "Conectado a {$db}@{$host}. Comparando servicio comercial con consultas legacy.\n\n";

$rowConfig = $pdo->query('
    SELECT DescuentoContado, RecargoTarjeta, ComisionDistribuidor, ComisionVendedor, TasaIVA
    FROM configuracion
    ORDER BY IdConfiguracion DESC
    LIMIT 1
')->fetch();
$config = $service->configuracion(22.0);
$expectedIva = $rowConfig && (float)($rowConfig['TasaIVA'] ?? 0) > 0
    ? (float)$rowConfig['TasaIVA']
    : 22.0;
$check('configuración devuelve cinco campos', count($config) === 5);
$check('configuración preserva TasaIVA legacy', $money($config['TasaIVA'], $expectedIva));

$userRow = $pdo->query('SELECT IdUsuario, ComisionPct FROM usuario ORDER BY IdUsuario LIMIT 1')->fetch();
if ($userRow) {
    $expected = max(0.0, (float)$userRow['ComisionPct']);
    $check(
        'comisión de vendedor coincide con SQL legacy',
        $money($service->comisionVendedor((int)$userRow['IdUsuario']), $expected)
    );
}
$check('usuario inexistente tiene comisión cero', $money($service->comisionVendedor(0), 0.0));

$distRow = $pdo->query(
    'SELECT IdDistribuidor, ComisionPct FROM distribuidor WHERE Activo = 1 ORDER BY IdDistribuidor LIMIT 1'
)->fetch();
if ($distRow) {
    $expected = max(0.0, (float)$distRow['ComisionPct']);
    $check(
        'comisión de distribuidor activo coincide con SQL legacy',
        $money($service->comisionDistribuidor((int)$distRow['IdDistribuidor'], 99.0), $expected)
    );
}
$check(
    'distribuidor inexistente conserva default',
    $money($service->comisionDistribuidor(PHP_INT_MAX, 6.67), 6.67)
);

$configured = $pdo->prepare("
    SELECT u.IdUsuario, u.ComisionDistribuidorPct
    FROM configuracion c
    INNER JOIN usuario u ON u.IdUsuario = c.UsuarioComisionDistribuidorId
    WHERE u.Activo = 1 AND u.Rol <> ?
    ORDER BY c.IdConfiguracion DESC
    LIMIT 1
");
$configured->execute(['Distribuidor']);
$internal = $configured->fetch();
if (!$internal) {
    $fallback = $pdo->prepare("
        SELECT IdUsuario, ComisionDistribuidorPct
        FROM usuario
        WHERE Usuario = 'lteco' AND Activo = 1 AND Rol <> ?
        LIMIT 1
    ");
    $fallback->execute(['Distribuidor']);
    $internal = $fallback->fetch();
}

if ($internal) {
    $resolved = $service->usuarioInternoDistribuidor('Distribuidor');
    $check('usuario interno coincide con fallback legacy', $resolved['IdUsuario'] === (int)$internal['IdUsuario']);
    $check(
        'comisión interna coincide con fallback legacy',
        $money($resolved['ComisionDistribuidorPct'], max(0.0, (float)$internal['ComisionDistribuidorPct']))
    );
}

echo "\n";
if ($failures === []) {
    echo sprintf("OK \xE2\x80\x94 %d aserciones de integración comercial pasaron.\n", $passed);
    exit(0);
}

echo sprintf("FALL\xC3\x93 \xE2\x80\x94 %d ok, %d fallaron:\n", $passed, count($failures));
foreach ($failures as $failure) {
    echo ' - ' . $failure . "\n";
}
exit(1);
