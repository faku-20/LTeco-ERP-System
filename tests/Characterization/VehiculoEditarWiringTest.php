<?php

declare(strict_types=1);

/**
 * S6 + S6.1: congela que lteco-panel/vehiculos/editar.php delegue tanto la
 * EDICIÓN principal (UPDATE producto + UPDATE vehiculo) en
 * Lteco\Application\Vehiculo\VehiculoEditarService como la GESTIÓN DE IMÁGENES
 * (set_principal / delete_image / move_image) en
 * Lteco\Application\Vehiculo\VehiculoImagenService, y ya NO contenga ese SQL de
 * escritura inline.
 *
 * S6.1: el bloque de imágenes deja de hacer UPDATE/DELETE sobre vehiculo_imagen
 * inline; todo el SQL de escritura vive ahora en VehiculoRepository orquestado
 * por VehiculoImagenService. La página conserva como lectura el SELECT de
 * listado para la vista (fuera del bloque de acciones).
 *
 * El handler conserva sus concerns: guard de módulo, CSRF, validación del
 * request, apertura/cierre de la transacción (los servicios son
 * transaction-agnostic), guardado de imágenes (helper de IO), borrado físico
 * del archivo (IO, post-commit), auditoría y redirect.
 */
final class VehiculoEditarWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/vehiculos/editar.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring editar vehículo', 'editar.php legible', $source !== '');

        // Delega exactamente una vez en el servicio de edición.
        Assert::same(
            'Wiring editar vehículo',
            'delega en ->editar(',
            1,
            substr_count($source, '->editar(')
        );

        // SQL de escritura del edit principal que debe haber salido del handler.
        Assert::same(
            'Wiring editar vehículo',
            'sin UPDATE producto inline',
            0,
            substr_count($source, 'UPDATE producto')
        );
        // Token distintivo del UPDATE vehiculo principal (no aparece en las
        // acciones de imagen, que operan sobre vehiculo_imagen).
        Assert::same(
            'Wiring editar vehículo',
            'sin UPDATE vehiculo principal inline',
            0,
            substr_count($source, 'NumeroMotor = ?')
        );

        // S6.1: el SQL de escritura de imágenes salió del handler hacia el
        // servicio/repositorio. No debe quedar UPDATE/DELETE inline.
        Assert::same(
            'Wiring editar vehículo',
            'sin UPDATE vehiculo_imagen inline',
            0,
            substr_count($source, 'UPDATE vehiculo_imagen')
        );
        Assert::same(
            'Wiring editar vehículo',
            'sin DELETE de imagen inline',
            0,
            substr_count($source, 'DELETE FROM vehiculo_imagen')
        );
        Assert::same(
            'Wiring editar vehículo',
            'delega carga inicial en ->cargar(',
            1,
            substr_count($source, '$vehiculoEditar->cargar($idVehiculo)')
        );
        // Regresión: tras la migración no debe quedar ningún PDOStatement crudo
        // ($stmt) en la página. Un $stmt residual del código legacy quedaba sin
        // preparar y reventaba en GET con "execute() on null" (editar.php:426).
        Assert::same(
            'Wiring editar vehículo',
            'sin $stmt crudo residual',
            0,
            substr_count($source, '$stmt')
        );
        foreach (['FROM vehiculo v', 'FROM garantia g', 'FROM service_vehiculo', 'FROM vehiculo_imagen'] as $sqlPropio) {
            Assert::same(
                'Wiring editar vehículo',
                'sin lectura inline ' . $sqlPropio,
                0,
                substr_count($source, $sqlPropio)
            );
        }

        // Delega las tres acciones de imagen en VehiculoImagenService.
        Assert::isTrue(
            'Wiring editar vehículo',
            'usa VehiculoImagenService',
            strpos($source, 'VehiculoImagenService') !== false
        );
        Assert::same(
            'Wiring editar vehículo',
            'delega set_principal en ->marcarPrincipal(',
            1,
            substr_count($source, '->marcarPrincipal(')
        );
        Assert::same(
            'Wiring editar vehículo',
            'delega delete_image en ->eliminar(',
            1,
            substr_count($source, '->eliminar(')
        );
        Assert::same(
            'Wiring editar vehículo',
            'delega move_image en ->mover(',
            1,
            substr_count($source, '->mover(')
        );

        // Concerns del handler que NO deben moverse al servicio.
        Assert::isTrue(
            'Wiring editar vehículo',
            'conserva auditoría EDITAR_VEHICULO',
            strpos($source, "'EDITAR_VEHICULO'") !== false
        );
        // Las auditorías de imagen siguen en el handler (no en el servicio).
        Assert::isTrue(
            'Wiring editar vehículo',
            'conserva auditoría ELIMINAR_IMAGEN_VEHICULO',
            strpos($source, "'ELIMINAR_IMAGEN_VEHICULO'") !== false
        );
        Assert::isTrue(
            'Wiring editar vehículo',
            'conserva auditorías CAMBIAR/MOVER imagen',
            strpos($source, "'CAMBIAR_IMAGEN_PRINCIPAL_VEHICULO'") !== false
                && strpos($source, "'MOVER_IMAGEN_VEHICULO'") !== false
        );
        // El borrado físico del archivo (IO) sigue en el handler.
        Assert::isTrue(
            'Wiring editar vehículo',
            'conserva borrado físico de archivo en el handler',
            substr_count($source, 'eliminarArchivoProyecto(') === 1
        );
        Assert::isTrue(
            'Wiring editar vehículo',
            'conserva guardado de imágenes (helper de IO) en el handler',
            substr_count($source, 'guardarImagenesVehiculo($pdo') === 1
        );
        Assert::isTrue(
            'Wiring editar vehículo',
            'conserva apertura de transacción en el handler',
            substr_count($source, '$pdo->beginTransaction()') >= 1
        );
    }
}
