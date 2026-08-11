<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

use Lteco\Support\InputNormalizer;

/**
 * Interpreta la selección de vehículos y repuestos del formulario de venta.
 */
final class SeleccionProductos
{
    /**
     * @param array<string,mixed> $payload
     * @return array<int,mixed>
     */
    public static function vehiculos(array $payload): array
    {
        return array_values(array_unique(array_filter(
            (array) ($payload['vehiculos'] ?? []),
            static fn (mixed $id): bool => trim((string) $id) !== ''
        )));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,int>
     */
    public static function repuestos(array $payload): array
    {
        $repuestos = [];

        foreach ($payload as $key => $value) {
            if (strpos((string) $key, 'rep_') !== 0) {
                continue;
            }

            $idRepuesto = (int) str_replace('rep_', '', (string) $key);
            $cantidad = InputNormalizer::nonNegativeInt($value);
            if ($idRepuesto > 0 && $cantidad > 0) {
                $repuestos[$idRepuesto] = $cantidad;
            }
        }

        return $repuestos;
    }

    /**
     * @param array<int,mixed> $vehiculos
     * @param array<int,int> $repuestos
     */
    public static function tieneProductos(array $vehiculos, array $repuestos): bool
    {
        return $vehiculos !== [] || $repuestos !== [];
    }
}
