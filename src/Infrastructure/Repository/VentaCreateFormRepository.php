<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Lecturas necesarias para mostrar el formulario de alta de venta.
 */
final class VentaCreateFormRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function distribuidoresActivos(): array
    {
        $stmt = $this->pdo->query(
            'SELECT IdDistribuidor, Nombre, ComisionPct
             FROM distribuidor
             WHERE Activo = 1
             ORDER BY Nombre ASC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function clientes(bool $soloDelVendedor, int $idUsuarioVendedor): array
    {
        $campos = '
            IdCliente,
            NombreApellido,
            Telefono,
            Correo,
            TipoFiscal,
            Cedula,
            Direccion,
            RUT
        ';

        if (!$soloDelVendedor) {
            $stmt = $this->pdo->query("
                SELECT {$campos}
                FROM cliente
                ORDER BY NombreApellido ASC
            ");

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                c.IdCliente,
                c.NombreApellido,
                c.Telefono,
                c.Correo,
                c.TipoFiscal,
                c.Cedula,
                c.Direccion,
                c.RUT
            FROM cliente c
            WHERE EXISTS (
                SELECT 1
                FROM venta v
                WHERE v.Cliente_IdCliente = c.IdCliente
                  AND v.UsuarioVendedorId = ?
            )
            ORDER BY c.NombreApellido ASC
        ");
        $stmt->execute([$idUsuarioVendedor]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function vehiculosDisponibles(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                v.IdVehiculo,
                v.Modelo,
                v.NumeroMotor,
                v.ClienteReservaId,
                p.IdProducto,
                p.PrecioVenta,
                p.PrecioDistribuidor,
                p.GastoTotal,
                p.Moneda,
                p.Estado,
                v.TipoCambioImportacion
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE p.Estado IN ('Disponible','Reservado')
              AND v.FechaVenta IS NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM venta_detalle vd_check
                  INNER JOIN venta ve_check
                      ON ve_check.IdVenta = vd_check.Venta_IdVenta
                  WHERE vd_check.Producto_IdProducto = p.IdProducto
                    AND ve_check.EstadoVenta <> 'Anulada'
              )
            ORDER BY v.IdVehiculo ASC
        ");

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function repuestosConStock(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                r.IdRepuesto,
                p.IdProducto,
                p.Nombre,
                p.PrecioVenta,
                p.PrecioDistribuidor,
                p.GastoTotal,
                p.Moneda,
                p.Stock,
                r.TipoCambioImportacion
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.Stock > 0
            ORDER BY p.Nombre ASC
        ");

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
