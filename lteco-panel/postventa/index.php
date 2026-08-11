<?php
$pageTitle = "Postventa | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereModulo("postventa");
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/actualizar_estados.php";

$qRaw = trim((string)($_GET['q'] ?? ''));
if ($qRaw !== '' && preg_match('/^[\s_\-—–]+$/u', $qRaw)) {
    $qRaw = '';
}

$filtros = [
    'q' => $qRaw,
    'estado_service' => trim((string)($_GET['estado_service'] ?? '')),
    'garantia' => trim((string)($_GET['garantia'] ?? '')),
];

$estadosServicePermitidos = Lteco\Application\Postventa\PostventaConsultaService::ESTADOS_SERVICE;
$estadosGarantiaPermitidos = Lteco\Application\Postventa\PostventaConsultaService::ESTADOS_GARANTIA;

$idUsuarioVendedor = esVendedor() ? (int)(usuarioActual()['IdUsuario'] ?? 0) : 0;

$consulta = new Lteco\Application\Postventa\PostventaConsultaService(
    new Lteco\Infrastructure\Repository\PostventaConsultaRepository(
        Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);

$datos = $consulta->listado($filtros, $idUsuarioVendedor);
$items = $datos['items'];
$recordatoriosWA = $datos['recordatoriosWA'];
$metricas = $datos['metricas'];

$hayFiltrosActivos = ($filtros['q'] !== '' || $filtros['estado_service'] !== '' || $filtros['garantia'] !== '');

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Postventa</h1>
            <p class="subtle">Seguimiento de motos vendidas, garantías y services programados.</p>
        </div>
    </div>

    <?php if ($recordatoriosWA): ?>
    <section class="v4-card" style="border-left: 3px solid var(--brand); margin-bottom: 1.5rem;">
        <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <strong>Recordatorios WhatsApp pendientes</strong>
            <span class="badge badge-warning no-dot"><?= count($recordatoriosWA) ?></span>
        </div>
        <p class="subtle" style="margin-bottom:1rem;">Services programados en los próximos 14 días sin notificar al cliente.</p>
        <ul class="recent-list">
            <?php foreach ($recordatoriosWA as $r):
                $telefono = preg_replace('/\D/', '', (string)($r['Telefono'] ?? ''));
                $nombre   = htmlspecialchars((string)($r['NombreApellido'] ?? 'Cliente'));
                $modelo   = htmlspecialchars((string)($r['Modelo'] ?? 'Moto'));
                $motor    = htmlspecialchars((string)($r['NumeroMotor'] ?? ''));
                $fecha    = htmlspecialchars(date('d/m/Y', strtotime((string)$r['FechaProgramada'])));
                $numSvc   = (int)$r['NumeroService'];
                $mensaje  = rawurlencode("Hola {$r['NombreApellido']}, te recordamos que el service #{$numSvc} de tu moto {$r['Modelo']} (motor: {$r['NumeroMotor']}) está programado para el {$fecha}. Por favor coordiná una visita. ¡Gracias! - ERP");
                $waUrl    = $telefono ? "https://wa.me/598{$telefono}?text={$mensaje}" : null;
            ?>
            <li style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                <span class="recent-list__main">
                    <strong><?= $nombre ?> — Service #<?= $numSvc ?></strong>
                    <small><?= $modelo ?> · Motor <?= $motor ?> · <?= $fecha ?></small>
                </span>
                <div style="display:flex; gap:.5rem; flex-shrink:0;">
                    <?php if ($waUrl): ?>
                    <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="btn btn-small postventa-wa-link"
                       data-id-service="<?= (int)$r['IdService'] ?>"
                       data-id-vehiculo="<?= htmlspecialchars((string)$r['IdVehiculo']) ?>">
                        WhatsApp
                    </a>
                    <?php else: ?>
                    <span class="badge badge-muted no-dot">Sin teléfono</span>
                    <?php endif; ?>
                    <form method="POST" action="<?= panelBaseUrl('postventa/marcar_notificado_wa.php') ?>" style="margin:0;">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id_service"  value="<?= (int)$r['IdService'] ?>">
                        <input type="hidden" name="id_vehiculo" value="<?= htmlspecialchars((string)$r['IdVehiculo']) ?>">
                        <button type="submit" class="btn-secondary btn-small">Marcar enviado</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <script nonce="<?= cspNonce() ?>">
	    function marcarNotificadoWA(idService, idVehiculo) {
	        // Enviar POST en background al hacer clic en WhatsApp
	        const fd = new FormData();
        fd.append('id_service', idService);
        fd.append('id_vehiculo', idVehiculo);
	        fd.append('_csrf', document.querySelector('input[name="_csrf"]') ? document.querySelector('input[name="_csrf"]').value : '');
	        fetch('<?= panelBaseUrl('postventa/marcar_notificado_wa.php') ?>', { method: 'POST', body: fd });
	    }

        document.querySelectorAll('.postventa-wa-link').forEach(function (link) {
            link.addEventListener('click', function () {
                marcarNotificadoWA(this.dataset.idService || '', this.dataset.idVehiculo || '');
            });
        });
	    </script>
    <?php endif; ?>

    <section class="stats-v4">
        <article class="v4-card stat-v4">
            <span>Services vencidos</span>
            <strong><?= h((string)$metricas['ServicesVencidos']) ?></strong>
        </article>
        <article class="v4-card stat-v4">
            <span>Próximos 7 días</span>
            <strong><?= h((string)$metricas['ProximosServices']) ?></strong>
        </article>
        <article class="v4-card stat-v4">
            <span>Garantías vigentes</span>
            <strong><?= h((string)$metricas['GarantiasVigentes']) ?></strong>
        </article>
        <article class="v4-card stat-v4">
            <span>Vehículos en seguimiento</span>
            <strong><?= h((string)$metricas['VehiculosSeguimiento']) ?></strong>
        </article>
    </section>

    <section class="v4-card filters-v4">
        <form method="get" action="" class="filters-grid-v4" autocomplete="off">
            <div class="form-group">
                <label for="q">Cliente / teléfono / motor</label>
                <input type="search" id="q" name="q"
                       value="<?= h($filtros['q'], '') ?>"
                       placeholder="Nombre, teléfono, motor, modelo o venta"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
            </div>
            <div class="form-group">
                <label for="estado_service">Estado service</label>
                <select id="estado_service" name="estado_service">
                    <option value="">Todos</option>
                    <?php foreach ($estadosServicePermitidos as $estado): ?>
                        <option value="<?= h($estado) ?>" <?= $filtros['estado_service'] === $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="garantia">Garantía</label>
                <select id="garantia" name="garantia">
                    <option value="">Todas</option>
                    <?php foreach ($estadosGarantiaPermitidos as $estado): ?>
                        <option value="<?= h($estado) ?>" <?= $filtros['garantia'] === $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions-v4">
                <button type="submit" class="btn">Filtrar</button>
                <?php if ($hayFiltrosActivos): ?>
                    <a class="btn-secondary" href="<?= h(panelBaseUrl('postventa/index.php')) ?>">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Motos en seguimiento</h2>
                <p>Garantía, próximo service y resumen de mantenimiento por moto.</p>
            </div>
            <div class="result-pill-v4"><?= count($items) ?> resultado<?= count($items) === 1 ? '' : 's' ?></div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Moto</th>
                    <th>Cliente</th>
                    <th>Venta</th>
                    <th>Garantía</th>
                    <th>Services</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--ink-3);padding:32px;">
                            <?= $hayFiltrosActivos ? 'No hay resultados para los filtros aplicados.' : 'No hay motos vendidas con postventa.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $proximoService = (string)($item['ProximoService'] ?? '');
                        $estadoProximoService = (string)($item['EstadoProximoService'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($item['Modelo'] ?? '-') ?></strong>
                                <div class="muted" style="font-size:12px;margin-top:2px;font-family:var(--font-mono);">
                                    <?= h($item['IdVehiculo'] ?? '-') ?> · <?= h($item['NumeroMotor'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                <?= h($item['NombreApellido'] ?? 'Sin cliente') ?>
                                <div class="muted" style="font-size:12px;margin-top:2px;">
                                    <?= h($item['Telefono'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                #<?= h((string)($item['IdVenta'] ?? '-')) ?>
                                <div class="muted" style="font-size:12px;margin-top:2px;font-family:var(--font-mono);">
                                    <?= h($item['FechaVenta'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                <?= h($item['EstadoGarantia'] ?? '-') ?>
                                <div class="muted" style="font-size:12px;margin-top:2px;">
                                    Vence: <?= h($item['VencimientoGarantia'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($proximoService !== ''): ?>
                                    <strong>Próximo:</strong> <?= h($proximoService) ?>
                                    <div class="muted" style="font-size:12px;margin-top:2px;">
                                        <?= h($estadoProximoService !== '' ? $estadoProximoService : 'Pendiente') ?>
                                    </div>
                                <?php else: ?>
                                    <strong>Próximo:</strong> <span class="muted">Sin pendientes</span>
                                <?php endif; ?>
                                <div class="muted" style="font-size:11px;margin-top:6px;">
                                    Pend: <?= h((string)($item['CantPendiente'] ?? 0)) ?> ·
                                    Venc: <?= h((string)($item['CantVencido'] ?? 0)) ?> ·
                                    Real: <?= h((string)($item['CantRealizado'] ?? 0)) ?> ·
                                    Canc: <?= h((string)($item['CantCancelado'] ?? 0)) ?>
                                </div>
                            </td>
                            <td>
                                <a class="btn-secondary btn-small" href="<?= h(panelBaseUrl('postventa/detalle.php?id=' . urlencode((string)$item['IdVehiculo']))) ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
