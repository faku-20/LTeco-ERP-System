<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();

$id = (int)($_POST['id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));

if ($id <= 0 || mb_strlen($body) < 2) {
    setFlash('error', 'Respuesta inválida.');
    redirect(panelBaseUrl('whatsapp/index.php'));
}

aiEnsureSchema($pdo);
$entry = aiInboxEntry($pdo, $id);

if (!$entry || trim((string)$entry['Telefono']) === '') {
    setFlash('error', 'La conversación no tiene teléfono.');
    redirect(panelBaseUrl('whatsapp/index.php'));
}

$ok = enviarWhatsAppTextoConPdo($pdo, (string)$entry['Telefono'], $body, $id);

aiRecordOutboundInbox($pdo, $entry, $body, $ok, $id);

registrarAuditoria($pdo, 'WHATSAPP_RESPUESTA_PANEL', 'WhatsApp', 'Respuesta enviada desde bandeja WhatsApp.', [
    'id_inbox' => $id,
    'telefono' => $entry['Telefono'],
    'ok' => $ok,
]);

setFlash($ok ? 'success' : 'error', $ok ? 'Respuesta enviada y guardada.' : 'La respuesta quedó guardada, pero Meta no aceptó el envío.');
redirect(panelBaseUrl('whatsapp/index.php'));
