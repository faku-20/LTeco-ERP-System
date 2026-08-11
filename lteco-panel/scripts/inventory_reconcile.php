<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

$service = new \Lteco\Application\Inventario\InventarioReconciliadorService(
    \Lteco\Infrastructure\Db\Connection::desdeGlobal()
);

$result = $service->ejecutar();
$hasErrors = false;
foreach ($result as $check => $row) {
    if ($row['count'] > 0 && $row['severity'] === 'ERROR') {
        $hasErrors = true;
    }
    echo sprintf(
        "%s %s count=%d ids=%s\n",
        $row['count'] === 0 ? 'OK' : $row['severity'],
        $check,
        $row['count'],
        implode(',', $row['ids'])
    );
}

exit($hasErrors ? 1 : 0);
