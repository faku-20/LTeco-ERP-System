<?php

declare(strict_types=1);

use Lteco\Domain\Dashboard\DashboardCalculo;

final class DashboardCalculoTest
{
    public static function run(): void
    {
        $ventas = [
            [
                'EstadoVenta' => 'Confirmada', 'Moneda' => 'USD', 'Total' => 100,
                'MontoPagado' => null, 'SaldoPendiente' => 20, 'GananciaEstimada' => 30,
                'TipoCambioAplicado' => 40, 'FechaVenta' => '2026-06-10',
            ],
            [
                'EstadoVenta' => 'Pendiente', 'Moneda' => 'UYU', 'Total' => 1000,
                'MontoPagado' => 200, 'SaldoPendiente' => 800, 'GananciaEstimada' => 100,
                'TipoCambioAplicado' => 0, 'FechaVenta' => '2026-05-10',
            ],
            ['EstadoVenta' => 'Anulada', 'Total' => 99999, 'FechaVenta' => '2026-06-11'],
        ];
        $resumen = DashboardCalculo::resumenVentas($ventas, 45, '2026-06');

        Assert::same('Dashboard cálculo', 'ventas activas', 2, $resumen['ventas_activas']);
        Assert::same('Dashboard cálculo', 'ventas anuladas', 1, $resumen['ventas_anuladas']);
        Assert::same('Dashboard cálculo', 'ventas pendientes', 1, $resumen['ventas_pendientes']);
        Assert::money('Dashboard cálculo', 'usa TC histórico', 5000, $resumen['ingresos_facturados_uyu']);
        Assert::money('Dashboard cálculo', 'saldo pendiente', 1600, $resumen['saldo_pendiente_uyu']);
        Assert::money('Dashboard cálculo', 'ventas del mes', 4000, $resumen['ventas_mes_uyu']);

        $inventario = DashboardCalculo::inventario([
            [
                'TipoProducto' => 'Moto', 'Stock' => 1, 'Estado' => 'Disponible',
                'MostrarEnWeb' => 1, 'DestacadoWeb' => 1, 'Moneda' => 'USD',
                'PrecioVenta' => 100, 'GastoTotal' => 60, 'TipoCambioImportacion' => 40,
            ],
            [
                'TipoProducto' => 'Moto', 'Stock' => 0, 'Estado' => 'Vendido',
                'Moneda' => 'UYU', 'PrecioVenta' => 1000, 'GastoTotal' => 500,
                'TipoCambioImportacion' => null,
            ],
            [
                'TipoProducto' => 'Repuesto', 'Stock' => 3, 'Estado' => 'Disponible',
                'Moneda' => 'UYU', 'PrecioVenta' => 500, 'GastoTotal' => 200,
                'TipoCambioImportacion' => null,
            ],
        ], 45, true);

        Assert::same('Dashboard cálculo', 'motos disponibles', 1, $inventario['motos_disponibles']);
        Assert::same('Dashboard cálculo', 'motos vendidas', 1, $inventario['motos_vendidas']);
        Assert::same('Dashboard cálculo', 'publicadas web', 1, $inventario['publicadas_web']);
        Assert::same('Dashboard cálculo', 'unidades repuesto', 3, $inventario['repuestos_unidades']);
        Assert::same('Dashboard cálculo', 'repuesto stock bajo', 1, $inventario['repuestos_stock_bajo']);
        Assert::money('Dashboard cálculo', 'inventario venta UYU', 5500, $inventario['inventario_venta_uyu']);
    }
}
