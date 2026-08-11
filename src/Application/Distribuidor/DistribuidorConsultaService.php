<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use Lteco\Infrastructure\Repository\DistribuidorConsultaRepository;

/**
 * Coordina las lecturas simples de los listados de Distribuidores C3.
 */
final class DistribuidorConsultaService
{
    public const ESTADOS_REPORTE = ['', 'Nuevo', 'Revisado', 'Resuelto'];

    public function __construct(private DistribuidorConsultaRepository $repository)
    {
    }

    public function tablasDistribuidorListas(): bool
    {
        return $this->repository->tablaExiste('distribuidor_stock')
            && $this->repository->tablaExiste('distribuidor_pedido');
    }

    /**
     * @return array<string,mixed>
     */
    public function panelDistribuidor(int $idDistribuidor): array
    {
        $pedidos = ['Pendiente' => 0, 'Aprobado' => 0, 'Rechazado' => 0];
        foreach ($this->repository->pedidosPorEstado($idDistribuidor) as $fila) {
            $pedidos[(string) $fila['Estado']] = (int) $fila['Cant'];
        }

        return [
            'distribuidor' => $this->repository->distribuidor($idDistribuidor),
            'stockTotal' => $this->repository->stockTotal($idDistribuidor),
            'pedidos' => $pedidos,
            'postventasAbiertas' => $this->repository->postventasAbiertas($idDistribuidor),
            'remitosPendientes' => $this->repository->remitosPendientes($idDistribuidor),
            'stockItems' => $this->repository->stockAsignado($idDistribuidor),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDistribuidores(bool $tablasDistribuidorListas): array
    {
        return $this->repository->listarDistribuidores($tablasDistribuidorListas);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pedidos(?int $idDistribuidor): array
    {
        return $this->repository->pedidos($idDistribuidor);
    }

    /**
     * @return array{ventas:list<array<string,mixed>>,total:float,ventasActivas:int,ventasAnuladas:int}
     */
    public function ventas(int $idDistribuidor): array
    {
        $ventas = $this->repository->ventas($idDistribuidor);
        $total = 0.0;
        $ventasActivas = 0;
        $ventasAnuladas = 0;

        foreach ($ventas as $venta) {
            if (($venta['EstadoVenta'] ?? 'Confirmada') === 'Anulada') {
                $ventasAnuladas++;
                continue;
            }

            $ventasActivas++;
            $total += (float) $venta['Total'];
        }

        return compact('ventas', 'total', 'ventasActivas', 'ventasAnuladas');
    }

    /**
     * @return array{q:string,stock:list<array<string,mixed>>,ventas:list<array<string,mixed>>,clientes:list<array<string,mixed>>,totalResultados:int}
     */
    public function buscar(int $idDistribuidor, string $q): array
    {
        $q = trim($q);
        $stock = [];
        $ventas = [];
        $clientes = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $stock = $this->repository->buscarStock($idDistribuidor, $like);
            $ventas = $this->repository->buscarVentas($idDistribuidor, $like);
            $clientes = $this->repository->buscarClientes($idDistribuidor, $like);
        }

        return [
            'q' => $q,
            'stock' => $stock,
            'ventas' => $ventas,
            'clientes' => $clientes,
            'totalResultados' => count($stock) + count($ventas) + count($clientes),
        ];
    }

    /**
     * @return array{tablaLista:bool,filtroEstado:string,reportes:list<array<string,mixed>>}
     */
    public function reportes(string $filtroEstado): array
    {
        $filtroEstado = trim($filtroEstado);
        if (!in_array($filtroEstado, self::ESTADOS_REPORTE, true)) {
            $filtroEstado = '';
        }

        $tablaLista = $this->repository->tablaExiste('distribuidor_reporte_problema');

        return [
            'tablaLista' => $tablaLista,
            'filtroEstado' => $filtroEstado,
            'reportes' => $tablaLista ? $this->repository->reportes($filtroEstado) : [],
        ];
    }
}
