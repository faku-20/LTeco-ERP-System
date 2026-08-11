<?php

declare(strict_types=1);

namespace Lteco\Application\Vehiculo;

use Lteco\Infrastructure\Repository\VehiculoRepository;
use RuntimeException;

final class VehiculoEstadoService
{
    public function __construct(
        private VehiculoRepository $repository,
    ) {}

    /** @return array<string,mixed>|null */
    public function datosPublicacion(string $idVehiculo): ?array
    {
        return $this->repository->datosPublicacion($idVehiculo);
    }

    /** @return array<string,mixed>|null */
    public function datosEstado(string $idVehiculo): ?array
    {
        return $this->repository->datosEstado($idVehiculo);
    }

    /** @return array<string,mixed>|null */
    public function datosOcultar(string $idVehiculo): ?array
    {
        return $this->repository->datosOcultar($idVehiculo);
    }

    /** @return array<string,mixed>|null */
    public function datosReserva(string $idVehiculo): ?array
    {
        return $this->repository->datosReserva($idVehiculo);
    }

    /**
     * @param array<string, mixed> $data Fila del vehículo con datos de producto.
     * @return array{success: bool, mensaje: string, accion?: string, faltantes?: list<string>, nuevoValor?: int}
     */
    public function toggleDestacado(array $data): array
    {
        $nuevoValor = (int) !$data['DestacadoWeb'];

        if ($nuevoValor === 1) {
            $analisis = \vehiculoAnalizarPublicacion($data);
            if (!$analisis['puede_destacarse']) {
                return [
                    'success' => false,
                    'mensaje' => $analisis['motivo_destacado'],
                    'accion' => 'toggle_destacado',
                    'faltantes' => $analisis['faltantes'] ?? [],
                ];
            }
        }

        $this->repository->setDestacadoWeb((int) $data['IdProducto'], $nuevoValor);

        $mensaje = $nuevoValor === 1
            ? 'Vehículo marcado como destacado en la web.'
            : 'Vehículo quitado de destacados.';

        return [
            'success' => true,
            'mensaje' => $mensaje,
            'nuevoValor' => $nuevoValor,
        ];
    }

    /**
     * Alterna la visibilidad web del vehículo. Origen: vehiculos/toggle_web.php.
     *
     * @param array<string, mixed> $data Fila del vehículo con datos de producto.
     * @return array{success: bool, mensaje: string, accion?: string, faltantes?: list<string>, nuevoValor?: int}
     */
    public function toggleWeb(array $data): array
    {
        $activar = (int) !$data['MostrarEnWeb'];

        if ($activar === 1) {
            $analisis = \vehiculoAnalizarPublicacion($data);
            if (!$analisis['publicable']) {
                return [
                    'success' => false,
                    'mensaje' => $analisis['motivo_publicacion'],
                    'accion' => 'toggle_web',
                    'faltantes' => $analisis['faltantes'],
                ];
            }
        }

        $publicacion = \vehiculoNormalizarPublicacion(
            (string) $data['Estado'],
            (int) $data['Stock'],
            $activar,
            (int) $data['DestacadoWeb']
        );

        $this->repository->setPublicacionWeb(
            (int) $data['IdProducto'],
            $publicacion['MostrarEnWeb'],
            $publicacion['DestacadoWeb']
        );

        $mensaje = $activar === 1
            ? 'Vehículo publicado correctamente en la web.'
            : 'Vehículo ocultado de la web correctamente.';

        return [
            'success' => true,
            'mensaje' => $mensaje,
            'nuevoValor' => $activar,
        ];
    }

    /**
     * Aplica un cambio de estado al producto y sincroniza el vehículo.
     * NO abre transacción: el caller (cambiar_estado.php) es el dueño.
     * Origen: vehiculos/cambiar_estado.php.
     *
     * @param array<string, mixed> $vehiculo Fila con IdProducto, MostrarEnWeb, DestacadoWeb.
     * @return array{mensaje: string, stock: int, mostrarEnWeb: int, destacadoWeb: int}
     */
    public function cambiarEstado(string $idVehiculo, array $vehiculo, string $nuevoEstado): array
    {
        if ($nuevoEstado === 'Vendido') {
            throw new RuntimeException('El estado Vendido sólo puede originarse desde una venta registrada.');
        }

        $stock = \stockSegunEstadoMoto($nuevoEstado);
        $publicacion = \vehiculoNormalizarPublicacion(
            $nuevoEstado,
            $stock,
            (int) $vehiculo['MostrarEnWeb'],
            (int) $vehiculo['DestacadoWeb']
        );

        $this->repository->actualizarEstadoYPublicacion(
            (int) $vehiculo['IdProducto'],
            $nuevoEstado,
            $stock,
            $publicacion['MostrarEnWeb'],
            $publicacion['DestacadoWeb']
        );
        $this->repository->sincronizarVehiculoSegunEstado($idVehiculo, $nuevoEstado);

        return [
            'mensaje' => 'Estado actualizado correctamente.',
            'stock' => $stock,
            'mostrarEnWeb' => $publicacion['MostrarEnWeb'],
            'destacadoWeb' => $publicacion['DestacadoWeb'],
        ];
    }

    /**
     * Reserva el vehículo: estado 'Reservado' + datos de reserva.
     * NO abre transacción: el caller (reservar.php) es el dueño.
     * Origen: vehiculos/reservar.php.
     *
     * @param array<string, mixed> $vehiculo Fila con IdProducto, MostrarEnWeb, DestacadoWeb.
     * @return array{mensaje: string, stock: int, mostrarEnWeb: int, destacadoWeb: int}
     */
    public function reservar(string $idVehiculo, array $vehiculo, int $clienteReservaId, ?float $senia): array
    {
        $stock = \stockSegunEstadoMoto('Reservado');
        $publicacion = \vehiculoNormalizarPublicacion(
            'Reservado',
            $stock,
            (int) $vehiculo['MostrarEnWeb'],
            (int) $vehiculo['DestacadoWeb']
        );

        $this->repository->actualizarEstadoYPublicacion(
            (int) $vehiculo['IdProducto'],
            'Reservado',
            $stock,
            $publicacion['MostrarEnWeb'],
            $publicacion['DestacadoWeb']
        );
        $this->repository->guardarReserva($idVehiculo, $clienteReservaId, $senia);

        return [
            'mensaje' => 'Reserva guardada correctamente.',
            'stock' => $stock,
            'mostrarEnWeb' => $publicacion['MostrarEnWeb'],
            'destacadoWeb' => $publicacion['DestacadoWeb'],
        ];
    }

    /**
     * Oculta (soft-delete) el producto del vehículo.
     * Origen: vehiculos/eliminar.php. Sin reglas de negocio: delega en el repo.
     */
    public function ocultar(int $idProducto): void
    {
        $this->repository->ocultar($idProducto);
    }

    /**
     * Intercambia el OrdenWeb con el vehículo vecino. La transacción pertenece
     * al handler para preservar la composición con auditoría y redirect.
     */
    public function moverOrdenWeb(string $idVehiculo, string $direccion): bool
    {
        $actual = $this->repository->productoOrdenWeb($idVehiculo);
        if (!$actual) {
            throw new RuntimeException('Vehículo no encontrado.');
        }

        $ordenActual = (int) ($actual['OrdenWeb'] ?? 0);
        $vecino = $this->repository->productoOrdenWebVecino($ordenActual, $direccion);
        if (!$vecino) {
            return false;
        }

        $this->repository->actualizarOrdenWeb((int) $actual['IdProducto'], 999999);
        $this->repository->actualizarOrdenWeb((int) $vecino['IdProducto'], $ordenActual);
        $this->repository->actualizarOrdenWeb((int) $actual['IdProducto'], (int) $vecino['OrdenWeb']);

        return true;
    }
}
