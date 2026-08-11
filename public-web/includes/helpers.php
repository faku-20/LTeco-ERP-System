<?php

require_once __DIR__ . '/../../shared/app_config.php';
require_once __DIR__ . '/../../shared/vehiculo_logic.php';

function publicRedirect(string $url): never
{
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url);
    exit;
}

function storefrontPublicUrl(string $path = ''): string
{
    $base = rtrim((string)configEnv('LTECO_STOREFRONT_PUBLIC_URL', 'https://storefront.example.com'), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function dbTieneColumnaPublic(PDO $pdo, string $tabla, string $columna): bool
{
    static $cache = [];
    $key = $tabla . '.' . $columna;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    // No usamos SHOW COLUMNS ... LIKE ? porque algunas versiones de MySQL/Laragon
    // no aceptan placeholders en sentencias SHOW preparadas. INFORMATION_SCHEMA
    // es compatible y evita errores de sintaxis con PDO.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tabla, $columna]);

    $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$key];
}

function obtenerEmpresaPublica(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM empresa ORDER BY RUT ASC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function obtenerConfiguracionPublica(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM configuracion ORDER BY IdConfiguracion ASC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function numeroWhatsappPublico(?string $numero): string
{
    return preg_replace('/\D+/', '', (string)$numero) ?: (string)configEnv('LTECO_DEFAULT_WHATSAPP', '59892000086');
}

function whatsappEmpresaPublica(array $empresa): string
{
    return numeroWhatsappPublico($empresa['WhatsApp'] ?? null);
}

function armarLinkWhatsapp(string $numero, string $mensaje): string
{
    return 'https://wa.me/' . numeroWhatsappPublico($numero) . '?text=' . rawurlencode($mensaje);
}


function instagramUsuarioPublico(?string $instagram): string
{
    $instagram = trim((string)$instagram);
    if ($instagram === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $instagram)) {
        $host = strtolower((string)(parse_url($instagram, PHP_URL_HOST) ?? ''));
        return str_ends_with($host, 'instagram.com') ? $instagram : '';
    }

    $instagram = ltrim($instagram, '@');
    $instagram = trim($instagram, '/');

    return $instagram;
}

function armarLinkInstagram(?string $instagram): string
{
    $instagram = instagramUsuarioPublico($instagram);
    if ($instagram === '') {
        return '#';
    }

    if (preg_match('#^https?://#i', $instagram)) {
        return $instagram;
    }

    return 'https://instagram.com/' . rawurlencode($instagram);
}

function urlContactoPublico(?string $modelo = null, ?string $volver = null): string
{
    $params = [];
    $modelo = trim((string)$modelo);
    $volver = trim((string)$volver);

    if ($modelo !== '') {
        $params['modelo'] = $modelo;
    }

    if ($volver !== '') {
        $params['volver'] = $volver;
    }

    return publicBaseUrl('contacto.php') . ($params ? '?' . http_build_query($params) : '');
}

function formatoPrecioWeb(float $precio, string $moneda): string
{
    if ($moneda === 'USD') {
        return 'USD ' . number_format($precio, 2, ',', '.');
    }
    return '$ ' . number_format($precio, 2, ',', '.');
}

function precioReferenciaUYU(float $precio, string $moneda, float $tipoCambio): ?string
{
    if ($moneda !== 'USD' || $tipoCambio <= 0) {
        return null;
    }

    return '$ ' . number_format($precio * $tipoCambio, 2, ',', '.');
}

function rutaImagenMoto(?string $ruta): string
{
    if (!$ruta) {
        return publicBaseUrl('assets/img/logo-header.png');
    }
    return panelBaseUrl(ltrim($ruta, '/'));
}

function textoResumen(?string $texto, int $limite = 140): string
{
    $texto = trim((string)$texto);
    if ($texto === '') {
        return '';
    }

    return mb_strlen($texto, 'UTF-8') > $limite
        ? mb_substr($texto, 0, $limite - 3, 'UTF-8') . '...'
        : $texto;
}


if (!function_exists('rutaActivaPublic')) {
    function rutaActivaPublic(string $archivo): string
    {
        $actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
        return $actual === $archivo ? 'is-active' : '';
    }
}

if (!function_exists('facebookUsuarioPublico')) {
function facebookUsuarioPublico(?string $facebook): string
{
    $facebook = trim((string)$facebook);

    if ($facebook === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $facebook)) {
        $host = strtolower((string)(parse_url($facebook, PHP_URL_HOST) ?? ''));
        return str_ends_with($host, 'facebook.com') ? $facebook : '';
    }

    return $facebook;
}
}

if (!function_exists('armarLinkFacebook')) {
    function armarLinkFacebook(?string $facebook): string
    {
        $facebook = trim((string)$facebook);

        if ($facebook === '') {
            return '';
        }

        if (str_starts_with($facebook, 'http://') || str_starts_with($facebook, 'https://')) {
            return $facebook;
        }

        if (ctype_digit($facebook)) {
            return 'https://www.facebook.com/profile.php?id=' . $facebook;
        }

        return 'https://www.facebook.com/' . ltrim($facebook, '@/');
    }
}
