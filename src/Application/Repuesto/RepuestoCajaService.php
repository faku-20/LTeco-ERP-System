<?php

declare(strict_types=1);

namespace Lteco\Application\Repuesto;

use Lteco\Infrastructure\Repository\RepuestoCajaRepository;
use RuntimeException;
use Throwable;

final class RepuestoCajaService
{
    public function __construct(
        private RepuestoCajaRepository $repository,
        private RepuestoCrudService $repuestoCrudService,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function repuestosParaSelect(): array
    {
        return $this->repository->repuestosParaSelect();
    }

    /** @return list<array<string,mixed>> */
    public function importacionesActivas(): array
    {
        return $this->repository->importacionesActivas();
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return $this->repository->listarCajas();
    }

    /** @return list<array<string,mixed>> */
    public function cajasActivas(): array
    {
        return $this->repository->listarCajasActivas();
    }

    /** @return array{caja:array<string,mixed>,contenido:list<array<string,mixed>>,movimientos:list<array<string,mixed>>}|null */
    public function obtener(int|string $identificador, string $campo = 'IdCaja'): ?array
    {
        $caja = $this->repository->obtenerCaja($identificador, $campo);
        if (!$caja) {
            return null;
        }

        $idCaja = (int) $caja['IdCaja'];
        return [
            'caja' => $caja,
            'contenido' => $this->repository->contenidoCaja($idCaja),
            'movimientos' => $this->repository->movimientosCaja($idCaja),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function cajasPorRepuesto(int $idRepuesto): array
    {
        return $this->repository->cajasPorRepuesto($idRepuesto);
    }

    /**
     * @param array<string,mixed> $datosCaja
     * @param list<array<string,mixed>> $lineas
     * @return array{id_caja:int,codigo:string,token_uuid:string}
     */
    public function crear(array $datosCaja, array $lineas, ?int $idUsuario): array
    {
        $modo = (string) ($datosCaja['modo'] ?? 'ingreso');
        if (!in_array($modo, ['ingreso', 'ubicar'], true)) {
            throw new RuntimeException('Modo de caja inválido.');
        }

        $normalizadas = $this->normalizarLineas($lineas, $modo, (string) ($datosCaja['empresa_rut'] ?? ''));
        if ($normalizadas === []) {
            throw new RuntimeException('Agregá al menos un repuesto a la caja.');
        }

        $pdo = $this->repository->pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $token = $this->uuidV4();
            $idCaja = $this->repository->crearCajaPendiente(
                $token,
                $this->nullableText($datosCaja['nombre'] ?? null, 160),
                $this->nullableText($datosCaja['ubicacion'] ?? null, 160),
                $this->nullableText($datosCaja['observaciones'] ?? null, 1000)
            );
            $codigo = 'CJ-' . str_pad((string) $idCaja, 4, '0', STR_PAD_LEFT);
            $this->repository->fijarCodigo($idCaja, $codigo);
            $this->repository->registrarMovimiento($idCaja, null, 'CREAR_CAJA', 0, null, null, $idUsuario, 'Caja creada: ' . $codigo);

            foreach ($normalizadas as $linea) {
                $idRepuesto = (int) ($linea['id_repuesto'] ?? 0);
                $cantidad = (int) $linea['cantidad'];

                if (($linea['tipo'] ?? '') === 'nuevo') {
                    if ($modo !== 'ingreso') {
                        throw new RuntimeException('No se puede crear un repuesto nuevo al ubicar stock existente.');
                    }
                    $datosRepuesto = $linea['repuesto'];
                    $errores = $this->repuestoCrudService->validar($datosRepuesto);
                    if ($errores !== []) {
                        throw new RuntimeException(implode(' ', $errores));
                    }
                    $idRepuesto = $this->repository->crearRepuesto($datosRepuesto);
                    $stockAnterior = 0;
                    $stockNuevo = $cantidad;
                    $tipoMovimiento = 'INGRESO_NUEVO';
                } else {
                    $repuesto = $this->repository->repuestoConProductoParaActualizar($idRepuesto);
                    if (!$repuesto) {
                        throw new RuntimeException('Repuesto existente no encontrado.');
                    }
                    $stockAnterior = (int) $repuesto['Stock'];
                    if ($modo === 'ingreso') {
                        $stockNuevo = $stockAnterior + $cantidad;
                        $this->repository->aumentarStockProducto((int) $repuesto['IdProducto'], $stockNuevo);
                        $tipoMovimiento = 'INGRESO_NUEVO';
                    } else {
                        $ubicado = $this->repository->cantidadUbicadaRepuesto($idRepuesto);
                        if (($ubicado + $cantidad) > $stockAnterior) {
                            throw new RuntimeException('La cantidad ubicada en cajas no puede superar el stock total del repuesto.');
                        }
                        $stockNuevo = $stockAnterior;
                        $tipoMovimiento = 'UBICACION_EXISTENTE';
                    }
                }

                $caja = $this->repository->cajaParaActualizar($idCaja);
                if (!$caja || (string) $caja['Estado'] === 'Archivada') {
                    throw new RuntimeException('La caja archivada no admite movimientos.');
                }

                $this->repository->agregarItem($idCaja, $idRepuesto, $cantidad);
                $ubicadoFinal = $this->repository->cantidadUbicadaRepuesto($idRepuesto);
                $stockGeneral = $stockNuevo;
                if ($ubicadoFinal > $stockGeneral) {
                    throw new RuntimeException('La cantidad ubicada en cajas no puede superar el stock total del repuesto.');
                }
                $this->repository->registrarMovimiento(
                    $idCaja,
                    $idRepuesto,
                    $tipoMovimiento,
                    $cantidad,
                    $stockAnterior,
                    $stockNuevo,
                    $idUsuario,
                    $modo === 'ingreso' ? 'Ingreso en caja ' . $codigo : 'Ubicación de stock existente en caja ' . $codigo
                );
            }

            if ($started) {
                $pdo->commit();
            }

            return ['id_caja' => $idCaja, 'codigo' => $codigo, 'token_uuid' => $token];
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function ubicarRepuestoExistente(int $idCaja, int $idRepuesto, int $cantidad, ?int $idUsuario): void
    {
        if ($idCaja <= 0) {
            throw new RuntimeException('Seleccioná una caja.');
        }
        if ($idRepuesto <= 0) {
            throw new RuntimeException('Repuesto no encontrado.');
        }
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }

        $pdo = $this->repository->pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $caja = $this->repository->cajaParaActualizar($idCaja);
            if (!$caja) {
                throw new RuntimeException('Caja no encontrada.');
            }
            if ((string) $caja['Estado'] === 'Archivada') {
                throw new RuntimeException('La caja archivada no admite movimientos.');
            }

            $repuesto = $this->repository->repuestoConProductoParaActualizar($idRepuesto);
            if (!$repuesto) {
                throw new RuntimeException('Repuesto no encontrado.');
            }

            $stock = (int) $repuesto['Stock'];
            $ubicado = $this->repository->cantidadUbicadaRepuesto($idRepuesto);
            if (($ubicado + $cantidad) > $stock) {
                throw new RuntimeException('La cantidad ubicada en cajas no puede superar el stock total del repuesto.');
            }

            $this->repository->agregarItem($idCaja, $idRepuesto, $cantidad);
            $this->repository->registrarMovimiento(
                $idCaja,
                $idRepuesto,
                'UBICACION_EXISTENTE',
                $cantidad,
                $stock,
                $stock,
                $idUsuario,
                'Ubicación de stock existente en caja ' . (string) $caja['Codigo']
            );

            if ($started) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param list<array<string,mixed>> $lineas */
    private function normalizarLineas(array $lineas, string $modo, string $empresaRut): array
    {
        $normalizadas = [];
        foreach ($lineas as $linea) {
            $cantidad = max(0, (int) ($linea['cantidad'] ?? 0));
            if ($cantidad <= 0) {
                continue;
            }

            $tipo = (string) ($linea['tipo'] ?? 'existente');
            if ($tipo === 'nuevo') {
                $datos = $this->normalizarRepuestoNuevo($linea, $cantidad, $empresaRut);
                $normalizadas[] = ['tipo' => 'nuevo', 'cantidad' => $cantidad, 'repuesto' => $datos];
                continue;
            }

            $idRepuesto = (int) ($linea['id_repuesto'] ?? 0);
            if ($idRepuesto <= 0) {
                throw new RuntimeException('Seleccioná un repuesto existente o cargá los datos de uno nuevo.');
            }
            $key = 'e:' . $idRepuesto;
            if (!isset($normalizadas[$key])) {
                $normalizadas[$key] = ['tipo' => 'existente', 'id_repuesto' => $idRepuesto, 'cantidad' => 0];
            }
            $normalizadas[$key]['cantidad'] += $cantidad;
        }

        if ($modo === 'ubicar') {
            foreach ($normalizadas as $linea) {
                if (($linea['tipo'] ?? '') === 'nuevo') {
                    throw new RuntimeException('Ubicar stock existente solo admite repuestos ya creados.');
                }
            }
        }

        return array_values($normalizadas);
    }

    /** @param array<string,mixed> $linea */
    private function normalizarRepuestoNuevo(array $linea, int $cantidad, string $empresaRut): array
    {
        $nombre = trim((string) ($linea['nombre'] ?? ''));
        $moneda = in_array((string) ($linea['moneda'] ?? 'UYU'), ['UYU', 'USD'], true) ? (string) $linea['moneda'] : 'UYU';
        return [
            'nombre' => mb_substr($nombre, 0, 150),
            'descripcion' => trim((string) ($linea['descripcion'] ?? '')),
            'costo' => max(0.0, (float) ($linea['costo'] ?? 0)),
            'gasto_total' => max(0.0, (float) ($linea['gasto_total'] ?? 0)),
            'precio_venta' => max(0.0, (float) ($linea['precio_venta'] ?? 0)),
            'precio_distribuidor' => ($linea['precio_distribuidor'] ?? '') !== '' ? max(0.0, (float) $linea['precio_distribuidor']) : null,
            'moneda' => $moneda,
            'stock' => $cantidad,
            'estado' => $cantidad > 0 ? 'Disponible' : 'Sin stock',
            'numero_importacion' => ($linea['numero_importacion'] ?? '') !== '' ? (int) $linea['numero_importacion'] : null,
            'empresa_rut' => $empresaRut,
        ];
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, $max);
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
