<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class RepuestoCajaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @return list<array<string,mixed>> */
    public function repuestosParaSelect(): array
    {
        $stmt = $this->pdo->query("
            SELECT r.IdRepuesto, p.IdProducto, p.Nombre, p.Stock, p.Estado
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.Estado <> 'Oculto'
            ORDER BY p.Nombre ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function importacionesActivas(): array
    {
        $stmt = $this->pdo->query("SELECT Numero, TipoCambioUSD, Descripcion FROM importacion WHERE Activa = 1 ORDER BY Numero ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function tipoCambioImportacion(?int $numeroImportacion): ?float
    {
        if (!$numeroImportacion) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT TipoCambioUSD FROM importacion WHERE Numero = ? AND Activa = 1 LIMIT 1");
        $stmt->execute([$numeroImportacion]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? (float) $fila['TipoCambioUSD'] : null;
    }

    public function crearCajaPendiente(string $tokenUuid, ?string $nombre, ?string $ubicacion, ?string $observaciones): int
    {
        $codigoTemporal = 'PEND-' . bin2hex(random_bytes(6));
        $stmt = $this->pdo->prepare("
            INSERT INTO repuesto_caja (Codigo, TokenUuid, Nombre, Ubicacion, Observaciones)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$codigoTemporal, $tokenUuid, $nombre, $ubicacion, $observaciones]);
        return (int) $this->pdo->lastInsertId();
    }

    public function fijarCodigo(int $idCaja, string $codigo): void
    {
        $stmt = $this->pdo->prepare("UPDATE repuesto_caja SET Codigo = ? WHERE IdCaja = ?");
        $stmt->execute([$codigo, $idCaja]);
    }

    /** @return array<string,mixed>|null */
    public function cajaParaActualizar(int $idCaja): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdCaja, Codigo, Nombre, Estado FROM repuesto_caja WHERE IdCaja = ? FOR UPDATE");
        $stmt->execute([$idCaja]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string,mixed> $datos */
    public function crearRepuesto(array $datos): int
    {
        $tipoCambioImportacion = $this->tipoCambioImportacion(
            $datos['numero_importacion'] !== null ? (int) $datos['numero_importacion'] : null
        );

        $stmtProducto = $this->pdo->prepare("
            INSERT INTO producto (
                Nombre, TipoProducto, Descripcion, Costo, GastoTotal, PrecioVenta,
                PrecioDistribuidor, Moneda, Stock, Estado, MostrarEnWeb, Empresa_RUT
            ) VALUES (?, 'Repuesto', ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ");
        $stmtProducto->execute([
            $datos['nombre'],
            $datos['descripcion'],
            $datos['costo'],
            $datos['gasto_total'],
            $datos['precio_venta'],
            $datos['precio_distribuidor'],
            $datos['moneda'],
            $datos['stock'],
            $datos['estado'],
            $datos['empresa_rut'],
        ]);

        $idProducto = (int) $this->pdo->lastInsertId();
        $stmtRepuesto = $this->pdo->prepare("
            INSERT INTO repuesto (IdProducto, NombreInterno, NumeroImportacion, TipoCambioImportacion)
            VALUES (?, ?, ?, ?)
        ");
        $stmtRepuesto->execute([
            $idProducto,
            $datos['nombre'],
            $datos['numero_importacion'],
            $tipoCambioImportacion,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function repuestoConProductoParaActualizar(int $idRepuesto): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.IdRepuesto, r.IdProducto, p.Nombre, p.Stock, p.Estado
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE r.IdRepuesto = ?
            FOR UPDATE
        ");
        $stmt->execute([$idRepuesto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function cantidadUbicadaRepuesto(int $idRepuesto): int
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(Cantidad), 0) FROM repuesto_caja_item WHERE IdRepuesto = ?");
        $stmt->execute([$idRepuesto]);
        return (int) $stmt->fetchColumn();
    }

    public function aumentarStockProducto(int $idProducto, int $stockNuevo): void
    {
        $estado = $stockNuevo > 0 ? 'Disponible' : 'Sin stock';
        $stmt = $this->pdo->prepare("UPDATE producto SET Stock = ?, Estado = CASE WHEN Estado = 'Oculto' THEN Estado ELSE ? END WHERE IdProducto = ?");
        $stmt->execute([$stockNuevo, $estado, $idProducto]);
    }

    public function agregarItem(int $idCaja, int $idRepuesto, int $cantidad): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO repuesto_caja_item (IdCaja, IdRepuesto, Cantidad)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE Cantidad = Cantidad + VALUES(Cantidad)
        ");
        $stmt->execute([$idCaja, $idRepuesto, $cantidad]);
    }

    public function registrarMovimiento(int $idCaja, ?int $idRepuesto, string $tipo, int $cantidad, ?int $stockAnterior, ?int $stockNuevo, ?int $idUsuario, string $detalle): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO repuesto_caja_movimiento (
                IdCaja, IdRepuesto, Tipo, Cantidad, StockAnterior, StockNuevo, IdUsuario, Detalle
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$idCaja, $idRepuesto, $tipo, $cantidad, $stockAnterior, $stockNuevo, $idUsuario, $detalle]);
    }

    /** @return list<array<string,mixed>> */
    public function listarCajas(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                c.IdCaja,
                c.Codigo,
                c.TokenUuid,
                c.Nombre,
                c.Ubicacion,
                c.Estado,
                c.FechaAlta,
                COALESCE(SUM(i.Cantidad), 0) AS TotalUnidades,
                COUNT(i.IdCajaItem) AS TotalRepuestos
            FROM repuesto_caja c
            LEFT JOIN repuesto_caja_item i ON i.IdCaja = c.IdCaja
            GROUP BY c.IdCaja, c.Codigo, c.TokenUuid, c.Nombre, c.Ubicacion, c.Estado, c.FechaAlta
            ORDER BY c.IdCaja DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function listarCajasActivas(): array
    {
        $stmt = $this->pdo->query("
            SELECT IdCaja, Codigo, Nombre, Ubicacion
            FROM repuesto_caja
            WHERE Estado = 'Activa'
            ORDER BY COALESCE(NULLIF(Nombre, ''), Codigo), Codigo
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function obtenerCaja(int|string $identificador, string $campo = 'IdCaja'): ?array
    {
        $permitidos = ['IdCaja', 'Codigo', 'TokenUuid'];
        $campo = in_array($campo, $permitidos, true) ? $campo : 'IdCaja';
        $stmt = $this->pdo->prepare("SELECT * FROM repuesto_caja WHERE {$campo} = ? LIMIT 1");
        $stmt->execute([$identificador]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function contenidoCaja(int $idCaja): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.IdCajaItem, i.IdRepuesto, i.Cantidad, p.IdProducto, p.Nombre, p.Stock, p.Estado
            FROM repuesto_caja_item i
            INNER JOIN repuesto r ON r.IdRepuesto = i.IdRepuesto
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE i.IdCaja = ?
            ORDER BY p.Nombre ASC
        ");
        $stmt->execute([$idCaja]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function movimientosCaja(int $idCaja): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, p.Nombre AS RepuestoNombre, u.Usuario
            FROM repuesto_caja_movimiento m
            LEFT JOIN repuesto r ON r.IdRepuesto = m.IdRepuesto
            LEFT JOIN producto p ON p.IdProducto = r.IdProducto
            LEFT JOIN usuario u ON u.IdUsuario = m.IdUsuario
            WHERE m.IdCaja = ?
            ORDER BY m.IdMovimiento DESC
        ");
        $stmt->execute([$idCaja]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function cajasPorRepuesto(int $idRepuesto): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.IdCaja, c.Codigo, c.TokenUuid, c.Nombre, c.Ubicacion, c.Estado, i.Cantidad
            FROM repuesto_caja_item i
            INNER JOIN repuesto_caja c ON c.IdCaja = i.IdCaja
            WHERE i.IdRepuesto = ?
            ORDER BY c.Codigo ASC
        ");
        $stmt->execute([$idRepuesto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
