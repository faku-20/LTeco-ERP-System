<?php

declare(strict_types=1);

namespace Lteco\Application\Repuesto;

use Lteco\Infrastructure\Repository\RepuestoCrudRepository;

final class RepuestoCrudService
{
    public function __construct(private RepuestoCrudRepository $repository)
    {
    }

    /**
     * Importaciones activas para los selects del formulario.
     *
     * @return list<array<string,mixed>>
     */
    public function importacionesActivas(): array
    {
        return $this->repository->importacionesActivas();
    }

    /**
     * Ficha de repuesto+producto por IdProducto.
     *
     * @return array<string,mixed>|null
     */
    public function obtener(int $idProducto): ?array
    {
        return $this->repository->buscarPorProducto($idProducto);
    }

    /**
     * Repuesto+producto por IdRepuesto (para ocultar).
     *
     * @return array<string,mixed>|null
     */
    public function repuestoParaOcultar(int $idRepuesto): ?array
    {
        return $this->repository->buscarPorRepuesto($idRepuesto);
    }

    /**
     * Reglas de negocio puras de validación del repuesto.
     * Preserva exactamente los mensajes legacy de crear/editar.
     *
     * @param array<string,mixed> $datos
     * @return list<string>
     */
    public function validar(array $datos): array
    {
        $errores = [];

        $nombre = (string) ($datos['nombre'] ?? '');
        $precioVenta = (float) ($datos['precio_venta'] ?? 0);
        $precioDistribuidor = $datos['precio_distribuidor'] !== null ? (float) $datos['precio_distribuidor'] : null;
        $stock = (int) ($datos['stock'] ?? 0);
        $estado = (string) ($datos['estado'] ?? '');

        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        }

        $permitePrecioVentaCero = !empty($datos['permitir_precio_venta_cero']);
        if ($precioVenta <= 0 && !$permitePrecioVentaCero) {
            $errores[] = 'El precio de venta debe ser mayor a 0.';
        }

        if ($precioDistribuidor !== null && $precioDistribuidor > 0 && $precioDistribuidor > $precioVenta) {
            $errores[] = 'El precio distribuidor no debería ser mayor al precio de venta.';
        }

        if ($estado === 'Disponible' && $stock <= 0) {
            $errores[] = 'Un repuesto disponible debe tener stock mayor a 0.';
        }

        return $errores;
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos): int
    {
        return $this->repository->crear($datos);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function editar(int $idProducto, array $datos): void
    {
        $this->repository->editar($idProducto, $datos);
    }

    public function ocultar(int $idProducto): void
    {
        $this->repository->ocultar($idProducto);
    }
}
