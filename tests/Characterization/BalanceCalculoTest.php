<?php

declare(strict_types=1);

use Lteco\Domain\Balance\BalanceCalculo;

/**
 * Golden numbers E5: congela las fórmulas financieras del balance. El IVA
 * incluido se delega a la regla comercial canónica.
 */
final class BalanceCalculoTest
{
    public static function run(): void
    {
        $caso = 'BalanceCalculo';

        // --- IVA extraído del bruto (precio incluye IVA): ingresos * 22/122 ---
        $iva = BalanceCalculo::ivaDesdeBruto(12345.67);
        Assert::money($caso, 'iva(12345.67)', 2226.27, $iva);
        Assert::money($caso, 'iva(0)', 0.00, BalanceCalculo::ivaDesdeBruto(0.0));
        Assert::money($caso, 'iva configurable 10%', 1122.33, BalanceCalculo::ivaDesdeBruto(12345.67, 10.0));

        // --- ingresos netos = ingresos - iva (redondeado) ---
        Assert::money($caso, 'ingresosNeto(12345.67, 2226.27)', 10119.40, BalanceCalculo::ingresosNeto(12345.67, 2226.27));

        // --- utilidad neta = ingresos netos - gastos (redondeado) ---
        Assert::money($caso, 'utilidadNeta(10119.40, 5000)', 5119.40, BalanceCalculo::utilidadNeta(10119.40, 5000.00));

        // --- utilidad estimada = ganancia - gastos (sin redondear) ---
        Assert::money($caso, 'utilidadEstimada(8000, 5000)', 3000.00, BalanceCalculo::utilidadEstimada(8000.00, 5000.00));
        Assert::money($caso, 'utilidadEstimada negativa (1000, 3000)', -2000.00, BalanceCalculo::utilidadEstimada(1000.00, 3000.00));

        // --- flujo de caja = cobrado - gastos (sin redondear) ---
        Assert::money($caso, 'flujoCaja(10000, 5000)', 5000.00, BalanceCalculo::flujoCaja(10000.00, 5000.00));

        // --- resultado mensual = ganancia mes - gastos mes (sin redondear) ---
        Assert::money($caso, 'resultadoMensual(8000, 5000)', 3000.00, BalanceCalculo::resultadoMensual(8000.00, 5000.00));

        // --- lock de redondeo: IVA/netos SÍ se redondean a 2 decimales ---
        Assert::isTrue($caso, 'iva redondea a 2 decimales', abs(round($iva, 2) - $iva) < 1e-9);

        // --- lock de no-redondeo: utilidad estimada NO se redondea ---
        $sinRedondeo = BalanceCalculo::utilidadEstimada(10.001, 0.0);
        Assert::isTrue($caso, 'utilidadEstimada conserva el valor crudo', abs($sinRedondeo - 10.001) < 1e-12);
        Assert::isTrue($caso, 'utilidadEstimada NO está redondeada a 2 decimales', abs(round($sinRedondeo, 2) - $sinRedondeo) > 1e-9);
    }
}
