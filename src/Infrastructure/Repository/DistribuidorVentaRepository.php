<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos propio de la venta realizada desde el portal distribuidor.
 *
 * Conserva las queries legacy de nueva_venta.php y no administra transacciones.
 */
final class DistribuidorVentaRepository
{
    private PDO $pdo;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
    }

    /** @return list<array<string,mixed>> */
    public function stockDisponible(int $idDistribuidor): array
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
            WHERE ds.IdDistribuidor = ? AND ds.Cantidad > 0
            ORDER BY ds.TipoItem ASC, RepuestoNombre ASC, VehiculoNombre ASC
        ");
        $stmt->execute([$idDistribuidor]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function bloquearStock(int $idStock, int $idDistribuidor): ?array
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
            WHERE ds.IdStock = ? AND ds.IdDistribuidor = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$idStock, $idDistribuidor]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function buscarIdProductoVehiculo(?string $idVehiculo): ?int
    {
        $stmt = $this->pdo->prepare('SELECT IdProducto FROM vehiculo WHERE IdVehiculo = ? LIMIT 1');
        $stmt->execute([$idVehiculo]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : (int) $valor;
    }

    public function buscarIdProductoRepuesto(?int $idRepuesto): ?int
    {
        $stmt = $this->pdo->prepare('SELECT IdProducto FROM repuesto WHERE IdRepuesto = ? LIMIT 1');
        $stmt->execute([$idRepuesto]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : (int) $valor;
    }

    public function obtenerComisionDistribuidorPct(int $idDistribuidor): float
    {
        $stmt = $this->pdo->prepare('SELECT ComisionPct FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1');
        $stmt->execute([$idDistribuidor]);

        return max(0.0, (float) $stmt->fetchColumn());
    }

    /**
     * @param array<string,float> $calculo
     */
    public function crearVenta(
        int $clienteId,
        string $metodoPago,
        int $idDistribuidor,
        int $idUsuarioComisionDistribuidor,
        ?string $observaciones,
        array $calculo
    ): int {
        $this->pdo->prepare("
            INSERT INTO venta (
                Cliente_IdCliente,
                FechaVenta,
                MetodoPago,
                TipoCliente,
                Total,
                GananciaEstimada,
                Moneda,
                Observaciones,
                EstadoVenta,
                DistribuidorVendedorId,
                UsuarioVendedorId,
                MontoIVA,
                TotalSinIVA,
                ComisionDistribuidor,
                ComisionVendedor
            )
            VALUES (?, NOW(), ?, 'Final', ?, ?, 'UYU', ?, 'Confirmada', ?, ?, ?, ?, ?, ?)
        ")->execute([
            $clienteId,
            $metodoPago,
            $calculo['subtotal'],
            $calculo['ganancia'],
            $observaciones,
            $idDistribuidor,
            $idUsuarioComisionDistribuidor,
            $calculo['montoIVA'],
            $calculo['totalSinIVA'],
            $calculo['comisionDistribuidor'],
            $calculo['comisionVendedor'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Preserva el INSERT legacy sin columna Moneda y con costo = precioMinimo.
     */
    public function crearDetalle(
        int $idVenta,
        int $idProducto,
        int $cantidad,
        float $precioVenta,
        float $precioMinimo,
        float $subtotal,
        float $ganancia
    ): void {
        $this->pdo->prepare("
            INSERT INTO venta_detalle (
                Venta_IdVenta,
                Producto_IdProducto,
                Cantidad,
                PrecioUnitario,
                CostoUnitario,
                Subtotal,
                GananciaLinea
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $idVenta,
            $idProducto,
            $cantidad,
            $precioVenta,
            $precioMinimo,
            $subtotal,
            $ganancia,
        ]);
    }

    public function descontarStockDistribuidor(int $idStock, int $cantidad): bool
    {
        $stmt = $this->pdo->prepare('UPDATE distribuidor_stock SET Cantidad = Cantidad - ? WHERE IdStock = ? AND Cantidad >= ?');
        $stmt->execute([$cantidad, $idStock, $cantidad]);

        return $stmt->rowCount() === 1;
    }

    public function descontarStockGlobalRepuesto(int $idProducto, int $cantidad): bool
    {
        $stmt = $this->pdo->prepare("UPDATE producto SET Stock = Stock - ?, Estado = IF(Stock - ? <= 0, 'Sin stock', Estado) WHERE IdProducto = ? AND Stock >= ?");
        $stmt->execute([$cantidad, $cantidad, $idProducto, $cantidad]);

        return $stmt->rowCount() === 1;
    }

    public function registrarComisionDistribuidor(
        int $idDistribuidor,
        int $idVenta,
        float $base,
        float $porcentaje,
        float $monto
    ): void {
        $this->pdo->prepare("
            INSERT INTO distribuidor_comision
                (IdDistribuidor, IdVenta, BaseComision, Porcentaje, Monto, Estado, FechaGenerada)
            VALUES (?, ?, ?, ?, ?, 'Pendiente', NOW())
            ON DUPLICATE KEY UPDATE
                BaseComision = VALUES(BaseComision),
                Porcentaje = VALUES(Porcentaje),
                Monto = VALUES(Monto),
                FechaActualizacion = NOW()
        ")->execute([$idDistribuidor, $idVenta, $base, $porcentaje, $monto]);
    }

    public function nombreDistribuidor(int $idDistribuidor): string
    {
        $stmt = $this->pdo->prepare("SELECT Nombre FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1");
        $stmt->execute([$idDistribuidor]);
        return (string) ($stmt->fetchColumn() ?: 'Distribuidor #' . $idDistribuidor);
    }

    public function crearGastoComision(
        int $idVenta,
        string $concepto,
        float $monto,
        string $observaciones
    ): void {
        $this->pdo->prepare("
            INSERT INTO gasto
                (IdVenta, FechaGasto, Concepto, Categoria, MetodoPago, Moneda, Monto, Observaciones, Estado, Origen)
            VALUES (?, CURDATE(), ?, 'Comisiones', 'Otro', 'UYU', ?, ?, 'Activo', 'Venta')
        ")->execute([$idVenta, $concepto, $monto, $observaciones]);
    }

    public function facturarRemitoVehiculo(
        int $idDistribuidor,
        string $idVehiculo,
        int $idVenta,
        ?string $numeroFactura
    ): void {
        $this->pdo->prepare("
            UPDATE remito
            SET Estado = 'Facturado',
                IdVenta = ?,
                FechaFactura = NOW(),
                NumeroFactura = ?,
                ReferenciaFactura = ?
            WHERE IdDistribuidor = ?
              AND TipoItem = 'Vehiculo'
              AND IdVehiculo = ?
              AND Estado = 'Pendiente'
            ORDER BY FechaEmision ASC
            LIMIT 1
        ")->execute([
            $idVenta,
            $numeroFactura,
            'Venta distribuidor #' . $idVenta,
            $idDistribuidor,
            $idVehiculo,
        ]);
    }

    public function facturarRemitoRepuesto(
        int $idDistribuidor,
        int $idRepuesto,
        int $idVenta,
        ?string $numeroFactura
    ): void {
        $this->pdo->prepare("
            UPDATE remito
            SET Estado = 'Facturado',
                IdVenta = ?,
                FechaFactura = NOW(),
                NumeroFactura = ?,
                ReferenciaFactura = ?
            WHERE IdDistribuidor = ?
              AND TipoItem = 'Repuesto'
              AND IdRepuesto = ?
              AND Estado = 'Pendiente'
            ORDER BY FechaEmision ASC
            LIMIT 1
        ")->execute([
            $idVenta,
            $numeroFactura,
            'Venta distribuidor #' . $idVenta,
            $idDistribuidor,
            $idRepuesto,
        ]);
    }
}
