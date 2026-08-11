<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/helpers.php";

requiereModulo('repuestos');

$token = trim((string)($_GET['t'] ?? ''));
if ($token === '') {
    redirectWithFlash(panelBaseUrl('repuestos/cajas/index.php'), 'error', 'Caja no recibida.');
}

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
$data = $service->obtener($token, 'TokenUuid');
if (!$data) {
    redirectWithFlash(panelBaseUrl('repuestos/cajas/index.php'), 'error', 'Caja no encontrada.');
}
$caja = $data['caja'];
$qrPayload = panelAbsoluteUrl('repuestos/cajas/ver.php?t=' . urlencode((string)$caja['TokenUuid']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR caja <?= h($caja['Codigo'], '') ?> | ERP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f3ea;color:#17251d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .sheet{width:min(640px,100%);display:grid;gap:16px}
        .actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
        .btn{border:0;border-radius:14px;padding:12px 18px;background:#0f6b38;color:white;font-weight:900;cursor:pointer;text-decoration:none}
        .btn-secondary{background:white;color:#0b3d2e;border:1px solid rgba(15,61,46,.18)}
        .label{background:#fffaf2;border:2px solid #0b3d2e;border-radius:24px;padding:28px;display:grid;grid-template-columns:1fr 270px;gap:24px;align-items:center}
        .brand{font-size:2rem;font-weight:1000;color:#0b3d2e;margin-bottom:4px}
        .subbrand{color:#5f6f67;font-weight:700;margin-bottom:20px}
        .item{padding:12px 14px;border:1px solid rgba(15,61,46,.18);border-radius:12px;background:rgba(255,255,255,.52);margin-bottom:10px}
        .item span{display:block;font-size:.74rem;text-transform:uppercase;color:#5f6f67;font-weight:900;margin-bottom:4px}
        .item strong{display:block;font-size:1.08rem}
        .qr{display:grid;justify-items:center;gap:10px;padding:16px;border-radius:18px;border:1px solid rgba(15,61,46,.18);background:white}
        .qr img,.qr canvas{width:240px;height:240px;display:block}
        .qr small{color:#5f6f67;text-align:center;line-height:1.3;font-weight:700}
        .footer{grid-column:1/-1;padding-top:14px;border-top:1px solid rgba(15,61,46,.18);color:#5f6f67;font-size:.88rem;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
        @media (max-width:720px){.label{grid-template-columns:1fr}.qr{justify-self:start}}
        @media print{body{padding:0;background:white}.actions{display:none}.sheet{width:100%}.label{box-shadow:none;border-radius:18px;page-break-inside:avoid}}
    </style>
</head>
<body>
    <main class="sheet">
        <div class="actions">
            <button class="btn" type="button" id="btnImprimirEtiqueta">Imprimir QR</button>
            <a class="btn btn-secondary" href="<?= panelBaseUrl('repuestos/cajas/ver.php?t=' . urlencode((string)$caja['TokenUuid'])) ?>">Volver</a>
        </div>

        <section class="label">
            <div>
                <div class="brand">ERP</div>
                <div class="subbrand">Caja de repuestos</div>
                <div class="item"><span>Código</span><strong><?= h($caja['Codigo'], '') ?></strong></div>
                <div class="item"><span>Ubicación</span><strong><?= h($caja['Ubicacion']) ?></strong></div>
                <div class="item"><span>Estado</span><strong><?= h($caja['Estado'], '') ?></strong></div>
            </div>
            <div class="qr">
                <div id="qr-local"></div>
                <small>Escanear para abrir ficha interna autenticada</small>
            </div>
            <div class="footer">
                <span>Uso interno · URL permanente</span>
                <span><?= h(date('d/m/Y H:i'), '') ?></span>
            </div>
        </section>
    </main>
<script src="<?= h(panelBaseUrl('assets/vendor/qrcodejs/qrcode.min.js')) ?>"></script>
<script nonce="<?= cspNonce() ?>">
new QRCode(document.getElementById("qr-local"), {
    text: <?= json_encode($qrPayload, JSON_UNESCAPED_UNICODE) ?>,
    width: 260,
    height: 260,
    correctLevel: QRCode.CorrectLevel.M
});
document.getElementById('btnImprimirEtiqueta')?.addEventListener('click', function () { window.print(); });
</script>
</body>
</html>
