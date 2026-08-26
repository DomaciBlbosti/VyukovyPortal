<?php
/**
 * Matematika — témata podle ročníku (sčítání, násobilka po řadách,
 * dělení se zbytkem, desetinná čísla, zlomky, dělitelnost).
 * Každé téma jde hrát psaním výsledku nebo výběrem ze šesti možností.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/data/math.php';
require_once __DIR__ . '/../includes/mistakes.php';
require_once __DIR__ . '/../includes/selection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    // Sadu určuje server podle poslaného tématu a variant, ne prohlížeč.
    // Hraje-li se víc řad najednou, roztřídíme odpovědi podle varianty, kterou
    // nese klíč příkladu — jinak by se chyby z různých řad slily dohromady.
    $t          = (string)($_POST['topic'] ?? '');
    $topicRow   = mathTopics()[$t] ?? null;
    $saveVars   = $topicRow ? parseSelection((string)($_POST['variant'] ?? ''), $topicRow['variants']) : [];
    $saveGroups = [];

    if ($topicRow) {
        foreach (parseAnswerPayload($_POST['answers'] ?? null) as $item) {
            $variant = mathVariantFromKey((string)$item['key'], $t, $saveVars);
            if ($variant === null) continue;
            if (!isset($saveGroups[$variant])) {
                $saveGroups[$variant] = [
                    'topic'       => $t . '/' . $variant,
                    'topic_label' => $topicRow['label'] . ' — ' . $topicRow['variants'][$variant],
                    'items'       => [],
                ];
            }
            $saveGroups[$variant]['items'][] = $item;
        }
    }
    echo json_encode(saveGameResult([
        'game_type'        => 'math',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? 'matematika', 0, 100),
        'answer_groups'    => array_values($saveGroups),
    ]));
    exit;
}

$grade  = getUserGrade();
$topics = mathTopicsForGrade($grade);
if (!$topics) $topics = mathTopics();

$topic = $_GET['topic'] ?? array_key_first($topics);
if (!isset($topics[$topic])) $topic = array_key_first($topics);

$variants = $topics[$topic]['variants'];
// Vybraných variant může být víc — „řada 6 + řada 7"
$picked  = parseSelection($_GET['v'] ?? null, $variants);
$variant = $picked[0];   // pro nápovědy a texty, které se ptají na jednu

// Režim: 'input' = psaní výsledku, 'choice' = výběr ze 6 možností
$mode = ($_GET['mode'] ?? 'input') === 'choice' ? 'choice' : 'input';

$examples = generateMathExamplesMulti($topic, $picked, 15);

// Adaptivní opakování: příklady, které dítě naposled splétlo, dostanou
// přednost před náhodně vylosovanými — z každé vybrané řady.
$practiceRows = [];
foreach ($picked as $v) {
    foreach (mistakesForPractice((int)($_SESSION['user_id'] ?? 0), 'math', $topic . '/' . $v, 4) as $row) {
        $row['variant'] = $v;
        $practiceRows[] = $row;
    }
}
shuffle($practiceRows);
$practiceRows = array_slice($practiceRows, 0, 4);
mathInjectPractice($examples, $practiceRows);
$practiceCount = count(array_intersect(array_column($examples, 'q'), array_column($practiceRows, 'prompt')));

foreach ($examples as &$ex) {
    $ex['key'] = mathItemKey($topic, (string)($ex['variant'] ?? $variant), $ex['q']);
    if ($mode === 'choice') $ex['choices'] = mathChoices($ex['a']);
}
unset($ex);

$setLabel  = $topics[$topic]['label'] . ' — ' . selectionLabel($picked, $variants);
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
    <a href="<?= mathUrl(['topic' => $topic, 'v' => implode(',', $picked), 'mode' => 'input']) ?>"  class="mode-tab <?= $mode==='input' ?'active':'' ?>">⌨ Psaní výsledku</a>
    <a href="<?= mathUrl(['topic' => $topic, 'v' => implode(',', $picked), 'mode' => 'choice']) ?>" class="mode-tab <?= $mode==='choice'?'active':'' ?>">🎯 Výběr z možností</a>
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
        <span class="filter-label"><?= $topic === 'nasobilka' ? 'Řady:' : 'Obtížnost:' ?></span>
        <?php foreach ($variants as $key => $label):
            $on = in_array((string)$key, $picked, true); ?>
        <a href="<?= mathUrl(['topic' => $topic, 'v' => toggleSelection($picked, (string)$key, $variants)]) ?>"
           class="filter-btn filter-btn-multi <?= $on ? 'active' : '' ?>">
            <?= $on ? '✓ ' : '' ?><?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
        <?php if (count($picked) > 1): ?>
        <a href="<?= mathUrl(['topic' => $topic, 'v' => $picked[0]]) ?>" class="filter-btn filter-btn-reset">✕ jen jednu</a>
        <?php endif; ?>
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

<?php if ($practiceCount > 0): ?>
<div class="lesson-hint lesson-hint-practice" style="margin-bottom:1rem">
    🔁 <?= practiceNote($practiceCount, 'ex') ?>
</div>
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
        <a href="<?= mathUrl(['topic' => $topic, 'v' => implode(',', $picked)]) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const MATH_EXAMPLES = <?= json_encode(array_values($examples), JSON_UNESCAPED_UNICODE) ?>;
const MATH_SET      = <?= json_encode($setLabel, JSON_UNESCAPED_UNICODE) ?>;
const MATH_TOPIC    = <?= json_encode($topic) ?>;
const MATH_VARIANT  = <?= json_encode(implode(',', $picked)) ?>;
const SAVE_URL      = '<?= BASE_URL ?>/games/math.php';
</script>
<?php if ($mode === 'input'): ?>
<script src="<?= asset_url('/js/math_game.js') ?>"></script>
<?php else: ?>
<script src="<?= asset_url('/js/math_choice.js') ?>"></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
