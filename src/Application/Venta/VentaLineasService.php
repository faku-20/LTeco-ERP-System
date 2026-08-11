<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\PostventaRepository;
use Lteco\Infrastructure\Repository\VehiculoRepository;
use Lteco\Infrastructure\Repository\VentaRepository;
use PDO;
use RuntimeException;

/**
 * Writer compartido de líneas de venta (Wave 1 de la migración POO).
 *
 * Encapsula los writes cross-aggregate que hoy viven inline y DUPLICADOS en
 * lteco-panel/ventas/guardar.php y lteco-panel/distribuidores/nueva_venta.php.
 * Objetivo: una sola fuente para esas escrituras, así ambos caminos de venta no
 * divergen (riesgo señalado en /autoplan: filas de garantía/service/dinero).
 *
 * Comportamiento IDÉNTICO al de guardar.php (líneas ~328-458 y ~518-533):
 *   - Vehículo: detalle + producto('Vendido',0,0,0) + vehiculo(FechaVenta, reserva
 *     limpia) + garantía(Vigente, +12m) + 4 services(Pendiente, +3/6/9/12m), con
 *     guardas de idempotencia por (IdVehiculo, IdVenta).
 *   - Repuesto: detalle + producto(Stock, Estado ya normalizado por el caller).
 *
 * NO escribe gasto: depende del cálculo comercial post-loop; se difiere a la ola
 * de Gastos (decisión E3 de /autoplan).
 *
 * TRANSACTION-AGNOSTIC: reutiliza el $pdo de Connection y NUNCA abre/cierra
 * transacción. El caller (guardar.php / nueva_venta.php) sigue siendo dueño de la
 * transacción, manteniendo la venta atómica.
 */
final class VentaLineasService
{
    private PDO $pdo;
    private VentaPersistenceService $persistence;
    private VehiculoRepository $vehiculos;
    private PostventaRepository $postventa;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
        $this->persistence = new VentaPersistenceService(new VentaRepository($conexion));
        $this->vehiculos = new VehiculoRepository($conexion);
        $this->postventa = new PostventaRepository($conexion);
    }

    /**
     * Registra una línea de moto: detalle + marca producto vendido + marca
     * vehículo vendido + genera garantía y services automáticos.
     *
     * @param array<string,mixed> $linea
     * @return int IdVentaDetalle creado.
     */
    public function registrarVehiculo(array $linea): int
    {
        $idDetalle = $this->registrarDetalleVehiculo($linea);

        $this->aplicarEfectosVehiculo(
            (string) $linea['idVehiculo'],
            (int) $linea['idVenta'],
            (int) $linea['clienteId'],
            (int) $linea['idProducto']
        );

        return $idDetalle;
    }

    /**
     * Venta ecommerce cobrada pero todavía no entregada: registra el detalle y
     * consume el stock, sin iniciar garantía ni calendario de services.
     * La postventa se activa luego con activarPostventaEnEntrega().
     *
     * @param array<string,mixed> $linea
     */
    public function registrarVehiculoPendienteEntrega(array $linea): int
    {
        $idDetalle = $this->registrarDetalleVehiculo($linea);
        $this->vehiculos->marcarVendido((string) $linea['idVehiculo'], (int) $linea['idProducto']);
        return $idDetalle;
    }

    public function activarPostventaEnEntrega(string $idVehiculo, int $idVenta, int $clienteId, ?string $fechaEntrega = null): void
    {
        $this->postventa->generarGarantia($idVehiculo, $idVenta, $clienteId, $fechaEntrega);
        $this->postventa->generarServices($idVehiculo, $idVenta, $clienteId, $fechaEntrega);
    }

    /** @param array<string,mixed> $linea */
    private function registrarDetalleVehiculo(array $linea): int
    {
        return $this->persistence->agregarDetalle([
            'ventaId'        => $linea['idVenta'],
            'productoId'     => $linea['idProducto'],
            'cantidad'       => 1,
            'precioUnitario' => $linea['precioUnitario'],
            'costoUnitario'  => $linea['costoUnitario'],
            'subtotal'       => $linea['subtotal'],
            'gananciaLinea'  => $linea['gananciaLinea'],
            'moneda'         => $linea['moneda'],
        ]);

    }

    /**
     * Efectos post-venta de una moto, SIN la línea de detalle: marca el producto
     * como vendido, marca el vehículo con fecha de venta (y limpia reserva), y
     * genera garantía + 4 services automáticos (idempotentes por IdVehiculo+IdVenta).
     *
     * Es el bloque que estaba duplicado IDÉNTICO en guardar.php y en
     * distribuidores/nueva_venta.php; compartirlo evita que esas filas de
     * garantía/service diverjan entre los dos caminos de venta. Las ventas de
     * distribuidor llaman a este método pero arman su propio detalle (sin Moneda,
     * con su costo) y su propio stock, que difieren del flujo directo.
     */
    public function aplicarEfectosVehiculo(string $idVehiculo, int $idVenta, int $clienteId, int $idProducto): void
    {
        $this->vehiculos->marcarVendido($idVehiculo, $idProducto);
        $this->postventa->generarGarantia($idVehiculo, $idVenta, $clienteId);
        $this->postventa->generarServices($idVehiculo, $idVenta, $clienteId);
    }

    /** @return array<string,mixed>|null */
    public function bloquearVehiculo(string $idVehiculo): ?array
    {
        return $this->persistence->bloquearVehiculo($idVehiculo);
    }

    public function vehiculoTieneFechaVenta(string $idVehiculo): bool
    {
        return $this->persistence->vehiculoTieneFechaVenta($idVehiculo);
    }

    public function productoTieneVentaActiva(int $idProducto): bool
    {
        return $this->persistence->productoTieneVentaActiva($idProducto);
    }

    /** @return array<string,mixed>|null */
    public function bloquearRepuesto(int $idRepuesto): ?array
    {
        return $this->persistence->bloquearRepuesto($idRepuesto);
    }

    /** @return array<string,mixed>|null */
    public function clienteWhatsapp(int $idCliente): ?array
    {
        return $this->persistence->clienteWhatsapp($idCliente);
    }

    public function garantiaFechaFinPorVenta(int $idVenta): ?string
    {
        return $this->persistence->garantiaFechaFinPorVenta($idVenta);
    }

    /** @return list<string> */
    public function serviceFechasProgramadasPorVenta(int $idVenta, int $limite = 3): array
    {
        return $this->persistence->serviceFechasProgramadasPorVenta($idVenta, $limite);
    }

    /**
     * Registra una línea de repuesto: detalle + actualización de stock/estado.
     * El caller calcula nuevoStock/nuevoEstado (normalizarEstadoRepuesto) igual
     * que hoy, para preservar comportamiento.
     *
     * @param array<string,mixed> $linea
     * @return int IdVentaDetalle creado.
     */
    public function registrarRepuesto(array $linea): int
    {
        $idDetalle = $this->persistence->agregarDetalle([
            'ventaId'        => $linea['idVenta'],
            'productoId'     => $linea['idProducto'],
            'cantidad'       => $linea['cantidad'],
            'precioUnitario' => $linea['precioUnitario'],
            'costoUnitario'  => $linea['costoUnitario'],
            'subtotal'       => $linea['subtotal'],
            'gananciaLinea'  => $linea['gananciaLinea'],
            'moneda'         => $linea['moneda'],
        ]);

        $stmt = $this->pdo->prepare('UPDATE producto SET Stock = ?, Estado = ? WHERE IdProducto = ? AND Stock >= ?');
        $stmt->execute([
            (int) $linea['nuevoStock'],
            (string) $linea['nuevoEstado'],
            (int) $linea['idProducto'],
            (int) $linea['cantidad'],
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No hay suficiente stock para completar la venta del repuesto.');
        }

        return $idDetalle;
    }
}
