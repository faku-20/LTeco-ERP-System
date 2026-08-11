<?php

declare(strict_types=1);

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

require_once __DIR__ . '/../Support/PanelTestDb.php';
require_once __DIR__ . '/../Support/PanelTestFixtures.php';

use Lteco\Application\Inventario\InventarioReconciliadorService;
use Lteco\Application\Repuesto\RepuestoCajaService;
use Lteco\Application\Repuesto\RepuestoCrudService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\RepuestoCajaRepository;
use Lteco\Infrastructure\Repository\RepuestoConsultaRepository;
use Lteco\Infrastructure\Repository\RepuestoCrudRepository;

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

function cajaTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function cajaCleanup(PDO $pdo, string $marker): void
{
    if (cajaTableExists($pdo, 'repuesto_caja')) {
        $stmt = $pdo->prepare('SELECT IdCaja FROM repuesto_caja WHERE Nombre LIKE ? OR Ubicacion LIKE ? OR Observaciones LIKE ?');
        $stmt->execute([$marker . '%', $marker . '%', $marker . '%']);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM repuesto_caja WHERE IdCaja IN ({$in})")->execute($ids);
        }
    }

    $prodStmt = $pdo->prepare("SELECT IdProducto FROM producto WHERE Nombre LIKE ? OR Slug LIKE ?");
    $prodStmt->execute(['CajaTest ' . $marker . '%', 'b1-repuesto-' . $marker . '%']);
    $productos = array_map('intval', $prodStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($productos !== []) {
        $in = implode(',', array_fill(0, count($productos), '?'));
        $pdo->prepare("DELETE FROM distribuidor_stock WHERE IdRepuesto IN (SELECT IdRepuesto FROM repuesto WHERE IdProducto IN ({$in}))")->execute($productos);
        $pdo->prepare("DELETE FROM repuesto WHERE IdProducto IN ({$in})")->execute($productos);
        $pdo->prepare("DELETE FROM producto WHERE IdProducto IN ({$in})")->execute($productos);
    }
}

function cajaService(PDO $pdo): RepuestoCajaService
{
    $conn = new Connection($pdo);
    return new RepuestoCajaService(
        new RepuestoCajaRepository($conn),
        new RepuestoCrudService(new RepuestoCrudRepository($conn))
    );
}

$pdo = PanelTestDb::connect();
foreach (['repuesto_caja', 'repuesto_caja_item', 'repuesto_caja_movimiento'] as $table) {
    if (!cajaTableExists($pdo, $table)) {
        fwrite(STDERR, "Falta tabla {$table}. Ejecutá migraciones B5 antes del test.\n");
        exit(1);
    }
}

$marker = 'CAJA_' . bin2hex(random_bytes(4));
$fixtures = new PanelTestFixtures($pdo);
$empresaRut = $fixtures->ensureEmpresa();
$service = cajaService($pdo);
$consulta = new RepuestoConsultaRepository(new Connection($pdo));
$reconciliador = new InventarioReconciliadorService(new Connection($pdo));

try {
    cajaCleanup($pdo, $marker);
    echo "Cajas de repuestos V1\n";

    $repIngreso = $fixtures->repuesto($marker . '-ingreso', 5);
    $cajaIngreso = $service->crear([
        'modo' => 'ingreso',
        'nombre' => $marker . '-Caja ingreso',
        'ubicacion' => $marker . '-ingreso',
        'observaciones' => $marker . '-ingreso',
        'empresa_rut' => $empresaRut,
    ], [[
        'tipo' => 'existente',
        'id_repuesto' => $repIngreso['idRepuesto'],
        'cantidad' => 3,
    ]], null);
    $stockIngreso = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $repIngreso['idProducto'])->fetchColumn();
    $filaCajaIngreso = $pdo->query('SELECT Nombre FROM repuesto_caja WHERE IdCaja = ' . (int) $cajaIngreso['id_caja'])->fetch(PDO::FETCH_ASSOC);
    $itemIngreso = (int) $pdo->query('SELECT Cantidad FROM repuesto_caja_item WHERE IdCaja = ' . (int) $cajaIngreso['id_caja'])->fetchColumn();
    $check('crear caja con repuesto existente aumenta stock', $stockIngreso === 8 && $itemIngreso === 3);
    $check('crear caja persiste nombre visible', is_array($filaCajaIngreso) && $filaCajaIngreso['Nombre'] === $marker . '-Caja ingreso');

    $cajaNueva = $service->crear([
        'modo' => 'ingreso',
        'nombre' => $marker . '-Caja nuevo',
        'ubicacion' => $marker . '-nuevo',
        'observaciones' => $marker . '-nuevo',
        'empresa_rut' => $empresaRut,
    ], [[
        'tipo' => 'nuevo',
        'cantidad' => 4,
        'nombre' => 'CajaTest ' . $marker . ' nuevo',
        'descripcion' => 'Creado desde caja',
        'costo' => 10,
        'gasto_total' => 1,
        'precio_venta' => 100,
        'precio_distribuidor' => 80,
        'moneda' => 'UYU',
        'numero_importacion' => '',
    ]], null);
    $nuevo = $pdo->query("SELECT r.IdRepuesto, p.Stock FROM repuesto r JOIN producto p ON p.IdProducto=r.IdProducto WHERE p.Nombre = " . $pdo->quote('CajaTest ' . $marker . ' nuevo'))->fetch();
    $check('crear nuevo repuesto dentro de caja', is_array($nuevo) && (int) $nuevo['Stock'] === 4);
    $check('QR/código único estable', str_starts_with($cajaNueva['codigo'], 'CJ-') && preg_match('/^[0-9a-f-]{36}$/', $cajaNueva['token_uuid']) === 1 && $cajaNueva['codigo'] !== $cajaIngreso['codigo']);

    $repUbicar = $fixtures->repuesto($marker . '-ubicar', 7);
    $cajaUbicar = $service->crear([
        'modo' => 'ubicar',
        'nombre' => $marker . '-Caja ubicar',
        'ubicacion' => $marker . '-ubicar',
        'observaciones' => $marker . '-ubicar',
        'empresa_rut' => $empresaRut,
    ], [[
        'tipo' => 'existente',
        'id_repuesto' => $repUbicar['idRepuesto'],
        'cantidad' => 2,
    ]], null);
    $stockUbicar = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $repUbicar['idProducto'])->fetchColumn();
    $check('ubicar stock existente no duplica stock', $stockUbicar === 7 && $cajaUbicar['id_caja'] > 0);
    $service->ubicarRepuestoExistente((int)$cajaUbicar['id_caja'], (int)$repUbicar['idRepuesto'], 1, null);
    $stockUbicarEdit = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $repUbicar['idProducto'])->fetchColumn();
    $cantidadUbicarEdit = (int) $pdo->query('SELECT Cantidad FROM repuesto_caja_item WHERE IdCaja = ' . (int)$cajaUbicar['id_caja'] . ' AND IdRepuesto = ' . (int)$repUbicar['idRepuesto'])->fetchColumn();
    $check('editar repuesto permite asociar caja sin duplicar stock', $stockUbicarEdit === 7 && $cantidadUbicarEdit === 3);

    $sobreasignado = false;
    try {
        $service->crear([
            'modo' => 'ubicar',
            'nombre' => $marker . '-Caja sobre',
            'ubicacion' => $marker . '-sobre',
            'observaciones' => $marker . '-sobre',
            'empresa_rut' => $empresaRut,
        ], [[
            'tipo' => 'existente',
            'id_repuesto' => $repUbicar['idRepuesto'],
            'cantidad' => 6,
        ]], null);
    } catch (Throwable) {
        $sobreasignado = true;
    }
    $check('impide sobreasignación por encima del stock', $sobreasignado);

    $repRollback = $fixtures->repuesto($marker . '-rollback', 5);
    $rollbackOk = false;
    try {
        $service->crear([
            'modo' => 'ingreso',
            'nombre' => $marker . '-Caja rollback',
            'ubicacion' => $marker . '-rollback',
            'observaciones' => $marker . '-rollback',
            'empresa_rut' => $empresaRut,
        ], [
            ['tipo' => 'existente', 'id_repuesto' => $repRollback['idRepuesto'], 'cantidad' => 2],
            ['tipo' => 'existente', 'id_repuesto' => 999999999, 'cantidad' => 1],
        ], null);
    } catch (Throwable) {
        $stockRollback = (int) $pdo->query('SELECT Stock FROM producto WHERE IdProducto = ' . (int) $repRollback['idProducto'])->fetchColumn();
        $cajasRollback = (int) $pdo->query("SELECT COUNT(*) FROM repuesto_caja WHERE Ubicacion = " . $pdo->quote($marker . '-rollback'))->fetchColumn();
        $rollbackOk = $stockRollback === 5 && $cajasRollback === 0;
    }
    $check('rollback completo si falla una línea', $rollbackOk);

    $rowsBusqueda = $consulta->listar([
        'estado' => '',
        'q' => 'B1 Repuesto ' . $marker . '-ubicar',
        'importacion' => '',
        'es_distribuidor' => false,
    ]);
    $rowBusqueda = $rowsBusqueda[0] ?? null;
    $check(
        'búsqueda repuesto muestra cajas y sin ubicar',
        is_array($rowBusqueda)
            && str_contains((string)($rowBusqueda['CajasResumen'] ?? ''), $cajaUbicar['codigo'] . ':' . $marker . '-Caja ubicar:3')
            && (int)($rowBusqueda['StockSinUbicar'] ?? -1) === 4
    );

    $limpio = $reconciliador->ejecutar();
    $check('reconciliador limpio no marca cajas válidas', (int)($limpio['repuesto_cajas_sobre_stock']['count'] ?? -1) === 0);

    $idCajaManual = (int) $cajaUbicar['id_caja'];
    $pdo->prepare('UPDATE repuesto_caja_item SET Cantidad = ? WHERE IdCaja = ? AND IdRepuesto = ?')
        ->execute([99, $idCajaManual, $repUbicar['idRepuesto']]);
    $sucio = $reconciliador->ejecutar();
    $check('reconciliador detecta cantidad en cajas mayor al stock', (int)($sucio['repuesto_cajas_sobre_stock']['count'] ?? 0) >= 1);
} finally {
    cajaCleanup($pdo, $marker);
}

if ($fallos !== []) {
    echo "\nFALLO Cajas: " . count($fallos) . " checks fallaron\n";
    foreach ($fallos as $f) {
        echo ' - ' . $f . "\n";
    }
    exit(1);
}

echo "\nOK Cajas: {$ok} checks pasaron\n";
