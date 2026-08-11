<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura del listado de repuestos (D2).
 */
final class RepuestoConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * IdDistribuidor asociado a un usuario (cuando la sesión no lo trae).
     */
    public function distribuidorIdPorUsuario(int $idUsuario): int
    {
        $stmt = $this->pdo->prepare("SELECT IdDistribuidor FROM usuario WHERE IdUsuario = ? LIMIT 1");
        $stmt->execute([$idUsuario]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Datos básicos del distribuidor para el encabezado del catálogo.
     *
     * @return array<string,mixed>|null
     */
    public function distribuidorBasico(int $idDistribuidor): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdDistribuidor, Nombre, Contacto, Telefono, Correo FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1");
        $stmt->execute([$idDistribuidor]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Listado de repuestos con los filtros legacy.
     *
     * @param array{estado:string,q:string,importacion:string,es_distribuidor:bool} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros): array
    {
        $esDistribuidor = (bool) $filtros['es_distribuidor'];
        $where = [];
        $params = [];
        $cajasDisponibles = $this->tablasCajasDisponibles();

        if (!$esDistribuidor && $filtros['estado'] !== '') {
            $where[] = "p.Estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if ($filtros['q'] !== '') {
            // Placeholders nombrados distintos: con prepares nativos (sin
            // emulación) no se puede reusar :q en dos posiciones (HY093).
            $where[] = "(p.Nombre LIKE :q_nombre OR p.Descripcion LIKE :q_desc)";
            $params[':q_nombre'] = '%' . $filtros['q'] . '%';
            $params[':q_desc'] = '%' . $filtros['q'] . '%';
        }

        if (!$esDistribuidor && $filtros['importacion'] !== '') {
            if ($filtros['importacion'] === 'sin') {
                $where[] = "r.NumeroImportacion IS NULL";
            } else {
                $where[] = "r.NumeroImportacion = :importacion";
                $params[':importacion'] = (int) $filtros['importacion'];
            }
        }

        if ($esDistribuidor) {
            $where[] = "p.Estado = 'Disponible'";
            $where[] = "p.Stock > 0";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cajasSelect = $cajasDisponibles
            ? "COALESCE(cajas.UbicadoEnCajas, 0) AS UbicadoEnCajas,
                GREATEST(p.Stock - COALESCE(cajas.UbicadoEnCajas, 0), 0) AS StockSinUbicar,
                cajas.CajasResumen"
            : "0 AS UbicadoEnCajas,
                p.Stock AS StockSinUbicar,
                NULL AS CajasResumen";

        $cajasJoin = $cajasDisponibles
            ? "LEFT JOIN (
                SELECT
                    i.IdRepuesto,
                    SUM(i.Cantidad) AS UbicadoEnCajas,
                    GROUP_CONCAT(CONCAT(
                        c.Codigo,
                        ':',
                        REPLACE(REPLACE(COALESCE(NULLIF(c.Nombre, ''), c.Codigo), '|', '/'), ':', ' - '),
                        ':',
                        i.Cantidad
                    ) ORDER BY c.Codigo SEPARATOR '|') AS CajasResumen
                FROM repuesto_caja_item i
                INNER JOIN repuesto_caja c ON c.IdCaja = i.IdCaja
                GROUP BY i.IdRepuesto
            ) cajas ON cajas.IdRepuesto = r.IdRepuesto"
            : "";

        $sql = "
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
                r.NumeroImportacion,
                r.TipoCambioImportacion,
                {$cajasSelect}
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            {$cajasJoin}
            {$whereSql}
            ORDER BY p.Nombre ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Importaciones para el filtro (todas, descendente).
     *
     * @return list<array<string,mixed>>
     */
    public function importaciones(): array
    {
        $stmt = $this->pdo->query("SELECT Numero, Descripcion FROM importacion ORDER BY Numero DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Conteo de repuestos por estado.
     *
     * @return array<string,int>
     */
    public function conteosPorEstado(): array
    {
        $stmt = $this->pdo->query("SELECT p.Estado, COUNT(*) AS Cant
               FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
               GROUP BY p.Estado");
        $conteos = [];
        foreach ($stmt as $row) {
            $conteos[(string) ($row['Estado'] ?? '')] = (int) ($row['Cant'] ?? 0);
        }
        return $conteos;
    }

    /**
     * Cantidad de repuestos disponibles con stock (vista distribuidor).
     */
    public function conteoDisponiblesDistribuidor(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS Cant
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.Estado = 'Disponible' AND p.Stock > 0
        ");
        return (int) $stmt->fetchColumn();
    }

    private function tablasCajasDisponibles(): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('repuesto_caja', 'repuesto_caja_item')
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn() === 2;
    }
}
