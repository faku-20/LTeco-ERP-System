<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

final class VentaListadoService
{
    /**
     * @param array<int,array<string,mixed>> $ventas
     * @param callable(float,string,array<string,mixed>):float $convertirAUyu
     * @return array{cantidad:int,total_uyu:float,ganancia_uyu:float,mes_uyu:float}
     */
    public function resumen(array $ventas, callable $convertirAUyu, string $mesActual): array
    {
        $resumen = [
            'cantidad' => 0,
            'total_uyu' => 0.0,
            'ganancia_uyu' => 0.0,
            'mes_uyu' => 0.0,
        ];

        foreach ($ventas as $venta) {
            if (($venta['EstadoVenta'] ?? 'Confirmada') === 'Anulada') {
                continue;
            }

            $resumen['cantidad']++;
            $moneda = (string) ($venta['Moneda'] ?? 'UYU');
            $totalU = $convertirAUyu((float) ($venta['Total'] ?? 0), $moneda, $venta);
            $gananciaU = $convertirAUyu((float) ($venta['GananciaEstimada'] ?? 0), $moneda, $venta);

            $resumen['total_uyu'] += $totalU;
            $resumen['ganancia_uyu'] += $gananciaU;

            if (str_starts_with((string) ($venta['FechaVenta'] ?? ''), $mesActual)) {
                $resumen['mes_uyu'] += $totalU;
            }
        }

        return $resumen;
    }
}
