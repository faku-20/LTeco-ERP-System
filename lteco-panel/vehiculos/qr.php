<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";

requiereModulo("vehiculos");

$id = trim((string)($_GET['id'] ?? ''));

if ($id === '') {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'ID de vehículo no recibido.');
}
requierePuedeVerRegistro('vehiculo', $id);

$consulta = new \Lteco\Application\Vehiculo\VehiculoConsultaService(
    new \Lteco\Infrastructure\Repository\VehiculoConsultaRepository(
        \Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);
$vehiculo = $consulta->paraQr($id);

if (!$vehiculo) {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'Vehículo no encontrado.');
}

$numeroMotor = trim((string)($vehiculo['NumeroMotor'] ?? ''));
$qrPayload = 'LTECO|' . trim((string)$vehiculo['IdVehiculo']) . '|' . $numeroMotor;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR vehículo <?= h($vehiculo['IdVehiculo']) ?> | Ltecobike</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --green: #0f6b38;
            --green-dark: #0b3d2e;
            --ink: #17251d;
            --muted: #5f6f67;
            --border: rgba(15, 61, 46, .18);
            --bg: #f7f3ea;
            --card: #fffaf2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .sheet {
            width: min(720px, 100%);
            display: grid;
            gap: 16px;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            border-radius: 14px;
            padding: 12px 18px;
            background: var(--green);
            color: white;
            font-weight: 900;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-secondary {
            background: white;
            color: var(--green-dark);
            border: 1px solid var(--border);
        }

        .label {
            background: var(--card);
            border: 2px solid var(--green-dark);
            border-radius: 28px;
            padding: 28px;
            display: grid;
            grid-template-columns: 1fr 270px;
            gap: 24px;
            align-items: center;
            box-shadow: 0 24px 70px rgba(15, 61, 46, .15);
        }

        .brand {
            font-size: 2rem;
            font-weight: 1000;
            color: var(--green-dark);
            margin-bottom: 4px;
        }

        .subbrand {
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 20px;
        }

        .data {
            display: grid;
            gap: 10px;
        }

        .item {
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: rgba(255,255,255,.52);
        }

        .item span {
            display: block;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            font-weight: 900;
            margin-bottom: 4px;
        }

        .item strong {
            display: block;
            font-size: 1.05rem;
        }

        .qr {
            display: grid;
            justify-items: center;
            gap: 10px;
            padding: 16px;
            border-radius: 22px;
            border: 1px solid var(--border);
            background: white;
        }

        .qr img {
            width: 240px;
            height: 240px;
            display: block;
        }

        .qr small {
            color: var(--muted);
            text-align: center;
            line-height: 1.3;
            font-weight: 700;
        }

        .footer {
            grid-column: 1 / -1;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .88rem;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 720px) {
            .label {
                grid-template-columns: 1fr;
            }

            .qr {
                justify-self: start;
            }
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .actions {
                display: none;
            }

            .sheet {
                width: 100%;
            }

            .label {
                box-shadow: none;
                border-radius: 18px;
                page-break-inside: avoid;
            }
        }
    
.qr-local{
    display:inline-block;
}
.qr-local img,
.qr-local canvas{
    display:block;
}
</style>
</head>
<body>
    <main class="sheet">
        <div class="actions">
            <button class="btn" type="button" id="btnImprimirEtiqueta">Imprimir etiqueta</button>
            <!-- LTECO:QR_VOLVER_SEGUN_ROL_V3 -->
            <a class="btn btn-secondary" href="<?= h(panelBaseUrl('vehiculos/index.php')) ?>">Volver</a>
        </div>

        <section class="label">
            <div>
                <div class="brand">Ltecobike</div>
                <div class="subbrand">Identificación interna de vehículo</div>

                <div class="data">
                    <div class="item">
                        <span>ID vehículo</span>
                        <strong><?= h($vehiculo['IdVehiculo']) ?></strong>
                    </div>
                    <div class="item">
                        <span>Modelo</span>
                        <strong><?= h($vehiculo['Modelo'] ?: $vehiculo['Nombre']) ?></strong>
                    </div>
                    <div class="item">
                        <span>Número de motor</span>
                        <strong><?= h($vehiculo['NumeroMotor']) ?></strong>
                    </div>
                    <div class="item">
                        <span>Color</span>
                        <strong><?= h($vehiculo['Color'] ?: '-') ?></strong>
                    </div>
                    <div class="item">
                        <span>Estado</span>
                        <strong><?= h($vehiculo['Estado']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="qr">
                <div id="qr-local" class="qr-local"></div>
                <small>Escanear para abrir ficha interna por número de motor</small>
            </div>

            <div class="footer">
                <span>Uso interno · No es comprobante fiscal</span>
                <span><?= h(date('d/m/Y H:i')) ?></span>
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

document.getElementById('btnImprimirEtiqueta')?.addEventListener('click', function () {
    window.print();
});
</script>
</body>
</html>
