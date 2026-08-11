<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use Lteco\Application\Venta\VentaLineasService;
use Lteco\Domain\Distribuidor\VentaDistribuidorCalculo;
use Lteco\Infrastructure\Repository\DistribuidorVentaRepository;
use RuntimeException;

/**
 * Orquesta el núcleo C2 de nueva_venta.php sin administrar la transacción.
 *
 * Auditoría, permisos, transacción y HTTP permanecen en el handler.
 */
final class DistribuidorVentaService
{
    public function __construct(
        private DistribuidorVentaRepository $repository,
        private VentaLineasService $ventaLineas,
    ) {}

    /** @return list<array<string,mixed>> */
    public function stockDisponible(int $idDistribuidor): array
    {
        return $this->repository->stockDisponible($idDistribuidor);
    }

    /**
     * Bloquea y valida el stock antes del find-or-create de cliente, preservando
     * el orden del flujo legacy.
     *
     * @return array<string,mixed>
     */
    public function prepararVenta(int $idStock, int $idDistribuidor, int $cantidad): array
    {
        $item = $this->repository->bloquearStock($idStock, $idDistribuidor);

        if (!$item || (int) $item['Cantidad'] <= 0) {
            throw new RuntimeException('El producto seleccionado no está disponible en tu stock.');
        }
        if ($cantidad > (int) $item['Cantidad']) {
            throw new RuntimeException('No tenés suficiente stock. Disponible: ' . (int) $item['Cantidad'] . '.');
        }

        $precioVenta = isset($item['PrecioVenta']) ? (float) $item['PrecioVenta'] : 0.0;
        $precioMinimo = isset($item['PrecioMinimo']) ? (float) $item['PrecioMinimo'] : 0.0;

        if ($precioVenta <= 0) {
            throw new RuntimeException('El producto seleccionado no tiene precio de venta configurado.');
        }
        if ($precioMinimo > 0 && $precioVenta < $precioMinimo - 0.001) {
            throw new RuntimeException(
                'El precio del sistema no puede ser menor al mínimo ($ '
                . number_format($precioMinimo, 2, ',', '.')
                . ').'
            );
        }

        return [
            'idStock' => $idStock,
            'idDistribuidor' => $idDistribuidor,
            'cantidad' => $cantidad,
            'item' => $item,
            'precioVenta' => $precioVenta,
            'precioMinimo' => $precioMinimo,
        ];
    }

    /**
     * @param array<string,mixed> $preparada
     * @return array<string,mixed>
     */
    public function registrarVenta(
        array $preparada,
        int $clienteId,
        string $metodoPago,
        int $idUsuarioComisionDistribuidor,
        ?string $observaciones,
        float $comisionVendedorPct,
        float $tasaIVA
    ): array {
        $item = (array) $preparada['item'];
        $tipoItem = (string) $item['TipoItem'];
        $idVehiculo = $tipoItem === 'Vehiculo' ? (string) $item['IdVehiculo'] : null;
        $idRepuesto = $tipoItem === 'Repuesto' ? (int) $item['IdRepuesto'] : null;

        $idProducto = $tipoItem === 'Vehiculo'
            ? $this->repository->buscarIdProductoVehiculo($idVehiculo)
            : $this->repository->buscarIdProductoRepuesto($idRepuesto);

        if ($idProducto === null) {
            throw new RuntimeException('No se pudo identificar el producto en el catálogo interno.');
        }

        $idDistribuidor = (int) $preparada['idDistribuidor'];
        $cantidad = (int) $preparada['cantidad'];
        $precioVenta = (float) $preparada['precioVenta'];
        $precioMinimo = (float) $preparada['precioMinimo'];
        $comisionDistribuidorPct = $this->repository->obtenerComisionDistribuidorPct($idDistribuidor);

        $calculo = VentaDistribuidorCalculo::calcular(
            $precioVenta,
            $precioMinimo,
            $cantidad,
            $comisionDistribuidorPct,
            $comisionVendedorPct,
            $tasaIVA
        );

        $idVenta = $this->repository->crearVenta(
            $clienteId,
            $metodoPago,
            $idDistribuidor,
            $idUsuarioComisionDistribuidor,
            $observaciones,
            $calculo
        );
        $this->repository->crearDetalle(
            $idVenta,
            $idProducto,
            $cantidad,
            $precioVenta,
            $precioMinimo,
            $calculo['subtotal'],
            $calculo['ganancia']
        );

        if (!$this->repository->descontarStockDistribuidor((int) $preparada['idStock'], $cantidad)) {
            throw new RuntimeException('No se pudo descontar el stock del producto.');
        }

        if ($tipoItem === 'Vehiculo' && $idVehiculo !== null && $idVehiculo !== '') {
            $this->ventaLineas->aplicarEfectosVehiculo($idVehiculo, $idVenta, $clienteId, $idProducto);
        }

        return [
            ...$calculo,
            'idVenta' => $idVenta,
            'idDistribuidor' => $idDistribuidor,
            'idStock' => (int) $preparada['idStock'],
            'cantidad' => $cantidad,
            'tipoItem' => $tipoItem,
            'idVehiculo' => $idVehiculo,
            'idRepuesto' => $idRepuesto,
            'idProducto' => $idProducto,
            'precioVenta' => $precioVenta,
            'precioMinimo' => $precioMinimo,
            'comisionDistribuidorPct' => $comisionDistribuidorPct,
        ];
    }

    /**
     * @param array<string,mixed> $venta
     */
    public function facturarRemito(array $venta, ?string $numeroFactura, bool $remitoDisponible): void
    {
        if (!$remitoDisponible) {
            return;
        }

        if ($venta['tipoItem'] === 'Vehiculo') {
            $this->repository->facturarRemitoVehiculo(
                (int) $venta['idDistribuidor'],
                (string) $venta['idVehiculo'],
                (int) $venta['idVenta'],
                $numeroFactura
            );
            return;
        }

        $this->repository->facturarRemitoRepuesto(
            (int) $venta['idDistribuidor'],
            (int) $venta['idRepuesto'],
            (int) $venta['idVenta'],
            $numeroFactura
        );
    }

    /**
     * @param array<string,mixed> $venta
     */
    public function registrarComisiones(
        array $venta,
        float $comisionVendedorPct,
        bool $tablaComisionDisponible,
        bool $tablaGastoDisponible
    ): void {
        $idVenta = (int) $venta['idVenta'];
        $idDistribuidor = (int) $venta['idDistribuidor'];
        $subtotal = (float) $venta['subtotal'];
        $porcentajeDistribuidor = (float) $venta['comisionDistribuidorPct'];
        $montoDistribuidor = round($subtotal * max(0.0, $porcentajeDistribuidor) / 100, 2);

        if ($tablaComisionDisponible) {
            $this->repository->registrarComisionDistribuidor(
                $idDistribuidor,
                $idVenta,
                $subtotal,
                $porcentajeDistribuidor,
                $montoDistribuidor
            );
        }

        if ($tablaGastoDisponible && $montoDistribuidor > 0) {
            $nombre = $this->repository->nombreDistribuidor($idDistribuidor);
            $this->repository->crearGastoComision(
                $idVenta,
                'Comisión distribuidor - ' . $nombre . ' - Venta #' . $idVenta,
                $montoDistribuidor,
                round($porcentajeDistribuidor, 2) . '% sobre ' . number_format($subtotal, 2, ',', '.')
            );
        }

        $montoVendedor = (float) $venta['comisionVendedor'];
        if ($tablaGastoDisponible && $montoVendedor > 0) {
            $this->repository->crearGastoComision(
                $idVenta,
                'Comisión interna lteco - Venta distribuidor #' . $idVenta,
                $montoVendedor,
                round($comisionVendedorPct, 2) . '% sobre ' . number_format($subtotal, 2, ',', '.')
            );
        }
    }
}
