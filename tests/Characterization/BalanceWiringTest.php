<?php

declare(strict_types=1);

/**
 * Wiring E5: balance/index.php y balance/exportar.php delegan las lecturas en
 * BalanceService, usan el dominio BalanceCalculo para las fórmulas y no
 * conservan SQL inline.
 */
final class BalanceWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/balance/';

        // --- index.php (resumen + gráficos) ---
        $index = (string) @file_get_contents($panel . 'index.php');
        Assert::isTrue('Wiring balance (index.php)', 'index.php legible', $index !== '');
        Assert::isTrue('Wiring balance (index.php)', 'index usa BalanceService', strpos($index, 'BalanceService') !== false);
        Assert::isTrue('Wiring balance (index.php)', 'index usa BalanceCalculo', strpos($index, 'BalanceCalculo') !== false);
        Assert::same('Wiring balance (index.php)', 'index delega ventas resumen', 1, substr_count($index, '->ventasResumen('));
        Assert::same('Wiring balance (index.php)', 'index delega gastos resumen', 1, substr_count($index, '->gastosResumen('));
        Assert::same(
            'Wiring balance (index.php)',
            'index sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $index)
        );
        Assert::isTrue(
            'Wiring balance (index.php)',
            'index conserva guards de admin',
            strpos($index, 'requiereLogin()') !== false && strpos($index, 'requiereAdmin()') !== false
        );

        // --- exportar.php (CSV) ---
        $export = (string) @file_get_contents($panel . 'exportar.php');
        Assert::isTrue('Wiring balance (exportar.php)', 'exportar.php legible', $export !== '');
        Assert::isTrue('Wiring balance (exportar.php)', 'exportar usa BalanceService', strpos($export, 'BalanceService') !== false);
        Assert::isTrue('Wiring balance (exportar.php)', 'exportar usa BalanceCalculo', strpos($export, 'BalanceCalculo') !== false);
        Assert::same('Wiring balance (exportar.php)', 'exportar delega ventas export', 1, substr_count($export, '->ventasExport('));
        Assert::same('Wiring balance (exportar.php)', 'exportar delega gastos export', 1, substr_count($export, '->gastosExport('));
        Assert::same(
            'Wiring balance (exportar.php)',
            'exportar sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $export)
        );
    }
}
