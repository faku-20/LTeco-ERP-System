<?php

declare(strict_types=1);

namespace Lteco\Application\Repuesto;

use Lteco\Infrastructure\Repository\RepuestoConsultaRepository;

final class RepuestoConsultaService
{
    public function __construct(private RepuestoConsultaRepository $repository)
    {
    }

    /**
     * Resuelve el distribuidor de la sesión: usa el id de sesión, o lo busca
     * por usuario si no vino, y devuelve sus datos básicos. Replica index.php.
     *
     * @return array<string,mixed>|null
     */
    public function resolverDistribuidorActual(int $idUsuario, int $idDistribuidorSesion): ?array
    {
        if ($idDistribuidorSesion <= 0) {
            $idDistribuidorSesion = $this->repository->distribuidorIdPorUsuario($idUsuario);
        }
        if ($idDistribuidorSesion <= 0) {
            return null;
        }
        return $this->repository->distribuidorBasico($idDistribuidorSesion);
    }

    /**
     * @param array{estado:string,q:string,importacion:string,es_distribuidor:bool} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros): array
    {
        return $this->repository->listar($filtros);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function importaciones(): array
    {
        return $this->repository->importaciones();
    }

    /**
     * Conteos para los pills, con el mismo armado que index.php.
     *
     * @return array<string,int>
     */
    public function conteos(bool $esDistribuidor): array
    {
        $conteos = ['' => 0, 'Disponible' => 0, 'Sin stock' => 0, 'Oculto' => 0];
        foreach ($this->repository->conteosPorEstado() as $estado => $cant) {
            $conteos[$estado] = $cant;
            $conteos[''] += $cant;
        }
        if ($esDistribuidor) {
            $conteos[''] = $this->repository->conteoDisponiblesDistribuidor();
        }
        return $conteos;
    }
}
