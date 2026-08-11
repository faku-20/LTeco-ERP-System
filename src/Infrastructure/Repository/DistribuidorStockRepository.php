<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos del stock asignado a distribuidores (tabla distribuidor_stock)
 * y al descuento del stock interno (tabla producto).
 *
 * Origen: el helper distribuidorUpsertStock() de
 * lteco-panel/distribuidores/_common.php y el núcleo de escritura de
 * lteco-panel/distribuidores/asignar_stock.php. Movido aquí SIN cambios de
 * comportamiento (mismas sentencias y parámetros).
 *
 * TRANSACTION-AGNOSTIC: reutiliza el $pdo de Connection y NUNCA abre/cierra
 * transacción; el dueño de la transacción sigue siendo el caller.
 */
final class DistribuidorStockRepository
{
    private PDO $pdo;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
    }

    /** @return list<array<string,mixed>> */
    public function distribuidoresActivos(): array
    {
        $stmt = $this->pdo->query('SELECT IdDistribuidor, Nombre FROM distribuidor WHERE Activo = 1 ORDER BY Nombre ASC');
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return list<array<string,mixed>> */
    public function vehiculosDisponibles(): array
    {
        $stmt = $this->pdo->query("
            SELECT v.IdVehiculo, p.Nombre, p.Stock, p.PrecioVenta
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE p.Stock > 0 AND p.Estado = 'Disponible'
            ORDER BY p.Nombre ASC, v.IdVehiculo ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return list<array<string,mixed>> */
    public function repuestosDisponibles(): array
    {
        $stmt = $this->pdo->query("
            SELECT r.IdRepuesto, p.Nombre, p.Stock, p.PrecioVenta, p.PrecioDistribuidor
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.Stock > 0 AND p.Estado = 'Disponible'
            ORDER BY p.Nombre ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<string,mixed>|null */
    public function itemDisponible(string $tipoItem, ?string $idVehiculo, ?int $idRepuesto): ?array
    {
        if ($tipoItem === 'Vehiculo') {
            $stmt = $this->pdo->prepare("
                SELECT v.IdVehiculo, p.IdProducto, p.Nombre, p.Stock, p.PrecioVenta
                FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
                WHERE v.IdVehiculo = ? AND p.Stock > 0 AND p.Estado = 'Disponible'
                LIMIT 1
            ");
            $stmt->execute([$idVehiculo]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT r.IdRepuesto, p.IdProducto, p.Nombre, p.Stock, p.PrecioVenta, p.PrecioDistribuidor
                FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
                WHERE r.IdRepuesto = ? AND p.Stock > 0 AND p.Estado = 'Disponible'
                LIMIT 1
            ");
            $stmt->execute([$idRepuesto]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca la fila de stock de un vehículo para un distribuidor (o null).
     * Origen: distribuidorUpsertStock() rama 'Vehiculo'.
     *
     * @return array<string, mixed>|null
     */
    public function buscarStockVehiculo(int $idDistribuidor, ?string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdStock, Cantidad FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = 'Vehiculo' AND IdVehiculo = ? LIMIT 1");
        $stmt->execute([$idDistribuidor, $idVehiculo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Busca la fila de stock de un repuesto para un distribuidor (o null).
     * Origen: distribuidorUpsertStock() rama 'Repuesto'.
     *
     * @return array<string, mixed>|null
     */
    public function buscarStockRepuesto(int $idDistribuidor, ?int $idRepuesto): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdStock, Cantidad FROM distribuidor_stock WHERE IdDistribuidor = ? AND TipoItem = 'Repuesto' AND IdRepuesto = ? LIMIT 1");
        $stmt->execute([$idDistribuidor, $idRepuesto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Suma cantidad a una fila de stock existente y pisa los precios.
     * Origen: UPDATE de distribuidorUpsertStock().
     */
    public function sumarCantidadStock(int $idStock, int $cantidad, float $precioVenta, float $precioMinimo): void
    {
        $this->pdo->prepare("UPDATE distribuidor_stock SET Cantidad = Cantidad + ?, PrecioVenta = ?, PrecioMinimo = ? WHERE IdStock = ?")
            ->execute([$cantidad, $precioVenta, $precioMinimo, $idStock]);
    }

    /**
     * Inserta una fila nueva de stock para el distribuidor.
     * Origen: INSERT de distribuidorUpsertStock() (mismas columnas y orden).
     */
    public function insertarStock(int $idDistribuidor, string $tipoItem, ?string $idVehiculo, ?int $idRepuesto, int $cantidad, float $precioVenta, float $precioMinimo): void
    {
        $this->pdo->prepare("
            INSERT INTO distribuidor_stock (IdDistribuidor, TipoItem, IdVehiculo, IdRepuesto, Cantidad, PrecioVenta, PrecioMinimo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$idDistribuidor, $tipoItem, $idVehiculo, $idRepuesto, $cantidad, $precioVenta, $precioMinimo]);
    }

    /**
     * Descuenta stock interno del producto sin dejarlo negativo.
     * Origen: UPDATE producto ... GREATEST(0, Stock - ?) de asignar_stock.php.
     */
    public function descontarStockProducto(int $idProducto, int $cantidad): bool
    {
        $stmt = $this->pdo->prepare("UPDATE producto SET Stock = Stock - ?, Estado = IF(Stock - ? <= 0, 'Sin stock', Estado) WHERE IdProducto = ? AND Stock >= ?");
        $stmt->execute([$cantidad, $cantidad, $idProducto, $cantidad]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Marca el producto como 'Sin stock'. Origen: asignar_stock.php (al agotarse).
     */
    public function marcarProductoSinStock(int $idProducto): void
    {
        $this->pdo->prepare("UPDATE producto SET Estado = 'Sin stock' WHERE IdProducto = ?")
            ->execute([$idProducto]);
    }
}
