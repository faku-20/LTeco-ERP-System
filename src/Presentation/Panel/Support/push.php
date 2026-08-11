<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';
require_once __DIR__ . '/helpers.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

function pushEnsureSchema(PDO $pdo): void
{
    ltecoRequireSchemaTables($pdo, ['web_push_subscription', 'web_push_delivery'], 'Web Push');
}

function pushConfig(): array
{
    return [
        'enabled' => in_array(strtolower((string)configEnv('LTECO_WEB_PUSH_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true),
        'subject' => trim((string)configEnv('LTECO_WEB_PUSH_VAPID_SUBJECT', '')),
        'public_key' => trim((string)configEnv('LTECO_WEB_PUSH_VAPID_PUBLIC_KEY', '')),
        'private_key' => trim((string)configEnv('LTECO_WEB_PUSH_VAPID_PRIVATE_KEY', '')),
    ];
}

function pushIsConfigured(): bool
{
    $config = pushConfig();
    return $config['enabled'] && $config['subject'] !== '' && $config['public_key'] !== '' && $config['private_key'] !== '';
}

function pushSaveSubscription(PDO $pdo, int $idUsuario, array $data, string $userAgent): int
{
    pushEnsureSchema($pdo);
    $endpoint = trim((string)($data['endpoint'] ?? ''));
    $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
    $publicKey = trim((string)($keys['p256dh'] ?? ''));
    $authToken = trim((string)($keys['auth'] ?? ''));
    if ($idUsuario <= 0 || filter_var($endpoint, FILTER_VALIDATE_URL) === false || !str_starts_with($endpoint, 'https://') || mb_strlen($endpoint) > 2048 || $publicKey === '' || $authToken === '' || preg_match('/^[A-Za-z0-9_-]{20,512}$/', $publicKey) !== 1 || preg_match('/^[A-Za-z0-9_-]{8,256}$/', $authToken) !== 1) {
        throw new InvalidArgumentException('Suscripción Web Push inválida.');
    }

    $hash = hash('sha256', $endpoint);
    $stmt = $pdo->prepare("
        INSERT INTO web_push_subscription
            (IdUsuario, Endpoint, EndpointHash, PublicKey, AuthToken, ContentEncoding, UserAgent, Activa)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            IdUsuario = VALUES(IdUsuario), PublicKey = VALUES(PublicKey), AuthToken = VALUES(AuthToken),
            ContentEncoding = VALUES(ContentEncoding), UserAgent = VALUES(UserAgent), Activa = 1, UltimoError = NULL
    ");
    $stmt->execute([
        $idUsuario, $endpoint, $hash, $publicKey, $authToken,
        in_array((string)($data['contentEncoding'] ?? 'aes128gcm'), ['aes128gcm', 'aesgcm'], true) ? (string)$data['contentEncoding'] : 'aes128gcm',
        mb_substr($userAgent, 0, 500),
    ]);

    $find = $pdo->prepare('SELECT IdSubscription FROM web_push_subscription WHERE EndpointHash = ?');
    $find->execute([$hash]);
    $idSubscription = (int)$find->fetchColumn();

    $cleanup = $pdo->prepare("
        UPDATE web_push_subscription
        SET Activa = 0, UltimoError = ?
        WHERE IdUsuario = ? AND EndpointHash <> ? AND UserAgent = ? AND Activa = 1
    ");
    $cleanup->execute(["Registro anterior del mismo navegador desactivado al renovar suscripcion.", $idUsuario, $hash, mb_substr($userAgent, 0, 500)]);

    return $idSubscription;
}

function pushDisableSubscription(PDO $pdo, int $idUsuario, string $endpoint): bool
{
    pushEnsureSchema($pdo);
    $stmt = $pdo->prepare('UPDATE web_push_subscription SET Activa = 0 WHERE IdUsuario = ? AND EndpointHash = ?');
    $stmt->execute([$idUsuario, hash('sha256', trim($endpoint))]);
    return $stmt->rowCount() > 0;
}

function pushListSubscriptions(PDO $pdo, int $idUsuario): array
{
    pushEnsureSchema($pdo);
    $stmt = $pdo->prepare('SELECT IdSubscription,UserAgent,Activa,UltimoEnvio,UltimoError,FechaAlta,FechaActualizacion FROM web_push_subscription WHERE IdUsuario=? ORDER BY Activa DESC,FechaActualizacion DESC,IdSubscription DESC');
    $stmt->execute([$idUsuario]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pushCreateTestEvent(PDO $pdo, int $idUsuario): int
{
    pushEnsureSchema($pdo);
    $key = 'push_test:' . $idUsuario . ':' . date('YmdHis');
    $stmt = $pdo->prepare("INSERT INTO automation_event (EventKey,IdempotencyKey,SourceType,SourceId,Payload) VALUES ('push_test',?,?,?,?)");
    $stmt->execute([$key, 'usuario', $idUsuario, json_encode(['target_user_id' => $idUsuario], JSON_THROW_ON_ERROR)]);
    return (int)$pdo->lastInsertId();
}

function pushErrorCode(string $reason, bool $expired): string
{
    if ($expired) return 'subscription_expired';
    $reason = strtolower($reason);
    if (str_contains($reason, 'payload') || str_contains($reason, 'json')) return 'payload_invalid';
    if (str_contains($reason, '401') || str_contains($reason, '403') || str_contains($reason, 'vapid')) return 'configuration';
    if (str_contains($reason, 'timeout') || str_contains($reason, 'tempor')) return 'temporary';
    return 'unknown';
}

function pushDispatchAutomationEvent(PDO $pdo, int $idEvent): array
{
    pushEnsureSchema($pdo);
    $claim = $pdo->prepare("UPDATE automation_event SET Status = 'processing', Intentos = Intentos + 1, FechaUltimoIntento = NOW(), ErrorMessage = NULL WHERE IdEvent = ? AND Status = 'pending' AND Intentos < 5");
    $claim->execute([$idEvent]);
    if ($claim->rowCount() !== 1) {
        return ['ok' => false, 'status' => 'not_pending', 'sent' => 0, 'failed' => 0];
    }

    $stmt = $pdo->prepare("SELECT * FROM automation_event WHERE IdEvent = ? AND Status = 'processing' LIMIT 1");
    $stmt->execute([$idEvent]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        return ['ok' => false, 'status' => 'not_pending', 'sent' => 0, 'failed' => 0];
    }
    if (!in_array((string)$event['EventKey'], ['visita_agendada', 'visita_hora_confirmada', 'pedido_web_creado', 'push_test'], true)) {
        pushReleaseAutomationEvent($pdo, $idEvent, 'Evento no soportado por Web Push.');
        return ['ok' => false, 'status' => 'unsupported', 'sent' => 0, 'failed' => 0];
    }
    if (!pushIsConfigured()) {
        pushReleaseAutomationEvent($pdo, $idEvent, 'Web Push no configurado.');
        return ['ok' => false, 'status' => 'not_configured', 'sent' => 0, 'failed' => 0];
    }

    $payload = json_decode((string)($event['Payload'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $targetUserId = (int)($payload['target_user_id'] ?? 0);
    $subscriptionsSql = "
        SELECT s.* FROM web_push_subscription s
        JOIN usuario u ON u.IdUsuario = s.IdUsuario
        WHERE s.Activa = 1 AND u.Activo = 1 AND u.Rol IN ('Administrador','Superadmin','Superadministrador')" . ($targetUserId > 0 ? " AND u.IdUsuario = " . $targetUserId : '') . "
        ORDER BY s.IdSubscription
    ";
    $subscriptions = $pdo->query($subscriptionsSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($subscriptions === []) {
        pushReleaseAutomationEvent($pdo, $idEvent, 'No hay teléfonos suscritos.');
        return ['ok' => false, 'status' => 'no_subscriptions', 'sent' => 0, 'failed' => 0];
    }

    $eventKey = (string)$event['EventKey'];
    $date = isset($payload['fecha_visita']) ? new DateTimeImmutable((string)$payload['fecha_visita']) : null;
    $hourConfirmed = !empty($payload['hora_confirmada']) || (string)$event['EventKey'] === 'visita_hora_confirmada';
    $client = trim((string)($payload['cliente'] ?? 'Cliente')) ?: 'Cliente';
    $dateLabel = $date ? $date->format('d/m/Y').($hourConfirmed ? ' '.$date->format('H:i') : ' - hora pendiente') : 'fecha a confirmar';
    if ($eventKey === 'pedido_web_creado') {
        $orderId = (int)($event['SourceId'] ?? 0);
        $stmt = $pdo->prepare("SELECT p.NumeroPedido,p.Nombre,p.Apellido,p.Moneda,p.Total,p.ProveedorPago,p.Estado,(SELECT CONCAT(i.Modelo,IF(i.Color IS NULL OR i.Color='','',CONCAT(' ',i.Color))) FROM ecommerce_pedido_item i WHERE i.IdPedido=p.IdPedido ORDER BY i.IdItem LIMIT 1) ProductoPrincipal,(SELECT COUNT(*) FROM ecommerce_pedido_item i WHERE i.IdPedido=p.IdPedido) CantidadProductos FROM ecommerce_pedido p WHERE p.IdPedido=? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) { pushReleaseAutomationEvent($pdo, $idEvent, 'Pedido no encontrado.'); return ['ok'=>false,'status'=>'invalid_order','sent'=>0,'failed'=>0]; }
        $extra = max(0, (int)$order['CantidadProductos'] - 1);
        $product = (string)($order['ProductoPrincipal'] ?: 'Producto');
        $product .= $extra > 0 ? ' y ' . $extra . ' más' : '';
        $payment = (string)$order['ProveedorPago'] === 'cash' ? 'Efectivo coordinado' : 'Tarjeta';
        $notification = json_encode([
            'title' => 'Ltecobike — Nueva venta',
            'body' => 'Pedido #'.(string)$order['NumeroPedido'].'\n'.$product.'\nCliente: '.trim((string)$order['Nombre'].' '.(string)$order['Apellido']).'\nTotal: '.(string)$order['Moneda'].' '.number_format((float)$order['Total'],2,',','.').'\nPago: '.$payment.'\nEstado: '.(string)$order['Estado'],
            'order_id' => $orderId,
            'pedidoId' => $orderId,
            'url' => '/lteco-panel/ecommerce/ver.php?id='.$orderId,
            'tag' => 'pedido-web-'.$orderId,
            'icon' => '/lteco-panel/assets/icons/icon-192.png',
            'badge' => '/lteco-panel/assets/icons/icon-192.png',
            'renotify' => true,
            'requireInteraction' => true,
            'silent' => false,
            'vibrate' => [300, 150, 300, 150, 500],
            'timestamp' => (int)round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($eventKey === 'push_test') {
        $notification = json_encode([
            'title' => 'Notificaciones Ltecobike activadas',
            'body' => 'Este dispositivo quedó vinculado al panel.',
            'url' => '/lteco-panel/configuracion/notificaciones.php',
            'tag' => 'push-test-' . $idEvent,
            'icon' => '/lteco-panel/assets/icons/icon-192.png',
            'badge' => '/lteco-panel/assets/icons/icon-192.png',
            'renotify' => true,
            'requireInteraction' => true,
            'silent' => false,
            'vibrate' => [300, 150, 300, 150, 500],
            'timestamp' => (int)round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $notification = json_encode([
            'title' => $hourConfirmed ? 'Visita agendada' : 'Visita agendada sin hora',
            'body' => $client.' · '.$dateLabel.(empty($payload['modelo']) ? '' : ' · '.trim((string)$payload['modelo'])),
            'url' => '/lteco-panel/agenda/index.php',
            'tag' => 'visita-'.$idEvent,
            'icon' => '/lteco-panel/assets/icons/icon-192.png',
            'badge' => '/lteco-panel/assets/icons/icon-192.png',
            'renotify' => true,
            'requireInteraction' => true,
            'silent' => false,
            'vibrate' => [300, 150, 300, 150, 500],
            'timestamp' => (int)round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $config = pushConfig();
    $webPush = new WebPush(['VAPID' => [
        'subject' => $config['subject'], 'publicKey' => $config['public_key'], 'privateKey' => $config['private_key'],
    ]]);
    $webPush->setDefaultOptions(['TTL' => 3600, 'urgency' => 'high', 'topic' => 'visita-'.$idEvent]);

    $queued = [];
    $sent = 0;
    foreach ($subscriptions as $row) {
        $already = $pdo->prepare("SELECT COUNT(*) FROM web_push_delivery WHERE IdEvent = ? AND IdSubscription = ? AND Estado = 'enviado'");
        $already->execute([$idEvent, (int)$row['IdSubscription']]);
        if ((int)$already->fetchColumn() > 0) {
            $sent++;
            continue;
        }
        $webPush->queueNotification(Subscription::create([
            'endpoint' => (string)$row['Endpoint'], 'publicKey' => (string)$row['PublicKey'],
            'authToken' => (string)$row['AuthToken'], 'contentEncoding' => (string)$row['ContentEncoding'],
        ]), $notification);
        $queued[] = $row;
    }

    $failed = 0;
    $index = 0;
    foreach ($webPush->flush() as $report) {
        $row = $queued[$index++] ?? null;
        if (!$row) continue;
        $success = $report->isSuccess();
        $reason = $success ? null : mb_substr($report->getReason(), 0, 2000);
        $expired = $report->isSubscriptionExpired();
        $delivery = $pdo->prepare("INSERT INTO web_push_delivery (IdEvent,IdSubscription,IdPedido,Estado,Intentos,CodigoError,FechaUltimoIntento,FechaEntrega,ErrorMessage) VALUES (?,?,?,?,1,?,NOW(),?,?) ON DUPLICATE KEY UPDATE IdPedido=VALUES(IdPedido),Estado=VALUES(Estado),Intentos=Intentos+1,CodigoError=VALUES(CodigoError),FechaUltimoIntento=NOW(),FechaEntrega=VALUES(FechaEntrega),ErrorMessage=VALUES(ErrorMessage),FechaAlta=NOW()");
        $delivery->execute([$idEvent,(int)$row['IdSubscription'], $eventKey === 'pedido_web_creado' ? (int)($event['SourceId'] ?? 0) : null, $success ? 'enviado' : 'error', $success ? null : pushErrorCode((string)$reason, $expired), $success ? date('Y-m-d H:i:s') : null, $reason]);
        $update = $pdo->prepare('UPDATE web_push_subscription SET UltimoEnvio = NOW(), UltimoError = ?, Activa = ? WHERE IdSubscription = ?');
        $update->execute([$reason, $expired ? 0 : 1, (int)$row['IdSubscription']]);
        $success ? $sent++ : $failed++;
    }

    $ok = $sent > 0 && $failed === 0;
    if (!$ok) {
        pushReleaseAutomationEvent($pdo, $idEvent, 'Falló una o más entregas Web Push.');
    }
    return ['ok' => $ok, 'status' => $failed === 0 ? 'sent' : 'partial', 'sent' => $sent, 'failed' => $failed];
}

function pushReleaseAutomationEvent(PDO $pdo, int $idEvent, string $error): void
{
    $stmt = $pdo->prepare("UPDATE automation_event SET Status = 'pending', ErrorMessage = ? WHERE IdEvent = ? AND Status = 'processing'");
    $stmt->execute([mb_substr($error, 0, 2000), $idEvent]);
}
