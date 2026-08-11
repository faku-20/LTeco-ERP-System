<?php

declare(strict_types=1);

namespace Lteco\Application\Postventa;

use Lteco\Infrastructure\Repository\PostventaRepository;

/**
 * Operaciones de postventa sobre un service (realizar / cancelar / observación /
 * marcar notificado WA). Origen: lteco-panel/vehiculos/service_realizar.php y
 * lteco-panel/postventa/{service_cancelar,service_observacion_agregar,
 * marcar_notificado_wa}.php.
 *
 * Sin side effects de HTTP: recibe entradas resueltas (incl. el nombre de usuario
 * para el historial) y devuelve un resultado; el handler decide redirect/flash.
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el caller sigue siendo dueño.
 */
final class PostventaService
{
    public function __construct(
        private PostventaRepository $repository,
    ) {}

    /**
     * Valida garantía/estado/venta y marca el service como Realizado, registrando
     * el historial. Réplica de la lógica inline de service_realizar.php.
     *
     * @return array{success: bool, mensaje: string}
     */
    public function realizarService(int $idService, string $idVehiculo, string $observaciones, string $usuario): array
    {
        $validacion = $this->repository->buscarServiceParaRealizar($idService, $idVehiculo);

        if ($validacion === null) {
            return ['success' => false, 'mensaje' => 'Service no encontrado.'];
        }

        if (($validacion['EstadoVenta'] ?? 'Confirmada') === 'Anulada') {
            return ['success' => false, 'mensaje' => 'No se puede realizar el service: la venta asociada está anulada.'];
        }

        if (!in_array((string) ($validacion['EstadoService'] ?? ''), ['Pendiente', 'Vencido'], true)) {
            return ['success' => false, 'mensaje' => 'No se puede realizar el service: ya está realizado o cancelado.'];
        }

        if (in_array((string) ($validacion['EstadoGarantia'] ?? ''), ['Vencida', 'Anulada', 'Cancelada'], true)) {
            return ['success' => false, 'mensaje' => 'No se puede realizar el service: la garantía no está vigente.'];
        }

        $numeroService = $this->repository->obtenerNumeroService($idService, $idVehiculo);
        if ($numeroService !== null && $numeroService > 1) {
            if ($this->repository->contarServicesPreviosPendientes($idVehiculo, $numeroService) > 0) {
                return ['success' => false, 'mensaje' => 'Hay un service anterior pendiente de realizar. Los services deben completarse en orden.'];
            }
        }

        $filas = $this->repository->marcarServiceRealizado(
            $idService,
            $idVehiculo,
            $observaciones !== '' ? $observaciones : 'Service realizado.'
        );

        if ($filas < 1) {
            return ['success' => false, 'mensaje' => 'No se actualizó el service. Puede que ya esté realizado o cancelado.'];
        }

        $detalleHistorial = $observaciones !== '' ? $observaciones : 'Service realizado.';
        $this->repository->registrarHistorial($idService, $idVehiculo, 'REALIZADO', $detalleHistorial, $usuario);

        return ['success' => true, 'mensaje' => 'Service marcado como realizado.'];
    }

    /**
     * Cancela un service (sólo Pendiente/Vencido) anexando el motivo y registra el
     * historial. Réplica de la lógica inline de postventa/service_cancelar.php.
     *
     * @return array{success: bool, mensaje: string}
     */
    public function cancelarService(int $idService, string $idVehiculo, string $motivo, string $usuario): array
    {
        $filas = $this->repository->cancelarService($idService, $idVehiculo, $motivo);

        if ($filas < 1) {
            return ['success' => false, 'mensaje' => 'No se canceló el service. Puede que ya esté realizado o cancelado.'];
        }

        $this->repository->registrarHistorial($idService, $idVehiculo, 'CANCELADO', $motivo, $usuario);

        return ['success' => true, 'mensaje' => 'Service cancelado correctamente.'];
    }

    /**
     * Anexa una nota técnica al service (sin cambiar estado) y registra el
     * historial. Réplica de postventa/service_observacion_agregar.php.
     *
     * @return array{success: bool, mensaje: string}
     */
    public function agregarObservacion(int $idService, string $idVehiculo, string $nota, string $usuario): array
    {
        $filas = $this->repository->agregarObservacionService($idService, $idVehiculo, $nota);

        if ($filas < 1) {
            return ['success' => false, 'mensaje' => 'No se actualizó la observación. Verificá el service.'];
        }

        $this->repository->registrarHistorial($idService, $idVehiculo, 'NOTA', $nota, $usuario);

        return ['success' => true, 'mensaje' => 'Observación técnica agregada.'];
    }

    /**
     * Batch "cron" que corre al cargar postventa: vence garantías/services pasados,
     * corrige services con fecha movida a futuro y auto-cancela los muy vencidos.
     * Réplica de la lógica inline de postventa/actualizar_estados.php, respetando el
     * mismo orden (garantías antes que services).
     *
     * @param int  $idUsuarioVendedor >0 => scoped a las ventas de ese vendedor; 0 => global.
     * @param bool $incluirGarantias  El handler sólo lo activa si existe la tabla garantia.
     */
    public function actualizarEstados(int $idUsuarioVendedor, bool $incluirGarantias): void
    {
        if ($incluirGarantias) {
            $this->repository->marcarGarantiasVencidas($idUsuarioVendedor);
        }

        $this->repository->actualizarEstadosServices($idUsuarioVendedor);
    }

    /** @return array{proximos:list<array<string,mixed>>,vencidos:list<array<string,mixed>>} */
    public function servicesParaNotificar(): array
    {
        return [
            'proximos' => $this->repository->servicesProximos(),
            'vencidos' => $this->repository->servicesVencidosParaNotificar(),
        ];
    }

    public function vendedorPuedeIntervenir(string $idVehiculo, int $idVenta, int $idUsuario): bool
    {
        return $this->repository->vendedorPuedeIntervenir($idVehiculo, $idVenta, $idUsuario);
    }

    /**
     * Registra en el historial el envío del recordatorio WhatsApp.
     * Réplica de postventa/marcar_notificado_wa.php (mismo INSERT ... SELECT).
     */
    public function marcarNotificadoWa(int $idService, string $idVehiculo, string $usuario): void
    {
        $this->repository->registrarHistorial(
            $idService,
            $idVehiculo,
            'NOTIFICACION_WA',
            'Recordatorio WhatsApp enviado',
            $usuario
        );
    }
}
