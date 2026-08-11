<?php
$pageTitle = "Repuestos | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';

requiereLogin();
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Repuesto\RepuestoConsultaService(
    new \Lteco\Infrastructure\Repository\RepuestoConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$esDistribuidor = esDistribuidor();
$usuarioActual = usuarioActual();
$distribuidorActual = null;

if ($esDistribuidor) {
    $distribuidorActual = $service->resolverDistribuidorActual(
        (int)($usuarioActual['IdUsuario'] ?? 0),
        (int)($usuarioActual['IdDistribuidor'] ?? 0)
    );
}

$filtroEstado = trim((string)($_GET['estado'] ?? ''));
$filtroBusqueda = trim((string)($_GET['q'] ?? ''));
$filtroImportacion = trim((string)($_GET['importacion'] ?? ''));

if ($esDistribuidor) {
    $filtroEstado = '';
    $filtroImportacion = '';
}

$repuestos = $service->listar([
    'estado' => $filtroEstado,
    'q' => $filtroBusqueda,
    'importacion' => $filtroImportacion,
    'es_distribuidor' => $esDistribuidor,
]);

$importaciones = $service->importaciones();

$conteos = $service->conteos($esDistribuidor);
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 25;
$totalRepuestos = count($repuestos);
$totalPaginas = max(1, (int)ceil($totalRepuestos / $porPagina));
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}
$repuestosPagina = $esDistribuidor
    ? $repuestos
    : array_slice($repuestos, ($pagina - 1) * $porPagina, $porPagina);

function badgeClaseEstadoRepuesto(string $estado): string
{
    return match ($estado) {
        'Disponible' => 'badge badge-disponible',
        'Reservado'  => 'badge badge-reservado',
        'Vendido'    => 'badge badge-vendido',
        'Oculto'     => 'badge badge-oculto',
        'Sin stock'  => 'badge badge-sin-stock',
        default      => 'badge badge-oculto',
    };
}

$pills = $esDistribuidor
    ? ['' => 'Disponibles']
    : [
        ''           => 'Todos',
        'Disponible' => 'Disponibles',
        'Sin stock'  => 'Sin stock',
        'Oculto'     => 'Ocultos',
    ];

function repuestosPaginaUrl(int $pagina): string
{
    $params = $_GET;
    $params['p'] = max(1, $pagina);
    return panelBaseUrl('repuestos/index.php') . '?' . buildQuery($params);
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1><?= $esDistribuidor ? 'Catálogo mayorista' : 'Repuestos' ?></h1>
            <p class="subtle">
                <?= $esDistribuidor
                    ? 'Repuestos disponibles con precio distribuidor. Armá el pedido y envialo a Ltecobike.'
                    : 'Catálogo de repuestos con stock, costos y precios de venta.' ?>
            </p>
            <?php if ($esDistribuidor && $distribuidorActual): ?>
                <p class="subtle">Distribuidor: <strong><?= h($distribuidorActual['Nombre'], '') ?></strong></p>
            <?php endif; ?>
        </div>
        <?php if (esAdmin()): ?>
            <div class="actions-row">
                <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/cajas/index.php') ?>">Cajas</a>
                <a class="btn" href="<?= panelBaseUrl('repuestos/crear.php') ?>">+ Nuevo repuesto</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (esSuperadmin()): ?>
    <section class="v4-card filters-v4">
        <div class="quick-pills-v4">
            <?php foreach ($pills as $val => $label): ?>
                <a class="<?= $filtroEstado === $val ? 'active' : '' ?>"
                   href="<?= panelBaseUrl('repuestos/index.php' . ($val !== '' ? '?estado=' . urlencode($val) : '')) ?>">
                    <?= htmlspecialchars($label) ?>
                    <span class="pill-count"><?= (int)($conteos[$val] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="filters-grid-v4">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filtroBusqueda) ?>" placeholder="Nombre o descripción">
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="Disponible" <?= $filtroEstado === 'Disponible' ? 'selected' : '' ?>>Disponible</option>
                    <option value="Sin stock"  <?= $filtroEstado === 'Sin stock'  ? 'selected' : '' ?>>Sin stock</option>
                    <option value="Oculto"     <?= $filtroEstado === 'Oculto'     ? 'selected' : '' ?>>Oculto</option>
                </select>
            </div>
            <div class="form-group">
                <label>Importaci&oacute;n</label>
                <select name="importacion">
                    <option value="">Todas</option>
                    <option value="sin" <?= $filtroImportacion === 'sin' ? 'selected' : '' ?>>Sin importaci&oacute;n</option>
                    <?php foreach ($importaciones as $imp): ?>
                        <option value="<?= (int)$imp['Numero'] ?>" <?= $filtroImportacion === (string)$imp['Numero'] ? 'selected' : '' ?>>
                            #<?= (int)$imp['Numero'] ?><?= !empty($imp['Descripcion']) ? ' — ' . htmlspecialchars(mb_strimwidth($imp['Descripcion'], 0, 40, '…')) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions-v4">
                <button class="btn" type="submit">Aplicar</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/index.php') ?>">Limpiar</a>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Listado de repuestos</h2>
                <p><?= $esDistribuidor ? 'Seleccioná cantidades y prepará tu pedido.' : 'Stock, costo, precio venta y precio distribuidor.' ?></p>
            </div>
            <div class="result-pill-v4">
                <?= $esDistribuidor ? count($repuestos) : count($repuestosPagina) ?> de <?= (int)$totalRepuestos ?> resultados
            </div>
        </div>
    </section>

    <?php if ($esDistribuidor): ?>
        <section class="distributor-catalog" data-distributor-name="<?= h($distribuidorActual['Nombre'] ?? ($usuarioActual['NombreCompleto'] ?? 'Distribuidor'), '') ?>">
            <?php if ($repuestos): ?>
                <?php foreach ($repuestos as $repuesto): ?>
                    <?php
                    $tipoCambioImportacion = (float)($repuesto['TipoCambioImportacion'] ?: defaultTipoCambioUSD());
                    $precioBase = $repuesto['PrecioDistribuidor'] !== null && (float)$repuesto['PrecioDistribuidor'] > 0
                        ? (float)$repuesto['PrecioDistribuidor']
                        : (float)$repuesto['PrecioVenta'];
                    $precioUyu = $repuesto['Moneda'] === 'USD' ? $precioBase * $tipoCambioImportacion : $precioBase;
                    ?>
                    <article class="distributor-product"
                        data-id="<?= h($repuesto['IdProducto'], '') ?>"
                        data-name="<?= h($repuesto['Nombre'], '') ?>"
                        data-price="<?= h((string)round($precioUyu, 2), '') ?>"
                        data-stock="<?= (int)$repuesto['Stock'] ?>">
                        <div>
                            <h3><?= h($repuesto['Nombre'], '') ?></h3>
                            <?php if (!empty($repuesto['Descripcion'])): ?>
                                <p><?= h(mb_strimwidth((string)$repuesto['Descripcion'], 0, 96, '…'), '') ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="distributor-product__meta">
                            <span>Stock <?= (int)$repuesto['Stock'] ?></span>
                            <strong><?= formatearMontoUYU($precioUyu) ?></strong>
                        </div>
                        <div class="distributor-product__actions">
                            <input type="number" min="0" max="<?= (int)$repuesto['Stock'] ?>" value="0" inputmode="numeric" aria-label="Cantidad <?= h($repuesto['Nombre'], '') ?>">
                            <button type="button" class="btn-secondary btn-small distributor-add">Agregar</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-v4">
                    <h2>No hay repuestos disponibles</h2>
                    <p>Probá cambiar la búsqueda o consultá con Ltecobike.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="distributor-order-panel" id="distributorOrderPanel" hidden>
            <div>
                <span>Pedido en preparación</span>
                <strong><span id="distributorOrderCount">0</span> ítems · <span id="distributorOrderTotal">$ 0,00</span></strong>
            </div>
            <div class="distributor-order-panel__actions">
                <button type="button" class="btn-secondary btn-small" id="distributorOrderClear">Limpiar</button>
                <button type="button" class="btn-secondary btn-small" id="distributorOrderCopy">Copiar pedido</button>
                <button type="button" class="btn btn-small" id="distributorOrderWhatsapp">Enviar WhatsApp</button>
            </div>
        </section>
    <?php else: ?>
        <div class="table-wrap">

        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Stock</th>
                    <th>Ubicación</th>
                    <?php if (esAdmin()): ?>
                        <th class="num">Costo</th>
                        <th class="num">Gasto total</th>
                    <?php endif; ?>
                    <th class="num">Precio venta</th>
                    <th class="num">Precio distribuidor</th>
                    <th>Moneda</th>
                    <th>Importaci&oacute;n</th>
                    <th>Estado</th>
                    <?php if (esAdmin()): ?>
                        <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($repuestosPagina): ?>
                    <?php foreach ($repuestosPagina as $repuesto): ?>
                        <?php $tipoCambioImportacion = (float)($repuesto['TipoCambioImportacion'] ?: defaultTipoCambioUSD()); ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($repuesto['Nombre']) ?></strong>
                                <?php if (!empty($repuesto['Descripcion'])): ?>
                                    <div class="muted" style="font-size:12px;margin-top:2px;">
                                        <?= htmlspecialchars(mb_strimwidth((string)$repuesto['Descripcion'], 0, 80, '…')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= htmlspecialchars((string)$repuesto['Stock']) ?></td>
                            <td>
                                <div><strong>Sin ubicar:</strong> <?= (int)($repuesto['StockSinUbicar'] ?? $repuesto['Stock']) ?></div>
                                <?php
                                $cajasResumen = array_filter(explode('|', (string)($repuesto['CajasResumen'] ?? '')));
                                ?>
                                <?php if ($cajasResumen): ?>
                                    <div class="muted" style="font-size:12px;margin-top:4px;">
                                        <?php foreach ($cajasResumen as $idx => $cajaResumen): ?>
                                            <?php [$codigoCaja, $nombreCaja, $cantidadCaja] = array_pad(explode(':', $cajaResumen, 3), 3, '0'); ?>
                                            <?= $idx > 0 ? ' · ' : '' ?><a href="<?= panelBaseUrl('repuestos/cajas/ver.php?c=' . urlencode($codigoCaja)) ?>"><?= h($codigoCaja, '') ?></a> <?= h($nombreCaja !== $codigoCaja ? '(' . $nombreCaja . ')' : '', '') ?>: <?= (int)$cantidadCaja ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="muted" style="font-size:12px;margin-top:4px;">Sin cajas</div>
                                <?php endif; ?>
                            </td>
                            <?php if (esAdmin()): ?>
                                <td class="num"><?= formatearEnPesos((float)$repuesto['Costo'], $repuesto['Moneda'], $tipoCambioImportacion) ?></td>
                                <td class="num"><?= formatearEnPesos((float)$repuesto['GastoTotal'], $repuesto['Moneda'], $tipoCambioImportacion) ?></td>
                            <?php endif; ?>
                            <td class="num"><?= formatearEnPesos((float)$repuesto['PrecioVenta'], $repuesto['Moneda'], $tipoCambioImportacion) ?></td>
                            <td class="num"><?= $repuesto['PrecioDistribuidor'] !== null ? formatearEnPesos((float)$repuesto['PrecioDistribuidor'], $repuesto['Moneda'], $tipoCambioImportacion) : '-' ?></td>
                            <td><?= htmlspecialchars($repuesto['Moneda']) ?></td>
                            <td>
                                <?php if ($repuesto['NumeroImportacion'] !== null): ?>
                                    <span class="badge badge-importacion">#<?= (int)$repuesto['NumeroImportacion'] ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= badgeClaseEstadoRepuesto((string)$repuesto['Estado']) ?>">
                                    <?= htmlspecialchars((string)$repuesto['Estado']) ?>
                                </span>
                            </td>
                            <?php if (esAdmin()): ?>
                                <td>
                                    <div class="actions-wrap">
                                        <a class="btn-secondary btn-small" href="<?= panelBaseUrl('repuestos/editar.php?id=' . urlencode((string)$repuesto['IdProducto'])) ?>">Editar</a>
                                        <button
                                            type="button"
                                            class="btn-danger btn-small js-repuesto-post"
                                            data-action="<?= h(panelBaseUrl('repuestos/eliminar.php'), '') ?>"
                                            data-id="<?= h($repuesto['IdRepuesto'], '') ?>"
                                            data-confirm="¿Seguro que querés ocultar este repuesto?"
                                        >Ocultar</button>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= esAdmin() ? 11 : 8 ?>" style="text-align:center;color:var(--ink-3);padding:32px;">
                            No hay repuestos que coincidan con el filtro.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        </div>
        <?php if (!$esDistribuidor && $totalPaginas > 1): ?>
            <div class="pager">
                <?php if ($pagina > 1): ?>
                    <a class="btn-secondary" href="<?= h(repuestosPaginaUrl($pagina - 1), '') ?>">Anterior</a>
                <?php endif; ?>
                <span class="pager__status">Página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?></span>
                <?php if ($pagina < $totalPaginas): ?>
                    <a class="btn-secondary" href="<?= h(repuestosPaginaUrl($pagina + 1), '') ?>">Siguiente</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php if (esAdmin()): ?>
<form method="POST" id="repuestoActionForm" class="u-m-0" hidden>
    <?= csrfInput() ?>
    <input type="hidden" name="id" value="">
</form>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-repuesto-post');
    if (!button) return;

    const message = button.dataset.confirm || '';
    if (message && !window.confirm(message)) return;

    const form = document.getElementById('repuestoActionForm');
    if (!form) return;

    form.action = button.dataset.action || '';
    const idInput = form.querySelector('input[name="id"]');
    if (idInput) idInput.value = button.dataset.id || '';
    form.submit();
});
</script>
<?php endif; ?>

<?php if ($esDistribuidor): ?>
<script nonce="<?= cspNonce() ?>">
(function () {
    const catalog = document.querySelector('.distributor-catalog');
    const panel = document.getElementById('distributorOrderPanel');
    if (!catalog || !panel) return;

    const fmt = new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU' });
    const items = new Map();
    const distributorName = catalog.dataset.distributorName || 'Distribuidor';
    const countEl = document.getElementById('distributorOrderCount');
    const totalEl = document.getElementById('distributorOrderTotal');

    function buildText() {
        let total = 0;
        const lines = ['Pedido distribuidor - ' + distributorName, ''];
        items.forEach(function (item) {
            const subtotal = item.price * item.qty;
            total += subtotal;
            lines.push('- ' + item.name + ' x' + item.qty + ' = ' + fmt.format(subtotal));
        });
        lines.push('', 'Total estimado: ' + fmt.format(total));
        return lines.join('\n');
    }

    function syncPanel() {
        let qty = 0;
        let total = 0;
        items.forEach(function (item) {
            qty += item.qty;
            total += item.qty * item.price;
        });
        panel.hidden = qty === 0;
        countEl.textContent = String(qty);
        totalEl.textContent = fmt.format(total);
    }

    catalog.addEventListener('click', function (event) {
        const button = event.target.closest('.distributor-add');
        if (!button) return;

        const card = button.closest('.distributor-product');
        const input = card.querySelector('input[type="number"]');
        const qty = Math.max(0, Math.min(parseInt(input.value || '0', 10) || 0, parseInt(card.dataset.stock || '0', 10)));

        if (qty <= 0) {
            items.delete(card.dataset.id);
            card.classList.remove('is-in-order');
        } else {
            items.set(card.dataset.id, {
                name: card.dataset.name || 'Repuesto',
                price: parseFloat(card.dataset.price || '0') || 0,
                qty: qty
            });
            card.classList.add('is-in-order');
        }

        syncPanel();
    });

    document.getElementById('distributorOrderClear')?.addEventListener('click', function () {
        items.clear();
        catalog.querySelectorAll('input[type="number"]').forEach(function (input) { input.value = '0'; });
        catalog.querySelectorAll('.is-in-order').forEach(function (card) { card.classList.remove('is-in-order'); });
        syncPanel();
    });

    document.getElementById('distributorOrderCopy')?.addEventListener('click', function () {
        const text = buildText();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { alert('Pedido copiado.'); });
        } else {
            window.prompt('Copiá el pedido:', text);
        }
    });

    document.getElementById('distributorOrderWhatsapp')?.addEventListener('click', function () {
        window.open('https://wa.me/?text=' + encodeURIComponent(buildText()), '_blank');
    });
})();
</script>
<?php endif; ?>

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
