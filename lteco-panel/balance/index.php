<?php
$pageTitle = "Balance | Ltecobike";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
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

if ($filtroDesde !== '' && !fechaYmdValida($filtroDesde)) {
    $filtroDesde = '';
}
if ($filtroHasta !== '' && !fechaYmdValida($filtroHasta)) {
    $filtroHasta = '';
}

$ventas = $service->ventasResumen($filtroDesde, $filtroHasta);
$gastos = $service->gastosResumen($filtroDesde, $filtroHasta);

$resumen = [
    'ventas_count' => count($ventas),
    'gastos_count' => count($gastos),
    'ingresos_uyu' => 0.0,
    'cobrado_uyu' => 0.0,
    'saldo_pendiente_uyu' => 0.0,
    'ganancia_estimada_uyu' => 0.0,
    'gastos_uyu' => 0.0,
    'iva_uyu' => 0.0,
    'utilidad_estimada_uyu' => 0.0,
    'flujo_caja_uyu' => 0.0,
    'margen_estimado_pct' => 0.0,
    'gasto_sobre_ingreso_pct' => 0.0,
    'cobrado_pct' => 0.0,
];

$gastosPorCategoria = [];
$gastosPorMetodo = [];
$movimientos = [];
$mesesLabels = [];
$mesesVentas = [];
$mesesGanancia = [];
$mesesGastos = [];
$mesesResultado = [];

for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i month");
    $key = date('Y-m', $ts);
    $mesesLabels[$key] = date('m/Y', $ts);
    $mesesVentas[$key] = 0.0;
    $mesesGanancia[$key] = 0.0;
    $mesesGastos[$key] = 0.0;
    $mesesResultado[$key] = 0.0;
}

foreach ($ventas as $venta) {
    $moneda = (string)($venta['Moneda'] ?? 'UYU');
    $totalUyu = convertirMontoVentaAUyu((float)($venta['Total'] ?? 0), $moneda, $venta, $tipoCambio);
    $pagadoBase = $venta['MontoPagado'] !== null ? (float)$venta['MontoPagado'] : (float)($venta['Total'] ?? 0);
    $pagadoUyu = convertirMontoVentaAUyu($pagadoBase, $moneda, $venta, $tipoCambio);
    $saldoUyu = convertirMontoVentaAUyu((float)($venta['SaldoPendiente'] ?? 0), $moneda, $venta, $tipoCambio);
    $gananciaUyu = convertirMontoVentaAUyu((float)($venta['GananciaEstimada'] ?? 0), $moneda, $venta, $tipoCambio);
    $ivaBase = $venta['MontoIVA'] !== null && $venta['MontoIVA'] !== ''
        ? (float)$venta['MontoIVA']
        : \Lteco\Domain\Balance\BalanceCalculo::ivaDesdeBruto((float)($venta['Total'] ?? 0));
    $ivaUyu = convertirMontoVentaAUyu($ivaBase, $moneda, $venta, $tipoCambio);

    $resumen['ingresos_uyu'] += $totalUyu;
    $resumen['cobrado_uyu'] += $pagadoUyu;
    $resumen['saldo_pendiente_uyu'] += $saldoUyu;
    $resumen['ganancia_estimada_uyu'] += $gananciaUyu;
    $resumen['iva_uyu'] += $ivaUyu;

    $periodo = substr((string)($venta['FechaVenta'] ?? ''), 0, 7);
    if (isset($mesesVentas[$periodo])) {
        $mesesVentas[$periodo] += $totalUyu;
        $mesesGanancia[$periodo] += $gananciaUyu;
    }

    $movimientos[] = [
        'tipo' => 'Venta',
        'fecha' => $venta['FechaVenta'] ?? '',
        'concepto' => 'Venta #' . ($venta['IdVenta'] ?? ''),
        'monto' => $pagadoUyu,
        'clase' => 'ingreso',
    ];
}

foreach ($gastos as $gasto) {
    $tcGasto = (float)($gasto['TipoCambioAplicado'] ?? 0) > 0 ? (float)$gasto['TipoCambioAplicado'] : $tipoCambio;
    $montoUyu = convertirAUyu((float)($gasto['Monto'] ?? 0), $gasto['Moneda'] ?? 'UYU', $tcGasto);
    $resumen['gastos_uyu'] += $montoUyu;

    $categoria = trim((string)($gasto['Categoria'] ?? 'Otros')) ?: 'Otros';
    $metodo = trim((string)($gasto['MetodoPago'] ?? 'Otro')) ?: 'Otro';
    $gastosPorCategoria[$categoria] = ($gastosPorCategoria[$categoria] ?? 0.0) + $montoUyu;
    $gastosPorMetodo[$metodo] = ($gastosPorMetodo[$metodo] ?? 0.0) + $montoUyu;

    $periodo = substr((string)($gasto['FechaGasto'] ?? ''), 0, 7);
    if (isset($mesesGastos[$periodo])) {
        $mesesGastos[$periodo] += $montoUyu;
    }

    $movimientos[] = [
        'tipo' => 'Gasto',
        'fecha' => $gasto['FechaGasto'] ?? '',
        'concepto' => $categoria . ' - ' . ($gasto['Concepto'] ?? ''),
        'monto' => $montoUyu,
        'clase' => 'egreso',
    ];
}

foreach (array_keys($mesesLabels) as $periodo) {
    $mesesResultado[$periodo] = \Lteco\Domain\Balance\BalanceCalculo::resultadoMensual($mesesGanancia[$periodo], $mesesGastos[$periodo]);
}

$resumen['utilidad_estimada_uyu'] = \Lteco\Domain\Balance\BalanceCalculo::utilidadEstimada($resumen['ganancia_estimada_uyu'], $resumen['gastos_uyu']);
$resumen['flujo_caja_uyu'] = \Lteco\Domain\Balance\BalanceCalculo::flujoCaja($resumen['cobrado_uyu'], $resumen['gastos_uyu']);
$resumen['margen_estimado_pct'] = porcentajeSeguro($resumen['ganancia_estimada_uyu'], $resumen['ingresos_uyu']);
$resumen['gasto_sobre_ingreso_pct'] = porcentajeSeguro($resumen['gastos_uyu'], $resumen['ingresos_uyu']);
$resumen['cobrado_pct'] = porcentajeSeguro($resumen['cobrado_uyu'], $resumen['ingresos_uyu']);

$resumen['ingresos_neto_uyu'] = \Lteco\Domain\Balance\BalanceCalculo::ingresosNeto($resumen['ingresos_uyu'], $resumen['iva_uyu']);
$resumen['utilidad_neta_uyu'] = \Lteco\Domain\Balance\BalanceCalculo::utilidadNeta($resumen['ingresos_neto_uyu'], $resumen['gastos_uyu']);

arsort($gastosPorCategoria);
arsort($gastosPorMetodo);
usort($movimientos, fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
$movimientos = array_slice($movimientos, 0, 14);

$chartCategorias = [
    'labels' => array_keys($gastosPorCategoria),
    'values' => array_map(fn($v) => round($v, 2), array_values($gastosPorCategoria)),
];
$chartBalanceMensual = [
    'labels' => array_values($mesesLabels),
    'ventas' => array_map(fn($v) => round($v, 2), array_values($mesesVentas)),
    'ganancia' => array_map(fn($v) => round($v, 2), array_values($mesesGanancia)),
    'gastos' => array_map(fn($v) => round($v, 2), array_values($mesesGastos)),
    'resultado' => array_map(fn($v) => round($v, 2), array_values($mesesResultado)),
];

$queryExport = http_build_query([
    'desde' => $filtroDesde,
    'hasta' => $filtroHasta,
]);

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main balance-page">
    <div class="topbar balance-topbar">
        <div>
            <h1>Balance</h1>
            <p class="session-note">Lectura financiera en UYU, usando tipo de cambio histórico cuando la venta lo tiene guardado.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('balance/exportar.php' . ($queryExport ? '?' . $queryExport : '')) ?>">Exportar CSV</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('gastos/index.php') ?>">Ver gastos</a>
            <a class="btn" href="<?= panelBaseUrl('gastos/crear.php') ?>">+ Nuevo gasto</a>
        </div>
    </div>

    <div class="section-box balance-filters-box">
        <form method="GET" class="balance-filter-form">
            <div class="balance-filter-field">
                <label class="form-label">Desde</label>
                <input type="date" name="desde" value="<?= h($filtroDesde, '') ?>" class="input u-w-100">
            </div>
            <div class="balance-filter-field">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta" value="<?= h($filtroHasta, '') ?>" class="input u-w-100">
            </div>
            <div class="balance-filter-actions">
                <button type="submit" class="btn">Filtrar</button>
                <a href="<?= panelBaseUrl('balance/index.php') ?>" class="btn-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="cards cards--executive">
        <div class="card metric-card"><h3>Ingresos facturados</h3><strong><?= formatearMonto($resumen['ingresos_uyu'], 'UYU') ?></strong><span><?= (int)$resumen['ventas_count'] ?> ventas (con IVA)</span></div>
        <div class="card metric-card finance-card--negative"><h3>IVA a pagar</h3><strong class="text-danger"><?= formatearMonto($resumen['iva_uyu'], 'UYU') ?></strong><span><?= number_format(defaultTasaIVA(), 2, ',', '.') ?>% extraído del bruto</span></div>
        <div class="card metric-card"><h3>Ingresos netos</h3><strong><?= formatearMonto($resumen['ingresos_neto_uyu'], 'UYU') ?></strong><span>Facturado sin IVA</span></div>
        <div class="card metric-card"><h3>Gastos reales</h3><strong><?= formatearMonto($resumen['gastos_uyu'], 'UYU') ?></strong><span><?= (int)$resumen['gastos_count'] ?> gastos</span></div>
    </div>

    <div class="section-box balance-note">
        <strong>Lectura recomendada:</strong>
        <span>Los ingresos facturados incluyen IVA. El IVA a pagar se separa desde el bruto con la tasa vigente para mostrarte los ingresos netos reales. La utilidad neta es lo que queda después de restar IVA y gastos.</span>
    </div>

    <div class="two-column-grid">
        <div class="section-box">
            <h2 class="section-title">Evolución últimos 6 meses</h2>
            <canvas id="chartBalanceMensual" height="170"></canvas>
        </div>
        <div class="section-box">
            <h2 class="section-title">Gastos por categoría</h2>
            <?php if ($gastosPorCategoria): ?>
                <canvas id="chartCategoriasGasto" height="170"></canvas>
            <?php else: ?>
                <p>No hay gastos para mostrar.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="two-column-grid">
        <div class="section-box balance-table-card">
            <h2 class="section-title">Desglose por categoría</h2>
            <div class="table-wrap balance-table-wrap">
<table class="table balance-table">
                <thead><tr><th>Categoría</th><th>Total UYU</th><th>% gastos</th></tr></thead>
                <tbody>
                <?php if ($gastosPorCategoria): ?>
                    <?php foreach ($gastosPorCategoria as $categoria => $monto): ?>
                        <tr>
                            <td><?= h($categoria) ?></td>
                            <td><?= formatearMonto((float)$monto, 'UYU') ?></td>
                            <td><?= porcentajeSeguro((float)$monto, $resumen['gastos_uyu']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">No hay gastos cargados en el período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
</div>
        </div>
        <div class="section-box balance-table-card">
            <h2 class="section-title">Gastos por método de pago</h2>
            <div class="table-wrap balance-table-wrap">
<table class="table balance-table">
                <thead><tr><th>Método</th><th>Total UYU</th></tr></thead>
                <tbody>
                <?php if ($gastosPorMetodo): ?>
                    <?php foreach ($gastosPorMetodo as $metodo => $monto): ?>
                        <tr><td><?= h($metodo) ?></td><td><?= formatearMonto((float)$monto, 'UYU') ?></td></tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2">No hay gastos cargados en el período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
</div>
        </div>
    </div>

    <div class="section-box balance-table-card">
        <h2 class="section-title">Últimos movimientos financieros</h2>
        <div class="table-wrap balance-table-wrap">
<table class="table balance-table balance-movements-table">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Monto UYU</th></tr></thead>
            <tbody>
            <?php if ($movimientos): ?>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?= h(formatearFechaCorta($mov['fecha'] ?? '')) ?></td>
                        <td><span class="badge <?= ($mov['clase'] ?? '') === 'egreso' ? 'badge-anulada' : 'badge-activo' ?>"><?= h($mov['tipo']) ?></span></td>
                        <td><?= h($mov['concepto']) ?></td>
                        <td class="<?= ($mov['clase'] ?? '') === 'egreso' ? 'text-danger' : 'text-success' ?> text-bold-strong">
                            <?= (($mov['clase'] ?? '') === 'egreso' ? '- ' : '+ ') . formatearMonto((float)($mov['monto'] ?? 0), 'UYU') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No hay movimientos para mostrar.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
</div>
    </div>
</main>

<script src="<?= panelBaseUrl('assets/vendor/chartjs/chart.umd.js') ?>"></script>
<script nonce="<?= cspNonce() ?>">
const categoriasGastoData = <?= json_encode($chartCategorias, JSON_UNESCAPED_UNICODE) ?>;
const balanceMensualData = <?= json_encode($chartBalanceMensual, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('chartBalanceMensual'), {
    type: 'line',
    data: {
        labels: balanceMensualData.labels,
        datasets: [
            { label: 'Ingresos', data: balanceMensualData.ventas, borderWidth: 2, tension: 0.25 },
            { label: 'Ganancia', data: balanceMensualData.ganancia, borderWidth: 2, tension: 0.25 },
            { label: 'Gastos', data: balanceMensualData.gastos, borderWidth: 2, tension: 0.25 },
            { label: 'Resultado', data: balanceMensualData.resultado, borderWidth: 2, tension: 0.25 }
        ]
    },
    options: { responsive: true, plugins: { legend: { display: true } } }
});

if (categoriasGastoData.labels.length > 0) {
    new Chart(document.getElementById('chartCategoriasGasto'), {
        type: 'bar',
        data: { labels: categoriasGastoData.labels, datasets: [{ label: 'Gasto UYU', data: categoriasGastoData.values, borderWidth: 1 }] },
        options: { responsive: true, plugins: { legend: { display: true } } }
    });
}
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
