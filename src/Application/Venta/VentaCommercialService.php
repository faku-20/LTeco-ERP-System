<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

use Lteco\Domain\Venta\ConfiguracionComercial;
use Lteco\Infrastructure\Repository\VentaCommercialRepository;
use RuntimeException;

final class VentaCommercialService
{
    public function __construct(private VentaCommercialRepository $repository)
    {
    }

    /**
     * @return array<string,float>
     */
    public function configuracion(float $tasaIvaDefault): array
    {
        return ConfiguracionComercial::normalizar(
            $this->repository->configuracionActual(),
            $tasaIvaDefault
        );
    }

    /**
     * @return array<string,float>
     */
    public function configuracionFormulario(float $tasaIvaDefault): array
    {
        return ConfiguracionComercial::paraFormulario(
            $this->repository->configuracionActual(),
            $tasaIvaDefault
        );
    }

    public function comisionVendedor(int $idUsuario): float
    {
        if ($idUsuario <= 0) {
            return 0.0;
        }

        return ConfiguracionComercial::comisionNoNegativa(
            $this->repository->comisionUsuario($idUsuario)
        );
    }

    public function comisionDistribuidor(int $idDistribuidor, float $default): float
    {
        if ($idDistribuidor <= 0) {
            return $default;
        }

        $row = $this->repository->comisionDistribuidorActivo($idDistribuidor);
        if ($row === null) {
            return $default;
        }

        return ConfiguracionComercial::comisionNoNegativa($row['ComisionPct'] ?? null);
    }

    /**
     * @return array{IdUsuario:int,ComisionDistribuidorPct:float}
     */
    public function usuarioInternoDistribuidor(string $rolDistribuidor): array
    {
        $row = $this->repository->usuarioInternoDistribuidor($rolDistribuidor);
        if ($row === null) {
            throw new RuntimeException(
                'No hay usuario configurado para la comisión interna por venta de distribuidor. ' .
                'Configurá el usuario en Configuración > Usuario comisión distribuidores.'
            );
        }

        return [
            'IdUsuario' => (int) $row['IdUsuario'],
            'ComisionDistribuidorPct' => ConfiguracionComercial::comisionNoNegativa(
                $row['ComisionDistribuidorPct'] ?? null
            ),
        ];
    }
}
