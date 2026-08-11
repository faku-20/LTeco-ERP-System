<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ai.php';

function n8nEnsureSchema(PDO $pdo): void
{
    ltecoRequireSchemaTables($pdo, ['n8n_webhook_setting', 'n8n_webhook_log', 'automation_event'], 'n8n');

    $defaults = [
        'visita_agendada' => 'Visita agendada',
        'visita_hora_confirmada' => 'Hora de visita confirmada',
        'visita_proxima' => 'Visita próxima',
        'reserva_por_vencer' => 'Reserva por vencer',
        'moto_lista_publicar' => 'Moto lista para publicar',
        'service_proximo' => 'Service próximo',
        'resumen_diario' => 'Resumen diario',
        'inbox_mensaje_entrante' => 'Mensaje entrante',
    ];

    $stmt = $pdo->prepare("
        INSERT INTO n8n_webhook_setting (EventKey, Label)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE Label = VALUES(Label)
    ");
    foreach ($defaults as $eventKey => $label) {
        $stmt->execute([$eventKey, $label]);
    }
}

function n8nSettings(PDO $pdo): array
{
    n8nEnsureSchema($pdo);
    return $pdo->query("
        SELECT *
        FROM n8n_webhook_setting
        ORDER BY IdSetting ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function n8nLogs(PDO $pdo, int $limit = 60): array
{
    n8nEnsureSchema($pdo);
    $limit = max(1, min(200, $limit));
    return $pdo->query("
        SELECT *
        FROM n8n_webhook_log
        ORDER BY FechaAlta DESC, IdLog DESC
        LIMIT {$limit}
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function n8nUpdateSettings(PDO $pdo, array $rows): void
{
    n8nEnsureSchema($pdo);
    $stmt = $pdo->prepare("
        UPDATE n8n_webhook_setting
        SET WebhookUrl = ?, Enabled = ?, TimeoutSeconds = ?, Secret = ?
        WHERE IdSetting = ?
    ");

    foreach ($rows as $id => $row) {
        $id = (int)$id;
        if ($id <= 0 || !is_array($row)) {
            continue;
        }

        $url = trim((string)($row['webhook_url'] ?? ''));
        $secret = trim((string)($row['secret'] ?? ''));
        $timeout = max(3, min(60, (int)($row['timeout_seconds'] ?? 10)));
        $enabled = !empty($row['enabled']) ? 1 : 0;

        if ($url !== '') {
            n8nValidateWebhookUrlOrFail($url);
        }

        $stmt->execute([$url !== '' ? $url : null, $enabled, $timeout, $secret !== '' ? $secret : null, $id]);
    }
}

function n8nDispatch(PDO $pdo, string $eventKey, array $payload, ?string $sourceType = null, ?int $sourceId = null): array
{
    n8nEnsureSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM n8n_webhook_setting WHERE EventKey = ? LIMIT 1");
    $stmt->execute([$eventKey]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting || (int)$setting['Enabled'] !== 1 || trim((string)$setting['WebhookUrl']) === '') {
        n8nLog($pdo, $eventKey, 'skipped', $sourceType, $sourceId, (string)($setting['WebhookUrl'] ?? ''), null, null, 'Webhook n8n desactivado o sin URL.', $payload);
        return ['ok' => false, 'status' => 'skipped', 'message' => 'Webhook n8n desactivado o sin URL.'];
    }

    try {
        n8nValidateWebhookUrlOrFail((string)$setting['WebhookUrl']);
    } catch (Throwable $e) {
        n8nLog($pdo, $eventKey, 'error', $sourceType, $sourceId, (string)$setting['WebhookUrl'], null, null, $e->getMessage(), $payload);
        return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
    }

    $body = [
        'event' => $eventKey,
        'app' => 'ltecobike-v2',
        'environment' => appEnv(),
        'sent_at' => date(DATE_ATOM),
        'payload' => $payload,
    ];

    $headers = [
        'User-Agent: LTECOBike-V2/n8n-bridge',
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if (trim((string)$setting['Secret']) !== '') {
        $headers[] = 'X-Lteco-N8n-Secret: ' . trim((string)$setting['Secret']);
    }

    $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init((string)$setting['WebhookUrl']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(3, min(60, (int)$setting['TimeoutSeconds'])),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
    ]);

    $response = (string)curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        n8nLog($pdo, $eventKey, 'error', $sourceType, $sourceId, (string)$setting['WebhookUrl'], $httpStatus ?: null, $response, 'cURL: ' . $curlError, $body);
        return ['ok' => false, 'status' => 'error', 'message' => $curlError, 'http_status' => $httpStatus];
    }

    $ok = $httpStatus >= 200 && $httpStatus < 300;
    n8nLog($pdo, $eventKey, $ok ? 'sent' : 'failed', $sourceType, $sourceId, (string)$setting['WebhookUrl'], $httpStatus, $response, $ok ? null : 'n8n respondió con error HTTP.', $body);

    return ['ok' => $ok, 'status' => $ok ? 'sent' : 'failed', 'http_status' => $httpStatus, 'response' => mb_substr($response, 0, 1000)];
}

function n8nLog(PDO $pdo, string $eventKey, string $status, ?string $sourceType, ?int $sourceId, ?string $webhookUrl, ?int $httpStatus, ?string $responseBody, ?string $errorMessage, array $payload): void
{
    $stmt = $pdo->prepare("
        INSERT INTO n8n_webhook_log
            (EventKey, Status, SourceType, SourceId, WebhookUrl, HttpStatus, ResponseBody, ErrorMessage, Payload, SentAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        mb_substr($eventKey, 0, 80),
        mb_substr($status, 0, 32),
        $sourceType !== null ? mb_substr($sourceType, 0, 80) : null,
        $sourceId,
        $webhookUrl,
        $httpStatus,
        $responseBody !== null ? mb_substr((string)redactSensitiveLogValue($responseBody), 0, 65535) : null,
        $errorMessage !== null ? mb_substr((string)redactSensitiveLogValue($errorMessage), 0, 2000) : null,
        json_encode(redactSensitiveLogValue($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function n8nAllowedHosts(): array
{
    $raw = trim((string)configEnv('LTECO_N8N_ALLOWED_HOSTS', 'n8n.ltecobike.shop'));
    $hosts = array_filter(array_map(
        static fn(string $host): string => strtolower(trim($host)),
        preg_split('/[,\\s]+/', $raw) ?: []
    ));

    return array_values(array_unique($hosts));
}

function n8nValidateWebhookUrlOrFail(string $url): void
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Hay una URL de webhook inválida.');
    }

    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($scheme !== 'https' || $host === '') {
        throw new RuntimeException('El webhook n8n debe usar HTTPS.');
    }

    if (!in_array($host, n8nAllowedHosts(), true)) {
        throw new RuntimeException('El host del webhook n8n no está permitido.');
    }

    foreach (n8nResolveHostIps($host) as $ip) {
        if (n8nIpBloqueada($ip)) {
            throw new RuntimeException('El webhook n8n resuelve a una IP no permitida.');
        }
    }
}

function n8nResolveHostIps(string $host): array
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return [$host];
    }

    $ips = [];
    foreach (dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $record) {
        if (!empty($record['ip'])) {
            $ips[] = (string)$record['ip'];
        }
        if (!empty($record['ipv6'])) {
            $ips[] = (string)$record['ipv6'];
        }
    }

    if ($ips === []) {
        $resolved = gethostbynamel($host) ?: [];
        $ips = array_merge($ips, array_map('strval', $resolved));
    }

    if ($ips === []) {
        throw new RuntimeException('No se pudo resolver el host del webhook n8n.');
    }

    return array_values(array_unique($ips));
}

function n8nIpBloqueada(string $ip): bool
{
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
        return true;
    }

    return in_array($ip, ['169.254.169.254', '100.100.100.200'], true);
}

function n8nHealth(PDO $pdo): array
{
    n8nEnsureSchema($pdo);
    $settings = (int)$pdo->query('SELECT COUNT(*) FROM n8n_webhook_setting')->fetchColumn();
    $enabled = (int)$pdo->query('SELECT COUNT(*) FROM n8n_webhook_setting WHERE Enabled = 1 AND WebhookUrl IS NOT NULL')->fetchColumn();
    $pendingEvents = (int)$pdo->query("SELECT COUNT(*) FROM automation_event WHERE Status = 'pending'")->fetchColumn();
    $ai = aiConfig();

    return [
        'ok' => true,
        'app' => 'ltecobike-v2',
        'environment' => appEnv(),
        'ai_enabled' => $ai['enabled'] && trim((string)$ai['api_key']) !== '',
        'n8n_token_configured' => trim((string)configEnv('LTECO_N8N_WEBHOOK_TOKEN', '')) !== '',
        'settings' => $settings,
        'enabled_webhooks' => $enabled,
        'pending_events' => $pendingEvents,
        'checked_at' => date(DATE_ATOM),
    ];
}

function n8nDigest(PDO $pdo): array
{
    n8nEnsureSchema($pdo);
    aiEnsureSchema($pdo);

    $metrics = [
        'open_leads' => n8nScalar($pdo, "SELECT COUNT(*) FROM commercial_lead WHERE Estado NOT IN ('cerrado','ganado','perdido')"),
        'new_inbox_messages' => n8nScalar($pdo, "SELECT COUNT(*) FROM commercial_inbox_message WHERE Direccion = 'inbound' AND Estado = 'nuevo'"),
        'available_vehicles' => n8nScalar($pdo, 'SELECT COUNT(*) FROM vehiculo WHERE FechaVenta IS NULL'),
        'parts_without_stock' => n8nScalar($pdo, "SELECT COUNT(*) FROM producto WHERE TipoProducto = 'Repuesto' AND COALESCE(Stock, 0) <= 0"),
        'services_due_14_days' => n8nScalar($pdo, "SELECT COUNT(*) FROM service_vehiculo WHERE Estado = 'Pendiente' AND FechaProgramada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)"),
        'whatsapp_errors_7_days' => n8nScalar($pdo, "SELECT COUNT(*) FROM notificacion_whatsapp WHERE Estado = 'error' AND FechaEnvio >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    ];

    return [
        'ok' => true,
        'generated_at' => date(DATE_ATOM),
        'metrics' => $metrics,
        'summary_text' => sprintf(
            'Leads abiertos: %d. Mensajes nuevos: %d. Vehículos disponibles: %d. Repuestos sin stock: %d. Services próximos: %d. Errores WhatsApp 7 días: %d.',
            $metrics['open_leads'],
            $metrics['new_inbox_messages'],
            $metrics['available_vehicles'],
            $metrics['parts_without_stock'],
            $metrics['services_due_14_days'],
            $metrics['whatsapp_errors_7_days']
        ),
    ];
}

function n8nScalar(PDO $pdo, string $sql): int
{
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function n8nEvents(PDO $pdo, int $limit = 50): array
{
    n8nEnsureSchema($pdo);
    $limit = max(1, min(200, $limit));
    return $pdo->query("
        SELECT *
        FROM automation_event
        WHERE Status = 'pending'
        ORDER BY FechaAlta ASC, IdEvent ASC
        LIMIT {$limit}
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function n8nStoreEvent(PDO $pdo, string $eventKey, array $payload, ?string $sourceType = null, ?int $sourceId = null): int
{
    n8nEnsureSchema($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO automation_event (EventKey, SourceType, SourceId, Payload)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        mb_substr($eventKey, 0, 80),
        $sourceType !== null ? mb_substr($sourceType, 0, 80) : null,
        $sourceId,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return (int)$pdo->lastInsertId();
}

function n8nAcknowledgeEvent(PDO $pdo, int $id, string $status, ?string $errorMessage = null): void
{
    n8nEnsureSchema($pdo);
    $status = in_array($status, ['processed', 'error', 'ignored'], true) ? $status : 'processed';
    $stmt = $pdo->prepare("
        UPDATE automation_event
        SET Status = ?, ErrorMessage = ?, FechaProcesado = NOW()
        WHERE IdEvent = ?
    ");
    $stmt->execute([$status, $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null, $id]);
}

function n8nStoreClassification(PDO $pdo, array $data): int
{
    aiEnsureSchema($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO ia_accion_sugerida
            (TipoAccion, IdLead, IdInbox, IdCliente, ClienteNombre, ClienteTelefono, IdVehiculo, VehiculoTexto, Prioridad, MensajeOrigen, Motivo, Payload)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        mb_substr((string)($data['tipo_accion'] ?? 'seguimiento_lead'), 0, 80),
        isset($data['id_lead']) ? (int)$data['id_lead'] : null,
        isset($data['id_inbox']) ? (int)$data['id_inbox'] : null,
        isset($data['id_cliente']) ? (int)$data['id_cliente'] : null,
        isset($data['cliente_nombre']) ? mb_substr((string)$data['cliente_nombre'], 0, 160) : null,
        isset($data['cliente_telefono']) ? mb_substr((string)$data['cliente_telefono'], 0, 40) : null,
        isset($data['id_vehiculo']) ? mb_substr((string)$data['id_vehiculo'], 0, 10) : null,
        isset($data['vehiculo_texto']) ? mb_substr((string)$data['vehiculo_texto'], 0, 160) : null,
        in_array((string)($data['prioridad'] ?? 'media'), ['baja','media','alta','urgente'], true) ? (string)$data['prioridad'] : 'media',
        isset($data['mensaje_origen']) ? mb_substr((string)$data['mensaje_origen'], 0, 2000) : null,
        isset($data['motivo']) ? mb_substr((string)$data['motivo'], 0, 2000) : null,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return (int)$pdo->lastInsertId();
}

function n8nIngestInbox(PDO $pdo, array $data): array
{
    require_once __DIR__ . '/agenda.php';
    aiEnsureSchema($pdo);
    $idInbox = aiIngestInboundMessage($pdo, [
        'channel' => (string)($data['channel'] ?? $data['canal'] ?? 'whatsapp'),
        'external_id' => (string)($data['external_id'] ?? $data['message_id'] ?? ''),
        'phone' => (string)($data['phone'] ?? $data['telefono'] ?? ''),
        'name' => (string)($data['name'] ?? $data['nombre'] ?? 'Cliente'),
        'message' => (string)($data['message'] ?? $data['mensaje'] ?? ''),
        'received_at' => $data['received_at'] ?? $data['fecha_recibido'] ?? date('Y-m-d H:i:s'),
        'raw' => $data,
    ]);

    $classified = false;
    if ($idInbox !== null && !empty($data['classify'])) {
        aiClassifyInbox($pdo, $idInbox);
        $classified = true;
    }

    if ($idInbox !== null) {
        n8nStoreEvent($pdo, 'inbox_mensaje_entrante', ['id_inbox' => $idInbox, 'channel' => $data['channel'] ?? $data['canal'] ?? 'whatsapp'], 'inbox', $idInbox);
        agendaMaybeScheduleFromInbox($pdo, $idInbox);
    }

    return ['ok' => $idInbox !== null, 'id_inbox' => $idInbox, 'classified' => $classified];
}

function n8nIngestMetaWhatsapp(PDO $pdo, array $payload): array
{
    require_once __DIR__ . '/agenda.php';
    require_once __DIR__ . '/whatsapp.php';
    whatsappEnsureTabla($pdo);

    $saved = 0;
    $statuses = 0;
    foreach (($payload['entry'] ?? []) as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            foreach (($change['value']['statuses'] ?? []) as $status) {
                if (!is_array($status)) {
                    continue;
                }
                $messageId = (string)($status['id'] ?? '');
                $deliveryStatus = (string)($status['status'] ?? '');
                $statusDate = isset($status['timestamp']) && ctype_digit((string)$status['timestamp'])
                    ? date('Y-m-d H:i:s', (int)$status['timestamp'])
                    : null;
                whatsappService($pdo)->registrarEstadoWebhook(
                    $messageId,
                    $deliveryStatus,
                    json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    $statusDate
                );
                aiMaybeSendInitialAutoReplyClosure($pdo, $messageId, $deliveryStatus);
                $statuses++;
            }

            foreach (($change['value']['messages'] ?? []) as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $text = '';
                $type = (string)($message['type'] ?? '');
                if ($type === 'text') {
                    $text = (string)($message['text']['body'] ?? '');
                } elseif ($type !== '') {
                    $text = '[' . $type . ']';
                }

                $phone = (string)($message['from'] ?? '');
                $name = '';
                foreach (($change['value']['contacts'] ?? []) as $contact) {
                    if (($contact['wa_id'] ?? '') === $phone) {
                        $name = (string)($contact['profile']['name'] ?? '');
                        break;
                    }
                }

                $id = aiIngestInboundMessage($pdo, [
                    'channel' => 'whatsapp',
                    'external_id' => (string)($message['id'] ?? ''),
                    'phone' => $phone,
                    'name' => $name,
                    'message' => $text,
                    'reply_to_message_id' => (string)($message['context']['id'] ?? ''),
                    'received_at' => isset($message['timestamp']) && ctype_digit((string)$message['timestamp']) ? date('Y-m-d H:i:s', (int)$message['timestamp']) : date('Y-m-d H:i:s'),
                    'raw' => $change,
                ]);
                if ($id !== null) {
                    $saved++;

                    if (in_array(strtolower((string)configEnv('LTECO_AI_CLASSIFY_ON_WEBHOOK', '1')), ['1', 'true', 'yes', 'on'], true)) {
                        try {
                            aiClassifyInbox($pdo, $id);
                        } catch (Throwable $e) {
                            try {
                                aiSetInboxError($pdo, $id, $e->getMessage());
                            } catch (Throwable) {
                            }
                        }
                    }

                    try {
                        aiMaybeSendInitialAutoReply($pdo, $phone, $id);
                    } catch (Throwable) {
                    }

                    agendaMaybeScheduleFromInbox($pdo, $id);
                    n8nStoreEvent($pdo, 'inbox_mensaje_entrante', ['id_inbox' => $id, 'source' => 'meta_whatsapp'], 'inbox', $id);
                }
            }
        }
    }

    return ['ok' => true, 'statuses' => $statuses, 'messages' => $saved];
}

function n8nRequestJson(): array
{
    $raw = (string)file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('JSON inválido.');
    }
    return $json;
}

function n8nAuthorizeOrFail(): void
{
    $configured = trim((string)configEnv('LTECO_N8N_WEBHOOK_TOKEN', ''));
    if ($configured === '') {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'LTECO_N8N_WEBHOOK_TOKEN no configurado.']);
        exit;
    }

    $token = trim((string)($_SERVER['HTTP_X_LTECO_N8N_TOKEN'] ?? ''));
    $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($token === '' && stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
    }

    if ($token === '' || !hash_equals($configured, $token)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Token n8n inválido.']);
        exit;
    }
}

function n8nJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
