<?php
$pageTitle = "Estado de cuenta distribuidor | Lteco";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

$idDistribuidor = esDistribuidor() ? requiereDistribuidorPanel() : 0;
if (!esDistribuidor()) {
    requiereAdmin();
    $idDistribuidor = (int)($_GET['id'] ?? 0);
}

$cuentaService = new \Lteco\Application\Distribuidor\DistribuidorCuentaService(
    new \Lteco\Infrastructure\Repository\DistribuidorCuentaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$idDistribuidor = $cuentaService->resolverDistribuidorId($idDistribuidor);

if ($idDistribuidor <= 0) {
    redirectWithFlash(panelBaseUrl('distribuidores/index.php'), 'error', 'No hay distribuidor seleccionado.');
}

if (esDistribuidor() && !esDistribuidorPropietario(usuarioActual() ?? [], ['IdDistribuidor' => $idDistribuidor])) {
    denegarAcceso('No podés ver el estado de cuenta de otro distribuidor.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();
        if (!esAdmin()) {
            denegarAcceso('Solo administración puede cambiar estados de comisiones.');
        }

        $idComision = (int)($_POST['id_comision'] ?? 0);
        $estado = $cuentaService->actualizarComision(
            $idComision,
            $idDistribuidor,
            (string)($_POST['accion'] ?? '')
        );
        registrarAuditoria($pdo, 'COMISION_DISTRIBUIDOR_' . strtoupper($estado), 'Distribuidores', 'Comisión actualizada', [
            'id_comision' => $idComision,
            'id_distribuidor' => $idDistribuidor,
            'estado' => $estado,
        ]);
        redirectWithFlash(panelBaseUrl('distribuidores/estado_cuenta.php?id=' . urlencode((string)$idDistribuidor)), 'success', 'Comisión actualizada.');
    } catch (Throwable $e) {
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo actualizar la comisión.'));
    }
}

$cuenta = $cuentaService->cargarCuenta(
    $idDistribuidor,
    (string)($_GET['desde'] ?? date('Y-m-01')),
    (string)($_GET['hasta'] ?? date('Y-m-d')),
    esAdmin()
);
$desde = $cuenta['desde'];
$hasta = $cuenta['hasta'];
$distribuidor = $cuenta['distribuidor'];
$distribuidores = $cuenta['distribuidores'];
$comisiones = $cuenta['comisiones'];
$resumen = $cuenta['resumen'];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Estado de cuenta</h1>
            <p class="subtle"><?= h($distribuidor['Nombre'] ?? 'Distribuidor', '') ?> · comisiones y pagos.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <form method="GET" class="section-box">
        <?php if (esAdmin()): ?>
            <div class="form-group">
                <label>Distribuidor</label>
                <select name="id">
                    <?php foreach ($distribuidores as $d): ?>
                        <option value="<?= (int)$d['IdDistribuidor'] ?>" <?= $idDistribuidor === (int)$d['IdDistribuidor'] ? 'selected' : '' ?>><?= h($d['Nombre'], '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group"><label>Desde</label><input type="date" name="desde" value="<?= h($desde, '') ?>"></div>
            <div class="form-group"><label>Hasta</label><input type="date" name="hasta" value="<?= h($hasta, '') ?>"></div>
        </div>
        <button class="btn-secondary" type="submit">Filtrar</button>
        <button class="btn-secondary" type="button" data-print-page>Imprimir</button>
    </form>

    <div class="cards">
        <div class="card"><small>Pendiente</small><strong><?= formatearMonto($resumen['Pendiente'], 'UYU') ?></strong></div>
        <div class="card"><small>Aprobada</small><strong><?= formatearMonto($resumen['Aprobada'], 'UYU') ?></strong></div>
        <div class="card"><small>Pagada</small><strong><?= formatearMonto($resumen['Pagada'], 'UYU') ?></strong></div>
        <div class="card"><small>Anulada</small><strong><?= formatearMonto($resumen['Anulada'], 'UYU') ?></strong></div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Venta</th><th>Cliente</th><th>Base</th><th>%</th><th>Comisión</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($comisiones as $c): ?>
                <tr>
                    <td><?= h(substr((string)$c['FechaGenerada'], 0, 16), '') ?></td>
                    <td>#<?= (int)$c['IdVenta'] ?></td>
                    <td><?= h($c['NombreApellido'] ?? '-', '') ?></td>
                    <td><?= formatearMonto((float)$c['BaseComision'], 'UYU') ?></td>
                    <td><?= number_format((float)$c['Porcentaje'], 2) ?>%</td>
                    <td><strong><?= formatearMonto((float)$c['Monto'], 'UYU') ?></strong></td>
                    <td><?= h($c['Estado'], '') ?></td>
                    <td>
                        <?php if (esAdmin() && $c['Estado'] !== 'Pagada' && $c['Estado'] !== 'Anulada'): ?>
                            <div class="actions-row">
                                <?php foreach (['aprobar' => 'Aprobar', 'pagar' => 'Pagar', 'anular' => 'Anular'] as $accion => $label): ?>
                                    <form method="POST">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="id_comision" value="<?= (int)$c['IdComision'] ?>">
                                        <input type="hidden" name="accion" value="<?= h($accion, '') ?>">
                                        <button class="btn-secondary btn-small" type="submit"><?= h($label, '') ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="subtle">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$comisiones): ?>
                <tr><td colspan="8" class="help-text">No hay movimientos para el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
