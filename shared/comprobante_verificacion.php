<?php

require_once __DIR__ . '/app_config.php';

function comprobanteVerificacionSecret(): string
{
    $secret = trim((string)configEnv('LTECO_COMPROBANTE_SECRET', ''));

    $placeholders = [
        '',
        'CAMBIAR_SECRET_LARGO_ALEATORIO',
        'changeme',
        'secret',
    ];

    if (in_array($secret, $placeholders, true) || strlen($secret) < 32) {
        throw new \RuntimeException(
            'LTECO_COMPROBANTE_SECRET no está configurado o usa un valor inseguro. ' .
            'Configurá un secreto aleatorio de al menos 32 caracteres en el .env.'
        );
    }

    return $secret;
}

function comprobanteVerificacionPayload(array $venta): string
{
    $idVenta = (int)($venta['IdVenta'] ?? 0);
    $numero = trim((string)($venta['NumeroFactura'] ?? ''));
    $fecha = trim((string)($venta['FechaVenta'] ?? ''));
    $total = number_format((float)($venta['Total'] ?? 0), 2, '.', '');
    $moneda = strtoupper(trim((string)($venta['Moneda'] ?? 'UYU')));

    return implode('|', [$idVenta, $numero, $fecha, $total, $moneda]);
}

function comprobanteVerificacionToken(array $venta): string
{
    return hash_hmac('sha256', comprobanteVerificacionPayload($venta), comprobanteVerificacionSecret());
}

function comprobanteVerificacionUrl(array $venta): string
{
    $base = trim((string)configEnv('LTECO_VERIFY_PUBLIC_URL', ''));
    if ($base === '') {
        $base = publicAbsoluteUrl('verificar-comprobante.php');
    }

    $params = [
        'id' => (int)($venta['IdVenta'] ?? 0),
        'token' => comprobanteVerificacionToken($venta),
    ];

    return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
}

function comprobanteVerificacionTokenValido(array $venta, string $token): bool
{
    $token = trim($token);
    if ($token === '' || strlen($token) !== 64 || preg_match('/^[a-f0-9]{64}$/i', $token) !== 1) {
        return false;
    }

    return hash_equals(comprobanteVerificacionToken($venta), strtolower($token));
}
