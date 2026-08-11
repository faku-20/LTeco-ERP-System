<?php

declare(strict_types=1);

namespace Lteco\Domain\Distribuidor;

use Lteco\Domain\Venta\ReglasComerciales;

/**
 * Calculo comercial puro del flujo de venta realizada por un distribuidor.
 */
final class VentaDistribuidorCalculo
{
    /**
     * @return array<string,float>
     */
    public static function calcular(
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
        $montoIVA = ReglasComerciales::ivaIncluido($subtotal, $tasaIVA);
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
