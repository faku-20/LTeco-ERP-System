<?php

function vehiculoEstadoPermitePublicacion(string $estado, int $stock = 1): bool
{
    return $estado === 'Disponible' && $stock > 0;
}

function vehiculoCampoTexto(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            return trim((string)$data[$key]);
        }
    }

    return '';
}

function vehiculoCampoNumero(array $data, array $keys): float
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            return (float)$data[$key];
        }
    }

    return 0.0;
}

function vehiculoTieneImagenPrincipal(array $data): bool
{
    foreach (['TieneImagen', 'tieneImagen', 'ImagenPrincipal', 'RutaImagen', 'imagen_principal'] as $key) {
        if (!empty($data[$key])) {
            return true;
        }
    }

    return false;
}

function vehiculoChecklistPublicacion(array $data): array
{
    $faltantes = [];

    $estado = vehiculoCampoTexto($data, ['Estado', 'estado']);
    $stock = (int)vehiculoCampoNumero($data, ['Stock', 'stock']);
    $precioVenta = vehiculoCampoNumero($data, ['PrecioVenta', 'precio_venta', 'Precio', 'precio']);
    $modelo = vehiculoCampoTexto($data, ['Modelo', 'Nombre', 'modelo', 'nombre']);
    $slug = vehiculoCampoTexto($data, ['Slug', 'slug']);
    $descripcionWeb = vehiculoCampoTexto($data, ['DescripcionWeb', 'descripcion_web']);
    $tieneImagen = vehiculoTieneImagenPrincipal($data);

    if ($modelo === '') {
        $faltantes[] = 'modelo';
    }

    if ($precioVenta <= 0) {
        $faltantes[] = 'precio de venta';
    }

    if ($slug === '') {
        $faltantes[] = 'slug';
    }

    if ($descripcionWeb === '') {
        $faltantes[] = 'descripción web';
    }

    if (!$tieneImagen) {
        $faltantes[] = 'imagen principal';
    }

    if ($estado !== '' && $estado !== 'Disponible') {
        $faltantes[] = 'estado disponible';
    }

    if (array_key_exists('Stock', $data) || array_key_exists('stock', $data)) {
        if ($stock <= 0) {
            $faltantes[] = 'stock disponible';
        }
    }

    return [
        'publicable' => empty($faltantes),
        'faltantes' => $faltantes,
    ];
}

function vehiculoMotivoBloqueoPublicacion(array $data): string
{
    $check = vehiculoChecklistPublicacion($data);
    if ($check['publicable']) {
        return '';
    }

    return 'No se puede publicar porque faltan o no cumplen estos requisitos: ' . implode(', ', $check['faltantes']) . '.';
}

function vehiculoPuedeDestacarse(string $estado, int $stock, int $mostrarEnWeb): bool
{
    return vehiculoEstadoPermitePublicacion($estado, $stock) && $mostrarEnWeb === 1;
}

function vehiculoMotivoBloqueoDestacado(array $data): string
{
    $mostrarEnWeb = (int)vehiculoCampoNumero($data, ['MostrarEnWeb', 'mostrar_en_web']);

    if ($mostrarEnWeb !== 1) {
        return 'No se puede destacar porque el vehículo no está visible en la web.';
    }

    $check = vehiculoChecklistPublicacion($data);
    if (!$check['publicable']) {
        return 'No se puede destacar porque primero debe quedar publicable: ' . implode(', ', $check['faltantes']) . '.';
    }

    return '';
}

function vehiculoAnalizarPublicacion(array $data): array
{
    $estado = vehiculoCampoTexto($data, ['Estado', 'estado']);
    $stock = (int)vehiculoCampoNumero($data, ['Stock', 'stock']);
    $mostrarEnWeb = (int)vehiculoCampoNumero($data, ['MostrarEnWeb', 'mostrar_en_web']);
    $destacadoWeb = (int)vehiculoCampoNumero($data, ['DestacadoWeb', 'destacado_web']);

    $check = vehiculoChecklistPublicacion($data);
    $publicable = $check['publicable'];
    $visibleReal = $publicable && $mostrarEnWeb === 1;
    $puedeDestacarse = $visibleReal;
    $motivoPublicacion = $publicable ? '' : vehiculoMotivoBloqueoPublicacion($data);
    $motivoDestacado = $puedeDestacarse ? '' : vehiculoMotivoBloqueoDestacado($data);

    $etiquetas = [];
    if ($visibleReal) {
        $etiquetas[] = 'Visible en web';
    } elseif ($mostrarEnWeb === 1) {
        $etiquetas[] = 'Flag web activo, pero bloqueado';
    } else {
        $etiquetas[] = 'No visible';
    }

    if ($destacadoWeb === 1 && $visibleReal) {
        $etiquetas[] = 'Destacado activo';
    } elseif ($destacadoWeb === 1) {
        $etiquetas[] = 'Destacado bloqueado';
    }

    if ($publicable) {
        $etiquetas[] = 'Publicable';
    }

    return [
        'publicable' => $publicable,
        'faltantes' => $check['faltantes'],
        'motivo_publicacion' => $motivoPublicacion,
        'visible_real' => $visibleReal,
        'puede_destacarse' => $puedeDestacarse,
        'motivo_destacado' => $motivoDestacado,
        'etiquetas' => $etiquetas,
    ];
}

function vehiculoNormalizarPublicacion(string $estado, int $stock, int $mostrarEnWeb, int $destacadoWeb = 0): array
{
    if (!vehiculoEstadoPermitePublicacion($estado, $stock)) {
        return [
            'MostrarEnWeb' => 0,
            'DestacadoWeb' => 0,
        ];
    }

    $mostrarEnWeb = $mostrarEnWeb === 1 ? 1 : 0;
    $destacadoWeb = ($destacadoWeb === 1 && $mostrarEnWeb === 1) ? 1 : 0;

    return [
        'MostrarEnWeb' => $mostrarEnWeb,
        'DestacadoWeb' => $destacadoWeb,
    ];
}

function stockSegunEstadoMoto(string $estado): int
{
    return in_array($estado, ['Vendido', 'Sin stock'], true) ? 0 : 1;
}

function sqlMotoVisibleEnWeb(string $alias = 'p'): string
{
    return sprintf(
        "%s.TipoProducto = 'Moto' AND %s.MostrarEnWeb = 1 AND %s.Estado = 'Disponible' AND %s.Stock > 0",
        $alias,
        $alias,
        $alias,
        $alias
    );
}
