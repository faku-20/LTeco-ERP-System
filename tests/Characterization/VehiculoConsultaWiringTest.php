<?php

declare(strict_types=1);

final class VehiculoConsultaWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/vehiculos/';
        $casos = [
            'qr.php' => '$consulta->paraQr($id)',
            'etiqueta_multi.php' => '$consulta->paraEtiquetas($ids)',
            'scan.php' => '$consulta->buscarEscaneado($rawScan)',
        ];

        foreach ($casos as $archivo => $delegacion) {
            $source = @file_get_contents($base . $archivo);
            Assert::isTrue('Wiring consultas vehiculos', $archivo . ' legible', is_string($source));
            if (!is_string($source)) {
                continue;
            }

            Assert::same(
                'Wiring consultas vehiculos',
                $archivo . ' delega consulta',
                1,
                substr_count($source, $delegacion)
            );
            Assert::same(
                'Wiring consultas vehiculos',
                $archivo . ' sin SELECT inline',
                0,
                preg_match('/["\']\s*SELECT\b/i', $source)
            );
        }

        $scan = (string) @file_get_contents($base . 'scan.php');
        Assert::same(
            'Wiring consultas vehiculos',
            'scan.php delega parser QR',
            1,
            substr_count($scan, '$consulta->extraerQr($rawScan)')
        );
        Assert::same(
            'Wiring consultas vehiculos',
            'scan.php sin función parser legacy',
            0,
            substr_count($scan, 'function ltecoExtraerQrVehiculo')
        );
    }
}
