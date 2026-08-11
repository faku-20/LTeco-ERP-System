<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

function requiereDistribuidorPanel(): int
{
    requiereLogin();

    if (!esDistribuidor()) {
        denegarAcceso('Solo distribuidores pueden acceder a esta sección.');
    }

    return distribuidorActualId();
}

function distribuidorActualId(): int
{
    $usuario = usuarioActual() ?? [];
    return (int)($usuario['IdDistribuidor'] ?? 0);
}

function distribuidorItemLabel(array $row): string
{
    $tipo = (string)($row['TipoItem'] ?? 'Repuesto');
    if ($tipo === 'Vehiculo') {
        $modelo = trim((string)($row['VehiculoNombre'] ?? $row['Nombre'] ?? 'Vehículo'));
        $idVehiculo = trim((string)($row['IdVehiculo'] ?? ''));
        return $modelo . ($idVehiculo !== '' ? ' · ' . $idVehiculo : '');
    }

    return trim((string)($row['RepuestoNombre'] ?? $row['Nombre'] ?? 'Repuesto'));
}

function distribuidorWhatsappNotificar(PDO $pdo, int $idDistribuidor, string $mensaje): ?string
{
    require_once __DIR__ . '/../includes/whatsapp.php';
    $telefono = whatsappService($pdo)->telefonoDistribuidor($idDistribuidor);
    return $telefono !== null ? linkWhatsappPanel($telefono, $mensaje) : null;
}
