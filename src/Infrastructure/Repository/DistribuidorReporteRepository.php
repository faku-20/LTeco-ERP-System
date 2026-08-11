<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Persistencia de reportes de problemas enviados por distribuidores.
 *
 * No abre transacciones ni contiene decisiones HTTP, permisos o auditoría.
 */
final class DistribuidorReporteRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function tablaDisponible(): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'distribuidor_reporte_problema'
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(
        int $idDistribuidor,
        int $idUsuario,
        string $mensaje,
        ?string $imagenRuta
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO distribuidor_reporte_problema
                (IdDistribuidor, IdUsuario, Mensaje, ImagenRuta, EstadoInterno, FechaCreacion)
            VALUES (?, ?, ?, ?, 'Nuevo', NOW())
        ");
        $stmt->execute([$idDistribuidor, $idUsuario, $mensaje, $imagenRuta]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarPorId(int $idReporte): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.*,
                d.Nombre AS DistribuidorNombre,
                u.NombreCompleto AS UsuarioNombre,
                ur.NombreCompleto AS UsuarioResolucionNombre
            FROM distribuidor_reporte_problema r
            INNER JOIN distribuidor d ON d.IdDistribuidor = r.IdDistribuidor
            INNER JOIN usuario u ON u.IdUsuario = r.IdUsuario
            LEFT JOIN usuario ur ON ur.IdUsuario = r.UsuarioResolucionId
            WHERE r.IdReporte = ?
            LIMIT 1
        ");
        $stmt->execute([$idReporte]);
        $reporte = $stmt->fetch(PDO::FETCH_ASSOC);

        return $reporte ?: null;
    }

    public function actualizarEstado(
        int $idReporte,
        string $nuevoEstado,
        ?int $usuarioResolucionId
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE distribuidor_reporte_problema
            SET EstadoInterno = ?,
                UsuarioResolucionId = ?,
                FechaResolucion = CASE WHEN ? = 'Resuelto' THEN NOW() ELSE NULL END
            WHERE IdReporte = ?
        ");
        $stmt->execute([$nuevoEstado, $usuarioResolucionId, $nuevoEstado, $idReporte]);
    }
}
