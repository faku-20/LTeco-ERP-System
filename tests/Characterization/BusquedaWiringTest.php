<?php

declare(strict_types=1);

final class BusquedaWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/busqueda/index.php');
        $phpSource = '';
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_INLINE_HTML) {
                $phpSource .= is_array($token) ? $token[1] : $token;
            }
        }

        Assert::isTrue('Wiring búsqueda', 'index legible', $source !== '');
        Assert::isTrue('Wiring búsqueda', 'usa BusquedaService', strpos($source, 'BusquedaService') !== false);
        Assert::same('Wiring búsqueda', 'delega consulta una vez', 1, substr_count($source, '->consultar('));
        Assert::same(
            'Wiring búsqueda',
            'sin SQL inline',
            0,
            preg_match_all(
                '/\b(?:SELECT\b[\s\S]{0,500}?\bFROM\b|INSERT\s+INTO\b|UPDATE\s+[`a-z0-9_.]+\s+SET\b|DELETE\s+FROM\b)/i',
                $phpSource
            )
        );
        Assert::isTrue(
            'Wiring búsqueda',
            'conserva guards',
            strpos($source, 'requiereLogin()') !== false && strpos($source, 'requiereNoDistribuidor()') !== false
        );
    }
}
