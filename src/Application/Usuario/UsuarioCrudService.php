<?php

declare(strict_types=1);

namespace Lteco\Application\Usuario;

use Lteco\Infrastructure\Repository\UsuarioRepository;

/**
 * Casos de uso de gestión de usuarios (F2): alta, edición, cambio de clave,
 * baja y activación. Los guards de rol/jerarquía, el hashing de contraseñas, la
 * validación de clave y la auditoría se conservan en el handler; este servicio
 * coordina lecturas y escrituras vía repositorio.
 */
final class UsuarioCrudService
{
    public function __construct(private UsuarioRepository $repository)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function distribuidoresActivos(): array
    {
        return $this->repository->distribuidoresActivos();
    }

    public function usuarioDisponible(string $usuario): bool
    {
        return !$this->repository->existeUsuario($usuario);
    }

    public function usuarioDisponibleExcepto(string $usuario, int $idExcluido): bool
    {
        return !$this->repository->existeUsuarioExcepto($usuario, $idExcluido);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerParaEdicion(int $id): ?array
    {
        return $this->repository->buscarParaEdicion($id);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerParaClave(int $id): ?array
    {
        return $this->repository->buscarParaClave($id);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerParaEliminar(int $id): ?array
    {
        return $this->repository->buscarParaEliminar($id);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerParaToggle(int $id): ?array
    {
        return $this->repository->buscarParaToggle($id);
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
    public function actualizar(int $id, array $datos): void
    {
        $this->repository->actualizar($id, $datos);
    }

    public function cambiarClave(int $id, string $claveHash): void
    {
        $this->repository->actualizarClave($id, $claveHash);
    }

    public function eliminar(int $id): void
    {
        $this->repository->eliminar($id);
    }

    public function actualizarActivo(int $id, int $activo): void
    {
        $this->repository->actualizarActivo($id, $activo);
    }
}
