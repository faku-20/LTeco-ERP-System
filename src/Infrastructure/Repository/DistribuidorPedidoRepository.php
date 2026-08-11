<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos de los pedidos de stock de distribuidores (tabla
 * distribuidor_pedido) y de su remito asociado (tabla remito), más el bloqueo
 * del producto interno que se va a despachar.
 *
 * Origen: SQL inline del POST de lteco-panel/distribuidores/nuevo_pedido.php.
 * Movido aquí SIN cambios de comportamiento (mismas sentencias y parámetros,
 * mismo lock FOR UPDATE).
 *
 * TRANSACTION-AGNOSTIC: reutiliza el $pdo de Connection y NUNCA abre/cierra
 * transacción; el dueño de la transacción sigue siendo el caller (el lock
 * FOR UPDATE sólo tiene efecto dentro de la transacción del caller).
 */
final class DistribuidorPedidoRepository
{
    private PDO $pdo;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarPendiente(int $idPedido): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->consultaBase() . " WHERE dp.IdPedido = ? AND dp.Estado = 'Pendiente' LIMIT 1"
        );
        $stmt->execute([$idPedido]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPendientes(): array
    {
        $stmt = $this->pdo->prepare(
            $this->consultaBase() . " WHERE dp.Estado = 'Pendiente' ORDER BY dp.FechaPedido ASC, dp.IdPedido ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function distribuidoresActivos(): array
    {
        $stmt = $this->pdo->query('SELECT IdDistribuidor, Nombre FROM distribuidor WHERE Activo = 1 ORDER BY Nombre ASC');
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return list<array<string,mixed>> */
    public function vehiculosSolicitables(): array
    {
        $stmt = $this->pdo->query("
            SELECT v.IdVehiculo, p.IdProducto, p.Nombre, p.Stock, p.PrecioVenta, p.Moneda
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE p.Estado = 'Disponible'
              AND NOT EXISTS (
                  SELECT 1 FROM distribuidor_pedido dp
                  WHERE dp.IdVehiculo = v.IdVehiculo
                    AND dp.Estado IN ('Pendiente', 'Aprobado')
              )
            ORDER BY p.Nombre ASC, v.IdVehiculo ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return list<array<string,mixed>> */
    public function repuestosSolicitables(): array
    {
        $stmt = $this->pdo->query("
            SELECT r.IdRepuesto, p.IdProducto, p.Nombre, p.Stock, p.PrecioVenta, p.PrecioDistribuidor, p.Moneda
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.Estado <> 'Oculto' AND p.Stock > 0
            ORDER BY p.Nombre ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<string,mixed>|null */
    public function itemSolicitable(string $tipoItem, ?string $idVehiculo, ?int $idRepuesto): ?array
    {
        if ($tipoItem === 'Vehiculo') {
            $stmt = $this->pdo->prepare("
                SELECT v.IdVehiculo, p.IdProducto, p.Nombre, p.Stock, p.PrecioVenta
                FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
                WHERE v.IdVehiculo = ? AND p.Estado = 'Disponible'
                LIMIT 1
            ");
            $stmt->execute([$idVehiculo]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT r.IdRepuesto, p.IdProducto, p.Nombre, p.Stock, p.PrecioVenta, p.PrecioDistribuidor
                FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
                WHERE r.IdRepuesto = ? AND p.Estado <> 'Oculto'
                LIMIT 1
            ");
            $stmt->execute([$idRepuesto]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function vehiculoYaSolicitado(?string $idVehiculo): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM distribuidor_pedido WHERE IdVehiculo = ? AND Estado IN ('Pendiente', 'Aprobado')");
        $stmt->execute([$idVehiculo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Bloquea (FOR UPDATE) el producto de un vehículo disponible y devuelve sus
     * datos de stock/precio (o null). Origen: lock de nuevo_pedido.php.
     *
     * @return array<string, mixed>|null
     */
    public function bloquearProductoPorVehiculo(?string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.IdProducto, p.Stock, p.PrecioVenta FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto WHERE v.IdVehiculo = ? AND p.Estado = 'Disponible' LIMIT 1 FOR UPDATE");
        $stmt->execute([$idVehiculo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Bloquea (FOR UPDATE) el producto de un repuesto y devuelve sus datos de
     * stock/precio (o null). Origen: lock de nuevo_pedido.php.
     *
     * @return array<string, mixed>|null
     */
    public function bloquearProductoPorRepuesto(?int $idRepuesto): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.IdProducto, p.Stock, p.PrecioVenta, p.PrecioDistribuidor FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto WHERE r.IdRepuesto = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$idRepuesto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Inserta el pedido ya como 'Aprobado' (proceso automático) y devuelve su
     * IdPedido. Origen: INSERT distribuidor_pedido de nuevo_pedido.php (mismas
     * columnas, orden y FechaResolucion = NOW()).
     */
    public function crearPedidoAprobado(
        int $idDistribuidor,
        string $tipoItem,
        ?string $idVehiculo,
        ?int $idRepuesto,
        int $cantidad,
        ?string $observaciones,
        int $idUsuario
    ): int {
        $this->pdo->prepare("
            INSERT INTO distribuidor_pedido (IdDistribuidor, TipoItem, IdVehiculo, IdRepuesto, Cantidad, Estado, Observaciones, IdUsuarioSolicita, FechaResolucion)
            VALUES (?, ?, ?, ?, ?, 'Aprobado', ?, ?, NOW())
        ")->execute([
            $idDistribuidor, $tipoItem, $idVehiculo, $idRepuesto,
            $cantidad, $observaciones, $idUsuario,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Marca un pedido ya existente como resuelto (Aprobado/Rechazado) y sella la
     * fecha de resolución. Origen: UPDATE distribuidor_pedido de pedidos_admin.php
     * (mismas columnas, FechaResolucion = NOW()).
     */
    public function marcarEstadoResuelto(int $idPedido, string $nuevoEstado): void
    {
        $this->pdo->prepare("UPDATE distribuidor_pedido SET Estado = ?, FechaResolucion = NOW() WHERE IdPedido = ?")
            ->execute([$nuevoEstado, $idPedido]);
    }

    /**
     * Inserta el remito 'Pendiente' del pedido. Origen: INSERT remito de
     * nuevo_pedido.php. El caller decide si la tabla remito existe.
     */
    public function crearRemitoPendiente(
        int $idDistribuidor,
        int $idPedido,
        string $tipoItem,
        ?string $idVehiculo,
        ?int $idRepuesto,
        int $cantidad
    ): void {
        $this->pdo->prepare("INSERT INTO remito (IdDistribuidor, IdPedido, TipoItem, IdVehiculo, IdRepuesto, Cantidad, Estado) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')")
            ->execute([$idDistribuidor, $idPedido, $tipoItem, $idVehiculo, $idRepuesto, $cantidad]);
    }

    private function consultaBase(): string
    {
        return "
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
        ";
    }
}
