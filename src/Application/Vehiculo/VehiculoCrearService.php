<?php

declare(strict_types=1);

namespace Lteco\Application\Vehiculo;

use Lteco\Infrastructure\Repository\VehiculoRepository;
use RuntimeException;

/**
 * Creación de un vehículo nuevo (producto 'Moto' + fila de vehiculo).
 * Origen: el bloque de persistencia inline de lteco-panel/vehiculos/crear.php.
 *
 * Sin side effects de HTTP: recibe entradas YA validadas/normalizadas por el
 * handler (precios, estado, moneda, flags de publicación, RUT de empresa, TC de
 * importación, etc.) y devuelve el IdVehiculo + IdProducto creados para que el
 * caller registre auditoría y arme el redirect.
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo el
 * dueño (también encadena el guardado de imágenes dentro de su transacción).
 *
 * Réplica del comportamiento legacy: genera el IdVehiculo 'Vxxxx' desde la
 * secuencia, deriva el slug (manual o automático modelo-IdVehiculo) y re-chequea
 * su unicidad lanzando RuntimeException con el mismo texto que la página original
 * ('El slug generado ya existe. Ajustá el slug manualmente.') para que
 * mensajeErrorSeguro lo muestre igual y el handler haga rollback.
 */
final class VehiculoCrearService
{
    public function __construct(
        private VehiculoRepository $repository,
    ) {}

    /**
     * Crea el producto y el vehículo. Devuelve los ids generados.
     *
     * @param array{
     *     puedeGestionarCatalogoWeb: bool,
     *     modelo: string,
     *     slugManual: string,
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
     *     empresaRut: string,
     *     numeroMotor: string,
     *     color: string,
     *     numeroImportacion: int|null,
     *     tipoCambioImportacion: float|null
     * } $datos
     *
     * @return array{idVehiculo: string, idProducto: int}
     *
     * @throws RuntimeException si el slug generado ya está en uso.
     */
    public function crear(array $datos): array
    {
        if ($datos['estado'] === 'Vendido') {
            throw new RuntimeException('El estado Vendido sólo puede originarse desde una venta registrada.');
        }

        $puedeGestionarCatalogoWeb = $datos['puedeGestionarCatalogoWeb'];
        $modelo = $datos['modelo'];
        $slugManual = $datos['slugManual'];

        $idVehiculoNum = $this->repository->reservarSecuenciaVehiculo();
        $idVehiculo = 'V' . str_pad((string) $idVehiculoNum, 4, '0', STR_PAD_LEFT);

        $slug = $puedeGestionarCatalogoWeb
            ? ($slugManual !== '' ? $slugManual : \slugify($modelo . '-' . $idVehiculo))
            : '';

        if ($puedeGestionarCatalogoWeb && $slug !== '' && !$this->repository->slugProductoDisponible($slug)) {
            throw new RuntimeException('El slug generado ya existe. Ajustá el slug manualmente.');
        }

        $idProducto = $this->repository->insertarProductoMoto(
            $modelo,
            $slug !== '' ? $slug : null,
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
            $datos['textoBotonWeb'],
            $datos['empresaRut']
        );

        $this->repository->insertarVehiculo(
            $idVehiculo,
            $idProducto,
            $datos['numeroMotor'],
            $modelo,
            $datos['color'] !== '' ? $datos['color'] : null,
            $datos['numeroImportacion'],
            $datos['tipoCambioImportacion'],
            null
        );

        return [
            'idVehiculo' => $idVehiculo,
            'idProducto' => $idProducto,
        ];
    }
}
