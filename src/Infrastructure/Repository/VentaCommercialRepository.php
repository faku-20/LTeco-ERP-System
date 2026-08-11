<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Lecturas SQL para configuración y comisiones del flujo de venta.
 */
final class VentaCommercialRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function configuracionActual(): ?array
    {
        $stmt = $this->pdo->query('
            SELECT DescuentoContado, RecargoTarjeta, ComisionDistribuidor, ComisionVendedor, TasaIVA
            FROM configuracion
            ORDER BY IdConfiguracion DESC
            LIMIT 1
        ');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return $row ?: null;
    }

    public function comisionUsuario(int $idUsuario): mixed
    {
        $stmt = $this->pdo->prepare('SELECT ComisionPct FROM usuario WHERE IdUsuario = ? LIMIT 1');
        $stmt->execute([$idUsuario]);
        $value = $stmt->fetchColumn();

        return $value !== false ? $value : null;
    }

    /**
     * @return array{ComisionPct:mixed}|null
     */
    public function comisionDistribuidorActivo(int $idDistribuidor): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ComisionPct FROM distribuidor WHERE IdDistribuidor = ? AND Activo = 1 LIMIT 1'
        );
        $stmt->execute([$idDistribuidor]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function usuarioInternoDistribuidor(string $rolDistribuidor): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.IdUsuario, u.ComisionDistribuidorPct
            FROM configuracion c
            INNER JOIN usuario u ON u.IdUsuario = c.UsuarioComisionDistribuidorId
            WHERE u.Activo = 1
              AND u.Rol <> ?
            ORDER BY c.IdConfiguracion DESC
            LIMIT 1
        ");
        $stmt->execute([$rolDistribuidor]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $fallback = $this->pdo->prepare("
            SELECT IdUsuario, ComisionDistribuidorPct
            FROM usuario
            WHERE Usuario = 'lteco'
              AND Activo = 1
              AND Rol <> ?
            LIMIT 1
        ");
        $fallback->execute([$rolDistribuidor]);
        $row = $fallback->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
