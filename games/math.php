<?php
/**
 * Matematika — témata podle ročníku (sčítání, násobilka po řadách,
 * dělení se zbytkem, desetinná čísla, zlomky, dělitelnost).
 * Každé téma jde hrát psaním výsledku nebo výběrem ze šesti možností.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/data/math.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(saveGameResult([
        'game_type'        => 'math',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? 'matematika', 0, 100),
    ]));
    exit;
}

$grade  = getUserGrade();
$topics = mathTopicsForGrade($grade);
if (!$topics) $topics = mathTopics();

$topic = $_GET['topic'] ?? array_key_first($topics);
if (!isset($topics[$topic])) $topic = array_key_first($topics);

$variants = $topics[$topic]['variants'];
$variant  = $_GET['v'] ?? array_key_first($variants);
if (!isset($variants[$variant])) $variant = array_key_first($variants);

// Režim: 'input' = psaní výsledku, 'choice' = výběr ze 6 možností
$mode = ($_GET['mode'] ?? 'input') === 'choice' ? 'choice' : 'input';

$examples = generateMathExamples($topic, $variant, 15);
if ($mode === 'choice') {
    foreach ($examples as &$ex) $ex['choices'] = mathChoices($ex['a']);
    unset($ex);
}

$setLabel  = $topics[$topic]['label'] . ' — ' . $variants[$variant];
$pageTitle = 'Matematika';
include __DIR__ . '/../includes/header.php';

/** Odkaz se zachováním ostatních parametrů */
function mathUrl(array $override): string {
    $q = array_merge(['topic' => $_GET['topic'] ?? '', 'v' => $_GET['v'] ?? '', 'mode' => $_GET['mode'] ?? 'input'], $override);
    return '?' . http_build_query(array_filter($q, fn($x) => $x !== ''));
}
?>

<div class="page-header">
    <h1>🔢 <span class="accent">Matematika</span></h1>
    <p class="page-subtitle"><?= $mode === 'choice' ? 'Vypočítej a vyber správný výsledek' : 'Vypočítej a napiš výsledek' ?></p>
</div>

<div class="mode-tabs">
    <a href="<?= mathUrl(['topic' => $topic, 'v' => $variant, 'mode' => 'input']) ?>"  class="mode-tab <?= $mode==='input' ?'active':'' ?>">⌨ Psaní výsledku</a>
    <a href="<?= mathUrl(['topic' => $topic, 'v' => $variant, 'mode' => 'choice']) ?>" class="mode-tab <?= $mode==='choice'?'active':'' ?>">🎯 Výběr z možností</a>
</div>

<div class="filters" style="margin-bottom:.75rem">
    <div class="filter-group">
        <span class="filter-label">Téma:</span>
        <?php foreach ($topics as $key => $t): ?>
        <a href="<?= mathUrl(['topic' => $key, 'v' => '']) ?>" class="filter-btn <?= $key === $topic ? 'active' : '' ?>">
            <?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label"><?= $topic === 'nasobilka' ? 'Řada:' : 'Obtížnost:' ?></span>
        <?php foreach ($variants as $key => $label): ?>
        <a href="<?= mathUrl(['topic' => $topic, 'v' => $key]) ?>" class="filter-btn <?= $key === $variant ? 'active' : '' ?>">
            <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Zadání držíme krátká, aby se na mobilu nelámala — co po dítěti chceme,
// říká tahle věta nad hrou.
$taskNote = match (true) {
    $topic === 'zlomky'    && $variant === 'kraceni' => 'Zkrať zlomek na základní tvar.',
    $topic === 'zlomky'                              => 'Vypočítej část z celku.',
    $topic === 'deleni'    && $variant === 'se'      => 'Vyděl se zbytkem.',
    $topic === 'delitelnost' && $variant === 'nsd'   => 'Najdi největší společný dělitel obou čísel.',
    $topic === 'delitelnost'                         => 'Spočítej, kolik má číslo dělitelů (včetně 1 a sebe sama).',
    default => '',
};
?>
<?php if ($taskNote): ?>
<div class="lesson-hint" style="margin-bottom:1rem">📌 <?= $taskNote ?></div>
<?php endif; ?>

<?php if ($grade > 0): ?>
<div class="lesson-hint" style="margin-bottom:1.25rem">
    💡 Témata odpovídají <strong><?= $grade ?>. třídě</strong>. Změnit ročník může rodič v nastavení účtu.
</div>
<?php endif; ?>

<div class="game-container" id="gameContainer" style="max-width:600px">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="statRemain"><?= count($examples) ?></span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="statTime">0</span><span class="gstat-label">sekund</span></div>
    </div>

    <!-- Aktuální příklad -->
    <div class="math-question-wrapper">
        <div class="math-question" id="mathQuestion">–</div>
        <div class="math-feedback" id="mathFeedback"></div>
    </div>

    <!-- Progress příkladů -->
    <div class="math-dots" id="mathDots"></div>

    <?php if ($mode === 'input'): ?>
    <div class="typing-input-wrapper">
        <input type="text" inputmode="<?= in_array($topic, ['zlomky','deleni','desetinna'], true) ? 'text' : 'numeric' ?>"
               id="mathInput" class="typing-input"
               style="font-size:1.5rem;text-align:center"
               placeholder="napiš výsledek..." autocomplete="off" disabled>
        <button id="startBtn" class="btn-primary">Začít ▶</button>
        <button id="submitBtn" class="btn-primary" style="display:none">✓ OK</button>
    </div>
    <?php if ($topic === 'zlomky' && $variant === 'kraceni'): ?>
    <p style="text-align:center;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">Zlomek piš ve tvaru 3/4</p>
    <?php elseif ($topic === 'deleni' && $variant === 'se'): ?>
    <p style="text-align:center;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">Piš ve tvaru „3 zb 2"</p>
    <?php elseif ($topic === 'desetinna'): ?>
    <p style="text-align:center;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">Desetinná čísla piš s čárkou, např. 4,5</p>
    <?php endif; ?>
    <?php else: ?>
    <div class="math-choice-grid" id="choiceGrid" style="display:none"></div>
    <div style="text-align:center;margin-bottom:1rem">
        <button id="startBtn" class="btn-primary">Začít ▶</button>
    </div>
    <div style="text-align:center;margin-bottom:.75rem;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">
        Klepni na správný výsledek (na počítači i klávesy 1–6 zleva)
    </div>
    <?php endif; ?>

    <div class="progress-bar-wrapper">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>🔢 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div class="results-actions">
        <a href="<?= mathUrl(['topic' => $topic, 'v' => $variant]) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const MATH_EXAMPLES = <?= json_encode(array_values($examples), JSON_UNESCAPED_UNICODE) ?>;
const MATH_SET      = <?= json_encode($setLabel, JSON_UNESCAPED_UNICODE) ?>;
const SAVE_URL      = '<?= BASE_URL ?>/games/math.php';
</script>
<?php if ($mode === 'input'): ?>
<script src="<?= asset_url('/js/math_game.js') ?>"></script>
<?php else: ?>
<script src="<?= asset_url('/js/math_choice.js') ?>"></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
