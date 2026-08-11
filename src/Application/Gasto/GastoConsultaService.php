<?php

declare(strict_types=1);

namespace Lteco\Application\Gasto;

use Lteco\Infrastructure\Repository\GastoConsultaRepository;

/**
 * Caso de uso de lectura de gastos (E1): listado y exportación comparten el
 * mismo listado filtrado. La conversión a UYU y los totales quedan en la vista
 * porque dependen de los helpers globales (convertirAUyu / tipo de cambio).
 */
final class GastoConsultaService
{
    public function __construct(private GastoConsultaRepository $repository)
    {
    }

    /**
     * @param array{categoria:string,metodo:string,desde:string,hasta:string} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros): array
    {
        return $this->repository->listar($filtros);
    }
}
