<?php

declare(strict_types=1);

namespace Lteco\Application\Vehiculo;

use Lteco\Infrastructure\Repository\VehiculoRepository;
use RuntimeException;

/**
 * Edición del formulario principal de un vehículo (producto 'Moto' + fila de
 * vehiculo). Origen: el bloque de persistencia inline del POST de guardado de
 * lteco-panel/vehiculos/editar.php (UPDATE producto + UPDATE vehiculo).
 *
 * Sin side effects de HTTP: recibe entradas YA validadas/normalizadas por el
 * handler (precios, estado, moneda, flags de publicación, slug ya resuelto, TC
 * de importación, etc.) y persiste los dos UPDATE.
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo el
 * dueño (también encadena el guardado de imágenes y la auditoría dentro de su
 * transacción).
 *
 * NO migra el bloque de gestión de imágenes (set_principal / delete_image /
 * move_image) de editar.php: eso queda en el handler para un bloque futuro.
 *
 * Réplica del comportamiento legacy: deriva FechaVenta igual que la página
 * original ($estado === 'Vendido' ? (FechaVenta actual ?: hoy) : null) y delega
 * el null-coalescing de slug/descripcion/color en el repositorio.
 */
final class VehiculoEditarService
{
    public function __construct(
        private VehiculoRepository $repository,
    ) {}

    /** @return array{vehiculo:array<string,mixed>|null,garantia:array<string,mixed>|null,services:list<array<string,mixed>>,imagenes:list<array<string,mixed>>} */
    public function cargar(string $idVehiculo): array
    {
        return [
            'vehiculo' => $this->repository->datosEdicion($idVehiculo),
            'garantia' => $this->repository->garantiaReciente($idVehiculo),
            'services' => $this->repository->servicesVehiculo($idVehiculo),
            'imagenes' => $this->repository->imagenesVehiculo($idVehiculo),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function imagenes(string $idVehiculo): array
    {
        return $this->repository->imagenesVehiculo($idVehiculo);
    }

    /**
     * Actualiza el producto y el vehículo con los datos editados.
     *
     * @param array{
     *     idVehiculo: string,
     *     idProducto: int,
     *     modelo: string,
     *     slug: string,
     *     descripcion: string,
     *     descripcionWeb: string,
     *     costo: float,
     *     gastoTotal: float,
     *     precioVenta: float,
     *     precioDistribuidor: float|null,
     *     moneda: string,
     *     stock: int,
     *     estado: string,
     *     mostrarEnWeb: int,
     *     destacadoWeb: int,
     *     ordenWeb: int,
     *     textoBotonWeb: string,
     *     numeroMotor: string,
     *     color: string,
     *     numeroImportacion: int|null,
     *     tipoCambioImportacion: float|null,
     *     fechaVentaActual: string|null
     * } $datos
     */
    public function editar(array $datos): void
    {
        if ($datos['estado'] === 'Vendido') {
            throw new RuntimeException('El estado Vendido sólo puede originarse desde una venta registrada.');
        }

        $this->repository->actualizarProductoEdicion(
            $datos['idProducto'],
            $datos['modelo'],
            $datos['slug'] !== '' ? $datos['slug'] : null,
            $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
            $datos['descripcionWeb'] !== '' ? $datos['descripcionWeb'] : null,
            $datos['costo'],
            $datos['gastoTotal'],
            $datos['precioVenta'],
            $datos['precioDistribuidor'],
            $datos['moneda'],
            $datos['stock'],
            $datos['estado'],
            $datos['mostrarEnWeb'],
            $datos['destacadoWeb'],
            $datos['ordenWeb'],
            $datos['textoBotonWeb']
        );

        $fechaVenta = null;

        $this->repository->actualizarVehiculoEdicion(
            $datos['idVehiculo'],
            $datos['numeroMotor'],
            $datos['modelo'],
            $datos['color'] !== '' ? $datos['color'] : null,
            $fechaVenta,
            $datos['numeroImportacion'],
            $datos['tipoCambioImportacion'],
            $datos['estado']
        );
    }
}
