<?php
$pageTitle = "Gastos | Lteco";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
 
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$tipoCambio = obtenerTipoCambioUSD($pdo);
$categorias = categoriasGastoSistema();
$metodosPago = metodosPagoGastoSistema();

$service = new \Lteco\Application\Gasto\GastoConsultaService(
    new \Lteco\Infrastructure\Repository\GastoConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$filtroCategoria = trim($_GET['categoria'] ?? '');
$filtroMetodo = trim($_GET['metodo'] ?? '');
$filtroDesde = trim($_GET['desde'] ?? '');
$filtroHasta = trim($_GET['hasta'] ?? '');

$gastos = $service->listar([
    'categoria' => $filtroCategoria,
    'metodo' => $filtroMetodo,
    'desde' => $filtroDesde,
    'hasta' => $filtroHasta,
]);

$totalUyu = 0.0;
$totalMesUyu = 0.0;
$mesActual = date('Y-m');

foreach ($gastos as $gasto) {
    $montoUyu = convertirAUyu((float)($gasto['Monto'] ?? 0), $gasto['Moneda'] ?? 'UYU', $tipoCambio);
    $totalUyu += $montoUyu;

    if (strpos((string)($gasto['FechaGasto'] ?? ''), $mesActual) === 0) {
        $totalMesUyu += $montoUyu;
    }
}

$queryExport = http_build_query([
    'categoria' => $filtroCategoria,
    'metodo' => $filtroMetodo,
    'desde' => $filtroDesde,
    'hasta' => $filtroHasta,
]);

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <h1>Gastos</h1>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('gastos/exportar.php' . ($queryExport ? '?' . $queryExport : '')) ?>">Exportar CSV</a>
            <a class="btn" href="<?= panelBaseUrl('gastos/crear.php') ?>">+ Nuevo gasto</a>
        </div>
    </div>

    <section class="v4-card filters-v4">
        <form method="GET" class="filter-grid-5">
            <div>
                <label class="form-label">Categoría</label>
                <select name="categoria" class="input u-w-100">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filtroCategoria === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label">Método</label>
                <select name="metodo" class="input u-w-100">
                    <option value="">Todos</option>
                    <?php foreach ($metodosPago as $metodo): ?>
                        <option value="<?= htmlspecialchars($metodo) ?>" <?= $filtroMetodo === $metodo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($metodo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label">Desde</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($filtroDesde) ?>" class="input u-w-100">
            </div>

            <div>
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($filtroHasta) ?>" class="input u-w-100">
            </div>

            <div class="u-flex-gap-8">
                <button type="submit" class="btn">Filtrar</button>
                <a href="<?= panelBaseUrl('gastos/index.php') ?>" class="btn-secondary">Limpiar</a>
            </div>
        </form>
    </section>

    <div class="cards">
        <div class="card">
            <h3>Gastos filtrados</h3>
            <strong><?= count($gastos) ?></strong>
        </div>

        <div class="card">
            <h3>Total gastos</h3>
            <strong><?= formatearMonto($totalUyu, 'UYU') ?></strong>
        </div>

        <div class="card">
            <h3>Gastos del mes</h3>
            <strong><?= formatearMonto($totalMesUyu, 'UYU') ?></strong>
        </div>
    </div>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Listado de gastos</h2>
            </div>
            <div class="result-pill-v4"><?= count($gastos) ?> resultados</div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Categoría</th>
                    <th>Método</th>
                    <th>Moneda</th>
                    <th>Monto</th>
                    <th>Ref. UYU</th>
                    <th>Concepto</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($gastos): ?>
                    <?php foreach ($gastos as $gasto): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($gasto['FechaGasto'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($gasto['Categoria'] ?? 'Otros')) ?></td>
                            <td><?= htmlspecialchars((string)($gasto['MetodoPago'] ?? 'Efectivo')) ?></td>
                            <td><?= htmlspecialchars((string)($gasto['Moneda'] ?? 'UYU')) ?></td>
                            <td><?= formatearMonto((float)($gasto['Monto'] ?? 0), $gasto['Moneda'] ?? 'UYU') ?></td>
                            <td><?= formatearMonto(convertirAUyu((float)($gasto['Monto'] ?? 0), $gasto['Moneda'] ?? 'UYU', $tipoCambio), 'UYU') ?></td>
                            <td><?= htmlspecialchars((string)($gasto['Concepto'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)(($gasto['Observaciones'] ?? '') !== '' ? $gasto['Observaciones'] : '-')) ?></td>
                            <td>
                                <a class="btn-small" href="<?= panelBaseUrl('gastos/editar.php?id=' . urlencode((string)$gasto['IdGasto'])) ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No hay gastos cargados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>