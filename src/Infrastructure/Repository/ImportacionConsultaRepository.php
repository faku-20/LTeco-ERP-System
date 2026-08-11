<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura del listado de importaciones (E3). Preserva
 * textualmente el SELECT con conteo de vehículos y el orden de
 * importaciones/index.php.
 */
final class ImportacionConsultaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Importaciones con la cantidad de vehículos asociados, descendente por
     * número.
     *
     * @return list<array<string,mixed>>
     */
    public function listarConConteoVehiculos(): array
    {
        $stmt = $this->pdo->query("
            SELECT i.*, COUNT(v.IdVehiculo) as TotalVehiculos
            FROM importacion i
            LEFT JOIN vehiculo v ON v.NumeroImportacion = i.Numero
            GROUP BY i.IdImportacion
            ORDER BY i.Numero DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function listarActivasParaSelector(): array
    {
        $stmt = $this->pdo->query("
            SELECT Numero, TipoCambioUSD, Descripcion
            FROM importacion
            WHERE Activa = 1
            ORDER BY Numero ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function tipoCambioActivo(int $numero): ?float
    {
        $stmt = $this->pdo->prepare("SELECT TipoCambioUSD FROM importacion WHERE Numero = ? AND Activa = 1 LIMIT 1");
        $stmt->execute([$numero]);
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (float) $valor : null;
    }

    public function tipoCambioMasRecienteActivo(): ?float
    {
        $stmt = $this->pdo->query("SELECT TipoCambioUSD FROM importacion WHERE Activa = 1 ORDER BY Numero DESC LIMIT 1");
        $valor = $stmt ? $stmt->fetchColumn() : false;
        return $valor !== false ? (float) $valor : null;
    }
}
