<?php

declare(strict_types=1);

namespace Lteco\Application\Balance;

use Lteco\Infrastructure\Repository\BalanceRepository;

/**
 * Caso de uso de lectura del balance financiero (E5). Coordina las lecturas de
 * ventas y gastos. Las conversiones de moneda por fila dependen de helpers
 * globales y se mantienen en la vista; las fórmulas puras viven en
 * Lteco\Domain\Balance\BalanceCalculo.
 */
final class BalanceService
{
    public function __construct(private BalanceRepository $repository)
    {
    }

    /** @return list<array<string,mixed>> */
    public function mesesResumen(float $tipoCambio, int $limite = 6): array
    {
        $rows = $this->repository->mesesResumen($tipoCambio, $limite);
        foreach ($rows as &$row) {
            $row['balance_uyu'] = (float) $row['ganancia_uyu'] - (float) $row['gastos_uyu'];
        }
        unset($row);
        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function ventasResumen(string $desde, string $hasta): array
    {
        return $this->repository->ventasResumen($desde, $hasta);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function gastosResumen(string $desde, string $hasta): array
    {
        return $this->repository->gastosResumen($desde, $hasta);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function ventasExport(string $desde, string $hasta): array
    {
        return $this->repository->ventasExport($desde, $hasta);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function gastosExport(string $desde, string $hasta): array
    {
        return $this->repository->gastosExport($desde, $hasta);
    }
}
