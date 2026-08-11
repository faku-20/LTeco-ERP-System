<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas read-only del buscador global (G2).
 */
final class BusquedaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array{
     *   vehiculos:list<array<string,mixed>>,
     *   clientes:list<array<string,mixed>>,
     *   ventas:list<array<string,mixed>>,
     *   repuestos:list<array<string,mixed>>
     * }
     */
    public function recientes(): array
    {
        return [
            'vehiculos' => $this->pdo->query("
                SELECT v.IdVehiculo, v.NumeroMotor, v.Modelo, v.Color, p.Estado
                FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
                ORDER BY v.IdVehiculo DESC LIMIT 4
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'clientes' => $this->pdo->query("
                SELECT IdCliente, NombreApellido, Telefono
                FROM cliente ORDER BY IdCliente DESC LIMIT 4
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'ventas' => $this->pdo->query("
                SELECT v.IdVenta, v.NumeroFactura, v.FechaVenta, v.Total, v.Moneda, c.NombreApellido
                FROM venta v LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
                WHERE COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
                ORDER BY v.IdVenta DESC LIMIT 4
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'repuestos' => $this->pdo->query("
                SELECT r.IdRepuesto, p.Nombre, p.Stock, p.Estado
                FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
                ORDER BY r.IdRepuesto DESC LIMIT 4
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function buscarVehiculos(string $qLike): array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.IdVehiculo, v.NumeroMotor, v.Modelo, v.Color,
                   p.Estado, p.PrecioVenta, p.Moneda
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE v.IdVehiculo LIKE ? OR v.NumeroMotor LIKE ? OR v.Modelo LIKE ? OR v.Color LIKE ?
            ORDER BY v.IdVehiculo DESC LIMIT 8
        ");
        $stmt->execute([$qLike, $qLike, $qLike, $qLike]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function buscarClientes(string $qLike, int $idUsuarioVendedor): array
    {
        $scopeSql = '';
        $params = [$qLike, $qLike, $qLike];
        if ($idUsuarioVendedor > 0) {
            $scopeSql = "
                AND EXISTS (
                    SELECT 1
                    FROM venta v_scope
                    WHERE v_scope.Cliente_IdCliente = cliente.IdCliente
                      AND v_scope.UsuarioVendedorId = ?
                )
            ";
            $params[] = $idUsuarioVendedor;
        }

        $stmt = $this->pdo->prepare("
            SELECT IdCliente, NombreApellido, Telefono, Correo
            FROM cliente
            WHERE (NombreApellido LIKE ? OR Telefono LIKE ? OR Correo LIKE ?)
            {$scopeSql}
            ORDER BY IdCliente DESC LIMIT 8
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function clienteAjenoCoincide(string $qLike, int $idUsuarioVendedor): bool
    {
        if ($idUsuarioVendedor <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM cliente c
            WHERE (
                c.NombreApellido LIKE ?
                OR c.Telefono LIKE ?
                OR c.Correo LIKE ?
                OR c.Cedula LIKE ?
                OR c.RUT LIKE ?
            )
              AND NOT EXISTS (
                  SELECT 1
                  FROM venta v_scope
                  WHERE v_scope.Cliente_IdCliente = c.IdCliente
                    AND v_scope.UsuarioVendedorId = ?
              )
            LIMIT 1
        ");
        $stmt->execute([$qLike, $qLike, $qLike, $qLike, $qLike, $idUsuarioVendedor]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function ventasCliente(int $idCliente, int $idUsuarioVendedor): array
    {
        $scopeSql = '';
        $params = [$idCliente];
        if ($idUsuarioVendedor > 0) {
            $scopeSql = ' AND ve.UsuarioVendedorId = ?';
            $params[] = $idUsuarioVendedor;
        }

        $stmt = $this->pdo->prepare("
            SELECT ve.IdVenta, ve.NumeroFactura, ve.FechaVenta, ve.EstadoVenta,
                   ve.Total, ve.Moneda, ve.MetodoPago
            FROM venta ve
            WHERE ve.Cliente_IdCliente = ?
              {$scopeSql}
              AND (ve.EstadoVenta IS NULL OR ve.EstadoVenta <> 'Anulada')
            ORDER BY ve.IdVenta DESC LIMIT 5
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function motosCliente(int $idCliente, int $idUsuarioVendedor): array
    {
        $scopeSql = '';
        $params = [$idCliente];
        if ($idUsuarioVendedor > 0) {
            $scopeSql = ' AND ve.UsuarioVendedorId = ?';
            $params[] = $idUsuarioVendedor;
        }

        $stmt = $this->pdo->prepare("
            SELECT vh.IdVehiculo, vh.NumeroMotor, vh.Modelo, vh.Color,
                   p.Estado, ve.IdVenta, ve.FechaVenta
            FROM vehiculo vh
            INNER JOIN producto p ON p.IdProducto = vh.IdProducto
            INNER JOIN venta_detalle vd ON vd.Producto_IdProducto = p.IdProducto
            INNER JOIN venta ve ON ve.IdVenta = vd.Venta_IdVenta
            WHERE ve.Cliente_IdCliente = ?
              {$scopeSql}
              AND (ve.EstadoVenta IS NULL OR ve.EstadoVenta <> 'Anulada')
            ORDER BY ve.IdVenta DESC LIMIT 5
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function buscarVentas(string $qLike, int $idUsuarioVendedor): array
    {
        $scopeSql = '';
        $params = [$qLike, $qLike];
        if ($idUsuarioVendedor > 0) {
            $scopeSql = ' AND v.UsuarioVendedorId = ?';
            $params[] = $idUsuarioVendedor;
        }

        $stmt = $this->pdo->prepare("
            SELECT v.IdVenta, v.NumeroFactura, v.FechaVenta, v.EstadoVenta,
                   v.Total, v.Moneda, c.NombreApellido
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            WHERE (CAST(v.IdVenta AS CHAR) LIKE ? OR v.NumeroFactura LIKE ?)
              {$scopeSql}
              AND COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
            ORDER BY v.IdVenta DESC LIMIT 8
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function buscarRepuestos(string $qLike): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.IdRepuesto, p.IdProducto, p.Nombre, p.Estado, p.Stock, p.PrecioVenta, p.Moneda
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.Nombre LIKE ? OR CAST(r.IdRepuesto AS CHAR) LIKE ?
            ORDER BY r.IdRepuesto DESC LIMIT 8
        ");
        $stmt->execute([$qLike, $qLike]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{Realizados:mixed,Proximo:mixed} */
    public function resumenServicesVehiculo(string $idVehiculo): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(CASE WHEN Estado = 'Realizado' THEN 1 ELSE 0 END) AS Realizados,
                MIN(CASE WHEN Estado IN ('Pendiente','Vencido') THEN FechaProgramada END) AS Proximo
            FROM service_vehiculo WHERE IdVehiculo = ?
        ");
        $stmt->execute([$idVehiculo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['Realizados' => 0, 'Proximo' => null];
    }
}
