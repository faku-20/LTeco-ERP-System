<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura compartidas por las vistas de una venta.
 */
final class VentaReadRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function ventaConCliente(int $idVenta): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                v.*,
                c.NombreApellido,
                c.Telefono,
                c.Correo,
                c.TipoFiscal,
                c.Cedula,
                c.Direccion,
                c.RUT
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            WHERE v.IdVenta = ?
        ");
        $stmt->execute([$idVenta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Datos mínimos usados por el reenvío del template de WhatsApp.
     *
     * @return array<string,mixed>|null
     */
    public function ventaParaWhatsapp(int $idVenta): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                v.IdVenta,
                v.Moneda,
                v.NumeroFactura,
                v.Total,
                v.EstadoVenta,
                c.NombreApellido,
                c.Telefono
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            WHERE v.IdVenta = ?
            LIMIT 1
        ");
        $stmt->execute([$idVenta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Conserva la selección legacy del primer detalle de tipo Moto.
     *
     * @return array<string,mixed>|null
     */
    public function primerVehiculoVenta(int $idVenta): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT vh.Modelo, vh.NumeroMotor
            FROM venta_detalle vd
            INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
            LEFT JOIN vehiculo vh ON vh.IdProducto = p.IdProducto
            WHERE vd.Venta_IdVenta = ?
              AND p.TipoProducto = 'Moto'
            ORDER BY vd.IdVentaDetalle ASC
            LIMIT 1
        ");
        $stmt->execute([$idVenta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function detalles(int $idVenta): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                vd.*,
                p.IdProducto,
                p.Nombre,
                p.TipoProducto,
                p.Estado,
                vh.IdVehiculo,
                vh.NumeroMotor,
                vh.Modelo,
                vh.Color
            FROM venta_detalle vd
            INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
            LEFT JOIN vehiculo vh ON vh.IdProducto = p.IdProducto
            WHERE vd.Venta_IdVenta = ?
            ORDER BY vd.IdVentaDetalle ASC
        ");
        $stmt->execute([$idVenta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function garantiaFin(string $idVehiculo, int $idVenta): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT FechaFin FROM garantia WHERE IdVehiculo = ? AND IdVenta = ? LIMIT 1'
        );
        $stmt->execute([$idVehiculo, $idVenta]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    public function fechasService(string $idVehiculo, int $idVenta): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT FechaProgramada
             FROM service_vehiculo
             WHERE IdVehiculo = ? AND IdVenta = ?
             ORDER BY NumeroService ASC'
        );
        $stmt->execute([$idVehiculo, $idVenta]);

        return array_map(
            static fn (array $row): string => (string) $row['FechaProgramada'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }
}
