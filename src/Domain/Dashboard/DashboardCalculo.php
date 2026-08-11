<?php

declare(strict_types=1);

namespace Lteco\Domain\Dashboard;

/**
 * Cálculos puros del dashboard visible. Replica las conversiones y agregados
 * legacy sin depender de helpers globales ni base de datos.
 */
final class DashboardCalculo
{
    /**
     * @param list<array<string,mixed>> $ventas
     * @return array<string,int|float>
     */
    public static function resumenVentas(array $ventas, float $tipoCambio, string $mesActual): array
    {
        $resumen = [
            'ventas_activas' => 0,
            'ventas_anuladas' => 0,
            'ventas_pendientes' => 0,
            'ingresos_facturados_uyu' => 0.0,
            'cobrado_uyu' => 0.0,
            'saldo_pendiente_uyu' => 0.0,
            'ganancia_estimada_uyu' => 0.0,
            'ventas_mes_uyu' => 0.0,
            'ganancia_mes_uyu' => 0.0,
            'ticket_promedio_uyu' => 0.0,
            'margen_estimado_pct' => 0.0,
        ];

        foreach ($ventas as $venta) {
            $estado = (string) ($venta['EstadoVenta'] ?? 'Confirmada');
            if ($estado === 'Anulada') {
                $resumen['ventas_anuladas']++;
                continue;
            }
            if ($estado === 'Pendiente') {
                $resumen['ventas_pendientes']++;
            }
            $resumen['ventas_activas']++;

            $moneda = (string) ($venta['Moneda'] ?? 'UYU');
            $total = self::ventaAUyu((float) ($venta['Total'] ?? 0), $moneda, $venta, $tipoCambio);
            $pagadoBase = $venta['MontoPagado'] !== null
                ? (float) $venta['MontoPagado']
                : (float) ($venta['Total'] ?? 0);
            $pagado = self::ventaAUyu($pagadoBase, $moneda, $venta, $tipoCambio);
            $saldo = self::ventaAUyu((float) ($venta['SaldoPendiente'] ?? 0), $moneda, $venta, $tipoCambio);
            $ganancia = self::ventaAUyu((float) ($venta['GananciaEstimada'] ?? 0), $moneda, $venta, $tipoCambio);

            $resumen['ingresos_facturados_uyu'] += $total;
            $resumen['cobrado_uyu'] += $pagado;
            $resumen['saldo_pendiente_uyu'] += $saldo;
            $resumen['ganancia_estimada_uyu'] += $ganancia;

            if (substr((string) ($venta['FechaVenta'] ?? ''), 0, 7) === $mesActual) {
                $resumen['ventas_mes_uyu'] += $total;
                $resumen['ganancia_mes_uyu'] += $ganancia;
            }
        }

        if ($resumen['ventas_activas'] > 0) {
            $resumen['ticket_promedio_uyu'] =
                $resumen['ingresos_facturados_uyu'] / $resumen['ventas_activas'];
        }
        if (abs($resumen['ingresos_facturados_uyu']) >= 0.00001) {
            $resumen['margen_estimado_pct'] = round(
                ($resumen['ganancia_estimada_uyu'] / $resumen['ingresos_facturados_uyu']) * 100,
                1
            );
        }

        return $resumen;
    }

    /**
     * @param list<array<string,mixed>> $productos
     * @return array<string,int|float>
     */
    public static function inventario(array $productos, float $tipoCambio, bool $gestionaWeb): array
    {
        $inventario = [
            'motos_total' => 0,
            'motos_disponibles' => 0,
            'motos_reservadas' => 0,
            'motos_vendidas' => 0,
            'motos_ocultas' => 0,
            'motos_sin_stock' => 0,
            'publicadas_web' => 0,
            'destacadas_web' => 0,
            'repuestos_sku' => 0,
            'repuestos_unidades' => 0,
            'repuestos_stock_bajo' => 0,
            'inventario_venta_uyu' => 0.0,
            'inventario_costo_uyu' => 0.0,
            'margen_potencial_uyu' => 0.0,
        ];

        foreach ($productos as $producto) {
            $tipo = (string) ($producto['TipoProducto'] ?? '');
            $stock = (int) ($producto['Stock'] ?? 0);
            $estado = (string) ($producto['Estado'] ?? '');
            $moneda = (string) ($producto['Moneda'] ?? 'UYU');
            $tcProducto = (float) ($producto['TipoCambioImportacion'] ?: $tipoCambio);

            if ($stock > 0 && $estado !== 'Vendido') {
                $inventario['inventario_venta_uyu'] +=
                    self::aUyu((float) ($producto['PrecioVenta'] ?? 0), $moneda, $tcProducto) * $stock;
                $inventario['inventario_costo_uyu'] +=
                    self::aUyu((float) ($producto['GastoTotal'] ?? 0), $moneda, $tcProducto) * $stock;
            }

            if ($tipo === 'Moto') {
                $inventario['motos_total']++;
                if ($estado === 'Disponible' && $stock > 0) {
                    $inventario['motos_disponibles']++;
                    if ($gestionaWeb) {
                        if ((int) ($producto['MostrarEnWeb'] ?? 0) === 1) {
                            $inventario['publicadas_web']++;
                        }
                        if ((int) ($producto['DestacadoWeb'] ?? 0) === 1) {
                            $inventario['destacadas_web']++;
                        }
                    }
                } elseif ($estado === 'Reservado') {
                    $inventario['motos_reservadas']++;
                } elseif ($estado === 'Vendido') {
                    $inventario['motos_vendidas']++;
                } elseif ($estado === 'Oculto') {
                    $inventario['motos_ocultas']++;
                } elseif ($estado === 'Sin stock' || $stock <= 0) {
                    $inventario['motos_sin_stock']++;
                }
            } elseif ($tipo === 'Repuesto') {
                $inventario['repuestos_sku']++;
                $inventario['repuestos_unidades'] += $stock;
                if ($stock > 0 && $stock <= 3) {
                    $inventario['repuestos_stock_bajo']++;
                }
            }
        }

        $inventario['margen_potencial_uyu'] =
            $inventario['inventario_venta_uyu'] - $inventario['inventario_costo_uyu'];
        return $inventario;
    }

    /**
     * @param list<array<string,mixed>> $filas
     * @return array{cantidad:int,detalle:list<array<string,mixed>>}
     */
    public static function deudas(array $filas, float $tipoCambio): array
    {
        $porCliente = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['IdCliente'] ?? 0);
            if (!isset($porCliente[$id])) {
                $porCliente[$id] = [
                    'NombreApellido' => $fila['NombreApellido'] ?? 'Cliente',
                    'TotalDeudaUYU' => 0.0,
                ];
            }
            $porCliente[$id]['TotalDeudaUYU'] += self::ventaAUyu(
                (float) ($fila['SaldoPendiente'] ?? 0),
                (string) ($fila['Moneda'] ?? 'UYU'),
                $fila,
                $tipoCambio
            );
        }
        usort($porCliente, static fn(array $a, array $b): int =>
            $b['TotalDeudaUYU'] <=> $a['TotalDeudaUYU']
        );
        return ['cantidad' => count($porCliente), 'detalle' => array_slice($porCliente, 0, 5)];
    }

    /**
     * @param list<array<string,mixed>> $filas
     * @return list<array<string,mixed>>
     */
    public static function topClientes(array $filas, float $tipoCambio): array
    {
        $clientes = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['IdCliente'] ?? 0);
            if (!isset($clientes[$id])) {
                $clientes[$id] = [
                    'NombreApellido' => $fila['NombreApellido'] ?? '-',
                    'Telefono' => $fila['Telefono'] ?? '-',
                    'Compras' => 0,
                    'TotalGastadoUYU' => 0.0,
                ];
            }
            $clientes[$id]['Compras']++;
            $clientes[$id]['TotalGastadoUYU'] += self::ventaAUyu(
                (float) ($fila['Total'] ?? 0),
                (string) ($fila['Moneda'] ?? 'UYU'),
                $fila,
                $tipoCambio
            );
        }
        $clientes = array_values($clientes);
        usort($clientes, static fn(array $a, array $b): int =>
            ($b['Compras'] <=> $a['Compras'])
                ?: ($b['TotalGastadoUYU'] <=> $a['TotalGastadoUYU'])
        );
        return array_slice($clientes, 0, 5);
    }

    private static function ventaAUyu(float $monto, string $moneda, array $venta, float $tipoCambio): float
    {
        $historico = (float) ($venta['TipoCambioAplicado'] ?? 0);
        return self::aUyu($monto, $moneda, $historico > 0 ? $historico : $tipoCambio);
    }

    private static function aUyu(float $monto, string $moneda, float $tipoCambio): float
    {
        return strtoupper(trim($moneda ?: 'UYU')) === 'USD' ? $monto * $tipoCambio : $monto;
    }
}
