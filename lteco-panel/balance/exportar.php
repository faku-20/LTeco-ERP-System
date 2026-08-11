<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();

require_once __DIR__ . "/../includes/helpers.php";

$tipoCambio = obtenerTipoCambioUSD($pdo);

$service = new \Lteco\Application\Balance\BalanceService(
    new \Lteco\Infrastructure\Repository\BalanceRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$filtroDesde = trim((string)($_GET['desde'] ?? ''));
$filtroHasta = trim((string)($_GET['hasta'] ?? ''));

if ($filtroDesde !== '' && function_exists('fechaYmdValida') && !fechaYmdValida($filtroDesde)) {
    $filtroDesde = '';
}

if ($filtroHasta !== '' && function_exists('fechaYmdValida') && !fechaYmdValida($filtroHasta)) {
    $filtroHasta = '';
}

$ventas = $service->ventasExport($filtroDesde, $filtroHasta);
$gastos = $service->gastosExport($filtroDesde, $filtroHasta);

$resumen = [
    'ventas_count' => count($ventas),
    'gastos_count' => count($gastos),
    'ingresos_uyu' => 0.0,
    'cobrado_uyu' => 0.0,
    'saldo_pendiente_uyu' => 0.0,
    'ganancia_estimada_uyu' => 0.0,
    'gastos_uyu' => 0.0,
    'utilidad_estimada_uyu' => 0.0,
    'flujo_caja_uyu' => 0.0,
];

$movimientos = [];

foreach ($ventas as $venta) {
    $moneda = (string)($venta['Moneda'] ?? 'UYU');

    $totalUyu = convertirMontoVentaAUyu((float)($venta['Total'] ?? 0), $moneda, $venta, $tipoCambio);

    $pagadoBase = $venta['MontoPagado'] !== null
        ? (float)$venta['MontoPagado']
        : (float)($venta['Total'] ?? 0);

    $pagadoUyu = convertirMontoVentaAUyu($pagadoBase, $moneda, $venta, $tipoCambio);
    $saldoUyu = convertirMontoVentaAUyu((float)($venta['SaldoPendiente'] ?? 0), $moneda, $venta, $tipoCambio);
    $gananciaUyu = convertirMontoVentaAUyu((float)($venta['GananciaEstimada'] ?? 0), $moneda, $venta, $tipoCambio);

    $resumen['ingresos_uyu'] += $totalUyu;
    $resumen['cobrado_uyu'] += $pagadoUyu;
    $resumen['saldo_pendiente_uyu'] += $saldoUyu;
    $resumen['ganancia_estimada_uyu'] += $gananciaUyu;

    $detallePago = (string)($venta['MetodoPago'] ?? '');

    if ($detallePago === 'Tarjeta') {
        $partesTarjeta = [];

        if (!empty($venta['TipoTarjeta'])) {
            $partesTarjeta[] = $venta['TipoTarjeta'];
        }

        if (!empty($venta['MarcaTarjeta'])) {
            $partesTarjeta[] = $venta['MarcaTarjeta'];
        }

        if (!empty($venta['CuotasTarjeta'])) {
            $partesTarjeta[] = $venta['CuotasTarjeta'] . ' cuotas';
        }

        if ($partesTarjeta) {
            $detallePago .= ' - ' . implode(' - ', $partesTarjeta);
        }
    }

    $movimientos[] = [
        'fecha' => $venta['FechaVenta'] ?? '',
        'tipo' => 'Venta',
        'referencia' => 'Venta #' . ($venta['IdVenta'] ?? ''),
        'cliente_o_categoria' => $venta['Cliente'] ?? '',
        'concepto' => 'Venta confirmada',
        'metodo_pago' => $detallePago,
        'moneda_original' => $moneda,
        'monto_original' => (float)($venta['Total'] ?? 0),
        'ingreso_uyu' => $pagadoUyu,
        'egreso_uyu' => 0,
        'ganancia_estimada_uyu' => $gananciaUyu,
        'saldo_pendiente_uyu' => $saldoUyu,
    ];
}

foreach ($gastos as $gasto) {
    $moneda = (string)($gasto['Moneda'] ?? 'UYU');
    $tcGasto = (float)($gasto['TipoCambioAplicado'] ?? 0) > 0 ? (float)$gasto['TipoCambioAplicado'] : $tipoCambio;
    $montoUyu = convertirAUyu((float)($gasto['Monto'] ?? 0), $moneda, $tcGasto);

    $resumen['gastos_uyu'] += $montoUyu;

    $movimientos[] = [
        'fecha' => $gasto['FechaGasto'] ?? '',
        'tipo' => 'Gasto',
        'referencia' => 'Gasto #' . ($gasto['IdGasto'] ?? ''),
        'cliente_o_categoria' => $gasto['Categoria'] ?? '',
        'concepto' => $gasto['Concepto'] ?? '',
        'metodo_pago' => $gasto['MetodoPago'] ?? '',
        'moneda_original' => $moneda,
        'monto_original' => (float)($gasto['Monto'] ?? 0),
        'ingreso_uyu' => 0,
        'egreso_uyu' => $montoUyu,
        'ganancia_estimada_uyu' => 0,
        'saldo_pendiente_uyu' => 0,
    ];
}

$resumen['utilidad_estimada_uyu'] = \Lteco\Domain\Balance\BalanceCalculo::utilidadEstimada($resumen['ganancia_estimada_uyu'], $resumen['gastos_uyu']);
$resumen['flujo_caja_uyu'] = \Lteco\Domain\Balance\BalanceCalculo::flujoCaja($resumen['cobrado_uyu'], $resumen['gastos_uyu']);

usort($movimientos, static function (array $a, array $b): int {
    return strcmp((string)$b['fecha'], (string)$a['fecha']);
});

$filename = 'balance_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, csvFilaSegura(['BALANCE LTECOBIKE']), ';');
fputcsv($output, csvFilaSegura(['Generado', date('Y-m-d H:i:s')]), ';');
fputcsv($output, csvFilaSegura(['Desde', $filtroDesde !== '' ? $filtroDesde : 'Sin filtro']), ';');
fputcsv($output, csvFilaSegura(['Hasta', $filtroHasta !== '' ? $filtroHasta : 'Sin filtro']), ';');
fputcsv($output, [], ';');

fputcsv($output, csvFilaSegura(['RESUMEN']), ';');
fputcsv($output, csvFilaSegura(['Concepto', 'Valor UYU']), ';');
fputcsv($output, csvFilaSegura(['Ingresos facturados', number_format($resumen['ingresos_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Cobrado real', number_format($resumen['cobrado_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Saldo pendiente', number_format($resumen['saldo_pendiente_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Ganancia estimada', number_format($resumen['ganancia_estimada_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Gastos reales', number_format($resumen['gastos_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Utilidad estimada', number_format($resumen['utilidad_estimada_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Flujo de caja', number_format($resumen['flujo_caja_uyu'], 2, '.', '')]), ';');
fputcsv($output, csvFilaSegura(['Cantidad ventas', (int)$resumen['ventas_count']]), ';');
fputcsv($output, csvFilaSegura(['Cantidad gastos', (int)$resumen['gastos_count']]), ';');

fputcsv($output, [], ';');
fputcsv($output, csvFilaSegura(['MOVIMIENTOS']), ';');

fputcsv($output, csvFilaSegura([
    'Fecha',
    'Tipo',
    'Referencia',
    'Cliente/Categoria',
    'Concepto',
    'MetodoPago',
    'MonedaOriginal',
    'MontoOriginal',
    'Ingreso_UYU',
    'Egreso_UYU',
    'GananciaEstimada_UYU',
    'SaldoPendiente_UYU',
]), ';');

foreach ($movimientos as $movimiento) {
    fputcsv($output, csvFilaSegura([
        $movimiento['fecha'],
        $movimiento['tipo'],
        $movimiento['referencia'],
        $movimiento['cliente_o_categoria'],
        $movimiento['concepto'],
        $movimiento['metodo_pago'],
        $movimiento['moneda_original'],
        number_format((float)$movimiento['monto_original'], 2, '.', ''),
        number_format((float)$movimiento['ingreso_uyu'], 2, '.', ''),
        number_format((float)$movimiento['egreso_uyu'], 2, '.', ''),
        number_format((float)$movimiento['ganancia_estimada_uyu'], 2, '.', ''),
        number_format((float)$movimiento['saldo_pendiente_uyu'], 2, '.', ''),
    ]), ';');
}

fclose($output);
exit;
