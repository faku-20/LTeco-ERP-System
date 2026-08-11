<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

/**
 * Genera la numeración interna histórica de una venta.
 */
final class FacturaInterna
{
    public static function generar(int $idVenta, ?string $fechaVenta = null): string
    {
        $anio = date('Y');

        if ($fechaVenta !== null && trim($fechaVenta) !== '') {
            $timestamp = strtotime($fechaVenta);
            if ($timestamp !== false) {
                $anio = date('Y', $timestamp);
            }
        }

        return 'F-' . $anio . '-' . str_pad((string) $idVenta, 6, '0', STR_PAD_LEFT);
    }
}
