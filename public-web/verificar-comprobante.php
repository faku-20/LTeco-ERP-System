<?php
require_once dirname(__DIR__) . '/shared/db.php';
require_once dirname(__DIR__) . '/shared/comprobante_verificacion.php';
require_once __DIR__ . '/includes/helpers.php';

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
header('Pragma: no-cache', true);

function verificarComprobanteIpCliente(): string
{
    $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if (isTrustedProxy($remoteAddr)) {
        $forwarded = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
        ];
        foreach ($forwarded as $ip) {
            $ip = trim((string)$ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    return '0.0.0.0';
}

function verificarComprobanteRateLimit(): bool
{
    $limite = max(1, (int)configEnv('LTECO_VERIFY_RATE_LIMIT', 60));
    $ventana = max(60, (int)configEnv('LTECO_VERIFY_RATE_WINDOW_SECONDS', 3600));
    $dir = dirname(__DIR__) . '/storage/rate-limit';

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return true;
    }

    $ipHash = hash('sha256', verificarComprobanteIpCliente());
    $archivo = $dir . '/verify_' . $ipHash . '.json';
    $ahora = time();
    $eventos = [];

    $handle = @fopen($archivo, 'c+');
    if (!$handle) {
        return true;
    }

    try {
        if (flock($handle, LOCK_EX)) {
            $contenido = stream_get_contents($handle);
            $data = $contenido !== false && $contenido !== '' ? json_decode($contenido, true) : [];
            if (is_array($data)) {
                $eventos = array_values(array_filter(
                    array_map('intval', $data),
                    static fn(int $ts): bool => $ts >= ($ahora - $ventana)
                ));
            }

            if (count($eventos) >= $limite) {
                flock($handle, LOCK_UN);
                return false;
            }

            $eventos[] = $ahora;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($eventos));
            fflush($handle);
            flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }

    return true;
}

if (!verificarComprobanteRateLimit()) {
    http_response_code(429);
    header('Retry-After: 3600', true);
    exit('Demasiadas solicitudes. Intentá nuevamente más tarde.');
}

$idVenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = trim((string)($_GET['token'] ?? ''));

$venta = null;
$valido = false;

if ($idVenta > 0) {
    $selectEstadoVenta = dbTieneColumnaPublic($pdo, 'venta', 'EstadoVenta')
        ? 'EstadoVenta'
        : "'Confirmada' AS EstadoVenta";

    $stmt = $pdo->prepare("
        SELECT IdVenta, NumeroFactura, FechaVenta, Total, Moneda, {$selectEstadoVenta}
        FROM venta
        WHERE IdVenta = ?
        LIMIT 1
    ");
    $stmt->execute([$idVenta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $valido = $venta !== null && comprobanteVerificacionTokenValido($venta, $token);
}

$empresaPublica = obtenerEmpresaPublica($pdo);
$pageTitle = ($valido ? 'Comprobante válido' : 'Comprobante no válido') . ' | ' . appName();

function formatoMontoVerificacion(float $monto, string $moneda): string
{
    return (strtoupper($moneda) === 'USD' ? 'USD ' : '$ ') . number_format($monto, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= publicBaseUrl('assets/css/styles.css') ?>">
    <style>
        .verify-wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 16px;
            background: #f7f3ea;
        }
        .verify-card {
            width: min(100%, 520px);
            background: #fffdf8;
            border: 1px solid #ddd5c6;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(20, 30, 24, .10);
            color: #18241d;
        }
        .verify-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 12px;
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .verify-status--ok {
            background: #dcfce7;
            color: #166534;
        }
        .verify-status--bad {
            background: #fde2e0;
            color: #991b1b;
        }
        .verify-card h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .verify-card p {
            color: #5b574d;
            line-height: 1.5;
        }
        .verify-data {
            display: grid;
            gap: 10px;
            margin-top: 22px;
        }
        .verify-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-top: 1px solid #ebe3d5;
            padding-top: 10px;
        }
        .verify-row span {
            color: #746d5e;
        }
        .verify-row strong {
            text-align: right;
        }
    </style>
</head>
<body>
<main class="verify-wrap">
    <section class="verify-card">
        <?php if ($valido && $venta): ?>
            <div class="verify-status verify-status--ok">Comprobante válido</div>
            <h1><?= htmlspecialchars($empresaPublica['Nombre'] ?? appName(), ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Este comprobante fue emitido por el sistema y el token de verificación coincide.</p>
            <div class="verify-data">
                <div class="verify-row">
                    <span>Comprobante</span>
                    <strong><?= htmlspecialchars((string)($venta['NumeroFactura'] ?: ('Venta #' . $venta['IdVenta'])), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="verify-row">
                    <span>Fecha</span>
                    <strong><?= htmlspecialchars(date('d/m/Y', strtotime((string)$venta['FechaVenta'])), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="verify-row">
                    <span>Total</span>
                    <strong><?= htmlspecialchars(formatoMontoVerificacion((float)$venta['Total'], (string)$venta['Moneda']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="verify-row">
                    <span>Estado</span>
                    <strong><?= htmlspecialchars((string)($venta['EstadoVenta'] ?? 'Confirmada'), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>
        <?php else: ?>
            <div class="verify-status verify-status--bad">No se pudo validar</div>
            <h1>Comprobante no válido</h1>
            <p>El enlace está incompleto, el comprobante no existe o el token de verificación no coincide.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
