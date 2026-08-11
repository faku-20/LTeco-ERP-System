<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de SOLO LECTURA de postventa (listado + detalle).
 *
 * Extraído verbatim desde lteco-panel/postventa/index.php y detalle.php para que
 * esas páginas queden como vistas finas. No abre transacciones; no muta nada.
 *
 * El alcance por vendedor se interpola por id ENTERO (igual que el legacy), no por
 * placeholder, porque va dentro de subqueries correlacionadas; el valor proviene
 * de la sesión, ya casteado a int.
 */
final class PostventaConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Listado de motos en seguimiento. $estadoService/$garantia deben venir ya
     * validados contra las listas permitidas (o vacíos).
     *
     * @return list<array<string,mixed>>
     */
    public function listadoMotos(string $q, string $estadoService, string $garantia, int $idUsuarioVendedor): array
    {
        $where = ["ve.EstadoVenta <> 'Anulada'"];
        $params = [];

        $idv = $idUsuarioVendedor;
        $ventaOwnershipWhereSql    = $idv > 0 ? "ve.UsuarioVendedorId = {$idv}" : '';
        $serviceVentaOwnershipSql  = $idv > 0 ? " AND vsvc.UsuarioVendedorId = {$idv}" : '';
        $serviceLookupOwnershipSql = $idv > 0 ? " AND vsv2.UsuarioVendedorId = {$idv}" : '';

        if ($q !== '') {
            $where[] = "(
        c.NombreApellido LIKE ?
        OR c.Telefono LIKE ?
        OR v.NumeroMotor LIKE ?
        OR v.Modelo LIKE ?
        OR v.IdVehiculo LIKE ?
        OR CAST(ve.IdVenta AS CHAR) LIKE ?
    )";
            $like = '%' . $q . '%';
            for ($i = 0; $i < 6; $i++) { $params[] = $like; }
        }

        if ($ventaOwnershipWhereSql !== '') {
            $where[] = $ventaOwnershipWhereSql;
        }

        if ($estadoService !== '') {
            if ($estadoService === 'Sin pendientes') {
                $where[] = "COALESCE(svc.TienePendienteOVencido, 0) = 0";
            } else {
                $where[] = "COALESCE(svc.Tiene" . $estadoService . ", 0) = 1";
            }
        } else {
            // Si no hay filtro explícito, excluir cancelados
            $where[] = "COALESCE(svc.TieneCancelado, 0) = 0";
        }

        if ($garantia !== '') {
            if ($garantia === 'Sin garantía') {
                $where[] = "NOT EXISTS (SELECT 1 FROM garantia gf WHERE gf.IdVehiculo = v.IdVehiculo AND gf.IdVenta = ve.IdVenta)";
            } else {
                $where[] = "EXISTS (SELECT 1 FROM garantia gf WHERE gf.IdVehiculo = v.IdVehiculo AND gf.IdVenta = ve.IdVenta AND gf.Estado = ?)";
                $params[] = $garantia;
            }
        }

        $sql = "
    SELECT
        v.IdVehiculo,
        v.NumeroMotor,
        v.Modelo,
        v.Color,

        MAX(ve.IdVenta) AS IdVenta,
        MAX(ve.FechaVenta) AS FechaVenta,

        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(c.NombreApellido, 'Sin cliente') ORDER BY ve.IdVenta DESC SEPARATOR '||'), '||', 1) AS NombreApellido,
        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(c.Telefono, '-') ORDER BY ve.IdVenta DESC SEPARATOR '||'), '||', 1) AS Telefono,
        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(g.Estado, '-') ORDER BY ve.IdVenta DESC SEPARATOR '||'), '||', 1) AS EstadoGarantia,
        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(g.FechaFin, '-') ORDER BY ve.IdVenta DESC SEPARATOR '||'), '||', 1) AS VencimientoGarantia,

        (SELECT sv2.FechaProgramada FROM service_vehiculo sv2 INNER JOIN venta vsv2 ON vsv2.IdVenta = sv2.IdVenta WHERE sv2.IdVehiculo = v.IdVehiculo{$serviceLookupOwnershipSql} AND sv2.Estado IN ('Vencido', 'Pendiente') ORDER BY FIELD(sv2.Estado, 'Vencido', 'Pendiente'), sv2.FechaProgramada ASC LIMIT 1) AS ProximoService,
        (SELECT sv2.Estado FROM service_vehiculo sv2 INNER JOIN venta vsv2 ON vsv2.IdVenta = sv2.IdVenta WHERE sv2.IdVehiculo = v.IdVehiculo{$serviceLookupOwnershipSql} AND sv2.Estado IN ('Vencido', 'Pendiente') ORDER BY FIELD(sv2.Estado, 'Vencido', 'Pendiente'), sv2.FechaProgramada ASC LIMIT 1) AS EstadoProximoService,

        COALESCE(svc.CantPendiente, 0) AS CantPendiente,
        COALESCE(svc.CantVencido, 0) AS CantVencido,
        COALESCE(svc.CantRealizado, 0) AS CantRealizado,
        COALESCE(svc.CantCancelado, 0) AS CantCancelado

    FROM vehiculo v
    INNER JOIN venta_detalle vd ON vd.Producto_IdProducto = v.IdProducto
    INNER JOIN venta ve ON ve.IdVenta = vd.Venta_IdVenta
    LEFT JOIN cliente c ON c.IdCliente = ve.Cliente_IdCliente
    LEFT JOIN garantia g ON g.IdVehiculo = v.IdVehiculo AND g.IdVenta = ve.IdVenta
    LEFT JOIN (
        SELECT
            sv.IdVehiculo,
            MAX(CASE WHEN sv.Estado = 'Pendiente' AND sv.FechaProgramada >= CURDATE() THEN 1 ELSE 0 END) AS TienePendiente,
            MAX(CASE WHEN sv.Estado = 'Vencido' OR (sv.Estado = 'Pendiente' AND sv.FechaProgramada < CURDATE()) THEN 1 ELSE 0 END) AS TieneVencido,
            MAX(CASE WHEN sv.Estado = 'Realizado' THEN 1 ELSE 0 END) AS TieneRealizado,
            MAX(CASE WHEN sv.Estado = 'Cancelado' THEN 1 ELSE 0 END) AS TieneCancelado,
            MAX(CASE WHEN sv.Estado IN ('Pendiente', 'Vencido') THEN 1 ELSE 0 END) AS TienePendienteOVencido,
            SUM(CASE WHEN sv.Estado = 'Pendiente' AND sv.FechaProgramada >= CURDATE() THEN 1 ELSE 0 END) AS CantPendiente,
            SUM(CASE WHEN sv.Estado = 'Vencido' OR (sv.Estado = 'Pendiente' AND sv.FechaProgramada < CURDATE()) THEN 1 ELSE 0 END) AS CantVencido,
            SUM(CASE WHEN sv.Estado = 'Realizado' THEN 1 ELSE 0 END) AS CantRealizado,
            SUM(CASE WHEN sv.Estado = 'Cancelado' THEN 1 ELSE 0 END) AS CantCancelado
        FROM service_vehiculo sv
        INNER JOIN venta vsvc ON vsvc.IdVenta = sv.IdVenta
        WHERE COALESCE(vsvc.EstadoVenta, 'Confirmada') <> 'Anulada'
          {$serviceVentaOwnershipSql}
        GROUP BY sv.IdVehiculo
    ) svc ON svc.IdVehiculo = v.IdVehiculo

    WHERE " . implode("\n      AND ", $where) . "

    GROUP BY v.IdVehiculo, v.NumeroMotor, v.Modelo, v.Color
    ORDER BY MAX(ve.FechaVenta) DESC, v.IdVehiculo DESC
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Services pendientes en los próximos 14 días sin notificar por WhatsApp.
     *
     * @return list<array<string,mixed>>
     */
    public function recordatoriosWhatsApp(int $idUsuarioVendedor): array
    {
        $idv = $idUsuarioVendedor;
        $ventaOwnershipSql = $idv > 0 ? " AND ve.UsuarioVendedorId = {$idv}" : '';

        $stmt = $this->pdo->query("
    SELECT
        sv.IdService, sv.IdVehiculo, sv.NumeroService, sv.FechaProgramada,
        v.NumeroMotor, v.Modelo,
        c.NombreApellido, c.Telefono
    FROM service_vehiculo sv
    INNER JOIN vehiculo v ON v.IdVehiculo = sv.IdVehiculo
    LEFT JOIN venta ve ON ve.IdVenta = sv.IdVenta
    LEFT JOIN cliente c ON c.IdCliente = ve.Cliente_IdCliente
    WHERE sv.Estado = 'Pendiente'
        AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
      {$ventaOwnershipSql}
      AND sv.FechaProgramada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
      AND NOT EXISTS (
          SELECT 1 FROM service_historial sh
          WHERE sh.IdService = sv.IdService AND sh.TipoEvento = 'NOTIFICACION_WA'
      )
    ORDER BY sv.FechaProgramada ASC
");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Métricas de cabecera del listado.
     *
     * @return array{ServicesVencidos:int,ProximosServices:int,GarantiasVigentes:int,VehiculosSeguimiento:int}
     */
    public function metricas(int $idUsuarioVendedor): array
    {
        $idv = $idUsuarioVendedor;
        $metricasOwnershipSql = $idv > 0 ? " AND v.UsuarioVendedorId = {$idv}" : '';

        $servicesVencidos = (int) ($this->pdo->query("
    SELECT COUNT(*) AS total
    FROM service_vehiculo sv
    INNER JOIN venta v ON v.IdVenta = sv.IdVenta
    WHERE sv.Estado = 'Vencido'
      AND COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
      {$metricasOwnershipSql}
")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $proximosServices = (int) ($this->pdo->query("
    SELECT COUNT(*) AS total
    FROM service_vehiculo sv
    INNER JOIN venta v ON v.IdVenta = sv.IdVenta
    WHERE sv.Estado = 'Pendiente'
      AND COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
      {$metricasOwnershipSql}
      AND sv.FechaProgramada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $garantiasVigentes = (int) ($this->pdo->query("
    SELECT COUNT(*) AS total
    FROM garantia g
    INNER JOIN venta v ON v.IdVenta = g.IdVenta
    WHERE g.Estado = 'Vigente'
      AND COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
      {$metricasOwnershipSql}
")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $vehiculosSeguimiento = (int) ($this->pdo->query("
    SELECT COUNT(DISTINCT sv.IdVehiculo) AS total
    FROM service_vehiculo sv
    INNER JOIN venta v ON v.IdVenta = sv.IdVenta
    WHERE COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
      {$metricasOwnershipSql}
")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        return [
            'ServicesVencidos'     => $servicesVencidos,
            'ProximosServices'     => $proximosServices,
            'GarantiasVigentes'    => $garantiasVigentes,
            'VehiculosSeguimiento' => $vehiculosSeguimiento,
        ];
    }

    /**
     * Cabecera del detalle: vehículo + su venta no anulada más reciente.
     *
     * @return array<string,mixed>|null
     */
    public function vehiculoConVenta(string $idVehiculo, int $idUsuarioVendedor): ?array
    {
        $idv = $idUsuarioVendedor;
        $ventaOwnershipSql = $idv > 0 ? " AND ve.UsuarioVendedorId = {$idv}" : '';

        $stmt = $this->pdo->prepare("
    SELECT
        v.IdVehiculo, v.NumeroMotor, v.Modelo, v.Color, v.FechaVenta,
        ve.IdVenta, ve.FechaVenta AS VentaFecha,
        c.IdCliente, c.NombreApellido, c.Telefono,
        g.Estado AS EstadoGarantia, g.FechaInicio, g.FechaFin
    FROM vehiculo v
    LEFT JOIN venta_detalle vd ON vd.Producto_IdProducto = v.IdProducto
    LEFT JOIN venta ve ON ve.IdVenta = vd.Venta_IdVenta AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
    LEFT JOIN cliente c ON c.IdCliente = ve.Cliente_IdCliente
    LEFT JOIN garantia g ON g.IdVehiculo = v.IdVehiculo AND g.IdVenta = ve.IdVenta
    WHERE v.IdVehiculo = ? AND ve.IdVenta IS NOT NULL
    {$ventaOwnershipSql}
    ORDER BY ve.IdVenta DESC
    LIMIT 1
");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function servicesDeVenta(string $idVehiculo, int $idVenta): array
    {
        $stmt = $this->pdo->prepare("
    SELECT * FROM service_vehiculo
    WHERE IdVehiculo = ? AND IdVenta = ?
    ORDER BY NumeroService ASC
");
        $stmt->execute([$idVehiculo, $idVenta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function historialDeVenta(string $idVehiculo, int $idVenta): array
    {
        $stmt = $this->pdo->prepare("
    SELECT h.IdHistorial, h.IdService, h.TipoEvento, h.Detalle, h.Usuario, h.FechaEvento
    FROM service_historial h
    INNER JOIN service_vehiculo sv ON sv.IdService = h.IdService
    WHERE h.IdVehiculo = ? AND sv.IdVenta = ?
    ORDER BY h.FechaEvento DESC, h.IdHistorial DESC
");
        $stmt->execute([$idVehiculo, $idVenta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function historialTecnico(string $idVehiculo, int $idVenta): array
    {
        if (!$this->tablaExiste('postventa_historial_tecnico')) {
            return [];
        }

        $stmt = $this->pdo->prepare("
        SELECT *
        FROM postventa_historial_tecnico
        WHERE IdVehiculo = ? AND IdVenta = ?
        ORDER BY FechaApertura DESC, IdHistorialTecnico DESC
    ");
        $stmt->execute([$idVehiculo, $idVenta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Repuestos usados por una lista de intervenciones técnicas.
     *
     * @param list<int> $idsHistorial
     * @return list<array<string,mixed>>
     */
    public function repuestosUsados(array $idsHistorial): array
    {
        if ($idsHistorial === [] || !$this->tablaExiste('postventa_repuesto_usado')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idsHistorial), '?'));
        $stmt = $this->pdo->prepare("
        SELECT pru.*, p.Nombre
        FROM postventa_repuesto_usado pru
        INNER JOIN producto p ON p.IdProducto = pru.IdProducto
        WHERE pru.IdHistorialTecnico IN ($placeholders)
        ORDER BY pru.FechaUso DESC
    ");
        $stmt->execute($idsHistorial);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function repuestosDisponibles(): array
    {
        if (!$this->tablaExiste('repuesto')) {
            return [];
        }

        return $this->pdo->query("
        SELECT r.IdRepuesto, p.Nombre, p.Stock
        FROM repuesto r
        INNER JOIN producto p ON p.IdProducto = r.IdProducto
        WHERE p.TipoProducto = 'Repuesto' AND p.Estado <> 'Oculto' AND p.Stock > 0
        ORDER BY p.Nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Réplica de includes/helpers.php::dbTieneTabla (las vistas ya no llaman helpers).
     */
    private function tablaExiste(string $tabla): bool
    {
        static $cache = [];
        if (array_key_exists($tabla, $cache)) {
            return $cache[$tabla];
        }

        $stmt = $this->pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
        $stmt->execute([$tabla]);

        return $cache[$tabla] = ((int) $stmt->fetchColumn()) > 0;
    }
}
