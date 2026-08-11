<?php

declare(strict_types=1);

namespace Lteco\Application\Configuracion;

use Lteco\Infrastructure\Repository\ConfiguracionRepository;

/**
 * Casos de uso de configuración general (F4). El handler conserva permisos,
 * transacción, el "ensure" de columnas/tabla de WhatsApp, la normalización con
 * helpers y la auditoría. Este servicio coordina lecturas y escrituras de
 * configuración y empresa vía repositorio.
 */
final class ConfiguracionService
{
    public function __construct(private ConfiguracionRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerConfiguracion(): ?array
    {
        return $this->repository->obtenerConfiguracion();
    }

    public function crearConfiguracionDefault(): void
    {
        $this->repository->crearConfiguracionDefault();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerEmpresa(): ?array
    {
        return $this->repository->obtenerEmpresa();
    }

    /**
     * @return list<string>
     */
    public function columnasEmpresa(): array
    {
        return $this->repository->columnasEmpresa();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerEmpresaContacto(): ?array
    {
        return $this->repository->obtenerEmpresaContacto();
    }

    /**
     * @param array<string,mixed> $campos
     */
    public function actualizarWhatsapp(int $idConfiguracion, array $campos): void
    {
        $this->repository->actualizarWhatsapp($idConfiguracion, $campos);
    }

    /**
     * @param array<string,mixed> $columnas
     */
    public function actualizarEmpresa(array $columnas, string $rutActual): void
    {
        $this->repository->actualizarEmpresa($columnas, $rutActual);
    }

    /**
     * @param array<string,mixed> $columnas
     */
    public function insertarEmpresa(array $columnas): void
    {
        $this->repository->insertarEmpresa($columnas);
    }

    public function propagarRutProducto(string $rutNuevo, string $rutAnterior): void
    {
        $this->repository->propagarRutProducto($rutNuevo, $rutAnterior);
    }

    /** @param array<string,mixed> $defaults @return array<string,mixed> */
    public function configuracionNegocio(array $defaults): array
    {
        $config = $defaults;
        $rowCfg = $this->repository->obtenerConfiguracion();
        if ($rowCfg) {
            $config['TipoCambioUSD'] = isset($rowCfg['TipoCambioUSD']) ? (float) $rowCfg['TipoCambioUSD'] : $defaults['TipoCambioUSD'];
            if (isset($rowCfg['NombreEmpresa']) && trim((string) $rowCfg['NombreEmpresa']) !== '') {
                $config['NombreEmpresa'] = (string) $rowCfg['NombreEmpresa'];
            }
            if (isset($rowCfg['TextoComprobante']) && trim((string) $rowCfg['TextoComprobante']) !== '') {
                $config['TextoComprobante'] = (string) $rowCfg['TextoComprobante'];
            }
        }

        $rowEmp = $this->repository->obtenerEmpresa();
        if ($rowEmp) {
            foreach ([
                'Nombre' => 'NombreEmpresa',
                'WhatsApp' => 'Whatsapp',
                'Telefono' => 'Telefono',
                'Correo' => 'Correo',
                'Instagram' => 'Instagram',
                'Logo' => 'Logo',
                'Direccion' => 'Direccion',
                'SitioWeb' => 'SitioWeb',
                'PieDocumentos' => 'TextoComprobante',
                'ColorPrimario' => 'ColorPrimario',
                'ColorSecundario' => 'ColorSecundario',
                'PoweredByEnabled' => 'PoweredByEnabled',
            ] as $origen => $destino) {
                if (!empty($rowEmp[$origen])) {
                    $config[$destino] = (string) $rowEmp[$origen];
                }
            }
            if (!empty($rowEmp['Descripcion'])) {
                $config['Descripcion'] = (string) $rowEmp['Descripcion'];
                if ($config['TextoComprobante'] === $defaults['TextoComprobante']) {
                    $config['TextoComprobante'] = (string) $rowEmp['Descripcion'];
                }
            }
        }

        return $config;
    }
}
