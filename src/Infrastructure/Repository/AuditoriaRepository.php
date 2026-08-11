<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consultas de solo lectura para la pantalla de auditoría (G1).
 */
final class AuditoriaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function tablaExiste(): bool
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'auditoria'");
        return $stmt && (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{modulos:list<string>,acciones:list<string>,usuarios:list<string>}
     */
    public function opciones(): array
    {
        return [
            'modulos' => $this->pdo
                ->query("SELECT DISTINCT Modulo FROM auditoria WHERE Modulo IS NOT NULL AND Modulo <> '' ORDER BY Modulo ASC")
                ->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'acciones' => $this->pdo
                ->query("SELECT DISTINCT Accion FROM auditoria WHERE Accion IS NOT NULL AND Accion <> '' ORDER BY Accion ASC")
                ->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'usuarios' => $this->pdo
                ->query("SELECT DISTINCT Usuario FROM auditoria WHERE Usuario IS NOT NULL AND Usuario <> '' ORDER BY Usuario ASC LIMIT 100")
                ->fetchAll(PDO::FETCH_COLUMN) ?: [],
        ];
    }

    /**
     * @param array{q:string,modulo:string,accion:string,usuario:string,desde:string,hasta:string} $filtros
     */
    public function contar(array $filtros): int
    {
        [$whereSql, $params] = $this->construirFiltros($filtros);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM auditoria {$whereSql}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array{q:string,modulo:string,accion:string,usuario:string,desde:string,hasta:string} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        [$whereSql, $params] = $this->construirFiltros($filtros);
        $sql = "
            SELECT IdAuditoria, IdUsuario, Usuario, Rol, Accion, Modulo, Detalle, ExtraJson, Ip, UserAgent, FechaHora
            FROM auditoria
            {$whereSql}
            ORDER BY FechaHora DESC, IdAuditoria DESC
            LIMIT " . max(1, $limite) . ' OFFSET ' . max(0, $offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{q:string,modulo:string,accion:string,usuario:string,desde:string,hasta:string} $filtros
     * @return array{0:string,1:array<string,string>}
     */
    private function construirFiltros(array $filtros): array
    {
        $where = [];
        $params = [];

        if ($filtros['q'] !== '') {
            $where[] = "(
                Detalle LIKE :q_detalle
                OR ExtraJson LIKE :q_json
                OR Ip LIKE :q_ip
                OR UserAgent LIKE :q_user_agent
                OR Usuario LIKE :q_usuario
                OR Rol LIKE :q_rol
                OR Accion LIKE :q_accion
                OR Modulo LIKE :q_modulo
            )";
            $busqueda = '%' . $filtros['q'] . '%';
            foreach (['detalle', 'json', 'ip', 'user_agent', 'usuario', 'rol', 'accion', 'modulo'] as $campo) {
                $params[':q_' . $campo] = $busqueda;
            }
        }

        if ($filtros['modulo'] !== '') {
            $where[] = 'Modulo = :modulo';
            $params[':modulo'] = $filtros['modulo'];
        }
        if ($filtros['accion'] !== '') {
            $where[] = 'Accion = :accion';
            $params[':accion'] = $filtros['accion'];
        }
        if ($filtros['usuario'] !== '') {
            $where[] = 'Usuario = :usuario';
            $params[':usuario'] = $filtros['usuario'];
        }
        if ($filtros['desde'] !== '') {
            $where[] = 'FechaHora >= :desde';
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if ($filtros['hasta'] !== '') {
            $where[] = 'FechaHora <= :hasta';
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }
}
