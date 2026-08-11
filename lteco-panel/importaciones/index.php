<?php
$pageTitle = "Importaciones | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Importacion\ImportacionConsultaService(
    new \Lteco\Infrastructure\Repository\ImportacionConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$importaciones = $service->listar();

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Importaciones</h1>
            <p class="subtle">Gesti&oacute;n de contenedores y tipo de cambio hist&oacute;rico.</p>
        </div>
        <a class="btn" href="<?= panelBaseUrl('importaciones/crear.php') ?>">+ Nueva importaci&oacute;n</a>
    </div>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Importaciones</h2>
            </div>
            <div class="result-pill-v4"><?= count($importaciones) ?> resultados</div>
        </div>
    </section>

    <div class="table-wrap">
<table class="table">
            <thead>
                <tr>
                    <th>N&uacute;mero</th>
                    <th>TC USD</th>
                    <th>Fecha</th>
                    <th>Descripci&oacute;n</th>
                    <th>Veh&iacute;culos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($importaciones): ?>
                <?php foreach ($importaciones as $imp): ?>
                    <tr>
                        <td><strong>#<?= (int)$imp['Numero'] ?></strong></td>
                        <td>USD <?= number_format((float)$imp['TipoCambioUSD'], 2, ',', '.') ?></td>
                        <td><?= $imp['Fecha'] ? date('d/m/Y', strtotime($imp['Fecha'])) : '-' ?></td>
                        <td><?= h($imp['Descripcion'] ?? '-') ?></td>
                        <td><?= (int)$imp['TotalVehiculos'] ?></td>
                        <td><span class="badge <?= $imp['Activa'] ? 'badge--success' : 'badge--muted' ?>"><?= $imp['Activa'] ? 'Activa' : 'Inactiva' ?></span></td>
                        <td>
                            <a class="btn-secondary" href="<?= panelBaseUrl('importaciones/editar.php?id=' . (int)$imp['IdImportacion']) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No hay importaciones registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
