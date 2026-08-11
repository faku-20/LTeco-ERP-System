<?php

declare(strict_types=1);

namespace Lteco\Application\Inventario;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class InventarioReconciliadorService
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /** @return array<string,array{severity:string,count:int,ids:list<string>}> */
    public function ejecutar(): array
    {
        return [
            'vehiculos_vendidos_sin_venta' => $this->check(
                'ERROR',
                "SELECT vh.IdVehiculo id
                 FROM vehiculo vh
                 JOIN producto p ON p.IdProducto=vh.IdProducto
                 WHERE p.TipoProducto='Moto'
                   AND p.Estado='Vendido'
                   AND NOT EXISTS (
                       SELECT 1 FROM venta_detalle vd
                       JOIN venta v ON v.IdVenta=vd.Venta_IdVenta
                       WHERE vd.Producto_IdProducto=p.IdProducto
                         AND COALESCE(v.EstadoVenta,'Confirmada')<>'Anulada'
                   )"
            ),
            'ventas_activas_vehiculo_no_vendido' => $this->check(
                'ERROR',
                "SELECT DISTINCT vd.Producto_IdProducto id
                 FROM venta_detalle vd
                 JOIN venta v ON v.IdVenta=vd.Venta_IdVenta
                 JOIN producto p ON p.IdProducto=vd.Producto_IdProducto
                 WHERE p.TipoProducto='Moto'
                   AND COALESCE(v.EstadoVenta,'Confirmada')<>'Anulada'
                   AND p.Estado<>'Vendido'"
            ),
            'reservas_activas_incompatibles' => $this->check(
                'WARN',
                "SELECT vh.IdVehiculo id
                 FROM vehiculo vh
                 JOIN producto p ON p.IdProducto=vh.IdProducto
                 WHERE p.TipoProducto='Moto'
                   AND p.Estado='Reservado'
                   AND vh.ClienteReservaId IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM storefront_reservation_item sri
                       JOIN storefront_reservation sr ON sr.ReservationId=sri.ReservationId
                       WHERE sri.IdVehiculo=vh.IdVehiculo AND sr.Estado='active'
                   )"
            ),
            'multiples_ventas_activas_unidad' => $this->check(
                'ERROR',
                "SELECT CAST(vd.Producto_IdProducto AS CHAR) id
                 FROM venta_detalle vd
                 JOIN venta v ON v.IdVenta=vd.Venta_IdVenta
                 JOIN producto p ON p.IdProducto=vd.Producto_IdProducto
                 WHERE p.TipoProducto='Moto'
                   AND COALESCE(v.EstadoVenta,'Confirmada')<>'Anulada'
                 GROUP BY vd.Producto_IdProducto
                 HAVING COUNT(*)>1"
            ),
            'producto_stock_negativo' => $this->check('ERROR', 'SELECT IdProducto id FROM producto WHERE Stock < 0'),
            'distribuidor_stock_negativo' => $this->check('ERROR', 'SELECT IdStock id FROM distribuidor_stock WHERE Cantidad < 0'),
            'distribuidor_stock_duplicado_vehiculo' => $this->check(
                'ERROR',
                "SELECT CONCAT(IdDistribuidor,':',IdVehiculo) id
                 FROM distribuidor_stock
                 WHERE TipoItem='Vehiculo'
                 GROUP BY IdDistribuidor,IdVehiculo
                 HAVING COUNT(*)>1"
            ),
            'distribuidor_stock_duplicado_repuesto' => $this->check(
                'ERROR',
                "SELECT CONCAT(IdDistribuidor,':',IdRepuesto) id
                 FROM distribuidor_stock
                 WHERE TipoItem='Repuesto'
                 GROUP BY IdDistribuidor,IdRepuesto
                 HAVING COUNT(*)>1"
            ),
            'repuesto_cajas_sobre_stock' => $this->checkRepuestoCajasSobreStock(),
            'ventas_activas_sin_detalle' => $this->check(
                'ERROR',
                "SELECT v.IdVenta id
                 FROM venta v
                 WHERE COALESCE(v.EstadoVenta,'Confirmada')<>'Anulada'
                   AND NOT EXISTS (SELECT 1 FROM venta_detalle vd WHERE vd.Venta_IdVenta=v.IdVenta)"
            ),
        ];
    }

    /** @return array{severity:string,count:int,ids:list<string>} */
    private function check(string $severity, string $sql): array
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM (' . $sql . ') inventario_check')->fetchColumn();
        $stmt = $this->pdo->query($sql . ' LIMIT 50');
        $ids = array_map(static fn (array $row): string => (string) $row['id'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return ['severity' => $severity, 'count' => $count, 'ids' => $ids];
    }

    /** @return array{severity:string,count:int,ids:list<string>} */
    private function checkRepuestoCajasSobreStock(): array
    {
        if (!$this->tableExists('repuesto_caja_item')) {
            return ['severity' => 'ERROR', 'count' => 0, 'ids' => []];
        }

        return $this->check(
            'ERROR',
            "SELECT CAST(r.IdRepuesto AS CHAR) id
             FROM repuesto r
             JOIN producto p ON p.IdProducto=r.IdProducto
             JOIN repuesto_caja_item i ON i.IdRepuesto=r.IdRepuesto
             GROUP BY r.IdRepuesto, p.Stock
             HAVING SUM(i.Cantidad)>p.Stock"
        );
    }

    private function tableExists(string $table): bool
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);
                return (int) $stmt->fetchColumn() > 0;
            }

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ");
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
