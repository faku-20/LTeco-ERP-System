<?php

declare(strict_types=1);

namespace Lteco\Application\Vehiculo;

use Lteco\Infrastructure\Repository\VehiculoConsultaRepository;

final class VehiculoConsultaService
{
    public function __construct(private VehiculoConsultaRepository $repository)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function paraQr(string $idVehiculo): ?array
    {
        return $this->repository->paraQr($idVehiculo);
    }

    /**
     * @param list<string> $ids
     * @return list<array<string,mixed>>
     */
    public function paraEtiquetas(array $ids): array
    {
        $rows = $this->repository->paraEtiquetas($ids);
        $porId = [];
        foreach ($rows as $row) {
            $porId[(string) $row['IdVehiculo']] = $row;
        }

        $ordenadas = [];
        foreach ($ids as $id) {
            if (isset($porId[$id])) {
                $ordenadas[] = $porId[$id];
            }
        }

        return $ordenadas;
    }

    /**
     * @return array{id:string,motor:string}
     */
    public function extraerQr(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return ['id' => '', 'motor' => ''];
        }

        $partsUrl = parse_url($valor);
        if (is_array($partsUrl) && isset($partsUrl['query'])) {
            parse_str((string) $partsUrl['query'], $query);
            $valor = trim((string) ($query['qr'] ?? $query['data'] ?? $query['motor'] ?? $query['m'] ?? $valor));
        }

        if (str_starts_with($valor, 'LTECO|')) {
            $parts = explode('|', $valor);
            if (count($parts) >= 3) {
                return ['id' => trim((string) $parts[1]), 'motor' => trim((string) $parts[2])];
            }
        }

        if (str_contains($valor, '|')) {
            $parts = array_values(array_filter(array_map('trim', explode('|', $valor))));
            return ['id' => '', 'motor' => (string) end($parts)];
        }

        return ['id' => '', 'motor' => $valor];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarEscaneado(string $valor): ?array
    {
        $qr = $this->extraerQr($valor);
        if ($qr['motor'] === '') {
            return null;
        }

        return $this->repository->buscarEscaneado($qr['motor'], $qr['id'] !== '' ? $qr['id'] : null);
    }
}
