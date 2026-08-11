<?php

declare(strict_types=1);

namespace Lteco\Application\Auditoria;

use Lteco\Infrastructure\Repository\AuditoriaEscrituraRepository;

final class AuditoriaEscrituraService
{
    public function __construct(private AuditoriaEscrituraRepository $repository)
    {
    }

    /** @param array<string,mixed> $datos */
    public function registrar(array $datos): void
    {
        if (!$this->repository->tablaDisponible()) {
            return;
        }
        $this->repository->registrar($datos);
    }
}
