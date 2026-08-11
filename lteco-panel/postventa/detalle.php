<?php
$pageTitle = "Detalle postventa | Lteco";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/actualizar_estados.php";

requiereModulo("postventa");

$idVehiculo = trim((string)($_GET['id'] ?? ''));
$idUsuarioVendedor = esVendedor() ? (int)(usuarioActual()['IdUsuario'] ?? 0) : 0;

if ($idVehiculo === '') {
    redirectWithFlash(panelBaseUrl('postventa/index.php'), 'error', 'Vehículo no recibido.');
}
requierePuedeVerRegistro('postventa', $idVehiculo);

$consulta = new Lteco\Application\Postventa\PostventaConsultaService(
    new Lteco\Infrastructure\Repository\PostventaConsultaRepository(
        Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);

$datos = $consulta->detalle($idVehiculo, $idUsuarioVendedor);
if ($datos === null) {
    redirectWithFlash(panelBaseUrl('postventa/index.php'), 'error', 'Vehículo no encontrado.');
}

$vehiculo = $datos['vehiculo'];
$services = $datos['services'];
$historialPorService = $datos['historialPorService'];
$historialTecnico = $datos['historialTecnico'];
$repuestosUsadosPorHistorial = $datos['repuestosUsadosPorHistorial'];
$repuestosDisponibles = $datos['repuestosDisponibles'];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Postventa · <?= h($vehiculo['IdVehiculo']) ?></h1>
            <p class="subtle">Seguimiento técnico y services del vehículo vendido.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= h(panelBaseUrl('postventa/index.php')) ?>">← Volver</a>
        </div>
    </div>

    <section class="v4-card">
        <h2 style="margin-bottom:14px;">Datos del vehículo</h2>
        <div class="vehicle-meta-grid">
            <div class="meta-chip-v4"><span>Vehículo</span><strong><?= h($vehiculo['Modelo'] ?? '-') ?></strong></div>
            <div class="meta-chip-v4"><span>ID vehículo</span><strong><?= h($vehiculo['IdVehiculo'] ?? '-') ?></strong></div>
            <div class="meta-chip-v4"><span>Número motor</span><strong><?= h($vehiculo['NumeroMotor'] ?? '-') ?></strong></div>
            <div class="meta-chip-v4"><span>Cliente</span><strong><?= h($vehiculo['NombreApellido'] ?? '-') ?></strong></div>
            <div class="meta-chip-v4"><span>Teléfono</span><strong><?= h($vehiculo['Telefono'] ?? '-') ?></strong></div>
            <div class="meta-chip-v4"><span>Garantía</span><strong><?= h($vehiculo['EstadoGarantia'] ?? '-') ?></strong></div>
        </div>
    </section>

    <section class="v4-card">
        <h2 style="margin-bottom:14px;">Historial técnico extendido</h2>
        <?php if (!$historialTecnico): ?>
            <p class="help-text">Todavía no hay intervenciones técnicas registradas.</p>
        <?php endif; ?>
        <?php foreach ($historialTecnico as $hist): ?>
            <article class="section-box" style="margin-bottom:12px;">
                <strong><?= h($hist['Estado'], '') ?> · <?= h(substr((string)$hist['FechaApertura'], 0, 16), '') ?></strong>
                <p><strong>Diagnóstico:</strong> <?= nl2br(h($hist['Diagnostico'], '')) ?></p>
                <?php if (!empty($hist['SolucionAplicada'])): ?><p><strong>Solución:</strong> <?= nl2br(h($hist['SolucionAplicada'], '')) ?></p><?php endif; ?>
                <p class="subtle">Responsable: <?= h($hist['Tecnico'] ?? '-', '') ?> · Cierre: <?= h($hist['FechaCierre'] ?? '-', '') ?></p>
                <?php foreach ($repuestosUsadosPorHistorial[(int)$hist['IdHistorialTecnico']] ?? [] as $ru): ?>
                    <span class="badge badge-oculto"><?= h($ru['Nombre'], '') ?> x<?= (int)$ru['Cantidad'] ?></span>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="v4-card">
        <h2 style="margin-bottom:14px;">Services</h2>

        <div class="services-grid-v4">
            <?php if (!$services): ?>
                <article class="postventa-service-card">
                    <div class="service-card__note muted">
                        No hay services programados para este vehículo.
                    </div>
                </article>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                    <?php
                    $estadoService = (string)($service['Estado'] ?? '');
                    $serviceEditable = in_array($estadoService, ['Pendiente', 'Vencido'], true);
                    $estadoClass = match ($estadoService) {
                        'Pendiente' => 'v4-badge--warning',
                        'Realizado' => 'v4-badge--success',
                        'Cancelado' => 'v4-badge--danger',
                        'Vencido'   => 'v4-badge--danger',
                        default     => 'v4-badge--muted',
                    };
                    ?>

                    <article class="postventa-service-card">
                        <div class="service-card__top">
                            <strong>Service <?= h((string)$service['NumeroService']) ?></strong>
                            <span class="v4-badge <?= h($estadoClass) ?>">
                                <?= h($estadoService !== '' ? $estadoService : '-') ?>
                            </span>
                        </div>

                        <div class="service-card__meta">
                            <p>
                                <span>Programado</span>
                                <strong><?= h((string)$service['FechaProgramada']) ?></strong>
                            </p>
                            <p>
                                <span>Realizado</span>
                                <strong><?= h((string)($service['FechaRealizada'] ?: '-')) ?></strong>
                            </p>
                        </div>

                        <div class="service-card__note">
                            <?= nl2br(h((string)$service['Observaciones'])) ?>
                        </div>

                        <?php $eventosService = $historialPorService[(int)$service['IdService']] ?? []; ?>
                        <?php if ($eventosService): ?>
                            <div class="service-history-v4">
                                <strong>Historial técnico</strong>
                                <?php foreach ($eventosService as $evento): ?>
                                    <div class="service-history-v4__item">
                                        <span><?= h((string)$evento['TipoEvento']) ?> · <?= h((string)$evento['FechaEvento']) ?></span>
                                        <p><?= nl2br(h((string)$evento['Detalle'])) ?></p>
                                        <?php if (!empty($evento['Usuario'])): ?>
                                            <small>Usuario: <?= h((string)$evento['Usuario']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($estadoService !== 'Cancelado'): ?>
                            <form method="POST"
                                  action="<?= h(panelBaseUrl('postventa/service_observacion_agregar.php')) ?>"
                                  class="service-card__note-form">
                                <?= csrfInput() ?>
                                <input type="hidden" name="id_service" value="<?= h((string)$service['IdService']) ?>">
                                <input type="hidden" name="id_vehiculo" value="<?= h($idVehiculo) ?>">
                                <textarea name="nota_tecnica" rows="2" maxlength="300" required
                                          placeholder="Agregar nota técnica sin cambiar estado..."></textarea>
                                <button type="submit" class="btn-secondary btn-small">Agregar nota</button>
                            </form>

                            <?php if (in_array($estadoService, ['Pendiente', 'Vencido'], true)): ?>
                                <div class="service-card__actions-wrap">
                                    <form method="POST"
                                          action="<?= h(panelBaseUrl('vehiculos/service_realizar.php')) ?>"
                                          class="service-card__action">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="id_service" value="<?= h((string)$service['IdService']) ?>">
                                        <input type="hidden" name="id_vehiculo" value="<?= h($idVehiculo) ?>">
                                        <input type="hidden" name="origen" value="postventa">
                                        <textarea name="observaciones" rows="2" placeholder="Observaciones técnicas..."></textarea>
                                        <button type="submit" class="btn btn-small">Realizar service</button>
                                    </form>

                                    <form method="POST"
                                          action="<?= h(panelBaseUrl('postventa/service_cancelar.php')) ?>"
                                          class="service-card__action"
                                          data-confirm="¿Confirmás cancelar este service?">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="id_service" value="<?= h((string)$service['IdService']) ?>">
                                        <input type="hidden" name="id_vehiculo" value="<?= h($idVehiculo) ?>">
                                        <textarea name="motivo_cancelacion" rows="2" required
                                                  placeholder="Motivo de cancelación..."></textarea>
                                        <button type="submit" class="btn-secondary btn-small">Cancelar service</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
