<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

define('LTECO_CRON', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/telegram.php';
require_once dirname(__DIR__, 2) . '/shared/mailer.php';
require_once dirname(__DIR__, 2) . '/src/Application/Ecommerce/EcommerceNotificationTemplate.php';
require_once dirname(__DIR__, 2) . '/src/Application/Ecommerce/EcommerceCronService.php';
require_once __DIR__ . '/../includes/push.php';

$service = new EcommerceCronService($pdo);
if (in_array('--telegram-check', $argv, true)) {
    $errors = telegramConfigurationErrors();
    echo $errors === [] ? "telegram: OK\n" : "telegram: FAIL " . implode(', ', $errors) . "\n";
    exit($errors === [] ? 0 : 1);
}

if (in_array('--telegram-test', $argv, true)) {
    $errors = telegramConfigurationErrors();
    if ($errors !== []) {
        fwrite(STDERR, "Telegram no configurado: " . implode(', ', $errors) . "\n");
        exit(1);
    }
    $ok = true;
    foreach (telegramChatIds() as $chatId) {
        $result = telegramSendMessage($chatId, "<b>Prueba Telegram ERP</b>\nEl ecommerce puede enviar avisos de nuevas ventas por Telegram.", rtrim((string) configEnv('LTECO_PANEL_PUBLIC_URL', 'https://panel.ltecobike.shop'), '/') . '/lteco-panel/inicio.php', 'Abrir panel');
        $ok = $ok && !empty($result['ok']);
    }
    echo $ok ? "telegram-test: OK\n" : "telegram-test: FAIL\n";
    exit($ok ? 0 : 1);
}

if (in_array('--mail-check', $argv, true)) {
    $errors = lteco_smtp_configuration_errors();
    echo $errors === [] ? "mail: OK\n" : "mail: FAIL " . implode(', ', $errors) . "\n";
    exit($errors === [] ? 0 : 1);
}

$testTarget = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--send-test=')) {
        $testTarget = trim(substr($arg, 12));
    }
}

if ($testTarget !== '') {
    if (filter_var($testTarget, FILTER_VALIDATE_EMAIL) === false) {
        fwrite(STDERR, "Destino inválido.\n");
        exit(1);
    }
    $ok = lteco_smtp_send_recipients(
        [$testTarget],
        'Prueba de correo transaccional ERP',
        '<h1>Correo operativo correcto</h1><p>La cola transaccional de LTecobike puede enviar mensajes.</p>'
    );
    echo $ok ? "mail-test: OK\n" : "mail-test: FAIL\n";
    exit($ok ? 0 : 1);
}

if (in_array('--dry-run', $argv, true)) {
    $r = $service->resumen();
    echo date('[Y-m-d H:i:s]') . " ecommerce dry-run: notificaciones={$r['notificaciones']}, reservas_vencidas={$r['reservas_vencidas']}, services_proximos={$r['services_proximos']}\n";
    exit(0);
}

$service->liberarReservasVencidas();
$service->encolarRecordatorios();
$pushResultado = ['enviados' => 0, 'fallidos' => 0];
if (filter_var((string) configEnv('LTECO_WEB_SALES_NOTIFY_WEB_PUSH', '0'), FILTER_VALIDATE_BOOL)) {
    $pushResultado = procesarPushPedidosWeb($pdo, 20);
}
$telegramResultado = telegramProcesarPedidosWeb($pdo, (string) configEnv('LTECO_PANEL_PUBLIC_URL', 'https://panel.ltecobike.shop'), 20);
$mailEnabled = filter_var((string) configEnv('LTECO_ECOMMERCE_MAIL_ENABLED', '0'), FILTER_VALIDATE_BOOL);
if (!$mailEnabled) {
    $service->marcarConciliaciones();
    $r = $service->resumen();
    echo date('[Y-m-d H:i:s]') . " ecommerce: push_enviados={$pushResultado['enviados']}, push_fallidos={$pushResultado['fallidos']}, telegram_enviados={$telegramResultado['enviados']}, telegram_fallidos={$telegramResultado['fallidos']}, telegram_omitidos={$telegramResultado['omitidos']}, envío de correo desactivado, {$r['notificaciones']} correo(s) en cola\n";
    exit(0);
}

$resultado = $service->procesarNotificaciones(
    static function (string $destino, string $asunto, string $titulo, string $mensaje, string $boton, string $url): bool {
        $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;color:#163127"><div style="max-width:520px;margin:auto;background:#fff;border-radius:12px;padding:28px"><h1 style="color:#0f6b38">' . htmlspecialchars($titulo) . '</h1><p>' . htmlspecialchars($mensaje) . '</p><p><a style="display:inline-block;background:#159447;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none" href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($boton) . '</a></p><hr style="border:0;border-top:1px solid #ddd;margin:24px 0"><small>LTecobike nunca te pedirá tu contraseña ni los datos completos de tu tarjeta por correo.</small></div></body></html>';
        return lteco_smtp_send_recipients(preg_split('/[,;]+/', $destino) ?: [], $asunto, $html);
    },
    static function (array $n): string {
        if ((string) $n['Tipo'] === 'PedidoWebInterno') {
            $panel = rtrim((string) configEnv('LTECO_PANEL_PUBLIC_URL', 'https://panel.ltecobike.shop'), '/');
            return $panel . '/lteco-panel/ecommerce/ver.php?id=' . (int) ($n['IdPedido'] ?? 0);
        }

        $base = rtrim((string) configEnv('LTECO_STOREFRONT_PUBLIC_URL', 'https://storefront.ltecobike.shop'), '/');
        $uuid = (string) ($n['StorefrontOrderUuid'] ?? '');
        if ($uuid !== '') {
            $hasReceipt = (string) $n['Tipo'] === 'PedidoEntregado'
                || ((string) $n['Tipo'] === 'PagoConfirmado' && (int) ($n['IdVenta'] ?? 0) > 0);
            return $base . '/pedidos/' . rawurlencode($uuid) . ($hasReceipt ? '/comprobante' : '');
        }
        if ((string) $n['Tipo'] === 'PrivacidadResuelta') {
            return $base . '/cuenta';
        }
        if ((string) $n['Tipo'] === 'RecordatorioService') {
            return $base . '/contacto';
        }
        return $base;
    }
);

$service->marcarConciliaciones();
$whatsapp = procesarWhatsAppInternoPedidosWeb($pdo);
echo date('[Y-m-d H:i:s]') . " ecommerce: enviadas={$resultado['enviadas']}, fallidas={$resultado['fallidas']}, push_enviados={$pushResultado['enviados']}, push_fallidos={$pushResultado['fallidos']}, telegram_enviados={$telegramResultado['enviados']}, telegram_fallidos={$telegramResultado['fallidos']}, telegram_omitidos={$telegramResultado['omitidos']}, whatsapp_enviados={$whatsapp['enviados']}, whatsapp_fallidos={$whatsapp['fallidos']}, whatsapp_omitidos={$whatsapp['omitidos']}\n";
exit($resultado['fallidas'] > 0 ? 1 : 0);

/** @return array{enviados:int,fallidos:int} */
function procesarPushPedidosWeb(PDO $pdo, int $limite): array
{
    $limite = max(1, min($limite, 50));
    $stmt = $pdo->query("SELECT IdEvent FROM automation_event WHERE EventKey='pedido_web_creado' AND Status='pending' AND Intentos < 5 ORDER BY FechaAlta,IdEvent LIMIT {$limite}");
    $enviados = 0;
    $fallidos = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $idEvent) {
        try {
            $result = pushDispatchAutomationEvent($pdo, (int)$idEvent);
            if (!empty($result['ok'])) {
                $pdo->prepare("UPDATE automation_event SET Status='processed',FechaProcesado=NOW(),ErrorMessage=NULL WHERE IdEvent=? AND Status='processing'")->execute([(int)$idEvent]);
                $enviados += (int)($result['sent'] ?? 0);
            } else {
                $fallidos += max(1, (int)($result['failed'] ?? 0));
            }
        } catch (Throwable $e) {
            $pdo->prepare("UPDATE automation_event SET Status='pending',ErrorMessage=? WHERE IdEvent=?")->execute([mb_substr($e->getMessage(), 0, 2000), (int)$idEvent]);
            $fallidos++;
            if (function_exists('logPanelError')) {
                logPanelError('web_push_pedido_worker', $e, ['id_event' => (int)$idEvent]);
            }
        }
    }
    return compact('enviados', 'fallidos');
}

/** @return array{enviados:int,fallidos:int,omitidos:int} */
function procesarWhatsAppInternoPedidosWeb(PDO $pdo): array
{
    global $service;
    $telefonos = array_values(array_unique(array_filter(array_map(
        'trim',
        preg_split('/[,;]+/', (string) configEnv('LTECO_WEB_SALES_NOTIFY_WHATSAPP', '')) ?: []
    ))));
    if ($telefonos === []) {
        return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
    }

    whatsappEnsureTabla($pdo);
    $cfg = whatsappObtenerConfig($pdo);
    return $service->procesarWhatsAppPedidosWeb(
        $telefonos,
        !empty($cfg['enabled']) && !empty($cfg['phone_id']) && !empty($cfg['token']),
        (string) configEnv('LTECO_PANEL_PUBLIC_URL', 'https://panel.ltecobike.shop'),
        static fn (string $telefono, string $mensaje, int $idPedido): bool => enviarWhatsAppTextoConPdo($pdo, $telefono, $mensaje, $idPedido, 'pedido_web_interno')
    );
}
