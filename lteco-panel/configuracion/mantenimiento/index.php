<?php
$pageTitle = "Mantenimiento | ERP";

require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/helpers.php";
require_once __DIR__ . "/../../includes/whatsapp.php";

requiereSuperadmin();

whatsappEnsureColumnas($pdo);
whatsappEnsureTabla($pdo);
$waCfgMant = whatsappObtenerConfig($pdo);
$waTestPhone = whatsappFormatearTelefono((string)configEnv('LTECO_WHATSAPP_TEST_PHONE', ''));
$waTestPhoneLabel = $waTestPhone !== null ? 'terminado en '.substr($waTestPhone, -3) : null;

$mariadbOnline = (new \Lteco\Application\Mantenimiento\EstadoSistemaService(
    new \Lteco\Infrastructure\Repository\MantenimientoRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
))->mariadbOnline();

$dockerOnline = true;
$ramInfo = '-';
$diskInfo = '-';
$uptimeInfo = '-';

try {
    exec('docker ps 2>&1', $dockerOutput, $dockerResult);
    if ($dockerResult === 0) { $dockerOnline = true; }
} catch (Throwable $e) {
    $dockerOnline = true;
}

try {
    $ramTotalRaw = trim((string)shell_exec("free -m | awk '/Mem:/ {print $2}'"));
    $ramUsedRaw = trim((string)shell_exec("free -m | awk '/Mem:/ {print $3}'"));
    if (is_numeric($ramTotalRaw) && is_numeric($ramUsedRaw) && (int)$ramTotalRaw > 0) {
        $ramTotal = round(((int)$ramTotalRaw) / 1024, 1);
        $ramUsed = round(((int)$ramUsedRaw) / 1024, 1);
        $ramInfo = "{$ramUsed} GB / {$ramTotal} GB";
    }
} catch (Throwable $e) {
    $ramInfo = '-';
}

try {
    $diskInfo = trim((string)shell_exec("df -h / | awk 'NR==2 {print $5}'")) ?: '-';
} catch (Throwable $e) {
    $diskInfo = '-';
}

try {
    $uptimeInfo = trim((string)shell_exec("uptime -p")) ?: '-';
} catch (Throwable $e) {
    $uptimeInfo = '-';
}

$backupDir = ltecoBackupDir();
$backupError = null;
$archivos = [];
try {
    $backupDir = ltecoEnsureBackupDir();
    $scan = scandir($backupDir, SCANDIR_SORT_DESCENDING) ?: [];
    foreach ($scan as $archivo) {
        if ($archivo === '.' || $archivo === '..' || !ltecoBackupFilenameValido($archivo)) {
            continue;
        }
        $ruta = $backupDir . DIRECTORY_SEPARATOR . $archivo;
        if (is_file($ruta)) {
            $archivos[] = $archivo;
        }
    }
} catch (Throwable $e) {
    $backupError = mensajeErrorSeguro($e, 'No se pudo acceder al directorio de backups.');
}

$totalBackups = count($archivos);
$ultimoBackup = $archivos[0] ?? null;
$espacioTotal = 0;
foreach ($archivos as $file) {
    $ruta = $backupDir . DIRECTORY_SEPARATOR . $file;
    if (is_file($ruta)) {
        $espacioTotal += filesize($ruta);
    }
}
$espacioTotalMB = round($espacioTotal / 1024 / 1024, 2);

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Mantenimiento</h1>
            <p class="subtle">Backups, restauración controlada y estado básico de infraestructura.</p>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

    <?php if ($backupError): ?>
        <div class="notice notice--error">
            <strong>Atención:</strong> <?= h($backupError, '') ?><br>
            <small>Directorio configurado: <?= h($backupDir, '') ?></small>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">📦 Último backup</span><strong class="stat-value"><?= $ultimoBackup ? date('d/m/Y H:i', filemtime($backupDir . DIRECTORY_SEPARATOR . $ultimoBackup)) : 'Sin backups' ?></strong></div>
        <div class="stat-card"><span class="stat-label">🗂️ Total backups</span><strong class="stat-value"><?= (int)$totalBackups ?></strong></div>
        <div class="stat-card"><span class="stat-label">💾 Espacio utilizado</span><strong class="stat-value"><?= h((string)$espacioTotalMB, '0') ?> MB</strong></div>
        <div class="stat-card"><span class="stat-label">🟢 MariaDB</span><strong class="stat-value"><?= $mariadbOnline ? 'Online' : 'Offline' ?></strong></div>
        <div class="stat-card"><span class="stat-label">🐳 Docker</span><strong class="stat-value"><?= $dockerOnline ? 'Online' : 'Offline' ?></strong></div>
        <div class="stat-card"><span class="stat-label">🧠 RAM</span><strong class="stat-value"><?= h($ramInfo, '-') ?></strong></div>
        <div class="stat-card"><span class="stat-label">💾 Disco</span><strong class="stat-value"><?= h($diskInfo, '-') ?></strong></div>
        <div class="stat-card"><span class="stat-label">⏱️ Uptime</span><strong class="stat-value"><?= h($uptimeInfo, '-') ?></strong></div>
    </div>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Backups de base de datos</h2>
                <p>Se guardan fuera del webroot: <code><?= h($backupDir, '') ?></code></p>
            </div>
            <form method="POST" action="<?= panelBaseUrl('configuracion/mantenimiento/backup.php') ?>" class="u-m-0">
                <?= csrfInput() ?>
                <button type="submit" class="btn">📦 Crear backup</button>
            </form>
        </div>
    </section>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>WhatsApp Cloud API</h2>
                <p>
                    Estado: <strong><?= $waCfgMant['enabled'] ? 'Activado' : 'Desactivado' ?></strong>
                    &mdash; Phone ID: <code><?= $waCfgMant['phone_id'] !== '' ? h(mb_substr($waCfgMant['phone_id'], 0, 12) . '***', '') : 'No configurado' ?></code>
                </p>
            </div>
            <form method="POST" action="<?= panelBaseUrl('configuracion/mantenimiento/whatsapp_probar.php') ?>" class="u-m-0 u-flex-center-wrap" style="gap:.5rem;">
                <?= csrfInput() ?>
                <input type="text" name="telefono_prueba"
                       placeholder="Teléfono destino"
                       style="max-width:180px;"
                       title="Usá un teléfono distinto al número conectado a WhatsApp Business">
                <input type="text" name="template_prueba"
                       placeholder="Nombre del template"
                       value="<?= h($waCfgMant['tpl_venta'] ?: $waCfgMant['tpl_service'], '') ?>"
                       style="max-width:220px;"
                       required
                       title="Nombre exacto del template aprobado en Meta">
                <button type="submit" class="btn">Probar WhatsApp</button>
            </form>
        </div>
        <div class="list-head-v4" style="margin-top:1rem;">
            <div>
                <h3 style="margin:0;">Reset de teléfono para pruebas IA</h3>
                <p>Habilita una única respuesta inicial para el número de prueba sin borrar conversaciones, leads ni visitas.</p>
            </div>
            <?php if ($waTestPhone !== null): ?>
                <form method="POST" action="<?= panelBaseUrl('configuracion/mantenimiento/whatsapp_reset_prueba.php') ?>" class="u-m-0 u-flex-center-wrap js-confirm-form" style="gap:.5rem;" data-confirm-message="El próximo mensaje del número de prueba se tratará una sola vez como contacto inicial.">
                    <?= csrfInput() ?>
                    <span><?= h($waTestPhoneLabel) ?></span>
                    <button type="submit" class="btn-secondary">Habilitar próxima prueba</button>
                </form>
            <?php else: ?>
                <span>Configurá <code>LTECO_WHATSAPP_TEST_PHONE</code> en el servidor.</span>
            <?php endif; ?>
        </div>
    </section>

    <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tamaño</th>
                        <th>Fecha</th>
                        <th>Descargar</th>
                        <th>Restaurar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($archivos)): ?>
                        <tr><td colspan="5">No hay backups disponibles.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($archivos as $archivo): ?>
                        <?php $ruta = $backupDir . DIRECTORY_SEPARATOR . $archivo; ?>
                        <tr>
                            <td><?= h($archivo, '') ?></td>
                            <td><?= number_format(filesize($ruta) / 1024, 2, ',', '.') ?> KB</td>
                            <td><?= date('d/m/Y H:i', filemtime($ruta)) ?></td>
                            <td>
                                <a class="btn-secondary btn-small" href="<?= panelBaseUrl('configuracion/mantenimiento/download.php?file=' . urlencode($archivo)) ?>">Descargar</a>
                            </td>
                            <td>
                                <?php if (str_ends_with($archivo, '.sql')): ?>
                                    <form method="POST" action="<?= panelBaseUrl('configuracion/mantenimiento/restore.php') ?>" class="u-m-0 js-confirm-form" data-confirm-message="¿Seguro que querés restaurar este backup? Se generará un backup automático antes de restaurar.">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="file" value="<?= h($archivo, '') ?>">
                                        <button type="submit" class="btn-danger btn-small">Restaurar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-muted">Solo descarga</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    </div>
</main>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
