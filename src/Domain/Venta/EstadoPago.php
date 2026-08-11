<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

use Lteco\Support\InputNormalizer;
use RuntimeException;

/**
 * Resuelve el pago y estado final de una venta.
 */
final class EstadoPago
{
    /**
     * @return array{montoPagado:float,saldoPendiente:float,estadoVenta:string}
     */
    public static function resolver(float $total, mixed $montoPagadoIngresado): array
    {
        $montoPagadoRaw = trim((string) $montoPagadoIngresado);
        $montoPagado = $montoPagadoRaw === ''
            ? $total
            : InputNormalizer::nonNegativeDecimal($montoPagadoRaw);

        if ($montoPagado - $total > 0.00001) {
            throw new RuntimeException('El monto pagado no puede superar el total de la venta.');
        }

        $saldoPendiente = max(0.0, round($total - $montoPagado, 2));

        return [
            'montoPagado' => $montoPagado,
            'saldoPendiente' => $saldoPendiente,
            'estadoVenta' => $saldoPendiente > 0 ? 'Pendiente' : 'Confirmada',
        ];
    }
}
