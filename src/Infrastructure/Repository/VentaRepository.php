<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Repositorio de acceso a datos de la venta (tablas `venta` y `venta_detalle`).
 *
 * Origen histórico: el SQL vivía inline en lteco-panel/ventas/guardar.php
 * (INSERT venta :276, INSERT venta_detalle :899) y en ventas/index.php
 * (listados con filtro Anulada y alcance Vendedor). Esta clase lo encapsula
 * preservando el comportamiento exacto.
 *
 * IMPORTANTE — agnóstico a transacciones:
 *   Reutiliza el $pdo que entrega Connection (el mismo $pdo global del sistema)
 *   y NUNCA llama a beginTransaction()/commit()/rollBack(). El dueño de la
 *   transacción sigue siendo el llamador (guardar.php abre en :159, confirma en
 *   :711, revierte en :792). Así la escritura de una venta sigue siendo atómica
 *   junto con los writes cross-aggregate (vehiculo/gasto/garantía) que por ahora
 *   permanecen en guardar.php.
 *
 * Alcance Fase 1: sólo `venta` + `venta_detalle`.
 */
final class VentaRepository
{
    /**
     * Valor centinela que se traduce a la expresión SQL NOW() en lugar de
     * insertarse como literal. Replica exactamente la convención de
     * guardar.php (la columna FechaVenta usa la hora del servidor MySQL).
     */
    public const AHORA = '__NOW__';

    private PDO $pdo;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
    }

    /**
     * Inserta una fila en `venta` a partir de un mapa columna => valor.
     *
     * Mapea exactamente las columnas provistas (mismo patrón dinámico que
     * guardar.php). No abre transacción: corre dentro de la del llamador.
     *
     * @param array<string,mixed> $datos
     * @return int IdVenta recién creado.
     */
    public function insertVenta(array $datos): int
    {
        return $this->insertarEn('venta', $datos);
    }

    /**
     * Inserta una fila en `venta_detalle` a partir de un mapa columna => valor.
     *
     * @param array<string,mixed> $datos
     * @return int IdVentaDetalle recién creado.
     */
    public function insertDetalle(array $datos): int
    {
        return $this->insertarEn('venta_detalle', $datos);
    }

    public function asignarNumeroFacturaSiVacio(int $idVenta, string $numeroFactura): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE venta
            SET NumeroFactura = ?
            WHERE IdVenta = ?
              AND (NumeroFactura IS NULL OR NumeroFactura = '')
        ");
        $stmt->execute([$numeroFactura, $idVenta]);
    }

    /** @return array<string,mixed>|null */
    public function bloquearVehiculoVenta(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.IdVehiculo, v.IdProducto, v.Modelo, v.NumeroMotor,
                   v.ClienteReservaId, p.PrecioVenta, p.PrecioDistribuidor,
                   p.GastoTotal, p.Moneda, p.Estado, v.TipoCambioImportacion
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE v.IdVehiculo = ?
            FOR UPDATE
        ");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function vehiculoTieneFechaVenta(string $idVehiculo): bool
    {
        $stmt = $this->pdo->prepare('SELECT FechaVenta FROM vehiculo WHERE IdVehiculo = ? AND FechaVenta IS NOT NULL LIMIT 1');
        $stmt->execute([$idVehiculo]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function productoTieneVentaActiva(int $idProducto): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT ve.IdVenta
            FROM venta_detalle vd_check
            INNER JOIN venta ve ON ve.IdVenta = vd_check.Venta_IdVenta
            WHERE vd_check.Producto_IdProducto = ?
              AND ve.EstadoVenta <> 'Anulada'
            LIMIT 1
        ");
        $stmt->execute([$idProducto]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function bloquearRepuestoVenta(int $idRepuesto): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.IdProducto, p.Nombre, p.PrecioVenta, p.PrecioDistribuidor,
                   p.GastoTotal, p.Moneda, p.Stock, p.Estado, r.TipoCambioImportacion
            FROM repuesto r
            INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE r.IdRepuesto = ?
            FOR UPDATE
        ");
        $stmt->execute([$idRepuesto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function clienteWhatsapp(int $idCliente): ?array
    {
        $stmt = $this->pdo->prepare('SELECT NombreApellido, Telefono FROM cliente WHERE IdCliente = ? LIMIT 1');
        $stmt->execute([$idCliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Fecha de fin de la garantía vigente de la venta (para el template de
     * WhatsApp de confirmación). Origen: SQL inline de ventas/guardar.php.
     */
    public function garantiaFechaFinPorVenta(int $idVenta): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT FechaFin
            FROM garantia
            WHERE IdVenta = ?
              AND Estado <> 'Anulada'
            ORDER BY IdGarantia ASC
            LIMIT 1
        ");
        $stmt->execute([$idVenta]);
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (string) $valor : null;
    }

    /**
     * Fechas programadas de los primeros services de la venta (para el template
     * de WhatsApp de confirmación). Origen: SQL inline de ventas/guardar.php.
     *
     * @return list<string>
     */
    public function serviceFechasProgramadasPorVenta(int $idVenta, int $limite = 3): array
    {
        $stmt = $this->pdo->prepare("
            SELECT FechaProgramada
            FROM service_vehiculo
            WHERE IdVenta = ?
              AND Estado <> 'Cancelado'
            ORDER BY NumeroService ASC
            LIMIT " . max(0, $limite) . "
        ");
        $stmt->execute([$idVenta]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function actualizarVenta(int $idVenta, array $datos): void
    {
        if ($datos === []) {
            throw new \InvalidArgumentException('No hay columnas para actualizar en venta.');
        }

        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "`{$columna}` = ?";
        }

        $stmt = $this->pdo->prepare(
            'UPDATE venta SET ' . implode(', ', $asignaciones) . ' WHERE IdVenta = ?'
        );
        $stmt->execute([...array_values($datos), $idVenta]);
    }

    /**
     * Devuelve las ventas NO anuladas.
     *
     * Replica la regla de negocio congelada en CLAUDE.md: las ventas con
     * EstadoVenta = 'Anulada' se excluyen de búsquedas y estadísticas. Se usa
     * COALESCE(EstadoVenta,'Confirmada') para tratar NULL como confirmada.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listarActivas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM venta WHERE COALESCE(EstadoVenta, 'Confirmada') <> 'Anulada'"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve las ventas cuyo UsuarioVendedorId coincide con el dado.
     *
     * Soporta el control de propiedad del rol Vendedor (CLAUDE.md): un vendedor
     * sólo ve sus propias ventas.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listarPorVendedor(int $idUsuarioVendedor): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM venta WHERE UsuarioVendedorId = ?'
        );
        $stmt->execute([$idUsuarioVendedor]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * INSERT dinámico a partir de un mapa columna => valor.
     *
     * @param array<string,mixed> $datos
     */
    private function insertarEn(string $tabla, array $datos): int
    {
        if ($datos === []) {
            throw new \InvalidArgumentException("No hay columnas para insertar en {$tabla}.");
        }

        // Mismo manejo que el loop original de guardar.php (:267-274): el
        // centinela AHORA se emite como NOW() crudo (no como parámetro ligado);
        // el resto de las columnas se ligan con placeholders '?'.
        $listaCols    = [];
        $placeholders = [];
        $valores      = [];
        foreach ($datos as $columna => $valor) {
            $listaCols[] = "`{$columna}`";
            if ($valor === self::AHORA) {
                $placeholders[] = 'NOW()';
            } else {
                $placeholders[] = '?';
                $valores[]      = $valor;
            }
        }

        $sql = 'INSERT INTO `' . $tabla . '` (' . implode(', ', $listaCols)
            . ') VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($valores);

        return (int) $this->pdo->lastInsertId();
    }
}
