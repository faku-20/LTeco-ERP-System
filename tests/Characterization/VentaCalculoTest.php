<?php

declare(strict_types=1);

use Lteco\Domain\Venta\ReglasComerciales;

/**
 * Tests de caracterización de los cálculos de venta.
 *
 * Los casos principales validan la CLASE REAL Lteco\Domain\Venta\ReglasComerciales.
 * Además, cada caso hace un chequeo de PARIDAD contra ReferenciaCalculoVenta (la
 * réplica congelada de guardar.php): ambas implementaciones deben dar idéntico.
 * Esa réplica es temporal; cuando ya no aporte confianza se borra junto con su test.
 *
 * Números de oro validados contra la venta real #1:
 *   Total 63.000 UYU, Mastercard Crédito, comisión tarjeta 5%, comisión vendedor 10%,
 *   TotalSinIVA 51.639,34, MontoIVA 11.360,66, ganancia real 42.189,34.
 */
final class VentaCalculoTest
{
    public static function run(): void
    {
        self::caso1_tarjeta();
        self::caso2_transferencia_es_contado();
        self::caso3_efectivo_es_contado();
        self::caso4_distribuidor_comision_sobre_total_con_iva();
        self::caso5_tarjeta_no_es_contado();
    }

    /**
     * Ejecuta la clase real y verifica que la réplica congelada da idéntico.
     *
     * @param array<string,mixed> $in
     * @return array<string,float|bool>
     */
    private static function calcular(string $caso, array $in): array
    {
        $real      = ReglasComerciales::calcular($in);
        $congelado = ReferenciaCalculoVenta::calcular($in);

        foreach ($real as $campo => $valor) {
            if (is_bool($valor)) {
                Assert::isTrue($caso . ' [paridad]', $campo, $valor === ($congelado[$campo] ?? null));
            } else {
                Assert::money($caso . ' [paridad]', $campo, (float)($congelado[$campo] ?? 0.0), (float)$valor);
            }
        }

        return $real;
    }

    /** Caso 1 — Venta real #1: tarjeta Mastercard Crédito. */
    private static function caso1_tarjeta(): void
    {
        $caso = 'Caso 1 (tarjeta crédito, venta real #1)';
        $r = self::calcular($caso, [
            'subtotalBruto'       => 63000.0,
            'metodoPago'          => 'Tarjeta',
            'tipoTarjeta'         => 'Crédito',
            'tipoCliente'         => 'Final',
            'recargoTarjetaPct'   => 5.0,
            'comisionVendedorPct' => 10.0,
            'tasaIVA'             => 22.0,
        ]);

        Assert::isFalse($caso, 'esContado', (bool)$r['esContado']);
        Assert::money($caso, 'total', 63000.00, (float)$r['total']);
        Assert::money($caso, 'comisionTarjeta', 3150.00, (float)$r['comisionTarjeta']);
        Assert::money($caso, 'comisionVendedor', 6300.00, (float)$r['comisionVendedor']);
        Assert::money($caso, 'comisionDistribuidor', 0.00, (float)$r['comisionDistribuidor']);
        Assert::money($caso, 'montoIVA', 11360.66, (float)$r['montoIVA']);
        Assert::money($caso, 'totalSinIVA', 51639.34, (float)$r['totalSinIVA']);
        Assert::money($caso, 'recargoAplicado', 0.00, (float)$r['recargoAplicado']);
        Assert::money($caso, 'ganancia', 42189.34, (float)$r['ganancia']);
    }

    /** Caso 2 — Transferencia se comporta como Efectivo: contado, sin comisión tarjeta. */
    private static function caso2_transferencia_es_contado(): void
    {
        $caso = 'Caso 2 (transferencia = contado)';
        $r = self::calcular($caso, [
            'subtotalBruto'       => 63000.0,
            'metodoPago'          => 'Transferencia',
            'tipoCliente'         => 'Final',
            'descuentoContadoPct' => 5.0,   // debe aplicar, igual que efectivo
            'recargoTarjetaPct'   => 5.0,   // no debe usarse: no es tarjeta
            'comisionVendedorPct' => 10.0,
            'tasaIVA'             => 22.0,
        ]);

        Assert::isTrue($caso, 'esContado', (bool)$r['esContado']);
        Assert::money($caso, 'comisionTarjeta', 0.00, (float)$r['comisionTarjeta']);
        Assert::money($caso, 'comisionTarjetaPct', 0.00, (float)$r['comisionTarjetaPct']);
        Assert::money($caso, 'descuentoAplicado (contado)', 3150.00, (float)$r['descuentoAplicado']);
    }

    /** Caso 3 — Efectivo: contado, sin comisión tarjeta. */
    private static function caso3_efectivo_es_contado(): void
    {
        $caso = 'Caso 3 (efectivo = contado)';
        $r = self::calcular($caso, [
            'subtotalBruto'       => 63000.0,
            'metodoPago'          => 'Efectivo',
            'tipoCliente'         => 'Final',
            'descuentoContadoPct' => 5.0,
            'tasaIVA'             => 22.0,
        ]);

        Assert::isTrue($caso, 'esContado', (bool)$r['esContado']);
        Assert::money($caso, 'comisionTarjeta', 0.00, (float)$r['comisionTarjeta']);
        Assert::money($caso, 'descuentoAplicado (contado)', 3150.00, (float)$r['descuentoAplicado']);
    }

    /** Caso 4 — Distribuidor: comisión 6.67% sobre el total CON IVA = 4.202,10. */
    private static function caso4_distribuidor_comision_sobre_total_con_iva(): void
    {
        $caso = 'Caso 4 (comisión distribuidor 6.67% s/total con IVA)';
        $r = self::calcular($caso, [
            'subtotalBruto'           => 63000.0,
            'metodoPago'              => 'Efectivo',
            'tipoCliente'             => 'Distribuidor',
            'comisionDistribuidorPct' => 6.67,
            'tasaIVA'                 => 22.0,
        ]);

        Assert::money($caso, 'total (con IVA)', 63000.00, (float)$r['total']);
        Assert::money($caso, 'comisionDistribuidor', 4202.10, (float)$r['comisionDistribuidor']);
    }

    /** Caso 5 — Tarjeta NO es contado: no recibe descuento contado. */
    private static function caso5_tarjeta_no_es_contado(): void
    {
        $caso = 'Caso 5 (tarjeta no es contado)';
        $r = self::calcular($caso, [
            'subtotalBruto'       => 63000.0,
            'metodoPago'          => 'Tarjeta',
            'tipoTarjeta'         => 'Crédito',
            'tipoCliente'         => 'Final',
            'descuentoContadoPct' => 5.0,   // NO debe aplicar en tarjeta
            'recargoTarjetaPct'   => 5.0,
            'tasaIVA'             => 22.0,
        ]);

        Assert::isFalse($caso, 'esContado', (bool)$r['esContado']);
        Assert::money($caso, 'descuentoAplicado (no contado)', 0.00, (float)$r['descuentoAplicado']);
    }
}
