<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

use RuntimeException;

final class ReglasAnulacion
{
    public static function esVehiculo(string $tipoProducto): bool
    {
        return strcasecmp(trim($tipoProducto), 'Moto') === 0
            || strcasecmp(trim($tipoProducto), 'Vehiculo') === 0;
    }

    /**
     * @param array<string,mixed>|null $columnaInfo
     */
    public static function estadoPermitido(?array $columnaInfo, string $estado): bool
    {
        if ($columnaInfo === null) {
            return false;
        }

        $tipo = (string) ($columnaInfo['COLUMN_TYPE'] ?? '');
        if (!str_starts_with(strtolower($tipo), 'enum(')) {
            return true;
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $tipo, $matches);
        $valores = array_map(
            static fn (string $valor): string => stripcslashes($valor),
            $matches[1] ?? []
        );

        return in_array($estado, $valores, true);
    }

    /**
     * @param array<string,mixed>|null $columnaInfo
     */
    public static function requerirEstado(
        ?array $columnaInfo,
        string $tabla,
        string $estado
    ): void {
        if (!self::estadoPermitido($columnaInfo, $estado)) {
            throw new RuntimeException(
                "La tabla {$tabla} no permite Estado='{$estado}'. No se aplicaron cambios parciales."
            );
        }
    }

    /**
     * @param array<string,mixed>|null $columnaInfo
     */
    public static function estadoGastoAnulado(?array $columnaInfo): string
    {
        if (self::estadoPermitido($columnaInfo, 'Inactivo')) {
            return 'Inactivo';
        }

        if (self::estadoPermitido($columnaInfo, 'Anulado')) {
            return 'Anulado';
        }

        throw new RuntimeException(
            "La tabla gasto no permite Estado='Inactivo' ni Estado='Anulado'. No se aplicaron cambios parciales."
        );
    }
}
