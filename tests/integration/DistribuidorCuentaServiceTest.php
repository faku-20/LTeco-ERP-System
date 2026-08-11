<?php

declare(strict_types=1);

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

use Lteco\Application\Distribuidor\DistribuidorCuentaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\DistribuidorCuentaRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  ✓ {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  ✗ {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . (getenv('LTECO_DB_NAME') ?: 'lteco_db') . ';charset=utf8mb4',
    getenv('LTECO_DB_USER') ?: 'lteco_user',
    getenv('LTECO_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $idDistribuidor = (int) $pdo->query(
        'SELECT IdDistribuidor FROM distribuidor ORDER BY IdDistribuidor LIMIT 1'
    )->fetchColumn();
    $venta = $pdo->query(
        'SELECT IdVenta FROM venta ORDER BY IdVenta LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($idDistribuidor <= 0 || !$venta) {
        throw new RuntimeException('Faltan distribuidor o venta base para el test.');
    }

    $pdo->prepare('DELETE FROM distribuidor_comision WHERE IdVenta = ?')->execute([(int) $venta['IdVenta']]);
    $pdo->prepare(
        "INSERT INTO distribuidor_comision
            (IdDistribuidor, IdVenta, BaseComision, Porcentaje, Monto, Estado, FechaGenerada)
         VALUES (?, ?, 1000, 10, 100, 'Pendiente', NOW())"
    )->execute([$idDistribuidor, (int) $venta['IdVenta']]);
    $idComision = (int) $pdo->lastInsertId();

    $service = new DistribuidorCuentaService(
        new DistribuidorCuentaRepository(new Connection($pdo))
    );

    $cuenta = $service->cargarCuenta($idDistribuidor, 'fecha-mala', 'otra-mala', true);
    $check('normaliza desde inválido al inicio del mes', $cuenta['desde'] === date('Y-m-01'));
    $check('normaliza hasta inválido a hoy', $cuenta['hasta'] === date('Y-m-d'));
    $check('carga distribuidor', (int) $cuenta['distribuidor']['IdDistribuidor'] === $idDistribuidor);
    $check('admin recibe listado de distribuidores', count($cuenta['distribuidores']) > 0);
    $check('incluye comisión del período', count($cuenta['comisiones']) >= 1);
    $check('resume monto Pendiente', abs($cuenta['resumen']['Pendiente'] - 100.0) < 0.005);

    $estado = $service->actualizarComision($idComision, $idDistribuidor, 'aprobar');
    $check('aprobar devuelve Aprobada', $estado === 'Aprobada');
    $fila = $pdo->query("SELECT * FROM distribuidor_comision WHERE IdComision = {$idComision}")->fetch();
    $check('aprobar persiste Aprobada', $fila['Estado'] === 'Aprobada');
    $check('aprobar no fija FechaPago', $fila['FechaPago'] === null);

    $service->actualizarComision($idComision, $idDistribuidor, 'pagar');
    $fila = $pdo->query("SELECT * FROM distribuidor_comision WHERE IdComision = {$idComision}")->fetch();
    $check('pagar persiste Pagada', $fila['Estado'] === 'Pagada');
    $check('pagar fija FechaPago', $fila['FechaPago'] !== null);

    $service->actualizarComision($idComision, $idDistribuidor, 'anular');
    $fila = $pdo->query("SELECT * FROM distribuidor_comision WHERE IdComision = {$idComision}")->fetch();
    $check('anular persiste Anulada', $fila['Estado'] === 'Anulada');
    $check('anular preserva FechaPago legacy', $fila['FechaPago'] !== null);

    $invalida = false;
    try {
        $service->actualizarComision(0, $idDistribuidor, 'x');
    } catch (RuntimeException $e) {
        $invalida = $e->getMessage() === 'Acción inválida.';
    }
    $check('acción inválida conserva mensaje legacy', $invalida);
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado]\n";
}

if ($fallos === []) {
    echo "\nOK — {$ok} aserciones de integración pasaron.\n";
    exit(0);
}
echo "\nFALLÓ — " . count($fallos) . " aserciones.\n";
exit(1);
