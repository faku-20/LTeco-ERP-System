<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/whatsapp.php';
require_once __DIR__ . '/includes/ai.php';
require_once __DIR__ . '/includes/agenda.php';

whatsappEnsureTabla($pdo);

$verifyToken = (string)configEnv('LTECO_WHATSAPP_WEBHOOK_VERIFY_TOKEN', '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string)($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string)($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string)($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

    if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Invalid verify token';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$raw = (string)file_get_contents('php://input');
$appSecret = (string)configEnv('LTECO_META_APP_SECRET', '');
$signature = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
if (!\Lteco\Application\Whatsapp\MetaWebhookSignature::isValid($raw, $signature, $appSecret)) {
    error_log('Meta WhatsApp webhook rejected: invalid or missing signature.');
    http_response_code(403);
    echo 'Invalid webhook signature';
    exit;
}

$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

$statuses = [];
$messages = [];
foreach (($payload['entry'] ?? []) as $entry) {
    foreach (($entry['changes'] ?? []) as $change) {
        foreach (($change['value']['statuses'] ?? []) as $status) {
            if (is_array($status)) {
                $statuses[] = $status;
            }
        }
        foreach (($change['value']['messages'] ?? []) as $message) {
            if (is_array($message)) {
                $messages[] = [
                    'message' => $message,
                    'contacts' => $change['value']['contacts'] ?? [],
                    'raw' => $change,
                ];
            }
        }
    }
}

foreach ($statuses as $status) {
    $messageId = trim((string)($status['id'] ?? ''));
    if ($messageId === '') {
        continue;
    }

    $estado = mb_substr((string)($status['status'] ?? ''), 0, 40);
    $fecha = null;
    if (isset($status['timestamp']) && ctype_digit((string)$status['timestamp'])) {
        $fecha = date('Y-m-d H:i:s', (int)$status['timestamp']);
    }

    whatsappService($pdo)->registrarEstadoWebhook(
        $messageId,
        $estado,
        mb_substr(json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $raw, 0, 65535),
        $fecha
    );
    try { aiMaybeSendInitialAutoReplyClosure($pdo, $messageId, $estado); } catch (Throwable $e) {
        error_log('Cierre auto respuesta WhatsApp: '.$e->getMessage());
    }
}

$inboundSaved = 0;
foreach ($messages as $item) {
    $message = $item['message'];
    $type = (string)($message['type'] ?? '');
    $text = '';
    if ($type === 'text') {
        $text = (string)($message['text']['body'] ?? '');
    } elseif ($type !== '') {
        $text = '[' . $type . ']';
    }

    $phone = (string)($message['from'] ?? '');
    $name = '';
    foreach (($item['contacts'] ?? []) as $contact) {
        if (($contact['wa_id'] ?? '') === $phone) {
            $name = (string)($contact['profile']['name'] ?? '');
            break;
        }
    }

    $receivedAt = null;
    if (isset($message['timestamp']) && ctype_digit((string)$message['timestamp'])) {
        $receivedAt = date('Y-m-d H:i:s', (int)$message['timestamp']);
    }

    $idInbox = aiIngestInboundMessage($pdo, [
        'channel' => 'whatsapp',
        'external_id' => (string)($message['id'] ?? ''),
        'phone' => $phone,
        'name' => $name,
        'message' => $text,
        'reply_to_message_id' => (string)($message['context']['id'] ?? ''),
        'received_at' => $receivedAt,
        'raw' => $item['raw'],
    ]);

    if ($idInbox !== null) {
        $inboundSaved++;

        if (in_array(strtolower((string)configEnv('LTECO_AI_CLASSIFY_ON_WEBHOOK', '1')), ['1', 'true', 'yes', 'on'], true)) {
            try {
                aiClassifyInbox($pdo, $idInbox);
            } catch (Throwable $e) {
                try { aiSetInboxError($pdo, $idInbox, $e->getMessage()); } catch (Throwable) {}
            }
        }

        try { aiMaybeSendInitialAutoReply($pdo, $phone, $idInbox); } catch (Throwable) {}
        try { agendaMaybeScheduleFromInbox($pdo, $idInbox); } catch (Throwable $e) {
            error_log('Agenda IA WhatsApp: '.$e->getMessage());
        }
    }
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'statuses' => count($statuses), 'messages' => $inboundSaved]);
