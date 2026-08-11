<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Infrastructure\Repository\VentaRepository;

/**
 * Persiste cabecera y detalles usando la transacción administrada por el llamador.
 */
final class VentaPersistenceService
{
    public function __construct(private VentaRepository $repository)
    {
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crearCabecera(array $datos): int
    {
        return $this->repository->insertVenta(VentaPersistenceData::cabecera($datos));
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function agregarDetalle(array $datos): int
    {
        return $this->repository->insertDetalle(VentaPersistenceData::detalle($datos));
    }

    public function asignarNumeroFactura(int $idVenta, string $numeroFactura): void
    {
        $this->repository->asignarNumeroFacturaSiVacio($idVenta, $numeroFactura);
    }

    /** @return array<string,mixed>|null */
    public function bloquearVehiculo(string $idVehiculo): ?array
    {
        return $this->repository->bloquearVehiculoVenta($idVehiculo);
    }

    public function vehiculoTieneFechaVenta(string $idVehiculo): bool
    {
        return $this->repository->vehiculoTieneFechaVenta($idVehiculo);
    }

    public function productoTieneVentaActiva(int $idProducto): bool
    {
        return $this->repository->productoTieneVentaActiva($idProducto);
    }

    /** @return array<string,mixed>|null */
    public function bloquearRepuesto(int $idRepuesto): ?array
    {
        return $this->repository->bloquearRepuestoVenta($idRepuesto);
    }

    /** @return array<string,mixed>|null */
    public function clienteWhatsapp(int $idCliente): ?array
    {
        return $this->repository->clienteWhatsapp($idCliente);
    }

    public function garantiaFechaFinPorVenta(int $idVenta): ?string
    {
        return $this->repository->garantiaFechaFinPorVenta($idVenta);
    }

    /** @return list<string> */
    public function serviceFechasProgramadasPorVenta(int $idVenta, int $limite = 3): array
    {
        return $this->repository->serviceFechasProgramadasPorVenta($idVenta, $limite);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function cerrarVenta(int $idVenta, array $datos): void
    {
        $this->repository->actualizarVenta($idVenta, VentaPersistenceData::cierre($datos));
    }
}
