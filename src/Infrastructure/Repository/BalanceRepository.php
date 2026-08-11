<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura del balance financiero (E5). Preserva textualmente
 * los SELECT de venta y gasto de balance/index.php y balance/exportar.php,
 * incluido el filtro de ventas anuladas y de gastos activos.
 *
 * IMPORTANTE: index y exportar usan queries distintas (distinto set de columnas
 * y, en gastos, index trae TipoCambioAplicado mientras export no). Se preservan
 * por separado para no alterar el comportamiento de cada pantalla.
 */
final class BalanceRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * WHERE de ventas (rango de fechas) con prefijo "AND" para concatenar tras
     * el filtro fijo de anuladas. Sin filtro deja cadena vacía.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function ventasWhere(string $desde, string $hasta): array
    {
        $where = [];
        $params = [];
        if ($desde !== '') {
            $where[] = "DATE(v.FechaVenta) >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $where[] = "DATE(v.FechaVenta) <= :hasta";
            $params[':hasta'] = $hasta;
        }
        return [$where ? 'AND ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * WHERE de gastos (estado activo + rango de fechas) con prefijo "WHERE".
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function gastosWhere(string $desde, string $hasta): array
    {
        $where = ["COALESCE(g.Estado, 'Activo') = 'Activo'"];
        $params = [];
        if ($desde !== '') {
            $where[] = "DATE(g.FechaGasto) >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $where[] = "DATE(g.FechaGasto) <= :hasta";
            $params[':hasta'] = $hasta;
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Ventas no anuladas para el resumen del balance (pantalla index).
     *
     * @return list<array<string,mixed>>
     */
    public function ventasResumen(string $desde, string $hasta): array
    {
        [$whereSql, $params] = $this->ventasWhere($desde, $hasta);
        $stmt = $this->pdo->prepare("
            SELECT
                v.IdVenta,
                v.FechaVenta,
                v.Total,
                v.GananciaEstimada,
                v.Moneda,
                v.EstadoVenta,
                v.MontoPagado,
                v.SaldoPendiente,
                v.MontoIVA,
                v.TotalSinIVA,
                v.TipoCambioAplicado
            FROM venta v
            WHERE (v.EstadoVenta IS NULL OR v.EstadoVenta <> 'Anulada')
            {$whereSql}
            ORDER BY v.FechaVenta DESC, v.IdVenta DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Gastos activos para el resumen del balance (pantalla index). Incluye
     * TipoCambioAplicado (con COALESCE a 0) para el tipo de cambio histórico.
     *
     * @return list<array<string,mixed>>
     */
    public function gastosResumen(string $desde, string $hasta): array
    {
        [$whereSql, $params] = $this->gastosWhere($desde, $hasta);
        $stmt = $this->pdo->prepare("
            SELECT
                g.IdGasto,
                g.FechaGasto,
                g.Categoria,
                g.MetodoPago,
                g.Moneda,
                g.Monto,
                g.Concepto,
                g.Observaciones,
                COALESCE(g.TipoCambioAplicado, 0) AS TipoCambioAplicado
            FROM gasto g
            {$whereSql}
            ORDER BY g.FechaGasto DESC, g.IdGasto DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ventas no anuladas para la exportación CSV (incluye cliente y datos de
     * tarjeta).
     *
     * @return list<array<string,mixed>>
     */
    public function ventasExport(string $desde, string $hasta): array
    {
        [$whereSql, $params] = $this->ventasWhere($desde, $hasta);
        $stmt = $this->pdo->prepare("
            SELECT
                v.IdVenta,
                v.FechaVenta,
                v.Total,
                v.GananciaEstimada,
                v.Moneda,
                v.EstadoVenta,
                v.MontoPagado,
                v.SaldoPendiente,
                v.MetodoPago,
                v.TipoCambioAplicado,
                v.TipoTarjeta,
                v.MarcaTarjeta,
                v.CuotasTarjeta,
                v.MontoIVA,
                v.TotalSinIVA,
                c.NombreApellido AS Cliente
            FROM venta v
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            WHERE (v.EstadoVenta IS NULL OR v.EstadoVenta <> 'Anulada')
            {$whereSql}
            ORDER BY v.FechaVenta DESC, v.IdVenta DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Gastos activos para la exportación CSV. Incluye TipoCambioAplicado para
     * conservar consistencia histórica con la pantalla de balance.
     *
     * @return list<array<string,mixed>>
     */
    public function gastosExport(string $desde, string $hasta): array
    {
        [$whereSql, $params] = $this->gastosWhere($desde, $hasta);
        $stmt = $this->pdo->prepare("
            SELECT
                g.IdGasto,
                g.FechaGasto,
                g.Categoria,
                g.MetodoPago,
                g.Moneda,
                g.Monto,
                g.Concepto,
                g.Observaciones,
                COALESCE(g.TipoCambioAplicado, 0) AS TipoCambioAplicado
            FROM gasto g
            {$whereSql}
            ORDER BY g.FechaGasto DESC, g.IdGasto DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function mesesResumen(float $tipoCambio, int $limite): array
    {
        $stmt = $this->pdo->prepare("
            SELECT periodo,
                   SUM(ventas_uyu) AS ventas_uyu,
                   SUM(ganancia_uyu) AS ganancia_uyu,
                   SUM(gastos_uyu) AS gastos_uyu
            FROM (
                SELECT DATE_FORMAT(FechaVenta, '%Y-%m') AS periodo,
                       CASE WHEN Moneda = 'USD' THEN Total * COALESCE(NULLIF(TipoCambioAplicado, 0), :tc1) ELSE Total END AS ventas_uyu,
                       CASE WHEN Moneda = 'USD' THEN GananciaEstimada * COALESCE(NULLIF(TipoCambioAplicado, 0), :tc2) ELSE GananciaEstimada END AS ganancia_uyu,
                       0 AS gastos_uyu
                FROM venta
                WHERE COALESCE(EstadoVenta, 'Confirmada') <> 'Anulada'
                UNION ALL
                SELECT DATE_FORMAT(FechaGasto, '%Y-%m') AS periodo,
                       0 AS ventas_uyu,
                       0 AS ganancia_uyu,
                       CASE WHEN Moneda = 'USD' THEN Monto * :tc3 ELSE Monto END AS gastos_uyu
                FROM gasto
                WHERE COALESCE(Estado, 'Activo') = 'Activo'
            ) x
            WHERE periodo IS NOT NULL
            GROUP BY periodo
            ORDER BY periodo DESC
            LIMIT " . (int) $limite
        );
        $stmt->execute([
            ':tc1' => $tipoCambio,
            ':tc2' => $tipoCambio,
            ':tc3' => $tipoCambio,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
