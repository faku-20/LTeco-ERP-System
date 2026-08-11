<?php
$pageTitle = 'Comercial | ERP';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';

requiereAdmin();

$cards = [
    ['href' => panelBaseUrl('busqueda/index.php'), 'icon' => 'search', 'label' => 'Buscar', 'desc' => 'Buscar vehículos, clientes o ventas.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('whatsapp/index.php'), 'icon' => 'bell', 'label' => 'WhatsApp', 'desc' => 'Conversaciones, sugerencias IA y respuestas.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('agenda/index.php'), 'icon' => 'calendar', 'label' => 'Agenda', 'desc' => 'Visitas showroom y seguimiento de asistencia.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('notificaciones/index.php'), 'icon' => 'bell', 'label' => 'Alertas', 'desc' => 'Visitas agendadas y avisos operativos.', 'tone' => 'neutral'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Sección</p>
            <h1>Comercial</h1>
            <p>Búsqueda, WhatsApp, agenda y alertas comerciales.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('dashboard.php') ?>">Volver al dashboard</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

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
