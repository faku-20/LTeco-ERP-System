<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/whatsapp.php";

try {
    requirePost();
    verifyCsrfOrFail();
} catch (Throwable $e) {
    redirectWithFlash(panelBaseUrl('configuracion/index.php'), 'error', 'La sesión del formulario venció o el método no es válido. Volvé a intentar.');
}

$esSuperadminConfig = function_exists('esSuperadmin') && esSuperadmin();

$nombreEmpresa = normalizarTextoHumano($_POST['nombre_empresa'] ?? appName(), 120);
$rutEmpresa = normalizarTextoHumano($_POST['rut'] ?? defaultEmpresaRut(), 40);
$razonSocial = limpiarTextoOpcional(normalizarTextoHumano($_POST['razon_social'] ?? '', 160));
$correo = limpiarTextoOpcional($_POST['correo'] ?? null);
$telefono = normalizarTelefono($_POST['telefono'] ?? null);
$whatsApp = $telefono;
$direccion = limpiarTextoOpcional(normalizarTextoHumano($_POST['direccion'] ?? '', 255));
$descripcion = limpiarTextoOpcional($_POST['descripcion'] ?? null);
$sitioWeb = limpiarTextoOpcional(normalizarTextoHumano($_POST['sitio_web'] ?? '', 255));
$logo = limpiarTextoOpcional(normalizarTextoHumano($_POST['logo'] ?? '', 500));
$favicon = limpiarTextoOpcional(normalizarTextoHumano($_POST['favicon'] ?? '', 500));
$colorPrimario = normalizarTextoHumano($_POST['color_primario'] ?? '#0f6b38', 7);
$colorSecundario = normalizarTextoHumano($_POST['color_secundario'] ?? '#151f1a', 7);
$pieDocumentos = limpiarTextoOpcional($_POST['pie_documentos'] ?? null);
$poweredByEnabled = isset($_POST['powered_by_enabled']) ? 1 : 0;
$rutEmpresa = $rutEmpresa !== '' ? $rutEmpresa : defaultEmpresaRut();

// WhatsApp Cloud API: solo Superadmin puede modificar estos valores.
$waEnabled  = ($esSuperadminConfig && isset($_POST['wa_enabled'])) ? (int)(bool)(int)$_POST['wa_enabled'] : null;
$waPhoneId  = $esSuperadminConfig ? limpiarTextoOpcional($_POST['wa_phone_id'] ?? null) : null;
$waToken    = ($esSuperadminConfig && isset($_POST['wa_token']) && trim($_POST['wa_token']) !== '') ? trim($_POST['wa_token']) : null;
$waTplVenta = $esSuperadminConfig ? limpiarTextoOpcional($_POST['wa_tpl_venta'] ?? null) : null;
$waTplSvc   = $esSuperadminConfig ? limpiarTextoOpcional($_POST['wa_tpl_service'] ?? null) : null;

if ($correo !== null && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    redirectWithFlash(panelBaseUrl('configuracion/index.php'), 'error', 'El correo de la empresa no tiene un formato válido.');
}

if ($sitioWeb !== null && !filter_var($sitioWeb, FILTER_VALIDATE_URL)) {
    redirectWithFlash(panelBaseUrl('configuracion/index.php'), 'error', 'El sitio web de la empresa no tiene un formato válido.');
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorPrimario)) {
    $colorPrimario = '#0f6b38';
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorSecundario)) {
    $colorSecundario = '#151f1a';
}

$configService = new \Lteco\Application\Configuracion\ConfiguracionService(
    new \Lteco\Infrastructure\Repository\ConfiguracionRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

try {
    $pdo->beginTransaction();

    $config = $configService->obtenerConfiguracion() ?? false;

    if ($esSuperadminConfig) {
        // Asegurar columnas de WhatsApp antes de intentar guardarlas
        whatsappEnsureColumnas($pdo);
    }

    if ($config && !empty($config['IdConfiguracion'])) {
        $camposWa = [];
        // WhatsApp
        if ($waEnabled !== null) {
            $camposWa['WaEnabled'] = $waEnabled;
        }
        if ($waPhoneId !== null) {
            $camposWa['WaPhoneId'] = mb_substr($waPhoneId, 0, 80);
        }
        if ($waToken !== null) {
            $camposWa['WaToken'] = $waToken;
        }
        if ($waTplVenta !== null) {
            $camposWa['WaTplVenta'] = mb_substr($waTplVenta, 0, 100);
        }
        if ($waTplSvc !== null) {
            $camposWa['WaTplService'] = mb_substr($waTplSvc, 0, 100);
        }
        $configService->actualizarWhatsapp((int)$config['IdConfiguracion'], $camposWa);
    } else {
        $configService->crearConfiguracionDefault();
    }

    $empresa = $configService->obtenerEmpresa() ?? false;

    $empresaColumns = [
        'Nombre' => $nombreEmpresa !== '' ? $nombreEmpresa : appName(),
        'Correo' => $correo,
        'Telefono' => $telefono,
        'WhatsApp' => $whatsApp,
        'Descripcion' => $descripcion,
        'Direccion' => $direccion,
        'RazonSocial' => $razonSocial,
        'Logo' => $logo,
        'Favicon' => $favicon,
        'ColorPrimario' => strtolower($colorPrimario),
        'ColorSecundario' => strtolower($colorSecundario),
        'SitioWeb' => $sitioWeb,
        'PieDocumentos' => $pieDocumentos,
        'PoweredByEnabled' => $poweredByEnabled,
    ];

    $columnasDisponibles = $configService->columnasEmpresa();
    if ($columnasDisponibles) {
        $empresaColumns = array_intersect_key($empresaColumns, array_flip($columnasDisponibles));
    }

    if ($empresa) {
        $rutAnterior = (string)($empresa['RUT'] ?? '');
        if ($rutEmpresa !== '' && $rutEmpresa !== $rutAnterior) {
            $empresaColumns = ['RUT' => $rutEmpresa] + $empresaColumns;
        }

        $configService->actualizarEmpresa($empresaColumns, (string)$empresa['RUT']);

        if ($rutEmpresa !== '' && $rutEmpresa !== $rutAnterior) {
            $configService->propagarRutProducto($rutEmpresa, $rutAnterior);
        }
    } else {
        $empresaColumns = ['RUT' => $rutEmpresa] + $empresaColumns;
        $configService->insertarEmpresa($empresaColumns);
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    registrarAuditoria($pdo, 'ACTUALIZAR_CONFIGURACION', 'Configuración', 'Configuración general actualizada desde endpoint guardar.php.', ['seccion' => 'configuracion_operativa']);
    redirectWithFlash(panelBaseUrl('configuracion/index.php'), 'success', 'Configuración actualizada correctamente.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logPanelError('configuracion_guardar', $e);
    redirectWithFlash(panelBaseUrl('configuracion/index.php'), 'error', mensajeErrorSeguro($e, 'No se pudo actualizar la configuración.'));
}
