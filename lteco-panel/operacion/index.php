<?php
$pageTitle = 'Ventas | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';

requiereAdmin();

$cards = [
    ['href' => panelBaseUrl('ventas/crear.php'), 'icon' => 'plus', 'label' => 'Nueva venta', 'desc' => 'Registrar venta de vehículo o accesorio.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('ventas/index.php'), 'icon' => 'receipt', 'label' => 'Ventas', 'desc' => 'Historial, detalle, anulación y comprobantes.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('clientes/index.php'), 'icon' => 'users', 'label' => 'Clientes', 'desc' => 'Crear, editar, listar y buscar clientes.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('postventa/index.php'), 'icon' => 'lifebuoy', 'label' => 'Postventa', 'desc' => 'Services, garantías, reclamos y seguimiento técnico.', 'tone' => 'neutral'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Sección</p>
            <h1>Ventas</h1>
            <p>Ventas, nueva venta, clientes y postventa.</p>
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
