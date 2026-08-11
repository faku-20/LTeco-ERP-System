<?php

declare(strict_types=1);

namespace Lteco\Application\Postventa;

use Lteco\Infrastructure\Repository\PostventaRepository;
use RuntimeException;

/**
 * Guardado de una intervención técnica de postventa (registro en el historial
 * técnico + consumo opcional de repuesto con descuento de stock).
 * Origen: la lógica inline de lteco-panel/postventa/intervencion_guardar.php.
 *
 * Sin side effects de HTTP: recibe entradas ya resueltas (estado/técnico/usuario
 * los resuelve el handler) y devuelve el IdHistorialTecnico creado para que el
 * caller registre la auditoría. Las reglas de negocio (estado válido, fechas
 * derivadas, validación de repuesto/stock) viven acá; el SQL vive en el
 * repositorio.
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo dueño
 * (el descuento de stock necesita el FOR UPDATE dentro de SU transacción).
 *
 * Réplica del comportamiento legacy: lanza RuntimeException en los casos que la
 * página original también lanzaba ('Repuesto no encontrado.', 'No hay stock
 * suficiente del repuesto seleccionado.') para que mensajeErrorSeguro muestre el
 * mismo texto y el handler haga rollback.
 */
final class PostventaIntervencionService
{
    /** Estados válidos de una intervención (enum de postventa_historial_tecnico). */
    private const ESTADOS = ['Abierta', 'En reparación', 'En espera', 'Cerrada', 'Cancelada'];

    public function __construct(
        private PostventaRepository $repository,
    ) {}

    /**
     * Registra la intervención y, si corresponde, descuenta el repuesto usado.
     * Devuelve el IdHistorialTecnico recién creado.
     *
     * @param string  $estado            Estado posteado (se normaliza a 'Abierta' si no es válido).
     * @param ?string $tecnico           Técnico ya resuelto por el handler (POST o usuario actual).
     * @param ?int    $idUsuarioRegistra Usuario que registra, ya resuelto (null si 0).
     *
     * @throws RuntimeException si el repuesto no existe o no hay stock suficiente.
     */
    public function guardarIntervencion(
        string $idVehiculo,
        int $idVenta,
        int $idCliente,
        int $idService,
        string $diagnostico,
        ?string $solucion,
        ?string $tecnico,
        string $estado,
        ?string $observaciones,
        int $idRepuesto,
        int $cantidadRepuesto,
        ?int $idUsuarioRegistra
    ): int {
        if (!in_array($estado, self::ESTADOS, true)) {
            $estado = 'Abierta';
        }

        $fechaInicio = in_array($estado, ['En reparación', 'Cerrada'], true) ? date('Y-m-d H:i:s') : null;
        $fechaCierre = in_array($estado, ['Cerrada', 'Cancelada'], true) ? date('Y-m-d H:i:s') : null;

        $idHistorial = $this->repository->insertarIntervencion(
            $idVehiculo,
            $idVenta,
            $idCliente > 0 ? $idCliente : null,
            $idService > 0 ? $idService : null,
            $diagnostico,
            $solucion,
            $tecnico,
            $estado,
            $fechaInicio,
            $fechaCierre,
            $observaciones,
            $idUsuarioRegistra
        );

        if ($idRepuesto > 0 && $cantidadRepuesto > 0) {
            $rep = $this->repository->buscarRepuestoParaConsumo($idRepuesto);
            if ($rep === null) {
                throw new RuntimeException('Repuesto no encontrado.');
            }
            if ((int) $rep['Stock'] < $cantidadRepuesto) {
                throw new RuntimeException('No hay stock suficiente del repuesto seleccionado.');
            }

            if (!$this->repository->descontarStockProducto((int) $rep['IdProducto'], $cantidadRepuesto)) {
                throw new RuntimeException('No hay stock suficiente del repuesto seleccionado.');
            }
            $this->repository->registrarRepuestoUsado(
                $idHistorial,
                $idRepuesto,
                (int) $rep['IdProducto'],
                $cantidadRepuesto,
                (float) $rep['Costo'],
                (string) $rep['Moneda']
            );
        }

        return $idHistorial;
    }
}
