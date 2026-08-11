<?php
$pageTitle = 'IA acciones | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
aiEnsureSchema($pdo);

$estado = trim((string)($_GET['estado'] ?? 'pendiente'));
$tipo = trim((string)($_GET['tipo'] ?? ''));
$acciones = aiSuggestedActionsRows($pdo, $estado, $tipo, 150);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">IA comercial</p>
            <h1>Acciones sugeridas</h1>
            <p>Confirmación y ejecución asistida desde conversaciones reales.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('ia/acciones_exportar.php') ?>?<?= h(http_build_query(['estado' => $estado, 'tipo' => $tipo]), '') ?>">Exportar teléfonos CSV</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('whatsapp/index.php') ?>">WhatsApp</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('automatizaciones/index.php') ?>">Automatizaciones</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box">
        <div class="wa-auto-reply-panel">
            <form class="filter-bar" method="get" action="<?= panelBaseUrl('ia/acciones.php') ?>">
                <label>Estado
                    <select name="estado">
                        <option value="">Todos</option>
                        <?php foreach (['pendiente', 'confirmada', 'rechazada', 'ejecutada', 'error'] as $item): ?>
                            <option value="<?= h($item) ?>" <?= $estado === $item ? 'selected' : '' ?>><?= h(ucfirst($item)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tipo
                    <input name="tipo" value="<?= h($tipo, '') ?>" placeholder="crear_visita, cotizar...">
                </label>
                <button class="btn" type="submit">Filtrar</button>
            </form>
            <form method="post" action="<?= panelBaseUrl('ia/acciones_analizar.php') ?>" class="wa-inline-form">
                <?= csrfInput() ?>
                <label>Límite
                    <input name="limite" type="number" min="1" max="200" value="50">
                </label>
                <button class="btn-secondary" type="submit">Analizar conversaciones</button>
            </form>
        </div>
    </section>

    <section class="section-box">
        <div class="table-wrap mobile-cards">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Prioridad</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($acciones as $accion): ?>
                        <tr>
                            <td data-label="Fecha"><?= h(date('d/m/Y H:i', strtotime((string)$accion['FechaAlta']))) ?></td>
                            <td data-label="Tipo"><span class="badge"><?= h($accion['TipoAccion']) ?></span></td>
                            <td data-label="Cliente">
                                <strong><?= h($accion['ClienteNombre'] ?: 'Sin nombre') ?></strong><br>
                                <small><?= h($accion['ClienteTelefono'] ?: $accion['VehiculoTexto'] ?: 'Sin dato') ?></small>
                            </td>
                            <td data-label="Prioridad"><?= h($accion['Prioridad']) ?></td>
                            <td data-label="Motivo"><?= h(mb_substr((string)$accion['Motivo'], 0, 180), '') ?></td>
                            <td data-label="Estado"><span class="badge"><?= h($accion['Estado']) ?></span></td>
                            <td data-label="Acciones" class="mobile-card-actions">
                                <form class="inline-form" method="post" action="<?= panelBaseUrl('ia/accion_estado.php') ?>">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="id" value="<?= (int)$accion['IdAccion'] ?>">
                                    <button class="btn-secondary" name="accion" value="confirmar" type="submit">Confirmar</button>
                                    <button class="btn-secondary" name="accion" value="ejecutar" type="submit">Ejecutar</button>
                                    <button class="btn-secondary" name="accion" value="rechazar" type="submit">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$acciones): ?>
                        <tr><td colspan="7" class="mobile-card-empty">Sin acciones sugeridas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
