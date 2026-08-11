<?php
// Handler AJAX: crea un reporte de problema del distribuidor.
// Solo acepta POST de distribuidores autenticados.
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_common.php';

function reporteProblemaJson(bool $ok, string $mensaje = '', int $status = 200): never
{
    http_response_code($status);
    $payload = ['ok' => $ok];
    if ($mensaje !== '') {
        $payload[$ok ? 'message' : 'error'] = $mensaje;
    }
    echo json_encode($payload);
    exit;
}

try {
    requirePost();
    verifyCsrfOrFail();
} catch (Throwable $e) {
    $status = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 400 : 405;
    reporteProblemaJson(false, $e->getMessage(), $status);
}

$reportes = new \Lteco\Application\Distribuidor\DistribuidorReporteService(
    new \Lteco\Infrastructure\Repository\DistribuidorReporteRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

if (!$reportes->estaDisponible()) {
    reporteProblemaJson(false, 'La función de reportes todavía no está habilitada.', 503);
}

$idDistribuidor = requiereDistribuidorPanel();
$usuario = usuarioActual();
$idUsuario = (int)($usuario['IdUsuario'] ?? 0);

if ($idDistribuidor <= 0 || $idUsuario <= 0) {
    reporteProblemaJson(false, 'Sesión inválida.', 403);
}

$mensaje = (string)($_POST['mensaje'] ?? '');
try {
    $mensaje = $reportes->validarMensaje($mensaje);
} catch (InvalidArgumentException $e) {
    reporteProblemaJson(false, $e->getMessage(), 422);
}

$imagenRuta = null;

$archivo = $_FILES['imagen'] ?? null;
$hayImagen = $archivo && isset($archivo['error']) && (int)$archivo['error'] !== UPLOAD_ERR_NO_FILE;

if ($hayImagen) {
    if ((int)$archivo['error'] !== UPLOAD_ERR_OK) {
        reporteProblemaJson(false, 'Error al subir la imagen.', 422);
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int)$archivo['size'] <= 0 || (int)$archivo['size'] > $maxBytes) {
        reporteProblemaJson(false, 'La imagen no puede superar 5 MB.', 422);
    }

    if (!is_uploaded_file($archivo['tmp_name'])) {
        reporteProblemaJson(false, 'La imagen no llegó correctamente.', 422);
    }

    $mime = @mime_content_type($archivo['tmp_name']) ?: '';
    $extensionesPermitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensionesPermitidas[$mime])) {
        reporteProblemaJson(false, 'Solo se aceptan imágenes JPG, PNG o WEBP.', 422);
    }

    $dims = @getimagesize($archivo['tmp_name']);
    if ($dims === false || empty($dims[0]) || empty($dims[1])) {
        reporteProblemaJson(false, 'El archivo no es una imagen válida.', 422);
    }

    $ext = $extensionesPermitidas[$mime];
    $nombreArchivo = 'rpt_' . $idDistribuidor . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dirDestino = dirname(__DIR__) . '/uploads/reportes_distribuidor';

    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    $rutaAbs = $dirDestino . '/' . $nombreArchivo;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaAbs)) {
        reporteProblemaJson(false, 'No se pudo guardar la imagen.', 500);
    }
    @chmod($rutaAbs, 0644);
    $imagenRuta = 'uploads/reportes_distribuidor/' . $nombreArchivo;
}

try {
    $idReporte = $reportes->crearReporte($idDistribuidor, $idUsuario, $mensaje, $imagenRuta);

    registrarAuditoria($pdo, 'REPORTE_PROBLEMA_CREAR', 'Distribuidores',
        'Reporte #' . $idReporte . ' creado por distribuidor #' . $idDistribuidor, [
            'id_reporte' => $idReporte,
            'id_distribuidor' => $idDistribuidor,
            'id_usuario' => $idUsuario,
            'tiene_imagen' => $imagenRuta !== null,
        ]);

    reporteProblemaJson(true);
} catch (Throwable $e) {
    logPanelError('reportar_problema', $e);
    reporteProblemaJson(false, 'Error interno. Intentá de nuevo.', 500);
}
