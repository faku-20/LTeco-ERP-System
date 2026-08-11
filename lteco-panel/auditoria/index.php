<?php
$pageTitle = "Auditoría | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";
requiereSuperadmin();
requiereAuditoria();

$filtros = [
    'q' => normalizarTextoHumano($_GET['q'] ?? '', 120),
    'modulo' => normalizarTextoHumano($_GET['modulo'] ?? '', 80),
    'accion' => normalizarTextoHumano($_GET['accion'] ?? '', 80),
    'usuario' => normalizarTextoHumano($_GET['usuario'] ?? '', 100),
    'desde' => normalizarTextoHumano($_GET['desde'] ?? '', 10),
    'hasta' => normalizarTextoHumano($_GET['hasta'] ?? '', 10),
];

$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 50;
$totalRegistros = 0;
$registros = [];
$modulos = [];
$acciones = [];
$usuarios = [];

$auditoriaService = new \Lteco\Application\Auditoria\AuditoriaConsultaService(
    new \Lteco\Infrastructure\Repository\AuditoriaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$tablaExiste = false;
try {
    $tablaExiste = $auditoriaService->tablaExiste();
} catch (Throwable $e) {
    $tablaExiste = false;
}

if ($tablaExiste) {
    try {
        $filtrosConsulta = $filtros;
        if ($filtrosConsulta['desde'] !== '' && !fechaYmdValida($filtrosConsulta['desde'])) {
            $filtrosConsulta['desde'] = '';
        }
        if ($filtrosConsulta['hasta'] !== '' && !fechaYmdValida($filtrosConsulta['hasta'])) {
            $filtrosConsulta['hasta'] = '';
        }

        $consulta = $auditoriaService->consultar($filtrosConsulta, $pagina, $porPagina);
        $totalRegistros = $consulta['total'];
        $registros = $consulta['registros'];
        $modulos = $consulta['modulos'];
        $acciones = $consulta['acciones'];
        $usuarios = $consulta['usuarios'];
    } catch (Throwable $e) {
        logPanelError('auditoria_cargar', $e);
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo cargar la auditoría.'));
    }
}

$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$hayFiltros = array_filter($filtros, static fn($valor) => $valor !== '') !== [];

function auditoriaBadgeClass(string $accion): string
{
    return match (true) {
        str_contains($accion, 'CREAR') => 'audit-badge audit-badge--create',
        str_contains($accion, 'EDITAR'), str_contains($accion, 'CONFIG') => 'audit-badge audit-badge--update',
        str_contains($accion, 'ANULAR'), str_contains($accion, 'OCULTAR'), str_contains($accion, 'DESACTIVAR') => 'audit-badge audit-badge--danger',
        str_contains($accion, 'PUBLICAR'), str_contains($accion, 'ACTIVAR'), str_contains($accion, 'DESTACAR') => 'audit-badge audit-badge--success',
        default => 'audit-badge',
    };
}

function auditoriaPaginaUrl(int $pagina): string
{
    $params = $_GET;
    $params['p'] = max(1, $pagina);
    return panelBaseUrl('auditoria/index.php') . '?' . http_build_query($params);
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Auditoría del sistema</h1>
            <p class="subtle">Registro de acciones importantes realizadas desde el panel interno.</p>
        </div>
        <span class="results-count"><?= (int)$totalRegistros ?> registros</span>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <?php if (!$tablaExiste): ?>
        <div class="notice notice--warning">
            La tabla <strong>auditoria</strong> todavía no existe. Ejecutá el patch de base de datos de la V2 antes de usar esta pantalla.
        </div>
    <?php else: ?>
        <section class="v4-card filters-v4">
            <form method="GET" class="audit-filters">
                <div class="filter-group filter-group--search">
                    <label class="form-label">Buscar</label>
                    <input class="input" type="text" name="q" value="<?= h($filtros['q'], '') ?>" placeholder="Detalle, IP, JSON o navegador">
                </div>

                <div class="filter-group">
                    <label class="form-label">Módulo</label>
                    <select class="input" name="modulo">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $modulo): ?>
                            <option value="<?= h($modulo, '') ?>" <?= $filtros['modulo'] === $modulo ? 'selected' : '' ?>><?= h($modulo, '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Acción</label>
                    <select class="input" name="accion">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $accion): ?>
                            <option value="<?= h($accion, '') ?>" <?= $filtros['accion'] === $accion ? 'selected' : '' ?>><?= h($accion, '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Usuario</label>
                    <select class="input" name="usuario">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= h($usuario, '') ?>" <?= $filtros['usuario'] === $usuario ? 'selected' : '' ?>><?= h($usuario, '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Desde</label>
                    <input class="input" type="date" name="desde" value="<?= h($filtros['desde'], '') ?>">
                </div>

                <div class="filter-group">
                    <label class="form-label">Hasta</label>
                    <input class="input" type="date" name="hasta" value="<?= h($filtros['hasta'], '') ?>">
                </div>

                <div class="filters-actions audit-filters__actions">
                    <button class="btn" type="submit">Filtrar</button>
                    <?php if ($hayFiltros): ?>
                        <a class="btn-secondary" href="<?= panelBaseUrl('auditoria/index.php') ?>">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="v4-card">
            <div class="list-head-v4">
                <div>
                    <h2>Movimientos recientes</h2>
                    <p>Mostrando hasta <?= (int)$porPagina ?> registros por p&aacute;gina.</p>
                </div>
                <div class="result-pill-v4"><?= (int)$totalRegistros ?> registros</div>
            </div>
        </section>

            <?php if (!$registros): ?>
                <div class="empty-state">
                    <strong>No hay registros para mostrar.</strong>
                    <p>Probá limpiar filtros o realizar una acción auditada en el sistema.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table audit-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Detalle</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $row): ?>
                                <?php $accion = (string)($row['Accion'] ?? ''); ?>
                                <tr>
                                    <td>
                                        <strong><?= h(formatearFechaCorta((string)($row['FechaHora'] ?? '')), '') ?></strong><br>
                                        <span class="muted-small">#<?= (int)($row['IdAuditoria'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= h($row['Usuario'] ?? 'Sistema') ?></strong><br>
                                        <span class="muted-small"><?= h($row['Rol'] ?? '') ?></span>
                                    </td>
                                    <td><?= h($row['Modulo'] ?? '') ?></td>
                                    <td><span class="<?= h(auditoriaBadgeClass($accion), '') ?>"><?= h($accion, '') ?></span></td>
                                    <td>
                                        <?= h($row['Detalle'] ?? '') ?>
                                        <?php if (!empty($row['ExtraJson'])): ?>
                                            <details class="audit-json">
                                                <summary>Datos técnicos</summary>
                                                <pre><?= h($row['ExtraJson'], '') ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= h($row['Ip'] ?? '') ?><br>
                                        <span class="muted-small"><?= h(mb_substr((string)($row['UserAgent'] ?? ''), 0, 80, 'UTF-8'), '') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="pager">
                        <?php if ($pagina > 1): ?>
                            <a class="btn-secondary" href="<?= h(auditoriaPaginaUrl($pagina - 1), '') ?>">Anterior</a>
                        <?php endif; ?>
                        <span class="pager__status">Página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?></span>
                        <?php if ($pagina < $totalPaginas): ?>
                            <a class="btn-secondary" href="<?= h(auditoriaPaginaUrl($pagina + 1), '') ?>">Siguiente</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
