<?php
$pageTitle = "Ventas | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';

requiereModulo("ventas");
require_once __DIR__ . "/../includes/helpers.php";

$tipoCambio = obtenerTipoCambioUSD($pdo);

$filtros = \Lteco\Application\Venta\VentaListadoFiltros::desdeInput($_GET);
$repository = new \Lteco\Infrastructure\Repository\VentaListadoRepository(
    new \Lteco\Infrastructure\Db\Connection($pdo)
);
$idUsuarioVendedor = esVendedor()
    ? (int) (usuarioActual()['IdUsuario'] ?? 0)
    : null;
$ventas = $repository->listarPantalla($filtros, $idUsuarioVendedor);
$resumen = (new \Lteco\Application\Venta\VentaListadoService())->resumen(
    $ventas,
    static fn (float $monto, string $moneda, array $venta): float =>
        convertirMontoVentaAUyu($monto, $moneda, $venta, $tipoCambio),
    date('Y-m')
);
$queryExport = http_build_query($filtros->paraQuery());

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main sales-page">
    <div class="topbar">
        <div>
            <h1>Ventas</h1>
            <p class="subtle">Listado completo · ganancias reales y método de pago.</p>
        </div>
        <div class="actions-row">
            <?php if (esAdmin()): ?>
                <a class="btn-secondary" href="<?= panelBaseUrl('ventas/exportar.php' . ($queryExport ? '?' . $queryExport : '')) ?>">Exportar CSV</a>
            <?php endif; ?>
            <a class="btn" href="<?= panelBaseUrl('ventas/crear.php') ?>">+ Nueva venta</a>
        </div>
    </div>

    <?php /* LTECO:FILTROS_VENTAS_REMOVIDOS — usar buscador global */ ?>

    <div class="cards">
        <div class="card">
            <small>Ventas filtradas</small>
            <strong><?= (int)$resumen['cantidad'] ?></strong>
        </div>

        <div class="card">
            <small>Total vendido</small>
            <strong><?= formatearMonto($resumen['total_uyu'], 'UYU') ?></strong>
        </div>

        <?php if (esAdmin()): ?>
            <div class="card">
                <small>Ganancia real</small>
                <strong><?= formatearMonto($resumen['ganancia_uyu'], 'UYU') ?></strong>
            </div>
        <?php endif; ?>

        <div class="card">
            <small>Ventas del mes</small>
            <strong><?= formatearMonto($resumen['mes_uyu'], 'UYU') ?></strong>
        </div>
    </div>

    <div class="table-wrap sales-table-wrap">

        <table class="table sales-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Tipo cliente</th>
                    <?php if (esAdmin()): ?>
                        <th>Vendedor/Distribuidor</th>
                    <?php endif; ?>
                    <th>Moneda</th>
                    <th>Método</th>
                    <th>Estado</th>
                    <th class="sales-table__amount">Total venta</th>
                    <th class="sales-table__amount">Ingreso final (sin IVA)</th>
                    <?php if (esAdmin()): ?>
                        <th class="sales-table__amount">Ganancia</th>
                    <?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ventas): ?>
                    <?php foreach ($ventas as $venta): ?>
                        <?php
                        $estado = $venta['EstadoVenta'] ?? 'Confirmada';
                        $filaClass = $estado === 'Anulada' ? 'anulada-row' : '';

                        $estadoClase = match ($estado) {
                            'Anulada'   => 'sale-state--anulada',
                            'Entregada' => 'sale-state--entregada',
                            'Pendiente' => 'sale-state--pendiente',
                            default     => 'sale-state--confirmada',
                        };
                        ?>
                        <tr class="<?= $filaClass ?>">
                            <td><?= htmlspecialchars((string)$venta['IdVenta']) ?></td>
                            <td>
                                <?= htmlspecialchars((string)($venta['NombreApellido'] ?? 'Sin cliente')) ?>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars((string)($venta['TipoFiscal'] ?? 'Consumidor final')) ?>
                                    <?php if (!empty($venta['RUT'])): ?>
                                        · RUT <?= htmlspecialchars((string)$venta['RUT']) ?>
                                    <?php elseif (!empty($venta['Cedula'])): ?>
                                        · CI <?= htmlspecialchars((string)$venta['Cedula']) ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars((string)($venta['FechaVenta'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($venta['TipoCliente'] ?? '-')) ?></td>
                            <?php if (esAdmin()): ?>
                                <td>
                                    <?php if (!empty($venta['DistribuidorVendedorNombre'])): ?>
                                        <strong><?= h($venta['DistribuidorVendedorNombre'], '') ?></strong><br><small class="text-muted">Distribuidor</small>
                                    <?php else: ?>
                                        <?= h($venta['UsuarioVendedorNombre'] ?? 'Panel interno', '') ?><br><small class="text-muted">Panel interno</small>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars((string)($venta['Moneda'] ?? 'UYU')) ?></td>
                            <td>
                                <?= htmlspecialchars((string)($venta['MetodoPago'] ?? 'Efectivo')) ?>
                                <?php if (($venta['MetodoPago'] ?? '') === 'Tarjeta'): ?>
                                    <br><small class="text-muted">
                                        <?= htmlspecialchars(trim(implode(' ', array_filter([
                                            $venta['TipoTarjeta'] ?? '',
                                            $venta['MarcaTarjeta'] ?? '',
                                            !empty($venta['CuotasTarjeta']) ? $venta['CuotasTarjeta'] . ' cuota' . ((int)$venta['CuotasTarjeta'] === 1 ? '' : 's') : '',
                                        ]))) ?: '-') ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><span class="sale-state <?= $estadoClase ?>"><?= htmlspecialchars((string)$estado) ?></span></td>
                            <td class="sales-table__amount"><?= formatearMonto((float)($venta['Total'] ?? 0), $venta['Moneda'] ?? 'UYU') ?></td>
                            <td class="sales-table__amount"><?php
                                $ingresoFinalFila = $venta['TotalSinIVA'] !== null && $venta['TotalSinIVA'] !== ''
                                    ? (float)$venta['TotalSinIVA']
                                    : round((float)($venta['Total'] ?? 0) - \Lteco\Domain\Venta\ReglasComerciales::ivaIncluido((float)($venta['Total'] ?? 0), defaultTasaIVA()), 2);
                                echo formatearMonto(convertirMontoVentaAUyu($ingresoFinalFila, $venta['Moneda'] ?? 'UYU', $venta, $tipoCambio), 'UYU');
                            ?></td>
                            <?php if (esAdmin()): ?>
                                <td class="sales-table__amount"><?= formatearMonto((float)($venta['GananciaEstimada'] ?? 0), $venta['Moneda'] ?? 'UYU') ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="actions-row" style="gap:6px;">
                                    <a class="btn-secondary btn-small" href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$venta['IdVenta'])) ?>">Ver detalle</a>
                                    <a class="btn-secondary btn-small" href="<?= panelBaseUrl('ventas/comprobante.php?id=' . urlencode((string)$venta['IdVenta'])) ?>" target="_blank" rel="noopener">Comprobante</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= esAdmin() ? 12 : 10 ?>" style="text-align:center; color: var(--ink-3); padding: 32px;">No hay ventas que coincidan con los filtros.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        </div>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
