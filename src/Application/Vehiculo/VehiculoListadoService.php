<?php

declare(strict_types=1);

namespace Lteco\Application\Vehiculo;

use Lteco\Infrastructure\Repository\VehiculoListadoRepository;

/**
 * Caso de uso de LECTURA del listado de vehículos (vehiculos/index.php).
 *
 * Recibe los filtros ya leídos del request y el flag de gestión de catálogo web
 * (derivado del rol en la página) y devuelve las filas. Sin HTML, sin $_GET, sin
 * echo. La agregación de presentación ($resumen, issues, badges) queda en la vista
 * porque usa helpers compartidos de UI.
 */
final class VehiculoListadoService
{
    public function __construct(private VehiculoListadoRepository $repo)
    {
    }

    /**
     * @param array{estado?:string,web?:string,destacado?:string,q?:string} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros, bool $puedeGestionarCatalogoWeb): array
    {
        return $this->repo->listar(
            (string) ($filtros['estado'] ?? ''),
            (string) ($filtros['web'] ?? ''),
            (string) ($filtros['destacado'] ?? ''),
            (string) ($filtros['q'] ?? ''),
            $puedeGestionarCatalogoWeb
        );
    }
}
