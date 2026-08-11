<?php
$pageTitle = "Buscar | Distribuidor | ERP";
require_once __DIR__ . "/_common.php";

$idDistribuidor = requiereDistribuidorPanel();

$consulta = new \Lteco\Application\Distribuidor\DistribuidorConsultaService(
    new \Lteco\Infrastructure\Repository\DistribuidorConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$resultados = $consulta->buscar($idDistribuidor, (string)($_GET['q'] ?? ''));
$q = $resultados['q'];
$stock = $resultados['stock'];
$ventas = $resultados['ventas'];
$clientes = $resultados['clientes'];
$totalResultados = $resultados['totalResultados'];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Buscar</h1>
            <p class="subtle">Buscá en tu stock, ventas y clientes.</p>
        </div>
    </div>

    <section class="search-hero">
        <form method="GET" class="search-hero__form" autocomplete="off">
            <span class="search-hero__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            </span>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                   class="search-hero__input"
                   placeholder="Buscá por nombre, motor, cliente o ID de venta…"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   <?= $q === '' ? 'autofocus' : '' ?>>
            <?php if ($q !== ''): ?>
                <a class="search-hero__clear" href="<?= panelBaseUrl('distribuidores/busqueda.php') ?>" aria-label="Limpiar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </a>
            <?php endif; ?>
            <button type="submit" class="btn search-hero__submit">Buscar</button>
        </form>
    </section>

    <?php if ($q === ''): ?>
        <div class="v4-card" style="text-align:center; padding:48px 24px;">
            <p class="muted">Escribí algo para buscar en tu stock, ventas y clientes.</p>
        </div>

    <?php elseif ($totalResultados === 0): ?>
        <div class="v4-card" style="text-align:center; padding:48px 24px;">
            <h3 style="margin-bottom:8px;">Sin resultados</h3>
            <p class="muted">No encontramos nada para "<?= htmlspecialchars($q) ?>". Probá con otro término.</p>
        </div>

    <?php else: ?>
        <div class="search-summary-bar">
            <strong><?= $totalResultados ?></strong> resultado<?= $totalResultados === 1 ? '' : 's' ?> para <em>"<?= htmlspecialchars($q) ?>"</em>
        </div>

        <?php if ($stock): ?>
        <section class="result-section">
            <h2 class="result-section__title">Stock <span class="result-section__count"><?= count($stock) ?></span></h2>
            <article class="v4-card">
                <ul class="recent-list">
                    <?php foreach ($stock as $item): ?>
                        <li>
                            <span class="recent-list__main">
                                <strong><?= htmlspecialchars((string)$item['NombreItem']) ?></strong>
                                <small>
                                    <?= $item['TipoItem'] === 'Vehiculo' ? 'Motor: ' . htmlspecialchars((string)($item['NumeroMotor'] ?? '—')) . ' · ' : '' ?>
                                    Cant. <?= (int)$item['Cantidad'] ?> ·
                                    <?= simboloMoneda('UYU') . number_format((float)$item['PrecioVenta'], 2, ',', '.') ?>
                                </small>
                            </span>
                            <span class="badge badge-success no-dot"><?= htmlspecialchars((string)$item['TipoItem']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </section>
        <?php endif; ?>

        <?php if ($clientes): ?>
        <section class="result-section">
            <h2 class="result-section__title">Clientes <span class="result-section__count"><?= count($clientes) ?></span></h2>
            <article class="v4-card">
                <ul class="recent-list">
                    <?php foreach ($clientes as $c): ?>
                        <li>
                            <span class="recent-list__main">
                                <strong><?= htmlspecialchars((string)$c['NombreApellido']) ?></strong>
                                <small>
                                    <?= htmlspecialchars((string)($c['Telefono'] ?: 'Sin teléfono')) ?>
                                    <?= $c['Correo'] ? ' · ' . htmlspecialchars((string)$c['Correo']) : '' ?>
                                </small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </section>
        <?php endif; ?>

        <?php if ($ventas): ?>
        <section class="result-section">
            <h2 class="result-section__title">Ventas <span class="result-section__count"><?= count($ventas) ?></span></h2>
            <article class="v4-card">
                <ul class="recent-list">
                    <?php foreach ($ventas as $v): ?>
                        <li>
                            <a href="<?= panelBaseUrl('distribuidores/ventas.php') ?>">
                                <span class="recent-list__main">
                                    <strong>Venta #<?= (int)$v['IdVenta'] ?></strong>
                                    <small>
                                        <?= htmlspecialchars((string)($v['NombreApellido'] ?: 'Sin cliente')) ?> ·
                                        <?= htmlspecialchars(substr((string)$v['FechaVenta'], 0, 10)) ?>
                                    </small>
                                </span>
                                <span class="mono recent-list__amount">
                                    <?= simboloMoneda((string)$v['Moneda']) . number_format((float)$v['Total'], 2, ',', '.') ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </section>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
