<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

/**
 * Reglas comerciales puras del cálculo de una venta.
 *
 * Lógica de negocio SIN PDO, SIN $_POST, SIN side effects: recibe los importes y
 * porcentajes ya resueltos y devuelve los totales/comisiones de la venta.
 *
 * Origen histórico: este cálculo y el IVA incluido vivían inline en
 * lteco-panel/ventas/guardar.php. Se extraen aquí preservando exactamente el
 * comportamiento; la red de seguridad son los tests de caracterización en
 * tests/Characterization/, validados contra la venta real #1.
 *
 * Reglas que esta clase garantiza:
 * - Transferencia se comporta como Efectivo (ambos "contado").
 * - Efectivo y Transferencia no generan comisión de tarjeta.
 * - El descuento contado solo aplica a métodos contado.
 * - La tarjeta NO aumenta el total cobrado al cliente; su comisión es costo interno
 *   (débito 2%, crédito = porcentaje configurado de recargo).
 * - La comisión de distribuidor se calcula sobre el Total CON IVA.
 * - El IVA va incluido dentro del total.
 */
final class ReglasComerciales
{
    /**
     * @param array<string,mixed> $in Claves:
     *   subtotalBruto, metodoPago ('Efectivo'|'Transferencia'|'Tarjeta'),
     *   tipoTarjeta ('Crédito'|'Débito'|null), tipoCliente ('Final'|'Distribuidor'),
     *   costoTotal, descuentoContadoPct, recargoTarjetaPct,
     *   comisionDistribuidorPct, comisionVendedorPct, tasaIVA.
     * @return array<string,float|bool>
     */
    public static function calcular(array $in): array
    {
        $subtotalBruto = round((float)($in['subtotalBruto'] ?? 0.0), 2);
        $metodoPago    = (string)($in['metodoPago'] ?? 'Efectivo');
        $tipoTarjeta   = $in['tipoTarjeta'] ?? null;
        $tipoCliente   = (string)($in['tipoCliente'] ?? 'Final');
        $costoTotal    = round((float)($in['costoTotal'] ?? 0.0), 2);

        $descuentoContadoPct     = (float)($in['descuentoContadoPct'] ?? 0.0);
        $recargoTarjetaPct       = (float)($in['recargoTarjetaPct'] ?? 0.0);
        $comisionDistribuidorPct = (float)($in['comisionDistribuidorPct'] ?? 0.0);
        $comisionVendedorPct     = (float)($in['comisionVendedorPct'] ?? 0.0);
        $tasaIVA                 = (float)($in['tasaIVA'] ?? 22.0);

        // Transferencia se comporta como Efectivo: ambos son contado.
        $esContado = in_array($metodoPago, self::metodosContado(), true);

        // El descuento contado solo aplica a métodos contado.
        $descuentoAplicado = 0.0;
        if ($esContado && $descuentoContadoPct > 0) {
            $descuentoAplicado = round($subtotalBruto * ($descuentoContadoPct / 100), 2);
        }

        $baseLuegoDescuento = max(0.0, round($subtotalBruto - $descuentoAplicado, 2));

        // La tarjeta NO sube el total cobrado; su comisión es costo financiero interno.
        $recargoAplicado    = 0.0;
        $comisionTarjeta    = 0.0;
        $comisionTarjetaPct = 0.0;
        if ($metodoPago === 'Tarjeta') {
            $recargoAplicado    = 0.0;
            $comisionTarjetaPct = $tipoTarjeta === 'Débito' ? 2.0 : $recargoTarjetaPct;
            $comisionTarjeta    = round($baseLuegoDescuento * ($comisionTarjetaPct / 100), 2);
        }

        $total       = round($baseLuegoDescuento, 2);
        $montoIVA    = self::ivaIncluido($total, $tasaIVA);
        $totalSinIVA = round($total - $montoIVA, 2);

        $comisionVendedor = 0.0;
        if ($comisionVendedorPct > 0) {
            $comisionVendedor = round($total * ($comisionVendedorPct / 100), 2);
        }

        // Comisión de distribuidor sobre el Total CON IVA.
        $comisionDistribuidor = 0.0;
        if ($tipoCliente === 'Distribuidor' && $comisionDistribuidorPct > 0) {
            $comisionDistribuidor = round($total * ($comisionDistribuidorPct / 100), 2);
        }

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

    /**
     * Métodos de pago considerados "contado".
     * Transferencia entra acá a propósito: se comporta igual que Efectivo.
     *
     * @return string[]
     */
    public static function metodosContado(): array
    {
        return ['Efectivo', 'Transferencia'];
    }

    /** IVA incluido dentro del total. */
    public static function ivaIncluido(float $totalConIVA, float $tasaIVA): float
    {
        if ($totalConIVA <= 0 || $tasaIVA <= 0) {
            return 0.0;
        }

        return round($totalConIVA * $tasaIVA / (100 + $tasaIVA), 2);
    }
}
