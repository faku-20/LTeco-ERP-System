<?php

declare(strict_types=1);

namespace Lteco\Application\Seguridad;

use Lteco\Infrastructure\Repository\SeguridadRepository;

final class SeguridadService
{
    public function __construct(private SeguridadRepository $repository)
    {
    }

    public static function normalizarUsuario(string $usuario): string
    {
        return mb_strtolower(substr(trim($usuario), 0, 190), 'UTF-8');
    }

    public function asegurarIntentosLogin(): void
    {
        $this->repository->asegurarIntentosLogin();
    }

    public function intentosFallidosRecientes(string $ip, string $usuario, int $ventanaMinutos = 15): int
    {
        return $this->repository->intentosFallidosRecientes(
            $ip,
            self::normalizarUsuario($usuario),
            $ventanaMinutos
        );
    }

    public function bloqueadoHasta(string $ip, string $usuario): ?string
    {
        return $this->repository->bloqueadoHasta($ip, self::normalizarUsuario($usuario));
    }

    public function registrarIntento(
        string $ip,
        string $usuario,
        string $userAgent,
        string $resultado,
        int $intentosRecientes = 0,
        ?string $bloqueadoHasta = null
    ): void {
        $this->repository->registrarIntento(
            $ip,
            self::normalizarUsuario($usuario),
            $userAgent,
            $resultado,
            max(0, $intentosRecientes),
            $bloqueadoHasta
        );
    }

    /** @return array<string,mixed>|null */
    public function usuarioPorLogin(string $usuario): ?array
    {
        return $this->repository->usuarioPorLogin($usuario);
    }

    public function vendedorPuedeVerVenta(int $idVenta, int $idUsuario): bool
    {
        return $idVenta > 0 && $idUsuario > 0
            && $this->repository->vendedorPuedeVerVenta($idVenta, $idUsuario);
    }

    public function vendedorPuedeVerCliente(int $idCliente, int $idUsuario): bool
    {
        return $idCliente > 0 && $idUsuario > 0
            && $this->repository->vendedorPuedeVerCliente($idCliente, $idUsuario);
    }

    public function vendedorPuedeVerPostventa(string $idVehiculo, int $idUsuario): bool
    {
        return trim($idVehiculo) !== '' && $idUsuario > 0
            && $this->repository->vendedorPuedeVerPostventa(trim($idVehiculo), $idUsuario);
    }

    public function vendedorPuedeOperarPostventaService(int $idService, string $idVehiculo, int $idUsuario): bool
    {
        return $idService > 0 && trim($idVehiculo) !== '' && $idUsuario > 0
            && $this->repository->vendedorPuedeOperarPostventaService(
                $idService,
                trim($idVehiculo),
                $idUsuario
            );
    }
}
