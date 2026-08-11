<?php

declare(strict_types=1);

/**
 * REL-01 integration checks.
 *
 * Runs against the configured DB with isolated REL01_TEST_* fixtures and cleans
 * them at the end. Intended command:
 * docker exec ltecobike_panel php /var/www/html/tests/integration/Rel01PanelReliabilityTest.php
 */

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $rel = substr($clase, strlen($prefijo));
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

require_once dirname(__DIR__, 2) . '/lteco-panel/includes/helpers.php';
require_once __DIR__ . '/../Support/PanelTestGuard.php';

use Lteco\Application\Venta\VentaLineasService;
use Lteco\Application\Venta\VentaPersistenceService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaRepository;

function rel01Pdo(): PDO
{
    $host = getenv('LTECO_TEST_DB_HOST') ?: getenv('LTECO_DB_HOST') ?: '127.0.0.1';
    $host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
    $db = getenv('LTECO_TEST_DB_NAME') ?: getenv('LTECO_DB_NAME') ?: 'lteco_db';
    $user = getenv('LTECO_TEST_DB_USER') ?: getenv('LTECO_DB_USER') ?: 'lteco_user';
    $pass = getenv('LTECO_TEST_DB_PASSWORD') ?: getenv('LTECO_TEST_DB_PASSWOR') ?: getenv('LTECO_DB_PASS') ?: '';
    putenv('LTECO_DB_NAME=' . $db);
    putenv('LTECO_DB_USER=' . $user);
    PanelTestGuard::assertSafeForMutation($db);

    return new PDO(
        'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function rel01Cleanup(PDO $pdo, string $marker): void
{
    $idsVenta = $pdo->prepare('SELECT IdVenta FROM venta WHERE Observaciones = ? OR NumeroFactura = ?');
    $idsVenta->execute([$marker, $marker]);
    $ventas = array_map('intval', $idsVenta->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($ventas !== []) {
        $in = implode(',', array_fill(0, count($ventas), '?'));
        $pdo->prepare("DELETE FROM venta_detalle WHERE Venta_IdVenta IN ({$in})")->execute($ventas);
        $pdo->prepare("DELETE FROM venta WHERE IdVenta IN ({$in})")->execute($ventas);
    }

    $idsProducto = $pdo->prepare('SELECT IdProducto FROM producto WHERE Slug LIKE ?');
    $idsProducto->execute([$marker . '%']);
    $productos = array_map('intval', $idsProducto->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($productos !== []) {
        $in = implode(',', array_fill(0, count($productos), '?'));
        $pdo->prepare("DELETE FROM repuesto WHERE IdProducto IN ({$in})")->execute($productos);
        $pdo->prepare("DELETE FROM producto WHERE IdProducto IN ({$in})")->execute($productos);
    }

    $pdo->prepare('DELETE FROM cliente WHERE Correo LIKE ?')->execute([$marker . '%@example.test']);
    $pdo->prepare('DELETE FROM empresa WHERE Correo = ?')->execute([$marker . '@example.test']);
    $pdo->prepare("DELETE FROM panel_idempotency_key WHERE OperationType = 'rel01.test' OR ResultType = 'rel01'")->execute();
}

function rel01CreateFixtures(PDO $pdo, string $marker): array
{
    $rutEmpresa = '99' . substr(hash('crc32b', $marker), 0, 10);
    $pdo->prepare('INSERT INTO empresa (RUT, Nombre, Telefono, Correo) VALUES (?, ?, ?, ?)')
        ->execute([$rutEmpresa, $marker . ' Empresa', '092000003', $marker . '@example.test']);

    $pdo->prepare('INSERT INTO cliente (NombreApellido, TipoFiscal, Correo) VALUES (?, ?, ?)')
        ->execute([$marker . ' Cliente', 'Consumidor final', $marker . '@example.test']);
    $idCliente = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO producto
            (Nombre, Slug, TipoProducto, Costo, GastoTotal, PrecioVenta, Stock, Estado, Empresa_RUT, Moneda)
        VALUES (?, ?, 'Repuesto', 10.00, 10.00, 100.00, 1, 'Disponible', ?, 'UYU')
    ")->execute([$marker . ' Repuesto', $marker . '-repuesto', $rutEmpresa]);
    $idProducto = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO repuesto (IdProducto, NombreInterno) VALUES (?, ?)')
        ->execute([$idProducto, $marker . ' Repuesto']);
    $idRepuesto = (int)$pdo->lastInsertId();

    return [$idCliente, $idProducto, $idRepuesto];
}

function rel01CreateSale(PDO $pdo, int $idCliente, int $idProducto, int $idRepuesto, string $marker): int
{
    $conn = new Connection($pdo);
    $lineas = new VentaLineasService($conn);
    $persistence = new VentaPersistenceService(new VentaRepository($conn));

    $pdo->beginTransaction();
    try {
        $rep = $lineas->bloquearRepuesto($idRepuesto);
        if (!$rep || (int)$rep['Stock'] < 1) {
            $pdo->rollBack();
            return 0;
        }

        $idVenta = $persistence->crearCabecera([
            'clienteId' => $idCliente,
            'metodoPago' => 'Efectivo',
            'tipoCliente' => 'Final',
            'usuarioVendedorId' => null,
            'moneda' => 'UYU',
            'observaciones' => $marker,
            'tipoCambio' => 1.0,
        ]);
        $persistence->asignarNumeroFactura($idVenta, $marker);

        usleep(400000);
        $lineas->registrarRepuesto([
            'idVenta' => $idVenta,
            'idProducto' => $idProducto,
            'cantidad' => 1,
            'precioUnitario' => 100.00,
            'costoUnitario' => 10.00,
            'subtotal' => 100.00,
            'gananciaLinea' => 90.00,
            'moneda' => 'UYU',
            'nuevoStock' => 0,
            'nuevoEstado' => 'Sin stock',
        ]);

        $pdo->commit();
        return $idVenta;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if (($argv[1] ?? '') === '--worker') {
    $pdo = rel01Pdo();
    $id = rel01CreateSale($pdo, (int)$argv[2], (int)$argv[3], (int)$argv[4], (string)$argv[5]);
    echo $id . PHP_EOL;
    exit(0);
}

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  OK {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  FAIL {$nombre}\n";
};

$marker = 'REL01_TEST_' . bin2hex(random_bytes(4));
$pdo = rel01Pdo();
rel01Cleanup($pdo, $marker);

try {
    [$idCliente, $idProducto, $idRepuesto] = rel01CreateFixtures($pdo, $marker);

    echo "REL-01 double submit/idempotency\n";
    $operationKey = bin2hex(random_bytes(16));
    $requestHash = hash('sha256', 'rel01');
    $pdo->beginTransaction();
    $first = panelIdempotencyClaim($pdo, 'rel01.test', $operationKey, $requestHash, null);
    panelIdempotencyComplete($pdo, $operationKey, 'rel01', '1', '/rel01-ok');
    $pdo->commit();

    $pdo->beginTransaction();
    $second = panelIdempotencyClaim($pdo, 'rel01.test', $operationKey, $requestHash, null);
    $pdo->commit();

    $check('primer claim ejecuta la operacion', $first === null);
    $check('segundo claim retorna resultado completado', is_array($second) && (string)$second['RedirectUrl'] === '/rel01-ok');
    $check('una sola fila idempotente', (int)$pdo->query("SELECT COUNT(*) FROM panel_idempotency_key WHERE OperationKey = " . $pdo->quote($operationKey))->fetchColumn() === 1);

    echo "\nREL-01 concurrent last unit sale\n";
    $script = __FILE__;
    $cmd = static function () use ($script, $idCliente, $idProducto, $idRepuesto, $marker): string {
        $env = [
            'LTECO_ENV',
            'LTECO_TEST_DB_ALLOW',
            'LTECO_TEST_DB_HOST',
            'LTECO_TEST_DB_NAME',
            'LTECO_TEST_DB_USER',
            'LTECO_TEST_DB_PASSWORD',
        ];
        $prefix = '';
        foreach ($env as $name) {
            $prefix .= $name . '=' . escapeshellarg((string) getenv($name)) . ' ';
        }

        return $prefix . 'php ' . escapeshellarg($script)
            . ' --worker ' . (int)$idCliente
            . ' ' . (int)$idProducto
            . ' ' . (int)$idRepuesto
            . ' ' . escapeshellarg($marker);
    };

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p1 = proc_open($cmd(), $descriptors, $pipes1);
    usleep(100000);
    $p2 = proc_open($cmd(), $descriptors, $pipes2);
    if (!is_resource($p1) || !is_resource($p2)) {
        throw new RuntimeException('No se pudieron iniciar workers REL01.');
    }

    $out1 = trim(stream_get_contents($pipes1[1]));
    $err1 = trim(stream_get_contents($pipes1[2]));
    $code1 = proc_close($p1);
    $out2 = trim(stream_get_contents($pipes2[1]));
    $err2 = trim(stream_get_contents($pipes2[2]));
    $code2 = proc_close($p2);
    if ($err1 !== '') {
        echo "worker1 stderr: {$err1}\n";
    }
    if ($err2 !== '') {
        echo "worker2 stderr: {$err2}\n";
    }

    $ventasCreadas = array_values(array_filter([(int)$out1, (int)$out2], static fn(int $id): bool => $id > 0));
    $stockFinal = (int)$pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int)$idProducto)->fetchColumn();
    $detalles = (int)$pdo->query('SELECT COUNT(*) FROM venta_detalle WHERE Producto_IdProducto = ' . (int)$idProducto)->fetchColumn();

    $check('ambos workers terminan sin error PHP', $code1 === 0 && $code2 === 0);
    $check('solo una venta consume la ultima unidad', count($ventasCreadas) === 1);
    $check('stock final queda en cero', $stockFinal === 0);
    $check('solo un detalle para el repuesto fixture', $detalles === 1);

    echo "\n";
} finally {
    rel01Cleanup($pdo, $marker);
    echo "[REL01 cleanup ejecutado]\n";
}

if ($fallos !== []) {
    echo "\nFALLO REL01: " . count($fallos) . " checks fallaron\n";
    foreach ($fallos as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

echo "\nOK REL01: {$ok} checks pasaron\n";
