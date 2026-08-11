<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura del listado de gastos (E1).
 *
 * Preserva textualmente el SELECT, el filtro de estado activo, los filtros
 * dinámicos y el ordenamiento usados por gastos/index.php y gastos/exportar.php.
 */
final class GastoConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Listado de gastos activos con los filtros legacy (categoría, método y
     * rango de fechas). Solo gastos con estado activo, ordenados por fecha.
     *
     * @param array{categoria:string,metodo:string,desde:string,hasta:string} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros): array
    {
        $where = ["COALESCE(g.Estado, 'Activo') = 'Activo'"];
        $params = [];

        if ($filtros['categoria'] !== '') {
            $where[] = "g.Categoria = :categoria";
            $params[':categoria'] = $filtros['categoria'];
        }

        if ($filtros['metodo'] !== '') {
            $where[] = "g.MetodoPago = :metodo";
            $params[':metodo'] = $filtros['metodo'];
        }

        if ($filtros['desde'] !== '') {
            $where[] = "DATE(g.FechaGasto) >= :desde";
            $params[':desde'] = $filtros['desde'];
        }

        if ($filtros['hasta'] !== '') {
            $where[] = "DATE(g.FechaGasto) <= :hasta";
            $params[':hasta'] = $filtros['hasta'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                g.IdGasto,
                g.FechaGasto,
                g.Categoria,
                g.MetodoPago,
                g.Moneda,
                g.Monto,
                g.Concepto,
                g.Observaciones
            FROM gasto g
            {$whereSql}
            ORDER BY g.FechaGasto DESC, g.IdGasto DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
