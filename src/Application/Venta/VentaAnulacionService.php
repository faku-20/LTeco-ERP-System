<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Domain\Venta\ReglasAnulacion;
use Lteco\Domain\Venta\ResultadoAnulacion;
use Lteco\Infrastructure\Repository\VentaAnulacionRepository;
use RuntimeException;

final class VentaAnulacionService
{
    public function __construct(private VentaAnulacionRepository $repository)
    {
    }

    public function anular(
        int $idVenta,
        string $motivo,
        int $idUsuario,
        string $usuarioAnulacion
    ): ResultadoAnulacion {
        $venta = $this->repository->bloquearVenta($idVenta);
        if ($venta === null) {
            throw new RuntimeException('La venta no existe.');
        }

        if (($venta['EstadoVenta'] ?? '') === 'Anulada') {
            throw new RuntimeException('La venta ya estaba anulada.');
        }

        $detalles = $this->repository->detalles($idVenta);
        $idDistribuidor = (int) ($venta['DistribuidorVendedorId'] ?? 0);
        foreach ($detalles as $item) {
            $idProducto = (int) $item['Producto_IdProducto'];
            $cantidad = (int) $item['Cantidad'];
            $tipoProducto = trim((string) ($item['TipoProducto'] ?? ''));

            if (ReglasAnulacion::esVehiculo($tipoProducto)) {
                if ($idDistribuidor > 0) {
                    $this->repository->restaurarVehiculoDistribuidor($idProducto);
                } elseif (!$this->repository->existeOtraVentaActiva($idProducto, $idVenta)) {
                    $this->repository->restaurarVehiculo($idProducto);
                }
            } elseif ($idDistribuidor === 0) {
                $this->repository->restaurarRepuesto($idProducto, $cantidad);
            }
        }

        if ($idDistribuidor > 0 && $this->repository->tablaExiste('distribuidor_stock')) {
            foreach ($detalles as $item) {
                $idProducto = (int) $item['Producto_IdProducto'];
                $cantidad = (int) $item['Cantidad'];
                $tipoProducto = (string) ($item['TipoProducto'] ?? '');

                if (ReglasAnulacion::esVehiculo($tipoProducto)) {
                    $this->repository->restaurarStockDistribuidorVehiculo(
                        $idProducto,
                        $idDistribuidor
                    );
                } else {
                    $this->repository->restaurarStockDistribuidorRepuesto(
                        $idProducto,
                        $idDistribuidor,
                        $cantidad
                    );
                }
            }
        }

        if ($idDistribuidor > 0 && $this->repository->tablaExiste('remito')) {
            $this->repository->anularRemito($idVenta);
        }

        $this->repository->anularVenta(
            $idVenta,
            $motivo,
            $idUsuario > 0 ? $idUsuario : null,
            $usuarioAnulacion !== '' ? $usuarioAnulacion : null
        );

        $comisiones = 0;
        $gastos = 0;
        $estadoGasto = null;
        $garantias = 0;
        $services = 0;

        $infoComision = $this->repository->columnaInfo('distribuidor_comision', 'Estado');
        if ($infoComision !== null) {
            ReglasAnulacion::requerirEstado($infoComision, 'distribuidor_comision', 'Anulada');
            $comisiones = $this->repository->anularComisiones($idVenta);
        }

        $infoGasto = $this->repository->columnaInfo('gasto', 'Estado');
        if ($infoGasto !== null) {
            $estadoGasto = ReglasAnulacion::estadoGastoAnulado($infoGasto);
            $gastos = $this->repository->inactivarGastosComision(
                $idVenta,
                $motivo,
                $estadoGasto
            );
        }

        $infoGarantia = $this->repository->columnaInfo('garantia', 'Estado');
        if ($infoGarantia !== null) {
            ReglasAnulacion::requerirEstado($infoGarantia, 'garantia', 'Anulada');
            $garantias = $this->repository->anularGarantias($idVenta);
        }

        $infoService = $this->repository->columnaInfo('service_vehiculo', 'Estado');
        if ($infoService !== null) {
            ReglasAnulacion::requerirEstado($infoService, 'service_vehiculo', 'Cancelado');
            $services = $this->repository->cancelarServices($idVenta, $motivo);
        }

        return new ResultadoAnulacion(
            count($detalles),
            $comisiones,
            $gastos,
            $estadoGasto,
            $garantias,
            $services
        );
    }
}
