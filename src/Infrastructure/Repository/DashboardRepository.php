<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Datasets read-only consumidos por el dashboard actual (G3).
 */
final class DashboardRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /** @return list<array<string,mixed>> */
    public function ventas(): array
    {
        return $this->pdo->query("
            SELECT
                IdVenta, FechaVenta, Total, GananciaEstimada, Moneda,
                EstadoVenta, MetodoPago, MontoPagado, SaldoPendiente,
                TipoCambioAplicado
            FROM venta
            ORDER BY FechaVenta DESC, IdVenta DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function productos(): array
    {
        return $this->pdo->query("
            SELECT
                p.IdProducto, p.Nombre, p.TipoProducto, p.Stock, p.Estado,
                p.MostrarEnWeb, p.DestacadoWeb, p.Moneda, p.GastoTotal,
                p.PrecioVenta, v.IdVehiculo,
                COALESCE(v.TipoCambioImportacion, r.TipoCambioImportacion) AS TipoCambioImportacion,
                (
                    SELECT COUNT(*)
                    FROM vehiculo_imagen vi
                    WHERE vi.IdVehiculo = v.IdVehiculo
                      AND vi.EsPrincipal = 1
                ) AS TieneImagenPrincipal
            FROM producto p
            LEFT JOIN vehiculo v ON v.IdProducto = p.IdProducto
            LEFT JOIN repuesto r ON r.IdProducto = p.IdProducto
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function deudas(): array
    {
        return $this->pdo->query("
            SELECT
                c.IdCliente, c.NombreApellido, v.SaldoPendiente, v.Moneda,
                v.TipoCambioAplicado
            FROM venta v
            INNER JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            WHERE (v.EstadoVenta IS NULL OR v.EstadoVenta <> 'Anulada')
              AND COALESCE(v.SaldoPendiente, 0) > 0
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function comprasClientes(): array
    {
        return $this->pdo->query("
            SELECT
                c.IdCliente, c.NombreApellido, c.Telefono,
                v.Total, v.Moneda, v.TipoCambioAplicado
            FROM cliente c
            INNER JOIN venta v ON v.Cliente_IdCliente = c.IdCliente
            WHERE (v.EstadoVenta IS NULL OR v.EstadoVenta <> 'Anulada')
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
