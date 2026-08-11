<?php

declare(strict_types=1);

namespace Lteco\Application\Busqueda;

use Lteco\Infrastructure\Repository\BusquedaRepository;

/**
 * Compone los resultados del buscador global preservando el alcance legacy.
 */
final class BusquedaService
{
    public function __construct(private BusquedaRepository $repository)
    {
    }

    /**
     * @return array{
     *   resultados:array<string,list<array<string,mixed>>>,
     *   recientes:array<string,list<array<string,mixed>>>,
     *   clientesConDetalle:array<int,array<string,mixed>>,
     *   clienteAjenoCoincide:bool
     * }
     */
    public function consultar(string $q, int $idUsuarioVendedor, bool $incluirResumenServices): array
    {
        $vacios = ['vehiculos' => [], 'clientes' => [], 'ventas' => [], 'repuestos' => []];
        if ($q === '') {
            return [
                'resultados' => $vacios,
                'recientes' => $this->repository->recientes(),
                'clientesConDetalle' => [],
                'clienteAjenoCoincide' => false,
            ];
        }

        $qLike = '%' . $q . '%';
        $resultados = [
            'vehiculos' => $this->repository->buscarVehiculos($qLike),
            'clientes' => $this->repository->buscarClientes($qLike, $idUsuarioVendedor),
            'ventas' => $this->repository->buscarVentas($qLike, $idUsuarioVendedor),
            'repuestos' => $this->repository->buscarRepuestos($qLike),
        ];

        $clientesConDetalle = [];
        foreach ($resultados['clientes'] as $cliente) {
            $idCliente = (int) $cliente['IdCliente'];
            $motos = $this->repository->motosCliente($idCliente, $idUsuarioVendedor);

            if ($incluirResumenServices) {
                foreach ($motos as &$moto) {
                    $idVehiculo = (string) ($moto['IdVehiculo'] ?? '');
                    if ($idVehiculo === '') {
                        continue;
                    }
                    $resumen = $this->repository->resumenServicesVehiculo($idVehiculo);
                    $moto['svc_realizados'] = (int) ($resumen['Realizados'] ?? 0);
                    $moto['svc_proximo'] = $resumen['Proximo'] ?? null;
                }
                unset($moto);
            }

            $clientesConDetalle[$idCliente] = [
                'cliente' => $cliente,
                'ventas' => $this->repository->ventasCliente($idCliente, $idUsuarioVendedor),
                'motos' => $motos,
            ];
        }

        return [
            'resultados' => $resultados,
            'recientes' => $vacios,
            'clientesConDetalle' => $clientesConDetalle,
            'clienteAjenoCoincide' => $this->repository->clienteAjenoCoincide($qLike, $idUsuarioVendedor),
        ];
    }
}
