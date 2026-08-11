<?php

declare(strict_types=1);

use Lteco\Domain\Venta\ConfiguracionComercial;

final class ConfiguracionComercialVentaTest
{
    public static function run(): void
    {
        $caso = 'Configuración comercial venta';

        Assert::same($caso, 'sin fila usa defaults', [
            'DescuentoContado' => 0.0,
            'RecargoTarjeta' => 0.0,
            'ComisionDistribuidor' => 0.0,
            'ComisionVendedor' => 0.0,
            'TasaIVA' => 22.0,
        ], ConfiguracionComercial::normalizar(null, 22.0));

        Assert::same($caso, 'formulario conserva valores legacy sin sanear', [
            'DescuentoContado' => -1.0,
            'RecargoTarjeta' => 0.0,
            'ComisionDistribuidor' => 0.0,
            'ComisionVendedor' => -5.0,
            'TasaIVA' => 0.0,
        ], ConfiguracionComercial::paraFormulario([
            'DescuentoContado' => -1,
            'RecargoTarjeta' => '',
            'ComisionDistribuidor' => null,
            'ComisionVendedor' => -5,
            'TasaIVA' => 0,
        ], 22.0));

        Assert::same($caso, 'normaliza fila existente', [
            'DescuentoContado' => 5.0,
            'RecargoTarjeta' => 3.5,
            'ComisionDistribuidor' => 6.67,
            'ComisionVendedor' => 2.0,
            'TasaIVA' => 22.0,
        ], ConfiguracionComercial::normalizar([
            'DescuentoContado' => '5',
            'RecargoTarjeta' => '3.5',
            'ComisionDistribuidor' => '6.67',
            'ComisionVendedor' => '2',
            'TasaIVA' => '22',
        ], 18.0));

        Assert::same($caso, 'negativos vacíos e IVA cero usan defaults', [
            'DescuentoContado' => 0.0,
            'RecargoTarjeta' => 0.0,
            'ComisionDistribuidor' => 0.0,
            'ComisionVendedor' => 0.0,
            'TasaIVA' => 22.0,
        ], ConfiguracionComercial::normalizar([
            'DescuentoContado' => -1,
            'RecargoTarjeta' => '',
            'ComisionDistribuidor' => null,
            'ComisionVendedor' => -5,
            'TasaIVA' => 0,
        ], 22.0));

        Assert::same($caso, 'comisión positiva', 7.5, ConfiguracionComercial::comisionNoNegativa('7.5'));
        Assert::same($caso, 'comisión negativa queda en cero', 0.0, ConfiguracionComercial::comisionNoNegativa('-3'));
        Assert::same($caso, 'comisión ausente usa default', 6.67, ConfiguracionComercial::comisionNoNegativa(null, 6.67));
    }
}
