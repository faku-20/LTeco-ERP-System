<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/whatsapp.php';

function aiEnsureSchema(PDO $pdo): void
{
    ltecoRequireSchemaTables($pdo, [
        'ai_instruction_entry',
        'commercial_lead',
        'commercial_inbox_message',
        'ai_conversation_example',
        'ia_accion_sugerida',
        'ai_usage_log',
    ], 'AI');
    ltecoRequireSchemaColumns($pdo, [
        'commercial_inbox_message' => ['ReplyToWaMessageId', 'ReplyToModelo'],
    ], 'AI');

    aiEnsureDefaultInstructions($pdo);
}

function aiEnsureDefaultInstructions(PDO $pdo): void
{
    $defaults = [
        'tono' => ['Tono de respuesta', 'Responder breve, claro, amable y en español rioplatense. No usar presión comercial.'],
        'reglas' => ['Reglas comerciales', 'No inventar precios, descuentos, promociones, stock, financiación ni beneficios. Si falta un dato, pedir confirmación a un asesor.'],
        'modelos' => ['Modelos conocidos', 'Q8 350W: motor 350W, autonomía aproximada 45km, velocidad máxima 45km/h, carga 5 a 6 hs, freno a tambor. Q8 500W, LY 500W y SL 500W: motor 500W, autonomía aproximada 50km, velocidad máxima 45km/h, carga 5 a 6 hs, freno a disco.'],
        'showroom' => ['Showroom', 'Si el cliente quiere ver una moto, sugerir coordinar visita al showroom. No decir prueba de manejo.'],
        'prohibidos' => ['Prohibidos', 'No confirmar ventas, reservas, entregas, colores disponibles, stock ni precios vigentes desde la IA.'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO ai_instruction_entry (Clave, Titulo, Cuerpo, Activo)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE Clave = VALUES(Clave)
    ");
    foreach ($defaults as $key => $row) {
        $stmt->execute([$key, $row[0], $row[1]]);
    }
}

function aiInstructionContext(PDO $pdo): string
{
    aiEnsureSchema($pdo);
    $rows = $pdo->query("
        SELECT Titulo, Cuerpo
        FROM ai_instruction_entry
        WHERE Activo = 1
        ORDER BY IdInstruction ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return implode("\n", array_map(
        static fn(array $row): string => '- ' . (string)$row['Titulo'] . ': ' . trim((string)$row['Cuerpo']),
        $rows
    ));
}

/**
 * Vincula cada respuesta enviada por el equipo con la pregunta que contestó.
 * El proceso es local: no envía el historial al proveedor de IA.
 *
 * @return array{total:int,created:int,updated:int,skipped:int}
 */
function aiSyncConversationExamples(PDO $pdo): array
{
    aiEnsureSchema($pdo);
    $outbound = $pdo->query("
        SELECT IdInbox, IdLead, Canal, Telefono, Mensaje, RawPayload,
               COALESCE(FechaRecibido, FechaAlta) AS FechaMensaje
        FROM commercial_inbox_message
        WHERE Direccion = 'outbound' AND Estado = 'enviado' AND TRIM(Mensaje) <> ''
        ORDER BY IdInbox ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byId = $pdo->prepare("
        SELECT IdInbox, Mensaje, COALESCE(FechaRecibido, FechaAlta) AS FechaMensaje
        FROM commercial_inbox_message
        WHERE IdInbox = ? AND Direccion = 'inbound'
        LIMIT 1
    ");
    $previous = $pdo->prepare("
        SELECT IdInbox, Mensaje, COALESCE(FechaRecibido, FechaAlta) AS FechaMensaje
        FROM commercial_inbox_message
        WHERE Direccion = 'inbound' AND IdInbox < ?
          AND ((? IS NOT NULL AND IdLead = ?) OR (? <> '' AND Telefono = ?))
        ORDER BY IdInbox DESC
        LIMIT 1
    ");
    $exists = $pdo->prepare('SELECT IdExample FROM ai_conversation_example WHERE IdInboxRespuesta = ?');
    $upsert = $pdo->prepare("
        INSERT INTO ai_conversation_example
            (IdInboxPregunta, IdInboxRespuesta, Canal, Pregunta, Respuesta, FechaConversacion)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            IdInboxPregunta = VALUES(IdInboxPregunta), Canal = VALUES(Canal),
            Pregunta = VALUES(Pregunta), Respuesta = VALUES(Respuesta),
            FechaConversacion = VALUES(FechaConversacion)
    ");

    $created = 0;
    $updated = 0;
    $skipped = 0;
    foreach ($outbound as $answer) {
        $replyTo = 0;
        $raw = json_decode((string)($answer['RawPayload'] ?? ''), true);
        if (is_array($raw)) {
            $replyTo = (int)($raw['reply_to_inbox_id'] ?? 0);
        }

        if ($replyTo > 0) {
            $byId->execute([$replyTo]);
            $question = $byId->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $leadId = isset($answer['IdLead']) ? (int)$answer['IdLead'] : null;
            $phone = trim((string)($answer['Telefono'] ?? ''));
            $previous->execute([(int)$answer['IdInbox'], $leadId, $leadId, $phone, $phone]);
            $question = $previous->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$question || trim((string)$question['Mensaje']) === '') {
            $skipped++;
            continue;
        }

        $exists->execute([(int)$answer['IdInbox']]);
        $wasStored = (bool)$exists->fetchColumn();
        $upsert->execute([
            (int)$question['IdInbox'],
            (int)$answer['IdInbox'],
            mb_substr((string)$answer['Canal'], 0, 32),
            mb_substr(trim((string)$question['Mensaje']), 0, 2000),
            mb_substr(trim((string)$answer['Mensaje']), 0, 2000),
            (string)$answer['FechaMensaje'],
        ]);
        $wasStored ? $updated++ : $created++;
    }

    return ['total' => count($outbound), 'created' => $created, 'updated' => $updated, 'skipped' => $skipped];
}

function aiConversationExamplesContext(PDO $pdo, string $message, int $limit = 6): string
{
    aiSyncConversationExamples($pdo);
    $normalized = mb_strtolower($message);
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = array_flip(['hola','buenas','buenos','dias','día','tardes','noches','que','qué','como','cómo','para','por','con','una','uno','unos','unas','del','las','los','esto','esta','este','hay','tienen','tiene','quiero','queria','quería','saber','gracias']);
    $tokens = array_values(array_unique(array_filter($tokens, static fn(string $token): bool => mb_strlen($token) >= 3 && !isset($stop[$token]))));
    usort($tokens, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
    $tokens = array_slice($tokens, 0, 8);

    $params = [];
    if ($tokens) {
        $where = [];
        foreach ($tokens as $token) {
            $where[] = 'Pregunta LIKE ?';
            $params[] = '%' . $token . '%';
        }
        $whereSql = 'WHERE ' . implode(' OR ', $where);
    } else {
        $whereSql = '';
    }
    $stmt = $pdo->prepare("
        SELECT Pregunta, Respuesta, FechaConversacion
        FROM ai_conversation_example
        {$whereSql}
        ORDER BY FechaConversacion DESC, IdExample DESC
        LIMIT 80
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $question = mb_strtolower((string)$row['Pregunta']);
        $row['_score'] = array_sum(array_map(static fn(string $token): int => str_contains($question, $token) ? 1 : 0, $tokens));
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: strcmp((string)$b['FechaConversacion'], (string)$a['FechaConversacion']));
    $rows = array_slice($rows, 0, max(1, min(10, $limit)));
    if (!$rows) {
        return '- No hay respuestas previas comparables guardadas.';
    }

    $lines = [];
    foreach ($rows as $index => $row) {
        $lines[] = ($index + 1) . ". Cliente: " . mb_substr(trim((string)$row['Pregunta']), 0, 500)
            . "\nEquipo: " . mb_substr(trim((string)$row['Respuesta']), 0, 700);
    }
    return implode("\n\n", $lines);
}

function aiConfig(): array
{
    return [
        'enabled' => in_array(strtolower((string)configEnv('LTECO_AI_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true),
        'provider' => (string)configEnv('LTECO_AI_PROVIDER', 'openai'),
        'base_url' => rtrim((string)configEnv('LTECO_AI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'api_key' => (string)configEnv('LTECO_AI_API_KEY', ''),
        'model' => (string)configEnv('LTECO_AI_MODEL', 'gpt-4o-mini'),
        'timeout' => max(5, (int)configEnv('LTECO_AI_TIMEOUT', 25)),
        'max_tokens' => max(200, (int)configEnv('LTECO_AI_MAX_OUTPUT_TOKENS', 900)),
        'daily_limit' => max(1, (int)configEnv('LTECO_AI_DAILY_LIMIT_PER_USER', 50)),
    ];
}

function aiEnsureEnabled(): void
{
    $cfg = aiConfig();
    if (!$cfg['enabled'] || trim($cfg['api_key']) === '') {
        throw new RuntimeException('La IA no está configurada. Revisar LTECO_AI_ENABLED y LTECO_AI_API_KEY.');
    }
}

function aiComplete(string $system, string $user, float $temperature = 0.15): string
{
    aiEnsureEnabled();
    $cfg = aiConfig();
    $payload = json_encode([
        'model' => $cfg['model'],
        'temperature' => $temperature,
        'max_tokens' => $cfg['max_tokens'],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($cfg['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $cfg['timeout'],
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $response = (string)curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        throw new RuntimeException('La IA no respondió: ' . $error);
    }
    if ($http < 200 || $http >= 300) {
        throw new RuntimeException('La IA no respondió correctamente: HTTP ' . $http);
    }

    $json = json_decode($response, true);
    $content = trim((string)($json['choices'][0]['message']['content'] ?? ''));
    if ($content === '') {
        throw new RuntimeException('La IA respondió vacío.');
    }

    return mb_substr($content, 0, 5000);
}

function aiLogUsage(PDO $pdo, string $ruta, string $accion, string $estado, int $chars): void
{
    aiEnsureSchema($pdo);
    $usuario = usuarioActual();
    $stmt = $pdo->prepare("
        INSERT INTO ai_usage_log (IdUsuario, Ruta, Accion, Provider, PromptChars, Estado)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        isset($usuario['IdUsuario']) ? (int)$usuario['IdUsuario'] : null,
        mb_substr($ruta, 0, 120),
        mb_substr($accion, 0, 120),
        mb_substr((string)aiConfig()['provider'], 0, 80),
        $chars,
        mb_substr($estado, 0, 40),
    ]);
}

function aiEnforceDailyLimit(PDO $pdo, string $ruta = 'ia.preguntar'): void
{
    aiEnsureSchema($pdo);
    $usuario = usuarioActual();
    $idUsuario = (int)($usuario['IdUsuario'] ?? 0);
    if ($idUsuario <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ai_usage_log
        WHERE IdUsuario = ?
          AND Ruta = ?
          AND Estado IN ('success','error')
          AND DATE(FechaAlta) = CURDATE()
    ");
    $stmt->execute([$idUsuario, $ruta]);
    if ((int)$stmt->fetchColumn() >= (int)aiConfig()['daily_limit']) {
        aiLogUsage($pdo, $ruta, 'limit', 'limited', 0);
        throw new RuntimeException('Límite diario de IA alcanzado para este usuario.');
    }
}

function aiScopesForCurrentUser(): array
{
    $scopes = [
        'general' => 'General',
        'comercial' => 'Comercial',
        'tareas' => 'Tareas',
        'balance' => 'Balance',
        'reportes' => 'Reportes',
        'stock' => 'Stock',
        'postventa' => 'Postventa',
    ];

    if (esVendedor()) {
        return array_intersect_key($scopes, array_flip(['general', 'comercial', 'tareas']));
    }

    return $scopes;
}

function aiPanelContext(PDO $pdo, string $scope): string
{
    $parts = [];
    if (in_array($scope, ['general', 'comercial'], true)) {
        $parts[] = aiCommercialContext($pdo);
    }
    if (in_array($scope, ['general', 'stock'], true) && !esVendedor()) {
        $parts[] = aiStockContext($pdo);
    }
    if (in_array($scope, ['general', 'balance'], true) && !esVendedor()) {
        $parts[] = aiBalanceContext($pdo);
    }
    if (in_array($scope, ['general', 'postventa'], true) && !esVendedor()) {
        $parts[] = aiPostventaContext($pdo);
    }
    if (in_array($scope, ['general', 'reportes'], true) && !esVendedor()) {
        $parts[] = aiReportsContext($pdo);
    }
    if (in_array($scope, ['general', 'tareas'], true)) {
        $parts[] = aiTasksContext($pdo);
    }

    return implode("\n\n", array_filter($parts));
}

function aiCommercialContext(PDO $pdo): string
{
    aiEnsureSchema($pdo);
    $rows = $pdo->query("
        SELECT Nombre, Telefono, Estado, Prioridad, Origen, Mensaje, ProximoContacto
        FROM commercial_lead
        WHERE Estado NOT IN ('ganado','perdido','cerrado')
        ORDER BY COALESCE(ProximoContacto, FechaAlta) ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $lines = ['Comercial - leads abiertos:'];
    foreach ($rows as $row) {
        $lines[] = '- ' . ($row['Nombre'] ?: 'Sin nombre') . ' / ' . $row['Estado'] . ' / ' . $row['Prioridad'] . ' / ' . ($row['Telefono'] ?: 'sin teléfono') . ' / ' . mb_substr((string)$row['Mensaje'], 0, 90);
    }
    if (count($lines) === 1) {
        $lines[] = '- Sin leads abiertos guardados.';
    }
    return implode("\n", $lines);
}

function aiStockContext(PDO $pdo): string
{
    $vehiculos = (int)$pdo->query("SELECT COUNT(*) FROM vehiculo WHERE FechaVenta IS NULL")->fetchColumn();
    $repuestosSinStock = (int)$pdo->query("SELECT COUNT(*) FROM producto WHERE TipoProducto = 'Repuesto' AND COALESCE(Stock, 0) <= 0")->fetchColumn();
    return "Stock:\n- Vehículos sin venta registrada: {$vehiculos}\n- Repuestos sin stock: {$repuestosSinStock}";
}

function aiBalanceContext(PDO $pdo): string
{
    $ventasMes = (float)$pdo->query("SELECT COALESCE(SUM(Total),0) FROM venta WHERE EstadoVenta <> 'Anulada' AND FechaVenta >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    $gastosMes = (float)$pdo->query("SELECT COALESCE(SUM(Monto),0) FROM gasto WHERE Estado <> 'Anulado' AND FechaGasto >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    return "Balance del mes:\n- Ventas: {$ventasMes}\n- Gastos: {$gastosMes}\n- Neto aproximado sin recalcular reglas: " . ($ventasMes - $gastosMes);
}

function aiPostventaContext(PDO $pdo): string
{
    $pendientes = (int)$pdo->query("SELECT COUNT(*) FROM service_vehiculo WHERE Estado = 'Pendiente'")->fetchColumn();
    $casos = dbTieneTabla($pdo, 'postventa_historial_tecnico')
        ? (int)$pdo->query("SELECT COUNT(*) FROM postventa_historial_tecnico WHERE Fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn()
        : 0;
    return "Postventa:\n- Services pendientes: {$pendientes}\n- Intervenciones técnicas últimos 30 días: {$casos}";
}

function aiReportsContext(PDO $pdo): string
{
    $ventas = (int)$pdo->query("SELECT COUNT(*) FROM venta WHERE EstadoVenta <> 'Anulada' AND FechaVenta >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $clientes = (int)$pdo->query("SELECT COUNT(*) FROM cliente WHERE FechaAlta >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    return "Reportes últimos 30 días:\n- Ventas confirmadas: {$ventas}\n- Clientes creados: {$clientes}";
}

function aiTasksContext(PDO $pdo): string
{
    aiEnsureSchema($pdo);
    $acciones = (int)$pdo->query("SELECT COUNT(*) FROM ia_accion_sugerida WHERE Estado = 'pendiente'")->fetchColumn();
    return "Tareas/acciones:\n- Acciones IA pendientes: {$acciones}";
}

function aiPanelAnswer(PDO $pdo, string $scope, string $question): string
{
    aiEnsureSchema($pdo);
    $scopes = aiScopesForCurrentUser();
    $scope = array_key_exists($scope, $scopes) ? $scope : 'general';
    $usuario = usuarioActual();

    $system = implode("\n", [
        'Sos el asistente interno del panel Ltecobike.',
        'Usá solo el contexto provisto. Si falta un dato, decilo claramente.',
        'No inventes ventas, gastos, clientes, precios, márgenes, reservas, descuentos ni stock.',
        'No confirmes operaciones críticas. El usuario humano decide.',
        'Respondé en español rioplatense, breve y con pasos concretos.',
        'Base comercial IA:',
        aiInstructionContext($pdo),
    ]);

    $prompt = implode("\n", [
        'Usuario: ' . (string)($usuario['NombreCompleto'] ?? $usuario['Usuario'] ?? 'sin usuario'),
        'Rol: ' . rolActual(),
        'Ámbito solicitado: ' . $scopes[$scope],
        'Pregunta:',
        $question,
        '',
        'Contexto actual del panel:',
        aiPanelContext($pdo, $scope),
    ]);

    return aiComplete($system, $prompt, 0.15);
}

function aiDetectKnownModel(string $message): ?array
{
    $models = [
        ['name' => 'Q8 350W', 'aliases' => ['q8 350', 'q8-350', 'q8 350w', 'q8-350w'], 'summary' => 'motor de 350W, autonomía aproximada de 45km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a tambor.'],
        ['name' => 'Q8 500W', 'aliases' => ['q8 500', 'q8-500', 'q8 500w', 'q8-500w'], 'summary' => 'motor de 500W, autonomía aproximada de 50km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a disco.'],
        ['name' => 'LY 500W', 'aliases' => ['ly 500', 'ly-500', 'ly 500w', 'ly-500w'], 'summary' => 'motor de 500W, autonomía aproximada de 50km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a disco.'],
        ['name' => 'SL 500W', 'aliases' => ['sl 500', 'sl-500', 'sl 500w', 'sl-500w'], 'summary' => 'motor de 500W, autonomía aproximada de 50km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a disco.'],
    ];
    $normalized = mb_strtolower($message);
    foreach ($models as $model) {
        foreach ($model['aliases'] as $alias) {
            if (str_contains($normalized, $alias)) {
                return $model;
            }
        }
    }
    return null;
}

function aiGuardSuggestedReply(string $message, string $reply): string
{
    $model = aiDetectKnownModel($message);
    if ($model) {
        return 'Hola, buenas. Sobre la ' . $model['name'] . ': ' . $model['summary'] . ' Si te interesa, te confirmamos disponibilidad, colores y precio vigente.';
    }

    $lower = mb_strtolower($message);
    foreach (['modelo', 'modelos', 'disponible', 'disponibles', 'opciones', 'catalogo', 'catálogo'] as $needle) {
        if (str_contains($lower, $needle)) {
            return 'Hola, buenas. Te paso las opciones disponibles y decime cuál te interesa más para enviarte la información puntual de ese modelo.';
        }
    }

    $reply = trim(preg_replace('/^¡?Hola\s+[^,!?.]{2,40}[,!]\s*/iu', 'Hola, buenas. ', trim($reply)) ?: trim($reply));
    $reply = str_ireplace('prueba de manejo', 'visita al showroom', $reply);
    return mb_substr($reply, 0, 1500);
}

function aiClassifyInbox(PDO $pdo, int $idInbox): array
{
    aiEnsureSchema($pdo);
    $stmt = $pdo->prepare("
        SELECT i.*, l.Nombre AS LeadNombre, l.Estado AS LeadEstado, v.Modelo
        FROM commercial_inbox_message i
        LEFT JOIN commercial_lead l ON l.IdLead = i.IdLead
        LEFT JOIN vehiculo v ON v.IdVehiculo = i.IdVehiculo COLLATE utf8mb4_general_ci
        WHERE i.IdInbox = ?
        LIMIT 1
    ");
    $stmt->execute([$idInbox]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$message) {
        throw new RuntimeException('Mensaje no encontrado.');
    }

    $conversationExamples = aiConversationExamplesContext(
        $pdo,
        trim((string)$message['Mensaje'] . ' ' . (string)($message['ReplyToModelo'] ?? ''))
    );

    $system = implode("\n", [
        'Clasificá mensajes entrantes de WhatsApp o Instagram para Ltecobike.',
        'Devolvé solo JSON válido, sin markdown.',
        'Campos: intent, priority, summary, suggested_reply.',
        'intent: consulta_compra, precio, reserva, financiacion, postventa, reclamo, repuesto, saludo, spam u otro.',
        'priority: baja, media, alta o urgente.',
        'No uses nombre propio artificial en el saludo.',
        'No sugieras prueba de manejo; si corresponde hablá de coordinar visita al showroom.',
        'No confirmes ventas, reservas, stock ni precios.',
        'No inventes descuentos, promociones ni beneficios.',
        'Usá los ejemplos reales solo para aprender el tono, la estructura y qué información suele resultar útil.',
        'No copies de los ejemplos precios, stock, promociones, disponibilidad ni otros datos que puedan haber cambiado.',
        'Base comercial IA:',
        aiInstructionContext($pdo),
        'Respuestas reales del equipo a consultas parecidas:',
        $conversationExamples,
    ]);

    $prompt = implode("\n", [
        'Canal: ' . (string)$message['Canal'],
        'Contacto: ' . ((string)($message['RemitenteNombre'] ?: $message['Telefono'] ?: 'sin contacto')),
        'Lead: ' . (string)($message['LeadNombre'] ?: 'sin lead'),
        'Estado lead: ' . (string)($message['LeadEstado'] ?: 'sin estado'),
        'Moto vinculada: ' . (string)($message['Modelo'] ?: 'sin moto'),
        'Modelo del mensaje citado: ' . (string)($message['ReplyToModelo'] ?: 'sin modelo citado'),
        'Mensaje:',
        (string)$message['Mensaje'],
    ]);

    $raw = aiComplete($system, $prompt, 0.05);
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        $json = [
            'intent' => 'otro',
            'priority' => 'media',
            'summary' => mb_substr($raw, 0, 1000),
            'suggested_reply' => '',
        ];
    }

    $intent = mb_substr((string)($json['intent'] ?? 'otro'), 0, 64);
    $priority = in_array(($json['priority'] ?? ''), ['baja', 'media', 'alta', 'urgente'], true) ? (string)$json['priority'] : 'media';
    $summary = mb_substr((string)($json['summary'] ?? ''), 0, 1000);
    $guardMessage = trim((string)$message['Mensaje'].' '.(string)($message['ReplyToModelo'] ?? ''));
    $reply = aiGuardSuggestedReply($guardMessage, (string)($json['suggested_reply'] ?? ''));

    $upd = $pdo->prepare("
        UPDATE commercial_inbox_message
        SET AiIntent = ?, AiPrioridad = ?, AiResumen = ?, AiRespuestaSugerida = ?, AiError = NULL, FechaClasificacion = NOW()
        WHERE IdInbox = ?
    ");
    $upd->execute([$intent, $priority, $summary, $reply, $idInbox]);

    return ['intent' => $intent, 'priority' => $priority, 'summary' => $summary, 'suggested_reply' => $reply];
}

function aiAnalyzeMessageForActions(PDO $pdo, array $data): int
{
    aiEnsureSchema($pdo);
    $message = trim((string)($data['mensaje'] ?? ''));
    if ($message === '') {
        return 0;
    }

    $lower = mb_strtolower($message);
    $actions = [];
    $hasVisit = aiContainsAny($lower, ['pasar', 'voy', 'verla', 'ver la moto', 'showroom', 'local', 'agenda', 'agendar', 'visita']);
    $hasPrice = aiContainsAny($lower, ['precio', 'cuánto', 'cuanto', 'vale', 'sale', 'financia', 'financiación', 'financiacion', 'cuotas', 'cotización', 'cotizacion']);
    $hasInterest = aiContainsAny($lower, ['me interesa', 'interesa', 'quiero', 'me gusta', 'tenés', 'tenes', 'disponible', 'stock', 'modelo']);
    $urgent = aiContainsAny($lower, ['hoy', 'ahora', 'urgente', 'ya', 'mañana', 'reservo', 'seña', 'señar', 'senia']);

    if ($hasVisit) {
        $actions[] = ['crear_visita', 'El mensaje indica intención de pasar por showroom o coordinar una visita.'];
    }
    if ($hasPrice) {
        $actions[] = ['cotizar', 'El mensaje pide precio, cuotas, financiación o cotización.'];
    }
    if ($hasInterest && trim((string)($data['vehiculo_texto'] ?? '')) === '') {
        $actions[] = ['pedir_modelo_interes', 'El cliente muestra interés, pero no hay una moto/modelo identificado.'];
    }
    if ($urgent) {
        $actions[] = ['marcar_prioridad_alta', 'El mensaje contiene señales de urgencia o intención fuerte de compra/reserva.'];
    }
    if ($actions === []) {
        $actions[] = ['crear_tarea', 'No se detectó una acción específica, pero conviene crear seguimiento comercial.'];
    }

    $usuario = usuarioActual();
    $stmt = $pdo->prepare("
        INSERT INTO ia_accion_sugerida
            (TipoAccion, IdLead, IdInbox, ClienteNombre, ClienteTelefono, VehiculoTexto, ResponsableUsuarioId, ResponsableNombre, Prioridad, Estado, MensajeOrigen, Motivo, Payload)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)
    ");

    foreach ($actions as [$type, $reason]) {
        $stmt->execute([
            $type,
            $data['id_lead'] ?? null,
            $data['id_inbox'] ?? null,
            mb_substr((string)($data['cliente_nombre'] ?? ''), 0, 160),
            mb_substr((string)($data['cliente_telefono'] ?? ''), 0, 40),
            mb_substr((string)($data['vehiculo_texto'] ?? ''), 0, 160),
            isset($usuario['IdUsuario']) ? (int)$usuario['IdUsuario'] : null,
            mb_substr((string)($usuario['NombreCompleto'] ?? $usuario['Usuario'] ?? ''), 0, 160),
            $urgent ? 'alta' : 'media',
            $message,
            $reason,
            json_encode(['generated_by' => 'local_rules_v2'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    return count($actions);
}

function aiContainsAny(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }
    return false;
}

function aiAnalyzeSavedConversations(PDO $pdo, int $limit = 50): array
{
    aiEnsureSchema($pdo);
    $limit = max(1, min(200, $limit));
    $rows = $pdo->query("
        SELECT *
        FROM commercial_inbox_message
        WHERE Direccion = 'inbound'
        ORDER BY COALESCE(FechaRecibido, FechaAlta) DESC, IdInbox DESC
        LIMIT {$limit}
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $generated = 0;
    $skipped = 0;
    foreach ($rows as $row) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM ia_accion_sugerida WHERE IdInbox = ?");
        $dup->execute([(int)$row['IdInbox']]);
        if ((int)$dup->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        $generated += aiAnalyzeMessageForActions($pdo, [
            'id_lead' => $row['IdLead'],
            'id_inbox' => $row['IdInbox'],
            'mensaje' => $row['Mensaje'],
            'cliente_nombre' => $row['RemitenteNombre'],
            'cliente_telefono' => $row['Telefono'],
            'vehiculo_texto' => '',
        ]);
    }

    return ['total' => count($rows), 'generated' => $generated, 'skipped' => $skipped];
}

function aiInstructionEntries(PDO $pdo): array
{
    aiEnsureSchema($pdo);
    return $pdo->query("
        SELECT *
        FROM ai_instruction_entry
        ORDER BY IdInstruction ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function aiUpdateInstructions(PDO $pdo, array $entries): void
{
    aiEnsureSchema($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE ai_instruction_entry
            SET Titulo = ?, Cuerpo = ?, Activo = ?, ActualizadoPor = ?
            WHERE IdInstruction = ?
        ");
        $usuario = usuarioActual();
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = (int)($entry['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $stmt->execute([
                mb_substr(trim((string)($entry['titulo'] ?? '')), 0, 160),
                mb_substr(trim((string)($entry['cuerpo'] ?? '')), 0, 4000),
                isset($entry['activo']) ? 1 : 0,
                isset($usuario['IdUsuario']) ? (int)$usuario['IdUsuario'] : null,
                $id,
            ]);
        }
        registrarAuditoria($pdo, 'IA_BASE_COMERCIAL_ACTUALIZADA', 'IA', 'Base comercial IA actualizada desde panel.', []);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function aiInboxRows(PDO $pdo, string $q = '', string $canal = '', int $limit = 30): array
{
    aiEnsureSchema($pdo);
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(i.Telefono LIKE ? OR i.RemitenteNombre LIKE ? OR i.Mensaje LIKE ? OR l.Nombre LIKE ?)";
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle, $needle);
    }
    if ($canal !== '') {
        $where[] = 'i.Canal = ?';
        $params[] = $canal;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = max(1, min(100, $limit));

    $stmt = $pdo->prepare("
        SELECT i.*, l.Nombre AS LeadNombre, l.Estado AS LeadEstado, l.Prioridad AS LeadPrioridad, v.Modelo
        FROM commercial_inbox_message i
        LEFT JOIN commercial_lead l ON l.IdLead = i.IdLead
        LEFT JOIN vehiculo v ON v.IdVehiculo = i.IdVehiculo COLLATE utf8mb4_general_ci
        {$whereSql}
        ORDER BY COALESCE(i.FechaRecibido, i.FechaAlta) DESC, i.IdInbox DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function aiWhatsappOutgoingRows(PDO $pdo, string $q = '', string $estado = '', int $limit = 40): array
{
    whatsappEnsureTabla($pdo);
    $where = [];
    $params = [];
    if ($estado !== '') {
        $where[] = 'Estado = ?';
        $params[] = $estado;
    }
    if ($q !== '') {
        $where[] = '(Telefono LIKE ? OR RespuestaMeta LIKE ? OR Template LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = max(1, min(100, $limit));

    $stmt = $pdo->prepare("
        SELECT *
        FROM notificacion_whatsapp
        {$whereSql}
        ORDER BY FechaEnvio DESC, IdNotificacion DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function aiInboxEntry(PDO $pdo, int $idInbox): ?array
{
    aiEnsureSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM commercial_inbox_message WHERE IdInbox = ? LIMIT 1");
    $stmt->execute([$idInbox]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aiSetInboxError(PDO $pdo, int $idInbox, string $error): void
{
    aiEnsureSchema($pdo);
    $stmt = $pdo->prepare("UPDATE commercial_inbox_message SET AiError = ?, FechaClasificacion = NOW() WHERE IdInbox = ?");
    $stmt->execute([mb_substr($error, 0, 1000), $idInbox]);
}

function aiRecordOutboundInbox(PDO $pdo, array $entry, string $body, bool $ok, int $replyToInboxId): void
{
    aiEnsureSchema($pdo);
    $usuario = usuarioActual();
    $stmt = $pdo->prepare("
        INSERT INTO commercial_inbox_message
            (IdLead, IdCliente, IdVehiculo, Canal, Direccion, Estado, RemitenteNombre, Telefono, Mensaje, RawPayload, FechaRecibido)
        VALUES (?, ?, ?, ?, 'outbound', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $entry['IdLead'] ?? null,
        $entry['IdCliente'] ?? null,
        $entry['IdVehiculo'] ?? null,
        $entry['Canal'] ?? 'whatsapp',
        $ok ? 'enviado' : 'error',
        $usuario['NombreCompleto'] ?? $usuario['Usuario'] ?? 'Panel',
        $entry['Telefono'] ?? null,
        mb_substr($body, 0, 1500),
        json_encode(['reply_to_inbox_id' => $replyToInboxId, 'sent_ok' => $ok], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function aiSuggestedActionsRows(PDO $pdo, string $estado = 'pendiente', string $tipo = '', int $limit = 150): array
{
    aiEnsureSchema($pdo);
    $where = [];
    $params = [];
    if ($estado !== '') {
        $where[] = 'Estado = ?';
        $params[] = $estado;
    }
    if ($tipo !== '') {
        $where[] = 'TipoAccion = ?';
        $params[] = $tipo;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("
        SELECT *
        FROM ia_accion_sugerida
        {$whereSql}
        ORDER BY FechaAlta DESC, IdAccion DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function aiSuggestedActionsContactsRows(PDO $pdo, string $estado = '', string $tipo = ''): array
{
    aiEnsureSchema($pdo);
    $where = ["ClienteTelefono IS NOT NULL", "TRIM(ClienteTelefono) <> ''"];
    $params = [];
    if ($estado !== '') {
        $where[] = 'Estado = ?';
        $params[] = $estado;
    }
    if ($tipo !== '') {
        $where[] = 'TipoAccion = ?';
        $params[] = $tipo;
    }

    $stmt = $pdo->prepare("
        SELECT ClienteNombre, ClienteTelefono, TipoAccion, Estado, Prioridad, FechaAlta
        FROM ia_accion_sugerida
        WHERE ".implode(' AND ', $where)."
        ORDER BY FechaAlta DESC, IdAccion DESC
    ");
    $stmt->execute($params);

    $contacts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $phone = whatsappFormatearTelefono((string)$row['ClienteTelefono']);
        if ($phone === null || isset($contacts[$phone])) {
            continue;
        }
        $row['ClienteTelefono'] = $phone;
        $contacts[$phone] = $row;
    }

    return array_values($contacts);
}

function aiProcessSuggestedAction(PDO $pdo, int $id, string $accion): string
{
    aiEnsureSchema($pdo);
    $usuario = usuarioActual();
    $idUsuario = isset($usuario['IdUsuario']) ? (int)$usuario['IdUsuario'] : null;

    if ($accion === 'confirmar') {
        $stmt = $pdo->prepare("UPDATE ia_accion_sugerida SET Estado = 'confirmada', FechaConfirmacion = NOW(), ConfirmadaPor = ? WHERE IdAccion = ?");
        $stmt->execute([$idUsuario, $id]);
        return 'Acción confirmada.';
    }

    if ($accion === 'rechazar') {
        $stmt = $pdo->prepare("UPDATE ia_accion_sugerida SET Estado = 'rechazada', FechaConfirmacion = NOW(), ConfirmadaPor = ? WHERE IdAccion = ?");
        $stmt->execute([$idUsuario, $id]);
        return 'Acción rechazada.';
    }

    $stmt = $pdo->prepare("SELECT * FROM ia_accion_sugerida WHERE IdAccion = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Acción no encontrada.');
    }

    $resultado = 'Acción marcada como ejecutada para seguimiento humano.';
    if (($row['TipoAccion'] ?? '') === 'marcar_prioridad_alta' && !empty($row['IdLead'])) {
        $updLead = $pdo->prepare("UPDATE commercial_lead SET Prioridad = 'alta', FechaActualizacion = NOW() WHERE IdLead = ?");
        $updLead->execute([(int)$row['IdLead']]);
        $resultado = 'Lead marcado con prioridad alta.';
    }

    $upd = $pdo->prepare("
        UPDATE ia_accion_sugerida
        SET Estado = 'ejecutada', FechaEjecucion = NOW(), EjecutadaPor = ?, ResultadoEjecucion = ?
        WHERE IdAccion = ?
    ");
    $upd->execute([$idUsuario, $resultado, $id]);
    return $resultado;
}

function aiHasSentInitialAutoReply(PDO $pdo, string $phone): bool
{
    $tel = whatsappFormatearTelefono($phone);
    if ($tel === null) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacion_whatsapp WHERE Telefono = ? AND Template = 'auto_respuesta_inicial' AND Estado = 'enviado'");
    $stmt->execute([$tel]);

    return (int)$stmt->fetchColumn() > 0;
}

function aiMaybeSendInitialAutoReply(PDO $pdo, string $phone, int $idInbox): bool
{
    if (!in_array(strtolower((string)configEnv('LTECO_AI_AUTO_REPLY_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true)) {
        return false;
    }

    $tel = whatsappFormatearTelefono($phone);
    if ($tel === null) {
        return false;
    }

    $whatsapp = whatsappService($pdo);
    $testResetClaimed = $whatsapp->reclamarResetPrueba($tel);

    if (!$testResetClaimed && aiHasSentInitialAutoReply($pdo, $tel)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT Mensaje, ReplyToWaMessageId FROM commercial_inbox_message WHERE IdInbox = ? LIMIT 1');
        $stmt->execute([$idInbox]);
        $inbox = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $message = (string)($inbox['Mensaje'] ?? '');

        if (!$testResetClaimed) {
            $prior = $pdo->prepare("SELECT COUNT(*) FROM commercial_inbox_message WHERE Direccion = 'inbound' AND Telefono = ? AND IdInbox < ?");
            $prior->execute([$tel, $idInbox]);
            if ((int)$prior->fetchColumn() > 0) {
                return false;
            }
        }

        $eligibility = new \Lteco\Application\Whatsapp\InitialAutoReplyEligibility();
        if (!$eligibility->shouldReply($message, (string)($inbox['ReplyToWaMessageId'] ?? ''))) {
            if ($testResetClaimed) {
                $whatsapp->liberarResetPrueba($tel);
            }
            return false;
        }

        $reply = (new \Lteco\Application\Whatsapp\InitialAutoReplyBuilder())->build($message);
        $sent = enviarWhatsAppTextoConPdo(
            $pdo,
            $tel,
            $reply['body'],
            $idInbox,
            'auto_respuesta_inicial'
        );
        if (!$sent) {
            if ($testResetClaimed) {
                $whatsapp->liberarResetPrueba($tel);
            }
            return false;
        }

        if ($testResetClaimed) {
            $whatsapp->completarResetPrueba($tel);
        }

        $lastMediaIndex = count($reply['media']) - 1;
        foreach ($reply['media'] as $index => $media) {
            enviarWhatsAppImagenConPdo(
                $pdo,
                $tel,
                $media['url'],
                $media['caption'],
                $idInbox,
                'auto_respuesta_inicial_media_'.$media['model_key'].($index === $lastMediaIndex && $reply['final_body'] !== null ? '_ultimo' : '')
            );
        }

        if ($reply['final_body'] !== null && $reply['media'] === []) {
            enviarWhatsAppTextoConPdo(
                $pdo,
                $tel,
                $reply['final_body'],
                $idInbox,
                'auto_respuesta_inicial_cierre'
            );
        }

        return true;
    } catch (Throwable $e) {
        if ($testResetClaimed) {
            $whatsapp->liberarResetPrueba($tel);
        }
        throw $e;
    }
}

function aiMaybeSendInitialAutoReplyClosure(PDO $pdo, string $waMessageId, string $status): bool
{
    if (!in_array(strtolower(trim($status)), ['delivered', 'read'], true)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT Telefono, IdReferencia FROM notificacion_whatsapp WHERE (WaMessageId = ? OR RespuestaMeta LIKE ?) AND Template LIKE 'auto_respuesta_inicial_media_%_ultimo' ORDER BY IdNotificacion DESC LIMIT 1");
    $stmt->execute([$waMessageId, '%'.$waMessageId.'%']);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$media) {
        return false;
    }

    $phone = (string)$media['Telefono'];
    $idInbox = (int)$media['IdReferencia'];
    $lockName = 'wa_initial_close_'.sha1($phone.'|'.$idInbox);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 2)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        return false;
    }

    try {
        $sent = $pdo->prepare("SELECT COUNT(*) FROM notificacion_whatsapp WHERE Telefono = ? AND IdReferencia = ? AND Template = 'auto_respuesta_inicial_cierre' AND Estado = 'enviado'");
        $sent->execute([$phone, $idInbox]);
        if ((int)$sent->fetchColumn() > 0) {
            return false;
        }

        $inbox = $pdo->prepare('SELECT Mensaje FROM commercial_inbox_message WHERE IdInbox = ? LIMIT 1');
        $inbox->execute([$idInbox]);
        $message = (string)$inbox->fetchColumn();
        $reply = (new \Lteco\Application\Whatsapp\InitialAutoReplyBuilder())->build($message);
        if ($reply['final_body'] === null) {
            return false;
        }

        return enviarWhatsAppTextoConPdo($pdo, $phone, $reply['final_body'], $idInbox, 'auto_respuesta_inicial_cierre');
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

function aiMaybeSendModelAutoReply(PDO $pdo, string $phone, int $idInbox): bool
{
    if (!in_array(strtolower((string)configEnv('LTECO_AI_AUTO_REPLY_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true)) {
        return false;
    }

    $tel = whatsappFormatearTelefono($phone);
    if ($tel === null || !aiHasSentInitialAutoReply($pdo, $tel)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT Mensaje, ReplyToModelo FROM commercial_inbox_message WHERE IdInbox = ? LIMIT 1');
    $stmt->execute([$idInbox]);
    $inbox = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $message = trim((string)($inbox['Mensaje'] ?? '').' '.(string)($inbox['ReplyToModelo'] ?? ''));
    $reply = (new \Lteco\Application\Whatsapp\InitialAutoReplyBuilder())->buildModelFollowUp($message);
    if ($reply === null) {
        return false;
    }

    $template = 'auto_respuesta_modelo_'.$reply['model_key'];
    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM notificacion_whatsapp WHERE Telefono = ? AND Template = ? AND Estado = \'enviado\'');
    $duplicate->execute([$tel, $template]);
    if ((int)$duplicate->fetchColumn() > 0) {
        return false;
    }

    $sent = enviarWhatsAppTextoConPdo($pdo, $tel, $reply['body'], $idInbox, $template);
    if (!$sent) {
        return false;
    }

    foreach ($reply['media'] as $media) {
        enviarWhatsAppImagenConPdo(
            $pdo,
            $tel,
            $media['url'],
            $media['caption'],
            $idInbox,
            $template.'_media'
        );
    }

    enviarWhatsAppTextoConPdo(
        $pdo,
        $tel,
        $reply['final_body'],
        $idInbox,
        $template.'_cierre'
    );

    return true;
}

function aiResolveQuotedModel(PDO $pdo, string $waMessageId): ?string
{
    $waMessageId = trim($waMessageId);
    if ($waMessageId === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT Template FROM notificacion_whatsapp WHERE WaMessageId = ? ORDER BY IdNotificacion DESC LIMIT 1');
    $stmt->execute([$waMessageId]);
    $template = $stmt->fetchColumn();
    if (!is_string($template)) {
        return null;
    }

    return \Lteco\Application\Whatsapp\WhatsappQuotedModel::fromTemplate($template);
}

function aiIngestInboundMessage(PDO $pdo, array $data): ?int
{
    aiEnsureSchema($pdo);
    $phone = whatsappFormatearTelefono((string)($data['phone'] ?? '')) ?: preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    if ($message === '') {
        return null;
    }

    $name = trim((string)($data['name'] ?? 'Cliente WhatsApp'));
    $channel = mb_substr((string)($data['channel'] ?? 'whatsapp'), 0, 32);
    $externalId = trim((string)($data['external_id'] ?? ''));
    $replyToWaMessageId = mb_substr(trim((string)($data['reply_to_message_id'] ?? '')), 0, 255);
    $replyToModel = aiResolveQuotedModel($pdo, $replyToWaMessageId);

    $lead = null;
    if ($phone !== '') {
        $stmt = $pdo->prepare("SELECT * FROM commercial_lead WHERE Telefono = ? ORDER BY IdLead DESC LIMIT 1");
        $stmt->execute([$phone]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$lead) {
        $stmt = $pdo->prepare("
            INSERT INTO commercial_lead (Origen, Estado, Prioridad, Nombre, Telefono, Mensaje, ResumenInteres)
            VALUES (?, 'nuevo', 'media', ?, ?, ?, ?)
        ");
        $stmt->execute([$channel, $name !== '' ? $name : 'Cliente WhatsApp', $phone ?: null, $message, mb_substr($message, 0, 500)]);
        $leadId = (int)$pdo->lastInsertId();
    } else {
        $leadId = (int)$lead['IdLead'];
        $stmt = $pdo->prepare("
            UPDATE commercial_lead
            SET Mensaje = ?, ResumenInteres = ?, FechaActualizacion = NOW()
            WHERE IdLead = ?
        ");
        $stmt->execute([$message, mb_substr($message, 0, 500), $leadId]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO commercial_inbox_message
            (IdLead, Canal, Direccion, Estado, ExternalId, RemitenteNombre, Telefono, Mensaje, ReplyToWaMessageId, ReplyToModelo, RawPayload, FechaRecibido)
        VALUES (?, ?, 'inbound', 'nuevo', ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE IdInbox = LAST_INSERT_ID(IdInbox)
    ");
    $stmt->execute([
        $leadId,
        $channel,
        $externalId !== '' ? $externalId : null,
        $name !== '' ? $name : null,
        $phone ?: null,
        $message,
        $replyToWaMessageId !== '' ? $replyToWaMessageId : null,
        $replyToModel,
        isset($data['raw']) ? json_encode($data['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $data['received_at'] ?? date('Y-m-d H:i:s'),
    ]);

    return (int)$pdo->lastInsertId();
}
