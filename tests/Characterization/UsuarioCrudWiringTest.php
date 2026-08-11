<?php

declare(strict_types=1);

/**
 * Wiring F2: los handlers de gestión de usuarios delegan en UsuarioCrudService,
 * no conservan SQL inline y preservan hashing, CSRF, guards y auditoría.
 */
final class UsuarioCrudWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/usuarios/';
        $sinSql = '/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i';

        // --- crear.php ---
        $crear = (string) @file_get_contents($panel . 'crear.php');
        Assert::isTrue('Wiring crud usuario (crear)', 'usa UsuarioCrudService', strpos($crear, 'UsuarioCrudService') !== false);
        Assert::same('Wiring crud usuario (crear)', 'delega el alta', 1, substr_count($crear, '->crear('));
        Assert::same('Wiring crud usuario (crear)', 'chequea unicidad por servicio', 1, substr_count($crear, '->usuarioDisponible('));
        Assert::same('Wiring crud usuario (crear)', 'sin SQL inline', 0, preg_match_all($sinSql, $crear));
        Assert::isTrue('Wiring crud usuario (crear)', 'conserva hashing', strpos($crear, 'password_hash(') !== false);
        Assert::isTrue('Wiring crud usuario (crear)', 'conserva CSRF', strpos($crear, 'verifyCsrfOrFail()') !== false);
        Assert::isTrue('Wiring crud usuario (crear)', 'conserva auditoría', strpos($crear, 'registrarAuditoria') !== false);

        // --- editar.php ---
        $editar = (string) @file_get_contents($panel . 'editar.php');
        Assert::isTrue('Wiring crud usuario (editar)', 'usa UsuarioCrudService', strpos($editar, 'UsuarioCrudService') !== false);
        Assert::same('Wiring crud usuario (editar)', 'delega la lectura', 1, substr_count($editar, '->obtenerParaEdicion('));
        Assert::same('Wiring crud usuario (editar)', 'delega la edición', 1, substr_count($editar, '->actualizar('));
        Assert::same('Wiring crud usuario (editar)', 'chequea unicidad por servicio', 1, substr_count($editar, '->usuarioDisponibleExcepto('));
        Assert::same('Wiring crud usuario (editar)', 'sin SQL inline', 0, preg_match_all($sinSql, $editar));
        Assert::isTrue('Wiring crud usuario (editar)', 'conserva guard superadmin', strpos($editar, 'requiereSuperadmin()') !== false);

        // --- cambiar_clave.php ---
        $clave = (string) @file_get_contents($panel . 'cambiar_clave.php');
        Assert::isTrue('Wiring crud usuario (clave)', 'usa UsuarioCrudService', strpos($clave, 'UsuarioCrudService') !== false);
        Assert::same('Wiring crud usuario (clave)', 'delega la lectura', 1, substr_count($clave, '->obtenerParaClave('));
        Assert::same('Wiring crud usuario (clave)', 'delega el cambio de clave', 1, substr_count($clave, '->cambiarClave('));
        Assert::same('Wiring crud usuario (clave)', 'sin SQL inline', 0, preg_match_all($sinSql, $clave));
        Assert::isTrue('Wiring crud usuario (clave)', 'conserva hashing', strpos($clave, 'password_hash(') !== false);
        Assert::isTrue('Wiring crud usuario (clave)', 'conserva verificación de clave actual', strpos($clave, 'password_verify(') !== false);

        // --- eliminar.php ---
        $eliminar = (string) @file_get_contents($panel . 'eliminar.php');
        Assert::isTrue('Wiring crud usuario (eliminar)', 'usa UsuarioCrudService', strpos($eliminar, 'UsuarioCrudService') !== false);
        Assert::same('Wiring crud usuario (eliminar)', 'delega la baja', 1, substr_count($eliminar, '->eliminar('));
        Assert::same('Wiring crud usuario (eliminar)', 'sin SQL inline', 0, preg_match_all($sinSql, $eliminar));
        Assert::isTrue('Wiring crud usuario (eliminar)', 'conserva guard superadmin', strpos($eliminar, 'esSuperadmin()') !== false);
        Assert::isTrue('Wiring crud usuario (eliminar)', 'conserva CSRF', strpos($eliminar, 'verifyCsrfOrFail()') !== false);

        // --- toggle_activo.php ---
        $toggle = (string) @file_get_contents($panel . 'toggle_activo.php');
        Assert::isTrue('Wiring crud usuario (toggle)', 'usa UsuarioCrudService', strpos($toggle, 'UsuarioCrudService') !== false);
        Assert::same('Wiring crud usuario (toggle)', 'delega el cambio de estado', 1, substr_count($toggle, '->actualizarActivo('));
        Assert::same('Wiring crud usuario (toggle)', 'sin SQL inline', 0, preg_match_all($sinSql, $toggle));
        Assert::isTrue('Wiring crud usuario (toggle)', 'conserva guard de permiso', strpos($toggle, 'puedeActivarDesactivarUsuario(') !== false);
    }
}
