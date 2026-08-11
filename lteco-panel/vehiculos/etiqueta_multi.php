<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereModulo("vehiculos");
require_once __DIR__ . "/../includes/helpers.php";

$idsRaw = trim((string)($_GET['ids'] ?? ''));
if ($idsRaw === '') {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'No se recibieron vehiculos.');
}

$ids = preg_split('/[\s,;]+/', $idsRaw) ?: [];
$ids = array_values(array_unique(array_filter(array_map('trim', $ids), fn($id) => $id !== '')));
if (empty($ids)) {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'IDs invalidos.');
}

$copias = enteroNoNegativo($_GET['copias'] ?? 2, 2);
if ($copias < 1 || $copias > 2) {
    $copias = 2;
}

$consulta = new \Lteco\Application\Vehiculo\VehiculoConsultaService(
    new \Lteco\Infrastructure\Repository\VehiculoConsultaRepository(
        \Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);
$vehiculos = $consulta->paraEtiquetas($ids);

if (empty($vehiculos)) {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'No se encontraron vehiculos.');
}

$etiquetas = [];
foreach ($vehiculos as $vehiculo) {
    for ($i = 0; $i < $copias; $i++) {
        $etiquetas[] = $vehiculo;
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Impresion QR multiple</title>
<script src="<?= panelBaseUrl('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --ink: #151f1a;
    --muted: #66736b;
    --line: #cfd8d2;
    --line-strong: #8fa196;
    --brand: #0f6b38;
    --brand-dark: #073d25;
    --paper: #fffdf8;
    --screen-bg: #ece7dc;
}

@page {
    size: A4 portrait;
    margin: 8mm;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--screen-bg);
    color: var(--ink);
    min-height: 100vh;
}

.pagina {
    width: 193mm;
    height: 277mm;
    margin: 18px auto;
    padding: 3mm;
    background: #fff;
    box-shadow: 0 18px 50px rgba(21, 31, 26, .18);
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: repeat(4, 1fr);
    gap: 3mm;
}

.pagina + .pagina {
    page-break-before: always;
    break-before: page;
}

.etiqueta {
    position: relative;
    overflow: hidden;
    border: 1px dashed var(--line-strong);
    border-radius: 8px;
    background:
        linear-gradient(180deg, rgba(15, 107, 56, .06), rgba(15, 107, 56, 0) 34%),
        var(--paper);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 6mm 3mm 3mm;
    gap: 1.5mm;
    break-inside: avoid;
}

.etiqueta::before {
    content: "LTECOBIKE";
    position: absolute;
    top: 4mm;
    left: 5mm;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: .14em;
    color: var(--brand-dark);
}

.etiqueta::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 18mm;
    height: 18mm;
    border-left: 1px solid rgba(15, 107, 56, .18);
    border-bottom: 1px solid rgba(15, 107, 56, .18);
    background: rgba(15, 107, 56, .08);
}

.etiqueta__qr {
    width: 96px;
    height: 96px;
    padding: 4px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    flex-shrink: 0;
}

.etiqueta__qr img,
.etiqueta__qr canvas {
    display: block;
}

.etiqueta__id {
    width: 100%;
    text-align: center;
    font-size: 16px;
    font-weight: 900;
    letter-spacing: .04em;
    color: var(--brand-dark);
}

.etiqueta__id::before {
    content: "ID ";
    font-size: 10px;
    letter-spacing: .08em;
    color: var(--muted);
}

.etiqueta__motor {
    width: 100%;
    max-width: 64mm;
    padding: 2mm 3mm;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #fff;
    text-align: center;
    font-size: 13px;
    color: var(--ink);
    font-weight: 900;
}

.etiqueta__motor::before {
    content: "Motor ";
    display: block;
    margin-bottom: 1mm;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: var(--muted);
}

.etiqueta__modelo {
    max-width: 68mm;
    color: var(--muted);
    font-size: 10.5px;
    line-height: 1.25;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.no-print {
    position: sticky;
    top: 0;
    z-index: 5;
    text-align: center;
    padding: 14px;
    background: rgba(255, 253, 248, .94);
    border-bottom: 1px solid var(--line);
    box-shadow: 0 10px 30px rgba(21, 31, 26, .08);
    backdrop-filter: blur(10px);
}

.no-print button {
    min-height: 38px;
    padding: 0 18px;
    border: 1px solid var(--brand-dark);
    border-radius: 8px;
    background: var(--brand);
    color: #fff;
    font-size: 14px;
    font-weight: 900;
    cursor: pointer;
    margin: 0 4px;
}

.no-print button + button {
    background: #fff;
    color: var(--brand-dark);
}

.no-print p {
    margin-top: 8px;
    font-size: 12px;
    color: var(--muted);
}

@media print {
    html,
    body {
        background: #fff;
        width: 100%;
        min-height: 0;
        height: auto;
        overflow: visible;
    }

    .no-print {
        display: none;
    }

    .pagina {
        width: 193mm;
        height: 277mm;
        margin: 0;
        padding: 3mm;
        box-shadow: none;
        background: #fff;
        page-break-after: auto;
        break-after: auto;
    }

    .etiqueta {
        background: #fff;
        border-color: #9b9b9b;
    }

    .etiqueta::after {
        display: none;
    }
}
</style>
</head>
<body>

<div class="no-print">
    <button type="button" id="btnImprimirEtiquetas">Imprimir</button>
    <button type="button" id="btnCerrarEtiquetas">Cerrar</button>
    <p style="margin-top:8px;font-size:12px;color:#666;">
        <?= count($vehiculos) ?> vehiculo(s) &middot; <?= $copias ?> QR por ID &middot; <?= count($etiquetas) ?> etiqueta(s)
    </p>
</div>

<?php $chunks = array_chunk($etiquetas, 8); ?>
<?php $qrCounter = 0; ?>
<?php foreach ($chunks as $grupo): ?>
<div class="pagina">
    <?php foreach ($grupo as $v): ?>
        <?php
        $qrCounter++;
        $qrData = 'LTECO|' . $v['IdVehiculo'] . '|' . $v['NumeroMotor'];
        $qrId   = 'qr_' . preg_replace('/[^a-zA-Z0-9]/', '_', (string)$v['IdVehiculo']) . '_' . $qrCounter;
        ?>
        <div class="etiqueta">
            <div class="etiqueta__qr" id="<?= htmlspecialchars($qrId) ?>"></div>
            <div class="etiqueta__id"><?= htmlspecialchars((string)$v['IdVehiculo']) ?></div>
            <div class="etiqueta__motor"><?= htmlspecialchars((string)$v['NumeroMotor']) ?></div>
            <div class="etiqueta__modelo"><?= htmlspecialchars((string)$v['Modelo']) ?> &middot; <?= htmlspecialchars((string)$v['Color']) ?></div>
            <script nonce="<?= cspNonce() ?>">
            new QRCode(document.getElementById("<?= htmlspecialchars($qrId) ?>"), {
                text: <?= json_encode($qrData, JSON_UNESCAPED_UNICODE) ?>,
                width: 88, height: 88,
                correctLevel: QRCode.CorrectLevel.M
            });
            </script>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<script nonce="<?= cspNonce() ?>">
document.getElementById('btnImprimirEtiquetas')?.addEventListener('click', function () {
    window.print();
});

document.getElementById('btnCerrarEtiquetas')?.addEventListener('click', function () {
    window.close();
});

window.addEventListener('load', function () {
    window.setTimeout(function () {
        window.print();
    }, 450);
});
</script>
</body>
</html>
