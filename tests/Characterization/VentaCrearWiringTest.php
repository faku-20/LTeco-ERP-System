<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/Assert.php';

$source = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/crear.php');
Assert::isTrue('Wiring crear venta', 'archivo legible', is_string($source));

if (is_string($source)) {
    Assert::same(
        'Wiring crear venta',
        'usa servicio del formulario una vez',
        1,
        substr_count($source, '$ventaCreateForm->cargar(')
    );
    Assert::same(
        'Wiring crear venta',
        'reutiliza servicio comercial',
        1,
        substr_count($source, 'new \\Lteco\\Application\\Venta\\VentaCommercialService(')
    );
    Assert::same(
        'Wiring crear venta',
        'no ejecuta consultas SQL directas',
        0,
        substr_count($source, '$pdo->query(') + substr_count($source, '$pdo->prepare(')
    );
    Assert::same(
        'Wiring crear venta',
        'conserva destino del formulario',
        1,
        substr_count($source, 'panelBaseUrl(\'ventas/guardar.php\')')
    );
    Assert::same(
        'Wiring crear venta',
        'conserva id del formulario',
        1,
        substr_count($source, 'id="ventaForm"')
    );
    Assert::same(
        'Wiring crear venta',
        'conserva inicialización JS',
        1,
        substr_count($source, 'let comisionDistribuidorPct = <?= json_encode($comisionDistribuidor) ?>;')
    );
}

if (Assert::$failed > 0) {
    foreach (Assert::$failures as $failure) {
        fwrite(STDERR, $failure . PHP_EOL);
    }
    exit(1);
}

echo sprintf("OK - %d aserciones de wiring de crear.php pasaron.\n", Assert::$passed);
