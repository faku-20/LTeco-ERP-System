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

use Lteco\Application\Vehiculo\VehiculoConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VehiculoConsultaRepository;

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
$service = new VehiculoConsultaService(new VehiculoConsultaRepository(new Connection($pdo)));

$rows = $pdo->query(
    'SELECT v.IdVehiculo, v.NumeroMotor
     FROM vehiculo v
     ORDER BY v.IdVehiculo
     LIMIT 2'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
if ($rows === []) {
    echo "SKIP - no hay vehículos para comparar.\n";
    exit(0);
}

$primero = $rows[0];
$id = (string) $primero['IdVehiculo'];
$motor = (string) $primero['NumeroMotor'];

$qr = $service->extraerQr("LTECO|{$id}|{$motor}");
$check('parser QR estándar conserva id', $qr['id'] === $id);
$check('parser QR estándar conserva motor', $qr['motor'] === $motor);
$check('parser QR legacy conserva motor', $service->extraerQr($motor) === ['id' => '', 'motor' => $motor]);
$check('parser URL vieja conserva motor', $service->extraerQr('https://panel.test/scan.php?motor=' . urlencode($motor)) === ['id' => '', 'motor' => $motor]);

$paraQr = $service->paraQr($id);
$check('paraQr encuentra el vehículo', $paraQr !== null && (string) $paraQr['IdVehiculo'] === $id);

$escaneado = $service->buscarEscaneado("LTECO|{$id}|{$motor}");
$check('buscarEscaneado encuentra id y motor exactos', $escaneado !== null && (string) $escaneado['IdVehiculo'] === $id);

$ids = array_reverse(array_map(static fn (array $row): string => (string) $row['IdVehiculo'], $rows));
$etiquetas = $service->paraEtiquetas($ids);
$idsResultado = array_map(static fn (array $row): string => (string) $row['IdVehiculo'], $etiquetas);
$check('paraEtiquetas preserva orden solicitado', $idsResultado === $ids);

if ($failures === []) {
    echo sprintf("OK - %d aserciones de consultas de vehículos pasaron.\n", $passed);
    exit(0);
}

echo sprintf("FALLO - %d ok, %d fallaron:\n", $passed, count($failures));
foreach ($failures as $failure) {
    echo ' - ' . $failure . "\n";
}
exit(1);
