<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Escrituras de gastos (E2): alta y edición. No abre transacciones: el caller
 * las posee. Preserva textualmente los INSERT/UPDATE legacy de
 * gastos/guardar.php y gastos/editar.php, incluido `Descripcion = NULL`.
 */
final class GastoCrudRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Ficha completa del gasto (vista de edición).
     *
     * @return array<string,mixed>|null
     */
    public function buscar(int $idGasto): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM gasto WHERE IdGasto = ?");
        $stmt->execute([$idGasto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Inserta un gasto y devuelve su IdGasto. El tipo de cambio aplicado se
     * recibe ya resuelto (el handler conserva el helper obtenerTipoCambio).
     *
     * @param array{
     *     fecha_gasto:string,concepto:string,categoria:string,metodo_pago:string,
     *     moneda:string,monto:float,observaciones:?string,tipo_cambio_aplicado:float
     * } $datos
     */
    public function crear(array $datos): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO gasto
            (FechaGasto, Concepto, Categoria, MetodoPago, Moneda, Monto, Descripcion, Observaciones, TipoCambioAplicado)
            VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)
        ");
        $stmt->execute([
            $datos['fecha_gasto'],
            $datos['concepto'],
            $datos['categoria'],
            $datos['metodo_pago'],
            $datos['moneda'],
            $datos['monto'],
            $datos['observaciones'],
            $datos['tipo_cambio_aplicado'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un gasto. Preserva el UPDATE legacy (incluye Descripcion = NULL
     * y NO toca TipoCambioAplicado).
     *
     * @param array{
     *     fecha_gasto:string,concepto:string,categoria:string,metodo_pago:string,
     *     moneda:string,monto:float,observaciones:?string
     * } $datos
     */
    public function editar(int $idGasto, array $datos): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE gasto
            SET FechaGasto = ?,
                Concepto = ?,
                Categoria = ?,
                MetodoPago = ?,
                Moneda = ?,
                Monto = ?,
                Descripcion = NULL,
                Observaciones = ?
            WHERE IdGasto = ?
        ");
        $stmt->execute([
            $datos['fecha_gasto'],
            $datos['concepto'],
            $datos['categoria'],
            $datos['metodo_pago'],
            $datos['moneda'],
            $datos['monto'],
            $datos['observaciones'],
            $idGasto,
        ]);
    }

    public function tablaDisponible(): bool
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'gasto'");
        return $stmt && (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{
     *   id_venta:int,concepto:string,metodo_pago:string,moneda:string,
     *   monto:float,observaciones:string
     * } $datos
     */
    public function registrarComisionVenta(array $datos): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO gasto
                (IdVenta, FechaGasto, Concepto, Categoria, MetodoPago, Moneda, Monto, Observaciones, Estado, Origen)
            VALUES (?, CURDATE(), ?, 'Comisiones', ?, ?, ?, ?, 'Activo', 'Venta')
        ");
        $stmt->execute([
            $datos['id_venta'],
            $datos['concepto'],
            $datos['metodo_pago'],
            $datos['moneda'],
            $datos['monto'],
            $datos['observaciones'],
        ]);
    }
}
