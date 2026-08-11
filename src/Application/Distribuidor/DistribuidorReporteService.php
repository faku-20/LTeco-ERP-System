<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use InvalidArgumentException;
use Lteco\Infrastructure\Repository\DistribuidorReporteRepository;

/**
 * Casos de uso C4 para reportes de problemas de distribuidores.
 */
final class DistribuidorReporteService
{
    private const ESTADOS_VALIDOS = ['Nuevo', 'Revisado', 'Resuelto'];

    public function __construct(private DistribuidorReporteRepository $repository)
    {
    }

    public function estaDisponible(): bool
    {
        return $this->repository->tablaDisponible();
    }

    public function validarMensaje(string $mensaje): string
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            throw new InvalidArgumentException('El mensaje es obligatorio.');
        }
        if (mb_strlen($mensaje) > 3000) {
            throw new InvalidArgumentException('El mensaje no puede superar los 3000 caracteres.');
        }

        return $mensaje;
    }

    public function crearReporte(
        int $idDistribuidor,
        int $idUsuario,
        string $mensaje,
        ?string $imagenRuta
    ): int {
        return $this->repository->crear(
            $idDistribuidor,
            $idUsuario,
            $this->validarMensaje($mensaje),
            $imagenRuta
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerReporte(int $idReporte): ?array
    {
        return $this->repository->buscarPorId($idReporte);
    }

    public function actualizarEstado(
        int $idReporte,
        string $nuevoEstado,
        int $idUsuarioActual
    ): void {
        $nuevoEstado = trim($nuevoEstado);
        if (!in_array($nuevoEstado, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException('Estado no válido.');
        }

        $usuarioResolucionId = $nuevoEstado === 'Resuelto' ? $idUsuarioActual : null;
        $this->repository->actualizarEstado($idReporte, $nuevoEstado, $usuarioResolucionId);
    }
}
