<?php
$pageTitle = "Buscador global | Lteco";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereNoDistribuidor();
require_once __DIR__ . "/../includes/helpers.php";

$q = trim((string)($_GET['q'] ?? ''));
$esVendedorActual = esVendedor();
$idUsuarioVendedor = $esVendedorActual ? (int)(usuarioActual()['IdUsuario'] ?? 0) : 0;

$busqueda = (new \Lteco\Application\Busqueda\BusquedaService(
    new \Lteco\Infrastructure\Repository\BusquedaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
))->consultar($q, $idUsuarioVendedor, !esSuperadmin());

$resultados = $busqueda['resultados'];
$recientes = $busqueda['recientes'];
$clientesConDetalle = $busqueda['clientesConDetalle'];
$clienteAjenoCoincide = $busqueda['clienteAjenoCoincide'];

function totalResultadosBusqueda(array $r): int
{
    return count($r['vehiculos']) + count($r['clientes']) + count($r['ventas']) + count($r['repuestos']);
}

function monedaBusqueda($moneda, $monto): string
{
    return simboloMoneda((string)$moneda) . number_format((float)$monto, 2, ',', '.');
}

function badgeEstado(string $estado): string
{
    $map = [
        'Disponible' => 'badge-success',
        'Reservado'  => 'badge-warning',
        'Vendido'    => 'badge-danger',
        'Oculto'     => 'badge-muted',
        'Sin stock'  => 'badge-muted',
        'Confirmada' => 'badge-success',
        'Pendiente'  => 'badge-warning',
        'Anulada'    => 'badge-danger',
    ];
    $cls = $map[$estado] ?? 'badge-muted';
    return '<span class="badge ' . $cls . ' no-dot">' . htmlspecialchars($estado) . '</span>';
}

// Sugerencias rápidas (chips)
$sugerencias = ['Daniel', 'MOTOR', 'F-2026', 'Disponible', 'Visa', '099'];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Buscar</h1>
            <p class="subtle">Buscá por nombre, motor, factura, ID o teléfono.</p>
        </div>
    </div>

    <section class="search-hero">
        <form method="GET" class="search-hero__form" autocomplete="off">
            <span class="search-hero__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            </span>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                   class="search-hero__input"
                   placeholder="Nombre, motor, factura, ID o teléfono…"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   <?= $q === '' ? 'autofocus' : '' ?>>
            <?php if ($q !== ''): ?>
                <a class="search-hero__clear" href="<?= panelBaseUrl('busqueda/index.php') ?>" aria-label="Limpiar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </a>
            <?php endif; ?>
            <button type="submit" class="btn search-hero__submit">Buscar</button>
        </form>
        <?php if (esSuperadmin()): ?>
        <div class="search-hero__hints">
            <span class="search-hero__hint-label">Probá con:</span>
            <?php foreach ($sugerencias as $s): ?>
                <a class="search-hero__chip" href="<?= panelBaseUrl('busqueda/index.php?q=' . urlencode($s)) ?>"><?= htmlspecialchars($s) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if (!esSuperadmin()): ?>
    <!-- ============ VISTA SIMPLIFICADA: fichas unificadas ============ -->
    <?php if ($q === ''): ?>
        <div class="v4-card" style="text-align:center; padding:48px 24px;">
            <p class="muted">Escribí un nombre, motor, factura, ID o teléfono para buscar.</p>
        </div>
    <?php elseif (empty($clientesConDetalle) && empty($resultados['vehiculos']) && empty($resultados['ventas']) && empty($resultados['repuestos']) && !$clienteAjenoCoincide): ?>
        <div class="v4-card" style="text-align:center; padding:48px 24px;">
            <h3 style="margin-bottom:8px;">Sin resultados</h3>
            <p class="muted">No encontramos nada para "<?= htmlspecialchars($q) ?>". Probá con otro término.</p>
        </div>
    <?php else: ?>
        <?php if ($clienteAjenoCoincide): ?>
        <article class="v4-card" style="margin-bottom:1rem;">
            <div class="eyebrow" style="margin-bottom:.4rem;">Cliente registrado</div>
            <p class="muted" style="margin:0;">Existe un cliente registrado. Solicitá a administración si necesitás asociarlo.</p>
        </article>
        <?php endif; ?>

        <?php foreach ($clientesConDetalle as $detalle):
            $c = $detalle['cliente']; ?>
        <article class="v4-card" style="margin-bottom:1rem;">
            <div style="display:grid; gap:1rem;">

                <div>
                    <div class="eyebrow" style="margin-bottom:.4rem;">Cliente</div>
                    <strong><?= htmlspecialchars((string)$c['NombreApellido']) ?></strong><br>
                    <?php if (!empty($c['Telefono'])): ?><span class="muted"><?= htmlspecialchars((string)$c['Telefono']) ?></span><br><?php endif; ?>
                    <?php if (!empty($c['Correo'])): ?><span class="muted"><?= htmlspecialchars((string)$c['Correo']) ?></span><?php endif; ?>
                </div>

                <?php foreach ($detalle['motos'] as $moto): ?>
                <div style="border-top:1px solid var(--line); padding-top:.8rem;">
                    <div class="eyebrow" style="margin-bottom:.4rem;">Vehículo</div>
                    <div style="display:flex; flex-wrap:wrap; gap:.5rem 1.5rem;">
                        <span><strong>ID:</strong> <?= htmlspecialchars((string)$moto['IdVehiculo']) ?></span>
                        <span><strong>Motor:</strong> <span class="mono"><?= htmlspecialchars((string)$moto['NumeroMotor']) ?></span></span>
                        <?php if (!empty($moto['FechaVenta'])): ?>
                        <span><strong>Venta:</strong> <?= htmlspecialchars(substr((string)$moto['FechaVenta'], 0, 10)) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php
                        $realizados = (int)($moto['svc_realizados'] ?? 0);
                        $proximo    = $moto['svc_proximo'] ?? null;
                    ?>
                    <div style="margin-top:.5rem; display:flex; flex-wrap:wrap; gap:.5rem 1.5rem;">
                        <span><strong>Services realizados:</strong> <?= $realizados ?></span>
                        <span><strong>Próximo service:</strong>
                            <?php if ($proximo): ?>
                                <?= htmlspecialchars(date('d/m/Y', strtotime($proximo))) ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($detalle['ventas']): ?>
                <div style="border-top:1px solid var(--line); padding-top:.8rem;">
                    <div class="eyebrow" style="margin-bottom:.4rem;">Compras</div>
                    <?php foreach ($detalle['ventas'] as $vv): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:.3rem 1.5rem; margin-bottom:.3rem;">
                        <?php if (!empty($vv['NumeroFactura'])): ?>
                        <span><strong>Factura:</strong> <?= htmlspecialchars((string)$vv['NumeroFactura']) ?></span>
                        <?php endif; ?>
                        <span><strong>Total:</strong> <?= monedaBusqueda($vv['Moneda'], $vv['Total']) ?></span>
                        <span><strong>Fecha:</strong> <?= htmlspecialchars(substr((string)$vv['FechaVenta'], 0, 10)) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </article>
        <?php endforeach; ?>

        <?php foreach ($resultados['vehiculos'] as $v):
            // Mostrar vehículos que no aparecieron via cliente
            $yaEnFichas = false;
            foreach ($clientesConDetalle as $det) {
                foreach ($det['motos'] as $m) {
                    if ($m['IdVehiculo'] === $v['IdVehiculo']) { $yaEnFichas = true; break 2; }
                }
            }
            if ($yaEnFichas) continue;
        ?>
        <article class="v4-card" style="margin-bottom:1rem;">
            <div class="eyebrow" style="margin-bottom:.4rem;">Vehículo</div>
            <div style="display:flex; flex-wrap:wrap; gap:.5rem 1.5rem;">
                <span><strong>ID:</strong> <?= htmlspecialchars((string)$v['IdVehiculo']) ?></span>
                <span><strong>Motor:</strong> <span class="mono"><?= htmlspecialchars((string)$v['NumeroMotor']) ?></span></span>
                <span><strong>Modelo:</strong> <?= htmlspecialchars((string)$v['Modelo']) ?></span>
                <?= badgeEstado((string)$v['Estado']) ?>
            </div>
        </article>
        <?php endforeach; ?>

        <?php foreach ($resultados['ventas'] as $v): ?>
        <article class="v4-card" style="margin-bottom:1rem;">
            <div class="eyebrow" style="margin-bottom:.4rem;">Venta</div>
            <div style="display:flex; flex-wrap:wrap; gap:.5rem 1.5rem;">
                <span><strong><?= htmlspecialchars((string)($v['NumeroFactura'] ?: 'Venta #' . $v['IdVenta'])) ?></strong></span>
                <span><?= htmlspecialchars((string)($v['NombreApellido'] ?: 'Sin cliente')) ?></span>
                <span><?= htmlspecialchars(substr((string)$v['FechaVenta'], 0, 10)) ?></span>
                <span class="mono"><?= monedaBusqueda($v['Moneda'], $v['Total']) ?></span>
            </div>
            <div style="margin-top:.7rem;">
                <a class="btn-secondary btn-small" href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$v['IdVenta'])) ?>">Ver venta</a>
            </div>
        </article>
        <?php endforeach; ?>

        <?php foreach ($resultados['repuestos'] as $r): ?>
        <article class="v4-card" style="margin-bottom:1rem;">
            <div class="eyebrow" style="margin-bottom:.4rem;">Repuesto</div>
            <div style="display:flex; flex-wrap:wrap; gap:.5rem 1.5rem;">
                <span><strong><?= htmlspecialchars((string)$r['Nombre']) ?></strong></span>
                <span>Stock <?= htmlspecialchars((string)$r['Stock']) ?></span>
                <span class="mono"><?= monedaBusqueda($r['Moneda'], $r['PrecioVenta']) ?></span>
                <?= badgeEstado((string)$r['Estado']) ?>
            </div>
            <div style="margin-top:.7rem;">
                <a class="btn-secondary btn-small" href="<?= panelBaseUrl('repuestos/index.php?q=' . urlencode((string)$r['Nombre'])) ?>">Ver repuesto</a>
            </div>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php else: /* esSuperadmin — layout original */ ?>

    <?php if ($q === ''): ?>
        <!-- ============ EMPTY STATE: categorías + accesos recientes ============ -->

        <section class="search-categories">
            <a class="search-category" href="<?= panelBaseUrl('vehiculos/index.php') ?>">
                <span class="search-category__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>
                </span>
                <strong>Vehículos</strong>
                <span class="search-category__hint">Por motor, modelo, color, ID</span>
            </a>
            <a class="search-category" href="<?= panelBaseUrl('clientes/index.php') ?>">
                <span class="search-category__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <strong>Clientes</strong>
                <span class="search-category__hint">Por nombre, teléfono, correo</span>
            </a>
            <a class="search-category" href="<?= panelBaseUrl('ventas/index.php') ?>">
                <span class="search-category__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
                </span>
                <strong>Ventas</strong>
                <span class="search-category__hint">Por ID de venta o factura</span>
            </a>
            <a class="search-category" href="<?= panelBaseUrl('repuestos/index.php') ?>">
                <span class="search-category__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </span>
                <strong>Repuestos</strong>
                <span class="search-category__hint">Por nombre o código</span>
            </a>
        </section>

        <section class="recent-grid">
            <article class="v4-card recent-card">
                <header class="recent-card__head">
                    <strong>Últimos vehículos</strong>
                    <a class="recent-card__more" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Ver todos →</a>
                </header>
                <ul class="recent-list">
                    <?php foreach ($recientes['vehiculos'] as $v): ?>
                        <li>
                            <a href="<?= panelBaseUrl('vehiculos/editar.php?id=' . urlencode((string)$v['IdVehiculo'])) ?>">
                                <span class="recent-list__main">
                                    <strong><?= htmlspecialchars((string)$v['Modelo']) ?></strong>
                                    <small><span class="mono"><?= htmlspecialchars((string)$v['NumeroMotor']) ?></span> · <?= htmlspecialchars((string)$v['Color']) ?></small>
                                </span>
                                <?= badgeEstado((string)$v['Estado']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recientes['vehiculos']): ?>
                        <li class="recent-list__empty">Sin vehículos cargados.</li>
                    <?php endif; ?>
                </ul>
            </article>

            <article class="v4-card recent-card">
                <header class="recent-card__head">
                    <strong>Últimos clientes</strong>
                    <a class="recent-card__more" href="<?= panelBaseUrl('clientes/index.php') ?>">Ver todos →</a>
                </header>
                <ul class="recent-list">
                    <?php foreach ($recientes['clientes'] as $c): ?>
                        <li>
                            <a href="<?= panelBaseUrl('clientes/detalle.php?id=' . urlencode((string)$c['IdCliente'])) ?>">
                                <span class="recent-list__main">
                                    <strong><?= htmlspecialchars((string)$c['NombreApellido']) ?></strong>
                                    <small><?= htmlspecialchars((string)($c['Telefono'] ?: '—')) ?></small>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recientes['clientes']): ?>
                        <li class="recent-list__empty">Sin clientes cargados.</li>
                    <?php endif; ?>
                </ul>
            </article>

            <article class="v4-card recent-card">
                <header class="recent-card__head">
                    <strong>Últimas ventas</strong>
                    <a class="recent-card__more" href="<?= panelBaseUrl('ventas/index.php') ?>">Ver todas →</a>
                </header>
                <ul class="recent-list">
                    <?php foreach ($recientes['ventas'] as $v): ?>
                        <li>
                            <a href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$v['IdVenta'])) ?>">
                                <span class="recent-list__main">
                                    <strong><?= htmlspecialchars((string)($v['NumeroFactura'] ?: 'Venta #' . $v['IdVenta'])) ?></strong>
                                    <small><?= htmlspecialchars((string)($v['NombreApellido'] ?: 'Sin cliente')) ?> · <?= htmlspecialchars(substr((string)$v['FechaVenta'], 0, 10)) ?></small>
                                </span>
                                <span class="mono recent-list__amount"><?= monedaBusqueda($v['Moneda'], $v['Total']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recientes['ventas']): ?>
                        <li class="recent-list__empty">Sin ventas registradas.</li>
                    <?php endif; ?>
                </ul>
            </article>

            <article class="v4-card recent-card">
                <header class="recent-card__head">
                    <strong>Repuestos recientes</strong>
                    <a class="recent-card__more" href="<?= panelBaseUrl('repuestos/index.php') ?>">Ver todos →</a>
                </header>
                <ul class="recent-list">
                    <?php foreach ($recientes['repuestos'] as $r): ?>
                        <li>
                            <a href="<?= panelBaseUrl('repuestos/index.php?q=' . urlencode((string)$r['Nombre'])) ?>">
                                <span class="recent-list__main">
                                    <strong><?= htmlspecialchars((string)$r['Nombre']) ?></strong>
                                    <small>Stock <?= htmlspecialchars((string)$r['Stock']) ?></small>
                                </span>
                                <?= badgeEstado((string)$r['Estado']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recientes['repuestos']): ?>
                        <li class="recent-list__empty">Sin repuestos cargados.</li>
                    <?php endif; ?>
                </ul>
            </article>
        </section>

    <?php else: ?>
        <!-- ============ SEARCH RESULTS ============ -->

        <?php $total = totalResultadosBusqueda($resultados); ?>

        <div class="search-summary-bar">
            <strong><?= $total ?></strong> resultado<?= $total === 1 ? '' : 's' ?> para <em>"<?= htmlspecialchars($q) ?>"</em>
            <?php if ($total > 0): ?>
                <span class="search-summary-bar__counts">
                    <?php if ($resultados['clientes']): ?><span><?= count($resultados['clientes']) ?> clientes</span><?php endif; ?>
                    <?php if ($resultados['vehiculos']): ?><span><?= count($resultados['vehiculos']) ?> vehículos</span><?php endif; ?>
                    <?php if ($resultados['ventas']): ?><span><?= count($resultados['ventas']) ?> ventas</span><?php endif; ?>
                    <?php if ($resultados['repuestos']): ?><span><?= count($resultados['repuestos']) ?> repuestos</span><?php endif; ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($total === 0): ?>
            <section class="v4-card" style="text-align:center; padding: 48px 24px;">
                <h3 style="margin-bottom:8px;">Sin resultados</h3>
                <p class="muted">No encontramos nada para "<?= htmlspecialchars($q) ?>". Probá con otro término o revisá la ortografía.</p>
            </section>
        <?php else: ?>

        <?php if ($clientesConDetalle): ?>
            <section class="result-section">
                <h2 class="result-section__title">Clientes <span class="result-section__count"><?= count($clientesConDetalle) ?></span></h2>
                <?php foreach ($clientesConDetalle as $detalle): $c = $detalle['cliente']; ?>
                    <article class="v4-card result-client">
                        <header class="result-client__head">
                            <div>
                                <a href="<?= panelBaseUrl('clientes/detalle.php?id=' . urlencode((string)$c['IdCliente'])) ?>" class="result-client__name"><?= htmlspecialchars((string)$c['NombreApellido']) ?></a>
                                <p class="result-client__contact">
                                    <?= htmlspecialchars((string)($c['Telefono'] ?: 'Sin teléfono')) ?> · <?= htmlspecialchars((string)($c['Correo'] ?: 'Sin correo')) ?>
                                </p>
                            </div>
                            <a class="btn-secondary btn-small" href="<?= panelBaseUrl('clientes/detalle.php?id=' . urlencode((string)$c['IdCliente'])) ?>">Ficha cliente →</a>
                        </header>

                        <?php if ($detalle['motos'] || $detalle['ventas']): ?>
                            <div class="result-client__split">
                                <?php if ($detalle['motos']): ?>
                                    <div class="result-client__col">
                                        <div class="eyebrow">Motos asociadas</div>
                                        <ul class="recent-list recent-list--compact">
                                            <?php foreach ($detalle['motos'] as $m): ?>
                                                <li>
                                                    <a href="<?= panelBaseUrl('vehiculos/editar.php?id=' . urlencode((string)$m['IdVehiculo'])) ?>">
                                                        <span class="recent-list__main">
                                                            <strong><?= htmlspecialchars((string)$m['Modelo']) ?></strong>
                                                            <small><span class="mono"><?= htmlspecialchars((string)$m['NumeroMotor']) ?></span> · <?= htmlspecialchars((string)$m['Color']) ?></small>
                                                        </span>
                                                        <?= badgeEstado((string)$m['Estado']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if ($detalle['ventas']): ?>
                                    <div class="result-client__col">
                                        <div class="eyebrow">Ventas</div>
                                        <ul class="recent-list recent-list--compact">
                                            <?php foreach ($detalle['ventas'] as $vv): ?>
                                                <li>
                                                    <a href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$vv['IdVenta'])) ?>">
                                                        <span class="recent-list__main">
                                                            <strong><?= htmlspecialchars((string)($vv['NumeroFactura'] ?: 'Venta #' . $vv['IdVenta'])) ?></strong>
                                                            <small><?= htmlspecialchars((string)$vv['MetodoPago']) ?> · <?= htmlspecialchars(substr((string)$vv['FechaVenta'], 0, 10)) ?></small>
                                                        </span>
                                                        <span class="mono recent-list__amount"><?= monedaBusqueda($vv['Moneda'], $vv['Total']) ?></span>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="result-double">
            <?php if ($resultados['vehiculos']): ?>
                <div class="result-section">
                    <h2 class="result-section__title">Vehículos <span class="result-section__count"><?= count($resultados['vehiculos']) ?></span></h2>
                    <article class="v4-card">
                        <ul class="recent-list">
                            <?php foreach ($resultados['vehiculos'] as $v): ?>
                                <li>
                                    <a href="<?= esAdmin() ? panelBaseUrl('vehiculos/editar.php?id=' . urlencode((string)$v['IdVehiculo'])) : panelBaseUrl('vehiculos/qr.php?id=' . urlencode((string)$v['IdVehiculo'])) ?>">
                                        <span class="recent-list__main">
                                            <strong><?= htmlspecialchars((string)$v['Modelo']) ?></strong>
                                            <small><span class="mono"><?= htmlspecialchars((string)$v['NumeroMotor']) ?></span> · <?= htmlspecialchars((string)$v['Color']) ?> · <?= monedaBusqueda($v['Moneda'], $v['PrecioVenta']) ?></small>
                                        </span>
                                        <?= badgeEstado((string)$v['Estado']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>
            <?php endif; ?>

            <?php if ($resultados['ventas']): ?>
                <div class="result-section">
                    <h2 class="result-section__title">Ventas / Comprobantes <span class="result-section__count"><?= count($resultados['ventas']) ?></span></h2>
                    <article class="v4-card">
                        <ul class="recent-list">
                            <?php foreach ($resultados['ventas'] as $v): ?>
                                <li>
                                    <a href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$v['IdVenta'])) ?>">
                                        <span class="recent-list__main">
                                            <strong><?= htmlspecialchars((string)($v['NumeroFactura'] ?: 'Venta #' . $v['IdVenta'])) ?></strong>
                                            <small><?= htmlspecialchars((string)($v['NombreApellido'] ?: 'Sin cliente')) ?> · <?= htmlspecialchars(substr((string)$v['FechaVenta'], 0, 10)) ?></small>
                                        </span>
                                        <span class="mono recent-list__amount"><?= monedaBusqueda($v['Moneda'], $v['Total']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>
            <?php endif; ?>

            <?php if ($resultados['repuestos']): ?>
                <div class="result-section">
                    <h2 class="result-section__title">Repuestos <span class="result-section__count"><?= count($resultados['repuestos']) ?></span></h2>
                    <article class="v4-card">
                        <ul class="recent-list">
                            <?php foreach ($resultados['repuestos'] as $r): ?>
                                <li>
                                    <a href="<?= panelBaseUrl('repuestos/index.php?q=' . urlencode((string)$r['Nombre'])) ?>">
                                        <span class="recent-list__main">
                                            <strong><?= htmlspecialchars((string)$r['Nombre']) ?></strong>
                                            <small>Stock <?= htmlspecialchars((string)$r['Stock']) ?> · <?= monedaBusqueda($r['Moneda'], $r['PrecioVenta']) ?></small>
                                        </span>
                                        <?= badgeEstado((string)$r['Estado']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>
            <?php endif; ?>
        </section>

        <?php endif; ?>
    <?php endif; ?>

    <?php endif; /* fin esSuperadmin/else */ ?>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
