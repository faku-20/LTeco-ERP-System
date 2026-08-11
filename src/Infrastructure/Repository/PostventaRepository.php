<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos de postventa: garantía, services y su historial.
 *
 * Origen: SQL inline en ventas/guardar.php, distribuidores/nueva_venta.php,
 * vehiculos/service_realizar.php y postventa/{service_cancelar,
 * service_observacion_agregar, marcar_notificado_wa, actualizar_estados}.php.
 * Movido aquí SIN cambios de comportamiento (mismas sentencias y parámetros).
 *
 * TRANSACTION-AGNOSTIC: reutiliza el $pdo de Connection y NUNCA abre/cierra
 * transacción; el dueño de la transacción sigue siendo el caller.
 */
final class PostventaRepository
{
    private PDO $pdo;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
    }

    // ===================================================================== //
    //  Alta de venta: garantía + services automáticos (idempotentes)        //
    // ===================================================================== //

    /**
     * Garantía automática (12 meses). Idempotente por (IdVehiculo, IdVenta).
     */
    public function generarGarantia(string $idVehiculo, int $idVenta, int $idCliente, ?string $fechaInicio = null): void
    {
        $existe = $this->pdo->prepare(
            'SELECT IdGarantia FROM garantia WHERE IdVehiculo = ? AND IdVenta = ? LIMIT 1'
        );
        $existe->execute([$idVehiculo, $idVenta]);
        if ($existe->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $inicio = $fechaInicio ?: date('Y-m-d');
        $this->pdo->prepare(
            "INSERT INTO garantia (IdVehiculo, IdVenta, IdCliente, FechaInicio, FechaFin, Estado, Observaciones)
             VALUES (?, ?, ?, ?, ?, 'Vigente', ?)"
        )->execute([
            $idVehiculo,
            $idVenta,
            $idCliente,
            $inicio,
            date('Y-m-d', strtotime($inicio . ' +12 months')),
            'Garantía generada automáticamente al registrar la entrega.',
        ]);
    }

    /**
     * 4 services automáticos (+3/6/9/12 meses, Pendiente). Idempotente por
     * (IdVehiculo, IdVenta) — LTECO:SERVICE_EXISTE_POR_VENTA_V3.
     */
    public function generarServices(string $idVehiculo, int $idVenta, int $idCliente, ?string $fechaInicio = null): void
    {
        $existe = $this->pdo->prepare(
            'SELECT IdService FROM service_vehiculo WHERE IdVehiculo = ? AND IdVenta = ? LIMIT 1'
        );
        $existe->execute([$idVehiculo, $idVenta]);
        if ($existe->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $inicio = $fechaInicio ?: date('Y-m-d');
        $services = [1 => '+3 months', 2 => '+6 months', 3 => '+9 months', 4 => '+12 months'];
        $stmt = $this->pdo->prepare(
            "INSERT INTO service_vehiculo (IdVehiculo, IdVenta, IdCliente, NumeroService, FechaProgramada, Estado, Observaciones)
             VALUES (?, ?, ?, ?, ?, 'Pendiente', ?)"
        );
        foreach ($services as $numeroService => $offset) {
            $stmt->execute([
                $idVehiculo,
                $idVenta,
                $idCliente,
                $numeroService,
                date('Y-m-d', strtotime($inicio . ' ' . $offset)),
                'Service generado automáticamente al registrar la entrega.',
            ]);
        }
    }

    // ===================================================================== //
    //  Operaciones sobre un service (realizar / cancelar / observación)     //
    // ===================================================================== //

    /**
     * Lee el estado del service + su garantía + el estado de la venta asociada,
     * para validar si se puede realizar. Origen: SELECT con LEFT JOIN garantia/venta
     * de vehiculos/service_realizar.php. Devuelve null si no existe el service.
     *
     * @return array{EstadoService: ?string, EstadoGarantia: ?string, EstadoVenta: string}|null
     */
    public function buscarServiceParaRealizar(int $idService, string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                sv.Estado AS EstadoService,
                g.Estado AS EstadoGarantia,
                COALESCE(v.EstadoVenta, 'Confirmada') AS EstadoVenta
            FROM service_vehiculo sv
            LEFT JOIN garantia g ON g.IdVehiculo = sv.IdVehiculo AND g.IdVenta = sv.IdVenta
            LEFT JOIN venta v ON v.IdVenta = sv.IdVenta
            WHERE sv.IdService = ? AND sv.IdVehiculo = ?
            LIMIT 1
        ");
        $stmt->execute([$idService, $idVehiculo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    /**
     * Devuelve el NumeroService del service indicado, o null si no existe.
     * Origen: SELECT NumeroService de vehiculos/service_realizar.php.
     */
    public function obtenerNumeroService(int $idService, string $idVehiculo): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT NumeroService FROM service_vehiculo WHERE IdService = ? AND IdVehiculo = ? LIMIT 1'
        );
        $stmt->execute([$idService, $idVehiculo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : (int) $fila['NumeroService'];
    }

    /**
     * Cuenta services anteriores (NumeroService menor) aún sin realizar
     * (Pendiente/Vencido). Origen: SELECT COUNT(*) de vehiculos/service_realizar.php.
     */
    public function contarServicesPreviosPendientes(string $idVehiculo, int $numeroService): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM service_vehiculo
            WHERE IdVehiculo = ? AND NumeroService < ? AND Estado IN ('Pendiente', 'Vencido')
        ");
        $stmt->execute([$idVehiculo, $numeroService]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Marca un service como Realizado (sólo si está Pendiente/Vencido).
     * Origen: vehiculos/service_realizar.php. Devuelve filas afectadas.
     */
    public function marcarServiceRealizado(int $idService, string $idVehiculo, string $observaciones): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE service_vehiculo
            SET
                Estado = 'Realizado',
                FechaRealizada = CURDATE(),
                Observaciones = ?
            WHERE IdService = ?
              AND IdVehiculo = ?
              AND Estado IN ('Pendiente', 'Vencido')
        ");
        $stmt->execute([$observaciones, $idService, $idVehiculo]);

        return $stmt->rowCount();
    }

    /**
     * Cancela un service (sólo Pendiente/Vencido), anexando el motivo a las
     * Observaciones. Origen: postventa/service_cancelar.php. Devuelve filas afectadas.
     */
    public function cancelarService(int $idService, string $idVehiculo, string $motivo): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE service_vehiculo
            SET
                Estado = 'Cancelado',
                Observaciones = TRIM(CONCAT(
                    COALESCE(NULLIF(Observaciones, ''), ''),
                    CASE
                        WHEN COALESCE(NULLIF(Observaciones, ''), '') = '' THEN ''
                        ELSE '\n\n'
                    END,
                    '[CANCELADO ',
                    DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'),
                    '] ',
                    ?
                ))
            WHERE IdService = ?
              AND IdVehiculo = ?
              AND Estado IN ('Pendiente', 'Vencido')
        ");
        $stmt->execute([$motivo, $idService, $idVehiculo]);

        return $stmt->rowCount();
    }

    /**
     * Agrega una nota técnica a Observaciones (sin cambiar estado/fecha),
     * recortada a 500 chars. Origen: postventa/service_observacion_agregar.php.
     * Devuelve filas afectadas.
     */
    public function agregarObservacionService(int $idService, string $idVehiculo, string $nota): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE service_vehiculo
            SET
                Observaciones = LEFT(
                    TRIM(CONCAT(
                        COALESCE(NULLIF(Observaciones, ''), ''),
                        CASE
                            WHEN COALESCE(NULLIF(Observaciones, ''), '') = '' THEN ''
                            ELSE '\n\n'
                        END,
                        '[NOTA ',
                        DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'),
                        '] ',
                        ?
                    )),
                    500
                )
            WHERE IdService = ?
              AND IdVehiculo = ?
        ");
        $stmt->execute([$nota, $idService, $idVehiculo]);

        return $stmt->rowCount();
    }

    // ===================================================================== //
    //  Intervención técnica (postventa_historial_tecnico + repuestos)        //
    // ===================================================================== //

    /**
     * Inserta una intervención técnica en postventa_historial_tecnico y devuelve
     * su IdHistorialTecnico. Origen: el INSERT inline de
     * postventa/intervencion_guardar.php — mismas columnas, mismo cálculo de
     * TiempoTotalMinutos (TIMESTAMPDIFF a NOW() cuando hay FechaCierre).
     *
     * El estado, las fechas y el técnico llegan ya resueltos por el caller; este
     * método sólo ejecuta la sentencia tal cual la tenía la página legacy.
     */
    public function insertarIntervencion(
        string $idVehiculo,
        int $idVenta,
        ?int $idCliente,
        ?int $idService,
        string $diagnostico,
        ?string $solucion,
        ?string $tecnico,
        string $estado,
        ?string $fechaInicio,
        ?string $fechaCierre,
        ?string $observaciones,
        ?int $idUsuarioRegistra
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO postventa_historial_tecnico (
                IdVehiculo, IdVenta, IdCliente, IdService, Diagnostico, SolucionAplicada, Tecnico,
                Estado, FechaInicioReparacion, FechaCierre, TiempoTotalMinutos, Observaciones, IdUsuarioRegistra
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? IS NULL, NULL, TIMESTAMPDIFF(MINUTE, NOW(), ?)), ?, ?)
        ");
        $stmt->execute([
            $idVehiculo,
            $idVenta,
            $idCliente,
            $idService,
            $diagnostico,
            $solucion,
            $tecnico,
            $estado,
            $fechaInicio,
            $fechaCierre,
            $fechaCierre,
            $fechaCierre,
            $observaciones,
            $idUsuarioRegistra,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lee un repuesto con su producto bloqueando la fila (FOR UPDATE) para
     * descontar stock de forma consistente dentro de la transacción del caller.
     * Origen: SELECT ... FOR UPDATE de intervencion_guardar.php. null si no existe.
     *
     * @return array{IdRepuesto:int,IdProducto:int,Nombre:string,Stock:int,Costo:string,Moneda:string}|null
     */
    public function buscarRepuestoParaConsumo(int $idRepuesto): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.IdRepuesto, r.IdProducto, p.Nombre, p.Stock, p.Costo, p.Moneda
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE r.IdRepuesto = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$idRepuesto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    /**
     * Descuenta stock del producto y lo pasa a 'Sin stock' si llega a 0.
     * Origen: UPDATE producto de intervencion_guardar.php.
     */
    public function descontarStockProducto(int $idProducto, int $cantidad): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE producto SET Stock = Stock - ?, Estado = IF(Stock - ? <= 0, 'Sin stock', Estado) WHERE IdProducto = ? AND Stock >= ?"
        );
        $stmt->execute([$cantidad, $cantidad, $idProducto, $cantidad]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Registra el repuesto consumido por una intervención.
     * Origen: INSERT postventa_repuesto_usado de intervencion_guardar.php.
     */
    public function registrarRepuestoUsado(
        int $idHistorial,
        int $idRepuesto,
        int $idProducto,
        int $cantidad,
        float $costoUnitario,
        string $moneda
    ): void {
        $this->pdo->prepare("
            INSERT INTO postventa_repuesto_usado (IdHistorialTecnico, IdRepuesto, IdProducto, Cantidad, CostoUnitario, Moneda)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$idHistorial, $idRepuesto, $idProducto, $cantidad, $costoUnitario, $moneda]);
    }

    /**
     * Inserta un evento en service_historial copiando IdService/IdVehiculo/IdVenta
     * del service. Origen: el INSERT ... SELECT repetido en las acciones de service.
     */
    public function registrarHistorial(
        int $idService,
        string $idVehiculo,
        string $tipoEvento,
        string $detalle,
        string $usuario
    ): void {
        $this->pdo->prepare("
            INSERT INTO service_historial (
                IdService,
                IdVehiculo,
                IdVenta,
                TipoEvento,
                Detalle,
                Usuario
            )
            SELECT
                IdService,
                IdVehiculo,
                IdVenta,
                ?,
                ?,
                ?
            FROM service_vehiculo
            WHERE IdService = ?
              AND IdVehiculo = ?
            LIMIT 1
        ")->execute([$tipoEvento, $detalle, $usuario, $idService, $idVehiculo]);
    }

    // ===================================================================== //
    //  Batch de estados (cron al cargar postventa)                          //
    // ===================================================================== //

    /**
     * Garantías Vigentes cuya FechaFin ya pasó → Vencida.
     * Variante scoped por vendedor (idUsuarioVendedor > 0) o global.
     * Origen: postventa/actualizar_estados.php.
     */
    public function marcarGarantiasVencidas(int $idUsuarioVendedor): void
    {
        if ($idUsuarioVendedor > 0) {
            $this->pdo->prepare("
                UPDATE garantia g
                INNER JOIN venta v ON v.IdVenta = g.IdVenta
                SET g.Estado = 'Vencida'
                WHERE g.Estado = 'Vigente'
                  AND g.FechaFin < CURDATE()
                  AND v.UsuarioVendedorId = ?
            ")->execute([$idUsuarioVendedor]);
        } else {
            $this->pdo->prepare("
                UPDATE garantia SET Estado = 'Vencida'
                WHERE Estado = 'Vigente' AND FechaFin < CURDATE()
            ")->execute();
        }
    }

    /**
     * Transiciones automáticas de services: Pendiente→Vencido (fecha pasada),
     * Vencido→Pendiente (fecha futura, sin realizar) y auto-cancelación tras 14
     * días vencido. Variante scoped por vendedor o global.
     * Origen: postventa/actualizar_estados.php.
     */
    public function actualizarEstadosServices(int $idUsuarioVendedor): void
    {
        if ($idUsuarioVendedor > 0) {
            $this->pdo->prepare("
                UPDATE service_vehiculo sv
                INNER JOIN venta v ON v.IdVenta = sv.IdVenta
                SET sv.Estado = 'Vencido'
                WHERE sv.Estado = 'Pendiente'
                  AND sv.FechaProgramada < CURDATE()
                  AND v.UsuarioVendedorId = ?
            ")->execute([$idUsuarioVendedor]);

            $this->pdo->prepare("
                UPDATE service_vehiculo sv
                INNER JOIN venta v ON v.IdVenta = sv.IdVenta
                SET sv.Estado = 'Pendiente'
                WHERE sv.Estado = 'Vencido'
                  AND sv.FechaProgramada >= CURDATE()
                  AND sv.FechaRealizada IS NULL
                  AND v.UsuarioVendedorId = ?
            ")->execute([$idUsuarioVendedor]);

            $this->pdo->prepare("
                UPDATE service_vehiculo sv
                INNER JOIN venta v ON v.IdVenta = sv.IdVenta
                SET sv.Estado = 'Cancelado',
                    sv.Observaciones = CONCAT(COALESCE(sv.Observaciones, ''), IF(COALESCE(sv.Observaciones,'') <> '', ' | ', ''), '[Auto-cancelado: no realizado dentro del plazo de 2 semanas]')
                WHERE sv.Estado IN ('Pendiente', 'Vencido')
                  AND sv.FechaProgramada < DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                  AND v.UsuarioVendedorId = ?
            ")->execute([$idUsuarioVendedor]);
        } else {
            $this->pdo->prepare("
                UPDATE service_vehiculo SET Estado = 'Vencido'
                WHERE Estado = 'Pendiente' AND FechaProgramada < CURDATE()
            ")->execute();

            $this->pdo->prepare("
                UPDATE service_vehiculo SET Estado = 'Pendiente'
                WHERE Estado = 'Vencido' AND FechaProgramada >= CURDATE() AND FechaRealizada IS NULL
            ")->execute();

            $this->pdo->prepare("
                UPDATE service_vehiculo
                SET Estado = 'Cancelado',
                    Observaciones = CONCAT(COALESCE(Observaciones, ''), IF(COALESCE(Observaciones,'') <> '', ' | ', ''), '[Auto-cancelado: no realizado dentro del plazo de 2 semanas]')
                WHERE Estado IN ('Pendiente', 'Vencido')
                  AND FechaProgramada < DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            ")->execute();
        }
    }

    /** @return list<array<string,mixed>> */
    public function servicesProximos(): array
    {
        $stmt = $this->pdo->query("
            SELECT sv.IdService, sv.FechaProgramada, sv.NumeroService,
                   v.Modelo, v.NumeroMotor, c.NombreApellido
            FROM service_vehiculo sv
            INNER JOIN vehiculo v ON v.IdVehiculo = sv.IdVehiculo
            LEFT JOIN venta ve ON ve.IdVenta = sv.IdVenta
            LEFT JOIN cliente c ON c.IdCliente = ve.Cliente_IdCliente
            WHERE sv.Estado = 'Pendiente'
              AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
              AND sv.FechaProgramada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY sv.FechaProgramada ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return list<array<string,mixed>> */
    public function servicesVencidosParaNotificar(): array
    {
        $stmt = $this->pdo->query("
            SELECT sv.IdService, sv.FechaProgramada, sv.NumeroService,
                   v.Modelo, v.NumeroMotor, c.NombreApellido
            FROM service_vehiculo sv
            INNER JOIN vehiculo v ON v.IdVehiculo = sv.IdVehiculo
            LEFT JOIN venta ve ON ve.IdVenta = sv.IdVenta
            LEFT JOIN cliente c ON c.IdCliente = ve.Cliente_IdCliente
            WHERE sv.Estado = 'Vencido'
              AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
            ORDER BY sv.FechaProgramada ASC
            LIMIT 20
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function vendedorPuedeIntervenir(string $idVehiculo, int $idVenta, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM vehiculo vh
            INNER JOIN venta_detalle vd ON vd.Producto_IdProducto = vh.IdProducto
            INNER JOIN venta v ON v.IdVenta = vd.Venta_IdVenta
            WHERE vh.IdVehiculo = ?
              AND v.IdVenta = ?
              AND v.UsuarioVendedorId = ?
              AND COALESCE(v.EstadoVenta, 'Confirmada') <> 'Anulada'
            LIMIT 1
        ");
        $stmt->execute([$idVehiculo, $idVenta, $idUsuario]);
        return (bool) $stmt->fetchColumn();
    }
}
