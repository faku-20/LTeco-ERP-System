<?php
$pageTitle = 'Agenda | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/agenda.php';

requiereNoDistribuidor();
$user = usuarioActual() ?: [];
$visits = agendaVisitRows($pdo, $user, 150);
$pending = array_filter($visits, static fn(array $row): bool => in_array($row['Estado'], ['agendada','reprogramada'], true));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Comercial</p>
            <h1>Agenda</h1>
            <p><?= count($pending) ?> visita(s) pendiente(s).</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('notificaciones/index.php') ?>">Alertas</a>
            <a class="btn-secondary" href="<?= panelBaseUrl(esAdmin() ? 'comercial/index.php' : 'inicio.php') ?>">Volver</a>
        </div>
    </header>

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">Showroom</p>
                <h2>Visitas</h2>
            </div>
        </div>

        <div class="table-wrap mobile-cards">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Modelo</th>
                        <th>Responsable</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                        <tr>
                            <td data-label="Fecha">
                                <strong><?= h(date('d/m/Y', strtotime((string)$visit['FechaVisita']))) ?></strong><br>
                                <?php if ((int)($visit['HoraConfirmada'] ?? 1) === 1): ?>
                                    <span><?= h(date('H:i', strtotime((string)$visit['FechaVisita']))) ?></span>
                                <?php else: ?>
                                    <span class="badge">Hora pendiente · preferencia <?= h(date('H:i', strtotime((string)$visit['FechaVisita']))) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Cliente">
                                <?= h($visit['ClienteNombre']) ?><br>
                                <small><?= h($visit['ClienteTelefono'] ?: 'Sin teléfono') ?></small>
                                <?php if (!empty($visit['ClienteCorreo'])): ?><br><small><?= h($visit['ClienteCorreo']) ?></small><?php endif; ?>
                            </td>
                            <td data-label="Modelo"><?= h($visit['VehiculoTexto'] ?: 'Sin modelo') ?><br><small><?= h($visit['Canal'] ?: 'Sin canal') ?></small></td>
                            <td data-label="Responsable"><?= h($visit['ResponsableNombre'] ?: 'Sin asignar') ?></td>
                            <td data-label="Estado"><span class="badge"><?= h(ucfirst(str_replace('_', ' ', (string)$visit['Estado']))) ?></span></td>
                            <td data-label="Acciones" class="mobile-card-actions">
                                <?php if (in_array($visit['Estado'], ['agendada','reprogramada'], true)): ?>
                                    <?php if ((int)($visit['HoraConfirmada'] ?? 1) === 0): ?>
                                        <form class="inline-form" method="post" action="<?= panelBaseUrl('agenda/hora.php') ?>">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="id" value="<?= (int)$visit['IdVisita'] ?>">
                                            <label>
                                                <span class="sr-only">Hora de la visita</span>
                                                <input name="hora" type="time" min="08:00" max="20:59" value="<?= h(date('H:i', strtotime((string)$visit['FechaVisita']))) ?>" required>
                                            </label>
                                            <button class="btn-secondary" type="submit">Confirmar hora</button>
                                        </form>
                                    <?php endif; ?>
                                    <form class="inline-form" method="post" action="<?= panelBaseUrl('agenda/estado.php') ?>">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="id" value="<?= (int)$visit['IdVisita'] ?>">
                                        <button class="btn-secondary" name="estado" value="asistio" type="submit">Asistió</button>
                                        <button class="btn-secondary" name="estado" value="no_asistio" type="submit">No asistió</button>
                                        <button class="btn-secondary" name="estado" value="cancelada" type="submit">Cancelar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Sin acciones pendientes</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$visits): ?>
                        <tr><td colspan="6" class="mobile-card-empty">No hay visitas registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
