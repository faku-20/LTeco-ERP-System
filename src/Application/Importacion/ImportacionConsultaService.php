<?php

declare(strict_types=1);

namespace Lteco\Application\Importacion;

use Lteco\Infrastructure\Repository\ImportacionConsultaRepository;

/**
 * Caso de uso de lectura de importaciones (E3): listado con conteo de vehículos.
 */
final class ImportacionConsultaService
{
    public function __construct(private ImportacionConsultaRepository $repository)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(): array
    {
        return $this->repository->listarConConteoVehiculos();
    }

    /** @return list<array<string,mixed>> */
    public function listarActivasParaSelector(): array
    {
        return $this->repository->listarActivasParaSelector();
    }

    public function tipoCambioActivo(int $numero): ?float
    {
        return $this->repository->tipoCambioActivo($numero);
    }

    public function tipoCambioMasRecienteActivo(?float $porDefecto = null): float
    {
        $valor = $this->repository->tipoCambioMasRecienteActivo();
        if ($valor !== null && $valor > 0) {
            return $valor;
        }
        return $porDefecto ?? \defaultTipoCambioUSD();
    }
}
