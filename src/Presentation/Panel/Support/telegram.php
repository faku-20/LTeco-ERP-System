<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';
require_once __DIR__ . '/helpers.php';

function telegramEnsureSchema(PDO $pdo): void
{
    ltecoRequireSchemaTables($pdo, ['telegram_delivery'], 'Telegram');
}

/** @return list<string> */
function telegramChatIds(): array
{
    return array_values(array_unique(array_filter(array_map(
        'trim',
        preg_split('/[,;]+/', (string) configEnv('LTECO_TELEGRAM_CHAT_IDS', '')) ?: []
    ), static fn (string $chatId): bool => $chatId !== '' && preg_match('/^-?[0-9]{5,20}$/', $chatId) === 1)));
}

function telegramConfig(): array
{
    return [
        'enabled' => in_array(strtolower((string) configEnv('LTECO_TELEGRAM_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true),
        'bot_token' => trim((string) configEnv('LTECO_TELEGRAM_BOT_TOKEN', '')),
        'chat_ids' => telegramChatIds(),
    ];
}

function telegramIsConfigured(): bool
{
    $config = telegramConfig();
    return $config['enabled'] && $config['bot_token'] !== '' && $config['chat_ids'] !== [];
}

/** @return list<string> */
function telegramConfigurationErrors(): array
{
    $errors = [];
    $config = telegramConfig();
    if (!$config['enabled']) {
        $errors[] = 'LTECO_TELEGRAM_ENABLED';
    }
    if ($config['bot_token'] === '') {
        $errors[] = 'LTECO_TELEGRAM_BOT_TOKEN';
    }
    if ($config['chat_ids'] === []) {
        $errors[] = 'LTECO_TELEGRAM_CHAT_IDS';
    }
    if (trim((string) configEnv('LTECO_TELEGRAM_START_AT', '')) === '') {
        $errors[] = 'LTECO_TELEGRAM_START_AT';
    }
    return $errors;
}

function telegramSendMessage(string $chatId, string $message, ?string $buttonUrl = null, string $buttonText = 'Abrir panel'): array
{
    $config = telegramConfig();
    if (!telegramIsConfigured()) {
        return ['ok' => false, 'error' => 'Telegram no configurado.'];
    }

    $body = [
        'chat_id' => $chatId,
        'text' => mb_substr($message, 0, 3900),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($buttonUrl !== null && filter_var($buttonUrl, FILTER_VALIDATE_URL) !== false) {
        $body['reply_markup'] = json_encode([
            'inline_keyboard' => [[['text' => mb_substr($buttonText, 0, 64), 'url' => $buttonUrl]]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $ch = curl_init('https://api.telegram.org/bot' . $config['bot_token'] . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = (string) curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return ['ok' => false, 'error' => 'cURL: ' . $curlError, 'http_status' => $httpStatus];
    }

    $decoded = json_decode($response, true);
    $ok = $httpStatus >= 200 && $httpStatus < 300 && is_array($decoded) && !empty($decoded['ok']);
    if (!$ok) {
        return [
            'ok' => false,
            'error' => is_array($decoded) ? (string) ($decoded['description'] ?? 'Telegram respondió con error.') : 'Respuesta inválida de Telegram.',
            'http_status' => $httpStatus,
        ];
    }

    return [
        'ok' => true,
        'message_id' => (int) ($decoded['result']['message_id'] ?? 0),
        'http_status' => $httpStatus,
    ];
}

function telegramEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function telegramPedidoWebMessage(array $pedido, string $panelUrl): string
{
    $isCash = (string) ($pedido['ProveedorPago'] ?? '') === 'cash';
    $paymentLabel = $isCash ? 'Efectivo coordinado' : 'Tarjeta';
    $followup = $isCash
        ? 'Reserva hasta: ' . (!empty($pedido['ExpiraEn']) ? date('d/m/Y H:i', strtotime((string) $pedido['ExpiraEn'])) : 'a confirmar')
        : 'Estado: venta confirmada con tarjeta';

    return ($isCash ? "<b>Nueva reserva web</b>" : "<b>Nueva venta web con tarjeta</b>") . "\n"
        . 'Pedido: ' . telegramEscape((string) $pedido['NumeroPedido']) . "\n"
        . 'Cliente: ' . telegramEscape(trim((string) $pedido['Nombre'] . ' ' . (string) $pedido['Apellido'])) . "\n"
        . 'Tel: ' . telegramEscape((string) $pedido['Telefono']) . "\n"
        . 'Unidad: ' . telegramEscape(((string) ($pedido['ItemsResumen'] ?? '') ?: 'A confirmar')) . "\n"
        . 'Pago: ' . telegramEscape($paymentLabel) . "\n"
        . 'Total: ' . telegramEscape((string) $pedido['Moneda'] . ' ' . number_format((float) $pedido['Total'], 2, ',', '.')) . "\n"
        . telegramEscape($followup) . "\n"
        . 'Panel: ' . telegramEscape($panelUrl);
}

/** @return array{enviados:int,fallidos:int,omitidos:int} */
function telegramProcesarPedidosWeb(PDO $pdo, string $panelBaseUrl, int $limite = 20): array
{
    telegramEnsureSchema($pdo);
    $config = telegramConfig();
    $chats = $config['chat_ids'];
    if ($chats === []) {
        return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
    }

    $lock = (int) $pdo->query("SELECT GET_LOCK('ltecobike:pedido_web_telegram',0)")->fetchColumn();
    if ($lock !== 1) {
        return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
    }

    try {
        $startAt = trim((string) configEnv('LTECO_TELEGRAM_START_AT', ''));
        try {
            $startAt = (new DateTimeImmutable($startAt))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
        }
        $where = "FROM internal_alert a JOIN ecommerce_pedido p ON p.IdPedido=a.SourceId WHERE a.Tipo='pedido_web_nuevo' AND a.SourceType='ecommerce_pedido' AND a.FechaAlta >= " . $pdo->quote($startAt) . " AND p.Estado NOT IN ('Cancelado','Vencido')";
        $limite = max(1, min($limite, 50));
        $stmt = $pdo->query("SELECT a.SourceId AS IdPedido,p.NumeroPedido,p.Nombre,p.Apellido,p.Telefono,p.ProveedorPago,p.Moneda,p.Total,p.ExpiraEn,(SELECT GROUP_CONCAT(CONCAT(i.Modelo,IF(i.CapacidadBateriaAh IS NULL,'',CONCAT(' ',i.CapacidadBateriaAh,'Ah')),IF(i.Color IS NULL OR i.Color='','',CONCAT(' ',i.Color))) ORDER BY i.IdItem SEPARATOR ', ') FROM ecommerce_pedido_item i WHERE i.IdPedido=p.IdPedido) AS ItemsResumen {$where} ORDER BY a.IdAlert ASC LIMIT {$limite}");
        $enviados = 0;
        $fallidos = 0;
        $omitidos = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pedido) {
            $idPedido = (int) $pedido['IdPedido'];
            $link = rtrim($panelBaseUrl, '/') . '/lteco-panel/ecommerce/ver.php?id=' . $idPedido;
            $mensaje = telegramPedidoWebMessage($pedido, $link);
            foreach ($chats as $chatId) {
                $exists = $pdo->prepare("SELECT Estado FROM telegram_delivery WHERE Tipo='pedido_web_interno' AND IdReferencia=? AND ChatId=? LIMIT 1");
                $exists->execute([$idPedido, $chatId]);
                if ((string) $exists->fetchColumn() === 'enviado') {
                    $omitidos++;
                    continue;
                }
                if (!$config['enabled'] || $config['bot_token'] === '') {
                    $omitidos++;
                    continue;
                }
                $result = telegramSendMessage($chatId, $mensaje, $link, 'Ver pedido');
                $ok = !empty($result['ok']);
                $delivery = $pdo->prepare("INSERT INTO telegram_delivery (Tipo,IdReferencia,ChatId,Estado,TelegramMessageId,ErrorMessage) VALUES ('pedido_web_interno',?,?,?,?,?) ON DUPLICATE KEY UPDATE Estado=VALUES(Estado),TelegramMessageId=VALUES(TelegramMessageId),ErrorMessage=VALUES(ErrorMessage),FechaActualizacion=NOW()");
                $delivery->execute([
                    $idPedido,
                    $chatId,
                    $ok ? 'enviado' : 'error',
                    $ok ? (int) ($result['message_id'] ?? 0) : null,
                    $ok ? null : mb_substr((string) ($result['error'] ?? 'Error enviando Telegram.'), 0, 2000),
                ]);
                $ok ? $enviados++ : $fallidos++;
            }
        }
        return compact('enviados', 'fallidos', 'omitidos');
    } finally {
        $pdo->query("DO RELEASE_LOCK('ltecobike:pedido_web_telegram')");
    }
}
