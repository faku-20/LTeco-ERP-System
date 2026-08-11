<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function notificarNuevaVenta(array $venta, string $cliente, string $total): void
{
    $idVenta  = (int)($venta['IdVenta'] ?? 0);
    $factura  = htmlspecialchars((string)($venta['NumeroFactura'] ?? '—'));
    $metodo   = htmlspecialchars((string)($venta['MetodoPago'] ?? '—'));
    $fecha    = date('d/m/Y H:i');

    notificar(
        "🛵 Nueva venta #{$idVenta} — {$cliente}",
        "Nueva venta registrada",
        "<p><strong>Cliente:</strong> " . htmlspecialchars($cliente) . "</p>"
        . "<p><strong>Total:</strong> " . htmlspecialchars($total) . "</p>"
        . "<p><strong>Método:</strong> {$metodo}</p>"
        . "<p><strong>Factura:</strong> {$factura}</p>"
        . "<p><strong>Fecha:</strong> {$fecha}</p>"
    );
}

function notificarVentaDistribuidor(array $venta, string $distribuidor, string $cliente, string $total): void
{
    $idVenta = (int)($venta['IdVenta'] ?? 0);
    $fecha   = date('d/m/Y H:i');

    notificar(
        "🏪 Venta distribuidor #{$idVenta} — {$distribuidor}",
        "Venta registrada por distribuidor",
        "<p><strong>Distribuidor:</strong> " . htmlspecialchars($distribuidor) . "</p>"
        . "<p><strong>Cliente:</strong> " . htmlspecialchars($cliente) . "</p>"
        . "<p><strong>Total:</strong> " . htmlspecialchars($total) . "</p>"
        . "<p><strong>Fecha:</strong> {$fecha}</p>"
    );
}

function notificarServicesProximos(array $services): void
{
    if (empty($services)) return;

    $filas = '';
    foreach ($services as $s) {
        $fecha  = date('d/m/Y', strtotime((string)$s['FechaProgramada']));
        $dias   = (int)ceil((strtotime((string)$s['FechaProgramada']) - time()) / 86400);
        $filas .= "<tr>"
            . "<td style='padding:6px 8px'>" . htmlspecialchars((string)($s['NombreApellido'] ?? '—')) . "</td>"
            . "<td style='padding:6px 8px'>" . htmlspecialchars((string)($s['Modelo'] ?? '—')) . "</td>"
            . "<td style='padding:6px 8px'><span style='font-family:monospace'>" . htmlspecialchars((string)($s['NumeroMotor'] ?? '—')) . "</span></td>"
            . "<td style='padding:6px 8px'>{$fecha}</td>"
            . "<td style='padding:6px 8px;color:#c00'><strong>{$dias} días</strong></td>"
            . "</tr>";
    }

    $tabla = "<table style='width:100%;border-collapse:collapse;font-size:13px'>"
        . "<thead><tr style='background:#f0f0f0'>"
        . "<th style='padding:6px 8px;text-align:left'>Cliente</th>"
        . "<th style='padding:6px 8px;text-align:left'>Moto</th>"
        . "<th style='padding:6px 8px;text-align:left'>Motor</th>"
        . "<th style='padding:6px 8px;text-align:left'>Fecha</th>"
        . "<th style='padding:6px 8px;text-align:left'>Días</th>"
        . "</tr></thead><tbody>{$filas}</tbody></table>";

    notificar(
        "⚠️ " . count($services) . " service(s) próximos a vencer",
        "Services próximos a vencer",
        "<p>Los siguientes services vencen en los próximos <strong>7 días</strong>:</p>{$tabla}"
    );
}

function notificarServicesVencidos(array $services): void
{
    if (empty($services)) return;

    $filas = '';
    foreach ($services as $s) {
        $fecha = date('d/m/Y', strtotime((string)$s['FechaProgramada']));
        $filas .= "<tr>"
            . "<td style='padding:6px 8px'>" . htmlspecialchars((string)($s['NombreApellido'] ?? '—')) . "</td>"
            . "<td style='padding:6px 8px'>" . htmlspecialchars((string)($s['Modelo'] ?? '—')) . "</td>"
            . "<td style='padding:6px 8px'><span style='font-family:monospace'>" . htmlspecialchars((string)($s['NumeroMotor'] ?? '—')) . "</span></td>"
            . "<td style='padding:6px 8px;color:#c00'>{$fecha}</td>"
            . "</tr>";
    }

    $tabla = "<table style='width:100%;border-collapse:collapse;font-size:13px'>"
        . "<thead><tr style='background:#f0f0f0'>"
        . "<th style='padding:6px 8px;text-align:left'>Cliente</th>"
        . "<th style='padding:6px 8px;text-align:left'>Moto</th>"
        . "<th style='padding:6px 8px;text-align:left'>Motor</th>"
        . "<th style='padding:6px 8px;text-align:left'>Fecha vencida</th>"
        . "</tr></thead><tbody>{$filas}</tbody></table>";

    notificar(
        "🔴 " . count($services) . " service(s) vencidos sin realizar",
        "Services vencidos",
        "<p>Los siguientes services están vencidos y no fueron realizados:</p>{$tabla}"
    );
}
