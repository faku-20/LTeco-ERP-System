<?php

declare(strict_types=1);

use Lteco\Application\Venta\VentaPersistenceData;
use Lteco\Infrastructure\Repository\VentaRepository;

/**
 * Congela los mapas de persistencia que guardar.php enviaba al repositorio.
 */
final class VentaPersistenceDataTest
{
    public static function run(): void
    {
        self::cabecera();
        self::detalle();
        self::cierre();
    }

    private static function cabecera(): void
    {
        $caso = 'Persistencia venta - cabecera';
        $datos = VentaPersistenceData::cabecera([
            'clienteId' => 25,
            'metodoPago' => 'Tarjeta',
            'tipoCliente' => 'Distribuidor',
            'distribuidorId' => 7,
            'moneda' => 'USD',
            'observaciones' => 'Prueba',
            'tipoCambio' => 41.75,
            'tipoTarjeta' => 'Crédito',
            'marcaTarjeta' => 'Mastercard',
            'cuotasTarjeta' => 6,
            'usuarioVendedorId' => 4,
        ]);

        Assert::same($caso, 'mapa completo', [
            'Cliente_IdCliente' => 25,
            'FechaVenta' => VentaRepository::AHORA,
            'MetodoPago' => 'Tarjeta',
            'TipoCliente' => 'Distribuidor',
            'Distribuidor_IdDistribuidor' => 7,
            'Total' => 0,
            'GananciaEstimada' => 0,
            'Moneda' => 'USD',
            'Observaciones' => 'Prueba',
            'TipoCambioAplicado' => 41.75,
            'TipoTarjeta' => 'Crédito',
            'MarcaTarjeta' => 'Mastercard',
            'CuotasTarjeta' => 6,
            'EstadoVenta' => 'Confirmada',
            'UsuarioVendedorId' => 4,
        ], $datos);

        Assert::same(
            $caso,
            'venta final ignora distribuidor',
            null,
            VentaPersistenceData::cabecera([
                'clienteId' => 25,
                'metodoPago' => 'Efectivo',
                'tipoCliente' => 'Final',
                'distribuidorId' => 7,
                'moneda' => 'UYU',
                'tipoCambio' => 40.0,
                'usuarioVendedorId' => 4,
            ])['Distribuidor_IdDistribuidor']
        );
    }

    private static function detalle(): void
    {
        Assert::same('Persistencia venta - detalle', 'mapa completo', [
            'Venta_IdVenta' => 10,
            'Producto_IdProducto' => 20,
            'Cantidad' => 2,
            'PrecioUnitario' => 1500.5,
            'CostoUnitario' => 900.25,
            'Subtotal' => 3001.0,
            'GananciaLinea' => 1200.5,
            'Moneda' => 'UYU',
        ], VentaPersistenceData::detalle([
            'ventaId' => 10,
            'productoId' => 20,
            'cantidad' => 2,
            'precioUnitario' => 1500.5,
            'costoUnitario' => 900.25,
            'subtotal' => 3001.0,
            'gananciaLinea' => 1200.5,
            'moneda' => 'UYU',
        ]));
    }

    private static function cierre(): void
    {
        Assert::same('Persistencia venta - cierre', 'mapa completo', [
            'Total' => 63000.0,
            'GananciaEstimada' => 42189.34,
            'MontoPagado' => 60000.0,
            'SaldoPendiente' => 3000.0,
            'EstadoVenta' => 'Pendiente',
            'SubtotalBruto' => 63000.0,
            'DescuentoAplicado' => 0.0,
            'RecargoAplicado' => 0.0,
            'ComisionTarjeta' => 3150.0,
            'ComisionDistribuidor' => 0.0,
            'ComisionVendedor' => 6300.0,
            'MontoIVA' => 11360.66,
            'TotalSinIVA' => 51639.34,
            'TipoCambioAplicado' => 41.75,
        ], VentaPersistenceData::cierre([
            'total' => 63000.0,
            'ganancia' => 42189.34,
            'montoPagado' => 60000.0,
            'saldoPendiente' => 3000.0,
            'estadoVenta' => 'Pendiente',
            'subtotalBruto' => 63000.0,
            'descuentoAplicado' => 0.0,
            'recargoAplicado' => 0.0,
            'comisionTarjeta' => 3150.0,
            'comisionDistribuidor' => 0.0,
            'comisionVendedor' => 6300.0,
            'montoIVA' => 11360.66,
            'totalSinIVA' => 51639.34,
            'tipoCambio' => 41.75,
        ]));
    }
}
