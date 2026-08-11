<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requiereModulo('vehiculos');

$idVehiculo = trim((string)($_GET['id'] ?? ''));

if ($idVehiculo === '') {
    redirect(panelBaseUrl('vehiculos/index.php'));
}

redirect(panelBaseUrl('vehiculos/etiqueta_multi.php?ids=' . urlencode($idVehiculo) . '&copias=1'));
