<?php

declare(strict_types=1);

final class DistribuidorCuentaWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/estado_cuenta.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring cuenta distribuidor', 'estado_cuenta.php legible', $source !== '');
        Assert::isTrue(
            'Wiring cuenta distribuidor',
            'usa DistribuidorCuentaService',
            strpos($source, 'DistribuidorCuentaService') !== false
        );
        Assert::same(
            'Wiring cuenta distribuidor',
            'delega carga de cuenta',
            1,
            substr_count($source, '->cargarCuenta(')
        );
        Assert::same(
            'Wiring cuenta distribuidor',
            'delega cambio de comisión',
            1,
            substr_count($source, '->actualizarComision(')
        );
        Assert::same(
            'Wiring cuenta distribuidor',
            'sin acceso PDO inline',
            0,
            preg_match_all('/\$pdo->(?:prepare|query|exec)\s*\(/', $source)
        );
        Assert::same(
            'Wiring cuenta distribuidor',
            'sin UPDATE comisión inline',
            0,
            substr_count($source, 'UPDATE distribuidor_comision')
        );
        Assert::isTrue(
            'Wiring cuenta distribuidor',
            'conserva guard admin de escritura',
            strpos($source, "Solo administración puede cambiar estados de comisiones.") !== false
        );
        Assert::isTrue(
            'Wiring cuenta distribuidor',
            'conserva auditoría',
            strpos($source, "'COMISION_DISTRIBUIDOR_'") !== false
        );
    }
}
