<?php
$pageTitle = "Reportes de distribuidores | ERP";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

requiereAdmin();

$consulta = new \Lteco\Application\Distribuidor\DistribuidorConsultaService(
    new \Lteco\Infrastructure\Repository\DistribuidorConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$listado = $consulta->reportes((string)($_GET['estado'] ?? ''));
$tablaLista = $listado['tablaLista'];
$filtroEstado = $listado['filtroEstado'];
$reportes = $listado['reportes'];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Reportes de distribuidores</h1>
            <p class="subtle">Problemas reportados desde el portal de distribuidores.</p>
        </div>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <?php if (!$tablaLista): ?>
        <div class="notice notice--error">
            Falta aplicar la migración. Ejecutá
            <code>database/migrations/2026_06_08_distribuidor_reporte_problema.sql</code>
            en la base <code>lteco_db</code>.
        </div>
    <?php else: ?>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Reportes recibidos</h2>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <?php
                $estados = ['' => 'Todos', 'Nuevo' => 'Nuevos', 'Revisado' => 'Revisados', 'Resuelto' => 'Resueltos'];
                foreach ($estados as $val => $label):
                    $activo = $filtroEstado === $val;
                ?>
                    <a href="?estado=<?= urlencode($val) ?>"
                       class="<?= $activo ? 'btn btn-small' : 'btn-secondary btn-small' ?>">
                        <?= h($label) ?>
                    </a>
                <?php endforeach; ?>
                <div class="result-pill-v4"><?= count($reportes) ?> reporte<?= count($reportes) !== 1 ? 's' : '' ?></div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Distribuidor</th>
                        <th>Usuario</th>
                        <th>Mensaje</th>
                        <th>Imagen</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $r): ?>
                        <tr>
                            <td><?= (int)$r['IdReporte'] ?></td>
                            <td><?= h(substr((string)($r['FechaCreacion'] ?? ''), 0, 16)) ?></td>
                            <td><strong><?= h($r['DistribuidorNombre'] ?? '-') ?></strong></td>
                            <td><?= h($r['UsuarioNombre'] ?? '-') ?></td>
                            <td>
                                <span title="<?= h($r['MensajeResumen'] ?? '') ?>">
                                    <?= h(mb_strimwidth((string)($r['MensajeResumen'] ?? ''), 0, 80, '…')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($r['ImagenRuta'])): ?>
                                    <span class="badge badge-disponible">Sí</span>
                                <?php else: ?>
                                    <span class="help-text">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $estadoBadge = match((string)($r['EstadoInterno'] ?? 'Nuevo')) {
                                    'Nuevo'    => 'badge-info',
                                    'Revisado' => 'badge-pendiente',
                                    'Resuelto' => 'badge-disponible',
                                    default    => 'badge-info',
                                };
                                ?>
                                <span class="badge <?= $estadoBadge ?>"><?= h($r['EstadoInterno'] ?? 'Nuevo') ?></span>
                            </td>
                            <td>
                                <a class="btn-secondary btn-small"
                                   href="<?= panelBaseUrl('distribuidores/reporte_detalle.php?id=' . (int)$r['IdReporte']) ?>">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$reportes): ?>
                        <tr>
                            <td colspan="8" class="help-text" style="text-align:center;padding:24px 0;">
                                No hay reportes<?= $filtroEstado !== '' ? ' con estado "' . h($filtroEstado) . '"' : '' ?>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php endif; ?>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
