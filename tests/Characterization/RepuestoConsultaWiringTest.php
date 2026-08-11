<?php

declare(strict_types=1);

final class RepuestoConsultaWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/repuestos/index.php');

        Assert::isTrue('Wiring consulta repuesto', 'index.php legible', $source !== '');
        Assert::isTrue(
            'Wiring consulta repuesto',
            'index usa RepuestoConsultaService',
            strpos($source, 'RepuestoConsultaService') !== false
        );
        Assert::same(
            'Wiring consulta repuesto',
            'index delega el listado',
            1,
            substr_count($source, '->listar(')
        );
        Assert::same(
            'Wiring consulta repuesto',
            'index sin SQL inline',
            0,
            // Lookbehind: ignora <select>/</select> del HTML y métodos JS como items.delete().
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring consulta repuesto',
            'index conserva guard de login',
            strpos($source, 'requiereLogin()') !== false
        );
        Assert::isTrue(
            'Wiring consulta repuesto',
            'index resetea el botón al volver atrás (pageshow/bfcache)',
            strpos($source, "addEventListener('pageshow'") !== false
                && strpos($source, 'event.persisted') !== false
        );
    }
}
