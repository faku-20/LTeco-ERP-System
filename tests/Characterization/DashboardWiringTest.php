<?php

declare(strict_types=1);

final class DashboardWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/dashboard.php');
        $phpSource = '';
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_INLINE_HTML) {
                $phpSource .= is_array($token) ? $token[1] : $token;
            }
        }

        Assert::isTrue('Wiring dashboard', 'dashboard legible', $source !== '');
        Assert::isTrue('Wiring dashboard', 'usa DashboardService', strpos($source, 'DashboardService') !== false);
        Assert::same('Wiring dashboard', 'delega carga una vez', 1, substr_count($source, '->cargar('));
        Assert::same(
            'Wiring dashboard',
            'sin SQL inline',
            0,
            preg_match_all(
                '/\b(?:SELECT\b[\s\S]{0,500}?\bFROM\b|INSERT\s+INTO\b|UPDATE\s+[`a-z0-9_.]+\s+SET\b|DELETE\s+FROM\b)/i',
                $phpSource
            )
        );
        Assert::isTrue(
            'Wiring dashboard',
            'conserva guard y redirect distribuidor',
            strpos($source, "requiereModulo('dashboard')") !== false
                && strpos($source, 'esDistribuidor()') !== false
        );
        Assert::isFalse(
            'Wiring dashboard',
            'elimina include legacy sin consumidores',
            is_file(dirname(__DIR__, 2) . '/lteco-panel/includes/dashboard_logic.php')
        );
        Assert::isFalse(
            'Wiring dashboard',
            'widget de ventas no fuerza columna financiera',
            str_contains($source,'<th>Ganancia</th>')
        );
        Assert::isTrue(
            'Wiring dashboard',
            'empty state de clientes respeta columnas visibles',
            str_contains($source,'$puedeVerFinanzas ? 3 : 2')
        );
    }
}
