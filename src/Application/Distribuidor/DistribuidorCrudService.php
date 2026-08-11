<?php

declare(strict_types=1);

namespace Lteco\Application\Distribuidor;

use Lteco\Infrastructure\Repository\DistribuidorCrudRepository;

final class DistribuidorCrudService
{
    public function __construct(private DistribuidorCrudRepository $repository)
    {
    }

    /**
     * @return array<string,string>
     */
    public function formularioNuevo(): array
    {
        return [
            'nombre' => '',
            'comision_pct' => '6.67',
            'contacto' => '',
            'telefono' => '',
            'correo' => '',
            'observaciones' => '',
            'activo' => '1',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtener(int $idDistribuidor): ?array
    {
        return $this->repository->buscar($idDistribuidor);
    }

    /**
     * @param array<string,mixed> $distribuidor
     * @return array<string,string>
     */
    public function formularioDesdeDistribuidor(array $distribuidor): array
    {
        return [
            'nombre' => (string) ($distribuidor['Nombre'] ?? ''),
            'comision_pct' => (string) ($distribuidor['ComisionPct'] ?? '6.67'),
            'contacto' => (string) ($distribuidor['Contacto'] ?? ''),
            'telefono' => (string) ($distribuidor['Telefono'] ?? ''),
            'correo' => (string) ($distribuidor['Correo'] ?? ''),
            'observaciones' => (string) ($distribuidor['Observaciones'] ?? ''),
            'activo' => (string) ($distribuidor['Activo'] ?? '1'),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{form:array<string,string>,errores:list<string>,comision:float}
     */
    public function prepararFormulario(array $input, bool $incluirActivo): array
    {
        $form = [
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'comision_pct' => trim((string) ($input['comision_pct'] ?? '')),
            'contacto' => trim((string) ($input['contacto'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'correo' => trim((string) ($input['correo'] ?? '')),
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'activo' => $incluirActivo && isset($input['activo']) ? '1' : ($incluirActivo ? '0' : '1'),
        ];
        $errores = [];
        if ($form['nombre'] === '') {
            $errores[] = 'El nombre es obligatorio.';
        }
        $comision = (float) str_replace(',', '.', $form['comision_pct']);
        if ($comision < 0 || $comision > 100) {
            $errores[] = 'La comision debe estar entre 0 y 100.';
        }

        return compact('form', 'errores', 'comision');
    }

    /**
     * @param array<string,string> $form
     */
    public function crear(array $form, float $comision): int
    {
        return $this->repository->crear($form, $comision);
    }

    /**
     * @param array<string,string> $form
     */
    public function editar(int $idDistribuidor, array $form, float $comision): void
    {
        $this->repository->editar($idDistribuidor, $form, $comision);
    }
}
