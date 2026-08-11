<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class RepuestoCrudRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Importaciones activas para los selects de los formularios.
     *
     * @return list<array<string,mixed>>
     */
    public function importacionesActivas(): array
    {
        $stmt = $this->pdo->query("SELECT Numero, TipoCambioUSD, Descripcion FROM importacion WHERE Activa = 1 ORDER BY Numero ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Tipo de cambio de una importación activa, o null si no aplica/no existe.
     */
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

    /**
     * Ficha de repuesto+producto por IdProducto (vista de edición).
     * Conserva exactamente el set de columnas legacy: NO incluye NumeroImportacion
     * (el formulario de edición nunca preseleccionó la importación guardada).
     *
     * @return array<string,mixed>|null
     */
    public function buscarPorProducto(int $idProducto): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.IdRepuesto,
                p.IdProducto,
                p.Nombre,
                p.Descripcion,
                p.Costo,
                p.GastoTotal,
                p.PrecioVenta,
                p.PrecioDistribuidor,
                p.Moneda,
                p.Stock,
                p.Estado,
                p.MostrarEnWeb
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.IdProducto = ?
        ");
        $stmt->execute([$idProducto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Repuesto+producto por IdRepuesto (para ocultar).
     *
     * @return array<string,mixed>|null
     */
    public function buscarPorRepuesto(int $idRepuesto): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.IdRepuesto, r.IdProducto, p.Nombre
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE r.IdRepuesto = ?
            LIMIT 1
        ");
        $stmt->execute([$idRepuesto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Inserta producto (Repuesto) + repuesto. No abre transacción: el caller la posee.
     *
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos): int
    {
        $tipoCambioImportacion = $this->tipoCambioImportacion(
            $datos['numero_importacion'] !== null ? (int) $datos['numero_importacion'] : null
        );

        $stmtProducto = $this->pdo->prepare("INSERT INTO producto (Nombre, TipoProducto, Descripcion, Costo, GastoTotal, PrecioVenta, PrecioDistribuidor, Moneda, Stock, Estado, MostrarEnWeb, Empresa_RUT)
            VALUES (?, 'Repuesto', ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
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
        $stmtRepuesto = $this->pdo->prepare("INSERT INTO repuesto (IdProducto, NombreInterno, NumeroImportacion, TipoCambioImportacion) VALUES (?, ?, ?, ?)");
        $stmtRepuesto->execute([
            $idProducto,
            $datos['nombre'],
            $datos['numero_importacion'],
            $tipoCambioImportacion,
        ]);

        return $idProducto;
    }

    /**
     * Actualiza producto + repuesto. No abre transacción.
     *
     * @param array<string,mixed> $datos
     */
    public function editar(int $idProducto, array $datos): void
    {
        $tipoCambioImportacion = $this->tipoCambioImportacion(
            $datos['numero_importacion'] !== null ? (int) $datos['numero_importacion'] : null
        );

        $stmtProducto = $this->pdo->prepare("UPDATE producto SET Nombre = ?, Descripcion = ?, Costo = ?, GastoTotal = ?, PrecioVenta = ?, PrecioDistribuidor = ?, Moneda = ?, Stock = ?, Estado = ? WHERE IdProducto = ?");
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
            $idProducto,
        ]);

        $stmtRepuesto = $this->pdo->prepare("UPDATE repuesto SET NombreInterno = ?, NumeroImportacion = ?, TipoCambioImportacion = ? WHERE IdProducto = ?");
        $stmtRepuesto->execute([
            $datos['nombre'],
            $datos['numero_importacion'],
            $tipoCambioImportacion,
            $idProducto,
        ]);
    }

    /**
     * Marca el producto como Oculto y lo saca de la web.
     */
    public function ocultar(int $idProducto): void
    {
        $stmt = $this->pdo->prepare("UPDATE producto SET Estado = 'Oculto', MostrarEnWeb = 0 WHERE IdProducto = ?");
        $stmt->execute([$idProducto]);
    }
}
