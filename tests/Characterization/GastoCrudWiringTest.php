<?php

declare(strict_types=1);

/**
 * Wiring E2: gastos/guardar.php y gastos/editar.php delegan las escrituras en
 * GastoCrudService y no conservan SQL inline. Conservan CSRF y auditoría.
 */
final class GastoCrudWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/gastos/';

        // --- guardar.php (alta) ---
        $guardar = (string) @file_get_contents($panel . 'guardar.php');
        Assert::isTrue('Wiring crud gasto (guardar.php)', 'guardar.php legible', $guardar !== '');
        Assert::isTrue(
            'Wiring crud gasto (guardar.php)',
            'guardar usa GastoCrudService',
            strpos($guardar, 'GastoCrudService') !== false
        );
        Assert::same('Wiring crud gasto (guardar.php)', 'guardar delega el alta', 1, substr_count($guardar, '->crear('));
        Assert::same(
            'Wiring crud gasto (guardar.php)',
            'guardar sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $guardar)
        );
        Assert::isTrue('Wiring crud gasto (guardar.php)', 'guardar conserva CSRF', strpos($guardar, 'verifyCsrfOrFail()') !== false);
        Assert::isTrue('Wiring crud gasto (guardar.php)', 'guardar conserva auditoría', strpos($guardar, "registrarAuditoria") !== false);

        // --- editar.php (lectura + edición) ---
        $editar = (string) @file_get_contents($panel . 'editar.php');
        Assert::isTrue('Wiring crud gasto (editar.php)', 'editar.php legible', $editar !== '');
        Assert::isTrue(
            'Wiring crud gasto (editar.php)',
            'editar usa GastoCrudService',
            strpos($editar, 'GastoCrudService') !== false
        );
        Assert::same('Wiring crud gasto (editar.php)', 'editar delega la lectura', 1, substr_count($editar, '->obtener('));
        Assert::same('Wiring crud gasto (editar.php)', 'editar delega la edición', 1, substr_count($editar, '->editar('));
        Assert::same(
            'Wiring crud gasto (editar.php)',
            'editar sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $editar)
        );
        Assert::isTrue('Wiring crud gasto (editar.php)', 'editar conserva CSRF', strpos($editar, 'verifyCsrfOrFail()') !== false);
        Assert::isTrue('Wiring crud gasto (editar.php)', 'editar conserva auditoría', strpos($editar, "registrarAuditoria") !== false);
    }
}
