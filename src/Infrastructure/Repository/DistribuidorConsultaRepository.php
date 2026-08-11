<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura usadas por los listados de Distribuidores C3.
 */
final class DistribuidorConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function tablaExiste(string $tabla): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tabla]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function distribuidor(int $idDistribuidor): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1');
        $stmt->execute([$idDistribuidor]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function stockTotal(int $idDistribuidor): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(Cantidad), 0) FROM distribuidor_stock WHERE IdDistribuidor = ?');
        $stmt->execute([$idDistribuidor]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pedidosPorEstado(int $idDistribuidor): array
    {
        $stmt = $this->pdo->prepare('SELECT Estado, COUNT(*) AS Cant FROM distribuidor_pedido WHERE IdDistribuidor = ? GROUP BY Estado');
        $stmt->execute([$idDistribuidor]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function postventasAbiertas(int $idDistribuidor): int
    {
        if (!$this->tablaExiste('service_vehiculo')) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM service_vehiculo sv
            INNER JOIN venta v ON v.IdVenta = sv.IdVenta
            WHERE v.DistribuidorVendedorId = ?
              AND sv.Estado IN ('Pendiente', 'Vencido')
        ");
        $stmt->execute([$idDistribuidor]);

        return (int) $stmt->fetchColumn();
    }

    public function remitosPendientes(int $idDistribuidor): int
    {
        if (!$this->tablaExiste('remito')) {
            return 0;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM remito WHERE IdDistribuidor = ? AND Estado = 'Pendiente'");
        $stmt->execute([$idDistribuidor]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function stockAsignado(int $idDistribuidor): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ds.*,
                dr.Nombre AS DistribuidorNombre,
                pv.Nombre AS VehiculoNombre,
                pr.Nombre AS RepuestoNombre,
                pv.IdProducto AS VehiculoProductoId,
                pr.IdProducto AS RepuestoProductoId,
                v.NumeroMotor AS VehiculoNumeroMotor
            FROM distribuidor_stock ds
            INNER JOIN distribuidor dr ON dr.IdDistribuidor = ds.IdDistribuidor
            LEFT JOIN vehiculo v ON v.IdVehiculo = ds.IdVehiculo
            LEFT JOIN producto pv ON pv.IdProducto = v.IdProducto
            LEFT JOIN repuesto r ON r.IdRepuesto = ds.IdRepuesto
            LEFT JOIN producto pr ON pr.IdProducto = r.IdProducto
            WHERE ds.IdDistribuidor = ?
              AND ds.Cantidad > 0
            ORDER BY
              ds.TipoItem ASC,
              COALESCE(pv.Nombre, pr.Nombre, '') ASC,
              ds.FechaActualizacion DESC
        ");
        $stmt->execute([$idDistribuidor]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDistribuidores(bool $conStock): array
    {
        if (!$conStock) {
            return $this->pdo->query("
                SELECT d.*, 0 AS StockAsignado
                FROM distribuidor d
                ORDER BY d.Activo DESC, d.Nombre ASC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return $this->pdo->query("
            SELECT d.*,
                   COALESCE(SUM(ds.Cantidad), 0) AS StockAsignado
            FROM distribuidor d
            LEFT JOIN distribuidor_stock ds ON ds.IdDistribuidor = d.IdDistribuidor
            GROUP BY d.IdDistribuidor
            ORDER BY d.Activo DESC, d.Nombre ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pedidos(?int $idDistribuidor): array
    {
        $where = $idDistribuidor !== null ? ' WHERE dp.IdDistribuidor = ?' : '';
        $params = $idDistribuidor !== null ? [$idDistribuidor] : [];

        $stmt = $this->pdo->prepare("
            SELECT
                dp.*,
                dr.Nombre AS DistribuidorNombre,
                pv.Nombre AS VehiculoNombre,
                pr.Nombre AS RepuestoNombre
            FROM distribuidor_pedido dp
            INNER JOIN distribuidor dr ON dr.IdDistribuidor = dp.IdDistribuidor
            LEFT JOIN vehiculo v ON v.IdVehiculo = dp.IdVehiculo
            LEFT JOIN producto pv ON pv.IdProducto = v.IdProducto
            LEFT JOIN repuesto r ON r.IdRepuesto = dp.IdRepuesto
            LEFT JOIN producto pr ON pr.IdProducto = r.IdProducto
            {$where}
            ORDER BY dp.FechaPedido DESC, dp.IdPedido DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function ventas(int $idDistribuidor): array
    {
        if ($idDistribuidor > 0) {
            $where = 'WHERE v.DistribuidorVendedorId = ?';
            $params = [$idDistribuidor];
        } else {
            $where = 'WHERE v.DistribuidorVendedorId IS NOT NULL';
            $params = [];
        }

        $stmt = $this->pdo->prepare("
            SELECT v.IdVenta, v.FechaVenta, v.Total, v.GananciaEstimada, v.Moneda, v.MetodoPago, v.EstadoVenta,
                   c.NombreApellido, d.Nombre AS DistribuidorNombre, u.Usuario AS UsuarioVendedor
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            LEFT JOIN distribuidor d ON d.IdDistribuidor = v.DistribuidorVendedorId
            LEFT JOIN usuario u ON u.IdUsuario = v.UsuarioVendedorId
            {$where}
            ORDER BY v.IdVenta DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function buscarStock(int $idDistribuidor, string $like): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ds.TipoItem, ds.IdVehiculo, ds.IdRepuesto, ds.Cantidad, ds.PrecioVenta,
                   COALESCE(pv.Nombre, pr.Nombre, '') AS NombreItem,
                   v.NumeroMotor
            FROM distribuidor_stock ds
            LEFT JOIN vehiculo v   ON v.IdVehiculo   = ds.IdVehiculo
            LEFT JOIN producto pv  ON pv.IdProducto  = v.IdProducto
            LEFT JOIN repuesto r   ON r.IdRepuesto   = ds.IdRepuesto
            LEFT JOIN producto pr  ON pr.IdProducto  = r.IdProducto
            WHERE ds.IdDistribuidor = ?
              AND ds.Cantidad > 0
              AND (COALESCE(pv.Nombre, pr.Nombre, '') LIKE ? OR v.NumeroMotor LIKE ?)
            ORDER BY ds.TipoItem, NombreItem
            LIMIT 10
        ");
        $stmt->execute([$idDistribuidor, $like, $like]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function buscarVentas(int $idDistribuidor, string $like): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ve.IdVenta, ve.FechaVenta, ve.Total, ve.Moneda, ve.EstadoVenta, ve.MetodoPago,
                   c.NombreApellido
            FROM venta ve
            LEFT JOIN cliente c ON c.IdCliente = ve.Cliente_IdCliente
            WHERE ve.DistribuidorVendedorId = ?
              AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
              AND (CAST(ve.IdVenta AS CHAR) LIKE ? OR ve.NumeroFactura LIKE ? OR c.NombreApellido LIKE ?)
            ORDER BY ve.IdVenta DESC
            LIMIT 10
        ");
        $stmt->execute([$idDistribuidor, $like, $like, $like]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function buscarClientes(int $idDistribuidor, string $like): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT c.IdCliente, c.NombreApellido, c.Telefono, c.Correo
            FROM cliente c
            INNER JOIN venta ve ON ve.Cliente_IdCliente = c.IdCliente
            WHERE ve.DistribuidorVendedorId = ?
              AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
              AND (c.NombreApellido LIKE ? OR c.Telefono LIKE ? OR c.Correo LIKE ?)
            ORDER BY c.NombreApellido
            LIMIT 10
        ");
        $stmt->execute([$idDistribuidor, $like, $like, $like]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function reportes(string $estado): array
    {
        $where = $estado !== '' ? 'WHERE r.EstadoInterno = ?' : '';
        $params = $estado !== '' ? [$estado] : [];

        $stmt = $this->pdo->prepare("
            SELECT
                r.IdReporte,
                r.IdDistribuidor,
                r.IdUsuario,
                LEFT(r.Mensaje, 120) AS MensajeResumen,
                r.ImagenRuta,
                r.EstadoInterno,
                r.FechaCreacion,
                d.Nombre AS DistribuidorNombre,
                u.NombreCompleto AS UsuarioNombre
            FROM distribuidor_reporte_problema r
            INNER JOIN distribuidor d ON d.IdDistribuidor = r.IdDistribuidor
            INNER JOIN usuario u ON u.IdUsuario = r.IdUsuario
            {$where}
            ORDER BY
                CASE r.EstadoInterno WHEN 'Nuevo' THEN 0 WHEN 'Revisado' THEN 1 ELSE 2 END ASC,
                r.FechaCreacion DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
