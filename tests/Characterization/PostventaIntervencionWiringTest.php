<?php

declare(strict_types=1);

/**
 * S4: congela que lteco-panel/postventa/intervencion_guardar.php delegue el
 * guardado de la intervención técnica en
 * Lteco\Application\Postventa\PostventaIntervencionService y ya NO contenga el SQL
 * inline de postventa_historial_tecnico / postventa_repuesto_usado ni el descuento
 * de stock de producto.
 *
 * El handler conserva sus concerns: guard de módulo, CSRF, permisos de Vendedor,
 * apertura/cierre de la transacción (el servicio es transaction-agnostic),
 * auditoría y redirect/flash.
 */
final class PostventaIntervencionWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/postventa/intervencion_guardar.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring intervención', 'intervencion_guardar.php legible', $source !== '');

        Assert::same(
            'Wiring intervención',
            'delega en ->guardarIntervencion(',
            1,
            substr_count($source, '->guardarIntervencion(')
        );

        // SQL de escritura que debe haber salido del handler.
        Assert::same(
            'Wiring intervención',
            'sin INSERT postventa_historial_tecnico inline',
            0,
            substr_count($source, 'INSERT INTO postventa_historial_tecnico')
        );
        Assert::same(
            'Wiring intervención',
            'sin INSERT postventa_repuesto_usado inline',
            0,
            substr_count($source, 'INSERT INTO postventa_repuesto_usado')
        );
        Assert::same(
            'Wiring intervención',
            'sin UPDATE de stock de producto inline',
            0,
            substr_count($source, 'UPDATE producto SET Stock')
        );
        Assert::same(
            'Wiring intervención',
            'delega ownership de vendedor',
            1,
            substr_count($source, '$postventaService->vendedorPuedeIntervenir(')
        );
        Assert::same(
            'Wiring intervención',
            'sin SELECT inline',
            0,
            preg_match('/["\']\s*SELECT\b/i', $source)
        );

        // Concerns del handler que NO deben moverse al servicio.
        Assert::same(
            'Wiring intervención',
            'conserva auditoría POSTVENTA_INTERVENCION',
            1,
            substr_count($source, "registrarAuditoria(")
        );
        Assert::isTrue(
            'Wiring intervención',
            'conserva apertura de transacción en el handler',
            substr_count($source, '$pdo->beginTransaction()') === 1
        );
        Assert::same(
            'Wiring intervención',
            'usa idempotencia de panel',
            1,
            substr_count($source, "panelIdempotencyClaim(")
        );
        Assert::same(
            'Wiring intervención',
            'completa idempotencia de panel',
            1,
            substr_count($source, "panelIdempotencyComplete(")
        );
    }
}
