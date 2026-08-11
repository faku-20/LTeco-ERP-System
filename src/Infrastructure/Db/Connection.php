<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Db;

use PDO;
use RuntimeException;

/**
 * Envoltura fina sobre la conexión PDO existente.
 *
 * IMPORTANTE: esta clase NO abre una conexión nueva. Reutiliza el mismo objeto
 * $pdo que crea shared/db.php al incluirse. Su único objetivo en la Fase 0 es
 * permitir inyectar la conexión por constructor en futuros repositorios/servicios
 * sin cambiar el comportamiento actual del sistema.
 */
final class Connection
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Devuelve el PDO subyacente (el mismo $pdo global del sistema).
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Envuelve el $pdo global ya creado por shared/db.php.
     *
     * No crea otra conexión: si $pdo todavía no existe, falla en vez de abrir
     * una nueva, para no duplicar conexiones ni transacciones.
     */
    public static function desdeGlobal(): self
    {
        global $pdo;

        if (!$pdo instanceof PDO) {
            throw new RuntimeException(
                'Connection::desdeGlobal() requiere que shared/db.php ya haya creado $pdo.'
            );
        }

        return new self($pdo);
    }
}
