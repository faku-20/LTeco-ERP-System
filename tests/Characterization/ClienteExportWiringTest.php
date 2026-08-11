<?php

declare(strict_types=1);

final class ClienteExportWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/clientes/exportar.php');

        Assert::isTrue('Wiring export cliente', 'exportar.php legible', $source !== '');
        Assert::isTrue(
            'Wiring export cliente',
            'exportar usa ClienteConsultaService',
            strpos($source, 'ClienteConsultaService') !== false
        );
        Assert::same(
            'Wiring export cliente',
            'exportar delega el listado',
            1,
            substr_count($source, '->listarParaExport(')
        );
        Assert::same(
            'Wiring export cliente',
            'exportar sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring export cliente',
            'exportar conserva guard admin',
            strpos($source, 'requiereAdmin()') !== false
        );
    }
}
