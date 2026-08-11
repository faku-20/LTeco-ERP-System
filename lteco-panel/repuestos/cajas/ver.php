<?php
$pageTitle = "Caja de repuestos | Lteco";
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . '/../../includes/flash.php';

requiereModulo('repuestos');
require_once __DIR__ . "/../../includes/helpers.php";

$service = new \Lteco\Application\Repuesto\RepuestoCajaService(
    new \Lteco\Infrastructure\Repository\RepuestoCajaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    ),
    new \Lteco\Application\Repuesto\RepuestoCrudService(
        new \Lteco\Infrastructure\Repository\RepuestoCrudRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    )
);

$token = trim((string)($_GET['t'] ?? ''));
$codigo = trim((string)($_GET['c'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$data = null;
if ($token !== '') {
    $data = $service->obtener($token, 'TokenUuid');
} elseif ($codigo !== '') {
    $data = $service->obtener($codigo, 'Codigo');
} elseif ($id > 0) {
    $data = $service->obtener($id, 'IdCaja');
}

if (!$data) {
    redirectWithFlash(panelBaseUrl('repuestos/cajas/index.php'), 'error', 'Caja no encontrada.');
}

$caja = $data['caja'];
$contenido = $data['contenido'];
$movimientos = $data['movimientos'];

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1><?= h($caja['Codigo'], '') ?></h1>
            <p class="subtle">Ficha interna de caja de repuestos.</p>
        </div>
        <div class="actions-row">
            <a class="btn" href="<?= panelBaseUrl('repuestos/cajas/qr.php?t=' . urlencode((string)$caja['TokenUuid'])) ?>">Imprimir QR</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/cajas/index.php') ?>">Volver</a>
        </div>
    </div>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <section class="v4-card">
        <div class="form-grid">
            <div class="form-group"><label>Código</label><strong><?= h($caja['Codigo'], '') ?></strong></div>
            <div class="form-group"><label>Nombre</label><strong><?= h($caja['Nombre'] ?: $caja['Codigo'], '') ?></strong></div>
            <div class="form-group"><label>Ubicación</label><strong><?= h($caja['Ubicacion']) ?></strong></div>
            <div class="form-group"><label>Estado</label><span class="badge <?= $caja['Estado'] === 'Activa' ? 'badge-disponible' : 'badge-oculto' ?>"><?= h($caja['Estado'], '') ?></span></div>
            <div class="form-group"><label>Fecha alta</label><strong><?= h($caja['FechaAlta'], '') ?></strong></div>
            <div class="form-group full"><label>Observaciones</label><p><?= h($caja['Observaciones']) ?></p></div>
        </div>
    </section>

    <section class="v4-card">
        <div class="list-head-v4"><div><h2>Contenido</h2><p>Repuestos ubicados en esta caja.</p></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Repuesto</th><th class="num">Cantidad en caja</th><th class="num">Stock total</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($contenido as $item): ?>
                        <tr>
                            <td><a href="<?= panelBaseUrl('repuestos/index.php?q=' . urlencode((string)$item['Nombre'])) ?>"><?= h($item['Nombre'], '') ?></a></td>
                            <td class="num"><?= (int)$item['Cantidad'] ?></td>
                            <td class="num"><?= (int)$item['Stock'] ?></td>
                            <td><?= h($item['Estado'], '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$contenido): ?><tr><td colspan="4" style="text-align:center;color:var(--ink-3);padding:24px;">Caja sin contenido.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="v4-card">
        <div class="list-head-v4"><div><h2>Historial</h2><p>Movimientos registrados para esta caja.</p></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Repuesto</th><th class="num">Cantidad</th><th>Detalle</th></tr></thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?= h($mov['FechaAlta'], '') ?></td>
                            <td><?= h($mov['Tipo'], '') ?></td>
                            <td><?= h($mov['RepuestoNombre']) ?></td>
                            <td class="num"><?= (int)$mov['Cantidad'] ?></td>
                            <td><?= h($mov['Detalle']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
