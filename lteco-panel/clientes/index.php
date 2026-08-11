<?php
$pageTitle = "Clientes | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
requiereModulo("clientes");
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Cliente\ClienteConsultaService(
    new \Lteco\Infrastructure\Repository\ClienteConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$tipoCambio = obtenerTipoCambioUSD($pdo);
$filtroCliente = trim((string)($_GET['cliente'] ?? ''));
$filtroTipo = trim((string)($_GET['tipo'] ?? ''));
$idUsuarioVendedor = esVendedor() ? (int)(usuarioActual()['IdUsuario'] ?? 0) : 0;

$clientes = $service->listar($filtroCliente, $idUsuarioVendedor, (float)$tipoCambio);

// Aplicar filtro por tipo en memoria (depende de Compras / SaldoPendiente)
$clientes = $service->aplicarFiltroTipo($clientes, $filtroTipo);

$queryExport = http_build_query(['cliente' => $filtroCliente, 'tipo' => $filtroTipo]);

// Conteos para pills (todos los clientes, o solo cartera propia para Vendedor)
$conteos = $service->conteos($idUsuarioVendedor, (float)$tipoCambio);
$totalClientes = $conteos['total'];
$conCompras = $conteos['conCompras'];
$conSaldo = $conteos['conSaldo'];
$sinCompras = $conteos['sinCompras'];

$pills = [
    ''             => ['Todos', $totalClientes],
    'con_compras'  => ['Con compras', $conCompras],
    'sin_compras'  => ['Sin compras', $sinCompras],
    'saldo'        => ['Con saldo pendiente', $conSaldo],
];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Clientes</h1>
            <p class="subtle">Ficha limpia de clientes + resumen calculado desde ventas reales.</p>
        </div>
        <div class="actions-row">
            <a class="btn" href="<?= panelBaseUrl('clientes/crear.php') ?>">+ Nuevo cliente</a>
            <?php if (esAdmin()): ?>
                <a class="btn-secondary" href="<?= panelBaseUrl('clientes/exportar.php' . ($queryExport ? '?' . $queryExport : '')) ?>">Exportar CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="v4-card filters-v4">
        <div class="quick-pills-v4">
            <?php foreach ($pills as $val => [$label, $count]): ?>
                <a class="<?= $filtroTipo === $val ? 'active' : '' ?>"
                   href="<?= panelBaseUrl('clientes/index.php' . ($val !== '' ? '?tipo=' . urlencode($val) : '')) ?><?= $filtroCliente !== '' ? (str_contains($val, '?') || $val === '' ? '?' : '&') . 'cliente=' . urlencode($filtroCliente) : '' ?>">
                    <?= htmlspecialchars($label) ?>
                    <span class="pill-count"><?= $count ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="filters-grid-v4">
            <?php if ($filtroTipo !== ''): ?>
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtroTipo) ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Buscar cliente</label>
                <input type="text" name="cliente" value="<?= h($filtroCliente, '') ?>" placeholder="Nombre, teléfono, correo, cédula, dirección o RUT">
            </div>
            <div class="filter-actions-v4">
                <button class="btn" type="submit">Aplicar</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('clientes/index.php') ?>">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Listado de clientes</h2>
                <p>Compras, total gastado, saldo pendiente y última actividad.</p>
            </div>
            <div class="result-pill-v4"><?= count($clientes) ?> resultados</div>
        </div>
    </section>

        <div class="table-wrap">

        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Documento</th>
                    <th class="num">Compras</th>
                    <th class="num">Total gastado UYU</th>
                    <th class="num">Saldo pendiente UYU</th>
                    <th>Última compra</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($clientes): ?>
                    <?php foreach ($clientes as $c): ?>
                        <?php
                        $compras = (int)($c['Compras'] ?? 0);
                        $totalUyu = (float)($c['TotalGastadoUYU'] ?? 0);
                        $saldoUyu = (float)($c['SaldoPendienteUYU'] ?? 0);
                        ?>
                        <tr>
                            <td><strong><?= h($c['NombreApellido'], '') ?></strong></td>
                            <td><?= h($c['TipoFiscal'] ?? 'Consumidor final', '') ?></td>
                            <td><?= h($c['Telefono'] ?? '-', '-') ?></td>
                            <td><?= h($c['Correo'] ?? '-', '-') ?></td>
                            <td>
                                <?= h($c['Cedula'] ?? '-', '-') ?><br>
                                <small><?= h($c['RUT'] ?? '', '') ?></small>
                            </td>
                            <td class="num"><span class="count-badge <?= $compras > 0 ? 'count-badge--success' : 'count-badge--muted' ?>"><?= $compras ?></span></td>
                            <td class="num"><?= formatearMonto($totalUyu, 'UYU') ?></td>
                            <td class="num"><?= $saldoUyu > 0 ? '<span class="text-danger text-bold-strong">' . formatearMonto($saldoUyu, 'UYU') . '</span>' : formatearMonto(0, 'UYU') ?></td>
                            <td><?= $c['UltimaCompra'] ? h(formatearFechaCorta((string)$c['UltimaCompra']), '') : '-' ?></td>
                            <td>
                                <div class="actions-wrap">
                                    <a class="btn-secondary btn-small" href="<?= panelBaseUrl('clientes/detalle.php?id=' . urlencode((string)$c['IdCliente'])) ?>">Ver cliente</a>
                                    <?php if (esAdmin()): ?>
                                        <a class="btn btn-small" href="<?= panelBaseUrl('clientes/editar.php?id=' . urlencode((string)$c['IdCliente'])) ?>">Editar</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center;color:var(--ink-3);padding:32px;">No hay clientes que coincidan con el filtro.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        </div>
    </div>
</main>

<script nonce="<?= cspNonce() ?>">
// Reset seguro al volver con el botón "Atrás" (bfcache): el anti-doble-submit
// deja el botón "Aplicar" en "Procesando..." y deshabilitado. Al restaurar la
// página desde caché lo devolvemos a su estado original.
window.addEventListener('pageshow', function (event) {
    if (!event.persisted) return;
    document.querySelectorAll('button[data-lteco-original-text], input[data-lteco-original-text]').forEach(function (button) {
        var original = button.dataset.ltecoOriginalText;
        if (button.tagName === 'INPUT') {
            button.value = original;
        } else {
            button.textContent = original;
        }
        button.disabled = false;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');
        button.removeAttribute('data-lteco-original-text');
    });
    document.querySelectorAll('form[data-lteco-submitting]').forEach(function (form) {
        form.removeAttribute('data-lteco-submitting');
    });
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
