<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;
use Throwable;

/**
 * Acceso a datos para el panel de mantenimiento. Por ahora solo el health-check
 * de la base, extraído de configuracion/mantenimiento/index.php sin cambiar el
 * comportamiento: ejecuta el ping y traduce cualquier Throwable a "offline".
 */
final class MantenimientoRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Ping de salud: ejecuta SELECT 1 y devuelve si la base responde.
     *
     * Preserva el try/catch inline legacy: una conexión caída (o cualquier
     * Throwable) se reporta como base offline en vez de propagar el error.
     */
    public function baseDeDatosResponde(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
