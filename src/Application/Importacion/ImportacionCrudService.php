<?php

declare(strict_types=1);

namespace Lteco\Application\Importacion;

use Lteco\Infrastructure\Repository\ImportacionCrudRepository;

/**
 * Casos de uso de escritura de importaciones (E4): alta y edición. La
 * validación con helpers globales, el redirect (sin flash) y la ausencia de
 * auditoría se conservan en el handler para preservar el comportamiento legacy.
 */
final class ImportacionCrudService
{
    public function __construct(private ImportacionCrudRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtener(int $idImportacion): ?array
    {
        return $this->repository->buscar($idImportacion);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos): int
    {
        return $this->repository->crear($datos);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function editar(int $idImportacion, array $datos): void
    {
        $this->repository->editar($idImportacion, $datos);
    }
}
