<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";

requiereLogin();
requiereAdmin();

try {
    requirePost();
    verifyCsrfOrFail();

    $fechaGasto = trim((string)($_POST['fecha_gasto'] ?? date('Y-m-d')));
    $concepto = trim((string)($_POST['concepto'] ?? ''));
    $categoria = trim((string)($_POST['categoria'] ?? 'Otros'));
    $metodoPago = trim((string)($_POST['metodo_pago'] ?? 'Efectivo'));
    $moneda = trim((string)($_POST['moneda'] ?? 'UYU'));
    $monto = decimalNoNegativo($_POST['monto'] ?? 0);
    $observaciones = limpiarTextoOpcional($_POST['observaciones'] ?? null);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaGasto)) {
        throw new RuntimeException('La fecha del gasto no es válida.');
    }

    if ($concepto === '') {
        throw new RuntimeException('El concepto del gasto es obligatorio.');
    }

    if (mb_strlen($concepto, 'UTF-8') > 150) {
        throw new RuntimeException('El concepto no puede superar los 150 caracteres.');
    }

    if (!in_array($categoria, categoriasGastoSistema(), true)) {
        $categoria = 'Otros';
    }

    if (!in_array($metodoPago, metodosPagoGastoSistema(), true)) {
        $metodoPago = 'Efectivo';
    }

    if (!in_array($moneda, monedasSistema(), true)) {
        $moneda = 'UYU';
    }

    if ($monto <= 0) {
        throw new RuntimeException('El monto del gasto debe ser mayor a 0.');
    }

    $tipoCambioGasto = obtenerTipoCambio($pdo);

    $service = new \Lteco\Application\Gasto\GastoCrudService(
        new \Lteco\Infrastructure\Repository\GastoCrudRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    );

    $idGasto = $service->crear([
        'fecha_gasto' => $fechaGasto,
        'concepto' => $concepto,
        'categoria' => $categoria,
        'metodo_pago' => $metodoPago,
        'moneda' => $moneda,
        'monto' => $monto,
        'observaciones' => $observaciones,
        'tipo_cambio_aplicado' => $tipoCambioGasto,
    ]);

    registrarAuditoria($pdo, 'CREAR_GASTO', 'Gastos', 'Gasto registrado: ' . $concepto, [
        'id_gasto' => $idGasto,
        'concepto' => $concepto,
        'categoria' => $categoria,
        'metodo_pago' => $metodoPago,
        'moneda' => $moneda,
        'monto' => $monto,
    ]);

    redirectWithFlash(panelBaseUrl('gastos/index.php'), 'success', 'Gasto registrado correctamente.');
} catch (Throwable $e) {
    logPanelError('guardar_gasto', $e, [
        'post_keys' => array_keys($_POST ?? []),
        'usuario' => usuarioActual()['Usuario'] ?? null,
    ]);
    redirectErrorSeguro(panelBaseUrl('gastos/crear.php'), $e, 'No se pudo guardar el gasto.');
}
