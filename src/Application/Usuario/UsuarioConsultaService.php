<?php

declare(strict_types=1);

namespace Lteco\Application\Usuario;

use Lteco\Infrastructure\Repository\UsuarioRepository;

/**
 * Caso de uso de lectura de usuarios (F1): listado jerárquico.
 */
final class UsuarioConsultaService
{
    public function __construct(private UsuarioRepository $repository)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(): array
    {
        return $this->repository->listar();
    }
}
