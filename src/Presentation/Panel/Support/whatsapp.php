<?php
/**
 * WhatsApp Cloud API — Ltecobike
 * Envía mensajes de template directo a Meta sin BSP intermediario.
 * Si WHATSAPP_ENABLED=false o las credenciales no están configuradas,
 * todas las funciones retornan false/void sin lanzar excepciones.
 */

/**
 * Lee la configuración de WhatsApp desde env + tabla configuracion.
 * La tabla tiene prioridad sobre el .env para permitir gestión desde el panel.
 */
function whatsappObtenerConfig(PDO $pdo): array
{
    $envEnabled = strtolower(trim((string)configEnv('LTECO_WHATSAPP_ENABLED', 'false')));

    $cfg = [
        'enabled'     => in_array($envEnabled, ['1', 'true', 'yes'], true),
        'phone_id'    => (string)configEnv('LTECO_WHATSAPP_PHONE_NUMBER_ID', ''),
        'token'       => (string)configEnv('LTECO_WHATSAPP_ACCESS_TOKEN', ''),
        'tpl_venta'   => (string)configEnv('LTECO_WHATSAPP_TEMPLATE_VENTA', ''),
        'tpl_service' => (string)configEnv('LTECO_WHATSAPP_TEMPLATE_SERVICE', ''),
    ];

    try {
        return whatsappService($pdo)->configuracion($cfg);
    } catch (Throwable) {
        return $cfg;
    }
}

/**
 * Formatea teléfono a formato E.164 con prefijo Uruguay +598.
 * Retorna null si el teléfono no es válido.
 */
function whatsappFormatearTelefono(?string $telefono): ?string
{
    $digitos = preg_replace('/\D+/', '', (string)$telefono) ?? '';
    if ($digitos === '') {
        return null;
    }

    if (str_starts_with($digitos, '00')) {
        $digitos = substr($digitos, 2);
    }

    if (str_starts_with($digitos, '5980')) {
        $digitos = '598' . substr($digitos, 4);
    }

    if (str_starts_with($digitos, '0')) {
        $digitos = substr($digitos, 1);
    }

    if (!str_starts_with($digitos, '598')) {
        $digitos = '598' . $digitos;
    }

    return (strlen($digitos) >= 11 && strlen($digitos) <= 15) ? $digitos : null;
}

function whatsappTemplateEsHelloWorld(string $template): bool
{
    return strtolower(trim($template)) === 'hello_world';
}

function whatsappLanguageTemplate(string $template): string
{
    return whatsappTemplateEsHelloWorld($template) ? 'en_US' : 'es_UY';
}

function whatsappResumenUltimoError(PDO $pdo, string $tipo = 'venta', int $idReferencia = 0, string $template = ''): string
{
    try {
        $service = whatsappService($pdo);
        if (!$service->tablaDisponible()) {
            return '';
        }
        $resp = $service->ultimoError($tipo, $idReferencia, $template);
        return is_string($resp) ? _waResumenRespuestaMeta($resp) : '';
    } catch (Throwable) {
        return '';
    }
}

/**
 * Envía un mensaje de template de WhatsApp.
 * Usa global $pdo — conveniente para scripts top-level del panel.
 * Retorna false silenciosamente si WA está desactivado o sin credenciales.
 */
function enviarWhatsAppTemplate(
    string $telefono,
    string $template,
    array $variables,
    string $tipo = 'venta',
    int $idReferencia = 0
): bool {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }
    return _waEnviarConPdo($pdo, $telefono, $template, $variables, $tipo, $idReferencia);
}

/**
 * Igual que enviarWhatsAppTemplate pero recibe PDO explícito.
 * Usar en cron scripts donde $pdo no está en el scope global.
 */
function enviarWhatsAppTemplateConPdo(
    PDO $pdo,
    string $telefono,
    string $template,
    array $variables,
    string $tipo = 'venta',
    int $idReferencia = 0
): bool {
    return _waEnviarConPdo($pdo, $telefono, $template, $variables, $tipo, $idReferencia);
}

function _waEnviarConPdo(
    PDO $pdo,
    string $telefono,
    string $template,
    array $variables,
    string $tipo,
    int $idReferencia
): bool {
    try {
        $cfg = whatsappObtenerConfig($pdo);

        if (!$cfg['enabled']) {
            _waRegistrar($pdo, $tipo, $idReferencia, $telefono, $template, 'omitido', 'WhatsApp desactivado');
            return false;
        }

        if (empty($cfg['phone_id']) || empty($cfg['token']) || $template === '') {
            _waRegistrar($pdo, $tipo, $idReferencia, $telefono, $template, 'omitido', 'Credenciales no configuradas');
            return false;
        }

        $tel = whatsappFormatearTelefono($telefono);
        if ($tel === null) {
            _waRegistrar($pdo, $tipo, $idReferencia, $telefono, $template, 'omitido', 'Teléfono inválido o vacío');
            return false;
        }

        if (whatsappTemplateEsHelloWorld($template)) {
            $variables = [];
        }

        $components = [];
        if (!empty($variables)) {
            $params = array_map(
                static fn($v) => ['type' => 'text', 'text' => (string)$v],
                array_values($variables)
            );
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        $templatePayload = [
            'name'     => $template,
            'language' => ['code' => whatsappLanguageTemplate($template)],
        ];

        if (!empty($components)) {
            $templatePayload['components'] = $components;
        }

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $tel,
            'type'              => 'template',
            'template'          => $templatePayload,
        ]);

        $url = 'https://graph.facebook.com/v20.0/' . urlencode($cfg['phone_id']) . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $cfg['token'],
                'Content-Type: application/json',
            ],
        ]);

        $response  = (string)curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            _waRegistrar($pdo, $tipo, $idReferencia, $tel, $template, 'error', 'cURL: ' . $curlError);
            return false;
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        _waRegistrar($pdo, $tipo, $idReferencia, $tel, $template, $success ? 'enviado' : 'error', $response);
        return $success;

    } catch (Throwable $e) {
        try {
            _waRegistrar($pdo, $tipo, $idReferencia, $telefono, $template, 'error', $e->getMessage());
        } catch (Throwable) {}
        return false;
    }
}

function _waRegistrar(
    PDO $pdo,
    string $tipo,
    int $idRef,
    string $tel,
    string $tpl,
    string $estado,
    ?string $resp
): void {
    try {
        $responseData = json_decode((string)$resp, true);
        $waMessageId = is_array($responseData)
            ? trim((string)($responseData['messages'][0]['id'] ?? ''))
            : '';

        whatsappService($pdo)->registrar(
            $tipo,
            $idRef,
            $tel,
            $tpl,
            $estado,
            $resp,
            $waMessageId !== '' ? $waMessageId : null
        );

        _waAuditarNotificacion($pdo, $tipo, $idRef, $tel, $tpl, $estado, $resp);
    } catch (Throwable) {}
}


/**
 * Registra un resumen humano del envío de WhatsApp en Auditoría.
 * La respuesta técnica completa queda en notificacion_whatsapp.
 */
function _waAuditarNotificacion(
    PDO $pdo,
    string $tipo,
    int $idRef,
    string $tel,
    string $tpl,
    string $estado,
    ?string $resp
): void {
    try {
        if (!function_exists('registrarAuditoria')) {
            require_once dirname(__DIR__) . '/bootstrap.php';
            $auditoriaPath = LTECO_PANEL_SUPPORT_DIR . '/auditoria.php';
            if (is_file($auditoriaPath)) {
                require_once $auditoriaPath;
            }
        }

        if (!function_exists('registrarAuditoria')) {
            return;
        }

        $tipoNorm = in_array($tipo, ['venta', 'service'], true) ? $tipo : 'venta';
        $estadoNorm = in_array($estado, ['enviado', 'error', 'omitido'], true) ? $estado : 'omitido';

        $accion = match ($estadoNorm) {
            'enviado' => 'WHATSAPP_ENVIADO',
            'error'   => 'WHATSAPP_ERROR',
            default   => 'WHATSAPP_OMITIDO',
        };

        $referencia = $tipoNorm === 'service' ? 'Service' : 'Venta';
        $resumen = _waResumenRespuestaMeta($resp);

        $detalle = match ($estadoNorm) {
            'enviado' => $referencia . ' #' . $idRef . ' - WhatsApp enviado a ' . $tel . ' con template ' . $tpl . '.',
            'error'   => $referencia . ' #' . $idRef . ' - Error al enviar WhatsApp a ' . $tel . ' con template ' . $tpl . ($resumen !== '' ? ': ' . $resumen : '.'),
            default   => $referencia . ' #' . $idRef . ' - WhatsApp omitido para ' . $tel . ' con template ' . $tpl . ($resumen !== '' ? ': ' . $resumen : '.'),
        };

        registrarAuditoria($pdo, $accion, 'WhatsApp', mb_substr($detalle, 0, 500), [
            'tipo' => $tipoNorm,
            'id_referencia' => $idRef,
            'telefono' => $tel,
            'template' => $tpl,
            'estado' => $estadoNorm,
            'origen' => PHP_SAPI === 'cli' ? 'cron' : 'panel',
            'respuesta_resumen' => $resumen,
        ]);
    } catch (Throwable) {
        // Nunca romper el flujo de venta/service por un error de auditoría.
    }
}

/**
 * Resume la respuesta de Meta para no llenar Auditoría con JSON gigante.
 */
function _waResumenRespuestaMeta(?string $resp): string
{
    $resp = trim((string)$resp);
    if ($resp === '') {
        return '';
    }

    $json = json_decode($resp, true);

    if (is_array($json)) {
        if (isset($json['error']['message'])) {
            $msg = (string)$json['error']['message'];
            $details = (string)($json['error']['error_data']['details'] ?? '');
            return mb_substr(trim($msg . ($details !== '' ? ' - ' . $details : '')), 0, 300);
        }

        if (isset($json['messages'][0]['id'])) {
            $status = (string)($json['messages'][0]['message_status'] ?? 'accepted');
            return 'Meta aceptó el mensaje. Estado: ' . $status;
        }
    }

    return mb_substr($resp, 0, 300);
}

/**
 * Crea la tabla notificacion_whatsapp si no existe.
 */
function whatsappEnsureTabla(PDO $pdo): void
{
    try {
        whatsappService($pdo)->asegurarEstructura();
        whatsappEnsureMediaCache($pdo);
    } catch (Throwable) {}
}

/**
 * Agrega las columnas de WhatsApp a la tabla configuracion si no existen.
 */
function whatsappEnsureColumnas(PDO $pdo): void
{
    try {
        whatsappService($pdo)->asegurarEstructura();
    } catch (Throwable) {}
}

function enviarWhatsAppTextoConPdo(
    PDO $pdo,
    string $telefono,
    string $body,
    int $idReferencia = 0,
    string $template = 'manual_text'
): bool
{
    return _enviarWhatsAppPayloadConPdo($pdo, $telefono, [
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => mb_substr(trim($body), 0, 1500),
        ],
    ], $idReferencia, $template);
}

function whatsappTieneVentanaTextoGratuita(PDO $pdo, string $telefono, int $horas = 24): bool
{
    $tel = whatsappFormatearTelefono($telefono);
    if ($tel === null) {
        return false;
    }

    try {
        return whatsappService($pdo)->tieneVentanaTextoGratuita($tel, $horas);
    } catch (Throwable) {
        return false;
    }

}

function enviarWhatsAppTextoGratisConPdo(
    PDO $pdo,
    string $telefono,
    string $body,
    int $idReferencia = 0,
    string $template = 'texto_gratis_24h'
): bool {
    try {
        whatsappEnsureTabla($pdo);
        if (!whatsappTieneVentanaTextoGratuita($pdo, $telefono)) {
            _waRegistrar($pdo, 'venta', $idReferencia, $telefono, $template, 'omitido', 'Sin ventana gratuita de 24 horas iniciada por el cliente');
            return false;
        }

        return enviarWhatsAppTextoConPdo($pdo, $telefono, $body, $idReferencia, $template);
    } catch (Throwable $e) {
        try {
            _waRegistrar($pdo, 'venta', $idReferencia, $telefono, $template, 'error', $e->getMessage());
        } catch (Throwable) {}
        return false;
    }
}

function enviarWhatsAppImagenConPdo(
    PDO $pdo,
    string $telefono,
    string $url,
    string $caption = '',
    int $idReferencia = 0,
    string $template = 'manual_image'
): bool {
    $mediaId = whatsappMediaIdParaImagen($pdo, $url);
    if ($mediaId !== null) {
        return _enviarWhatsAppPayloadConPdo($pdo, $telefono, [
            'type' => 'image',
            'image' => [
                'id' => $mediaId,
                'caption' => mb_substr(trim($caption), 0, 1024),
            ],
        ], $idReferencia, $template);
    }

    return _enviarWhatsAppPayloadConPdo($pdo, $telefono, [
        'type' => 'image',
        'image' => [
            'link' => trim($url),
            'caption' => mb_substr(trim($caption), 0, 1024),
        ],
    ], $idReferencia, $template);
}

function whatsappEnsureMediaCache(PDO $pdo): void
{
    whatsappService($pdo)->asegurarMediaCache();
}

function whatsappMediaIdParaImagen(PDO $pdo, string $url): ?string
{
    $url = trim($url);
    if ($url === '') return null;

    try {
        $cfg = whatsappObtenerConfig($pdo);
        if (empty($cfg['enabled']) || empty($cfg['phone_id']) || empty($cfg['token'])) return null;

        $localPath = whatsappLocalPathDesdeUrl($url);
        if ($localPath === null || !is_file($localPath) || !is_readable($localPath)) return null;

        $mime = (string) (mime_content_type($localPath) ?: '');
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return null;

        $service = whatsappService($pdo);
        $service->asegurarMediaCache();
        $fileHash = hash_file('sha256', $localPath) ?: hash('sha256', $url);
        $sourceKey = hash('sha256', (string)$cfg['phone_id'].'|'.$url.'|'.$fileHash);

        $cached = $service->mediaCacheId($sourceKey);
        if ($cached !== null) return $cached;

        $uploaded = whatsappSubirMediaImagen($cfg, $localPath, $mime);
        $mediaId = trim((string)($uploaded['id'] ?? ''));
        if ($mediaId === '') return null;

        $service->guardarMediaCache(
            $sourceKey,
            $url,
            $localPath,
            $fileHash,
            $mime,
            $mediaId,
            json_encode($uploaded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $mediaId;
    } catch (Throwable) {
        return null;
    }
}

function whatsappLocalPathDesdeUrl(string $url): ?string
{
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
    if ($path === '') return null;
    $prefix = '/lteco-panel/';
    if (!str_starts_with($path, $prefix)) return null;
    $relative = substr($path, 1);
    if (str_contains($relative, '..')) return null;
    $root = dirname(__DIR__, 4);
    $local = $root . '/' . $relative;
    $realRoot = realpath($root);
    $realLocal = realpath($local);
    if ($realRoot === false || $realLocal === false || !str_starts_with($realLocal, $realRoot . DIRECTORY_SEPARATOR)) return null;
    return $realLocal;
}

/** @param array<string,mixed> $cfg @return array<string,mixed> */
function whatsappSubirMediaImagen(array $cfg, string $localPath, string $mime): array
{
    $ch = curl_init('https://graph.facebook.com/v20.0/' . urlencode((string)$cfg['phone_id']) . '/media');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'messaging_product' => 'whatsapp',
            'type' => $mime,
            'file' => new CURLFile($localPath, $mime, basename($localPath)),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . (string)$cfg['token'],
        ],
    ]);

    $response = (string)curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
        return ['error' => $curlError !== '' ? $curlError : $response, 'http_code' => $httpCode];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['raw' => $response, 'http_code' => $httpCode];
}

/** @param array<string,mixed> $content */
function _enviarWhatsAppPayloadConPdo(
    PDO $pdo,
    string $telefono,
    array $content,
    int $idReferencia,
    string $template
): bool {
    try {
        $cfg = whatsappObtenerConfig($pdo);
        if (!$cfg['enabled']) {
            _waRegistrar($pdo, 'venta', $idReferencia, $telefono, $template, 'omitido', 'WhatsApp desactivado');
            return false;
        }
        if (empty($cfg['phone_id']) || empty($cfg['token'])) {
            _waRegistrar($pdo, 'venta', $idReferencia, $telefono, $template, 'omitido', 'Credenciales no configuradas');
            return false;
        }

        $tel = whatsappFormatearTelefono($telefono);
        if ($tel === null) {
            _waRegistrar($pdo, 'venta', $idReferencia, $telefono, $template, 'omitido', 'Teléfono inválido o vacío');
            return false;
        }

        $payload = json_encode(array_merge([
            'messaging_product' => 'whatsapp',
            'to' => $tel,
        ], $content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init('https://graph.facebook.com/v20.0/' . urlencode($cfg['phone_id']) . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $cfg['token'],
                'Content-Type: application/json',
            ],
        ]);

        $response = (string)curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            _waRegistrar($pdo, 'venta', $idReferencia, $tel, $template, 'error', 'cURL: ' . $curlError);
            return false;
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        _waRegistrar($pdo, 'venta', $idReferencia, $tel, $template, $success ? 'enviado' : 'error', $response);
        return $success;
    } catch (Throwable $e) {
        try {
            _waRegistrar($pdo, 'venta', $idReferencia, $telefono, $template, 'error', $e->getMessage());
        } catch (Throwable) {}
        return false;
    }
}

function whatsappService(PDO $pdo): \Lteco\Application\Whatsapp\WhatsappService
{
    return new \Lteco\Application\Whatsapp\WhatsappService(
        new \Lteco\Infrastructure\Repository\WhatsappRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    );
}
