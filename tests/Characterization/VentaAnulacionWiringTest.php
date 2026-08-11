<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/anular.php');
if (!is_string($source)) {
    fwrite(STDERR, "No se pudo leer anular.php\n");
    exit(1);
}

$checks = [
    'guard admin' => substr_count($source, 'requiereAdmin();') === 1,
    'guard POST' => substr_count($source, 'requirePost();') === 1,
    'guard CSRF' => substr_count($source, 'verifyCsrfOrFail();') === 1,
    'begin en controlador' => substr_count($source, '$pdo->beginTransaction();') === 1,
    'commit en controlador' => substr_count($source, '$pdo->commit();') === 1,
    'rollback en controlador' => substr_count($source, '$pdo->rollBack();') === 1,
    'servicio cableado una vez' => substr_count($source, '$ventaAnulacion->anular(') === 1,
    'auditoría en controlador' => substr_count($source, 'registrarAuditoria(') === 1,
    'redirect exitoso preservado' => str_contains($source, "'?id=' . \$idVenta . '&ok=anulada'"),
    'sin SQL inline' => preg_match('/\\b(SELECT|UPDATE|INSERT|DELETE)\\b/i', $source) === 0,
];

$fallos = [];
foreach ($checks as $nombre => $ok) {
    echo ($ok ? '  OK ' : '  FAIL ') . $nombre . "\n";
    if (!$ok) {
        $fallos[] = $nombre;
    }
}

if ($fallos === []) {
    echo "\nOK - wiring de anular.php preservado.\n";
    exit(0);
}

echo "\nFALLO - " . implode(', ', $fallos) . "\n";
exit(1);
