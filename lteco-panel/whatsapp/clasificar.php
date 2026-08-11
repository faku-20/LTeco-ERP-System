<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mensaje inválido.');
    redirect(panelBaseUrl('whatsapp/index.php'));
}

try {
    aiClassifyInbox($pdo, $id);
    aiLogUsage($pdo, 'whatsapp.clasificar', 'classify_inbox', 'success', 0);
    setFlash('success', 'Mensaje clasificado con IA.');
} catch (Throwable $e) {
    aiLogUsage($pdo, 'whatsapp.clasificar', 'classify_inbox', 'error', 0);
    try { aiSetInboxError($pdo, $id, $e->getMessage()); } catch (Throwable) {}
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo clasificar con IA.'));
}

redirect(panelBaseUrl('whatsapp/index.php'));
