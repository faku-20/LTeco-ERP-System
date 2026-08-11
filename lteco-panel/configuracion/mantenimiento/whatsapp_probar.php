<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/whatsapp.php';

requiereSuperadmin();

whatsappEnsureColumnas($pdo);
whatsappEnsureTabla($pdo);

try {
    requirePost();
    verifyCsrfOrFail();

    $cfg = whatsappObtenerConfig($pdo);

    if (!$cfg['enabled']) {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'WhatsApp está desactivado. Activalo en Configuración primero.'
        );
    }

    if (empty($cfg['phone_id']) || empty($cfg['token'])) {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'Falta Phone Number ID o Access Token. Completá la configuración de WhatsApp.'
        );
    }

    // Obtener el número de WhatsApp de la empresa como destinatario de prueba
    $empresa = (new \Lteco\Application\Configuracion\ConfiguracionService(
        new \Lteco\Infrastructure\Repository\ConfiguracionRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    ))->obtenerEmpresaContacto();

    $telefonoPruebaIngresado = trim((string)($_POST['telefono_prueba'] ?? ''));
    $telefonoPrueba = $telefonoPruebaIngresado;
    if ($telefonoPrueba === '') {
        $telefonoPrueba = $empresa['WhatsApp'] ?? $empresa['Telefono'] ?? '';
    }

    if ($telefonoPrueba === '') {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'No hay número de teléfono configurado. Ingresá uno en la empresa o en el formulario.'
        );
    }

    $telefonoNormalizado = whatsappFormatearTelefono($telefonoPrueba);
    $telefonoConectado = whatsappFormatearTelefono((string)configEnv('LTECO_DEFAULT_WHATSAPP', ''));
    if ($telefonoConectado !== null && $telefonoNormalizado === $telefonoConectado) {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'Ingresá un teléfono destino distinto al número conectado a WhatsApp Business para probar el envío.'
        );
    }

    // Template: prioridad al campo del formulario, luego al guardado en config
    $templatePost = trim((string)($_POST['template_prueba'] ?? ''));
    $template = $templatePost !== '' ? $templatePost : $cfg['tpl_venta'];
    if (empty($template)) {
        $template = $cfg['tpl_service'];
    }

    if (empty($template)) {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'Ingresá el nombre del template en el formulario de prueba.'
        );
    }

    // hello_world no tiene parámetros; venta_confirmada_v2 espera 7 variables.
    $params = whatsappTemplateEsHelloWorld($template)
        ? []
        : [
            'Cliente de prueba',
            'TEST-001',
            date('d/m/Y', strtotime('+1 year')),
            date('d/m/Y', strtotime('+3 months')),
            date('d/m/Y', strtotime('+6 months')),
            date('d/m/Y', strtotime('+9 months')),
            date('d/m/Y', strtotime('+12 months')),
        ];
    $ok = enviarWhatsAppTemplate(
        $telefonoPrueba,
        $template,
        $params,
        'venta',
        0
    );

    if ($ok) {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'success',
            'Meta aceptó el mensaje de prueba para ' . htmlspecialchars($telefonoPrueba) . '. La entrega final se confirma por webhook.'
        );
    } else {
        $detalle = whatsappResumenUltimoError($pdo, 'venta', 0, $template);
        if ($detalle !== '') {
            logPanelError('whatsapp_probar_meta', $detalle, [
                'template' => $template,
                'telefono_normalizado' => whatsappFormatearTelefono($telefonoPrueba),
            ]);
        }

        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'No se pudo enviar el mensaje de prueba.' . ($detalle !== '' ? ' Meta respondió: ' . $detalle : ' Revisá el log de notificaciones y las credenciales.')
        );
    }
} catch (Throwable $e) {
    logPanelError('whatsapp_probar', $e);
    $mensaje = ($e instanceof RuntimeException) ? $e->getMessage() : 'Error al enviar mensaje de prueba.';
    redirectWithFlash(panelBaseUrl('configuracion/mantenimiento/index.php'), 'error', $mensaje);
}
