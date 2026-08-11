<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/n8n.php';

function agendaEnsureSchema(PDO $pdo): void
{
    ltecoRequireSchemaTables($pdo, ['crm_visita', 'internal_alert'], 'Agenda');
    ltecoRequireSchemaColumns($pdo, [
        'crm_visita' => ['HoraConfirmada', 'StorefrontRequestUuid', 'ClienteCorreo'],
    ], 'Agenda');
}

/** @return array{scheduled:bool,reason:string,id_visit?:int,date?:string,hour_confirmed?:bool} */
function agendaMaybeScheduleFromInbox(PDO $pdo, int $idInbox, bool $ensureSchema = true): array
{
    if ($ensureSchema) {
        agendaEnsureSchema($pdo);
        aiEnsureSchema($pdo);
    }

    $stmt = $pdo->prepare("
        SELECT i.*, l.Nombre AS LeadNombre, l.ResponsableUsuarioId, l.IdCliente AS LeadCliente,
               l.IdVehiculo AS LeadVehiculo, u.NombreCompleto AS ResponsableNombre
        FROM commercial_inbox_message i
        LEFT JOIN commercial_lead l ON l.IdLead = i.IdLead
        LEFT JOIN usuario u ON u.IdUsuario = l.ResponsableUsuarioId
        WHERE i.IdInbox = ? AND i.Direccion = 'inbound'
        LIMIT 1
    ");
    $stmt->execute([$idInbox]);
    $inbox = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inbox) {
        return ['scheduled' => false, 'reason' => 'Mensaje entrante no encontrado.'];
    }

    $conversation = agendaConversationText($pdo, (int)($inbox['IdLead'] ?? 0), (string)($inbox['Telefono'] ?? ''));
    $recentPrompt = agendaHasRecentVisitPrompt($pdo, (string)($inbox['Telefono'] ?? ''));
    $detected = (new \Lteco\Application\Agenda\VisitIntentDetector())->detect($conversation, $recentPrompt);
    $dateHint = $detected['date_hint'] instanceof DateTimeImmutable ? $detected['date_hint'] : null;
    if (!$detected['wants_visit'] || $dateHint === null) {
        if ($detected['wants_visit']) {
            agendaRecordPendingVisit($pdo, $inbox, $detected, $conversation);
        }
        return ['scheduled' => false, 'reason' => $detected['reason'] ?: 'Sin visita confirmada.'];
    }

    $hourConfirmed = $detected['confirmed'] && $detected['date'] instanceof DateTimeImmutable;
    $visitDate = $hourConfirmed ? $detected['date'] : $dateHint->setTime(0, 0);
    $date = $visitDate->format('Y-m-d H:i:s');
    $phone = whatsappFormatearTelefono((string)($inbox['Telefono'] ?? '')) ?: preg_replace('/\D+/', '', (string)($inbox['Telefono'] ?? ''));
    if ($phone === '') {
        return ['scheduled' => false, 'reason' => 'La conversación no tiene teléfono válido.'];
    }

    if ($hourConfirmed) {
        $pendingVisit = $pdo->prepare("
            SELECT IdVisita
            FROM crm_visita
            WHERE ClienteTelefono = ? AND Estado IN ('agendada','reprogramada')
              AND HoraConfirmada = 0 AND DATE(FechaVisita) = DATE(?)
            ORDER BY IdVisita DESC LIMIT 1
        ");
        $pendingVisit->execute([$phone, $date]);
        $pendingVisitId = (int)$pendingVisit->fetchColumn();
        if ($pendingVisitId > 0 && agendaConfirmVisitHour($pdo, $pendingVisitId, $visitDate->format('H:i'), [], false)) {
            return [
                'scheduled' => true,
                'reason' => 'Se confirmó la hora de una visita ya agendada.',
                'id_visit' => $pendingVisitId,
                'date' => $date,
                'hour_confirmed' => true,
            ];
        }
    }

    $duplicateSql = $hourConfirmed
        ? "FechaVisita BETWEEN DATE_SUB(?, INTERVAL 45 MINUTE) AND DATE_ADD(?, INTERVAL 45 MINUTE)"
        : 'DATE(FechaVisita) = DATE(?)';
    $duplicate = $pdo->prepare("
        SELECT IdVisita
        FROM crm_visita
        WHERE ClienteTelefono = ?
          AND Estado IN ('agendada','reprogramada')
          AND {$duplicateSql}
        LIMIT 1
    ");
    $duplicate->execute($hourConfirmed ? [$phone, $date, $date] : [$phone, $date]);
    $existing = (int)$duplicate->fetchColumn();
    if ($existing > 0) {
        return ['scheduled' => false, 'reason' => 'La visita ya estaba agendada.', 'id_visit' => $existing, 'date' => $date, 'hour_confirmed' => $hourConfirmed];
    }

    $clientName = trim((string)($inbox['RemitenteNombre'] ?: $inbox['LeadNombre'] ?: 'Cliente WhatsApp'));
    $vehicleText = agendaDetectModel($conversation, (string)($inbox['ReplyToModelo'] ?? ''));
    $responsibleId = !empty($inbox['ResponsableUsuarioId']) ? (int)$inbox['ResponsableUsuarioId'] : null;
    $responsibleName = trim((string)($inbox['ResponsableNombre'] ?? '')) ?: null;

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $insertVisit = $pdo->prepare("
            INSERT INTO crm_visita
                (IdLead, IdInboxOrigen, IdCliente, IdVehiculo, ResponsableUsuarioId, ClienteNombre, ClienteTelefono,
                 VehiculoTexto, ResponsableNombre, Canal, FechaVisita, HoraConfirmada, Observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertVisit->execute([
            !empty($inbox['IdLead']) ? (int)$inbox['IdLead'] : null,
            $idInbox,
            !empty($inbox['IdCliente']) ? (int)$inbox['IdCliente'] : (!empty($inbox['LeadCliente']) ? (int)$inbox['LeadCliente'] : null),
            (string)($inbox['IdVehiculo'] ?: $inbox['LeadVehiculo'] ?: '') ?: null,
            $responsibleId,
            mb_substr($clientName, 0, 160),
            mb_substr($phone, 0, 40),
            $vehicleText !== null ? mb_substr($vehicleText, 0, 160) : null,
            $responsibleName,
            ucfirst((string)($inbox['Canal'] ?? 'WhatsApp')),
            $date,
            $hourConfirmed ? 1 : 0,
            mb_substr('Creada automáticamente desde conversación WhatsApp. Mensaje inbox #'.$idInbox.'. Contexto: '.$conversation, 0, 3000),
        ]);
        $idVisit = (int)$pdo->lastInsertId();

        $dateLabel = $hourConfirmed ? $visitDate->format('d/m/Y H:i') : $visitDate->format('d/m/Y').' - hora pendiente';
        $alertType = $hourConfirmed ? 'visita_agendada' : 'visita_pendiente';
        $severity = $hourConfirmed ? 'info' : 'warning';
        $title = ($hourConfirmed ? 'Visita agendada: ' : 'Visita agendada sin hora: ').$clientName.' - '.$dateLabel;
        $body = trim(implode(' · ', array_filter([
            $vehicleText,
            $hourConfirmed ? null : 'Confirmar hora desde Agenda',
            $phone,
            $responsibleName ? 'Responsable: '.$responsibleName : 'Sin responsable asignado',
        ])));
        $insertAlert = $pdo->prepare("
            INSERT INTO internal_alert
                (Tipo, Severidad, Titulo, Cuerpo, SourceType, SourceId, ResponsableUsuarioId, FechaEvento)
            VALUES (?, ?, ?, ?, 'crm_visita', ?, ?, ?)
            ON DUPLICATE KEY UPDATE Titulo = VALUES(Titulo), Cuerpo = VALUES(Cuerpo), FechaEvento = VALUES(FechaEvento)
        ");
        $insertAlert->execute([$alertType, $severity, $title, $body, $idVisit, $responsibleId, $date]);

        if (!empty($inbox['IdLead'])) {
            $closePending = $pdo->prepare("
                UPDATE internal_alert
                SET Estado = 'cerrada', FechaActualizacion = NOW()
                WHERE Tipo = 'visita_pendiente' AND SourceType = 'commercial_lead' AND SourceId = ?
            ");
            $closePending->execute([(int)$inbox['IdLead']]);

            if ($hourConfirmed) {
                $completePendingAction = $pdo->prepare("
                    UPDATE ia_accion_sugerida
                    SET Estado = 'ejecutada', FechaEjecucion = NOW(), ResultadoEjecucion = ?
                    WHERE TipoAccion = 'completar_visita' AND IdLead = ? AND Estado IN ('pendiente','confirmada')
                ");
                $completePendingAction->execute(['La visita fue agendada automáticamente #'.$idVisit, (int)$inbox['IdLead']]);
            }
        }

        if ($hourConfirmed) {
            $insertAction = $pdo->prepare("
                INSERT INTO ia_accion_sugerida
                    (TipoAccion, IdLead, IdInbox, ClienteNombre, ClienteTelefono, VehiculoTexto, ResponsableUsuarioId,
                     ResponsableNombre, Prioridad, Estado, MensajeOrigen, Motivo, Payload, FechaSugerida, FechaEjecucion, ResultadoEjecucion)
                VALUES ('crear_visita', ?, ?, ?, ?, ?, ?, ?, 'alta', 'ejecutada', ?, ?, ?, ?, NOW(), ?)
            ");
            $insertAction->execute([
                !empty($inbox['IdLead']) ? (int)$inbox['IdLead'] : null,
                $idInbox,
                $clientName,
                $phone,
                $vehicleText,
                $responsibleId,
                $responsibleName,
                mb_substr($conversation, 0, 2000),
                $detected['reason'],
                json_encode(['generated_by' => 'chat_visit_detector_v2', 'auto_executable' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $date,
                'Visita creada #'.$idVisit,
            ]);
        } else {
            $insertAction = $pdo->prepare("
                INSERT INTO ia_accion_sugerida
                    (TipoAccion, IdLead, IdInbox, ClienteNombre, ClienteTelefono, VehiculoTexto, ResponsableUsuarioId,
                     ResponsableNombre, Prioridad, Estado, MensajeOrigen, Motivo, Payload, FechaSugerida)
                VALUES ('completar_visita', ?, ?, ?, ?, ?, ?, ?, 'alta', 'pendiente', ?, ?, ?, ?)
            ");
            $insertAction->execute([
                !empty($inbox['IdLead']) ? (int)$inbox['IdLead'] : null,
                $idInbox,
                $clientName,
                $phone,
                $vehicleText,
                $responsibleId,
                $responsibleName,
                mb_substr($conversation, 0, 2000),
                'La visita tiene fecha confirmada y hora pendiente.',
                json_encode(['generated_by' => 'chat_visit_detector_v2', 'missing' => 'time', 'id_visit' => $idVisit], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $date,
            ]);
        }

        if (!empty($inbox['IdLead'])) {
            $updateLead = $pdo->prepare("
                UPDATE commercial_lead
                SET Estado = 'visita_agendada', ProximoContacto = ?, FechaActualizacion = NOW()
                WHERE IdLead = ? AND Estado NOT IN ('ganado','perdido','cerrado')
            ");
            $updateLead->execute([$date, (int)$inbox['IdLead']]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($ownsTransaction) {
        try {
            $eventPayload = [
                'id_visita' => $idVisit,
                'id_lead' => !empty($inbox['IdLead']) ? (int)$inbox['IdLead'] : null,
                'cliente' => $clientName,
                'telefono' => $phone,
                'modelo' => $vehicleText,
                'fecha_visita' => $date,
                'hora_confirmada' => $hourConfirmed,
                'responsable_usuario_id' => $responsibleId,
            ];
            $idEvent = n8nStoreEvent($pdo, 'visita_agendada', $eventPayload, 'crm_visita', $idVisit);
            n8nDispatch($pdo, 'visita_agendada', ['id_event' => $idEvent] + $eventPayload, 'crm_visita', $idVisit);
        } catch (Throwable) {}
    }

    return ['scheduled' => true, 'reason' => $detected['reason'], 'id_visit' => $idVisit, 'date' => $date, 'hour_confirmed' => $hourConfirmed];
}

/** @param array<string,mixed> $inbox @param array<string,mixed> $detected */
function agendaRecordPendingVisit(PDO $pdo, array $inbox, array $detected, string $conversation): bool
{
    $idLead = (int)($inbox['IdLead'] ?? 0);
    $idInbox = (int)($inbox['IdInbox'] ?? 0);
    $sourceType = $idLead > 0 ? 'commercial_lead' : 'commercial_inbox_message';
    $sourceId = $idLead > 0 ? $idLead : $idInbox;
    if ($sourceId <= 0) {
        return false;
    }

    $clientName = trim((string)($inbox['RemitenteNombre'] ?: $inbox['LeadNombre'] ?: 'Cliente WhatsApp'));
    $phone = whatsappFormatearTelefono((string)($inbox['Telefono'] ?? '')) ?: preg_replace('/\D+/', '', (string)($inbox['Telefono'] ?? ''));
    $dateHint = $detected['date_hint'] instanceof DateTimeImmutable ? $detected['date_hint'] : null;
    $dateLabel = $dateHint ? $dateHint->format('d/m/Y') : null;
    $reason = trim((string)($detected['reason'] ?? '')) ?: 'La conversación muestra intención de visita, pero faltan datos para agendar.';
    $body = implode(' · ', array_filter([
        $dateLabel ? 'Fecha mencionada: '.$dateLabel : null,
        $dateLabel ? 'Falta confirmar la hora desde el panel' : 'Falta confirmar la fecha y la hora desde el panel',
        $phone !== '' ? $phone : null,
    ]));
    $responsibleId = !empty($inbox['ResponsableUsuarioId']) ? (int)$inbox['ResponsableUsuarioId'] : null;

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $alert = $pdo->prepare("
            INSERT INTO internal_alert
                (Tipo, Severidad, Titulo, Cuerpo, Estado, SourceType, SourceId, ResponsableUsuarioId, FechaEvento)
            VALUES ('visita_pendiente', 'warning', ?, ?, 'abierta', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                Titulo = VALUES(Titulo), Cuerpo = VALUES(Cuerpo), Estado = 'abierta',
                ResponsableUsuarioId = VALUES(ResponsableUsuarioId), FechaEvento = VALUES(FechaEvento),
                FechaActualizacion = NOW()
        ");
        $alert->execute([
            mb_substr('Visita pendiente de completar: '.$clientName, 0, 220),
            mb_substr($body, 0, 3000),
            $sourceType,
            $sourceId,
            $responsibleId,
            $dateHint?->format('Y-m-d H:i:s'),
        ]);

        if ($idLead > 0) {
            $lead = $pdo->prepare("
                UPDATE commercial_lead
                SET Estado = 'visita_pendiente', ProximoContacto = COALESCE(?, ProximoContacto), FechaActualizacion = NOW()
                WHERE IdLead = ? AND Estado NOT IN ('visita_agendada','ganado','perdido','cerrado')
            ");
            $lead->execute([$dateHint?->format('Y-m-d H:i:s'), $idLead]);
        }

        $actionWhere = $idLead > 0 ? 'IdLead = ?' : 'IdInbox = ?';
        $findAction = $pdo->prepare("
            SELECT IdAccion FROM ia_accion_sugerida
            WHERE TipoAccion = 'completar_visita' AND {$actionWhere} AND Estado IN ('pendiente','confirmada')
            ORDER BY IdAccion DESC LIMIT 1
        ");
        $findAction->execute([$idLead > 0 ? $idLead : $idInbox]);
        $idAction = (int)$findAction->fetchColumn();

        if ($idAction > 0) {
            $updateAction = $pdo->prepare("
                UPDATE ia_accion_sugerida
                SET IdInbox = ?, MensajeOrigen = ?, Motivo = ?, FechaSugerida = ?, FechaActualizacion = NOW()
                WHERE IdAccion = ?
            ");
            $updateAction->execute([$idInbox, mb_substr($conversation, 0, 2000), $reason, $dateHint?->format('Y-m-d H:i:s'), $idAction]);
        } else {
            $insertAction = $pdo->prepare("
                INSERT INTO ia_accion_sugerida
                    (TipoAccion, IdLead, IdInbox, ClienteNombre, ClienteTelefono, ResponsableUsuarioId,
                     Prioridad, Estado, MensajeOrigen, Motivo, Payload, FechaSugerida)
                VALUES ('completar_visita', ?, ?, ?, ?, ?, 'alta', 'pendiente', ?, ?, ?, ?)
            ");
            $insertAction->execute([
                $idLead > 0 ? $idLead : null,
                $idInbox,
                mb_substr($clientName, 0, 160),
                mb_substr($phone, 0, 40),
                $responsibleId,
                mb_substr($conversation, 0, 2000),
                $reason,
                json_encode(['generated_by' => 'chat_visit_detector_v2', 'missing' => $dateHint ? 'time' : 'date_and_time'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $dateHint?->format('Y-m-d H:i:s'),
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** @return array{total:int,scheduled:int,pending:int,skipped:int} */
function agendaAnalyzeSavedConversations(PDO $pdo, int $limit = 50): array
{
    agendaEnsureSchema($pdo);
    aiEnsureSchema($pdo);
    $limit = max(1, min(200, $limit));
    $ids = $pdo->query("
        SELECT IdInbox FROM commercial_inbox_message
        WHERE Direccion = 'inbound'
        ORDER BY COALESCE(FechaRecibido, FechaAlta) DESC, IdInbox DESC
        LIMIT {$limit}
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $result = ['total' => count($ids), 'scheduled' => 0, 'pending' => 0, 'skipped' => 0];
    foreach ($ids as $idInbox) {
        $beforePending = (int)$pdo->query("SELECT COUNT(*) FROM internal_alert WHERE Tipo = 'visita_pendiente' AND Estado = 'abierta'")->fetchColumn();
        $visit = agendaMaybeScheduleFromInbox($pdo, (int)$idInbox, false);
        if ($visit['scheduled']) {
            $result['scheduled']++;
            continue;
        }
        $afterPending = (int)$pdo->query("SELECT COUNT(*) FROM internal_alert WHERE Tipo = 'visita_pendiente' AND Estado = 'abierta'")->fetchColumn();
        if ($afterPending > $beforePending) {
            $result['pending']++;
        } else {
            $result['skipped']++;
        }
    }
    return $result;
}

function agendaConversationText(PDO $pdo, int $idLead, string $phone): string
{
    $where = [];
    $params = [];
    if ($idLead > 0) {
        $where[] = 'IdLead = ?';
        $params[] = $idLead;
    }
    $phone = whatsappFormatearTelefono($phone) ?: preg_replace('/\D+/', '', $phone);
    if ($phone !== '') {
        $where[] = 'Telefono = ?';
        $params[] = $phone;
    }
    if ($where === []) {
        return '';
    }

    $stmt = $pdo->prepare("
        SELECT Mensaje, Direccion, ReplyToModelo
        FROM (
            SELECT Mensaje, Direccion, ReplyToModelo, COALESCE(FechaRecibido, FechaAlta) AS FechaOrden, IdInbox
            FROM commercial_inbox_message
            WHERE (".implode(' OR ', $where).")
              AND COALESCE(FechaRecibido, FechaAlta) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY COALESCE(FechaRecibido, FechaAlta) DESC, IdInbox DESC
            LIMIT 20
        ) recent
        ORDER BY FechaOrden ASC, IdInbox ASC
    ");
    $stmt->execute($params);

    $lines = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $prefix = (string)$row['Direccion'] === 'outbound' ? 'Equipo' : 'Cliente';
        $context = trim((string)($row['ReplyToModelo'] ?? ''));
        $lines[] = $prefix.': '.trim((string)$row['Mensaje']).($context !== '' ? ' [modelo citado: '.$context.']' : '');
    }
    return implode("\n", $lines);
}

function agendaHasRecentVisitPrompt(PDO $pdo, string $phone): bool
{
    $phone = whatsappFormatearTelefono($phone) ?: preg_replace('/\D+/', '', $phone);
    if ($phone === '') {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notificacion_whatsapp
        WHERE Telefono = ? AND Estado = 'enviado'
          AND Template LIKE 'auto_respuesta_modelo_%_cierre'
          AND FechaEnvio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$phone]);
    return (int)$stmt->fetchColumn() > 0;
}

function agendaDetectModel(string $conversation, string $quotedModel = ''): ?string
{
    if (trim($quotedModel) !== '') {
        return trim($quotedModel);
    }
    $text = mb_strtolower($conversation);
    return match (true) {
        str_contains($text, 'q8 350') || str_contains($text, 'q8-350') => 'Q8 350W',
        str_contains($text, 'q8 500') || str_contains($text, 'q8-500') => 'Q8 500W',
        str_contains($text, 'ly 500') || str_contains($text, 'ly-500') => 'LY 500W',
        str_contains($text, 'sl 500') || str_contains($text, 'sl-500') => 'SL 500W',
        default => null,
    };
}

function agendaVisitRows(PDO $pdo, ?array $user = null, int $limit = 150): array
{
    agendaEnsureSchema($pdo);
    $where = [];
    $params = [];
    $role = rolNormalizado((string)($user['Rol'] ?? ''));
    if ($role === ROL_VENDEDOR) {
        $where[] = '(ResponsableUsuarioId IS NULL OR ResponsableUsuarioId = ?)';
        $params[] = (int)($user['IdUsuario'] ?? 0);
    }
    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->prepare("
        SELECT * FROM crm_visita {$whereSql}
        ORDER BY CASE WHEN Estado IN ('agendada','reprogramada') THEN 0 ELSE 1 END, FechaVisita ASC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function agendaAlertRows(PDO $pdo, ?array $user = null, int $limit = 100): array
{
    agendaEnsureSchema($pdo);
    $where = [];
    $params = [];
    $role = rolNormalizado((string)($user['Rol'] ?? ''));
    if ($role === ROL_VENDEDOR) {
        $where[] = '(ResponsableUsuarioId IS NULL OR ResponsableUsuarioId = ?)';
        $params[] = (int)($user['IdUsuario'] ?? 0);
    }
    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("SELECT * FROM internal_alert {$whereSql} ORDER BY Estado = 'abierta' DESC, FechaAlta DESC LIMIT {$limit}");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{ok:bool,open_count:int,latest:?array<string,mixed>} */
function agendaLatestVisitAlertPayload(PDO $pdo): array
{
    agendaEnsureSchema($pdo);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM internal_alert WHERE Tipo IN ('visita_agendada','visita_pendiente') AND Estado = 'abierta'")->fetchColumn();
    $latest = $pdo->query("
        SELECT IdAlert, Tipo, Titulo, Cuerpo, FechaEvento, FechaAlta
        FROM internal_alert
        WHERE Tipo IN ('visita_agendada','visita_pendiente') AND Estado = 'abierta'
        ORDER BY IdAlert DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    return [
        'ok' => true,
        'open_count' => $count,
        'latest' => $latest ? [
            'id' => (int)$latest['IdAlert'],
            'kind' => (string)$latest['Tipo'],
            'title' => (string)$latest['Titulo'],
            'body' => (string)($latest['Cuerpo'] ?? ''),
            'event_at' => (string)($latest['FechaEvento'] ?? ''),
            'created_at' => (string)$latest['FechaAlta'],
        ] : null,
    ];
}

function agendaUpdateVisitStatus(PDO $pdo, int $idVisit, string $status, array $user): bool
{
    $allowed = ['agendada','reprogramada','asistio','no_asistio','cancelada','cerrada'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    $where = 'IdVisita = ?';
    $params = [$status, $idVisit];
    if (rolNormalizado((string)($user['Rol'] ?? '')) === ROL_VENDEDOR) {
        $where .= ' AND (ResponsableUsuarioId IS NULL OR ResponsableUsuarioId = ?)';
        $params[] = (int)($user['IdUsuario'] ?? 0);
    }
    $stmt = $pdo->prepare("UPDATE crm_visita SET Estado = ?, FechaActualizacion = NOW() WHERE {$where}");
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function agendaConfirmVisitHour(PDO $pdo, int $idVisit, string $hour, array $user, bool $ensureSchema = true): bool
{
    if ($ensureSchema) {
        agendaEnsureSchema($pdo);
        aiEnsureSchema($pdo);
    }
    if (!preg_match('/^(\d{2}):(\d{2})$/', trim($hour), $matches)) {
        return false;
    }
    $hourNumber = (int)$matches[1];
    $minute = (int)$matches[2];
    if ($hourNumber < 8 || $hourNumber > 20 || $minute > 59) {
        return false;
    }

    $where = "IdVisita = ? AND Estado IN ('agendada','reprogramada') AND HoraConfirmada = 0";
    $params = [$idVisit];
    if (rolNormalizado((string)($user['Rol'] ?? '')) === ROL_VENDEDOR) {
        $where .= ' AND (ResponsableUsuarioId IS NULL OR ResponsableUsuarioId = ?)';
        $params[] = (int)($user['IdUsuario'] ?? 0);
    }
    $stmt = $pdo->prepare("SELECT * FROM crm_visita WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$visit) {
        return false;
    }

    $dateTime = new DateTimeImmutable(date('Y-m-d', strtotime((string)$visit['FechaVisita'])).' '.trim($hour), new DateTimeZone('America/Montevideo'));
    if ($dateTime <= new DateTimeImmutable('now', new DateTimeZone('America/Montevideo'))) {
        return false;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $update = $pdo->prepare("UPDATE crm_visita SET FechaVisita = ?, HoraConfirmada = 1, FechaActualizacion = NOW() WHERE IdVisita = ? AND HoraConfirmada = 0");
        $update->execute([$dateTime->format('Y-m-d H:i:s'), $idVisit]);
        if ($update->rowCount() < 1) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        $close = $pdo->prepare("
            UPDATE internal_alert SET Estado = 'cerrada', FechaActualizacion = NOW()
            WHERE Tipo = 'visita_pendiente'
              AND ((SourceType = 'crm_visita' AND SourceId = ?)
                OR (SourceType = 'commercial_lead' AND SourceId = ?))
        ");
        $close->execute([$idVisit, (int)($visit['IdLead'] ?? 0)]);

        $title = 'Visita agendada: '.(string)$visit['ClienteNombre'].' - '.$dateTime->format('d/m/Y H:i');
        $body = trim(implode(' · ', array_filter([
            $visit['VehiculoTexto'] ?? null,
            $visit['ClienteTelefono'] ?? null,
            !empty($visit['ResponsableNombre']) ? 'Responsable: '.$visit['ResponsableNombre'] : 'Sin responsable asignado',
        ])));
        $alert = $pdo->prepare("
            INSERT INTO internal_alert
                (Tipo, Severidad, Titulo, Cuerpo, Estado, SourceType, SourceId, ResponsableUsuarioId, FechaEvento)
            VALUES ('visita_agendada', 'info', ?, ?, 'abierta', 'crm_visita', ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                Titulo = VALUES(Titulo), Cuerpo = VALUES(Cuerpo), Estado = 'abierta',
                ResponsableUsuarioId = VALUES(ResponsableUsuarioId), FechaEvento = VALUES(FechaEvento),
                FechaActualizacion = NOW()
        ");
        $alert->execute([$title, $body, $idVisit, $visit['ResponsableUsuarioId'] ?: null, $dateTime->format('Y-m-d H:i:s')]);

        $completeAction = $pdo->prepare("
            UPDATE ia_accion_sugerida
            SET Estado = 'ejecutada', FechaEjecucion = NOW(), ResultadoEjecucion = ?
            WHERE TipoAccion = 'completar_visita' AND Estado IN ('pendiente','confirmada')
              AND ((IdLead IS NOT NULL AND IdLead = ?) OR IdInbox = ?)
        ");
        $completeAction->execute(['Hora confirmada para visita #'.$idVisit, (int)($visit['IdLead'] ?? 0), (int)($visit['IdInboxOrigen'] ?? 0)]);

        if (!empty($visit['IdLead'])) {
            $lead = $pdo->prepare("
                UPDATE commercial_lead
                SET Estado = 'visita_agendada', ProximoContacto = ?, FechaActualizacion = NOW()
                WHERE IdLead = ? AND Estado NOT IN ('ganado','perdido','cerrado')
            ");
            $lead->execute([$dateTime->format('Y-m-d H:i:s'), (int)$visit['IdLead']]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($ownsTransaction) {
        try {
            $eventPayload = [
                'id_visita' => $idVisit,
                'id_lead' => !empty($visit['IdLead']) ? (int)$visit['IdLead'] : null,
                'fecha_visita' => $dateTime->format('Y-m-d H:i:s'),
                'responsable_usuario_id' => !empty($visit['ResponsableUsuarioId']) ? (int)$visit['ResponsableUsuarioId'] : null,
            ];
            $idEvent = n8nStoreEvent($pdo, 'visita_hora_confirmada', $eventPayload, 'crm_visita', $idVisit);
            n8nDispatch($pdo, 'visita_hora_confirmada', ['id_event' => $idEvent] + $eventPayload, 'crm_visita', $idVisit);
        } catch (Throwable) {}
    }

    return true;
}

function agendaUpdateAlertStatus(PDO $pdo, int $idAlert, string $status, array $user): bool
{
    if (!in_array($status, ['leida','cerrada'], true)) {
        return false;
    }
    $where = 'IdAlert = ?';
    $params = [$status, $idAlert];
    if (rolNormalizado((string)($user['Rol'] ?? '')) === ROL_VENDEDOR) {
        $where .= ' AND (ResponsableUsuarioId IS NULL OR ResponsableUsuarioId = ?)';
        $params[] = (int)($user['IdUsuario'] ?? 0);
    }
    $stmt = $pdo->prepare("UPDATE internal_alert SET Estado = ?, FechaActualizacion = NOW() WHERE {$where}");
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}
