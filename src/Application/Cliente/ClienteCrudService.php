<?php

declare(strict_types=1);

namespace Lteco\Application\Cliente;

use Lteco\Infrastructure\Repository\ClienteCrudRepository;

final class ClienteCrudService
{
    public function __construct(private ClienteCrudRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtener(int $idCliente): ?array
    {
        return $this->repository->buscar($idCliente);
    }

    /** @return list<array<string,mixed>> */
    public function listarParaSelector(): array
    {
        return $this->repository->listarParaSelector();
    }

    public function existe(int $idCliente): bool
    {
        return $this->repository->existe($idCliente);
    }

    public function telefonoDisponible(?string $telefono, ?int $excluirId): bool
    {
        return $this->repository->telefonoDisponible($telefono, $excluirId);
    }

    public function correoDisponible(?string $correo, ?int $excluirId): bool
    {
        return $this->repository->correoDisponible($correo, $excluirId);
    }

    public function cedulaDisponible(?string $cedula, ?int $excluirId): bool
    {
        return $this->repository->cedulaDisponible($cedula, $excluirId);
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
    public function editar(int $idCliente, array $datos): void
    {
        $this->repository->editar($idCliente, $datos);
    }
}
