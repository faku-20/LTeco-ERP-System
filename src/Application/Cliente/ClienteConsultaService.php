<?php

declare(strict_types=1);

namespace Lteco\Application\Cliente;

use Lteco\Infrastructure\Repository\ClienteConsultaRepository;

final class ClienteConsultaService
{
    public function __construct(private ClienteConsultaRepository $repository)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(string $filtroCliente, int $idUsuarioVendedor, float $tipoCambio): array
    {
        return $this->repository->listar($filtroCliente, $idUsuarioVendedor, $tipoCambio);
    }

    /**
     * Listado de clientes para exportación CSV (incluye FechaCliente, sin alcance).
     *
     * @return list<array<string,mixed>>
     */
    public function listarParaExport(string $filtroCliente, float $tipoCambio): array
    {
        return $this->repository->listarParaExport($filtroCliente, $tipoCambio);
    }

    /**
     * Filtro en memoria por tipo (con_compras / sin_compras / saldo). Replica index.php.
     *
     * @param list<array<string,mixed>> $clientes
     * @return list<array<string,mixed>>
     */
    public function aplicarFiltroTipo(array $clientes, string $filtroTipo): array
    {
        if ($filtroTipo === 'con_compras') {
            return array_values(array_filter($clientes, static fn($c) => (int) ($c['Compras'] ?? 0) > 0));
        }
        if ($filtroTipo === 'sin_compras') {
            return array_values(array_filter($clientes, static fn($c) => (int) ($c['Compras'] ?? 0) === 0));
        }
        if ($filtroTipo === 'saldo') {
            return array_values(array_filter($clientes, static fn($c) => (float) ($c['SaldoPendienteUYU'] ?? 0) > 0));
        }
        return $clientes;
    }

    /**
     * Conteos para los pills, con el mismo armado que index.php.
     *
     * @return array{total:int,conCompras:int,sinCompras:int,conSaldo:int}
     */
    public function conteos(int $idUsuarioVendedor, float $tipoCambio): array
    {
        $total = 0;
        $conCompras = 0;
        $conSaldo = 0;
        foreach ($this->repository->filasConteos($idUsuarioVendedor, $tipoCambio) as $row) {
            $total++;
            if ((int) ($row['Compras'] ?? 0) > 0) {
                $conCompras++;
            }
            if ((float) ($row['Saldo'] ?? 0) > 0) {
                $conSaldo++;
            }
        }
        return [
            'total' => $total,
            'conCompras' => $conCompras,
            'sinCompras' => $total - $conCompras,
            'conSaldo' => $conSaldo,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function cliente(int $idCliente): ?array
    {
        return $this->repository->cliente($idCliente);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function ventas(int $idCliente, int $idUsuarioVendedor): array
    {
        return $this->repository->ventas($idCliente, $idUsuarioVendedor);
    }

    /**
     * Líneas de venta agrupadas por IdVenta. Replica el agrupado de detalle.php.
     *
     * @return array<int,list<array<string,mixed>>>
     */
    public function detallesPorVenta(int $idCliente, int $idUsuarioVendedor): array
    {
        $detallesPorVenta = [];
        foreach ($this->repository->detalles($idCliente, $idUsuarioVendedor) as $detalle) {
            $detallesPorVenta[(int) $detalle['IdVenta']][] = $detalle;
        }
        return $detallesPorVenta;
    }
}
