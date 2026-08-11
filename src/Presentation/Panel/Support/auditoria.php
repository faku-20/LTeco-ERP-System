<?php

/**
 * Auditoría funcional del panel.
 *
 * La función está pensada para no romper el sistema si la tabla todavía no existe.
 * Cuando se ejecute database/patch_sistema_solido.sql, las acciones sensibles quedan registradas.
 */
function registrarAuditoria(PDO $pdo, string $accion, string $modulo, string $detalle = '', array $extra = []): void
{
    try {
        $usuario = function_exists('usuarioActual') ? usuarioActual() : null;
        $idUsuario = is_array($usuario) && isset($usuario['IdUsuario']) ? (int)$usuario['IdUsuario'] : null;
        $nombreUsuario = is_array($usuario) ? (string)($usuario['Usuario'] ?? $usuario['Nombre'] ?? 'Sistema') : 'Sistema';
        $rolUsuario = is_array($usuario) ? (string)($usuario['Rol'] ?? '') : '';

        $ip = function_exists('obtenerIpCliente') ? obtenerIpCliente() : ($_SERVER['REMOTE_ADDR'] ?? 'CLI');
        $userAgent = function_exists('userAgentSeguro') ? userAgentSeguro() : substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 250);
        $extraJson = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $service = new \Lteco\Application\Auditoria\AuditoriaEscrituraService(
            new \Lteco\Infrastructure\Repository\AuditoriaEscrituraRepository(
                new \Lteco\Infrastructure\Db\Connection($pdo)
            )
        );
        $service->registrar([
            ':id_usuario' => $idUsuario ?: null,
            ':usuario' => $nombreUsuario,
            ':rol' => $rolUsuario !== '' ? $rolUsuario : null,
            ':accion' => strtoupper(trim($accion)),
            ':modulo' => trim($modulo),
            ':detalle' => trim($detalle),
            ':extra_json' => $extraJson,
            ':ip' => $ip,
            ':user_agent' => $userAgent,
        ]);
    } catch (Throwable $e) {
        if (function_exists('logPanelError')) {
            logPanelError('auditoria', $e, ['accion' => $accion, 'modulo' => $modulo]);
        }
    }
}
