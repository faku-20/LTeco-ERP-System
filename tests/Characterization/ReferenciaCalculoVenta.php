<?php

declare(strict_types=1);

/**
 * RÉPLICA DE CARACTERIZACIÓN — NO ES CÓDIGO PRODUCTIVO.
 *
 * Transcribe, línea por línea, el bloque de cálculo de dinero que hoy vive
 * inline dentro de lteco-panel/ventas/guardar.php (líneas 575-624) y la función
 * el antiguo helper calcularIvaIncluidoVenta().
 *
 * Existe solo porque esa lógica todavía NO es invocable desde un test: está
 * mezclada con $_POST, PDO y la transacción dentro del try de guardar.php.
 *
 * Cuando se extraiga Lteco\Domain\Venta\ReglasComerciales, los tests deben
 * re-apuntarse a esa clase y ESTE ARCHIVO SE BORRA. Los números de oro que
 * fijan los tests (validados contra la venta real #1) son los que ReglasComerciales
 * tendrá que reproducir exactamente.
 *
 * Cualquier cambio de comportamiento aquí debe reflejar primero un cambio real
 * en guardar.php, nunca al revés.
 */
final class ReferenciaCalculoVenta
{
    /**
     * @param array<string,mixed> $in
     * @return array<string,float|bool>
     */
    public static function calcular(array $in): array
    {
        $subtotalBruto = round((float)($in['subtotalBruto'] ?? 0.0), 2);
        $metodoPago    = (string)($in['metodoPago'] ?? 'Efectivo');
        $tipoTarjeta   = $in['tipoTarjeta'] ?? null;            // 'Crédito' | 'Débito' | null
        $tipoCliente   = (string)($in['tipoCliente'] ?? 'Final');
        $costoTotal    = round((float)($in['costoTotal'] ?? 0.0), 2);

        $descuentoContadoPct     = (float)($in['descuentoContadoPct'] ?? 0.0);
        $recargoTarjetaPct       = (float)($in['recargoTarjetaPct'] ?? 0.0);
        $comisionDistribuidorPct = (float)($in['comisionDistribuidorPct'] ?? 0.0);
        $comisionVendedorPct     = (float)($in['comisionVendedorPct'] ?? 0.0);
        $tasaIVA                 = (float)($in['tasaIVA'] ?? 22.0);

        // guardar.php:108 — Transferencia se comporta como Efectivo (ambos contado).
        $metodosContado = ['Efectivo', 'Transferencia'];
        $esContado = in_array($metodoPago, $metodosContado, true);

        // guardar.php:580-588 — descuento contado solo aplica a métodos contado.
        $descuentoAplicado = 0.0;
        if ($esContado && $descuentoContadoPct > 0) {
            $descuentoAplicado = round($subtotalBruto * ($descuentoContadoPct / 100), 2);
        }

        // guardar.php:590
        $baseLuegoDescuento = max(0.0, round($subtotalBruto - $descuentoAplicado, 2));

        // guardar.php:582-603 — la tarjeta NO sube el total cobrado al cliente;
        // su comisión es costo financiero interno. Débito 2%, crédito = RecargoTarjeta.
        $recargoAplicado    = 0.0;
        $comisionTarjeta    = 0.0;
        $comisionTarjetaPct = 0.0;
        if ($metodoPago === 'Tarjeta') {
            $recargoAplicado    = 0.0;
            $comisionTarjetaPct = $tipoTarjeta === 'Débito' ? 2.0 : $recargoTarjetaPct;
            $comisionTarjeta    = round($baseLuegoDescuento * ($comisionTarjetaPct / 100), 2);
        }

        // guardar.php:605-607
        $total       = round($baseLuegoDescuento, 2);
        $montoIVA    = self::ivaIncluido($total, $tasaIVA);
        $totalSinIVA = round($total - $montoIVA, 2);

        // guardar.php:609-611
        $comisionVendedor = 0.0;
        if ($comisionVendedorPct > 0) {
            $comisionVendedor = round($total * ($comisionVendedorPct / 100), 2);
        }

        // guardar.php:613-622 — comisión distribuidor sobre el TOTAL CON IVA.
        $comisionDistribuidor = 0.0;
        if ($tipoCliente === 'Distribuidor' && $comisionDistribuidorPct > 0) {
            $comisionDistribuidor = round($total * ($comisionDistribuidorPct / 100), 2);
        }

        // guardar.php:624
        $ganancia = round(
            $totalSinIVA - $costoTotal - $comisionTarjeta - $comisionDistribuidor - $comisionVendedor,
            2
        );

        return [
            'esContado'            => $esContado,
            'descuentoAplicado'    => $descuentoAplicado,
            'baseLuegoDescuento'   => $baseLuegoDescuento,
            'recargoAplicado'      => $recargoAplicado,
            'comisionTarjetaPct'   => $comisionTarjetaPct,
            'comisionTarjeta'      => $comisionTarjeta,
            'total'                => $total,
            'montoIVA'             => $montoIVA,
            'totalSinIVA'          => $totalSinIVA,
            'comisionVendedor'     => $comisionVendedor,
            'comisionDistribuidor' => $comisionDistribuidor,
            'ganancia'             => $ganancia,
        ];
    }

    /** guardar.php:878-885 — IVA incluido dentro del total. */
    private static function ivaIncluido(float $totalConIVA, float $tasaIVA): float
    {
        if ($totalConIVA <= 0 || $tasaIVA <= 0) {
            return 0.0;
        }

        return round($totalConIVA * $tasaIVA / (100 + $tasaIVA), 2);
    }
}
