<?php

declare(strict_types=1);

namespace Lteco\Application\Auditoria;

use Lteco\Infrastructure\Repository\AuditoriaRepository;

/**
 * Caso de uso read-only de la pantalla de auditoría (G1).
 */
final class AuditoriaConsultaService
{
    public function __construct(private AuditoriaRepository $repository)
    {
    }

    public function tablaExiste(): bool
    {
        return $this->repository->tablaExiste();
    }

    /**
     * @param array{q:string,modulo:string,accion:string,usuario:string,desde:string,hasta:string} $filtros
     * @return array{
     *   total:int,
     *   registros:list<array<string,mixed>>,
     *   modulos:list<string>,
     *   acciones:list<string>,
     *   usuarios:list<string>
     * }
     */
    public function consultar(array $filtros, int $pagina, int $porPagina): array
    {
        $pagina = max(1, $pagina);
        $porPagina = max(1, $porPagina);
        $opciones = $this->repository->opciones();

        return [
            'total' => $this->repository->contar($filtros),
            'registros' => $this->repository->listar(
                $filtros,
                $porPagina,
                ($pagina - 1) * $porPagina
            ),
            'modulos' => $opciones['modulos'],
            'acciones' => $opciones['acciones'],
            'usuarios' => $opciones['usuarios'],
        ];
    }
}
