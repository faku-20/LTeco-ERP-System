<?php
$pageTitle = 'Alertas | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/agenda.php';

requiereNoDistribuidor();
$user = usuarioActual() ?: [];
$alerts = agendaAlertRows($pdo, $user, 100);
$open = array_filter($alerts, static fn(array $row): bool => $row['Estado'] === 'abierta');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Operación</p>
            <h1>Alertas</h1>
            <p><?= count($open) ?> alerta(s) abierta(s).</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('agenda/index.php') ?>">Agenda</a>
            <a class="btn-secondary" href="<?= panelBaseUrl(esAdmin() ? 'automatizaciones/index.php' : 'inicio.php') ?>">Volver</a>
        </div>
    </header>

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box">
        <div class="table-wrap mobile-cards">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Alerta</th>
                        <th>Detalle</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td data-label="Fecha"><?= h(date('d/m/Y H:i', strtotime((string)$alert['FechaAlta']))) ?></td>
                            <td data-label="Alerta"><strong><?= h($alert['Titulo']) ?></strong></td>
                            <td data-label="Detalle"><?= h($alert['Cuerpo'] ?: '-') ?></td>
                            <td data-label="Estado"><span class="badge"><?= h(ucfirst((string)$alert['Estado'])) ?></span></td>
                            <td data-label="Acciones" class="mobile-card-actions">
                                <?php if ($alert['Estado'] !== 'cerrada'): ?>
                                    <form class="inline-form" method="post" action="<?= panelBaseUrl('notificaciones/estado.php') ?>">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="id" value="<?= (int)$alert['IdAlert'] ?>">
                                        <?php if ($alert['Estado'] === 'abierta'): ?>
                                            <button class="btn-secondary" name="estado" value="leida" type="submit">Marcar leída</button>
                                        <?php endif; ?>
                                        <button class="btn-secondary" name="estado" value="cerrada" type="submit">Cerrar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Cerrada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$alerts): ?>
                        <tr><td colspan="5" class="mobile-card-empty">No hay alertas registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
