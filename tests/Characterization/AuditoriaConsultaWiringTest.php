<?php

declare(strict_types=1);

/**
 * Wiring G1: auditoria/index.php conserva permisos, filtros y vista, pero delega
 * todas las consultas en AuditoriaConsultaService.
 */
final class AuditoriaConsultaWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/auditoria/index.php');
        $phpSource = '';

        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_INLINE_HTML) {
                $phpSource .= is_array($token) ? $token[1] : $token;
            }
        }

        Assert::isTrue('Wiring consulta auditoría', 'index.php legible', $source !== '');
        Assert::isTrue(
            'Wiring consulta auditoría',
            'index usa AuditoriaConsultaService',
            strpos($source, 'AuditoriaConsultaService') !== false
        );
        Assert::same(
            'Wiring consulta auditoría',
            'index consulta existencia de tabla',
            1,
            substr_count($source, '->tablaExiste(')
        );
        Assert::same(
            'Wiring consulta auditoría',
            'index delega listado y opciones',
            1,
            substr_count($source, '->consultar(')
        );
        Assert::same(
            'Wiring consulta auditoría',
            'index sin SQL inline',
            0,
            preg_match_all(
                '/\b(?:SELECT\b[\s\S]{0,500}?\bFROM\b|INSERT\s+INTO\b|UPDATE\s+[`a-z0-9_.]+\s+SET\b|DELETE\s+FROM\b|SHOW\s+TABLES\b)/i',
                $phpSource
            )
        );
        Assert::isTrue(
            'Wiring consulta auditoría',
            'index conserva guards',
            strpos($source, 'requiereSuperadmin()') !== false
                && strpos($source, 'requiereAuditoria()') !== false
        );
    }
}
