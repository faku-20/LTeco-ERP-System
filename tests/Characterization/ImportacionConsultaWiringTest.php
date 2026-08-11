<?php

declare(strict_types=1);

/**
 * Wiring E3: importaciones/index.php delega el listado en
 * ImportacionConsultaService y no conserva SQL inline.
 */
final class ImportacionConsultaWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/importaciones/index.php');

        Assert::isTrue('Wiring consulta importacion', 'index.php legible', $source !== '');
        Assert::isTrue(
            'Wiring consulta importacion',
            'index usa ImportacionConsultaService',
            strpos($source, 'ImportacionConsultaService') !== false
        );
        Assert::same('Wiring consulta importacion', 'index delega el listado', 1, substr_count($source, '->listar('));
        Assert::same(
            'Wiring consulta importacion',
            'index sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring consulta importacion',
            'index conserva guards de admin',
            strpos($source, 'requiereLogin()') !== false && strpos($source, 'requiereAdmin()') !== false
        );
    }
}
