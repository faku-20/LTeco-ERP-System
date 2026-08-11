<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Infrastructure\Repository\VentaRepository;

/**
 * Traduce datos ya validados a las columnas persistidas por VentaRepository.
 */
final class VentaPersistenceData
{
    /**
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    public static function cabecera(array $datos): array
    {
        $tipoCliente = (string) ($datos['tipoCliente'] ?? 'Final');
        $distribuidorId = $datos['distribuidorId'] ?? null;

        return [
            'Cliente_IdCliente' => $datos['clienteId'] ?? null,
            'FechaVenta' => VentaRepository::AHORA,
            'MetodoPago' => $datos['metodoPago'] ?? 'Efectivo',
            'TipoCliente' => $tipoCliente,
            'Distribuidor_IdDistribuidor' => $tipoCliente === 'Distribuidor' && $distribuidorId
                ? $distribuidorId
                : null,
            'Total' => 0,
            'GananciaEstimada' => 0,
            'Moneda' => $datos['moneda'] ?? 'UYU',
            'Observaciones' => $datos['observaciones'] ?? null,
            'TipoCambioAplicado' => $datos['tipoCambio'] ?? null,
            'TipoTarjeta' => $datos['tipoTarjeta'] ?? null,
            'MarcaTarjeta' => $datos['marcaTarjeta'] ?? null,
            'CuotasTarjeta' => $datos['cuotasTarjeta'] ?? null,
            'EstadoVenta' => 'Confirmada',
            'UsuarioVendedorId' => $datos['usuarioVendedorId'] ?? 0,
        ];
    }

    /**
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    public static function detalle(array $datos): array
    {
        return [
            'Venta_IdVenta' => $datos['ventaId'],
            'Producto_IdProducto' => $datos['productoId'],
            'Cantidad' => $datos['cantidad'],
            'PrecioUnitario' => $datos['precioUnitario'],
            'CostoUnitario' => $datos['costoUnitario'],
            'Subtotal' => $datos['subtotal'],
            'GananciaLinea' => $datos['gananciaLinea'],
            'Moneda' => $datos['moneda'],
        ];
    }

    /**
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    public static function cierre(array $datos): array
    {
        return [
            'Total' => $datos['total'],
            'GananciaEstimada' => $datos['ganancia'],
            'MontoPagado' => $datos['montoPagado'],
            'SaldoPendiente' => $datos['saldoPendiente'],
            'EstadoVenta' => $datos['estadoVenta'],
            'SubtotalBruto' => $datos['subtotalBruto'],
            'DescuentoAplicado' => $datos['descuentoAplicado'],
            'RecargoAplicado' => $datos['recargoAplicado'],
            'ComisionTarjeta' => $datos['comisionTarjeta'],
            'ComisionDistribuidor' => $datos['comisionDistribuidor'],
            'ComisionVendedor' => $datos['comisionVendedor'],
            'MontoIVA' => $datos['montoIVA'],
            'TotalSinIVA' => $datos['totalSinIVA'],
            'TipoCambioAplicado' => $datos['tipoCambio'],
        ];
    }
}
