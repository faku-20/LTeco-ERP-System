<?php

declare(strict_types=1);

namespace Lteco\Domain\Balance;

use Lteco\Domain\Venta\ReglasComerciales;

/**
 * Cálculos financieros puros del balance (E5). Sin DB ni helpers globales:
 * recibe montos ya expresados en UYU y devuelve los agregados del modelo
 * financiero. Replica EXACTAMENTE las fórmulas y redondeos legacy de
 * balance/index.php y balance/exportar.php — no "mejora" el modelo.
 *
 * Reglas preservadas:
 * - IVA incluido en el bruto: delegado a ReglasComerciales::ivaIncluido().
 * - Ingresos netos / utilidad neta se redondean a 2 decimales.
 * - Utilidad estimada, flujo de caja y resultado mensual NO se redondean
 *   (igual que el legacy).
 */
final class BalanceCalculo
{
    /** IVA extraído del bruto: el precio incluye IVA. */
    public static function ivaDesdeBruto(float $ingresosUyu, float $tasaIVA = 22.0): float
    {
        return ReglasComerciales::ivaIncluido($ingresosUyu, $tasaIVA);
    }

    /** Ingresos sin IVA. */
    public static function ingresosNeto(float $ingresosUyu, float $ivaUyu): float
    {
        return round($ingresosUyu - $ivaUyu, 2);
    }

    /** Utilidad neta: ingresos netos menos gastos. */
    public static function utilidadNeta(float $ingresosNetoUyu, float $gastosUyu): float
    {
        return round($ingresosNetoUyu - $gastosUyu, 2);
    }

    /** Utilidad estimada (sin redondear): ganancia estimada menos gastos. */
    public static function utilidadEstimada(float $gananciaEstimadaUyu, float $gastosUyu): float
    {
        return $gananciaEstimadaUyu - $gastosUyu;
    }

    /** Flujo de caja (sin redondear): cobrado real menos gastos. */
    public static function flujoCaja(float $cobradoUyu, float $gastosUyu): float
    {
        return $cobradoUyu - $gastosUyu;
    }

    /** Resultado de un mes (sin redondear): ganancia del mes menos gastos del mes. */
    public static function resultadoMensual(float $gananciaMesUyu, float $gastosMesUyu): float
    {
        return $gananciaMesUyu - $gastosMesUyu;
    }
}
