<?php

declare(strict_types=1);

/**
 * Protege el cableado del flujo legacy mientras guardar.php sigue orquestando.
 *
 * En b45552c se eliminaron accidentalmente las dos inserciones de detalle. La
 * suite del repositorio seguía verde porque probaba el método aislado, no el
 * cableado desde guardar.php.
 */
final class VentaGuardarWiringTest
{
    public static function run(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/guardar.php');
        Assert::isTrue('Wiring guardar venta', 'archivo legible', is_string($source));

        if (!is_string($source)) {
            return;
        }

        // Wave 1: las dos inserciones de detalle ahora pasan por el writer
        // compartido VentaLineasService (una línea de moto + una de repuesto),
        // no por $ventaPersistence->agregarDetalle directo. Se sigue protegiendo
        // que ambos caminos de detalle estén cableados desde guardar.php.
        Assert::same(
            'Wiring guardar venta',
            'línea de moto delegada al writer compartido',
            1,
            substr_count($source, '$ventaLineas->registrarVehiculo([')
        );
        Assert::same(
            'Wiring guardar venta',
            'línea de repuesto delegada al writer compartido',
            1,
            substr_count($source, '$ventaLineas->registrarRepuesto([')
        );
        Assert::same(
            'Wiring guardar venta',
            'guardar.php ya no inserta detalle directamente',
            0,
            substr_count($source, '$ventaPersistence->agregarDetalle([')
        );
        Assert::same(
            'Wiring guardar venta',
            'una creación de cabecera mediante servicio',
            1,
            substr_count($source, '$ventaPersistence->crearCabecera([')
        );
        Assert::same(
            'Wiring guardar venta',
            'una asignación de factura mediante servicio',
            1,
            substr_count($source, '$ventaPersistence->asignarNumeroFactura(')
        );
        Assert::same(
            'Wiring guardar venta',
            'un cierre de venta mediante servicio',
            1,
            substr_count($source, '$ventaPersistence->cerrarVenta(')
        );
        Assert::same(
            'Wiring guardar venta',
            'guardar.php no contiene UPDATE directo de venta',
            0,
            substr_count($source, 'UPDATE venta')
        );
        Assert::same(
            'Wiring guardar venta',
            'guardar.php no consulta comisión de usuario directamente',
            0,
            substr_count($source, 'SELECT ComisionPct FROM usuario')
        );
        Assert::same(
            'Wiring guardar venta',
            'guardar.php no consulta comisión de distribuidor directamente',
            0,
            substr_count($source, 'SELECT ComisionPct FROM distribuidor')
        );
        Assert::same(
            'Wiring guardar venta',
            'guardar.php no consulta configuración comercial directamente',
            0,
            substr_count($source, 'SELECT DescuentoContado')
        );
        Assert::same(
            'Wiring guardar venta',
            'delega lock de vehículo',
            1,
            substr_count($source, '$ventaLineas->bloquearVehiculo(')
        );
        Assert::same(
            'Wiring guardar venta',
            'delega lock de repuesto',
            1,
            substr_count($source, '$ventaLineas->bloquearRepuesto(')
        );
        Assert::same(
            'Wiring guardar venta',
            'sin FOR UPDATE inline',
            0,
            substr_count($source, 'FOR UPDATE')
        );
    }
}
