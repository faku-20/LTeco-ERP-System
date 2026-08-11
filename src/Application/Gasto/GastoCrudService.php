<?php

declare(strict_types=1);

namespace Lteco\Application\Gasto;

use Lteco\Infrastructure\Repository\GastoCrudRepository;

/**
 * Casos de uso de escritura de gastos (E2): alta y edición. La validación y
 * normalización con helpers globales y la auditoría se conservan en el handler
 * (igual que el CRUD de clientes en la Ola D).
 */
final class GastoCrudService
{
    public function __construct(private GastoCrudRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtener(int $idGasto): ?array
    {
        return $this->repository->buscar($idGasto);
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
    public function editar(int $idGasto, array $datos): void
    {
        $this->repository->editar($idGasto, $datos);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function registrarComisionesVenta(array $datos): void
    {
        if (!$this->repository->tablaDisponible()) {
            return;
        }

        $idVenta = (int) $datos['id_venta'];
        $moneda = (string) $datos['moneda'];

        if ((float) $datos['comision_tarjeta'] > 0) {
            $marcaLabel = trim(
                (string) $datos['marca_tarjeta'] . ' '
                . (string) $datos['tipo_tarjeta']
                . ((int) $datos['cuotas_tarjeta'] > 1 ? ' ' . (int) $datos['cuotas_tarjeta'] . ' cuotas' : '')
            );
            $this->repository->registrarComisionVenta([
                'id_venta' => $idVenta,
                'concepto' => 'Comisión tarjeta - Venta #' . $idVenta . ($marcaLabel ? ' (' . $marcaLabel . ')' : ''),
                'metodo_pago' => 'Tarjeta',
                'moneda' => $moneda,
                'monto' => (float) $datos['comision_tarjeta'],
                'observaciones' => round((float) $datos['comision_tarjeta_pct'], 2)
                    . '% sobre ' . number_format((float) $datos['base_tarjeta'], 2, ',', '.'),
            ]);
        }

        if ((float) $datos['comision_distribuidor'] > 0) {
            $this->repository->registrarComisionVenta([
                'id_venta' => $idVenta,
                'concepto' => 'Comisión distribuidor - Venta #' . $idVenta,
                'metodo_pago' => 'Otro',
                'moneda' => $moneda,
                'monto' => (float) $datos['comision_distribuidor'],
                'observaciones' => round((float) $datos['comision_distribuidor_pct'], 2)
                    . '% sobre ' . number_format((float) $datos['total'], 2, ',', '.'),
            ]);
        }

        if ((float) $datos['comision_vendedor'] > 0) {
            $concepto = (string) $datos['tipo_cliente'] === 'Distribuidor'
                ? 'Comisión interna lteco - Venta distribuidor #' . $idVenta
                : 'Comisión vendedor - Venta directa #' . $idVenta;
            $this->repository->registrarComisionVenta([
                'id_venta' => $idVenta,
                'concepto' => $concepto,
                'metodo_pago' => 'Otro',
                'moneda' => $moneda,
                'monto' => (float) $datos['comision_vendedor'],
                'observaciones' => round((float) $datos['comision_vendedor_pct'], 2)
                    . '% sobre ' . number_format((float) $datos['total'], 2, ',', '.'),
            ]);
        }
    }
}
