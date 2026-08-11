<?php

declare(strict_types=1);

namespace Lteco\Domain\Venta;

final class ResultadoAnulacion
{
    public function __construct(
        public int $productosRevertidos,
        public int $comisionesAnuladas,
        public int $gastosComisionInactivados,
        public ?string $estadoGastoUsado,
        public int $garantiasAnuladas,
        public int $servicesCancelados
    ) {
    }

    /**
     * @return array<string,int|string|null>
     */
    public function paraAuditoria(): array
    {
        return [
            'productos_revertidos' => $this->productosRevertidos,
            'comisiones_anuladas' => $this->comisionesAnuladas,
            'gastos_comision_inactivados' => $this->gastosComisionInactivados,
            'estado_gasto_usado' => $this->estadoGastoUsado,
            'garantias_anuladas' => $this->garantiasAnuladas,
            'services_cancelados' => $this->servicesCancelados,
        ];
    }
}
