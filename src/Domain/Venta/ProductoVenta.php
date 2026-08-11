<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

/**
 * Reglas puras para valorar productos dentro de una venta.
 */
final class ProductoVenta
{
    /**
     * Distribuidor usa PrecioVenta igual que cliente final. La diferencia se
     * registra como comisión interna y no como rebaja automática del producto.
     * El parámetro se conserva porque el tipo de cliente forma parte de la regla,
     * aunque hoy ambos casos producen el mismo precio.
     *
     * @param array<string,mixed> $producto
     */
    public static function precioBase(array $producto, string $tipoCliente): float
    {
        return isset($producto['PrecioVenta']) ? (float) $producto['PrecioVenta'] : 0.0;
    }

    /**
     * @param array<string,mixed> $producto
     */
    public static function tipoCambio(array $producto, float $fallback, float $defaultSistema): float
    {
        $tipoCambioImportacion = (float) ($producto['TipoCambioImportacion'] ?? 0);
        if ($tipoCambioImportacion > 0) {
            return $tipoCambioImportacion;
        }

        return $fallback > 0 ? $fallback : $defaultSistema;
    }

    public static function tipoCambioPromedio(float $pesoAcumulado, float $totalPonderado, float $fallback): float
    {
        return $totalPonderado > 0
            ? round($pesoAcumulado / $totalPonderado, 4)
            : $fallback;
    }
}
