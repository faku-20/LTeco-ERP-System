<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/n8n.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();

try {
    n8nUpdateSettings($pdo, $_POST['settings'] ?? []);
    setFlash('success', 'Configuración n8n guardada.');
} catch (Throwable $e) {
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo guardar n8n.'));
}

redirect(panelBaseUrl('n8n/index.php'));
