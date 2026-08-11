<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();
aiEnsureSchema($pdo);

$entries = $_POST['entries'] ?? [];
if (!is_array($entries)) {
    setFlash('error', 'No se recibieron instrucciones válidas.');
    redirect(panelBaseUrl('ia/base.php'));
}

try {
    aiUpdateInstructions($pdo, $entries);
    setFlash('success', 'Base comercial IA actualizada.');
} catch (Throwable $e) {
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo guardar la base IA.'));
}

redirect(panelBaseUrl('ia/base.php'));
