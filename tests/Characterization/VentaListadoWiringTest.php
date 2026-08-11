<?php

declare(strict_types=1);

final class VentaListadoWiringTest
{
    public static function run(): void
    {
        $index = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/index.php');
        $exportar = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/exportar.php');

        Assert::isTrue('wiring listado', 'index usa repositorio', str_contains($index, 'VentaListadoRepository'));
        Assert::isTrue('wiring listado', 'index usa servicio', str_contains($index, 'VentaListadoService'));
        Assert::isFalse('wiring listado', 'index sin SQL directo', str_contains($index, '$pdo->prepare('));
        Assert::isTrue('wiring listado', 'exportar usa repositorio', str_contains($exportar, 'listarExportacion'));
        Assert::isFalse('wiring listado', 'exportar sin SQL directo', str_contains($exportar, '$pdo->prepare('));
        Assert::isTrue('wiring listado', 'exportar conserva guard admin', str_contains($exportar, 'requiereAdmin();'));
    }
}
