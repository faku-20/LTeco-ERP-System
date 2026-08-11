<?php
declare(strict_types=1);

/**
 * Limpieza controlada de datos comerciales de prueba.
 *
 * Dry-run:
 *   docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php
 *
 * Ejecutar, solo luego de confirmacion humana:
 *   docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php --execute --confirm=RESET-LTECO-TEST-DATA
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo se puede ejecutar por CLI.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once dirname(__DIR__, 2) . '/tests/Support/PanelTestGuard.php';

$execute = in_array('--execute', $argv, true);
$confirm = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--confirm=')) {
        $confirm = substr($arg, strlen('--confirm='));
    }
}

if ($execute && $confirm !== 'RESET-LTECO-TEST-DATA') {
    fwrite(STDERR, "Falta confirmacion: --confirm=RESET-LTECO-TEST-DATA\n");
    exit(2);
}

if ($execute) {
    try {
        PanelTestGuard::assertSafeForMutation((string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        fwrite(STDERR, "ABORTADO: " . $e->getMessage() . "\n");
        exit(3);
    }
}

function qi(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableCount(PDO $pdo, string $table): int
{
    if (!tableExists($pdo, $table)) {
        return 0;
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . qi($table))->fetchColumn();
}

function scalar(PDO $pdo, string $sql): int
{
    return (int)$pdo->query($sql)->fetchColumn();
}

function run(PDO $pdo, string $label, string $sql): int
{
    $affected = $pdo->exec($sql);
    $affected = $affected === false ? 0 : $affected;
    echo str_pad($label, 42) . $affected . PHP_EOL;
    return $affected;
}

function backupDatabase(): string
{
    [$dbHost, $dbName, $dbUser, $dbPass] = ltecoMysqlEnv();
    $backupDir = ltecoEnsureBackupDir();
    $file = 'pre_cleanup_test_data_' . date('Y-m-d_H-i-s') . '.sql';
    $path = $backupDir . DIRECTORY_SEPARATOR . $file;

    putenv('MYSQL_PWD=' . $dbPass);
    try {
        [$code, $stdout, $stderr] = ltecoRunCommandSeguro([
            'mysqldump',
            '--ssl=0',
            '--single-transaction',
            '--routines',
            '--triggers',
            '-h', $dbHost,
            '-u', $dbUser,
            $dbName,
        ], null, $path);
    } finally {
        putenv('MYSQL_PWD');
    }

    if ($code !== 0) {
        @unlink($path);
        throw new RuntimeException('mysqldump fallo: ' . trim($stderr ?: $stdout));
    }

    @chmod($path, 0640);
    return $path;
}

function printCounts(PDO $pdo, string $title, array $tables): void
{
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
    foreach ($tables as $table) {
        if (tableExists($pdo, $table)) {
            echo str_pad($table, 32) . tableCount($pdo, $table) . PHP_EOL;
        }
    }
}

$allTables = $pdo->query("
    SELECT TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
")->fetchAll(PDO::FETCH_COLUMN);

$trackedTables = [
    'venta',
    'venta_detalle',
    'cliente',
    'garantia',
    'service_vehiculo',
    'service_historial',
    'postventa_historial_tecnico',
    'postventa_repuesto_usado',
    'distribuidor_comision',
    'gasto',
    'notificacion_whatsapp',
    'remito',
    'distribuidor_pedido',
    'distribuidor_stock',
    'auditoria',
    'producto',
    'vehiculo',
    'repuesto',
    'usuario',
    'configuracion',
    'configuracion_negocio',
];

echo 'Modo: ' . ($execute ? 'EXECUTE' : 'DRY-RUN') . PHP_EOL;
echo 'Base: ' . (string)$pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo PHP_EOL . '== Tablas existentes ==' . PHP_EOL;
foreach ($allTables as $table) {
    echo $table . PHP_EOL;
}

printCounts($pdo, 'Conteos antes', $trackedTables);

$dryRunQueries = [
    'ventas objetivo' => "SELECT COUNT(*) FROM venta",
    'detalles objetivo' => "SELECT COUNT(*) FROM venta_detalle WHERE Venta_IdVenta IN (SELECT IdVenta FROM venta)",
    'clientes objetivo' => "SELECT COUNT(*) FROM cliente",
    'motos a dejar disponibles' => "
        SELECT COUNT(DISTINCT v.IdVehiculo)
        FROM venta_detalle vd
        INNER JOIN vehiculo v ON v.IdProducto = vd.Producto_IdProducto
        INNER JOIN producto p ON p.IdProducto = v.IdProducto
        WHERE vd.Venta_IdVenta IN (SELECT IdVenta FROM venta)
          AND p.TipoProducto = 'Moto'
    ",
    'motos legacy vendidas' => "
        SELECT COUNT(*)
        FROM producto p
        INNER JOIN vehiculo v ON v.IdProducto = p.IdProducto
        WHERE p.TipoProducto = 'Moto'
          AND p.Estado = 'Vendido'
          AND NOT EXISTS (
              SELECT 1
              FROM venta_detalle vd
              INNER JOIN venta ve ON ve.IdVenta = vd.Venta_IdVenta
              WHERE vd.Producto_IdProducto = p.IdProducto
                AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
          )
          AND NOT EXISTS (
              SELECT 1
              FROM venta_detalle vd_obj
              WHERE vd_obj.Producto_IdProducto = p.IdProducto
                AND vd_obj.Venta_IdVenta IN (SELECT IdVenta FROM venta)
          )
    ",
    'repuestos a reintegrar lineas' => "
        SELECT COUNT(*)
        FROM venta_detalle vd
        INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
        WHERE vd.Venta_IdVenta IN (SELECT IdVenta FROM venta)
          AND p.TipoProducto = 'Repuesto'
    ",
    'garantias objetivo' => "SELECT COUNT(*) FROM garantia WHERE IdVenta IN (SELECT IdVenta FROM venta)",
    'services objetivo' => "SELECT COUNT(*) FROM service_vehiculo WHERE IdVenta IN (SELECT IdVenta FROM venta)",
    'historial service objetivo' => "
        SELECT COUNT(*)
        FROM service_historial
        WHERE IdVenta IN (SELECT IdVenta FROM venta)
           OR IdService IN (SELECT IdService FROM service_vehiculo WHERE IdVenta IN (SELECT IdVenta FROM venta))
    ",
    'postventa tecnica objetivo' => "
        SELECT COUNT(*)
        FROM postventa_historial_tecnico
        WHERE IdVenta IN (SELECT IdVenta FROM venta)
           OR IdService IN (SELECT IdService FROM service_vehiculo WHERE IdVenta IN (SELECT IdVenta FROM venta))
    ",
    'repuestos postventa objetivo' => "
        SELECT COUNT(*)
        FROM postventa_repuesto_usado
        WHERE IdHistorialTecnico IN (
            SELECT IdHistorialTecnico
            FROM postventa_historial_tecnico
            WHERE IdVenta IN (SELECT IdVenta FROM venta)
               OR IdService IN (SELECT IdService FROM service_vehiculo WHERE IdVenta IN (SELECT IdVenta FROM venta))
        )
    ",
    'comisiones objetivo' => "SELECT COUNT(*) FROM distribuidor_comision WHERE IdVenta IN (SELECT IdVenta FROM venta)",
    'gastos venta objetivo' => "SELECT COUNT(*) FROM gasto WHERE IdVenta IN (SELECT IdVenta FROM venta)",
    'notificaciones objetivo' => "
        SELECT COUNT(*)
        FROM notificacion_whatsapp
        WHERE (Tipo = 'venta' AND IdReferencia IN (SELECT IdVenta FROM venta))
           OR (Tipo = 'service' AND IdReferencia IN (SELECT IdService FROM service_vehiculo WHERE IdVenta IN (SELECT IdVenta FROM venta)))
    ",
    'remitos comerciales' => "SELECT COUNT(*) FROM remito",
    'pedidos distribuidor' => "SELECT COUNT(*) FROM distribuidor_pedido",
    'stock distribuidor asignado' => "SELECT COUNT(*) FROM distribuidor_stock",
    'auditoria comercial' => "
        SELECT COUNT(*)
        FROM auditoria
        WHERE Modulo IN ('Ventas', 'Clientes', 'Distribuidores', 'Postventa')
           OR Accion LIKE '%VENTA%'
           OR Accion LIKE '%CLIENTE%'
           OR Accion LIKE '%SERVICE%'
    ",
];

echo PHP_EOL . '== Dry-run objetivo ==' . PHP_EOL;
foreach ($dryRunQueries as $label => $sql) {
    if (!$execute) {
        echo str_pad($label, 42) . scalar($pdo, $sql) . PHP_EOL;
    }
}

if (!$execute) {
    echo PHP_EOL . "No se modifico la base. Para ejecutar: --execute --confirm=RESET-LTECO-TEST-DATA\n";
    exit(0);
}

$backupPath = backupDatabase();
echo PHP_EOL . 'Backup creado: ' . $backupPath . PHP_EOL;

$pdo->beginTransaction();
try {
    $pdo->exec('CREATE TEMPORARY TABLE tmp_cleanup_ventas (IdVenta INT PRIMARY KEY) ENGINE=MEMORY AS SELECT IdVenta FROM venta');
    $pdo->exec('CREATE TEMPORARY TABLE tmp_cleanup_clientes (IdCliente INT PRIMARY KEY) ENGINE=MEMORY AS SELECT IdCliente FROM cliente');
    $pdo->exec("
        CREATE TEMPORARY TABLE tmp_cleanup_productos_venta (IdProducto INT PRIMARY KEY) ENGINE=MEMORY AS
        SELECT DISTINCT Producto_IdProducto AS IdProducto
        FROM venta_detalle
        WHERE Venta_IdVenta IN (SELECT IdVenta FROM tmp_cleanup_ventas)
    ");
    $pdo->exec("
        CREATE TEMPORARY TABLE tmp_cleanup_services (IdService INT PRIMARY KEY) ENGINE=MEMORY AS
        SELECT IdService FROM service_vehiculo WHERE IdVenta IN (SELECT IdVenta FROM tmp_cleanup_ventas)
    ");
    $pdo->exec("
        CREATE TEMPORARY TABLE tmp_cleanup_postventa (IdHistorialTecnico INT PRIMARY KEY) ENGINE=MEMORY AS
        SELECT IdHistorialTecnico
        FROM postventa_historial_tecnico
        WHERE IdVenta IN (SELECT IdVenta FROM tmp_cleanup_ventas)
           OR IdService IN (SELECT IdService FROM tmp_cleanup_services)
    ");

    echo PHP_EOL . '== Cambios ==' . PHP_EOL;

    run($pdo, 'Reintegrar stock repuestos', "
        UPDATE producto p
        INNER JOIN (
            SELECT vd.Producto_IdProducto, SUM(vd.Cantidad) AS CantidadVendida
            FROM venta_detalle vd
            INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = vd.Venta_IdVenta
            INNER JOIN producto pr ON pr.IdProducto = vd.Producto_IdProducto
            WHERE pr.TipoProducto = 'Repuesto'
            GROUP BY vd.Producto_IdProducto
        ) x ON x.Producto_IdProducto = p.IdProducto
        SET p.Stock = p.Stock + x.CantidadVendida,
            p.Estado = CASE WHEN p.Estado = 'Oculto' THEN p.Estado ELSE 'Disponible' END
    ");

    run($pdo, 'Dejar motos disponibles', "
        UPDATE producto p
        INNER JOIN vehiculo v ON v.IdProducto = p.IdProducto
        INNER JOIN venta_detalle vd ON vd.Producto_IdProducto = p.IdProducto
        INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = vd.Venta_IdVenta
        SET p.Stock = 1,
            p.Estado = 'Disponible',
            p.MostrarEnWeb = 0,
            p.DestacadoWeb = 0,
            v.FechaVenta = NULL,
            v.ClienteReservaId = NULL,
            v.FechaReserva = NULL,
            v.SeniaReserva = NULL
        WHERE p.TipoProducto = 'Moto'
    ");

    run($pdo, 'Borrar repuestos postventa', "DELETE pru FROM postventa_repuesto_usado pru INNER JOIN tmp_cleanup_postventa t ON t.IdHistorialTecnico = pru.IdHistorialTecnico");
    run($pdo, 'Borrar historial postventa', "DELETE pht FROM postventa_historial_tecnico pht INNER JOIN tmp_cleanup_postventa t ON t.IdHistorialTecnico = pht.IdHistorialTecnico");
    run($pdo, 'Borrar historial service', "DELETE sh FROM service_historial sh LEFT JOIN tmp_cleanup_services ts ON ts.IdService = sh.IdService LEFT JOIN tmp_cleanup_ventas tv ON tv.IdVenta = sh.IdVenta WHERE ts.IdService IS NOT NULL OR tv.IdVenta IS NOT NULL");
    run($pdo, 'Borrar notificaciones WA', "DELETE nw FROM notificacion_whatsapp nw LEFT JOIN tmp_cleanup_ventas tv ON nw.Tipo = 'venta' AND nw.IdReferencia = tv.IdVenta LEFT JOIN tmp_cleanup_services ts ON nw.Tipo = 'service' AND nw.IdReferencia = ts.IdService WHERE tv.IdVenta IS NOT NULL OR ts.IdService IS NOT NULL");
    run($pdo, 'Borrar garantias', "DELETE g FROM garantia g INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = g.IdVenta");
    run($pdo, 'Borrar services', "DELETE sv FROM service_vehiculo sv INNER JOIN tmp_cleanup_services ts ON ts.IdService = sv.IdService");
    run($pdo, 'Borrar comisiones distribuidor', "DELETE dc FROM distribuidor_comision dc INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = dc.IdVenta");
    run($pdo, 'Borrar gastos de venta', "DELETE g FROM gasto g INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = g.IdVenta");
    run($pdo, 'Desvincular remitos de ventas', "UPDATE remito r INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = r.IdVenta SET r.IdVenta = NULL, r.FechaFactura = NULL, r.NumeroFactura = NULL, r.ReferenciaFactura = NULL, r.Estado = 'Anulado'");
    run($pdo, 'Borrar remitos', "DELETE FROM remito");
    run($pdo, 'Borrar pedidos distribuidor', "DELETE FROM distribuidor_pedido");
    run($pdo, 'Borrar stock distribuidor', "DELETE FROM distribuidor_stock");
    run($pdo, 'Borrar detalle ventas', "DELETE vd FROM venta_detalle vd INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = vd.Venta_IdVenta");
    run($pdo, 'Borrar ventas', "DELETE v FROM venta v INNER JOIN tmp_cleanup_ventas tv ON tv.IdVenta = v.IdVenta");
    run($pdo, 'Limpiar reservas clientes', "UPDATE vehiculo v INNER JOIN tmp_cleanup_clientes tc ON tc.IdCliente = v.ClienteReservaId SET v.ClienteReservaId = NULL, v.FechaReserva = NULL, v.SeniaReserva = NULL");
    run($pdo, 'Borrar clientes', "DELETE c FROM cliente c INNER JOIN tmp_cleanup_clientes tc ON tc.IdCliente = c.IdCliente");
    run($pdo, 'Borrar auditoria comercial', "
        DELETE FROM auditoria
        WHERE Modulo IN ('Ventas', 'Clientes', 'Distribuidores', 'Postventa')
           OR Accion LIKE '%VENTA%'
           OR Accion LIKE '%CLIENTE%'
           OR Accion LIKE '%SERVICE%'
    ");
    run($pdo, 'Reset motos legacy vendidas', "
        UPDATE producto p
        INNER JOIN vehiculo v ON v.IdProducto = p.IdProducto
        LEFT JOIN tmp_cleanup_productos_venta tpv ON tpv.IdProducto = p.IdProducto
        SET p.Estado = 'Disponible',
            p.Stock = 1,
            p.MostrarEnWeb = 0,
            p.DestacadoWeb = 0,
            v.FechaVenta = NULL,
            v.ClienteReservaId = NULL,
            v.FechaReserva = NULL,
            v.SeniaReserva = NULL
        WHERE p.TipoProducto = 'Moto'
          AND p.Estado = 'Vendido'
          AND tpv.IdProducto IS NULL
          AND NOT EXISTS (
              SELECT 1
              FROM venta_detalle vd
              INNER JOIN venta ve ON ve.IdVenta = vd.Venta_IdVenta
              WHERE vd.Producto_IdProducto = p.IdProducto
                AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
          )
    ");

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

foreach (['venta', 'venta_detalle', 'cliente', 'garantia', 'service_vehiculo', 'service_historial', 'postventa_historial_tecnico', 'postventa_repuesto_usado', 'distribuidor_comision', 'gasto', 'notificacion_whatsapp', 'remito', 'distribuidor_pedido', 'distribuidor_stock'] as $table) {
    if (tableExists($pdo, $table) && tableCount($pdo, $table) === 0) {
        $pdo->exec('ALTER TABLE ' . qi($table) . ' AUTO_INCREMENT = 1');
    }
}

printCounts($pdo, 'Conteos despues', $trackedTables);
