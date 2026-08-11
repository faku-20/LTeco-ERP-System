<?php
date_default_timezone_set('America/Montevideo');

require_once dirname(__DIR__) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';
require_once LTECO_SHARED_DIR . '/vehiculo_logic.php';
require_once __DIR__ . '/auditoria.php';


function h(mixed $valor, string $default = '—'): string
{
    if ($valor === null) {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }

    $texto = trim((string)$valor);
    if ($texto === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function csvValorSeguro(mixed $valor): string
{
    $texto = str_replace(["\r", "\n"], ' ', (string)($valor ?? ''));

    if ($texto !== '' && preg_match('/^[=+\-@\t]/', $texto) === 1) {
        return "'" . $texto;
    }

    return $texto;
}

function csvFilaSegura(array $fila): array
{
    return array_map('csvValorSeguro', $fila);
}

function normalizarNumeroUsuario(mixed $valor): string
{
    return \Lteco\Support\InputNormalizer::number($valor);
}

function decimalNoNegativo(mixed $valor, float $default = 0.0): float
{
    return \Lteco\Support\InputNormalizer::nonNegativeDecimal($valor, $default);
}

function enteroNoNegativo(mixed $valor, int $default = 0): int
{
    return \Lteco\Support\InputNormalizer::nonNegativeInt($valor, $default);
}

function normalizarEstadoRepuesto(string $estado, int $stock): string
{
    $permitidos = ['Disponible', 'Sin stock', 'Oculto'];
    $estado = in_array($estado, $permitidos, true) ? $estado : 'Disponible';

    if ($stock <= 0) {
        return $estado === 'Oculto' ? 'Oculto' : 'Sin stock';
    }

    return $estado === 'Sin stock' ? 'Disponible' : $estado;
}

function dbTieneTabla(PDO $pdo, string $tabla): bool
{
    static $cache = [];
    if (array_key_exists($tabla, $cache)) {
        return $cache[$tabla];
    }

    $cache[$tabla] = schemaRepository($pdo)->tablaExiste($tabla);
    return $cache[$tabla];
}

function schemaRepository(PDO $pdo): \Lteco\Infrastructure\Repository\SchemaRepository
{
    return new \Lteco\Infrastructure\Repository\SchemaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    );
}

function ipEnCidr(string $ip, string $cidr): bool
{
    $partes = explode('/', trim($cidr), 2);
    $red = trim($partes[0] ?? '');
    $bits = isset($partes[1]) ? (int)$partes[1] : null;

    if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($red, FILTER_VALIDATE_IP) || $bits === null) {
        return false;
    }

    $ipBin = @inet_pton($ip);
    $redBin = @inet_pton($red);
    if ($ipBin === false || $redBin === false || strlen($ipBin) !== strlen($redBin)) {
        return false;
    }

    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }

    $bytes = intdiv($bits, 8);
    $resto = $bits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($redBin, 0, $bytes)) {
        return false;
    }

    if ($resto === 0) {
        return true;
    }

    $mascara = (0xFF << (8 - $resto)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mascara) === (ord($redBin[$bytes]) & $mascara);
}

function proxyClienteConfiable(?string $remoteAddr = null): bool
{
    $remoteAddr = trim((string)($remoteAddr ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    if ($remoteAddr === '' || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return false;
    }

    $rangos = [
        '127.0.0.0/8',
        '::1/128',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
        'fe80::/10',
    ];

    $extra = trim((string)configEnv('LTECO_TRUSTED_PROXY_RANGES', ''));
    if ($extra !== '') {
        $rangos = array_merge($rangos, array_filter(array_map('trim', explode(',', $extra))));
    }

    foreach ($rangos as $rango) {
        if (str_contains($rango, '/')) {
            if (ipEnCidr($remoteAddr, $rango)) {
                return true;
            }
            continue;
        }

        if (filter_var($rango, FILTER_VALIDATE_IP) && hash_equals($rango, $remoteAddr)) {
            return true;
        }
    }

    return false;
}

function primeraIpValidaDesdeHeader(string $valor): ?string
{
    foreach (explode(',', $valor) as $parte) {
        $ip = trim($parte);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return null;
}

function obtenerIpCliente(): string
{
    $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $proxyConfiable = proxyClienteConfiable($remoteAddr);

    if ($proxyConfiable) {
        $cfIp = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        $forwardedFor = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            $ip = primeraIpValidaDesdeHeader($forwardedFor);
            if ($ip !== null) {
                return $ip;
            }
        }

        $realIp = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }
    }

    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    return '0.0.0.0';
}

function ipClienteSegura(): string
{
    return obtenerIpCliente();
}

function debugIpClienteContext(): array
{
    if (PHP_SAPI !== 'cli' && !appDebug()) {
        return [];
    }

    return [
        'ip_cliente' => obtenerIpCliente(),
        'proxy_confiable' => proxyClienteConfiable(),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        'x_real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
    ];
}

function userAgentSeguro(): string
{
    return substr(str_replace(["\r", "\n"], ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI')), 0, 250);
}

function ensureLoginAttemptsTable(PDO $pdo): void
{
    seguridadService($pdo)->asegurarIntentosLogin();
}

function loginUsuarioIntentado(string $usuario): string
{
    return \Lteco\Application\Seguridad\SeguridadService::normalizarUsuario($usuario);
}

function loginIntentosFallidosRecientes(PDO $pdo, string $ip, string $usuario, int $ventanaMinutos = 15): int
{
    return seguridadService($pdo)->intentosFallidosRecientes($ip, $usuario, $ventanaMinutos);
}

function loginBloqueadoHasta(PDO $pdo, string $ip, string $usuario): ?string
{
    return seguridadService($pdo)->bloqueadoHasta($ip, $usuario);
}

function registrarIntentoLogin(PDO $pdo, string $usuario, string $resultado, int $intentosRecientes = 0, ?string $bloqueadoHasta = null): void
{
    try {
        seguridadService($pdo)->registrarIntento(
            ipClienteSegura(),
            $usuario,
            userAgentSeguro(),
            $resultado,
            $intentosRecientes,
            $bloqueadoHasta
        );
    } catch (Throwable $e) {
        logPanelError('login_attempts', $e, ['resultado' => $resultado]);
    }
}

function seguridadService(PDO $pdo): \Lteco\Application\Seguridad\SeguridadService
{
    return new \Lteco\Application\Seguridad\SeguridadService(
        new \Lteco\Infrastructure\Repository\SeguridadRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    );
}

function validarTurnstileLogin(): bool
{
    if (!turnstileEnabled()) {
        return true;
    }

    $token = trim((string)($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => turnstileSecretKey(),
        'response' => $token,
        'remoteip' => ipClienteSegura(),
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);

    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($response === false) {
        logPanelError('turnstile', 'No se pudo validar Turnstile.');
        return false;
    }

    $data = json_decode($response, true);
    return is_array($data) && (bool)($data['success'] ?? false);
}

function base32Encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }

    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= $alphabet[bindec($chunk)];
    }

    return $encoded;
}

function base32Decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
    $bits = '';

    for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
        $pos = strpos($alphabet, $secret[$i]);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $decoded = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $decoded .= chr(bindec($chunk));
        }
    }

    return $decoded;
}

function totpGenerarSecreto(): string
{
    return base32Encode(random_bytes(20));
}

function mfaEncryptionKey(): string
{
    $raw = trim((string)configEnv('LTECO_MFA_ENCRYPTION_KEY', ''));
    $placeholders = [
        '',
        'CAMBIAR_SECRET_LARGO_ALEATORIO_PARA_MFA',
        'changeme',
        'secret',
    ];
    if (in_array($raw, $placeholders, true) || strlen($raw) < 32) {
        return '';
    }
    return hash('sha256', $raw, true);
}

function mfaSecretProtect(string $secret): string
{
    $key = mfaEncryptionKey();
    if ($key === '') {
        throw new \RuntimeException(
            'LTECO_MFA_ENCRYPTION_KEY no está configurado o usa un valor inseguro. ' .
            'Configurá una clave aleatoria de al menos 32 caracteres en el .env.'
        );
    }

    if (!function_exists('openssl_encrypt')) {
        throw new \RuntimeException('openssl_encrypt no está disponible en este entorno PHP.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false || $tag === '') {
        throw new \RuntimeException('Error al cifrar el secreto MFA con AES-256-GCM.');
    }

    return 'enc:v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
}

function mfaSecretReveal(string $stored): string
{
    if (!str_starts_with($stored, 'enc:v1:')) {
        return $stored;
    }

    $key = mfaEncryptionKey();
    if ($key === '') {
        throw new \RuntimeException(
            'LTECO_MFA_ENCRYPTION_KEY no está configurado o usa un valor inseguro. ' .
            'No se puede descifrar el secreto MFA almacenado.'
        );
    }

    if (!function_exists('openssl_decrypt')) {
        throw new \RuntimeException('openssl_decrypt no está disponible en este entorno PHP.');
    }

    $parts = explode(':', $stored, 5);
    if (count($parts) !== 5) {
        return '';
    }

    $iv = base64_decode($parts[2], true);
    $tag = base64_decode($parts[3], true);
    $cipher = base64_decode($parts[4], true);
    if ($iv === false || $tag === false || $cipher === false) {
        return '';
    }

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain !== false ? $plain : '';
}

function hotpCodigo(string $secret, int $counter, int $digits = 6): string
{
    $key = base32Decode($secret);
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function totpVerificarCodigo(string $secret, string $codigo, int $ventana = 1): bool
{
    $codigo = preg_replace('/\D/', '', $codigo) ?? '';
    if (strlen($codigo) !== 6) {
        return false;
    }

    $counter = intdiv(time(), 30);
    for ($i = -$ventana; $i <= $ventana; $i++) {
        if (hash_equals(hotpCodigo($secret, $counter + $i), $codigo)) {
            return true;
        }
    }

    return false;
}

function totpProvisioningUri(string $usuario, string $secret): string
{
    $issuer = appName();
    $label = rawurlencode($issuer . ':' . $usuario);
    return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

function generarRecoveryCodes(int $cantidad = 8): array
{
    $codes = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 5) . '-' . substr(bin2hex(random_bytes(5)), 0, 5));
    }
    return $codes;
}

function hashRecoveryCodes(array $codes): string
{
    $hashes = [];
    foreach ($codes as $code) {
        $normalizado = strtoupper(trim((string)$code));
        if ($normalizado !== '') {
            $hashes[] = password_hash($normalizado, PASSWORD_DEFAULT);
        }
    }
    return json_encode($hashes, JSON_UNESCAPED_SLASHES) ?: '[]';
}

function consumirRecoveryCode(PDO $pdo, int $idUsuario, string $code, ?string $jsonActual): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return false;
    }

    $hashes = json_decode((string)$jsonActual, true);
    if (!is_array($hashes)) {
        return false;
    }

    foreach ($hashes as $idx => $hash) {
        if (is_string($hash) && password_verify($code, $hash)) {
            unset($hashes[$idx]);
            $service = new \Lteco\Application\Usuario\UsuarioMfaService(
                new \Lteco\Infrastructure\Repository\UsuarioRepository(
                    new \Lteco\Infrastructure\Db\Connection($pdo)
                )
            );
            $service->regenerarRecovery(
                $idUsuario,
                json_encode(array_values($hashes), JSON_UNESCAPED_SLASHES) ?: '[]'
            );
            return true;
        }
    }

    return false;
}

function obtenerTipoCambio(PDO $pdo, ?float $porDefecto = null): float
{
    $porDefecto = $porDefecto ?? defaultTipoCambioUSD();

    try {
        $service = new \Lteco\Application\Importacion\ImportacionConsultaService(
            new \Lteco\Infrastructure\Repository\ImportacionConsultaRepository(
                new \Lteco\Infrastructure\Db\Connection($pdo)
            )
        );
        return $service->tipoCambioMasRecienteActivo($porDefecto);
    } catch (Throwable $e) {
        return $porDefecto;
    }
}

function obtenerTipoCambioUSD(PDO $pdo, ?float $default = null): float
{
    return obtenerTipoCambio($pdo, $default);
}

function convertirMoneda(float $monto, string $monedaOrigen, string $monedaDestino, ?float $tipoCambio = null): float
{
    $tipoCambio = $tipoCambio ?? defaultTipoCambioUSD();
    if ($monedaOrigen === $monedaDestino) {
        return $monto;
    }

    if ($monedaOrigen === 'USD' && $monedaDestino === 'UYU') {
        return $monto * $tipoCambio;
    }

    if ($monedaOrigen === 'UYU' && $monedaDestino === 'USD') {
        return $tipoCambio > 0 ? ($monto / $tipoCambio) : 0.0;
    }

    return $monto;
}

function convertirAUyu(float $monto, string $moneda, ?float $tipoCambio = null): float
{
    $tipoCambio = $tipoCambio ?? defaultTipoCambioUSD();
    return convertirMoneda($monto, $moneda ?: 'UYU', 'UYU', $tipoCambio);
}

function tipoCambioHistoricoVenta(array $venta, ?float $tipoCambioActual = null): float
{
    $tipoCambioActual = $tipoCambioActual ?? defaultTipoCambioUSD();
    $tipoCambioVenta = (float)($venta['TipoCambioAplicado'] ?? 0);

    return $tipoCambioVenta > 0 ? $tipoCambioVenta : $tipoCambioActual;
}

function convertirMontoVentaAUyu(float $monto, ?string $moneda, array $venta, ?float $tipoCambioActual = null): float
{
    return convertirAUyu($monto, $moneda ?: 'UYU', tipoCambioHistoricoVenta($venta, $tipoCambioActual));
}

function porcentajeSeguro(float $parte, float $total, int $decimales = 1): float
{
    if (abs($total) < 0.00001) {
        return 0.0;
    }

    return round(($parte / $total) * 100, $decimales);
}

function simboloMoneda(?string $moneda): string
{
    return $moneda === 'USD' ? 'USD ' : '$ ';
}

function formatearMonto(float $monto, ?string $moneda = 'UYU'): string
{
    return simboloMoneda($moneda) . number_format($monto, 2, ',', '.');
}

function formatearMontoUYU(float $monto): string
{
    return '$ ' . number_format($monto, 2, ',', '.');
}

function formatearEnPesos(float $monto, string $moneda, ?float $tc = null): string
{
    $tc = ($tc !== null && $tc > 0) ? $tc : defaultTipoCambioUSD();
    $enUYU = ($moneda === 'USD') ? $monto * $tc : $monto;
    return '$ ' . number_format($enUYU, 2, ',', '.');
}

function claseBadgeEstado(?string $estado): string
{
    return match ($estado) {
        'Disponible', 'Confirmada', 'Entregada' => 'badge-disponible',
        'Reservado', 'Pendiente' => 'badge-reservado',
        'Vendido' => 'badge-vendido',
        'Oculto', 'Anulada' => 'badge-oculto',
        'Sin stock' => 'badge-sin-stock',
        default => 'badge-default',
    };
}

function formatearFechaCorta(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }

    try {
        return (new DateTime($fecha))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function obtenerMesesResumen(PDO $pdo, float $tipoCambio, int $limite = 6): array
{
    $service = new \Lteco\Application\Balance\BalanceService(
        new \Lteco\Infrastructure\Repository\BalanceRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    );
    return $service->mesesResumen($tipoCambio, $limite);
}


function limpiarTextoOpcional(?string $valor): ?string
{
    return \Lteco\Support\InputNormalizer::optionalText($valor);
}

function normalizarTextoHumano(?string $valor, int $max = 255): string
{
    return \Lteco\Support\InputNormalizer::humanText($valor, $max);
}

function normalizarTelefono(?string $telefono): ?string
{
    return \Lteco\Support\Phone::normalize($telefono);
}

function telefonoWhatsappPanel(?string $telefono): ?string
{
    $digitos = preg_replace('/\D+/', '', (string)$telefono) ?? '';
    if ($digitos === '') {
        return null;
    }

    if (str_starts_with($digitos, '0')) {
        $digitos = substr($digitos, 1);
    }

    if (!str_starts_with($digitos, '598')) {
        $digitos = '598' . $digitos;
    }

    return strlen($digitos) >= 11 ? $digitos : null;
}

function linkWhatsappPanel(?string $telefono, string $mensaje): ?string
{
    $telefono = telefonoWhatsappPanel($telefono);
    if ($telefono === null) {
        return null;
    }

    return 'https://wa.me/' . $telefono . '?text=' . rawurlencode($mensaje);
}

function telefonoValido(?string $telefono): bool
{
    return \Lteco\Support\Phone::isValid($telefono);
}

function normalizarCedula(?string $cedula): string
{
    return \Lteco\Support\Cedula::normalize($cedula);
}

function cedulaUruguayaValida(?string $cedula): bool
{
    return \Lteco\Support\Cedula::isValidUruguayan($cedula);
}

function fechaYmdValida(string $fecha): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }

    [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));
    return checkdate($mes, $dia, $anio);
}

function opcionSistema(string $valor, array $permitidos, string $default): string
{
    return in_array($valor, $permitidos, true) ? $valor : $default;
}

function valorUnicoOpcional(PDO $pdo, string $tabla, string $columna, ?string $valor, ?string $columnaExcluir = null, mixed $valorExcluir = null): bool
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return true;
    }

    return valorUnicoEnTabla($pdo, $tabla, $columna, $valor, $columnaExcluir, $valorExcluir);
}

function slugify(string $texto): string
{
    $texto = trim(mb_strtolower($texto, 'UTF-8'));
    if ($texto === '') {
        return '';
    }

    $reemplazos = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = preg_replace('/[^a-z0-9]+/u', '-', $texto) ?? '';
    $texto = trim($texto, '-');
    $texto = preg_replace('/-+/', '-', $texto) ?? '';
    return $texto;
}

function valorUnicoEnTabla(PDO $pdo, string $tabla, string $columna, mixed $valor, ?string $columnaExcluir = null, mixed $valorExcluir = null): bool
{
    return schemaRepository($pdo)->valorUnico($tabla, $columna, $valor, $columnaExcluir, $valorExcluir);
}

/** @param list<string> $tablas */
function ltecoRequireSchemaTables(PDO $pdo, array $tablas, string $componente): void
{
    if ($tablas === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($tablas), '?'));
    $stmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME IN ({$placeholders})
    ");
    $stmt->execute($tablas);
    $existentes = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    $faltantes = array_values(array_filter($tablas, static fn(string $tabla): bool => !isset($existentes[$tabla])));
    if ($faltantes !== []) {
        throw new RuntimeException($componente . ': schema incompleto. Ejecutá scripts/migrate.sh antes de usar este flujo. Faltan tablas: ' . implode(', ', $faltantes));
    }
}

/** @param array<string,list<string>> $columnasPorTabla */
function ltecoRequireSchemaColumns(PDO $pdo, array $columnasPorTabla, string $componente): void
{
    foreach ($columnasPorTabla as $tabla => $columnas) {
        if ($columnas === []) {
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($columnas), '?'));
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$tabla], $columnas));
        $existentes = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        $faltantes = array_values(array_filter($columnas, static fn(string $columna): bool => !isset($existentes[$columna])));
        if ($faltantes !== []) {
            throw new RuntimeException($componente . ': schema incompleto. Ejecutá scripts/migrate.sh antes de usar este flujo. Faltan columnas en ' . $tabla . ': ' . implode(', ', $faltantes));
        }
    }
}

function obtenerChecklistPublicacion(array $data, bool $tieneImagenPrincipal): array
{
    $payload = $data;
    $payload['TieneImagen'] = $tieneImagenPrincipal ? 1 : 0;

    return vehiculoChecklistPublicacion($payload);
}

function buildQuery(array $params): string
{
    return http_build_query(array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    }));
}

function redirect(string $url): never
{
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url);
    exit;
}



function carpetaUploadsVehiculos(): string
{
    $dir = LTECO_PANEL_PUBLIC_DIR . '/uploads/vehiculos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function archivosImagenesSeleccionados(array $files): array
{
    $resultado = [];
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        return $resultado;
    }

    foreach ($names as $i => $name) {
        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $resultado[] = [
            'name' => (string)$name,
            'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
            'error' => (int)$error,
            'size' => (int)($files['size'][$i] ?? 0),
            'type' => (string)($files['type'][$i] ?? ''),
        ];
    }

    return $resultado;
}

function validarImagenesVehiculo(array $files, int $maxArchivos = 8, int $maxMb = 5): array
{
    $seleccionados = archivosImagenesSeleccionados($files);
    $errores = [];

    if (count($seleccionados) > $maxArchivos) {
        $errores[] = 'Podés subir hasta ' . $maxArchivos . ' imágenes por vez.';
        return $errores;
    }

    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $maxBytes = $maxMb * 1024 * 1024;

    foreach ($seleccionados as $archivo) {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir ' . $archivo['name'] . '.';
            continue;
        }

        if ($archivo['size'] <= 0 || $archivo['size'] > $maxBytes) {
            $errores[] = 'La imagen ' . $archivo['name'] . ' supera el límite de ' . $maxMb . ' MB.';
            continue;
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            $errores[] = 'La imagen ' . $archivo['name'] . ' no llegó correctamente al servidor.';
            continue;
        }

        $mime = @mime_content_type($archivo['tmp_name']) ?: '';
        if (!isset($permitidos[$mime])) {
            $errores[] = 'La imagen ' . $archivo['name'] . ' debe ser JPG, PNG o WEBP.';
            continue;
        }

        $dimensiones = @getimagesize($archivo['tmp_name']);
        if ($dimensiones === false || empty($dimensiones[0]) || empty($dimensiones[1])) {
            $errores[] = 'La imagen ' . $archivo['name'] . ' no es un archivo de imagen válido.';
        }
    }

    return $errores;
}

function guardarImagenesVehiculo(PDO $pdo, string $idVehiculo, array $files): int
{
    $seleccionados = archivosImagenesSeleccionados($files);
    if (!$seleccionados) {
        return 0;
    }

    $errores = validarImagenesVehiculo($files);
    if ($errores) {
        throw new RuntimeException(implode(' ', $errores));
    }

    $repo = new \Lteco\Infrastructure\Repository\VehiculoRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    );
    $ordenBase = $repo->maxOrdenImagen($idVehiculo);
    $yaTienePrincipal = $repo->tieneImagenPrincipal($idVehiculo);

    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $insertadas = 0;

    foreach ($seleccionados as $archivo) {
        $mime = @mime_content_type($archivo['tmp_name']) ?: '';
        $extension = $permitidos[$mime] ?? strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'jpg';
        }

        $nombreArchivo = strtolower($idVehiculo) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $rutaDestinoAbs = carpetaUploadsVehiculos() . '/' . $nombreArchivo;
        $rutaGuardar = 'uploads/vehiculos/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestinoAbs)) {
            throw new RuntimeException('No se pudo mover la imagen ' . $archivo['name'] . '.');
        }
        @chmod($rutaDestinoAbs, 0644);

        $esPrincipal = (!$yaTienePrincipal && $insertadas === 0) ? 1 : 0;
        $repo->insertarImagen($idVehiculo, $rutaGuardar, $esPrincipal, $ordenBase + $insertadas + 1);
        $insertadas++;
    }

    return $insertadas;
}

function siguienteOrdenWebVehiculo(PDO $pdo): int
{
    $repo = new \Lteco\Infrastructure\Repository\VehiculoRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    );
    return $repo->siguienteOrdenWeb();
}

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function panelIdempotencyToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return bin2hex(random_bytes(16));
}

function panelIdempotencyInput(): string
{
    return '<input type="hidden" name="idempotency_key" value="' . htmlspecialchars(panelIdempotencyToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function panelIdempotencyRequestKey(): string
{
    $key = strtolower(trim((string)($_POST['idempotency_key'] ?? '')));
    if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
        throw new RuntimeException('Token de operación inválido o vencido. Recargá el formulario e intentá nuevamente.');
    }

    return $key;
}

/** @param array<string,mixed> $payload */
function panelIdempotencyRequestHash(string $operationType, array $payload): string
{
    unset($payload['csrf_token'], $payload['idempotency_key']);
    $normalize = static function (mixed $value) use (&$normalize): mixed {
        if (!is_array($value)) {
            return $value;
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $normalize($item);
        }

        return $value;
    };

    return hash('sha256', $operationType . ':' . json_encode($normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Reclama una operación dentro de la transacción del caller.
 *
 * @return array<string,mixed>|null null si el caller debe ejecutar; fila si ya estaba completada.
 */
function panelIdempotencyClaim(PDO $pdo, string $operationType, string $operationKey, string $requestHash, ?int $idUsuario): ?array
{
    return (new \Lteco\Infrastructure\Repository\PanelIdempotencyRepository($pdo))
        ->claim($operationType, $operationKey, $requestHash, $idUsuario);
}

function panelIdempotencyComplete(PDO $pdo, string $operationKey, string $resultType, int|string $resultId, string $redirectUrl): void
{
    (new \Lteco\Infrastructure\Repository\PanelIdempotencyRepository($pdo))
        ->complete($operationKey, $resultType, $resultId, $redirectUrl);
}

function requirePost(): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
}

function verifyCsrfOrFail(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $requestToken = (string)($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }
}

function requestIdString(string $key = 'id'): string
{
    $value = trim((string)($_REQUEST[$key] ?? ''));
    if ($value === '') {
        throw new InvalidArgumentException('ID de vehículo no recibido.');
    }
    return $value;
}

function vehicleActionContext(): array
{
    return [
        'ip' => obtenerIpCliente(),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'user' => $_SESSION['usuario']['Usuario'] ?? ($_SESSION['usuario']['Nombre'] ?? 'desconocido'),
    ];
}

function logPanelError(string $action, Throwable|string $error, array $context = []): void
{
    $logDir = LTECO_PROJECT_ROOT . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $message = $error instanceof Throwable ? $error->getMessage() : (string)$error;
    $payload = [
        'time' => date('Y-m-d H:i:s'),
        'action' => $action,
        'message' => redactSensitiveLogValue($message),
        'context' => redactSensitiveLogValue($context),
    ];

    @file_put_contents(
        $logDir . '/panel-error.log',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
}

function redactSensitiveLogValue(mixed $value): mixed
{
    if (is_array($value)) {
        $redacted = [];
        foreach ($value as $key => $item) {
            $keyString = is_string($key) ? $key : (string)$key;
            if (preg_match('/(authorization|api[_-]?key|token|password|passwd|clave|cookie|secret|mfa|recovery|csrf)/i', $keyString)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = redactSensitiveLogValue($item);
        }
        return $redacted;
    }

    if (!is_string($value)) {
        return $value;
    }

    $patterns = [
        '/(Authorization:\\s*(?:Bearer\\s+)?)[^\\s,;]+/i',
        '/(X-Lteco-N8n-Token:\\s*)[^\\s,;]+/i',
        '/((?:token|api[_-]?key|password|passwd|clave|secret|mfa|recovery|csrf)[=:\\s]+)[^\\s,;&]+/i',
        '/(Cookie:\\s*)[^\\r\\n]+/i',
    ];

    return preg_replace($patterns, '$1[REDACTED]', $value) ?? '[REDACTED]';
}

function redirectVehiculosError(string $message, array $context = []): never
{
    logPanelError('vehiculos_error', $message, $context + vehicleActionContext());
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', $message);
}

function redirectVehiculosSuccess(string $message): never
{
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'success', $message);
}

function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function redirectWithFlash(string $url, string $type, string $message): never
{
    setFlash($type, $message);
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url);
    exit;
}

function flashClass(string $type): string
{
    return match ($type) {
        'success' => 'notice notice--success',
        'warning' => 'notice notice--warning',
        'info' => 'notice notice--info',
        'error' => 'notice notice--error',
        default => 'notice',
    };
}




function mensajeErrorSeguro(Throwable $e, string $mensajeUsuario = 'No se pudo completar la operación. Revisá los datos e intentá nuevamente.'): string
{
    if ($e instanceof RuntimeException || $e instanceof InvalidArgumentException || appDebug()) {
        return $e->getMessage();
    }

    logPanelError('error_oculto_usuario', $e);
    return $mensajeUsuario;
}

function redirectErrorSeguro(string $url, Throwable $e, string $mensajeUsuario = 'No se pudo completar la operación. Revisá los datos e intentá nuevamente.'): never
{
    redirectWithFlash($url, 'error', mensajeErrorSeguro($e, $mensajeUsuario));
}

function ltecoBackupDir(): string
{
    $dir = str_replace('\\', '/', (string)configEnv('LTECO_BACKUP_DIR', '/opt/backups/ltecobike'));
    $dir = rtrim($dir, '/');
    if ($dir === '') {
        $dir = '/opt/backups/ltecobike';
    }
    return $dir;
}

function ltecoBackupFilenameValido(string $file): bool
{
    return preg_match('/^(backup|auto_pre_restore)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $file) === 1
        || preg_match('/^lteco_db_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $file) === 1;
}

function ltecoEnsureBackupDir(): string
{
    $dir = ltecoBackupDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('El directorio de backups no existe o no tiene permisos de escritura: ' . $dir);
    }
    return $dir;
}

function ltecoBackupPathSeguro(string $file): string
{
    $file = basename(trim($file));
    if ($file === '' || !ltecoBackupFilenameValido($file)) {
        throw new RuntimeException('Nombre de backup inválido.');
    }

    $base = realpath(ltecoEnsureBackupDir());
    if ($base === false) {
        throw new RuntimeException('Directorio de backups inválido.');
    }

    $path = realpath($base . DIRECTORY_SEPARATOR . $file);
    if ($path === false || !is_file($path) || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('El backup no existe o está fuera del directorio permitido.');
    }

    return $path;
}

function ltecoMysqlEnv(): array
{
    $host = (string)configEnv('LTECO_DB_BACKUP_HOST', (string)configEnv('LTECO_DB_HOST', 'host.docker.internal'));
    $name = (string)configEnv('LTECO_DB_NAME', 'lteco_db');
    $user = (string)configEnv('LTECO_DB_BACKUP_USER', (string)configEnv('LTECO_DB_USER', ''));
    $pass = (string)configEnv('LTECO_DB_BACKUP_PASS', (string)configEnv('LTECO_DB_PASS', ''));

    if ($name === '' || $user === '') {
        throw new RuntimeException('Faltan variables de conexión para backup/restore.');
    }

    return [$host, $name, $user, $pass];
}

function ltecoRunCommandSeguro(array $command, ?string $stdinFile = null, ?string $stdoutFile = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    if ($stdinFile !== null) {
        $descriptors[0] = ['file', $stdinFile, 'r'];
    }

    if ($stdoutFile !== null) {
        $descriptors[1] = ['file', $stdoutFile, 'w'];
    }

    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo ejecutar el comando del sistema.');
    }

    if ($stdinFile === null && isset($pipes[0])) {
        fclose($pipes[0]);
    }

    $stdout = '';
    if ($stdoutFile === null && isset($pipes[1])) {
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
    }

    $stderr = '';
    if (isset($pipes[2])) {
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
    }

    $code = proc_close($process);
    return [$code, $stdout, $stderr];
}

function eliminarArchivoProyecto(?string $rutaRelativa): void
{
    $rutaRelativa = trim((string)$rutaRelativa);
    if ($rutaRelativa === '') {
        return;
    }

    $rutaNormalizada = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $rutaRelativa), DIRECTORY_SEPARATOR);
    $uploadsRel = 'lteco-panel' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'vehiculos' . DIRECTORY_SEPARATOR;
    $uploadsRelAlternativa = 'uploads' . DIRECTORY_SEPARATOR . 'vehiculos' . DIRECTORY_SEPARATOR;

    if (str_starts_with($rutaNormalizada, $uploadsRelAlternativa)) {
        $rutaNormalizada = 'lteco-panel' . DIRECTORY_SEPARATOR . $rutaNormalizada;
    }

    if (!str_starts_with($rutaNormalizada, $uploadsRel)) {
        return;
    }

    $base = realpath(LTECO_PANEL_PUBLIC_DIR . '/uploads/vehiculos');
    if ($base === false) {
        return;
    }

    $nombreArchivo = basename($rutaNormalizada);
    $rutaCompleta = realpath($base . DIRECTORY_SEPARATOR . $nombreArchivo);
    if ($rutaCompleta === false) {
        return;
    }

    if (str_starts_with($rutaCompleta, $base . DIRECTORY_SEPARATOR) && is_file($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
}


function obtenerEmpresaRutPrincipal(PDO $pdo): string
{
    try {
        $service = new \Lteco\Application\Configuracion\ConfiguracionService(
            new \Lteco\Infrastructure\Repository\ConfiguracionRepository(
                new \Lteco\Infrastructure\Db\Connection($pdo)
            )
        );
        $empresa = $service->obtenerEmpresa();
        $rut = trim((string)($empresa['RUT'] ?? ''));
        return $rut !== '' ? $rut : defaultEmpresaRut();
    } catch (Throwable $e) {
        return defaultEmpresaRut();
    }
}


if (!function_exists('obtenerConfiguracionNegocio')) {
    function obtenerConfiguracionNegocio(PDO $pdo): array
    {
        $defaults = [
            'NombreEmpresa' => appName(),
            'Whatsapp' => '',
            'Direccion' => '',
            'Logo' => '',
            'TipoCambioUSD' => defaultTipoCambioUSD(),
            'TextoComprobante' => 'Gracias por confiar en ' . appName() . '.',
            'Correo' => '',
            'Telefono' => '',
            'Instagram' => '',
            'Descripcion' => '',
            'SitioWeb' => '',
            'ColorPrimario' => '',
            'ColorSecundario' => '',
            'PoweredByEnabled' => poweredByEnabled() ? 1 : 0,
        ];

        try {
            $service = new \Lteco\Application\Configuracion\ConfiguracionService(
                new \Lteco\Infrastructure\Repository\ConfiguracionRepository(
                    new \Lteco\Infrastructure\Db\Connection($pdo)
                )
            );
            $config = $service->configuracionNegocio($defaults);

            return $config;
        } catch (Throwable $e) {
            return $defaults;
        }
    }
}

if (!function_exists('rolRequiereClaveSegura')) {
    function rolRequiereClaveSegura(string $rol): bool
    {
        $rol = rolNormalizado($rol);
        return in_array($rol, [ROL_SUPERADMIN, ROL_ADMINISTRADOR], true);
    }
}

if (!function_exists('validarClaveSegunRol')) {
    /**
     * Valida contraseña según el rol objetivo.
     * Admin/Superadmin requieren una contraseña más fuerte.
     * Vendedor mantiene regla mínima simple para operación interna.
     */
    function validarClaveSegunRol(string $clave, string $rol): array
    {
        $errores = [];
        $rol = rolNormalizado($rol);

        if ($clave === '') {
            return ['La contraseña es obligatoria.'];
        }

        if (rolRequiereClaveSegura($rol)) {
            if (strlen($clave) < 8) {
                $errores[] = 'La contraseña de Administrador/Superadmin debe tener al menos 8 caracteres.';
            }

            if (!preg_match('/[A-Z]/', $clave)) {
                $errores[] = 'La contraseña de Administrador/Superadmin debe incluir al menos una mayúscula.';
            }

            if (!preg_match('/[a-z]/', $clave)) {
                $errores[] = 'La contraseña de Administrador/Superadmin debe incluir al menos una minúscula.';
            }

            if (!preg_match('/[0-9]/', $clave)) {
                $errores[] = 'La contraseña de Administrador/Superadmin debe incluir al menos un número.';
            }

            return $errores;
        }

        if (strlen($clave) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        return $errores;
    }
}
