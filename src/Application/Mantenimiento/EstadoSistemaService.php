<?php

declare(strict_types=1);

namespace Lteco\Application\Mantenimiento;

use Lteco\Infrastructure\Repository\MantenimientoRepository;

/**
 * Estado básico de infraestructura para el panel de mantenimiento.
 *
 * Orquesta el health-check de MariaDB delegando el SQL al repositorio. La vista
 * solo pregunta "¿está online?" y recibe un booleano, sin tocar $pdo ni SQL.
 */
final class EstadoSistemaService
{
    public function __construct(private MantenimientoRepository $repositorio)
    {
    }

    /**
     * ¿MariaDB está online? (health-check SELECT 1).
     */
    public function mariadbOnline(): bool
    {
        return $this->repositorio->baseDeDatosResponde();
    }
}
