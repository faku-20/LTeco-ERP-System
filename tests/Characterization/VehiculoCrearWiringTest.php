<?php

declare(strict_types=1);

/**
 * S5: congela que lteco-panel/vehiculos/crear.php delegue la creación del
 * vehículo en Lteco\Application\Vehiculo\VehiculoCrearService y ya NO contenga
 * el SQL inline de vehiculo_seq / producto / vehiculo.
 *
 * El handler conserva sus concerns: guard de módulo, CSRF, validación del
 * request, apertura/cierre de la transacción (el servicio es
 * transaction-agnostic), guardado de imágenes (helper de IO), auditoría y
 * redirect.
 */
final class VehiculoCrearWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/vehiculos/crear.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring crear vehículo', 'crear.php legible', $source !== '');

        Assert::same(
            'Wiring crear vehículo',
            'delega en ->crear(',
            1,
            substr_count($source, '->crear(')
        );

        // SQL de escritura que debe haber salido del handler.
        Assert::same(
            'Wiring crear vehículo',
            'sin INSERT vehiculo_seq inline',
            0,
            substr_count($source, 'INSERT INTO vehiculo_seq')
        );
        Assert::same(
            'Wiring crear vehículo',
            'sin INSERT producto inline',
            0,
            substr_count($source, 'INSERT INTO producto')
        );
        Assert::same(
            'Wiring crear vehículo',
            'sin INSERT vehiculo inline',
            0,
            substr_count($source, 'INSERT INTO vehiculo')
        );

        // Concerns del handler que NO deben moverse al servicio.
        Assert::same(
            'Wiring crear vehículo',
            'conserva auditoría CREAR_VEHICULO',
            1,
            substr_count($source, "registrarAuditoria(")
        );
        Assert::isTrue(
            'Wiring crear vehículo',
            'conserva apertura de transacción en el handler',
            substr_count($source, '$pdo->beginTransaction()') === 1
        );
        Assert::isTrue(
            'Wiring crear vehículo',
            'conserva guardado de imágenes (helper de IO) en el handler',
            substr_count($source, 'guardarImagenesVehiculo($pdo') === 1
        );
    }
}
