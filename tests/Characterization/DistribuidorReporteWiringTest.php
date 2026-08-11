<?php

declare(strict_types=1);

/**
 * C4: congela que la creación y administración de reportes de problemas
 * deleguen la persistencia y las reglas del caso de uso.
 */
final class DistribuidorReporteWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/';
        self::verificarCreacion($base . 'reportar_problema.php');
        self::verificarDetalle($base . 'reporte_detalle.php');
    }

    private static function verificarCreacion(string $ruta): void
    {
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring reportes distribuidor', 'reportar_problema.php legible', $source !== '');
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'alta usa DistribuidorReporteService',
            strpos($source, 'DistribuidorReporteService') !== false
        );
        Assert::same(
            'Wiring reportes distribuidor',
            'alta delega en crearReporte',
            1,
            substr_count($source, '->crearReporte(')
        );
        Assert::same(
            'Wiring reportes distribuidor',
            'alta sin INSERT inline',
            0,
            substr_count($source, 'INSERT INTO distribuidor_reporte_problema')
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'alta conserva guard POST',
            strpos($source, 'requirePost()') !== false
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'alta conserva CSRF',
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'alta conserva validación de upload',
            strpos($source, 'is_uploaded_file(') !== false
                && strpos($source, 'move_uploaded_file(') !== false
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'alta conserva auditoría',
            strpos($source, "'REPORTE_PROBLEMA_CREAR'") !== false
        );
    }

    private static function verificarDetalle(string $ruta): void
    {
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring reportes distribuidor', 'reporte_detalle.php legible', $source !== '');
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'detalle usa DistribuidorReporteService',
            strpos($source, 'DistribuidorReporteService') !== false
        );
        Assert::same(
            'Wiring reportes distribuidor',
            'detalle delega lectura',
            1,
            substr_count($source, '->obtenerReporte(')
        );
        Assert::same(
            'Wiring reportes distribuidor',
            'detalle delega cambio de estado',
            1,
            substr_count($source, '->actualizarEstado(')
        );
        Assert::same(
            'Wiring reportes distribuidor',
            'detalle sin SELECT inline',
            0,
            preg_match_all('/\bSELECT\b/i', $source)
        );
        Assert::same(
            'Wiring reportes distribuidor',
            'detalle sin UPDATE inline',
            0,
            substr_count($source, 'UPDATE distribuidor_reporte_problema')
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'detalle conserva guard admin',
            strpos($source, 'requiereAdmin()') !== false
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'detalle conserva CSRF',
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring reportes distribuidor',
            'detalle conserva auditoría',
            strpos($source, "'REPORTE_PROBLEMA_ESTADO'") !== false
        );
    }
}
