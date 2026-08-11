<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos de usuarios (Ola F). SQL puro: el handler conserva guards de
 * rol/jerarquía, hashing de contraseñas, criptografía MFA y auditoría. No abre
 * transacciones. Preserva textualmente los SELECT/INSERT/UPDATE/DELETE legacy y
 * sus sets de columnas (los SELECT sensibles —ClaveHash, mfa_secret— se acotan
 * a quién los necesita).
 */
final class UsuarioRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Listado de usuarios ordenado por jerarquía de rol (usuarios/index.php).
     *
     * @return list<array<string,mixed>>
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                IdUsuario,
                NombreCompleto,
                Usuario,
                Rol,
                mfa_enabled,
                Activo,
                FechaAlta,
                ComisionPct,
                ComisionDistribuidorPct
            FROM usuario
            ORDER BY
                CASE Rol
                    WHEN 'Superadmin' THEN 1
                    WHEN 'Administrador' THEN 2
                    WHEN 'Vendedor' THEN 3
                    ELSE 4
                END,
                NombreCompleto ASC,
                IdUsuario DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Distribuidores activos para los selects de los formularios de usuario.
     *
     * @return list<array<string,mixed>>
     */
    public function distribuidoresActivos(): array
    {
        $stmt = $this->pdo->query("SELECT IdDistribuidor, Nombre FROM distribuidor WHERE Activo = 1 ORDER BY Nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ¿Existe ya un usuario con ese nombre de login? (alta).
     */
    public function existeUsuario(string $usuario): bool
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario FROM usuario WHERE Usuario = ? LIMIT 1");
        $stmt->execute([$usuario]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ¿Existe otro usuario (distinto del id dado) con ese nombre de login? (edición).
     */
    public function existeUsuarioExcepto(string $usuario, int $idExcluido): bool
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario FROM usuario WHERE Usuario = ? AND IdUsuario <> ? LIMIT 1");
        $stmt->execute([$usuario, $idExcluido]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Ficha para el formulario de edición (sin columnas sensibles).
     *
     * @return array<string,mixed>|null
     */
    public function buscarParaEdicion(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario, NombreCompleto, Usuario, Rol, IdDistribuidor, Activo, ComisionPct, ComisionDistribuidorPct FROM usuario WHERE IdUsuario = ? LIMIT 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Ficha para cambio de clave (incluye ClaveHash para impedir reutilizarla).
     *
     * @return array<string,mixed>|null
     */
    public function buscarParaClave(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario, NombreCompleto, Usuario, Rol, Activo, ClaveHash FROM usuario WHERE IdUsuario = ? LIMIT 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Ficha mínima para eliminar.
     *
     * @return array<string,mixed>|null
     */
    public function buscarParaEliminar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario, NombreCompleto, Usuario, Rol FROM usuario WHERE IdUsuario = ? LIMIT 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Ficha para activar/desactivar.
     *
     * @return array<string,mixed>|null
     */
    public function buscarParaToggle(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario, NombreCompleto, Usuario, Rol, Activo FROM usuario WHERE IdUsuario = ? LIMIT 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Ficha para gestión de MFA (incluye mfa_secret y recovery codes).
     *
     * @return array<string,mixed>|null
     */
    public function buscarParaMfa(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdUsuario, NombreCompleto, Usuario, Rol, Activo, mfa_enabled, mfa_secret, mfa_recovery_codes FROM usuario WHERE IdUsuario = ? LIMIT 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Inserta un usuario (Activo = 1) y devuelve su IdUsuario. La contraseña
     * llega ya hasheada por el handler.
     *
     * @param array{nombre_completo:string,usuario:string,clave_hash:string,rol:string,id_distribuidor:?int,comision_pct:float,comision_distribuidor_pct:float} $datos
     */
    public function crear(array $datos): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO usuario (NombreCompleto, Usuario, ClaveHash, Rol, IdDistribuidor, ComisionPct, ComisionDistribuidorPct, Activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $datos['nombre_completo'],
            $datos['usuario'],
            $datos['clave_hash'],
            $datos['rol'],
            $datos['id_distribuidor'],
            $datos['comision_pct'],
            $datos['comision_distribuidor_pct'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza los datos de un usuario (no toca clave ni MFA).
     *
     * @param array{nombre_completo:string,usuario:string,rol:string,id_distribuidor:?int,comision_pct:float,comision_distribuidor_pct:float} $datos
     */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuario SET NombreCompleto = ?, Usuario = ?, Rol = ?, IdDistribuidor = ?, ComisionPct = ?, ComisionDistribuidorPct = ? WHERE IdUsuario = ?");
        $stmt->execute([
            $datos['nombre_completo'],
            $datos['usuario'],
            $datos['rol'],
            $datos['id_distribuidor'],
            $datos['comision_pct'],
            $datos['comision_distribuidor_pct'],
            $id,
        ]);
    }

    /**
     * Actualiza la contraseña (hash provisto por el handler).
     */
    public function actualizarClave(int $id, string $claveHash): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuario SET ClaveHash = ? WHERE IdUsuario = ?");
        $stmt->execute([$claveHash, $id]);
    }

    /**
     * Elimina un usuario.
     */
    public function eliminar(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM usuario WHERE IdUsuario = ?");
        $stmt->execute([$id]);
    }

    /**
     * Fija el estado Activo (0/1).
     */
    public function actualizarActivo(int $id, int $activo): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuario SET Activo = ? WHERE IdUsuario = ?");
        $stmt->execute([$activo, $id]);
    }

    /**
     * Activa MFA con secreto protegido y recovery codes hasheados (provistos por
     * el handler, que conserva la criptografía).
     */
    public function activarMfa(int $id, string $secretProtegido, string $recoveryHasheados): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuario SET mfa_enabled = 1, mfa_secret = ?, mfa_recovery_codes = ? WHERE IdUsuario = ?");
        $stmt->execute([$secretProtegido, $recoveryHasheados, $id]);
    }

    /**
     * Desactiva MFA y limpia secreto y recovery codes.
     */
    public function desactivarMfa(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuario SET mfa_enabled = 0, mfa_secret = NULL, mfa_recovery_codes = NULL WHERE IdUsuario = ?");
        $stmt->execute([$id]);
    }

    /**
     * Regenera solo los recovery codes (hasheados por el handler).
     */
    public function regenerarRecoveryCodes(int $id, string $recoveryHasheados): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuario SET mfa_recovery_codes = ? WHERE IdUsuario = ?");
        $stmt->execute([$recoveryHasheados, $id]);
    }
}
