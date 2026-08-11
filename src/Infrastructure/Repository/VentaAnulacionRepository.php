<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Persistencia del flujo de anulación. No administra transacciones.
 */
final class VentaAnulacionRepository
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
    public function columnaInfo(string $tabla, string $columna): ?array
    {
        if (!$this->tablaExiste($tabla)) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tabla, $columna]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        return $info ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function bloquearVenta(int $idVenta): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT IdVenta, EstadoVenta, DistribuidorVendedorId FROM venta WHERE IdVenta = ? FOR UPDATE'
        );
        $stmt->execute([$idVenta]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        return $venta ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function detalles(int $idVenta): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                vd.Cantidad,
                vd.Producto_IdProducto,
                p.TipoProducto,
                p.Stock
            FROM venta_detalle vd
            INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
            WHERE vd.Venta_IdVenta = ?
        ");
        $stmt->execute([$idVenta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeOtraVentaActiva(int $idProducto, int $idVenta): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM venta_detalle vd_check
            INNER JOIN venta v_check
                ON v_check.IdVenta = vd_check.Venta_IdVenta
            WHERE vd_check.Producto_IdProducto = ?
              AND v_check.IdVenta <> ?
              AND COALESCE(v_check.EstadoVenta, 'Confirmada') <> 'Anulada'
        ");
        $stmt->execute([$idProducto, $idVenta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function restaurarVehiculo(int $idProducto): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE producto
            SET Stock = 1,
                Estado = 'Disponible',
                MostrarEnWeb = 0,
                DestacadoWeb = 0
            WHERE IdProducto = ?
        ");
        $stmt->execute([$idProducto]);

        $stmt = $this->pdo->prepare('UPDATE vehiculo SET FechaVenta = NULL WHERE IdProducto = ?');
        $stmt->execute([$idProducto]);
    }

    public function restaurarVehiculoDistribuidor(int $idProducto): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE producto
            SET Stock = 0,
                Estado = 'Sin stock',
                MostrarEnWeb = 0,
                DestacadoWeb = 0
            WHERE IdProducto = ?
        ");
        $stmt->execute([$idProducto]);

        $stmt = $this->pdo->prepare('UPDATE vehiculo SET FechaVenta = NULL WHERE IdProducto = ?');
        $stmt->execute([$idProducto]);
    }

    public function restaurarRepuesto(int $idProducto, int $cantidad): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE producto
            SET Stock = COALESCE(Stock, 0) + ?,
                Estado = 'Disponible'
            WHERE IdProducto = ?
        ");
        $stmt->execute([$cantidad, $idProducto]);
    }

    public function restaurarStockDistribuidorVehiculo(int $idProducto, int $idDistribuidor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE distribuidor_stock ds
            INNER JOIN vehiculo v ON v.IdVehiculo = ds.IdVehiculo
            SET ds.Cantidad = ds.Cantidad + 1
            WHERE v.IdProducto = ? AND ds.IdDistribuidor = ?
        ");
        $stmt->execute([$idProducto, $idDistribuidor]);
    }

    public function restaurarStockDistribuidorRepuesto(
        int $idProducto,
        int $idDistribuidor,
        int $cantidad
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE distribuidor_stock ds
            INNER JOIN repuesto r ON r.IdRepuesto = ds.IdRepuesto
            SET ds.Cantidad = ds.Cantidad + ?
            WHERE r.IdProducto = ? AND ds.IdDistribuidor = ?
        ");
        $stmt->execute([$cantidad, $idProducto, $idDistribuidor]);
    }

    public function anularRemito(int $idVenta): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE remito
            SET Estado = 'Anulado',
                IdVenta = NULL,
                FechaFactura = NULL,
                NumeroFactura = NULL,
                ReferenciaFactura = CONCAT(COALESCE(ReferenciaFactura, ''), ' [Anulado: venta #', ?, ' anulada]')
            WHERE IdVenta = ?
        ");
        $stmt->execute([$idVenta, $idVenta]);
    }

    public function anularVenta(
        int $idVenta,
        string $motivo,
        ?int $idUsuario,
        ?string $usuarioAnulacion
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE venta
            SET EstadoVenta = 'Anulada',
                MotivoAnulacion = ?,
                FechaAnulacion = NOW(),
                AnuladaPorUsuarioId = ?,
                UsuarioAnulacion = ?,
                MontoPagado = 0,
                SaldoPendiente = Total
            WHERE IdVenta = ?
        ");
        $stmt->execute([$motivo, $idUsuario, $usuarioAnulacion, $idVenta]);
    }

    public function anularComisiones(int $idVenta): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE distribuidor_comision
            SET Estado = 'Anulada', FechaActualizacion = NOW()
            WHERE IdVenta = ?
              AND Estado <> 'Anulada'
        ");
        $stmt->execute([$idVenta]);

        return $stmt->rowCount();
    }

    public function inactivarGastosComision(int $idVenta, string $motivo, string $estado): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE gasto
            SET
                Estado = ?,
                FechaAnulacion = NOW(),
                Observaciones = TRIM(CONCAT(
                    COALESCE(NULLIF(Observaciones, ''), ''),
                    CASE
                        WHEN COALESCE(NULLIF(Observaciones, ''), '') = '' THEN ''
                        ELSE '\n'
                    END,
                    '[INACTIVADO ',
                    DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'),
                    '] Venta anulada: ',
                    ?
                ))
            WHERE IdVenta = ?
              AND COALESCE(Estado, 'Activo') <> ?
              AND (
                  Categoria = 'Comisiones'
                  OR Concepto LIKE '%comision%'
                  OR Concepto LIKE '%comisión%'
                  OR Descripcion LIKE '%comision%'
                  OR Descripcion LIKE '%comisión%'
                  OR Observaciones LIKE '%comision%'
                  OR Observaciones LIKE '%comisión%'
              )
        ");
        $stmt->execute([$estado, $motivo, $idVenta, $estado]);

        return $stmt->rowCount();
    }

    public function anularGarantias(int $idVenta): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE garantia
            SET
                Estado = 'Anulada',
                Observaciones = LEFT(CONCAT(
                    COALESCE(Observaciones, ''),
                    CASE
                        WHEN COALESCE(NULLIF(Observaciones, ''), '') = '' THEN ''
                        ELSE '\n'
                    END,
                    '[',
                    DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'),
                    '] Garantía anulada automáticamente por anulación de venta #',
                    ?
                ), 500)
            WHERE IdVenta = ?
              AND Estado <> 'Anulada'
        ");
        $stmt->execute([$idVenta, $idVenta]);

        return $stmt->rowCount();
    }

    public function cancelarServices(int $idVenta, string $motivo): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE service_vehiculo
            SET
                Estado = 'Cancelado',
                Observaciones = LEFT(TRIM(CONCAT(
                    COALESCE(NULLIF(Observaciones, ''), ''),
                    CASE
                        WHEN COALESCE(NULLIF(Observaciones, ''), '') = '' THEN ''
                        ELSE '\n'
                    END,
                    '[CANCELADO ',
                    DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'),
                    '] Venta anulada: ',
                    ?
                )), 500)
            WHERE IdVenta = ?
              AND Estado IN ('Pendiente', 'Vencido')
        ");
        $stmt->execute([$motivo, $idVenta]);

        return $stmt->rowCount();
    }
}
