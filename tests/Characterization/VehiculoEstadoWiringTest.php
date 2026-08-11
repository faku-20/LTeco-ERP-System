<?php

declare(strict_types=1);

/**
 * Protege el cableado de las acciones simples de vehículos (S1) hacia
 * Lteco\Application\Vehiculo\VehiculoEstadoService mientras las páginas legacy
 * siguen orquestando guard/CSRF/auditoría/redirect.
 *
 * Igual que VentaGuardarWiringTest: el repositorio aislado puede estar verde,
 * pero esto congela que cada handler DELEGUE en el servicio y ya NO contenga el
 * SQL inline de producto/vehiculo. Cubre: toggle_web, eliminar, cambiar_estado,
 * reservar y toggle_destacado (ya migrado).
 */
final class VehiculoEstadoWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/vehiculos/';

        self::assertDelegado('toggle_web.php', $base . 'toggle_web.php', [
            '$service->toggleWeb(' => 1,
        ]);
        self::assertDelegado('eliminar.php', $base . 'eliminar.php', [
            '$service->ocultar(' => 1,
        ]);
        self::assertDelegado('cambiar_estado.php', $base . 'cambiar_estado.php', [
            '$service->cambiarEstado(' => 1,
        ]);
        self::assertDelegado('reservar.php', $base . 'reservar.php', [
            '$service->reservar(' => 1,
        ]);
        self::assertDelegado('toggle_destacado.php', $base . 'toggle_destacado.php', [
            '$service->toggleDestacado(' => 1,
        ]);
        self::assertDelegado('mover_orden.php', $base . 'mover_orden.php', [
            '$service->moverOrdenWeb(' => 1,
        ]);

        // El handler con transacción propia debe seguir siendo el dueño de la
        // transacción (el servicio/repos son transaction-agnostic).
        $cambiarEstado = (string) @file_get_contents($base . 'cambiar_estado.php');
        Assert::same(
            'Wiring vehiculos',
            'cambiar_estado.php conserva su transacción',
            1,
            substr_count($cambiarEstado, '$pdo->beginTransaction()')
        );
        $reservar = (string) @file_get_contents($base . 'reservar.php');
        Assert::same(
            'Wiring vehiculos',
            'reservar.php conserva su transacción',
            1,
            substr_count($reservar, '$pdo->beginTransaction()')
        );
        $moverOrden = (string) @file_get_contents($base . 'mover_orden.php');
        Assert::same(
            'Wiring vehiculos',
            'mover_orden.php conserva su transacción',
            1,
            substr_count($moverOrden, '$pdo->beginTransaction()')
        );
        Assert::same(
            'Wiring vehiculos',
            'mover_orden.php ya no usa helper legacy',
            0,
            substr_count($moverOrden, 'moverVehiculoOrdenWeb(')
        );

        foreach (['toggle_web.php', 'toggle_destacado.php', 'cambiar_estado.php', 'eliminar.php', 'mover_orden.php'] as $pagina) {
            $source = (string) @file_get_contents($base . $pagina);
            Assert::same(
                'Wiring vehiculos',
                $pagina . ' sin SELECT inline',
                0,
                preg_match('/["\']\s*SELECT\b/i', $source)
            );
        }
    }

    /**
     * Verifica que el handler use el servicio (conteo exacto) y no quede SQL de
     * mutación inline de producto/vehiculo.
     *
     * @param array<string,int> $delegaciones
     */
    private static function assertDelegado(string $etiqueta, string $ruta, array $delegaciones): void
    {
        $source = @file_get_contents($ruta);
        Assert::isTrue('Wiring vehiculos', $etiqueta . ' legible', is_string($source));
        if (!is_string($source)) {
            return;
        }

        foreach ($delegaciones as $aguja => $veces) {
            Assert::same(
                'Wiring vehiculos',
                $etiqueta . ' delega en ' . $aguja,
                $veces,
                substr_count($source, $aguja)
            );
        }

        Assert::same(
            'Wiring vehiculos',
            $etiqueta . ' sin UPDATE producto inline',
            0,
            substr_count($source, 'UPDATE producto SET')
        );
        Assert::same(
            'Wiring vehiculos',
            $etiqueta . ' sin UPDATE vehiculo inline',
            0,
            substr_count($source, 'UPDATE vehiculo SET')
        );
    }
}
