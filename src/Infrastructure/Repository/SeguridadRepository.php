<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class SeguridadRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function asegurarIntentosLogin(): void
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'login_attempts'
        ");
        if ((int) $stmt->fetchColumn() === 0) {
            throw new \RuntimeException('Seguridad: schema incompleto. Ejecutá scripts/migrate.sh antes de usar login.');
        }
    }

    public function intentosFallidosRecientes(string $ip, string $usuario, int $ventanaMinutos): int
    {
        $this->asegurarIntentosLogin();
        $ventanaMinutos = max(1, $ventanaMinutos);
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM login_attempts
            WHERE Ip = ?
              AND UsuarioIntentado = ?
              AND Resultado = 'fallido'
              AND FechaHora >= (NOW() - INTERVAL {$ventanaMinutos} MINUTE)
        ");
        $stmt->execute([$ip, $usuario]);
        return (int) $stmt->fetchColumn();
    }

    public function bloqueadoHasta(string $ip, string $usuario): ?string
    {
        $this->asegurarIntentosLogin();
        $stmt = $this->pdo->prepare("
            SELECT BloqueadoHasta
            FROM login_attempts
            WHERE Ip = ?
              AND UsuarioIntentado = ?
              AND BloqueadoHasta IS NOT NULL
              AND BloqueadoHasta > NOW()
            ORDER BY BloqueadoHasta DESC
            LIMIT 1
        ");
        $stmt->execute([$ip, $usuario]);
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (string) $valor : null;
    }

    public function registrarIntento(
        string $ip,
        string $usuario,
        string $userAgent,
        string $resultado,
        int $intentosRecientes,
        ?string $bloqueadoHasta
    ): void {
        $this->asegurarIntentosLogin();
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts
                (Ip, UsuarioIntentado, UserAgent, Resultado, IntentosRecientes, BloqueadoHasta)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ip, $usuario, $userAgent, $resultado, $intentosRecientes, $bloqueadoHasta]);
    }

    /** @return array<string,mixed>|null */
    public function usuarioPorLogin(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT IdUsuario, NombreCompleto, Usuario, ClaveHash, Rol, IdDistribuidor, Activo,
                   mfa_enabled, mfa_secret, mfa_recovery_codes
            FROM usuario
            WHERE Usuario = ?
            LIMIT 1
        ");
        $stmt->execute([$usuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function vendedorPuedeVerVenta(int $idVenta, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare('SELECT UsuarioVendedorId FROM venta WHERE IdVenta = ? LIMIT 1');
        $stmt->execute([$idVenta]);
        $valor = $stmt->fetchColumn();
        return $valor !== false && (int) $valor === $idUsuario;
    }

    public function vendedorPuedeVerCliente(int $idCliente, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM venta
            WHERE Cliente_IdCliente = ? AND UsuarioVendedorId = ?
            LIMIT 1
        ');
        $stmt->execute([$idCliente, $idUsuario]);
        return (bool) $stmt->fetchColumn();
    }

    public function vendedorPuedeVerPostventa(string $idVehiculo, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM vehiculo vh
            INNER JOIN venta_detalle vd ON vd.Producto_IdProducto = vh.IdProducto
            INNER JOIN venta v ON v.IdVenta = vd.Venta_IdVenta
            WHERE vh.IdVehiculo = ?
              AND v.UsuarioVendedorId = ?
              AND COALESCE(v.EstadoVenta, \'Confirmada\') <> \'Anulada\'
            LIMIT 1
        ');
        $stmt->execute([$idVehiculo, $idUsuario]);
        return (bool) $stmt->fetchColumn();
    }

    public function vendedorPuedeOperarPostventaService(int $idService, string $idVehiculo, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM service_vehiculo sv
            INNER JOIN venta v ON v.IdVenta = sv.IdVenta
            WHERE sv.IdService = ?
              AND sv.IdVehiculo = ?
              AND v.UsuarioVendedorId = ?
              AND COALESCE(v.EstadoVenta, \'Confirmada\') <> \'Anulada\'
            LIMIT 1
        ');
        $stmt->execute([$idService, $idVehiculo, $idUsuario]);
        return (bool) $stmt->fetchColumn();
    }
}
