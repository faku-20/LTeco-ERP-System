<?php
$pageTitle = "Escanear vehículo | Lteco";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
requiereModulo("vehiculos");
require_once __DIR__ . "/../includes/helpers.php";

$rawScan = trim((string)($_GET['qr'] ?? $_GET['data'] ?? $_GET['motor'] ?? $_GET['m'] ?? ''));
$consulta = new \Lteco\Application\Vehiculo\VehiculoConsultaService(
    new \Lteco\Infrastructure\Repository\VehiculoConsultaRepository(
        \Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);
$qr = $consulta->extraerQr($rawScan);
$motor = $qr['motor'];

if ($motor !== '') {
    $vehiculo = $consulta->buscarEscaneado($rawScan);

    if (!$vehiculo) {
        redirectWithFlash(
            panelBaseUrl('vehiculos/index.php?buscar=' . urlencode($motor)),
            'error',
            'No se encontró ningún vehículo con el motor escaneado: ' . $motor
        );
    }

    registrarAuditoria(
        $pdo,
        'ESCANEAR_QR_VEHICULO',
        'Vehículos',
        'QR interno escaneado para vehículo ' . (string)$vehiculo['IdVehiculo'],
        [
            'id_vehiculo' => (string)$vehiculo['IdVehiculo'],
            'numero_motor' => (string)$vehiculo['NumeroMotor'],
            'modelo' => (string)($vehiculo['Modelo'] ?? ''),
            'estado' => (string)($vehiculo['Estado'] ?? ''),
        ]
    );

    // LTECO:SCAN_REDIRECT_SEGUN_ROL_V3
    $destinoScan = esAdmin()
        ? panelBaseUrl('vehiculos/editar.php?id=' . urlencode((string)$vehiculo['IdVehiculo']))
        : panelBaseUrl('vehiculos/qr.php?id=' . urlencode((string)$vehiculo['IdVehiculo']));

    redirectWithFlash(
        $destinoScan,
        'success',
        'Vehículo identificado por QR: ' . (string)$vehiculo['Modelo'] . ' · Motor ' . (string)$vehiculo['NumeroMotor']
    );
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="content">
    <div class="page-header">
        <div>
            <h1>Escanear vehículo</h1>
            <p>Usá la cámara para leer una etiqueta QR interna y abrir automáticamente la ficha del vehículo.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box vehicle-camera-scan">
        <div class="vehicle-camera-copy">
            <span class="section-kicker">QR interno</span>
            <h2>Escáner con cámara</h2>
            <p>Permití el acceso a la cámara, apuntá al QR de la moto y el sistema abrirá la ficha automáticamente.</p>

            <div id="scan-status" class="vehicle-scan-status">
                Esperando cámara...
            </div>

            <div class="form-actions">
                <button type="button" class="btn" id="btn-start-scan">Iniciar cámara</button>
                <button type="button" class="btn-secondary" id="btn-stop-scan" disabled>Detener</button>
            </div>
            <div class="form-group u-mt-1">
    <label>Entrada manual / lector externo</label>
    <input type="text" id="manual-scan-input" placeholder="Pegá URL del QR o MOTOR-0001">
    <button type="button" class="btn-secondary u-mt-1" id="manual-scan-btn">Abrir vehículo</button>
</div>
        </div>

        <div class="vehicle-camera-reader-wrap">
            <div id="qr-reader"></div>
        </div>
    </section>
</main>

<script src="<?= panelBaseUrl('assets/vendor/html5-qrcode/html5-qrcode.min.js') ?>"></script>
<script nonce="<?= cspNonce() ?>">
document.addEventListener("DOMContentLoaded", () => {
    const readerEl = document.getElementById("qr-reader");
    const statusEl = document.getElementById("scan-status");
    const startBtn = document.getElementById("btn-start-scan");
    const stopBtn = document.getElementById("btn-stop-scan");
    const manualInput = document.getElementById("manual-scan-input");
    const manualBtn = document.getElementById("manual-scan-btn");
    const scanBase = <?= json_encode(panelAbsoluteUrl('vehiculos/scan.php')) ?>;

    let scanner = null;
    let running = false;
    let alreadyRedirecting = false;

    function setStatus(message, type = "info") {
        statusEl.textContent = message;
        statusEl.classList.remove("is-ok", "is-error", "is-info");
        statusEl.classList.add(`is-${type}`);
    }

    function extraerPayload(texto) {
        let valor = String(texto || "").trim();

        if (!valor) return "";

        try {
            const url = new URL(valor);
            valor = url.searchParams.get("qr") || url.searchParams.get("data") || url.searchParams.get("motor") || url.searchParams.get("m") || valor;
        } catch (e) {
            // No era URL completa.
        }

        return valor.trim();
    }

    async function detenerCamara() {
        if (!scanner || !running) return;

        try {
            await scanner.stop();
            await scanner.clear();
        } catch (e) {
            console.warn(e);
        }

        running = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        setStatus("Cámara detenida.", "info");
    }

    async function iniciarCamara() {
        if (running) return;

        if (typeof Html5Qrcode === "undefined") {
            setStatus("No se pudo cargar el lector QR. Verificá conexión a internet.", "error");
            return;
        }

        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus("La cámara requiere HTTPS o localhost. Abrí el panel con HTTPS y revisá permisos del navegador.", "error");
            return;
        }

        scanner = new Html5Qrcode("qr-reader");

        const config = {
            fps: 10,
            qrbox: { width: 260, height: 260 },
            rememberLastUsedCamera: true,
            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
        };

        try {
            startBtn.disabled = true;
            stopBtn.disabled = false;
            setStatus("Buscando cámaras...", "info");

            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                throw new Error("No se encontraron cámaras");
            }

            let selectedCameraId = cameras[0].id;

            const trasera = cameras.find(cam => {
                const label = String(cam.label || "").toLowerCase();
                return label.includes("back") || label.includes("rear") || label.includes("environment") || label.includes("trasera");
            });

            if (trasera) {
                selectedCameraId = trasera.id;
            }

            setStatus("Iniciando cámara...", "info");

            await scanner.start(
                selectedCameraId,
                config,
                async (decodedText) => {
                    if (alreadyRedirecting) return;

                    const payload = extraerPayload(decodedText);

                    if (!payload) {
                        setStatus("QR leído, pero no contiene datos válidos.", "error");
                        return;
                    }

                    alreadyRedirecting = true;
                    setStatus("QR detectado. Abriendo vehículo...", "ok");

                    try {
                        await detenerCamara();
                    } catch (e) {
                        console.warn(e);
                    }

                    window.location.href = scanBase + "?qr=" + encodeURIComponent(payload);
                },
                () => {}
            );

            running = true;
            const cameraLabel = cameras.find(cam => cam.id === selectedCameraId)?.label || "cámara disponible";
            setStatus("Cámara activa: " + cameraLabel + ". Apuntá al QR de la moto.", "ok");
        } catch (error) {
            console.error("ERROR QR:", error);
            running = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;

            const name = error && error.name ? error.name : "";
            let detail = error && error.message ? error.message : String(error || "error desconocido");

            if (name === "NotAllowedError" || name === "PermissionDeniedError") {
                detail = "Permiso denegado. Tocá el icono de ajustes junto a la URL, entrá a permisos del sitio, permití la cámara y volvé a escanear.";
            } else if (name === "NotFoundError" || name === "DevicesNotFoundError") {
                detail = "No se encontró cámara disponible en esta computadora.";
            } else if (name === "NotReadableError" || name === "TrackStartError") {
                detail = "La cámara está ocupada por otra aplicación o bloqueada por el sistema.";
            }

            setStatus("No se pudo iniciar la cámara: " + detail, "error");
        }
    }

    startBtn.addEventListener("click", () => { setStatus("Botón detectado. Iniciando...", "info"); iniciarCamara(); });
    stopBtn.addEventListener("click", detenerCamara);

    if (manualBtn && manualInput) {
        manualBtn.addEventListener("click", () => {
            const payload = extraerPayload(manualInput.value);

            if (!payload) {
                setStatus("Ingresá un número de motor o pegá la URL del QR.", "error");
                return;
            }

            window.location.href = scanBase + "?qr=" + encodeURIComponent(payload);
        });

        manualInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                manualBtn.click();
            }
        });
    }

    setStatus("Listo. Tocá Iniciar cámara o usá la entrada manual.", "info");
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
