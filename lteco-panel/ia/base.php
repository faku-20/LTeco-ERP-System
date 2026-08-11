<?php
$pageTitle = 'Base comercial IA | Ltecobike';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
aiEnsureSchema($pdo);
$entries = aiInstructionEntries($pdo);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Entrenamiento interno</p>
            <h1>Base comercial IA</h1>
            <p>Reglas y contexto que la IA usa para sugerir respuestas.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('ia/index.php') ?>">Asistente IA</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('automatizaciones/index.php') ?>">Automatizaciones</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <form method="post" action="<?= panelBaseUrl('ia/base_guardar.php') ?>" class="section-box ai-base-form">
        <?= csrfInput() ?>
        <div class="section-head">
            <div>
                <p class="eyebrow">Contexto</p>
                <h2>Instrucciones activas</h2>
                <p>No cargues descuentos, promociones ni precios que no estén vigentes y verificados.</p>
            </div>
            <button class="btn" type="submit">Guardar base IA</button>
        </div>

        <div class="ai-base-grid">
            <?php foreach ($entries as $entry): ?>
                <article class="panel-card">
                    <input type="hidden" name="entries[<?= (int)$entry['IdInstruction'] ?>][id]" value="<?= (int)$entry['IdInstruction'] ?>">
                    <label>Título
                        <input name="entries[<?= (int)$entry['IdInstruction'] ?>][titulo]" maxlength="160" value="<?= h($entry['Titulo']) ?>" required>
                    </label>
                    <label>Contenido
                        <textarea name="entries[<?= (int)$entry['IdInstruction'] ?>][cuerpo]" rows="6" maxlength="4000"><?= h($entry['Cuerpo'], '') ?></textarea>
                    </label>
                    <label class="check-line">
                        <input type="checkbox" name="entries[<?= (int)$entry['IdInstruction'] ?>][activo]" value="1" <?= (int)$entry['Activo'] === 1 ? 'checked' : '' ?>>
                        Activo
                    </label>
                </article>
            <?php endforeach; ?>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
