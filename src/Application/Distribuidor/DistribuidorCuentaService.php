<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use Lteco\Infrastructure\Repository\DistribuidorCuentaRepository;
use RuntimeException;

final class DistribuidorCuentaService
{
    public function __construct(private DistribuidorCuentaRepository $repository)
    {
    }

    public function resolverDistribuidorId(int $idDistribuidor): int
    {
        return $idDistribuidor > 0
            ? $idDistribuidor
            : $this->repository->primerDistribuidorActivoId();
    }

    /**
     * @return array{
     *   desde:string,
     *   hasta:string,
     *   distribuidor:array<string,mixed>|null,
     *   distribuidores:list<array<string,mixed>>,
     *   comisiones:list<array<string,mixed>>,
     *   resumen:array<string,float>
     * }
     */
    public function cargarCuenta(
        int $idDistribuidor,
        string $desde,
        string $hasta,
        bool $esAdmin
    ): array {
        $idDistribuidor = $this->resolverDistribuidorId($idDistribuidor);
        $desde = $this->normalizarFecha($desde, date('Y-m-01'));
        $hasta = $this->normalizarFecha($hasta, date('Y-m-d'));
        $comisiones = $this->repository->tablaComisionesDisponible()
            ? $this->repository->listarComisiones($idDistribuidor, $desde, $hasta)
            : [];
        $resumen = ['Pendiente' => 0.0, 'Aprobada' => 0.0, 'Pagada' => 0.0, 'Anulada' => 0.0];

        foreach ($comisiones as $comision) {
            $estado = (string) $comision['Estado'];
            if (isset($resumen[$estado])) {
                $resumen[$estado] += (float) $comision['Monto'];
            }
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'distribuidor' => $this->repository->buscarDistribuidor($idDistribuidor),
            'distribuidores' => $esAdmin ? $this->repository->listarDistribuidores() : [],
            'comisiones' => $comisiones,
            'resumen' => $resumen,
        ];
    }

    public function actualizarComision(
        int $idComision,
        int $idDistribuidor,
        string $accion
    ): string {
        $estado = match (trim($accion)) {
            'aprobar' => 'Aprobada',
            'pagar' => 'Pagada',
            'anular' => 'Anulada',
            default => '',
        };

        if ($idComision <= 0 || $estado === '') {
            throw new RuntimeException('Acción inválida.');
        }

        $this->repository->actualizarEstadoComision($idComision, $idDistribuidor, $estado);
        return $estado;
    }

    private function normalizarFecha(string $fecha, string $default): string
    {
        $fecha = trim($fecha);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : $default;
    }
}
