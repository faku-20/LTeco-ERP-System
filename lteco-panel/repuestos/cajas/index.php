<?php
$pageTitle = "Cajas de repuestos | Lteco";
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . '/../../includes/flash.php';

requiereModulo('repuestos');
require_once __DIR__ . "/../../includes/helpers.php";

$repo = new \Lteco\Infrastructure\Repository\RepuestoCajaRepository(
    new \Lteco\Infrastructure\Db\Connection($pdo)
);
$service = new \Lteco\Application\Repuesto\RepuestoCajaService(
    $repo,
    new \Lteco\Application\Repuesto\RepuestoCrudService(
        new \Lteco\Infrastructure\Repository\RepuestoCrudRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    )
);

$cajas = $service->listar();

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Cajas de repuestos</h1>
            <p class="subtle">Cajas físicas de importación con contenido ubicado.</p>
        </div>
        <div class="actions-row repuesto-cajas-actions">
            <?php if (esAdmin()): ?>
                <a class="btn" href="<?= panelBaseUrl('repuestos/cajas/crear.php') ?>">+ Nueva caja</a>
            <?php endif; ?>
            <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/index.php') ?>">Volver a repuestos</a>
        </div>
    </div>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Listado</h2>
                <p><?= count($cajas) ?> cajas registradas.</p>
            </div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Ubicación</th>
                    <th class="num">Repuestos</th>
                    <th class="num">Unidades</th>
                    <th>Estado</th>
                    <th>Alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cajas): ?>
                    <?php foreach ($cajas as $caja): ?>
                        <tr>
                            <td><strong><?= h($caja['Codigo'], '') ?></strong></td>
                            <td><?= h($caja['Nombre'] ?: $caja['Codigo'], '') ?></td>
                            <td><?= h($caja['Ubicacion']) ?></td>
                            <td class="num"><?= (int)$caja['TotalRepuestos'] ?></td>
                            <td class="num"><?= (int)$caja['TotalUnidades'] ?></td>
                            <td><span class="badge <?= $caja['Estado'] === 'Activa' ? 'badge-disponible' : 'badge-oculto' ?>"><?= h($caja['Estado'], '') ?></span></td>
                            <td><?= h($caja['FechaAlta'], '') ?></td>
                            <td>
                                <div class="actions-wrap">
                                    <a class="btn-secondary btn-small" href="<?= panelBaseUrl('repuestos/cajas/ver.php?c=' . urlencode((string)$caja['Codigo'])) ?>">Ver</a>
                                    <a class="btn-secondary btn-small" href="<?= panelBaseUrl('repuestos/cajas/qr.php?t=' . urlencode((string)$caja['TokenUuid'])) ?>">QR</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--ink-3);padding:32px;">Todavía no hay cajas registradas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
