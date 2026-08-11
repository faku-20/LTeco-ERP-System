<?php

declare(strict_types=1);

namespace Lteco\Application\Vehiculo;

use Lteco\Infrastructure\Repository\VehiculoRepository;
use RuntimeException;

/**
 * Gestión de imágenes del catálogo de un vehículo (tabla vehiculo_imagen).
 * Origen: el bloque de acciones POST set_principal / delete_image / move_image
 * de lteco-panel/vehiculos/editar.php. Movido aquí SIN cambios de comportamiento
 * (mismas sentencias, mismo orden, mismos mensajes de error).
 *
 * Sin side effects de HTTP ni de IO de archivos: recibe identificadores ya
 * validados por el handler y opera sólo sobre la DB. El borrado físico del
 * archivo (eliminarArchivoProyecto) y la auditoría/redirect siguen en el
 * handler; por eso eliminar() devuelve la ruta a borrar.
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo el
 * dueño (encadena auditoría y commit/rollback dentro de su transacción).
 *
 * Réplica del comportamiento legacy: las tres acciones exigen que la imagen
 * exista para el vehículo (RuntimeException si no), igual que el fetch único del
 * handler original.
 */
final class VehiculoImagenService
{
    public function __construct(
        private VehiculoRepository $repository,
    ) {}

    /**
     * Marca una imagen como principal: desmarca el resto y marca la elegida.
     */
    public function marcarPrincipal(string $idVehiculo, int $idImagen): void
    {
        $this->exigirImagen($idVehiculo, $idImagen);

        $this->repository->desmarcarImagenesPrincipales($idVehiculo);
        $this->repository->marcarImagenPrincipal($idVehiculo, $idImagen);
    }

    /**
     * Elimina una imagen, recompacta el orden de las restantes (1..n) y reasigna
     * la principal a la primera si se borró la portada. Devuelve la ruta del
     * archivo para que el handler haga el borrado físico tras el commit (o null
     * si no había ruta).
     */
    public function eliminar(string $idVehiculo, int $idImagen): ?string
    {
        $imagen = $this->exigirImagen($idVehiculo, $idImagen);

        $ruta = $imagen['RutaImagen'] ?? null;
        $eraPrincipal = (int) ($imagen['EsPrincipal'] ?? 0) === 1;

        $this->repository->eliminarImagen($idVehiculo, $idImagen);

        $restantes = $this->repository->listarImagenesParaReordenar($idVehiculo);

        $nuevoOrden = 1;
        foreach ($restantes as $imgRestante) {
            $this->repository->actualizarOrdenImagen((int) $imgRestante['IdImagen'], $nuevoOrden++);
        }

        if ($eraPrincipal && $restantes) {
            $this->repository->marcarImagenPrincipalPorId((int) $restantes[0]['IdImagen']);
        }

        return $ruta;
    }

    /**
     * Reordena una imagen una posición hacia 'up' o 'down' intercambiando el
     * OrdenImagen con su vecina. Lanza RuntimeException si ya está en el extremo.
     */
    public function mover(string $idVehiculo, int $idImagen, string $direccion): void
    {
        $imagen = $this->exigirImagen($idVehiculo, $idImagen);

        $direccion = $direccion === 'up' ? 'up' : 'down';
        $ordenActual = (int) ($imagen['OrdenImagen'] ?? 0);

        $vecina = $this->repository->buscarImagenVecina($idVehiculo, $ordenActual, $direccion);

        if (!$vecina) {
            throw new RuntimeException('La imagen ya está en el extremo del orden.');
        }

        $this->repository->actualizarOrdenImagen($idImagen, (int) $vecina['OrdenImagen']);
        $this->repository->actualizarOrdenImagen((int) $vecina['IdImagen'], $ordenActual);
    }

    /**
     * @return array<string, mixed>
     */
    private function exigirImagen(string $idVehiculo, int $idImagen): array
    {
        $imagen = $this->repository->buscarImagenVehiculo($idVehiculo, $idImagen);

        if (!$imagen) {
            throw new RuntimeException('No se encontró esa imagen en este vehículo.');
        }

        return $imagen;
    }
}
