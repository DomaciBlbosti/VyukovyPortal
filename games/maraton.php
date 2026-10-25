<?php
/**
 * Matematický maraton — sto příkladů ze všech témat ročníku, na čas.
 *
 * Příklady se losují rovným dílem z každého tématu, které ročník má
 * (násobilku zastupuje „celá násobilka", ne deset řad), a zamíchají se,
 * ať dítě musí u každého přepnout hlavu. Měří se čas; rekord je kolo
 * s nejmíň chybami a při stejném počtu chyb nejrychlejší.
 *
 * Kolo se ukládá pod vlastním herním typem (kvůli rekordům a výzvě), ale
 * chyby padají do chybovníku k matematice — při tréninku té které sady se
 * pak nabídnou znovu.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/data/math.php';
require_once __DIR__ . '/../includes/mistakes.php';
require_once __DIR__ . '/../includes/marathon.php';

const MARATHON_COUNT = 100;

// Sestava: bez ní jede „vše pro ročník" (klíč ''), jinak to, co rodič poskládal
$presetId = (int)($_GET['sestava'] ?? $_POST['sestava'] ?? 0);
$preset   = $presetId ? getMarathonPreset($presetId) : null;
$variant  = $preset ? (string)$preset['id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    // Odpovědi roztřídíme podle sady, kterou nese klíč příkladu — do chybovníku
    // patří „násobilka — řada 7", ne „maraton"
    $groups = [];
    foreach (parseAnswerPayload($_POST['answers'] ?? null) as $item) {
        $split = mathSplitKey((string)$item['key']);
        if (!$split) continue;
        $t = mathTopics()[$split['topic']];
        $g = $split['topic'] . '/' . $split['variant'];
        $groups[$g] ??= ['topic' => $g, 'topic_label' => $t['label'] . ' — ' . $t['variants'][$split['variant']], 'items' => []];
        $groups[$g]['items'][] = $item;
    }

    // Nestihnuté příklady (vypršel limit) se počítají jako chyby — jinak by
    // rekord držel ten, kdo stihl nejmíň a nic nespletl
    $unanswered = max(0, intval($_POST['unanswered'] ?? 0));
    $result = saveGameResult([
        'game_type'         => 'math_marathon',
        'answers_game_type' => 'math',
        'variant'           => $variant,
        'topic'             => $variant,     // krok výzvy míří na konkrétní sestavu, nebo na jakýkoli maraton
        'wpm'               => floatval($_POST['wpm']       ?? 0),
        'accuracy'          => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds'  => intval($_POST['duration']    ?? 0),
        'chars_typed'       => intval($_POST['chars_typed'] ?? 0),
        'errors'            => intval($_POST['errors']      ?? 0) + $unanswered,
        'text_snippet'      => 'Matematický maraton' . ($preset ? ' — ' . $preset['title'] : ''),
        'answer_groups'     => array_values($groups),
    ]);

    // Rekord se pozná až po uložení: je to tohle kolo?
    $rec = marathonRecord((int)getCurrentUser()['id'], $variant);
    $result['marathon'] = [
        'time'       => marathonTime(intval($_POST['duration'] ?? 0)),
        'is_record'  => $rec['best'] && (int)$result['id'] === $rec['best']['id'],
        'best'       => $rec['best'] ? marathonLabel($rec['best']) : '',
        'count'      => $rec['count'],
        'unanswered' => $unanswered,
    ];
    echo json_encode($result);
    exit;
}

$user  = getCurrentUser();
$grade = getUserGrade();
$mode  = ($_GET['mode'] ?? 'input') === 'choice' ? 'choice' : 'input';

if ($preset) {
    $examples  = generateMathMarathonFrom($preset['categories'], $preset['count']);
    $cats      = mathMarathonCategories();
    $topicsIn  = array_map(fn($k) => $cats[$k]['label'] ?? $k, $preset['categories']);
    $timeLimit = $preset['time_limit'];
} else {
    $examples  = generateMathMarathon($grade, MARATHON_COUNT);
    $topicsIn  = [];
    foreach (mathMarathonPairs($grade) as [$t, $v]) $topicsIn[$t] = mathTopics()[$t]['label'];
    $timeLimit = 0;
}
if ($mode === 'choice') {
    foreach ($examples as &$ex) $ex['choices'] = mathChoices($ex['a']);
    unset($ex);
}

$presets = marathonPresets();
$record  = marathonRecord((int)$user['id'], $variant);
$qs      = $preset ? '&sestava=' . (int)$preset['id'] : '';

$pageTitle = 'Matematický maraton';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🏃 Matematický <span class="accent">maraton</span><?= $preset ? ' — ' . htmlspecialchars($preset['title']) : '' ?></h1>
    <p class="page-subtitle"><?= count($examples) ?> příkladů<?= $preset ? '' : ' ze všech témat' ?><?= $timeLimit ? ', limit ' . marathonTime($timeLimit) : ', na čas' ?></p>
</div>

<div class="mode-tabs">
    <a href="?mode=input<?= $qs ?>"  class="mode-tab <?= $mode==='input' ?'active':'' ?>">⌨ Psaní výsledku</a>
    <a href="?mode=choice<?= $qs ?>" class="mode-tab <?= $mode==='choice'?'active':'' ?>">🎯 Výběr z možností</a>
</div>

<?php if ($presets): ?>
<div class="marathon-pick">
    <a href="?mode=<?= $mode ?>" class="filter-btn <?= $preset ? '' : 'active' ?>">Vše pro můj ročník<small><?= MARATHON_COUNT ?> příkladů</small></a>
    <?php foreach ($presets as $p): ?>
    <a href="?mode=<?= $mode ?>&amp;sestava=<?= (int)$p['id'] ?>" class="filter-btn <?= $preset && $preset['id'] === $p['id'] ? 'active' : '' ?>">
        <?= htmlspecialchars($p['title']) ?><small><?= htmlspecialchars(marathonPresetMeta($p)) ?></small>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="lesson-hint" style="margin-bottom:1rem">
    📌 <?= $preset ? 'Co se počítá' : 'Témata' . ($grade > 0 ? ' pro ' . $grade . '. třídu' : '') ?>: <?= htmlspecialchars(implode(' · ', $topicsIn)) ?>.
    Příklady se střídají, u každého je napsáno, jak odpověď zapsat.
    <?php if ($timeLimit): ?><br>⏰ Na celý maraton máš <strong><?= marathonTime($timeLimit) ?></strong>. Co nestihneš, počítá se jako chyba.<?php endif; ?>
</div>

<?php if ($record['best']): ?>
<div class="lesson-hint marathon-record" style="margin-bottom:1.25rem">
    🏅 Tvůj rekord: <strong><?= htmlspecialchars(marathonLabel($record['best'])) ?></strong>
    <?php if ($record['last'] && $record['last']['id'] !== $record['best']['id']): ?>
    · naposledy <?= htmlspecialchars(marathonLabel($record['last'])) ?>
    <?php endif; ?>
    · odběhnuto <?= $record['count'] ?>×
</div>
<?php endif; ?>

<div class="game-container" id="gameContainer" style="max-width:600px">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="statRemain"><?= count($examples) ?></span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="statTime"><?= $timeLimit ?: 0 ?></span><span class="gstat-label"><?= $timeLimit ? 'zbývá s' : 'sekund' ?></span></div>
    </div>

    <div class="math-question-wrapper">
        <div class="math-question" id="mathQuestion">–</div>
        <div class="math-hint" id="mathHint"></div>
        <div class="math-feedback" id="mathFeedback"></div>
    </div>

    <div class="math-dots math-dots-many" id="mathDots"></div>

    <?php if ($mode === 'input'): ?>
    <div class="typing-input-wrapper">
        <input type="text" inputmode="text" id="mathInput" class="typing-input"
               style="font-size:1.5rem;text-align:center"
               placeholder="napiš výsledek..." autocomplete="off" disabled>
        <button id="startBtn" class="btn-primary">Začít ▶</button>
        <button id="submitBtn" class="btn-primary" style="display:none">✓ OK</button>
    </div>
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
    <h2>🏃 Maraton doběhnut</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div class="results-actions">
        <a href="?mode=<?= $mode ?><?= $qs ?>" class="btn-primary">↺ Běžet znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const MATH_EXAMPLES = <?= json_encode(array_values($examples), JSON_UNESCAPED_UNICODE) ?>;
const MATH_SET      = 'Matematický maraton';
const MATH_TOPIC    = '';
const MATH_VARIANT  = '';
const MATH_TIME_LIMIT = <?= (int)$timeLimit ?>;   // sekund, 0 = bez limitu
const SAVE_URL      = '<?= BASE_URL ?>/games/maraton.php<?= $preset ? '?sestava=' . (int)$preset['id'] : '' ?>';
</script>
<?php if ($mode === 'input'): ?>
<script src="<?= asset_url('/js/math_game.js') ?>"></script>
<?php else: ?>
<script src="<?= asset_url('/js/math_choice.js') ?>"></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
