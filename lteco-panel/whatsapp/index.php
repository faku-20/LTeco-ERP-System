<?php
$pageTitle = 'WhatsApp | ERP';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
aiEnsureSchema($pdo);
whatsappEnsureTabla($pdo);

$q = trim((string)($_GET['q'] ?? ''));
$canal = trim((string)($_GET['canal'] ?? ''));
$estado = trim((string)($_GET['estado'] ?? ''));

$inbox = aiInboxRows($pdo, $q, $canal, 30);
$salientes = aiWhatsappOutgoingRows($pdo, $q, $estado, 40);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Conversaciones</p>
            <h1>WhatsApp</h1>
            <p>Bandeja Meta, clasificación IA y respuestas desde el panel.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('ia/acciones.php') ?>">IA acciones</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('automatizaciones/index.php') ?>">Automatizaciones</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box">
        <form class="filter-bar" method="get" action="<?= panelBaseUrl('whatsapp/index.php') ?>">
            <label>Buscar
                <input name="q" value="<?= h($q, '') ?>" placeholder="Cliente, teléfono o texto">
            </label>
            <label>Canal
                <select name="canal">
                    <option value="">Todos</option>
                    <option value="whatsapp" <?= $canal === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                    <option value="instagram" <?= $canal === 'instagram' ? 'selected' : '' ?>>Instagram</option>
                </select>
            </label>
            <label>Estado saliente
                <select name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['enviado', 'error', 'omitido'] as $status): ?>
                        <option value="<?= h($status) ?>" <?= $estado === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn" type="submit">Filtrar</button>
        </form>
    </section>

    <section class="section-box">
        <div class="wa-auto-reply-panel">
            <div>
                <p class="eyebrow">Entradas</p>
                <h2>WhatsApp e Instagram</h2>
                <p class="muted">La IA aprende de las preguntas y respuestas guardadas en esta bandeja para mejorar futuras sugerencias. No puede leer historial viejo que Meta no haya entregado.</p>
            </div>
            <form method="post" action="<?= panelBaseUrl('ia/acciones_analizar.php') ?>" class="wa-inline-form">
                <?= csrfInput() ?>
                <input type="hidden" name="volver" value="<?= h(panelBaseUrl('whatsapp/index.php'), '') ?>">
                <input name="limite" type="hidden" value="200">
                <button class="btn-secondary" type="submit">Analizar y aprender del historial</button>
            </form>
        </div>
    </section>

    <section class="section-box">
        <div class="table-wrap mobile-cards mobile-cards-wide">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Canal</th>
                        <th>Contacto</th>
                        <th>Mensaje</th>
                        <th>IA</th>
                        <th>Responder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inbox as $entry): ?>
                        <tr>
                            <td data-label="Fecha"><?= h(date('d/m/Y H:i', strtotime((string)($entry['FechaRecibido'] ?: $entry['FechaAlta'])))) ?></td>
                            <td data-label="Canal"><span class="badge"><?= h(ucfirst((string)$entry['Canal'])) ?></span></td>
                            <td data-label="Contacto">
                                <strong><?= h($entry['RemitenteNombre'] ?: $entry['LeadNombre'] ?: 'Sin nombre') ?></strong><br>
                                <small><?= h($entry['Telefono'] ?: $entry['RemitenteHandle'] ?: 'Sin contacto') ?></small>
                            </td>
                            <td data-label="Mensaje">
                                <?php if (!empty($entry['ReplyToModelo'])): ?>
                                    <span class="badge">Responde por <?= h($entry['ReplyToModelo']) ?></span><br>
                                <?php endif; ?>
                                <?= h(mb_substr((string)$entry['Mensaje'], 0, 180)) ?>
                            </td>
                            <td data-label="IA">
                                <?php if ($entry['AiIntent']): ?>
                                    <span class="badge"><?= h($entry['AiIntent'] . ' · ' . $entry['AiPrioridad']) ?></span>
                                    <p><?= h(mb_substr((string)$entry['AiResumen'], 0, 120), '') ?></p>
                                    <?php if ($entry['AiRespuestaSugerida']): ?>
                                        <div class="wa-ai-alert">
                                            <div class="wa-ai-alert__head">Sugerencia IA</div>
                                            <div class="wa-ai-alert__body"><?= h($entry['AiRespuestaSugerida'], '') ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($entry['AiError']): ?>
                                    <span class="badge">Error IA</span>
                                    <small><?= h(mb_substr((string)$entry['AiError'], 0, 100), '') ?></small>
                                <?php else: ?>
                                    <span class="badge">Sin clasificar</span>
                                <?php endif; ?>
                                <form method="post" action="<?= panelBaseUrl('whatsapp/clasificar.php') ?>">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="id" value="<?= (int)$entry['IdInbox'] ?>">
                                    <button class="btn-secondary" type="submit">Clasificar</button>
                                </form>
                            </td>
                            <td data-label="Responder" class="mobile-card-actions">
                                <?php if ((string)$entry['Canal'] === 'whatsapp' && trim((string)$entry['Telefono']) !== ''): ?>
                                    <form class="wa-reply-form" method="post" action="<?= panelBaseUrl('whatsapp/responder.php') ?>">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="id" value="<?= (int)$entry['IdInbox'] ?>">
                                        <textarea name="body" maxlength="1500" required><?= h($entry['AiRespuestaSugerida'], '') ?></textarea>
                                        <button class="btn-secondary" type="submit">Enviar respuesta</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Sin teléfono</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$inbox): ?>
                        <tr><td colspan="6" class="mobile-card-empty">Sin entradas guardadas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">Salientes</p>
                <h2>Mensajes enviados por el panel</h2>
            </div>
        </div>
        <div class="table-wrap mobile-cards">
            <table>
                <thead><tr><th>Fecha</th><th>Teléfono</th><th>Tipo</th><th>Estado</th><th>Resumen</th></tr></thead>
                <tbody>
                    <?php foreach ($salientes as $msg): ?>
                        <tr>
                            <td data-label="Fecha"><?= h(date('d/m/Y H:i', strtotime((string)$msg['FechaEnvio']))) ?></td>
                            <td data-label="Teléfono"><?= h($msg['Telefono']) ?></td>
                            <td data-label="Tipo"><?= h($msg['Template']) ?></td>
                            <td data-label="Estado"><span class="badge"><?= h($msg['Estado']) ?></span></td>
                            <td data-label="Resumen"><?= h(whatsappResumenUltimoError($pdo, (string)$msg['Tipo'], (int)$msg['IdReferencia'], (string)$msg['Template']), '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$salientes): ?>
                        <tr><td colspan="5" class="mobile-card-empty">Sin mensajes salientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
