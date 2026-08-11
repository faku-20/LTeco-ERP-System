<?php
$pageTitle = 'n8n | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/n8n.php';

requiereAdmin();
n8nEnsureSchema($pdo);

$settings = n8nSettings($pdo);
$logs = n8nLogs($pdo, 50);
$health = n8nHealth($pdo);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Automatizaciones</p>
            <h1>n8n</h1>
            <p>Webhooks, eventos operativos y endpoints internos para automatización.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('automatizaciones/index.php') ?>">Automatizaciones</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">Estado</p>
                <h2>Integración</h2>
            </div>
        </div>
        <div class="metrics-grid">
            <div class="metric-card">
                <span>IA runtime</span>
                <strong><?= $health['ai_enabled'] ? 'Activa' : 'Inactiva' ?></strong>
            </div>
            <div class="metric-card">
                <span>Token n8n</span>
                <strong><?= $health['n8n_token_configured'] ? 'Configurado' : 'Falta' ?></strong>
            </div>
            <div class="metric-card">
                <span>Webhooks push activos</span>
                <strong><?= (int)$health['enabled_webhooks'] ?></strong>
            </div>
            <div class="metric-card">
                <span>Eventos pendientes</span>
                <strong><?= (int)$health['pending_events'] ?></strong>
            </div>
        </div>
    </section>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">Push opcional</p>
                <h2>Webhooks salientes</h2>
                <p class="muted">n8n consume las APIs internas del panel. Activá estas URLs solo para flujos push adicionales.</p>
            </div>
            <button form="n8n-settings-form" class="btn" type="submit">Guardar</button>
        </div>
        <form id="n8n-settings-form" method="post" action="<?= panelBaseUrl('n8n/guardar.php') ?>">
            <?= csrfInput() ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Activo</th>
                            <th>Webhook URL</th>
                            <th>Timeout</th>
                            <th>Secret</th>
                            <th>Prueba</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settings as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= h($row['Label']) ?></strong><br>
                                    <small><?= h($row['EventKey']) ?></small>
                                </td>
                                <td>
                                    <label class="switch-inline">
                                        <input type="checkbox" name="settings[<?= (int)$row['IdSetting'] ?>][enabled]" value="1" <?= (int)$row['Enabled'] === 1 ? 'checked' : '' ?>>
                                        <span>Activo</span>
                                    </label>
                                </td>
                                <td>
                                    <input name="settings[<?= (int)$row['IdSetting'] ?>][webhook_url]" value="<?= h($row['WebhookUrl'] ?? '', '') ?>" placeholder="https://n8n.../webhook/...">
                                </td>
                                <td>
                                    <input type="number" min="3" max="60" name="settings[<?= (int)$row['IdSetting'] ?>][timeout_seconds]" value="<?= (int)$row['TimeoutSeconds'] ?>">
                                </td>
                                <td>
                                    <input name="settings[<?= (int)$row['IdSetting'] ?>][secret]" value="<?= h($row['Secret'] ?? '', '') ?>" placeholder="Opcional">
                                </td>
                                <td>
                                    <button class="btn-secondary" type="submit" formaction="<?= panelBaseUrl('n8n/probar.php') ?>" name="event_key" value="<?= h($row['EventKey']) ?>">Probar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </section>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">API n8n</p>
                <h2>Endpoints disponibles</h2>
            </div>
        </div>
        <div class="ai-shortcuts">
            <?php foreach ([
                'api/n8n/health.php' => 'Salud de integración',
                'api/n8n/digest.php' => 'Resumen operativo',
                'api/n8n/inbox.php' => 'Ingreso de mensajes',
                'api/n8n/meta_whatsapp.php' => 'Payload Meta WhatsApp',
                'api/n8n/events.php' => 'Eventos pendientes',
                'api/n8n/event_ack.php' => 'Confirmar evento',
                'api/n8n/classification.php' => 'Guardar acción IA',
            ] as $path => $label): ?>
                <div class="ai-shortcut">
                    <span><?= h($label) ?></span>
                    <strong><?= h(panelBaseUrl($path)) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted">Todos requieren header <code>X-Lteco-N8n-Token</code>. El token no se muestra en pantalla.</p>
    </section>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">Historial</p>
                <h2>Últimos envíos</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Evento</th>
                        <th>Estado</th>
                        <th>HTTP</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= h(date('d/m/Y H:i', strtotime((string)$log['FechaAlta']))) ?></td>
                            <td><?= h($log['EventKey']) ?></td>
                            <td><span class="badge"><?= h($log['Status']) ?></span></td>
                            <td><?= h((string)($log['HttpStatus'] ?? '-')) ?></td>
                            <td><?= h(mb_substr((string)($log['ErrorMessage'] ?: $log['ResponseBody'] ?: ''), 0, 180), '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="5">Sin envíos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
