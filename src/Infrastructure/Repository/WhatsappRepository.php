<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class WhatsappRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /** @return array<string,mixed>|null */
    public function configuracion(): ?array
    {
        $stmt = $this->pdo->query("
            SELECT WaEnabled, WaPhoneId, WaToken, WaTplVenta, WaTplService
            FROM configuracion
            ORDER BY IdConfiguracion ASC
            LIMIT 1
        ");
        $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $fila ?: null;
    }

    public function tablaDisponible(): bool
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'notificacion_whatsapp'");
        return $stmt && (bool) $stmt->fetchColumn();
    }

    public function ultimoError(string $tipo, int $idReferencia, string $template): ?string
    {
        $where = ['Estado = ?'];
        $params = ['error'];

        if (in_array($tipo, ['venta', 'service'], true)) {
            $where[] = 'Tipo = ?';
            $params[] = $tipo;
        }
        if ($idReferencia >= 0) {
            $where[] = 'IdReferencia = ?';
            $params[] = $idReferencia;
        }
        if (trim($template) !== '') {
            $where[] = 'Template = ?';
            $params[] = mb_substr(trim($template), 0, 100);
        }

        $stmt = $this->pdo->prepare("
            SELECT RespuestaMeta
            FROM notificacion_whatsapp
            WHERE " . implode(' AND ', $where) . "
            ORDER BY IdNotificacion DESC
            LIMIT 1
        ");
        $stmt->execute($params);
        $valor = $stmt->fetchColumn();
        return is_string($valor) ? $valor : null;
    }

    public function tieneVentanaTextoGratuita(string $telefono, int $horas = 24): bool
    {
        $horas = max(1, min($horas, 24));
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'commercial_inbox_message'");
        if (!$stmt || !$stmt->fetchColumn()) {
            return false;
        }

        $sql = "
            SELECT IdInbox
            FROM commercial_inbox_message
            WHERE Canal = 'whatsapp'
              AND Direccion = 'inbound'
              AND Telefono = ?
              AND COALESCE(FechaRecibido, FechaAlta) >= DATE_SUB(NOW(), INTERVAL {$horas} HOUR)
            ORDER BY IdInbox DESC
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$telefono]);
        return (bool) $stmt->fetchColumn();
    }

    public function registrar(
        string $tipo,
        int $idReferencia,
        string $telefono,
        string $template,
        string $estado,
        ?string $respuesta,
        ?string $waMessageId = null
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO notificacion_whatsapp
                (Tipo, IdReferencia, Telefono, Template, Estado, RespuestaMeta, WaMessageId)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tipo, $idReferencia, $telefono, $template, $estado, $respuesta, $waMessageId]);
    }

    public function asegurarTabla(): void
    {
        $this->requireTables(['notificacion_whatsapp', 'whatsapp_test_reset'], 'WhatsApp');
        $this->asegurarMediaCache();
        $this->requireColumns('notificacion_whatsapp', ['WaMessageId', 'EstadoEntrega', 'RespuestaWebhook', 'FechaEstadoMeta'], 'WhatsApp');
    }

    public function asegurarMediaCache(): void
    {
        $this->requireTables(['whatsapp_media_cache'], 'WhatsApp media');
    }

    public function mediaCacheId(string $sourceKey): ?string
    {
        $stmt = $this->pdo->prepare('SELECT MediaId FROM whatsapp_media_cache WHERE SourceKey=? LIMIT 1');
        $stmt->execute([$sourceKey]);
        $mediaId = trim((string)($stmt->fetchColumn() ?: ''));
        return $mediaId !== '' ? $mediaId : null;
    }

    public function guardarMediaCache(
        string $sourceKey,
        string $url,
        ?string $localPath,
        ?string $fileHash,
        string $mimeType,
        string $mediaId,
        ?string $respuestaMeta
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_media_cache (SourceKey,Url,LocalPath,FileHash,MimeType,MediaId,RespuestaMeta)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                Url=VALUES(Url),
                LocalPath=VALUES(LocalPath),
                FileHash=VALUES(FileHash),
                MimeType=VALUES(MimeType),
                MediaId=VALUES(MediaId),
                RespuestaMeta=VALUES(RespuestaMeta),
                ActualizadoEn=NOW()
        ");
        $stmt->execute([$sourceKey, $url, $localPath, $fileHash, $mimeType, $mediaId, $respuestaMeta]);
    }

    public function registrarEstadoWebhook(
        string $messageId,
        string $estado,
        string $respuestaWebhook,
        ?string $fechaEstadoMeta
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE notificacion_whatsapp
            SET
                WaMessageId = ?,
                EstadoEntrega = ?,
                RespuestaWebhook = ?,
                FechaEstadoMeta = COALESCE(?, FechaEstadoMeta)
            WHERE WaMessageId = ?
               OR RespuestaMeta LIKE ?
            ORDER BY IdNotificacion DESC
            LIMIT 1
        ");
        $stmt->execute([
            $messageId,
            $estado,
            $respuestaWebhook,
            $fechaEstadoMeta,
            $messageId,
            '%' . $messageId . '%',
        ]);
    }

    public function programarResetPrueba(string $telefono): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_test_reset
                (Telefono, Estado, FechaSolicitud, FechaClaim, FechaConsumo)
            VALUES (?, 'pendiente', NOW(), NULL, NULL)
            ON DUPLICATE KEY UPDATE
                Estado = 'pendiente',
                FechaSolicitud = NOW(),
                FechaClaim = NULL,
                FechaConsumo = NULL
        ");
        $stmt->execute([$telefono]);
    }

    public function reclamarResetPrueba(string $telefono): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE whatsapp_test_reset
            SET Estado = 'procesando', FechaClaim = NOW()
            WHERE Telefono = ? AND Estado = 'pendiente'
        ");
        $stmt->execute([$telefono]);
        return $stmt->rowCount() === 1;
    }

    public function liberarResetPrueba(string $telefono): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE whatsapp_test_reset
            SET Estado = 'pendiente', FechaClaim = NULL
            WHERE Telefono = ? AND Estado = 'procesando'
        ");
        $stmt->execute([$telefono]);
    }

    public function completarResetPrueba(string $telefono): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE whatsapp_test_reset
            SET Estado = 'consumido', FechaConsumo = NOW()
            WHERE Telefono = ? AND Estado = 'procesando'
        ");
        $stmt->execute([$telefono]);
    }

    public function asegurarColumnasConfiguracion(): void
    {
        $this->requireColumns('configuracion', ['WaEnabled', 'WaPhoneId', 'WaToken', 'WaTplVenta', 'WaTplService'], 'WhatsApp configuracion');

        $stmt = $this->pdo->query("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'configuracion'
              AND COLUMN_NAME = 'WaEnabled'
            LIMIT 1
        ");
        $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($fila && strtoupper((string) $fila['IS_NULLABLE']) === 'NO') {
            throw new \RuntimeException('WhatsApp configuracion: WaEnabled debe permitir NULL. Ejecutá scripts/migrate.sh.');
        }
    }

    /** @param list<string> $tables */
    private function requireTables(array $tables, string $component): void
    {
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ({$placeholders})
        ");
        $stmt->execute($tables);
        $existing = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        $missing = array_values(array_filter($tables, static fn(string $table): bool => !isset($existing[$table])));
        if ($missing !== []) {
            throw new \RuntimeException($component . ': schema incompleto. Ejecutá scripts/migrate.sh. Faltan tablas: ' . implode(', ', $missing));
        }
    }

    /** @param list<string> $columns */
    private function requireColumns(string $table, array $columns, string $component): void
    {
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$table], $columns));
        $existing = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        $missing = array_values(array_filter($columns, static fn(string $column): bool => !isset($existing[$column])));
        if ($missing !== []) {
            throw new \RuntimeException($component . ': schema incompleto. Ejecutá scripts/migrate.sh. Faltan columnas en ' . $table . ': ' . implode(', ', $missing));
        }
    }

    /** @return list<array<string,mixed>> */
    public function servicesParaRecordatorio(string $desde, string $hasta): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                sv.IdService, sv.NumeroService, sv.FechaProgramada, sv.IdCliente,
                c.NombreApellido, c.Telefono, v.Modelo, v.NumeroMotor
            FROM service_vehiculo sv
            INNER JOIN cliente c ON c.IdCliente = sv.IdCliente
            LEFT JOIN vehiculo v ON v.IdVehiculo = sv.IdVehiculo
            LEFT JOIN venta ve ON ve.IdVenta = sv.IdVenta
            WHERE sv.Estado = 'Pendiente'
              AND COALESCE(ve.EstadoVenta, 'Confirmada') <> 'Anulada'
              AND sv.FechaProgramada >= ?
              AND sv.FechaProgramada < ?
            ORDER BY sv.FechaProgramada ASC
        ");
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function serviceYaNotificado(int $idService): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT IdNotificacion
            FROM notificacion_whatsapp
            WHERE Tipo = 'service' AND IdReferencia = ? AND Estado = 'enviado'
            LIMIT 1
        ");
        $stmt->execute([$idService]);
        return (bool) $stmt->fetchColumn();
    }

    public function telefonoDistribuidor(int $idDistribuidor): ?string
    {
        $stmt = $this->pdo->prepare("SELECT Telefono FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1");
        $stmt->execute([$idDistribuidor]);
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (string) $valor : null;
    }
}
