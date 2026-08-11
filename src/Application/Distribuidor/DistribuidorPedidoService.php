<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use Lteco\Infrastructure\Repository\DistribuidorPedidoRepository;
use RuntimeException;

/**
 * Registro de un pedido de stock de distribuidor procesado automáticamente
 * (auto-aprobado): crea el pedido, mueve stock interno → distribuidor y, si
 * corresponde, genera el remito pendiente.
 *
 * Origen: el núcleo de escritura del POST de
 * lteco-panel/distribuidores/nuevo_pedido.php. Movido aquí SIN cambios de
 * comportamiento (mismo orden de efectos, misma resolución de precioBase, mismo
 * mensaje de stock insuficiente).
 *
 * Reutiliza DistribuidorStockService::asignarStock() (bloque C1.1) para el
 * movimiento de stock (upsert distribuidor_stock + descuento de producto +
 * 'Sin stock'), que es idéntico al de asignar_stock.php.
 *
 * Sin side effects de HTTP: recibe entradas YA validadas/normalizadas por el
 * handler (distribuidor, tipo de ítem, cantidad, observaciones ya limpiadas,
 * usuario y el flag de existencia de la tabla remito).
 *
 * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo el
 * dueño (encadena la auditoría dentro de su transacción). El lock FOR UPDATE del
 * repositorio sólo tiene efecto dentro de esa transacción.
 */
final class DistribuidorPedidoService
{
    public function __construct(
        private DistribuidorPedidoRepository $pedidos,
        private DistribuidorStockService $stock,
    ) {}

    /**
     * @return array<string,mixed>|null
     */
    public function buscarPendiente(int $idPedido): ?array
    {
        return $this->pedidos->buscarPendiente($idPedido);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPendientes(): array
    {
        return $this->pedidos->listarPendientes();
    }

    /** @return array{distribuidores:list<array<string,mixed>>,vehiculos:list<array<string,mixed>>,repuestos:list<array<string,mixed>>} */
    public function datosFormularioPedido(bool $incluirDistribuidores): array
    {
        return [
            'distribuidores' => $incluirDistribuidores ? $this->pedidos->distribuidoresActivos() : [],
            'vehiculos' => $this->pedidos->vehiculosSolicitables(),
            'repuestos' => $this->pedidos->repuestosSolicitables(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function itemSolicitable(string $tipoItem, ?string $idVehiculo, ?int $idRepuesto): ?array
    {
        return $this->pedidos->itemSolicitable($tipoItem, $idVehiculo, $idRepuesto);
    }

    public function vehiculoYaSolicitado(?string $idVehiculo): bool
    {
        return $this->pedidos->vehiculoYaSolicitado($idVehiculo);
    }

    /**
     * Registra el pedido auto-aprobado y devuelve su IdPedido.
     *
     * Réplica del flujo legacy: bloquea el producto, valida stock, resuelve
     * precioBase, inserta el pedido 'Aprobado', asigna el stock al distribuidor
     * y crea el remito si $remitoDisponible.
     */
    public function registrarPedidoAprobado(
        int $idDistribuidor,
        string $tipoItem,
        ?string $idVehiculo,
        ?int $idRepuesto,
        int $cantidad,
        ?string $observaciones,
        int $idUsuario,
        bool $remitoDisponible
    ): int {
        $lock = $tipoItem === 'Vehiculo'
            ? $this->pedidos->bloquearProductoPorVehiculo($idVehiculo)
            : $this->pedidos->bloquearProductoPorRepuesto($idRepuesto);

        if (!$lock || (int) $lock['Stock'] < $cantidad) {
            throw new RuntimeException('Stock insuficiente al momento de procesar el pedido.');
        }

        $precioBase = (float) ($lock['PrecioDistribuidor'] ?? 0) > 0
            ? (float) $lock['PrecioDistribuidor']
            : (float) $lock['PrecioVenta'];

        $idPedido = $this->pedidos->crearPedidoAprobado(
            $idDistribuidor,
            $tipoItem,
            $idVehiculo,
            $idRepuesto,
            $cantidad,
            $observaciones,
            $idUsuario
        );

        $this->stock->asignarStock(
            $idDistribuidor,
            $tipoItem,
            $idVehiculo,
            $idRepuesto,
            $cantidad,
            $precioBase,
            $precioBase,
            (int) $lock['IdProducto'],
            (int) $lock['Stock']
        );

        if ($remitoDisponible) {
            $this->pedidos->crearRemitoPendiente($idDistribuidor, $idPedido, $tipoItem, $idVehiculo, $idRepuesto, $cantidad);
        }

        return $idPedido;
    }

    /**
     * Resuelve (aprueba o rechaza) un pedido pendiente YA existente y devuelve el
     * nuevo Estado ('Aprobado' | 'Rechazado').
     *
     * Origen: el núcleo de escritura del POST de
     * lteco-panel/distribuidores/pedidos_admin.php. Movido aquí SIN cambios de
     * comportamiento (mismo orden de efectos, mismos mensajes de error, misma
     * resolución de precioBase).
     *
     * Para 'aprobar': bloquea el producto (FOR UPDATE), valida que exista en stock
     * interno y que alcance la cantidad (mensajes propios, distintos a los de
     * registrarPedidoAprobado()), resuelve precioBase, mueve stock interno→
     * distribuidor vía DistribuidorStockService::asignarStock() (upsert + descuento
     * de producto + 'Sin stock' si se agota) y crea el remito 'Pendiente' si
     * $remitoDisponible. Para ambas acciones, marca el pedido como resuelto.
     *
     * TRANSACTION-AGNOSTIC: no abre/cierra transacción; el handler sigue siendo el
     * dueño (encadena la auditoría dentro de su transacción). El lock FOR UPDATE
     * sólo tiene efecto dentro de esa transacción.
     */
    public function resolverPedido(
        int $idPedido,
        string $accion,
        int $idDistribuidor,
        string $tipoItem,
        ?string $idVehiculo,
        ?int $idRepuesto,
        int $cantidad,
        bool $remitoDisponible
    ): string {
        if ($accion === 'aprobar') {
            $lock = $tipoItem === 'Vehiculo'
                ? $this->pedidos->bloquearProductoPorVehiculo($idVehiculo)
                : $this->pedidos->bloquearProductoPorRepuesto($idRepuesto);

            if (!$lock) {
                throw new RuntimeException('El producto del pedido no fue encontrado en stock interno.');
            }
            if ((int) $lock['Stock'] < $cantidad) {
                throw new RuntimeException('No hay stock interno suficiente para aprobar el pedido (disponible: ' . (int) $lock['Stock'] . ', solicitado: ' . $cantidad . ').');
            }

            $precioBase = (float) ($lock['PrecioDistribuidor'] ?? 0) > 0
                ? (float) $lock['PrecioDistribuidor']
                : (float) $lock['PrecioVenta'];

            $this->stock->asignarStock(
                $idDistribuidor,
                $tipoItem,
                $idVehiculo,
                $idRepuesto,
                $cantidad,
                $precioBase,
                $precioBase,
                (int) $lock['IdProducto'],
                (int) $lock['Stock']
            );

            if ($remitoDisponible) {
                $this->pedidos->crearRemitoPendiente($idDistribuidor, $idPedido, $tipoItem, $idVehiculo, $idRepuesto, $cantidad);
            }
        }

        $nuevoEstado = $accion === 'aprobar' ? 'Aprobado' : 'Rechazado';
        $this->pedidos->marcarEstadoResuelto($idPedido, $nuevoEstado);

        return $nuevoEstado;
    }
}
