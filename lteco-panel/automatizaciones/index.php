<?php
$pageTitle = 'Automatizaciones | ERP';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';

requiereAdmin();

$cards = [
    ['href' => panelBaseUrl('ia/index.php'), 'icon' => 'sparkles', 'label' => 'Asistente IA', 'desc' => 'Consulta interna sobre ventas, stock, balance y postventa.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('ia/acciones.php'), 'icon' => 'sparkles', 'label' => 'IA acciones', 'desc' => 'Acciones sugeridas desde conversaciones guardadas.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('ia/base.php'), 'icon' => 'settings', 'label' => 'Base comercial IA', 'desc' => 'Tono, reglas comerciales, modelos y prohibidos.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('n8n/index.php'), 'icon' => 'settings', 'label' => 'n8n', 'desc' => 'Webhooks, eventos operativos y API interna.', 'tone' => 'neutral'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Sistema de gestión automotor</p>
            <h1>Automatizaciones</h1>
            <p><?= h((usuarioActual()['Usuario'] ?? 'Usuario') . ' · ' . rolActual()) ?></p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('inicio.php') ?>">Volver al inicio</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box module-hub__hero">
        <div>
            <p class="eyebrow">Sección</p>
            <h2>Automatizaciones</h2>
            <p class="muted">Asistente IA, acciones, base comercial y n8n.</p>
        </div>
    </section>

    <section class="module-hub__grid">
        <?php foreach ($cards as $card): ?>
            <a class="module-hub__card <?= h($card['tone']) ?>" href="<?= h($card['href']) ?>">
                <span class="module-hub__icon"><?= panelIcon($card['icon']) ?></span>
                <h2><?= h($card['label']) ?></h2>
                <p><?= h($card['desc']) ?></p>
            </a>
        <?php endforeach; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
