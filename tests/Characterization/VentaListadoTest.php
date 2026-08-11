<?php

declare(strict_types=1);

use Lteco\Application\Venta\VentaListadoFiltros;
use Lteco\Application\Venta\VentaListadoService;

final class VentaListadoTest
{
    public static function run(): void
    {
        $filtros = VentaListadoFiltros::desdeInput([
            'estado' => ' Confirmada ',
            'metodo' => ' Tarjeta ',
            'cliente' => ' Ana ',
            'desde' => ' 2026-01-01 ',
            'hasta' => ' 2026-01-31 ',
        ]);
        $condicion = $filtros->condicion(7);

        Assert::isTrue('filtros listado', 'limita al vendedor', str_contains($condicion['sql'], 'v.UsuarioVendedorId = :usuario_vendedor_id'));
        Assert::isTrue('filtros listado', 'busca por RUT', str_contains($condicion['sql'], 'c.RUT LIKE :cliente_rut'));
        Assert::same('filtros listado', 'parámetro de vendedor', 7, $condicion['params'][':usuario_vendedor_id']);
        // Cada campo usa su propio placeholder (no se reusa :cliente => HY093),
        // todos enlazados al mismo comodín.
        Assert::same('filtros listado', 'comodines de cliente (nombre)', '%Ana%', $condicion['params'][':cliente_nombre']);
        Assert::same('filtros listado', 'comodines de cliente (rut)', '%Ana%', $condicion['params'][':cliente_rut']);
        Assert::isTrue('filtros listado', 'placeholder :cliente no se reusa', !str_contains($condicion['sql'], 'LIKE :cliente)') && !str_contains($condicion['sql'], 'LIKE :cliente '));
        Assert::same('filtros listado', 'query export normalizada', 'Confirmada', $filtros->paraQuery()['estado']);

        $sinFiltros = VentaListadoFiltros::desdeInput([])->condicion();
        Assert::same('filtros listado', 'sin filtros no agrega WHERE', '', $sinFiltros['sql']);
        Assert::same('filtros listado', 'sin filtros no agrega parámetros', [], $sinFiltros['params']);

        $ventas = [
            [
                'EstadoVenta' => 'Confirmada',
                'Total' => 100,
                'GananciaEstimada' => 25,
                'Moneda' => 'UYU',
                'FechaVenta' => '2026-06-02 12:00:00',
            ],
            [
                'EstadoVenta' => 'Anulada',
                'Total' => 900,
                'GananciaEstimada' => 400,
                'Moneda' => 'UYU',
                'FechaVenta' => '2026-06-03 12:00:00',
            ],
            [
                'EstadoVenta' => null,
                'Total' => 10,
                'GananciaEstimada' => 2,
                'Moneda' => 'USD',
                'FechaVenta' => '2026-05-20 12:00:00',
            ],
        ];
        $resumen = (new VentaListadoService())->resumen(
            $ventas,
            static fn (float $monto, string $moneda, array $venta): float => $moneda === 'USD'
                ? $monto * 40
                : $monto,
            '2026-06'
        );

        Assert::same('resumen listado', 'cantidad', 2, $resumen['cantidad']);
        Assert::same('resumen listado', 'total UYU', 500.0, $resumen['total_uyu']);
        Assert::same('resumen listado', 'ganancia UYU', 105.0, $resumen['ganancia_uyu']);
        Assert::same('resumen listado', 'mes UYU', 100.0, $resumen['mes_uyu']);
    }
}
