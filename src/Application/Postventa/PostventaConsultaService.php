<?php

declare(strict_types=1);

namespace Lteco\Application\Postventa;

use Lteco\Infrastructure\Repository\PostventaConsultaRepository;

/**
 * Caso de uso de LECTURA de postventa: arma los datos que consumen las vistas
 * postventa/index.php (listado) y postventa/detalle.php (detalle).
 *
 * Sin HTML, sin $_GET/$_POST, sin echo: recibe inputs ya resueltos por la página
 * (filtros y alcance de vendedor) y devuelve arrays. Solo lectura.
 */
final class PostventaConsultaService
{
    /** Estados de service ofrecidos en el filtro del listado (también validan la query). */
    public const ESTADOS_SERVICE = ['Pendiente', 'Vencido', 'Realizado', 'Cancelado', 'Sin pendientes'];

    /** Estados de garantía ofrecidos en el filtro del listado. */
    public const ESTADOS_GARANTIA = ['Vigente', 'Vencida', 'Anulada', 'Cancelada', 'Sin garantía'];

    public function __construct(private PostventaConsultaRepository $repo)
    {
    }

    /**
     * Datos del listado: motos en seguimiento + recordatorios WhatsApp + métricas.
     *
     * @param array{q?:string,estado_service?:string,garantia?:string} $filtros
     * @return array{items:list<array<string,mixed>>,recordatoriosWA:list<array<string,mixed>>,metricas:array<string,int>}
     */
    public function listado(array $filtros, int $idUsuarioVendedor): array
    {
        $q = (string) ($filtros['q'] ?? '');

        // Solo se aplican filtros dentro de la lista permitida; cualquier otro valor
        // se descarta (idéntico al in_array() del legacy), pasando '' al repositorio.
        $estadoService = $this->valorPermitido($filtros['estado_service'] ?? '', self::ESTADOS_SERVICE);
        $garantia      = $this->valorPermitido($filtros['garantia'] ?? '', self::ESTADOS_GARANTIA);

        return [
            'items'           => $this->repo->listadoMotos($q, $estadoService, $garantia, $idUsuarioVendedor),
            'recordatoriosWA' => $this->repo->recordatoriosWhatsApp($idUsuarioVendedor),
            'metricas'        => $this->repo->metricas($idUsuarioVendedor),
        ];
    }

    /**
     * Datos del detalle de un vehículo. Devuelve null si no hay venta visible para
     * ese vehículo (la vista redirige con flash de error).
     *
     * @return array{
     *     vehiculo:array<string,mixed>,
     *     services:list<array<string,mixed>>,
     *     historialPorService:array<int,list<array<string,mixed>>>,
     *     historialTecnico:list<array<string,mixed>>,
     *     repuestosUsadosPorHistorial:array<int,list<array<string,mixed>>>,
     *     repuestosDisponibles:list<array<string,mixed>>
     * }|null
     */
    public function detalle(string $idVehiculo, int $idUsuarioVendedor): ?array
    {
        $vehiculo = $this->repo->vehiculoConVenta($idVehiculo, $idUsuarioVendedor);
        if ($vehiculo === null) {
            return null;
        }

        $idVenta = (int) $vehiculo['IdVenta'];

        $services = $this->repo->servicesDeVenta($idVehiculo, $idVenta);

        $historialPorService = [];
        foreach ($this->repo->historialDeVenta($idVehiculo, $idVenta) as $evento) {
            $idServiceHistorial = (int) ($evento['IdService'] ?? 0);
            $historialPorService[$idServiceHistorial][] = $evento;
        }

        $historialTecnico = $this->repo->historialTecnico($idVehiculo, $idVenta);

        $repuestosUsadosPorHistorial = [];
        if ($historialTecnico !== []) {
            $idsHistorial = array_map(static fn ($h) => (int) $h['IdHistorialTecnico'], $historialTecnico);
            foreach ($this->repo->repuestosUsados($idsHistorial) as $ru) {
                $repuestosUsadosPorHistorial[(int) $ru['IdHistorialTecnico']][] = $ru;
            }
        }

        return [
            'vehiculo'                    => $vehiculo,
            'services'                    => $services,
            'historialPorService'         => $historialPorService,
            'historialTecnico'            => $historialTecnico,
            'repuestosUsadosPorHistorial' => $repuestosUsadosPorHistorial,
            'repuestosDisponibles'        => $this->repo->repuestosDisponibles(),
        ];
    }

    /**
     * @param list<string> $permitidos
     */
    private function valorPermitido(string $valor, array $permitidos): string
    {
        $valor = trim($valor);

        return ($valor !== '' && in_array($valor, $permitidos, true)) ? $valor : '';
    }
}
