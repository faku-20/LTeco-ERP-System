<?php
$pageTitle = 'Administración | ERP';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';

requiereAdmin();

$cards = [
    ['href' => panelBaseUrl('balance/index.php'), 'icon' => 'bar', 'label' => 'Balance', 'desc' => 'Ventas, gastos, comisiones y resumen financiero.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('gastos/index.php'), 'icon' => 'trendDown', 'label' => 'Gastos', 'desc' => 'Carga, control y anulación de gastos.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('distribuidores/index.php'), 'icon' => 'truck', 'label' => 'Distribuidores', 'desc' => 'Stock asignado, pedidos, ventas y estado de cuenta.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('distribuidores/reportes_admin.php'), 'icon' => 'lifebuoy', 'label' => 'Problemas reportados', 'desc' => 'Incidencias reportadas por distribuidores.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('usuarios/index.php'), 'icon' => 'userCog', 'label' => 'Usuarios', 'desc' => 'Altas, roles, activación y claves.', 'tone' => 'neutral'],
    ['href' => panelBaseUrl('configuracion/index.php'), 'icon' => 'settings', 'label' => 'Configuración', 'desc' => 'Parámetros operativos del panel.', 'tone' => 'neutral'],
];

if (esSuperadmin()) {
    $cards[] = ['href' => panelBaseUrl('auditoria/index.php'), 'icon' => 'shield', 'label' => 'Auditoría', 'desc' => 'Trazabilidad de acciones críticas.', 'tone' => 'neutral'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Sección</p>
            <h1>Administración</h1>
            <p>Finanzas, distribuidores, usuarios y configuración.</p>
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
