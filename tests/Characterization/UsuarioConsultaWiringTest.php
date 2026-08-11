<?php

declare(strict_types=1);

/**
 * Wiring F1: usuarios/index.php delega el listado en UsuarioConsultaService y no
 * conserva SQL inline. Mantiene el guard de gestión de usuarios.
 */
final class UsuarioConsultaWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/usuarios/index.php');
        $phpSource = '';

        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_INLINE_HTML) {
                $phpSource .= is_array($token) ? $token[1] : $token;
            }
        }

        Assert::isTrue('Wiring consulta usuario', 'index.php legible', $source !== '');
        Assert::isTrue('Wiring consulta usuario', 'index usa UsuarioConsultaService', strpos($source, 'UsuarioConsultaService') !== false);
        Assert::same('Wiring consulta usuario', 'index delega el listado', 1, substr_count($source, '->listar('));
        Assert::same(
            'Wiring consulta usuario',
            'index sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $phpSource)
        );
        Assert::isTrue('Wiring consulta usuario', 'index conserva guard de gestión de usuarios', strpos($source, 'requiereGestionUsuarios()') !== false);
    }
}
