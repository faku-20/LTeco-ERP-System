<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Lecturas de clientes (listado, conteos y detalle) — D4.
 * Conserva textualmente las expresiones de moneda y el alcance por vendedor
 * de clientes/index.php y clientes/detalle.php.
 */
final class ClienteConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    private const COND_VENTA_ACTIVA = "(v.EstadoVenta IS NULL OR v.EstadoVenta <> 'Anulada')";

    private function tcFallback(float $tipoCambio): string
    {
        return str_replace(',', '.', (string) $tipoCambio);
    }

    /**
     * Condición de búsqueda multi-campo con un placeholder único por campo.
     * Con prepares nativos (PDO::ATTR_EMULATE_PREPARES = false) no se puede
     * reusar un mismo placeholder nombrado en varias posiciones (HY093), así
     * que cada campo recibe el suyo enlazado al mismo valor LIKE.
     *
     * @param array<string,string> $params Se completa por referencia.
     */
    private function condicionBusquedaCliente(string $filtroCliente, array &$params): string
    {
        $campos = [
            'c.NombreApellido' => ':cliente_nombre',
            'c.Telefono'       => ':cliente_telefono',
            'c.Correo'         => ':cliente_correo',
            'c.Cedula'         => ':cliente_cedula',
            'c.Direccion'      => ':cliente_direccion',
            'c.RUT'            => ':cliente_rut',
        ];
        $like = '%' . $filtroCliente . '%';
        $condiciones = [];
        foreach ($campos as $columna => $placeholder) {
            $condiciones[] = $columna . ' LIKE ' . $placeholder;
            $params[$placeholder] = $like;
        }
        return '(' . implode(' OR ', $condiciones) . ')';
    }

    /**
     * Listado de clientes con agregados de ventas. $idUsuarioVendedor = 0 => sin alcance.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(string $filtroCliente, int $idUsuarioVendedor, float $tipoCambio): array
    {
        $where = [];
        $params = [];
        $joinVentaScopeSql = '';

        if ($filtroCliente !== '') {
            $where[] = $this->condicionBusquedaCliente($filtroCliente, $params);
        }

        if ($idUsuarioVendedor > 0) {
            $joinVentaScopeSql = ' AND v.UsuarioVendedorId = :usuario_vendedor_join';
            $where[] = 'EXISTS (
                SELECT 1
                FROM venta v_scope
                WHERE v_scope.Cliente_IdCliente = c.IdCliente
                  AND v_scope.UsuarioVendedorId = :usuario_vendedor_scope
            )';
            $params[':usuario_vendedor_join'] = $idUsuarioVendedor;
            $params[':usuario_vendedor_scope'] = $idUsuarioVendedor;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $condVentaActiva = self::COND_VENTA_ACTIVA;
        $saldoExpr = 'COALESCE(v.SaldoPendiente, 0)';
        $tcExpr = 'NULLIF(v.TipoCambioAplicado, 0)';
        $tcFallbackSql = $this->tcFallback($tipoCambio);
        $totalUyuExpr = "CASE WHEN v.Moneda = 'USD' THEN COALESCE(v.Total, 0) * COALESCE({$tcExpr}, {$tcFallbackSql}) ELSE COALESCE(v.Total, 0) END";
        $saldoUyuExpr = "CASE WHEN v.Moneda = 'USD' THEN {$saldoExpr} * COALESCE({$tcExpr}, {$tcFallbackSql}) ELSE {$saldoExpr} END";

        $sql = "
            SELECT
                c.IdCliente,
                c.NombreApellido,
                c.Telefono,
                c.Correo,
                c.TipoFiscal,
                c.Cedula,
                c.Direccion,
                c.RUT,
                COUNT(CASE WHEN {$condVentaActiva} THEN v.IdVenta END) AS Compras,
                SUM(CASE WHEN {$condVentaActiva} THEN {$totalUyuExpr} ELSE 0 END) AS TotalGastadoUYU,
                SUM(CASE WHEN {$condVentaActiva} THEN {$saldoUyuExpr} ELSE 0 END) AS SaldoPendienteUYU,
                MAX(CASE WHEN {$condVentaActiva} THEN v.FechaVenta END) AS UltimaCompra
            FROM cliente c
            LEFT JOIN venta v ON v.Cliente_IdCliente = c.IdCliente{$joinVentaScopeSql}
            {$whereSql}
            GROUP BY c.IdCliente, c.NombreApellido, c.Telefono, c.Correo, TipoFiscal, Cedula, Direccion, RUT
            ORDER BY c.IdCliente DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Listado de clientes para exportación CSV (incluye FechaCliente, sin alcance
     * por vendedor — el handler es Admin/Superadmin). Conserva exportar.php.
     *
     * @return list<array<string,mixed>>
     */
    public function listarParaExport(string $filtroCliente, float $tipoCambio): array
    {
        $where = [];
        $params = [];
        if ($filtroCliente !== '') {
            $where[] = $this->condicionBusquedaCliente($filtroCliente, $params);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $fechaClienteSelect = 'c.FechaAlta AS FechaCliente';
        $condVentaActiva = self::COND_VENTA_ACTIVA;
        $saldoExpr = 'COALESCE(v.SaldoPendiente, 0)';
        $tcExpr = 'NULLIF(v.TipoCambioAplicado, 0)';
        $tcFallbackSql = $this->tcFallback($tipoCambio);
        $totalUyuExpr = "CASE WHEN v.Moneda = 'USD' THEN COALESCE(v.Total, 0) * COALESCE({$tcExpr}, {$tcFallbackSql}) ELSE COALESCE(v.Total, 0) END";
        $saldoUyuExpr = "CASE WHEN v.Moneda = 'USD' THEN {$saldoExpr} * COALESCE({$tcExpr}, {$tcFallbackSql}) ELSE {$saldoExpr} END";

        $sql = "
            SELECT
                c.IdCliente,
                c.NombreApellido,
                c.Telefono,
                c.Correo,
                c.TipoFiscal,
                c.Cedula,
                c.Direccion,
                c.RUT,
                {$fechaClienteSelect},
                COUNT(CASE WHEN {$condVentaActiva} THEN v.IdVenta END) AS Compras,
                SUM(CASE WHEN {$condVentaActiva} THEN {$totalUyuExpr} ELSE 0 END) AS TotalGastadoUYU,
                SUM(CASE WHEN {$condVentaActiva} THEN {$saldoUyuExpr} ELSE 0 END) AS SaldoPendienteUYU,
                MAX(CASE WHEN {$condVentaActiva} THEN v.FechaVenta END) AS UltimaCompra
            FROM cliente c
            LEFT JOIN venta v ON v.Cliente_IdCliente = c.IdCliente
            {$whereSql}
            GROUP BY c.IdCliente, c.NombreApellido, c.Telefono, c.Correo, TipoFiscal, Cedula, Direccion, RUT, FechaCliente
            ORDER BY c.IdCliente DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Filas para los conteos de pills. $idUsuarioVendedor = 0 => sin alcance.
     *
     * @return list<array<string,mixed>>
     */
    public function filasConteos(int $idUsuarioVendedor, float $tipoCambio): array
    {
        $joinVentaConteosScopeSql = '';
        $whereConteosSql = '';
        $params = [];

        if ($idUsuarioVendedor > 0) {
            $joinVentaConteosScopeSql = ' AND v.UsuarioVendedorId = :usuario_vendedor_conteos_join';
            $whereConteosSql = 'WHERE EXISTS (
                SELECT 1
                FROM venta v_scope
                WHERE v_scope.Cliente_IdCliente = c.IdCliente
                  AND v_scope.UsuarioVendedorId = :usuario_vendedor_conteos_scope
            )';
            $params[':usuario_vendedor_conteos_join'] = $idUsuarioVendedor;
            $params[':usuario_vendedor_conteos_scope'] = $idUsuarioVendedor;
        }

        $condVentaActiva = self::COND_VENTA_ACTIVA;
        $saldoExpr = 'COALESCE(v.SaldoPendiente, 0)';
        $tcExpr = 'NULLIF(v.TipoCambioAplicado, 0)';
        $tcFallbackSql = $this->tcFallback($tipoCambio);
        $saldoUyuExpr = "CASE WHEN v.Moneda = 'USD' THEN {$saldoExpr} * COALESCE({$tcExpr}, {$tcFallbackSql}) ELSE {$saldoExpr} END";

        $stmt = $this->pdo->prepare("
            SELECT c.IdCliente,
                   COUNT(CASE WHEN {$condVentaActiva} THEN v.IdVenta END) AS Compras,
                   SUM(CASE WHEN {$condVentaActiva} THEN {$saldoUyuExpr} ELSE 0 END) AS Saldo
            FROM cliente c
            LEFT JOIN venta v ON v.Cliente_IdCliente = c.IdCliente{$joinVentaConteosScopeSql}
            {$whereConteosSql}
            GROUP BY c.IdCliente
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ficha de cliente para el detalle (set de columnas legacy).
     *
     * @return array<string,mixed>|null
     */
    public function cliente(int $idCliente): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdCliente, NombreApellido, Telefono, Correo, TipoFiscal, Cedula, Direccion, RUT FROM cliente WHERE IdCliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Ventas de un cliente para el detalle. $idUsuarioVendedor = 0 => sin alcance.
     *
     * @return list<array<string,mixed>>
     */
    public function ventas(int $idCliente, int $idUsuarioVendedor): array
    {
        $ventaScopeSql = $idUsuarioVendedor > 0 ? ' AND v.UsuarioVendedorId = ?' : '';
        $sql = "
            SELECT
                v.IdVenta,
                v.FechaVenta,
                v.Total,
                v.GananciaEstimada,
                v.Moneda,
                v.MetodoPago,
                v.EstadoVenta,
                v.MontoPagado,
                v.SaldoPendiente,
                v.TipoCambioAplicado
            FROM venta v
            WHERE v.Cliente_IdCliente = ?
            {$ventaScopeSql}
            ORDER BY v.FechaVenta DESC, v.IdVenta DESC
        ";
        $params = [$idCliente];
        if ($idUsuarioVendedor > 0) {
            $params[] = $idUsuarioVendedor;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Líneas de venta de un cliente para el detalle. $idUsuarioVendedor = 0 => sin alcance.
     *
     * @return list<array<string,mixed>>
     */
    public function detalles(int $idCliente, int $idUsuarioVendedor): array
    {
        $ventaScopeSql = $idUsuarioVendedor > 0 ? ' AND v.UsuarioVendedorId = ?' : '';
        $sql = "
            SELECT
                v.IdVenta,
                vd.Cantidad,
                vd.PrecioUnitario,
                vd.Subtotal,
                vd.Moneda,
                p.Nombre,
                p.TipoProducto,
                ve.IdVehiculo,
                ve.Modelo,
                ve.NumeroMotor
            FROM venta v
            INNER JOIN venta_detalle vd ON vd.Venta_IdVenta = v.IdVenta
            INNER JOIN producto p ON p.IdProducto = vd.Producto_IdProducto
            LEFT JOIN vehiculo ve ON ve.IdProducto = p.IdProducto
            WHERE v.Cliente_IdCliente = ?
            {$ventaScopeSql}
            ORDER BY v.FechaVenta DESC, v.IdVenta DESC, p.Nombre ASC
        ";
        $params = [$idCliente];
        if ($idUsuarioVendedor > 0) {
            $params[] = $idUsuarioVendedor;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
