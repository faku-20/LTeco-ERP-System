<?php
$pageTitle = 'Asistente IA | ERP';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/ai.php';

requiereNoDistribuidor();
aiEnsureSchema($pdo);

$scopes = aiScopesForCurrentUser();
$scope = (string)($_SESSION['ai_scope'] ?? 'general');
if (!array_key_exists($scope, $scopes)) {
    $scope = 'general';
}
$question = (string)($_SESSION['ai_question'] ?? '');
$answer = (string)($_SESSION['ai_answer'] ?? '');
unset($_SESSION['ai_answer'], $_SESSION['ai_question'], $_SESSION['ai_scope']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div>
            <p class="eyebrow">Asistencia transversal</p>
            <h1>Asistente IA</h1>
            <p>Consultas sobre tareas, ventas, balance, stock y postventa.</p>
        </div>
        <div class="actions-row">
            <?php if (!esVendedor()): ?>
                <a class="btn-secondary" href="<?= panelBaseUrl('ia/base.php') ?>">Base comercial IA</a>
            <?php endif; ?>
            <a class="btn-secondary" href="<?= panelBaseUrl('automatizaciones/index.php') ?>">Automatizaciones</a>
        </div>
    </header>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="section-box ai-query-panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Consulta manual</p>
                <h2>Preguntar sobre el panel</h2>
                <p>La IA usa datos guardados en el sistema y no ejecuta acciones.</p>
            </div>
        </div>
        <form class="filter-bar ai-query-panel" method="post" action="<?= panelBaseUrl('ia/preguntar.php') ?>">
            <?= csrfInput() ?>
            <label>Área
                <select name="scope" required>
                    <?php foreach ($scopes as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $scope === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field-wide">Pregunta
                <textarea name="question" rows="4" required minlength="3" maxlength="1200" placeholder="Ej: ¿Qué tareas debería priorizar hoy?"><?= h($question, '') ?></textarea>
            </label>
            <button class="btn" type="submit">Consultar IA</button>
        </form>
    </section>

    <?php if ($answer !== ''): ?>
        <section class="section-box">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Respuesta</p>
                    <h2><?= h($scopes[$scope] ?? 'General') ?></h2>
                </div>
            </div>
            <article class="ai-answer">
                <p class="ai-answer__body"><?= h($answer, '') ?></p>
            </article>
        </section>
    <?php endif; ?>

    <section class="section-box">
        <div class="section-head">
            <div>
                <p class="eyebrow">Atajos</p>
                <h2>Preguntas útiles</h2>
            </div>
        </div>
        <div class="ai-shortcuts">
            <?php
            $atajos = [
                ['scope' => 'tareas', 'title' => 'Priorizar hoy', 'question' => '¿Qué tareas debería priorizar hoy y por qué?'],
                ['scope' => 'comercial', 'title' => 'Seguimiento comercial', 'question' => '¿Qué consultas comerciales requieren seguimiento urgente?'],
                ['scope' => 'stock', 'title' => 'Faltantes de stock', 'question' => '¿Qué problemas de stock o publicación debería resolver primero?'],
                ['scope' => 'balance', 'title' => 'Lectura del mes', 'question' => '¿Cómo está el balance del mes y qué debería mirar primero?'],
            ];
            foreach ($atajos as $atajo):
                if (!array_key_exists($atajo['scope'], $scopes)) { continue; }
            ?>
                <form method="post" action="<?= panelBaseUrl('ia/preguntar.php') ?>" class="ai-shortcut">
                    <?= csrfInput() ?>
                    <input type="hidden" name="scope" value="<?= h($atajo['scope']) ?>">
                    <input type="hidden" name="question" value="<?= h($atajo['question']) ?>">
                    <span><?= h($scopes[$atajo['scope']]) ?></span>
                    <strong><?= h($atajo['title']) ?></strong>
                    <button class="btn-secondary" type="submit">Preguntar</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
