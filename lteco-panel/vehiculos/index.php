<?php
$pageTitle = "Vehículos | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requiereModulo("vehiculos");
require_once __DIR__ . "/../includes/helpers.php";

$puedeGestionarCatalogoWeb = esSuperadmin();
$filtroEstado = trim($_GET['estado'] ?? '');
$filtroWeb = $puedeGestionarCatalogoWeb ? trim($_GET['web'] ?? '') : '';
$filtroDestacado = $puedeGestionarCatalogoWeb ? trim($_GET['destacado'] ?? '') : '';
$busqueda = trim($_GET['q'] ?? '');

$listado = new Lteco\Application\Vehiculo\VehiculoListadoService(
    new Lteco\Infrastructure\Repository\VehiculoListadoRepository(
        Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);

$vehiculos = $listado->listar([
    'estado'    => $filtroEstado,
    'web'       => $filtroWeb,
    'destacado' => $filtroDestacado,
    'q'         => $busqueda,
], $puedeGestionarCatalogoWeb);
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 15;

function badgeClaseEstado(string $estado): string
{
    return match ($estado) {
        'Disponible' => 'badge badge-disponible',
        'Reservado' => 'badge badge-reservado',
        'Vendido' => 'badge badge-vendido',
        'Oculto' => 'badge badge-oculto',
        'Sin stock' => 'badge badge-sin-stock',
        default => 'badge badge-default',
    };
}

function vehiculoIssues(array $vehiculo): array
{
    $analisis = vehiculoAnalizarPublicacion($vehiculo);
    $issues = [];

    if (!$analisis['publicable']) {
        foreach ($analisis['faltantes'] as $faltante) {
            $issues[] = 'Falta ' . ucfirst($faltante);
        }
    } elseif ((int)($vehiculo['MostrarEnWeb'] ?? 0) === 0) {
        $issues[] = 'Lista para publicar';
    }

    if ((int)($vehiculo['DestacadoWeb'] ?? 0) === 1 && !$analisis['visible_real']) {
        $issues[] = 'Destacado bloqueado';
    }

    return array_values(array_unique($issues));
}

$resumen = [
    'total' => count($vehiculos),
    'disponibles' => 0,
    'visibles' => 0,
    'destacados' => 0,
    'reservados' => 0,
    'atencion' => 0,
];

foreach ($vehiculos as $vehiculo) {
    $estado = (string)$vehiculo['Estado'];
    $analisis = vehiculoAnalizarPublicacion($vehiculo);
    $issues = vehiculoIssues($vehiculo);

    if ($estado === 'Disponible') {
        $resumen['disponibles']++;
    }
    if ($estado === 'Reservado') {
        $resumen['reservados']++;
    }
    if ($puedeGestionarCatalogoWeb) {
        if ($analisis['visible_real']) {
            $resumen['visibles']++;
        }
        if ((int)$vehiculo['DestacadoWeb'] === 1) {
            $resumen['destacados']++;
        }
        if ($issues) {
            $resumen['atencion']++;
        }
    }
}

$totalVehiculos = count($vehiculos);
$totalPaginas = max(1, (int)ceil($totalVehiculos / $porPagina));
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}
$vehiculosPagina = array_slice($vehiculos, ($pagina - 1) * $porPagina, $porPagina);

$estadoLinks = [
    '' => 'Todos',
    'Disponible' => 'Disponibles',
    'Reservado' => 'Reservados',
    'Oculto' => 'Ocultos',
    'Vendido' => 'Vendidos',
];

function vehiculosPaginaUrl(int $pagina): string
{
    $params = $_GET;
    $params['p'] = max(1, $pagina);
    return panelBaseUrl('vehiculos/index.php') . '?' . buildQuery($params);
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main main-v4 vehicles-v4">
    <section class="page-header-v4 hero-v4">
        <div>
            <span class="hero-kicker">⚡ Inventario eléctrico</span>
            <h1>Vehículos</h1>
            <p>Controlá stock, reservas y estado comercial desde una vista operativa, clara y profesional.</p>
        </div>
        <div class="hero-actions-v4">
            <?php if (esAdmin()): ?>
                <button class="btn-secondary" id="btnImprimirSeleccion" type="button" hidden>Imprimir QR x2 (<span id="contadorSeleccion">0</span>)</button>
                <a class="btn" href="<?= panelBaseUrl('vehiculos/crear.php') ?>">+ Nuevo vehículo</a>
            <?php endif; ?>
        </div>
    </section>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="stats-v4" aria-label="Resumen de vehículos">
        <article class="v4-card stat-v4">
            <span>Total en vista</span>
            <strong><?= $resumen['total'] ?></strong>
            <small>Resultados según filtros.</small>
        </article>
        <article class="v4-card stat-v4">
            <span>Disponibles</span>
            <strong><?= $resumen['disponibles'] ?></strong>
            <small>Listas para vender.</small>
        </article>
        <?php if ($puedeGestionarCatalogoWeb): ?>
            <article class="v4-card stat-v4">
                <span>Visibles en web</span>
                <strong><?= $resumen['visibles'] ?></strong>
                <small>Publicables y activas.</small>
            </article>
        <?php endif; ?>
        <article class="v4-card stat-v4">
            <span>Reservados</span>
            <strong><?= $resumen['reservados'] ?></strong>
            <small>Unidades señadas o apartadas.</small>
        </article>
        <?php if ($puedeGestionarCatalogoWeb): ?>
            <article class="v4-card stat-v4">
                <span>Destacados</span>
                <strong><?= $resumen['destacados'] ?></strong>
                <small>Prioridad comercial.</small>
            </article>
            <article class="v4-card stat-v4">
                <span>Revisar</span>
                <strong><?= $resumen['atencion'] ?></strong>
                <small>Faltantes o pendientes web.</small>
            </article>
        <?php endif; ?>
    </section>

    <?php if (esSuperadmin()): ?>
    <section class="v4-card filters-v4">
        <div class="quick-pills-v4">
            <?php foreach ($estadoLinks as $estadoValue => $estadoLabel): ?>
                <?php $activo = $filtroEstado === $estadoValue; ?>
                <a class="<?= $activo ? 'active' : '' ?>" href="<?= panelBaseUrl('vehiculos/index.php?' . buildQuery([
                    'estado' => $estadoValue,
                    'q' => $busqueda,
                ] + ($puedeGestionarCatalogoWeb ? ['web' => $filtroWeb, 'destacado' => $filtroDestacado] : []))) ?>">
                    <?= htmlspecialchars($estadoLabel) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="filters-grid-v4">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Modelo, motor, color, slug o ID">
            </div>

            <div class="form-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="Disponible" <?= $filtroEstado === 'Disponible' ? 'selected' : '' ?>>Disponible</option>
                    <option value="Reservado" <?= $filtroEstado === 'Reservado' ? 'selected' : '' ?>>Reservado</option>
                    <option value="Vendido" <?= $filtroEstado === 'Vendido' ? 'selected' : '' ?>>Vendido</option>
                    <option value="Oculto" <?= $filtroEstado === 'Oculto' ? 'selected' : '' ?>>Oculto</option>
                    <option value="Sin stock" <?= $filtroEstado === 'Sin stock' ? 'selected' : '' ?>>Sin stock</option>
                </select>
            </div>

            <?php if ($puedeGestionarCatalogoWeb): ?>
                <div class="form-group">
                    <label>Web</label>
                    <select name="web">
                        <option value="">Todos</option>
                        <option value="visible" <?= $filtroWeb === 'visible' ? 'selected' : '' ?>>Con flag visible</option>
                        <option value="oculto" <?= $filtroWeb === 'oculto' ? 'selected' : '' ?>>Ocultos</option>
                        <option value="publicable" <?= $filtroWeb === 'publicable' ? 'selected' : '' ?>>Publicables</option>
                        <option value="bloqueado" <?= $filtroWeb === 'bloqueado' ? 'selected' : '' ?>>Bloqueados</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Destacado</label>
                    <select name="destacado">
                        <option value="">Todos</option>
                        <option value="1" <?= $filtroDestacado === '1' ? 'selected' : '' ?>>Sí</option>
                        <option value="0" <?= $filtroDestacado === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filter-actions-v4">
                <button class="btn" type="submit">Aplicar</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Limpiar</a>
            </div>
        </form>

    </section>
    <?php endif; ?>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Listado operativo</h2>
                <p>Vista del inventario: estado comercial, reserva, precio y acciones.</p>
            </div>
            <div class="result-pill-v4"><?= count($vehiculosPagina) ?> de <?= $resumen['total'] ?> resultado<?= $resumen['total'] === 1 ? '' : 's' ?></div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="table vehicles-table-v4">
            <thead>
                <tr>
                    <?php if (esAdmin()): ?>
                        <th>
                            <input type="checkbox" id="qrSelectAll" aria-label="Seleccionar todos los vehículos visibles">
                        </th>
                    <?php endif; ?>
                    <th>ID</th>
                    <th>Vehículo</th>
                    <th>Motor</th>
                    <th>Color</th>
                    <th>Estado</th>
                    <?php if ($puedeGestionarCatalogoWeb): ?>
                        <th>Web</th>
                    <?php endif; ?>
                    <th>Stock</th>
                    <?php if ($puedeGestionarCatalogoWeb): ?>
                        <th>Orden</th>
                    <?php endif; ?>
                    <th>Precio</th>
                    <th>Reserva / revisión</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vehiculosPagina): ?>
                    <?php foreach ($vehiculosPagina as $vehiculo): ?>
                        <?php
                        $estado = (string)$vehiculo['Estado'];
                        $stock = (int)$vehiculo['Stock'];
                        $mostrarEnWeb = (int)$vehiculo['MostrarEnWeb'];
                        $destacado = (int)$vehiculo['DestacadoWeb'];
                        $analisis = vehiculoAnalizarPublicacion($vehiculo);
                        $esPublicable = $analisis['publicable'];
                        $puedeDestacarse = $analisis['puede_destacarse'];
                        $visibleReal = $analisis['visible_real'];
                        $issues = vehiculoIssues($vehiculo);
                        $precioUyu = formatearEnPesos((float)$vehiculo['PrecioVenta'], $vehiculo['Moneda'], (float)($vehiculo['TipoCambioImportacion'] ?: defaultTipoCambioUSD()));
                        ?>
                        <tr>
                            <?php if (esAdmin()): ?>
                                <td>
                                    <input type="checkbox" class="qr-multi-check" data-id="<?= h($vehiculo['IdVehiculo'], '') ?>" aria-label="Seleccionar <?= h($vehiculo['IdVehiculo'], '') ?>">
                                </td>
                            <?php endif; ?>
                            <td><?= h($vehiculo['IdVehiculo'], '') ?></td>
                            <td>
                                <strong><?= h($vehiculo['Modelo'], '') ?></strong><br>
                                <small class="text-muted"><?= trim((string)$vehiculo['Slug']) !== '' ? h($vehiculo['Slug'], '') : 'Sin slug' ?></small>
                            </td>
                            <td><?= h($vehiculo['NumeroMotor'], '-') ?></td>
                            <td><?= h($vehiculo['Color'], '-') ?></td>
                            <td><span class="<?= badgeClaseEstado($estado) ?>"><?= h($estado, '') ?></span></td>
                            <?php if ($puedeGestionarCatalogoWeb): ?>
                                <td>
                                    <?php if ($visibleReal): ?>
                                        <span class="sale-state sale-state--confirmada">Visible</span>
                                    <?php elseif ($mostrarEnWeb === 1): ?>
                                        <span class="sale-state sale-state--pendiente">Flag activo</span>
                                    <?php elseif ($esPublicable): ?>
                                        <span class="sale-state sale-state--entregada">Publicable</span>
                                    <?php else: ?>
                                        <span class="sale-state sale-state--anulada">No apta</span>
                                    <?php endif; ?>
                                    <?php if ($destacado === 1): ?>
                                        <br><small class="text-muted">Destacado</small>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td><?= $stock ?></td>
                            <?php if ($puedeGestionarCatalogoWeb): ?>
                                <td><?= (int)$vehiculo['OrdenWeb'] ?></td>
                            <?php endif; ?>
                            <td>
                                <?= $precioUyu ?>
                                <?php if ($vehiculo['Moneda'] === 'USD'): ?>
                                    <br><small class="text-muted">USD <?= number_format((float)$vehiculo['PrecioVenta'], 2, ',', '.') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vehiculo['ClienteReservaId']): ?>
                                    <strong>Reserva</strong><br>
                                    <small><?= h($vehiculo['ClienteReservaNombre'], '-') ?> · Seña <?= number_format((float)$vehiculo['SeniaReserva'], 2, ',', '.') ?></small>
                                <?php elseif ($puedeGestionarCatalogoWeb && $issues): ?>
                                    <?php foreach ($issues as $issue): ?>
                                        <span class="issue-v4 <?= $issue === 'Lista para publicar' ? 'success' : '' ?>"><?= h($issue, '') ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">Sin pendientes</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (esAdmin()): ?>
                                    <div class="actions-row vehicle-table-actions" style="gap:6px;">
                                        <a class="btn-secondary btn-small" href="<?= panelBaseUrl('vehiculos/editar.php?id=' . urlencode((string)$vehiculo['IdVehiculo'])) ?>">Editar</a>
                                        <a class="btn-secondary btn-small" target="_blank" rel="noopener" href="<?= panelBaseUrl('vehiculos/etiqueta_multi.php?ids=' . urlencode((string)$vehiculo['IdVehiculo'])) ?>">QR x2</a>

                                        <?php if ($estado === 'Disponible' && !$vehiculo['ClienteReservaId']): ?>
                                            <a class="btn-warning btn-small" href="<?= panelBaseUrl('vehiculos/reservar.php?id=' . urlencode((string)$vehiculo['IdVehiculo'])) ?>">Reservar</a>
                                        <?php endif; ?>

                                        <?php if ($puedeGestionarCatalogoWeb && ($mostrarEnWeb === 1 || $esPublicable)): ?>
                                            <button
                                                type="button"
                                                class="btn-small js-vehiculo-post"
                                                data-action="<?= h(panelBaseUrl('vehiculos/toggle_web.php'), '') ?>"
                                                data-id="<?= h($vehiculo['IdVehiculo'], '') ?>"
                                            ><?= $mostrarEnWeb === 1 ? 'Sacar web' : 'Publicar' ?></button>
                                        <?php endif; ?>

                                        <?php if ($puedeGestionarCatalogoWeb && ($destacado === 1 || $puedeDestacarse)): ?>
                                            <button
                                                type="button"
                                                class="btn-warning btn-small js-vehiculo-post"
                                                data-action="<?= h(panelBaseUrl('vehiculos/toggle_destacado.php'), '') ?>"
                                                data-id="<?= h($vehiculo['IdVehiculo'], '') ?>"
                                            ><?= $destacado === 1 ? 'Quitar destacado' : 'Destacar' ?></button>
                                        <?php endif; ?>

                                        <?php if ($puedeGestionarCatalogoWeb): ?>
                                            <button
                                                type="button"
                                                class="btn-secondary btn-small js-vehiculo-post"
                                                data-action="<?= h(panelBaseUrl('vehiculos/mover_orden.php'), '') ?>"
                                                data-id="<?= h($vehiculo['IdVehiculo'], '') ?>"
                                                data-extra-name="direccion"
                                                data-extra-value="up"
                                            >Subir</button>
                                            <button
                                                type="button"
                                                class="btn-secondary btn-small js-vehiculo-post"
                                                data-action="<?= h(panelBaseUrl('vehiculos/mover_orden.php'), '') ?>"
                                                data-id="<?= h($vehiculo['IdVehiculo'], '') ?>"
                                                data-extra-name="direccion"
                                                data-extra-value="down"
                                            >Bajar</button>
                                        <?php endif; ?>

                                        <?php if ($estado !== 'Disponible'): ?>
                                            <button
                                                type="button"
                                                class="btn-secondary btn-small js-vehiculo-post"
                                                data-action="<?= h(panelBaseUrl('vehiculos/cambiar_estado.php'), '') ?>"
                                                data-id="<?= h($vehiculo['IdVehiculo'], '') ?>"
                                                data-extra-name="estado"
                                                data-extra-value="Disponible"
                                            >Disponible</button>
                                        <?php endif; ?>
                                        <?php if ($estado !== 'Oculto'): ?>
                                            <button
                                                type="button"
                                                class="btn-danger btn-small js-vehiculo-post"
                                                data-action="<?= h(panelBaseUrl('vehiculos/cambiar_estado.php'), '') ?>"
                                                data-id="<?= h($vehiculo['IdVehiculo'], '') ?>"
                                                data-extra-name="estado"
                                                data-extra-value="Oculto"
                                            >Desactivar</button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="btn-disabled">Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= (esAdmin() ? 10 : 9) + ($puedeGestionarCatalogoWeb ? 2 : 0) ?>" style="text-align:center; color: var(--ink-3); padding: 32px;">No hay vehículos cargados para este filtro.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <div class="pager">
            <?php if ($pagina > 1): ?>
                <a class="btn-secondary" href="<?= h(vehiculosPaginaUrl($pagina - 1), '') ?>">Anterior</a>
            <?php endif; ?>
            <span class="pager__status">Página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?>
                <a class="btn-secondary" href="<?= h(vehiculosPaginaUrl($pagina + 1), '') ?>">Siguiente</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (esAdmin()): ?>
        <div class="qr-selection-bar" id="qrSelectionBar" hidden>
            <span><strong id="contadorSeleccionBar">0</strong> seleccionado<span id="contadorSeleccionPlural">s</span></span>
            <div class="qr-selection-bar__actions">
                <button type="button" class="btn-secondary btn-small" id="btnLimpiarSeleccionQr">Limpiar</button>
                <button type="button" class="btn btn-small" id="btnImprimirSeleccionBar">Imprimir QR x2</button>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php if (esAdmin()): ?>
<form method="POST" id="vehiculoActionForm" hidden>
    <?= csrfInput() ?>
    <input type="hidden" name="id" value="">
</form>
<?php endif; ?>

<script nonce="<?= cspNonce() ?>">
function enviarAccionVehiculo(button) {
    const form = document.getElementById('vehiculoActionForm');
    if (!form) return;

    form.action = button.dataset.action || '';
    form.querySelectorAll('input[data-dynamic-field="1"]').forEach(function(input) {
        input.remove();
    });

    const idInput = form.querySelector('input[name="id"]');
    if (idInput) idInput.value = button.dataset.id || '';

    if (button.dataset.extraName) {
        const extra = document.createElement('input');
        extra.type = 'hidden';
        extra.name = button.dataset.extraName;
        extra.value = button.dataset.extraValue || '';
        extra.dataset.dynamicField = '1';
        form.appendChild(extra);
    }

    form.submit();
}

function imprimirSeleccion() {
    const checks = document.querySelectorAll('.qr-multi-check:checked');
    if (checks.length === 0) {
        const mensaje = 'Seleccioná al menos un vehículo para imprimir QR.';
        if (typeof window.ltecoAlert === 'function') {
            window.ltecoAlert(mensaje, 'Atención');
        } else {
            alert(mensaje);
        }
        return;
    }
    const ids = Array.from(checks).map(c => c.dataset.id);
    const url = '<?= panelBaseUrl("vehiculos/etiqueta_multi.php") ?>?ids=' + encodeURIComponent(ids.join(','));
    window.open(url, '_blank');
}

function actualizarSeleccionQr() {
    const checks = document.querySelectorAll('.qr-multi-check');
    const seleccionados = document.querySelectorAll('.qr-multi-check:checked');
    const total = seleccionados.length;
    const btn = document.getElementById('btnImprimirSeleccion');
    const contador = document.getElementById('contadorSeleccion');
    const bar = document.getElementById('qrSelectionBar');
    const contadorBar = document.getElementById('contadorSeleccionBar');
    const plural = document.getElementById('contadorSeleccionPlural');
    const selectAll = document.getElementById('qrSelectAll');

    if (btn) btn.hidden = total === 0;
    if (contador) contador.textContent = total;
    if (bar) bar.hidden = total === 0;
    if (contadorBar) contadorBar.textContent = total;
    if (plural) plural.hidden = total === 1;

    if (selectAll) {
        selectAll.checked = checks.length > 0 && total === checks.length;
        selectAll.indeterminate = total > 0 && total < checks.length;
    }

    checks.forEach(function(check) {
        const row = check.closest('tr');
        if (row) row.classList.toggle('is-selected-for-qr', check.checked);
    });
}

function limpiarSeleccionQr() {
    document.querySelectorAll('.qr-multi-check').forEach(function(check) {
        check.checked = false;
    });
    actualizarSeleccionQr();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnImprimirSeleccion')?.addEventListener('click', imprimirSeleccion);
    document.getElementById('btnImprimirSeleccionBar')?.addEventListener('click', imprimirSeleccion);
    document.getElementById('btnLimpiarSeleccionQr')?.addEventListener('click', limpiarSeleccionQr);

    document.addEventListener('click', function(e) {
        const button = e.target.closest('.js-vehiculo-post');
        if (!button) return;
        enviarAccionVehiculo(button);
    });

    document.addEventListener('change', function(e) {
        if (e.target.id === 'qrSelectAll') {
            document.querySelectorAll('.qr-multi-check').forEach(function(check) {
                check.checked = e.target.checked;
            });
            actualizarSeleccionQr();
            return;
        }

        if (e.target.classList.contains('qr-multi-check')) {
            actualizarSeleccionQr();
        }
    });

    actualizarSeleccionQr();
});
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
