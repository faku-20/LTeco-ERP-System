<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class DistribuidorCuentaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function tablaComisionesDisponible(): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'distribuidor_comision'
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    public function primerDistribuidorActivoId(): int
    {
        $stmt = $this->pdo->query(
            'SELECT IdDistribuidor FROM distribuidor WHERE Activo = 1 ORDER BY Nombre ASC LIMIT 1'
        );
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarDistribuidor(int $idDistribuidor): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1');
        $stmt->execute([$idDistribuidor]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDistribuidores(): array
    {
        return $this->pdo->query(
            'SELECT IdDistribuidor, Nombre FROM distribuidor ORDER BY Nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarComisiones(int $idDistribuidor, string $desde, string $hasta): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dc.*, v.FechaVenta, v.Total, c.NombreApellido
            FROM distribuidor_comision dc
            INNER JOIN venta v ON v.IdVenta = dc.IdVenta
            LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
            WHERE dc.IdDistribuidor = ?
              AND DATE(dc.FechaGenerada) BETWEEN ? AND ?
            ORDER BY dc.FechaGenerada DESC, dc.IdComision DESC
        ");
        $stmt->execute([$idDistribuidor, $desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function actualizarEstadoComision(
        int $idComision,
        int $idDistribuidor,
        string $estado
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE distribuidor_comision
            SET Estado = ?,
                FechaPago = IF(? = 'Pagada', NOW(), FechaPago),
                FechaActualizacion = NOW()
            WHERE IdComision = ?
              AND IdDistribuidor = ?
        ");
        $stmt->execute([$estado, $estado, $idComision, $idDistribuidor]);
    }
}
