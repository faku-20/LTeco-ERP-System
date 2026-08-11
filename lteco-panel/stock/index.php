<?php
$pageTitle = 'Stock | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';

requiereAdmin();

$cards = [
    ['href' => panelBaseUrl('vehiculos/index.php'), 'icon' => 'bike', 'label' => 'Vehículos', 'desc' => 'Stock de motos, estados, publicación y fotos.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('repuestos/index.php'), 'icon' => 'wrench', 'label' => 'Repuestos', 'desc' => 'Stock, catálogo y movimientos de repuestos.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('importaciones/index.php'), 'icon' => 'truck', 'label' => 'Importaciones', 'desc' => 'Lotes, costos y unidades importadas.', 'tone' => 'neutral'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Sección</p>
            <h1>Stock</h1>
            <p>Vehículos, repuestos e importaciones.</p>
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
