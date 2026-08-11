<?php

declare(strict_types=1);

namespace Lteco\Application\Dashboard;

use Lteco\Domain\Dashboard\DashboardCalculo;
use Lteco\Infrastructure\Repository\DashboardRepository;

final class DashboardService
{
    public function __construct(private DashboardRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function cargar(float $tipoCambio, bool $gestionaWeb, string $mesActual): array
    {
        $ventas = $this->repository->ventas();
        $deudas = DashboardCalculo::deudas($this->repository->deudas(), $tipoCambio);

        return [
            'resumen' => DashboardCalculo::resumenVentas($ventas, $tipoCambio, $mesActual),
            'inventario' => DashboardCalculo::inventario(
                $this->repository->productos(),
                $tipoCambio,
                $gestionaWeb
            ),
            'clientesConDeuda' => $deudas['cantidad'],
            'clientesDeudaDetalle' => $deudas['detalle'],
            'topClientes' => DashboardCalculo::topClientes(
                $this->repository->comprasClientes(),
                $tipoCambio
            ),
            'ultimasVentas' => array_slice(array_values(array_filter(
                $ventas,
                static fn(array $venta): bool => ($venta['EstadoVenta'] ?? '') !== 'Anulada'
            )), 0, 8),
        ];
    }
}
