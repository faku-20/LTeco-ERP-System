<?php

declare(strict_types=1);

/**
 * Wiring E1: gastos/index.php y gastos/exportar.php delegan el listado en
 * GastoConsultaService y no conservan SQL inline.
 */
final class GastoConsultaWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/gastos/';

        foreach (['index.php', 'exportar.php'] as $archivo) {
            $source = (string) @file_get_contents($panel . $archivo);
            $etiqueta = 'Wiring consulta gasto (' . $archivo . ')';

            Assert::isTrue($etiqueta, "{$archivo} legible", $source !== '');
            Assert::isTrue(
                $etiqueta,
                "{$archivo} usa GastoConsultaService",
                strpos($source, 'GastoConsultaService') !== false
            );
            Assert::same(
                $etiqueta,
                "{$archivo} delega el listado",
                1,
                substr_count($source, '->listar(')
            );
            Assert::same(
                $etiqueta,
                "{$archivo} sin SQL inline",
                0,
                preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
            );
            Assert::isTrue(
                $etiqueta,
                "{$archivo} conserva guards de admin",
                strpos($source, 'requiereLogin()') !== false
                    && strpos($source, 'requiereAdmin()') !== false
            );
        }
    }
}
