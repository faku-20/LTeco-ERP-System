<?php
$pageTitle = "Detalle de reporte | Lteco";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

requiereAdmin();

$reportes = new \Lteco\Application\Distribuidor\DistribuidorReporteService(
    new \Lteco\Infrastructure\Repository\DistribuidorReporteRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$tablaLista = $reportes->estaDisponible();
if (!$tablaLista) {
    redirectWithFlash(panelBaseUrl('distribuidores/reportes_admin.php'), 'error',
        'Falta aplicar la migración de reportes.');
}

$idReporte = (int)($_GET['id'] ?? 0);
if ($idReporte <= 0) {
    redirectWithFlash(panelBaseUrl('distribuidores/reportes_admin.php'), 'error', 'Reporte no encontrado.');
}

$reporte = $reportes->obtenerReporte($idReporte);

if (!$reporte) {
    redirectWithFlash(panelBaseUrl('distribuidores/reportes_admin.php'), 'error', 'Reporte no encontrado.');
}

// --- Cambio de estado interno (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    verifyCsrfOrFail();

    $nuevoEstado = (string)($_POST['estado_interno'] ?? '');
    $idUsuarioActual = (int)(usuarioActual()['IdUsuario'] ?? 0);
    try {
        $reportes->actualizarEstado($idReporte, $nuevoEstado, $idUsuarioActual);
        $nuevoEstado = trim($nuevoEstado);
    } catch (InvalidArgumentException $e) {
        redirectWithFlash(panelBaseUrl('distribuidores/reporte_detalle.php?id=' . $idReporte), 'error',
            $e->getMessage());
    }

    registrarAuditoria($pdo, 'REPORTE_PROBLEMA_ESTADO', 'Distribuidores',
        'Reporte #' . $idReporte . ' -> ' . $nuevoEstado, [
            'id_reporte' => $idReporte,
            'estado_anterior' => (string)($reporte['EstadoInterno'] ?? ''),
            'estado_nuevo' => $nuevoEstado,
            'id_usuario_admin' => $idUsuarioActual,
        ]);

    redirectWithFlash(panelBaseUrl('distribuidores/reporte_detalle.php?id=' . $idReporte), 'success',
        'Estado actualizado a "' . $nuevoEstado . '".');
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

$estadoBadge = match((string)($reporte['EstadoInterno'] ?? 'Nuevo')) {
    'Nuevo'    => 'badge-info',
    'Revisado' => 'badge-pendiente',
    'Resuelto' => 'badge-disponible',
    default    => 'badge-info',
};
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Reporte #<?= (int)$reporte['IdReporte'] ?></h1>
            <p class="subtle">
                <?= h($reporte['DistribuidorNombre'] ?? '-') ?> · <?= h($reporte['UsuarioNombre'] ?? '-') ?>
                · <?= h(substr((string)($reporte['FechaCreacion'] ?? ''), 0, 16)) ?>
            </p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/reportes_admin.php') ?>">← Volver</a>
        </div>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <div style="display:grid;gap:20px;grid-template-columns:1fr 340px;align-items:start;">

        <section class="v4-card">
            <div class="list-head-v4">
                <div>
                    <h2>Mensaje del distribuidor</h2>
                </div>
                <span class="badge <?= $estadoBadge ?>"><?= h($reporte['EstadoInterno'] ?? 'Nuevo') ?></span>
            </div>
            <div style="padding:4px 0 12px;">
                <p style="white-space:pre-wrap;line-height:1.65;"><?= h($reporte['Mensaje'] ?? '') ?></p>
            </div>

            <?php if (!empty($reporte['ImagenRuta'])): ?>
                <div style="margin-top:12px;">
                    <p class="help-text" style="margin-bottom:8px;">Imagen adjunta:</p>
                    <?php $imagenUrl = panelBaseUrl((string)$reporte['ImagenRuta']); ?>
                    <a href="<?= h($imagenUrl, '') ?>" target="_blank" rel="noopener">
                        <img src="<?= h($imagenUrl, '') ?>"
                             alt="Imagen del reporte"
                             style="max-width:100%;max-height:480px;border-radius:10px;border:1px solid var(--color-border-secondary);">
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <div style="display:flex;flex-direction:column;gap:16px;">
            <section class="v4-card">
                <div class="list-head-v4"><div><h2>Datos</h2></div></div>
                <div style="display:grid;gap:8px;font-size:.93rem;padding-bottom:4px;">
                    <div><span class="help-text">Distribuidor</span><br><strong><?= h($reporte['DistribuidorNombre'] ?? '-') ?></strong></div>
                    <div><span class="help-text">Usuario</span><br><strong><?= h($reporte['UsuarioNombre'] ?? '-') ?></strong></div>
                    <div><span class="help-text">Fecha</span><br><?= h(substr((string)($reporte['FechaCreacion'] ?? ''), 0, 16)) ?></div>
                    <?php if ($reporte['FechaResolucion']): ?>
                        <div><span class="help-text">Resuelto el</span><br><?= h(substr((string)$reporte['FechaResolucion'], 0, 16)) ?></div>
                    <?php endif; ?>
                    <?php if ($reporte['UsuarioResolucionNombre']): ?>
                        <div><span class="help-text">Resuelto por</span><br><?= h($reporte['UsuarioResolucionNombre']) ?></div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="v4-card">
                <div class="list-head-v4"><div><h2>Estado interno</h2></div></div>
                <p class="help-text" style="margin-bottom:12px;">Solo visible para el equipo. No lo ve el distribuidor.</p>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach (['Nuevo', 'Revisado', 'Resuelto'] as $opcion):
                        $activo = (string)($reporte['EstadoInterno'] ?? 'Nuevo') === $opcion;
                    ?>
                        <form method="post" action="" style="margin:0;">
                            <?= csrfInput() ?>
                            <input type="hidden" name="estado_interno" value="<?= h($opcion) ?>">
                            <button type="submit"
                                    class="<?= $activo ? 'btn' : 'btn-secondary' ?>"
                                    <?= $activo ? 'disabled' : '' ?>
                                    style="text-align:left;width:100%;">
                                <?= h($opcion) ?>
                                <?= $activo ? ' ✓' : '' ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

    </div>

    <style>
        @media (max-width: 720px) {
            .main > div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
