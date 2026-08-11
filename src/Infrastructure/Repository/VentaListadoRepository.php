<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Application\Venta\VentaListadoFiltros;
use Lteco\Infrastructure\Db\Connection;
use PDO;

final class VentaListadoRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listarPantalla(VentaListadoFiltros $filtros, ?int $idUsuarioVendedor): array
    {
        $condicion = $filtros->condicion($idUsuarioVendedor);
        $sql = "
            SELECT
                v.IdVenta,
                v.FechaVenta,
                v.Total,
                v.GananciaEstimada,
                v.Moneda,
                v.TipoCliente,
                v.Observaciones,
                v.MetodoPago,
                v.TipoTarjeta,
                v.MarcaTarjeta,
                v.CuotasTarjeta,
                v.EstadoVenta,
                v.MontoPagado,
                v.SaldoPendiente,
                v.TipoCambioAplicado,
                v.TotalSinIVA,
                c.NombreApellido,
                c.TipoFiscal,
                c.Cedula,
                c.RUT,
                dvend.Nombre AS DistribuidorVendedorNombre,
                uvend.Usuario AS UsuarioVendedorNombre
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            LEFT JOIN distribuidor dvend ON dvend.IdDistribuidor = v.DistribuidorVendedorId
            LEFT JOIN usuario uvend ON uvend.IdUsuario = v.UsuarioVendedorId
            {$condicion['sql']}
            ORDER BY v.IdVenta DESC
        ";

        return $this->consultar($sql, $condicion['params']);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listarExportacion(VentaListadoFiltros $filtros): array
    {
        $condicion = $filtros->condicion();
        $sql = "
            SELECT
                v.IdVenta,
                v.FechaVenta,
                c.NombreApellido,
                c.Telefono,
                c.Correo,
                c.TipoFiscal,
                c.Cedula,
                c.Direccion,
                c.RUT,
                v.TipoCliente,
                v.Moneda,
                v.Total,
                v.MontoPagado,
                v.SaldoPendiente,
                v.GananciaEstimada,
                v.MetodoPago,
                v.TipoTarjeta,
                v.MarcaTarjeta,
                v.CuotasTarjeta,
                v.EstadoVenta,
                v.Observaciones,
                v.TipoCambioAplicado
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            {$condicion['sql']}
            ORDER BY v.IdVenta DESC
        ";

        return $this->consultar($sql, $condicion['params']);
    }

    /**
     * @param array<string,int|string> $params
     * @return array<int,array<string,mixed>>
     */
    private function consultar(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
