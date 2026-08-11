<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Lecturas auxiliares de QR, etiquetas y escaneo de vehículos.
 */
final class VehiculoConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function paraQr(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                v.IdVehiculo,
                v.NumeroMotor,
                v.Modelo,
                v.Color,
                v.FechaIngreso,
                p.Nombre,
                p.Estado,
                p.Moneda,
                p.PrecioVenta
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE v.IdVehiculo = ?
            LIMIT 1
        ");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param list<string> $ids
     * @return list<array<string,mixed>>
     */
    public function paraEtiquetas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT v.IdVehiculo, v.NumeroMotor, v.Modelo, v.Color
            FROM vehiculo v
            WHERE v.IdVehiculo IN ({$placeholders})
            ORDER BY v.IdVehiculo ASC
        ");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarEscaneado(string $motor, ?string $idVehiculo): ?array
    {
        if ($idVehiculo !== null && $idVehiculo !== '') {
            $stmt = $this->pdo->prepare("
                SELECT v.IdVehiculo, v.NumeroMotor, v.Modelo, p.Estado
                FROM vehiculo v
                INNER JOIN producto p ON p.IdProducto = v.IdProducto
                WHERE v.IdVehiculo = ?
                  AND v.NumeroMotor = ?
                LIMIT 1
            ");
            $stmt->execute([$idVehiculo, $motor]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT v.IdVehiculo, v.NumeroMotor, v.Modelo, p.Estado
                FROM vehiculo v
                INNER JOIN producto p ON p.IdProducto = v.IdProducto
                WHERE v.NumeroMotor = ?
                LIMIT 1
            ");
            $stmt->execute([$motor]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
