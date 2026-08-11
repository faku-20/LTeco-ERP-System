<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class AuditoriaEscrituraRepository
{
    private PDO $pdo;
    private ?bool $tablaDisponible = null;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function tablaDisponible(): bool
    {
        if ($this->tablaDisponible === null) {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'auditoria'");
            $this->tablaDisponible = $stmt && (bool) $stmt->fetchColumn();
        }
        return $this->tablaDisponible;
    }

    /** @param array<string,mixed> $datos */
    public function registrar(array $datos): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (
                IdUsuario, Usuario, Rol, Accion, Modulo, Detalle, ExtraJson, Ip, UserAgent, FechaHora
            ) VALUES (
                :id_usuario, :usuario, :rol, :accion, :modulo, :detalle, :extra_json, :ip, :user_agent, NOW()
            )
        ");
        $stmt->execute($datos);
    }
}
