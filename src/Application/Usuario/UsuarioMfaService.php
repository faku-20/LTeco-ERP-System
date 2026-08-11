<?php

declare(strict_types=1);

namespace Lteco\Application\Usuario;

use Lteco\Infrastructure\Repository\UsuarioRepository;

/**
 * Casos de uso de MFA de usuarios (F3). La criptografía (generación de secreto
 * TOTP, protección del secreto, generación/hash de recovery codes) y los guards
 * se conservan en el handler; este servicio solo persiste los valores ya
 * resueltos. No relaja seguridad.
 */
final class UsuarioMfaService
{
    public function __construct(private UsuarioRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtener(int $id): ?array
    {
        return $this->repository->buscarParaMfa($id);
    }

    public function activar(int $id, string $secretProtegido, string $recoveryHasheados): void
    {
        $this->repository->activarMfa($id, $secretProtegido, $recoveryHasheados);
    }

    public function desactivar(int $id): void
    {
        $this->repository->desactivarMfa($id);
    }

    public function regenerarRecovery(int $id, string $recoveryHasheados): void
    {
        $this->repository->regenerarRecoveryCodes($id, $recoveryHasheados);
    }
}
