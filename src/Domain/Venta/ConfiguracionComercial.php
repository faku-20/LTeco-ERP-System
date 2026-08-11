<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

/**
 * Normaliza porcentajes y defaults comerciales usados al crear una venta.
 */
final class ConfiguracionComercial
{
    /**
     * Conserva los casts y defaults exactos que tenía ventas/crear.php.
     *
     * @param array<string,mixed>|null $fila
     * @return array<string,float>
     */
    public static function paraFormulario(?array $fila, float $tasaIvaDefault): array
    {
        $fila ??= [];

        return [
            'DescuentoContado' => isset($fila['DescuentoContado'])
                ? (float) $fila['DescuentoContado']
                : 0.0,
            'RecargoTarjeta' => isset($fila['RecargoTarjeta'])
                ? (float) $fila['RecargoTarjeta']
                : 0.0,
            'ComisionDistribuidor' => isset($fila['ComisionDistribuidor'])
                ? (float) $fila['ComisionDistribuidor']
                : 0.0,
            'ComisionVendedor' => isset($fila['ComisionVendedor'])
                ? (float) $fila['ComisionVendedor']
                : 0.0,
            'TasaIVA' => isset($fila['TasaIVA'])
                ? (float) $fila['TasaIVA']
                : $tasaIvaDefault,
        ];
    }

    /**
     * @param array<string,mixed>|null $fila
     * @return array<string,float>
     */
    public static function normalizar(?array $fila, float $tasaIvaDefault): array
    {
        $defaults = [
            'DescuentoContado' => 0.0,
            'RecargoTarjeta' => 0.0,
            'ComisionDistribuidor' => 0.0,
            'ComisionVendedor' => 0.0,
            'TasaIVA' => $tasaIvaDefault,
        ];

        if ($fila === null) {
            return $defaults;
        }

        $config = $defaults;
        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $fila) && $fila[$key] !== null && $fila[$key] !== '') {
                $valor = (float) $fila[$key];
                $config[$key] = $valor >= 0 ? $valor : $default;
            }
        }

        if ($config['TasaIVA'] <= 0) {
            $config['TasaIVA'] = $tasaIvaDefault;
        }

        return $config;
    }

    public static function comisionNoNegativa(mixed $valor, float $default = 0.0): float
    {
        if ($valor === null || $valor === '') {
            return $default;
        }

        return max(0.0, (float) $valor);
    }
}
