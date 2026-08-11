<?php

/**
 * Configuración central del sistema.
 *
 * Este archivo concentra valores que antes podían quedar hardcodeados en varias pantallas.
 * Para cambiar rutas, nombre público, tipo de cambio por defecto o RUT base, se puede usar
 * variables de entorno sin tocar el código fuente.
 */

// Autoload PSR-4 (Lteco\ -> src/). Si existe el autoloader generado por Composer
// (vendor/autoload.php) se usa ese; si no, se registra un fallback mínimo para el
// namespace Lteco\ así el sistema funciona sin necesidad de "composer install".
// No altera ninguna lógica existente: solo permite cargar clases bajo src/ a demanda.
(static function (): void {
    $vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;
        return;
    }

    spl_autoload_register(static function (string $clase): void {
        $prefijo = 'Lteco\\';
        $longitudPrefijo = strlen($prefijo);
        if (strncmp($clase, $prefijo, $longitudPrefijo) !== 0) {
            return;
        }

        $relativo = substr($clase, $longitudPrefijo);
        $ruta = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativo) . '.php';
        if (is_file($ruta)) {
            require_once $ruta;
        }
    });
})();

const LTECO_PANEL_BASE_URL = '/lteco-panel';
const LTECO_PUBLIC_BASE_URL = '/public-web';
const LTECO_APP_NAME = 'CommerceOps';
const LTECO_PANEL_NAME = 'Panel CommerceOps';
const LTECO_APP_TAGLINE = 'Gestión comercial integral';
const LTECO_BUSINESS_CATEGORY = 'productos y servicios';
const LTECO_DEFAULT_CURRENCY = 'USD';
const LTECO_DEFAULT_EXCHANGE_RATE = 42.00;
const LTECO_DEFAULT_IVA_RATE = 22.00;
const LTECO_DEFAULT_EMPRESA_RUT = '123456789012';

const ROL_SUPERADMIN = 'Superadmin';
const ROL_ADMINISTRADOR = 'Administrador';
const ROL_VENDEDOR = 'Vendedor';
const ROL_DISTRIBUIDOR = 'Distribuidor';

function cargarDotEnvLocal(): void
{
    static $cargado = false;
    if ($cargado) {
        return;
    }
    $cargado = true;

    $path = dirname(__DIR__) . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lineas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $linea, 2);
        $key = trim($key);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value);
        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function configEnv(string $key, mixed $default = null): mixed
{
    cargarDotEnvLocal();
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function appEnv(): string
{
    return strtolower((string)configEnv('LTECO_ENV', 'local'));
}

function appDebug(): bool
{
    return in_array(strtolower((string)configEnv('LTECO_DEBUG', appEnv() === 'local' ? '1' : '0')), ['1', 'true', 'yes', 'on'], true);
}

function appIsProduction(): bool
{
    return appEnv() === 'production';
}

function cspNonce(): string
{
    static $nonce = '';
    if ($nonce === '') {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

function appName(): string
{
    return (string)configEnv('LTECO_APP_NAME', LTECO_APP_NAME);
}

function appTagline(): string
{
    return (string)configEnv('LTECO_APP_TAGLINE', LTECO_APP_TAGLINE);
}

function panelName(): string
{
    return (string)configEnv('LTECO_PANEL_NAME', LTECO_PANEL_NAME);
}

function businessCategory(): string
{
    return (string)configEnv('LTECO_BUSINESS_CATEGORY', LTECO_BUSINESS_CATEGORY);
}

function supportTeamName(): string
{
    return (string)configEnv('LTECO_SUPPORT_TEAM_NAME', 'Equipo ' . appName());
}

function panelBaseUrl(string $path = ''): string
{
    $base = rtrim((string)configEnv('LTECO_PANEL_BASE_URL', LTECO_PANEL_BASE_URL), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function publicBaseUrl(string $path = ''): string
{
    $base = rtrim((string)configEnv('LTECO_PUBLIC_BASE_URL', LTECO_PUBLIC_BASE_URL), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function requestIsHttps(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));

    return $https === 'on'
        || $https === '1'
        || $forwardedProto === 'https'
        || $forwardedSsl === 'on';
}

function requestHost(): string
{
    $host = trim(str_replace(["\r", "\n"], '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));

    if ($host === '' || preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) !== 1) {
        return 'localhost';
    }

    return $host;
}

function requestBaseUrl(): string
{
    $scheme = requestIsHttps() ? 'https' : 'http';
    return $scheme . '://' . requestHost();
}

function panelAbsoluteUrl(string $path = ''): string
{
    $base = rtrim((string)configEnv('LTECO_PANEL_PUBLIC_URL', ''), '/');
    if ($base === '') {
        $base = requestBaseUrl() . panelBaseUrl();
    }

    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function publicAbsoluteUrl(string $path = ''): string
{
    $base = rtrim((string)configEnv('LTECO_PUBLIC_URL', ''), '/');
    if ($base === '') {
        $base = requestBaseUrl() . publicBaseUrl();
    }

    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function defaultTipoCambioUSD(): float
{
    return (float)configEnv('LTECO_DEFAULT_EXCHANGE_RATE', LTECO_DEFAULT_EXCHANGE_RATE);
}

function defaultTasaIVA(): float
{
    return (float)configEnv('LTECO_DEFAULT_IVA_RATE', LTECO_DEFAULT_IVA_RATE);
}

function defaultEmpresaRut(): string
{
    return (string)configEnv('LTECO_DEFAULT_EMPRESA_RUT', LTECO_DEFAULT_EMPRESA_RUT);
}

function sessionIdleTimeoutSeconds(): int
{
    return max(300, (int)configEnv('LTECO_SESSION_IDLE_SECONDS', 7200));
}

function sessionRegenerateSeconds(): int
{
    return max(300, (int)configEnv('LTECO_SESSION_REGENERATE_SECONDS', 1800));
}

function forceHttpsEnabled(): bool
{
    return in_array(strtolower((string)configEnv('LTECO_FORCE_HTTPS', '0')), ['1', 'true', 'yes', 'on'], true);
}

function turnstileSiteKey(): string
{
    return trim((string)configEnv('LTECO_TURNSTILE_SITE_KEY', ''));
}

function turnstileSecretKey(): string
{
    return trim((string)configEnv('LTECO_TURNSTILE_SECRET_KEY', ''));
}

function turnstileEnabled(): bool
{
    return turnstileSiteKey() !== '' && turnstileSecretKey() !== '';
}

function configureRuntime(): void
{
    static $configured = false;
    if ($configured) {
        return;
    }
    $configured = true;

    $debug = appDebug();
    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');

    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/php-error.log');
    }

    if (PHP_SAPI !== 'cli') {
        configureSecurityHeaders();
        configureSessionCookieSecurity();
        registerSafeErrorHandlers();
    }
}

function configureSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
    $nonce = cspNonce();
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; frame-src https://challenges.cloudflare.com; connect-src 'self' https://challenges.cloudflare.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

    if (appIsProduction() && forceHttpsEnabled() && requestIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function configureSessionCookieSecurity(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (requestIsHttps() || forceHttpsEnabled()) {
        ini_set('session.cookie_secure', '1');
    }

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => requestIsHttps() || forceHttpsEnabled(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function registerSafeErrorHandlers(): void
{
    set_exception_handler(function (Throwable $e): void {
        error_log('[UNCAUGHT] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (appDebug()) {
            echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error interno</title></head><body><h1>Ocurrió un error interno</h1><p>El sistema no pudo completar la operación. Intentá nuevamente o avisá al administrador.</p></body></html>';
    });

    register_shutdown_function(function (): void {
        $error = error_get_last();
        if (!$error) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)$error['type'], $fatalTypes, true)) {
            return;
        }

        error_log('[FATAL] ' . ($error['message'] ?? '') . ' in ' . ($error['file'] ?? '') . ':' . ($error['line'] ?? ''));
    });
}

function redirectToHttpsIfNeeded(): void
{
    if (!forceHttpsEnabled() || requestIsHttps() || headers_sent() || PHP_SAPI === 'cli') {
        return;
    }

    $host = requestHost();
    $uri = str_replace(["\r", "\n"], '', (string)($_SERVER['REQUEST_URI'] ?? '/'));
    if ($uri === '' || $uri[0] !== '/') {
        $uri = '/';
    }
    if ($host !== '') {
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

function rolesSistema(): array
{
    return [ROL_SUPERADMIN, ROL_ADMINISTRADOR, ROL_VENDEDOR, ROL_DISTRIBUIDOR];
}

function rolNormalizado(?string $rol): string
{
    $rol = trim((string)$rol);

    return match (mb_strtolower($rol, 'UTF-8')) {
        'superadmin', 'super admin', 'super-administrador', 'super administrador' => ROL_SUPERADMIN,
        'administrador', 'admin' => ROL_ADMINISTRADOR,
        'vendedor', 'ventas' => ROL_VENDEDOR,
        'distribuidor' => ROL_DISTRIBUIDOR,
        default => ROL_VENDEDOR,
    };
}

function monedasSistema(): array
{
    return ['USD', 'UYU'];
}

function estadosMotoSistema(): array
{
    return ['Disponible', 'Reservado', 'Vendido', 'Oculto', 'Sin stock'];
}

function estadosRepuestoSistema(): array
{
    return ['Disponible', 'Sin stock', 'Oculto'];
}

function estadosVentaSistema(): array
{
    return ['Pendiente', 'Confirmada', 'Entregada', 'Anulada'];
}

function metodosPagoVentaSistema(): array
{
    return ['Efectivo', 'Transferencia', 'Tarjeta', 'Otro'];
}

function tiposTarjetaSistema(): array
{
    return ['Crédito', 'Débito'];
}

function marcasTarjetaSistema(): array
{
    return ['Visa', 'Mastercard'];
}

function cuotasTarjetaPorMarcaSistema(): array
{
    return [
        'Visa' => [6],
        'Mastercard' => [6, 18],
    ];
}

function cuotasTarjetaSistema(): array
{
    // LTECO:VENTA_EXCEL_CUOTAS_EXACTAS_SISTEMA_V3
    // Lógica comercial actual:
    // Visa = 6 cuotas exactas.
    // Mastercard = 6 o 18 cuotas exactas.
    return array_values(array_unique(array_merge(...array_values(cuotasTarjetaPorMarcaSistema()))));
}

function metodosPagoGastoSistema(): array
{
    return ['Efectivo', 'Tarjeta', 'Transferencia', 'Otro'];
}

function categoriasGastoSistema(): array
{
    return ['Repuestos', 'Mantenimiento', 'Logística', 'Publicidad', 'Servicios', 'Sueldos', 'Transporte', 'Comisiones', 'Otros'];
}

function tiposClienteSistema(): array
{
    return ['Final', 'Distribuidor'];
}

function tiposClienteFiscalSistema(): array
{
    return ['Consumidor final', 'Empresa/RUT'];
}

/**
 * Returns true if $ip falls within $cidr (IPv4 only).
 */
function ipInCidrRange(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $bits = (int)$bits;
    if ($bits < 0 || $bits > 32) {
        return false;
    }
    $ipLong     = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) {
        return false;
    }
    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
    return ($ipLong & $mask) === ($subnetLong & $mask);
}

/**
 * Returns true if $remoteAddr is a trusted reverse-proxy.
 *
 * Trusted ranges: loopback, RFC-1918 private space (Docker), and any
 * CIDR/IP listed in LTECO_TRUSTED_PROXY_RANGES (comma-separated).
 */
function isTrustedProxy(string $remoteAddr): bool
{
    $defaults = ['127.0.0.1/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
    $extra    = array_filter(array_map(
        'trim',
        explode(',', (string)configEnv('LTECO_TRUSTED_PROXY_RANGES', ''))
    ));
    foreach (array_merge($defaults, $extra) as $range) {
        if ($range !== '' && ipInCidrRange($remoteAddr, $range)) {
            return true;
        }
    }
    return false;
}

configureRuntime();
redirectToHttpsIfNeeded();
