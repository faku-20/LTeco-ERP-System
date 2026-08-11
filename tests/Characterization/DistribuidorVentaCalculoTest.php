<?php

declare(strict_types=1);

use Lteco\Domain\Distribuidor\VentaDistribuidorCalculo;

/**
 * Congela la matematica inline original de distribuidores/nueva_venta.php.
 */
final class DistribuidorVentaCalculoTest
{
    public static function run(): void
    {
        self::verificarCaso(
            'Vehiculo cantidad 1, comisiones cero, IVA 22%',
            63000.0,
            12000.0,
            1,
            0.0,
            0.0,
            22.0,
            [
                'subtotal' => 63000.00,
                'costoTotal' => 12000.00,
                'comisionDistribuidor' => 0.00,
                'comisionVendedor' => 0.00,
                'montoIVA' => 11360.66,
                'totalSinIVA' => 51639.34,
                'ganancia' => 39639.34,
            ]
        );

        self::verificarCaso(
            'Repuesto cantidad mayor a 1, decimales y ambas comisiones',
            1234.56,
            789.12,
            3,
            6.67,
            2.5,
            22.0,
            [
                'subtotal' => 3703.68,
                'costoTotal' => 2367.36,
                'comisionDistribuidor' => 247.04,
                'comisionVendedor' => 92.59,
                'montoIVA' => 667.88,
                'totalSinIVA' => 3035.80,
                'ganancia' => 328.81,
            ]
        );

        self::verificarCaso(
            'Comision distribuidor positiva y vendedor cero',
            1999.99,
            1500.25,
            1,
            5.5,
            0.0,
            22.0,
            [
                'subtotal' => 1999.99,
                'costoTotal' => 1500.25,
                'comisionDistribuidor' => 110.00,
                'comisionVendedor' => 0.00,
                'montoIVA' => 360.65,
                'totalSinIVA' => 1639.34,
                'ganancia' => 29.09,
            ]
        );

        self::verificarCaso(
            'Comision distribuidor cero y vendedor positivo',
            500.15,
            300.10,
            4,
            0.0,
            3.25,
            22.0,
            [
                'subtotal' => 2000.60,
                'costoTotal' => 1200.40,
                'comisionDistribuidor' => 0.00,
                'comisionVendedor' => 65.02,
                'montoIVA' => 360.76,
                'totalSinIVA' => 1639.84,
                'ganancia' => 374.42,
            ]
        );
    }

    /**
     * @param array<string,float> $golden
     */
    private static function verificarCaso(
        string $caso,
        float $precioVenta,
        float $precioMinimo,
        int $cantidad,
        float $comisionDistribuidorPct,
        float $comisionVendedorPct,
        float $tasaIVA,
        array $golden
    ): void {
        $legacy = self::calcularLegacy(
            $precioVenta,
            $precioMinimo,
            $cantidad,
            $comisionDistribuidorPct,
            $comisionVendedorPct,
            $tasaIVA
        );
        $domain = VentaDistribuidorCalculo::calcular(
            $precioVenta,
            $precioMinimo,
            $cantidad,
            $comisionDistribuidorPct,
            $comisionVendedorPct,
            $tasaIVA
        );

        foreach ($golden as $campo => $valorEsperado) {
            Assert::money($caso . ' [legacy golden]', $campo, $valorEsperado, $legacy[$campo]);
            Assert::money($caso . ' [domain golden]', $campo, $valorEsperado, $domain[$campo]);
            Assert::money($caso . ' [paridad]', $campo, $legacy[$campo], $domain[$campo]);
        }
    }

    /**
     * Replica linea por linea del bloque legacy previo a C2.1.
     *
     * @return array<string,float>
     */
    private static function calcularLegacy(
        float $precioVenta,
        float $precioMinimo,
        int $cantidad,
        float $comisionDistribuidorPct,
        float $comisionVendedorPct,
        float $tasaIVA
    ): array {
        $subtotal = round($precioVenta * $cantidad, 2);
        $costoTotal = round($precioMinimo * $cantidad, 2);
        $comisionDistribuidor = round($subtotal * $comisionDistribuidorPct / 100, 2);
        $comisionVendedor = round($subtotal * $comisionVendedorPct / 100, 2);
        $montoIVA = round($subtotal * $tasaIVA / (100 + $tasaIVA), 2);
        $totalSinIVA = round($subtotal - $montoIVA, 2);
        $ganancia = round($totalSinIVA - $costoTotal - $comisionDistribuidor - $comisionVendedor, 2);

        return [
            'subtotal' => $subtotal,
            'costoTotal' => $costoTotal,
            'comisionDistribuidor' => $comisionDistribuidor,
            'comisionVendedor' => $comisionVendedor,
            'montoIVA' => $montoIVA,
            'totalSinIVA' => $totalSinIVA,
            'ganancia' => $ganancia,
        ];
    }
}
