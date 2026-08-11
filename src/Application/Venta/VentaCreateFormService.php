<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Infrastructure\Repository\VentaCreateFormRepository;

/**
 * Compone los datos iniciales del formulario de alta sin alterar sus defaults.
 */
final class VentaCreateFormService
{
    public function __construct(
        private VentaCreateFormRepository $repository,
        private VentaCommercialService $commercialService
    ) {
    }

    /**
     * @return array{
     *   distribuidoresActivos:array<int,array<string,mixed>>,
     *   configComercial:array<string,float>,
     *   clientes:array<int,array<string,mixed>>,
     *   vehiculos:array<int,array<string,mixed>>,
     *   repuestos:array<int,array<string,mixed>>
     * }
     */
    public function cargar(int $idUsuario, bool $esVendedor, float $tasaIvaDefault): array
    {
        $configComercial = $this->commercialService->configuracionFormulario($tasaIvaDefault);
        $comisionUsuario = $this->commercialService->comisionVendedor($idUsuario);
        if ($comisionUsuario > 0) {
            $configComercial['ComisionVendedor'] = $comisionUsuario;
        }

        return [
            'distribuidoresActivos' => $this->repository->distribuidoresActivos(),
            'configComercial' => $configComercial,
            'clientes' => $this->repository->clientes($esVendedor, $idUsuario),
            'vehiculos' => $this->repository->vehiculosDisponibles(),
            'repuestos' => $this->repository->repuestosConStock(),
        ];
    }

}
