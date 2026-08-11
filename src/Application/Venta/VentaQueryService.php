<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Infrastructure\Repository\VentaReadRepository;

final class VentaQueryService
{
    public function __construct(private VentaReadRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function ventaConCliente(int $idVenta): ?array
    {
        return $this->repository->ventaConCliente($idVenta);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function detalles(int $idVenta): array
    {
        return $this->repository->detalles($idVenta);
    }

    /**
     * @return array{venta:array<string,mixed>|null,vehiculo:array<string,mixed>|null}
     */
    public function datosWhatsapp(int $idVenta): array
    {
        return [
            'venta' => $this->repository->ventaParaWhatsapp($idVenta),
            'vehiculo' => $this->repository->primerVehiculoVenta($idVenta),
        ];
    }

    /**
     * Conserva la regla legacy: usa solamente el primer detalle de tipo Moto
     * que tenga vehículo asociado.
     *
     * @param list<array<string,mixed>> $detalles
     * @return array{garantiaFin:?string,serviceDates:list<string>}
     */
    public function garantiaYServices(int $idVenta, array $detalles): array
    {
        foreach ($detalles as $detalle) {
            if (($detalle['TipoProducto'] ?? '') !== 'Moto' || empty($detalle['IdVehiculo'])) {
                continue;
            }

            $idVehiculo = (string) $detalle['IdVehiculo'];

            return [
                'garantiaFin' => $this->repository->garantiaFin($idVehiculo, $idVenta),
                'serviceDates' => $this->repository->fechasService($idVehiculo, $idVenta),
            ];
        }

        return [
            'garantiaFin' => null,
            'serviceDates' => [],
        ];
    }
}
