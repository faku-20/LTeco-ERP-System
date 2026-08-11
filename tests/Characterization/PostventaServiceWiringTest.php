<?php

declare(strict_types=1);

/**
 * Protege el cableado de las operaciones de postventa sobre un service (S2) hacia
 * Lteco\Application\Postventa\PostventaService mientras las páginas legacy siguen
 * orquestando guard/CSRF/permisos/auditoría/redirect.
 *
 * Igual que VehiculoEstadoWiringTest: el repositorio/servicio aislados pueden
 * estar verdes, pero esto congela que cada handler DELEGUE en el servicio y ya NO
 * contenga el SQL inline de service_vehiculo / service_historial. Cubre:
 *   - vehiculos/service_realizar.php          -> realizarService()
 *   - postventa/service_cancelar.php          -> cancelarService()
 *   - postventa/service_observacion_agregar.php -> agregarObservacion()
 *   - postventa/marcar_notificado_wa.php      -> marcarNotificadoWa()
 *   - postventa/actualizar_estados.php        -> actualizarEstados()   (S3, batch)
 */
final class PostventaServiceWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/';

        self::assertDelegado('vehiculos/service_realizar.php', $panel . 'vehiculos/service_realizar.php', [
            '$service->realizarService(' => 1,
        ]);
        // service_realizar.php también delegaba la validación de garantía/estado:
        // ya no debe quedar el SELECT con el LEFT JOIN garantia inline.
        $realizar = (string) @file_get_contents($panel . 'vehiculos/service_realizar.php');
        Assert::same(
            'Wiring postventa',
            'service_realizar.php sin SELECT de validación inline',
            0,
            substr_count($realizar, 'LEFT JOIN garantia')
        );

        self::assertDelegado('postventa/service_cancelar.php', $panel . 'postventa/service_cancelar.php', [
            '$service->cancelarService(' => 1,
        ]);
        self::assertDelegado('postventa/service_observacion_agregar.php', $panel . 'postventa/service_observacion_agregar.php', [
            '$service->agregarObservacion(' => 1,
        ]);
        self::assertDelegado('postventa/marcar_notificado_wa.php', $panel . 'postventa/marcar_notificado_wa.php', [
            '$service->marcarNotificadoWa(' => 1,
        ], false);

        // S3: estados y lecturas de notificación delegan en el servicio.
        $batch = (string) @file_get_contents($panel . 'postventa/actualizar_estados.php');
        Assert::isTrue('Wiring postventa', 'actualizar_estados.php legible', $batch !== '');
        Assert::same(
            'Wiring postventa',
            'actualizar_estados.php delega en ->actualizarEstados(',
            1,
            substr_count($batch, '->actualizarEstados(')
        );
        Assert::same(
            'Wiring postventa',
            'actualizar_estados.php sin UPDATE garantia inline',
            0,
            substr_count($batch, 'UPDATE garantia')
        );
        Assert::same(
            'Wiring postventa',
            'actualizar_estados.php sin UPDATE service_vehiculo inline',
            0,
            substr_count($batch, 'UPDATE service_vehiculo')
        );
        Assert::same(
            'Wiring postventa',
            'actualizar_estados.php delega notificaciones',
            1,
            substr_count($batch, '$notificacionService->servicesParaNotificar()')
        );
        Assert::same(
            'Wiring postventa',
            'actualizar_estados.php sin SELECT inline',
            0,
            preg_match('/["\']\s*SELECT\b/i', $batch)
        );
    }

    /**
     * Verifica que el handler use el servicio (conteo exacto) y no quede SQL de
     * mutación inline de service_vehiculo / service_historial.
     *
     * @param array<string,int> $delegaciones
     */
    private static function assertDelegado(string $etiqueta, string $ruta, array $delegaciones, bool $tocaService = true): void
    {
        $source = @file_get_contents($ruta);
        Assert::isTrue('Wiring postventa', $etiqueta . ' legible', is_string($source));
        if (!is_string($source)) {
            return;
        }

        foreach ($delegaciones as $aguja => $veces) {
            Assert::same(
                'Wiring postventa',
                $etiqueta . ' delega en ' . $aguja,
                $veces,
                substr_count($source, $aguja)
            );
        }

        if ($tocaService) {
            Assert::same(
                'Wiring postventa',
                $etiqueta . ' sin UPDATE service_vehiculo inline',
                0,
                substr_count($source, 'UPDATE service_vehiculo')
            );
        }

        Assert::same(
            'Wiring postventa',
            $etiqueta . ' sin INSERT service_historial inline',
            0,
            substr_count($source, 'INSERT INTO service_historial')
        );
    }
}
