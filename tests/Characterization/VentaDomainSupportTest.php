<?php

declare(strict_types=1);

use Lteco\Domain\Venta\FacturaInterna;
use Lteco\Domain\Venta\EstadoPago;
use Lteco\Domain\Venta\ProductoVenta;
use Lteco\Domain\Venta\SeleccionProductos;

/**
 * Caracteriza reglas puras que históricamente vivían al pie de guardar.php.
 */
final class VentaDomainSupportTest
{
    public static function run(): void
    {
        self::facturaInterna();
        self::productoVenta();
        self::seleccionProductos();
        self::estadoPago();
    }

    private static function facturaInterna(): void
    {
        $caso = 'Dominio venta - factura interna';
        $anioActual = date('Y');

        Assert::same($caso, 'fecha explícita', 'F-2024-000123', FacturaInterna::generar(123, '2024-08-31 10:30:00'));
        Assert::same($caso, 'sin fecha usa año actual', 'F-' . $anioActual . '-000007', FacturaInterna::generar(7));
        Assert::same($caso, 'fecha inválida usa año actual', 'F-' . $anioActual . '-000042', FacturaInterna::generar(42, 'sin-fecha'));
        Assert::same($caso, 'id mayor a seis dígitos no se recorta', 'F-2025-1234567', FacturaInterna::generar(1234567, '2025-01-01'));
    }

    private static function productoVenta(): void
    {
        $caso = 'Dominio venta - producto';
        $producto = [
            'PrecioVenta' => '63000.50',
            'PrecioDistribuidor' => '50000.00',
            'TipoCambioImportacion' => '41.75',
        ];

        Assert::same($caso, 'cliente final usa PrecioVenta', 63000.5, ProductoVenta::precioBase($producto, 'Final'));
        Assert::same($caso, 'distribuidor también usa PrecioVenta', 63000.5, ProductoVenta::precioBase($producto, 'Distribuidor'));
        Assert::same($caso, 'precio ausente cae a cero', 0.0, ProductoVenta::precioBase([], 'Final'));
        Assert::same($caso, 'tipo de cambio de importación', 41.75, ProductoVenta::tipoCambio($producto, 40.0, 39.0));
        Assert::same($caso, 'tipo de cambio fallback', 40.0, ProductoVenta::tipoCambio(['TipoCambioImportacion' => 0], 40.0, 39.0));
        Assert::same($caso, 'fallback inválido usa default sistema', 39.0, ProductoVenta::tipoCambio([], 0.0, 39.0));
        Assert::same($caso, 'promedio ponderado', 41.3333, ProductoVenta::tipoCambioPromedio(1240.0, 30.0, 40.0));
        Assert::same($caso, 'promedio sin líneas usa fallback', 40.0, ProductoVenta::tipoCambioPromedio(0.0, 0.0, 40.0));
    }

    private static function seleccionProductos(): void
    {
        $caso = 'Dominio venta - selección';
        $payload = [
            'vehiculos' => [' MOTO-1 ', '', 'MOTO-1', 0, 'MOTO-2'],
            'rep_5' => '2',
            'rep_8' => '0',
            'rep_12_extra' => '3 unidades',
            'rep_-7' => '-4',
            'otro' => '99',
        ];

        Assert::same(
            $caso,
            'vehículos conserva valores, espacios y elimina duplicados exactos/vacíos',
            [' MOTO-1 ', 'MOTO-1', 0, 'MOTO-2'],
            SeleccionProductos::vehiculos($payload)
        );
        Assert::same(
            $caso,
            'repuestos conserva parsing legacy',
            [5 => 2, 12 => 3],
            SeleccionProductos::repuestos($payload)
        );
        Assert::isTrue($caso, 'selección con repuestos no está vacía', SeleccionProductos::tieneProductos([], [5 => 2]));
        Assert::isFalse($caso, 'selección sin productos está vacía', SeleccionProductos::tieneProductos([], []));
    }

    private static function estadoPago(): void
    {
        $caso = 'Dominio venta - pago';

        Assert::same($caso, 'vacío paga el total', [
            'montoPagado' => 1000.0,
            'saldoPendiente' => 0.0,
            'estadoVenta' => 'Confirmada',
        ], EstadoPago::resolver(1000.0, ''));

        Assert::same($caso, 'pago parcial', [
            'montoPagado' => 250.25,
            'saldoPendiente' => 749.75,
            'estadoVenta' => 'Pendiente',
        ], EstadoPago::resolver(1000.0, '250,25'));

        Assert::same($caso, 'diferencia menor a tolerancia aceptada', [
            'montoPagado' => 1000.000001,
            'saldoPendiente' => 0.0,
            'estadoVenta' => 'Confirmada',
        ], EstadoPago::resolver(1000.0, '1000.000001'));

        try {
            EstadoPago::resolver(1000.0, '1000.01');
            Assert::isTrue($caso, 'pago excedido lanza excepción', false);
        } catch (RuntimeException $e) {
            Assert::same(
                $caso,
                'mensaje de pago excedido',
                'El monto pagado no puede superar el total de la venta.',
                $e->getMessage()
            );
        }
    }
}
