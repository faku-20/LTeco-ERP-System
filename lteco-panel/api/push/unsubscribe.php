<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';

requiereAdmin();
header('Content-Type: application/json; charset=utf-8');
try {
    requirePost();
    $token = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data['endpoint']) || filter_var((string)$data['endpoint'], FILTER_VALIDATE_URL) === false || !str_starts_with((string)$data['endpoint'], 'https://')) {
        throw new RuntimeException('Suscripción inválida.');
    }
    pushDisableSubscription($pdo, (int)(usuarioActual()['IdUsuario'] ?? 0), (string)$data['endpoint']);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
