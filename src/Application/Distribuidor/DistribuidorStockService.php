<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use Lteco\Infrastructure\Repository\DistribuidorStockRepository;
use RuntimeException;

/**
 * Asignación de stock interno hacia el stock propio de un distribuidor.
 *
 * Origen: el helper distribuidorUpsertStock() de
 * lteco-panel/distribuidores/_common.php y el núcleo de escritura de
 * lteco-panel/distribuidores/asignar_stock.php. Movido aquí SIN cambios de
 * comportamiento (misma semántica de upsert y de descuento de producto).
 *
 * Sin side effects de HTTP: recibe entradas YA validadas/normalizadas por el
 * handler (distribuidor, tipo de ítem, cantidad y precios resueltos) y persiste
 * los efectos en distribuidor_stock + producto.
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo el
 * dueño (encadena la auditoría dentro de su transacción).
 */
final class DistribuidorStockService
{
    public function __construct(
        private DistribuidorStockRepository $repository,
    ) {}

    /** @return array{distribuidores:list<array<string,mixed>>,vehiculos:list<array<string,mixed>>,repuestos:list<array<string,mixed>>} */
    public function datosFormularioAsignacion(): array
    {
        return [
            'distribuidores' => $this->repository->distribuidoresActivos(),
            'vehiculos' => $this->repository->vehiculosDisponibles(),
            'repuestos' => $this->repository->repuestosDisponibles(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function itemDisponible(string $tipoItem, ?string $idVehiculo, ?int $idRepuesto): ?array
    {
        return $this->repository->itemDisponible($tipoItem, $idVehiculo, $idRepuesto);
    }

    /**
     * Upsert de la fila de stock del distribuidor: si ya existe suma la cantidad
     * y pisa los precios; si no, inserta una fila nueva. Réplica exacta de
     * distribuidorUpsertStock() de _common.php.
     */
    public function upsertStock(
        int $idDistribuidor,
        string $tipoItem,
        ?string $idVehiculo,
        ?int $idRepuesto,
        int $cantidad,
        float $precioVenta,
        float $precioMinimo
    ): void {
        $actual = $tipoItem === 'Vehiculo'
            ? $this->repository->buscarStockVehiculo($idDistribuidor, $idVehiculo)
            : $this->repository->buscarStockRepuesto($idDistribuidor, $idRepuesto);

        if ($actual) {
            $this->repository->sumarCantidadStock((int) $actual['IdStock'], $cantidad, $precioVenta, $precioMinimo);
            return;
        }

        $this->repository->insertarStock($idDistribuidor, $tipoItem, $idVehiculo, $idRepuesto, $cantidad, $precioVenta, $precioMinimo);
    }

    /**
     * Núcleo de asignar_stock.php: asigna stock al distribuidor (upsert),
     * descuenta el stock interno del producto y, si se agota, lo marca
     * 'Sin stock'. La decisión de agotamiento usa el stock PREVIO al descuento
     * ($stockActual), igual que el handler legacy.
     */
    public function asignarStock(
        int $idDistribuidor,
        string $tipoItem,
        ?string $idVehiculo,
        ?int $idRepuesto,
        int $cantidad,
        float $precioVenta,
        float $precioMinimo,
        int $idProducto,
        int $stockActual
    ): void {
        $this->upsertStock($idDistribuidor, $tipoItem, $idVehiculo, $idRepuesto, $cantidad, $precioVenta, $precioMinimo);

        if (!$this->repository->descontarStockProducto($idProducto, $cantidad)) {
            throw new RuntimeException('No hay suficiente stock interno para asignar el producto al distribuidor.');
        }

        if ($stockActual - $cantidad <= 0) {
            $this->repository->marcarProductoSinStock($idProducto);
        }
    }
}
