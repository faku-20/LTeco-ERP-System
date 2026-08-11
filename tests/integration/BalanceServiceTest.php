<?php

declare(strict_types=1);

/**
 * Integración E5. Verifica las lecturas del balance: exclusión de ventas
 * anuladas y gastos inactivos, rango de fechas y el distinto set de columnas
 * entre resumen y exportación. Todo dentro de una transacción con rollback.
 */

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

use Lteco\Application\Balance\BalanceService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\BalanceRepository;

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
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$idsEn = static fn(array $filas, string $col): array => array_map('intval', array_column($filas, $col));

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new BalanceService(new BalanceRepository(new Connection($pdo)));

    // --- Gastos (esquema conocido, inserción directa) ---
    $insG = $pdo->prepare("INSERT INTO gasto (FechaGasto, Concepto, Categoria, MetodoPago, Moneda, Monto, Estado, TipoCambioAplicado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insG->execute(['2099-01-10', 'E5 g activo', 'Repuestos', 'Efectivo', 'UYU', 100.00, 'Activo', 40.0000]);
    $gActivo = (int) $pdo->lastInsertId();
    $insG->execute(['2099-02-15', 'E5 g anulado', 'Repuestos', 'Efectivo', 'UYU', 999.00, 'Anulado', 40.0000]);
    $gAnulado = (int) $pdo->lastInsertId();

    $ventana = ['2099-01-01', '2099-12-31'];

    // gastosResumen: incluye activo, excluye anulado, trae TipoCambioAplicado
    $gr = $service->gastosResumen(...$ventana);
    $grIds = $idsEn($gr, 'IdGasto');
    $check('gastosResumen incluye el gasto activo', in_array($gActivo, $grIds, true));
    $check('gastosResumen excluye el gasto anulado', !in_array($gAnulado, $grIds, true));
    $filaGr = null;
    foreach ($gr as $r) { if ((int) $r['IdGasto'] === $gActivo) { $filaGr = $r; break; } }
    $check('gastosResumen expone TipoCambioAplicado', $filaGr !== null && array_key_exists('TipoCambioAplicado', $filaGr));

    // gastosExport: incluye activo, excluye anulado, SIN TipoCambioAplicado
    $ge = $service->gastosExport(...$ventana);
    $geIds = $idsEn($ge, 'IdGasto');
    $check('gastosExport incluye el gasto activo', in_array($gActivo, $geIds, true));
    $check('gastosExport excluye el gasto anulado', !in_array($gAnulado, $geIds, true));
    $filaGe = null;
    foreach ($ge as $r) { if ((int) $r['IdGasto'] === $gActivo) { $filaGe = $r; break; } }
    $check('gastosExport NO expone TipoCambioAplicado', $filaGe !== null && !array_key_exists('TipoCambioAplicado', $filaGe));

    // gastos: rango de fechas (desde > fecha del activo lo excluye)
    $grRango = $service->gastosResumen('2099-02-01', '2099-12-31');
    $check('gastos respeta desde (excluye el de 2099-01-10)', !in_array($gActivo, $idsEn($grRango, 'IdGasto'), true));

    // --- Ventas (requieren un cliente existente) ---
    $idCliente = $pdo->query("SELECT IdCliente FROM cliente LIMIT 1")->fetchColumn();
    if ($idCliente !== false) {
        $idCliente = (int) $idCliente;
        $insV = $pdo->prepare("INSERT INTO venta (FechaVenta, EstadoVenta, Total, Moneda, GananciaEstimada, MontoPagado, SaldoPendiente, TipoCambioAplicado, Cliente_IdCliente) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insV->execute(['2099-01-10', 'Confirmada', 1000.00, 'UYU', 300.00, 600.00, 400.00, 40.0000, $idCliente]);
        $vConfirmada = (int) $pdo->lastInsertId();
        $insV->execute(['2099-03-20', 'Anulada', 5000.00, 'UYU', 9999.00, 5000.00, 0.00, 40.0000, $idCliente]);
        $vAnulada = (int) $pdo->lastInsertId();
        $insV->execute(['2099-02-01', 'Entregada', 2000.00, 'UYU', 500.00, 2000.00, 0.00, 40.0000, $idCliente]);
        $vEntregada = (int) $pdo->lastInsertId();

        // ventasResumen: incluye no anuladas, excluye anulada, SIN columna Cliente
        $vr = $service->ventasResumen(...$ventana);
        $vrIds = $idsEn($vr, 'IdVenta');
        $check('ventasResumen incluye la confirmada', in_array($vConfirmada, $vrIds, true));
        $check('ventasResumen incluye la entregada', in_array($vEntregada, $vrIds, true));
        $check('ventasResumen excluye la anulada', !in_array($vAnulada, $vrIds, true));
        $filaVr = null;
        foreach ($vr as $r) { if ((int) $r['IdVenta'] === $vConfirmada) { $filaVr = $r; break; } }
        $check('ventasResumen NO expone Cliente ni datos de tarjeta', $filaVr !== null && !array_key_exists('Cliente', $filaVr) && !array_key_exists('MarcaTarjeta', $filaVr));

        // ventasExport: incluye no anuladas, excluye anulada, CON Cliente + tarjeta
        $ve = $service->ventasExport(...$ventana);
        $veIds = $idsEn($ve, 'IdVenta');
        $check('ventasExport incluye la confirmada', in_array($vConfirmada, $veIds, true));
        $check('ventasExport excluye la anulada', !in_array($vAnulada, $veIds, true));
        $filaVe = null;
        foreach ($ve as $r) { if ((int) $r['IdVenta'] === $vConfirmada) { $filaVe = $r; break; } }
        $check('ventasExport expone Cliente y datos de tarjeta', $filaVe !== null && array_key_exists('Cliente', $filaVe) && array_key_exists('MarcaTarjeta', $filaVe));

        // ventas: rango de fechas (desde > fecha de la confirmada la excluye)
        $vrRango = $service->ventasResumen('2099-02-01', '2099-12-31');
        $check('ventas respeta desde (excluye la de 2099-01-10)', !in_array($vConfirmada, $idsEn($vrRango, 'IdVenta'), true));
    } else {
        echo "  (sin clientes en la DB: se omite la verificación de ventas)\n";
    }
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
