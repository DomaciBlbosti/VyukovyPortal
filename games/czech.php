<?php
/**
 * Čeština — pravopisné jevy: vyjmenovaná slova, mě/mně, předložky s/z,
 * velká písmena, ú/ů, koncovky podstatných jmen a shoda přísudku.
 * Nabídka sad se řídí ročníkem žáka; kdo ročník nemá vyplněný, vidí vše.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/data/czech.php';
require_once __DIR__ . '/../includes/mistakes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    $saveCat  = (string)($_POST['cat'] ?? '');
    $catLabel = czechCategories()[$saveCat]['label'] ?? '';
    echo json_encode(saveGameResult([
        'game_type'        => 'czech',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? 'cestina', 0, 100),
        'answers'          => parseAnswerPayload($_POST['answers'] ?? null),
        'topic'            => $catLabel !== '' ? $saveCat : '',
        'topic_label'      => $catLabel,
    ]));
    exit;
}

$grade      = getUserGrade();
$categories = czechCategoriesForGrade($grade);
if (!$categories) $categories = czechCategories(); // pojistka pro nezvyklý ročník

$cat = $_GET['cat'] ?? array_key_first($categories);
if (!isset($categories[$cat])) $cat = array_key_first($categories);

// Vyber 12 úloh vyváženě: kdyby se losovalo čistě náhodně, mohla by vyjít
// samá slova s ypsilonem a dítě by prošlo strategií „mačkej pořád y".
// Proto se ze skupin odpovědí bere střídavě.
$want   = 12;
$all    = $categories[$cat]['items'];
$groups = [];
foreach ($all as $it) $groups[czechAnswerGroup($it['correct'], $cat)][] = $it;
foreach ($groups as &$g) shuffle($g);
unset($g);

$items = [];
while (count($items) < $want) {
    $took = false;
    foreach ($groups as &$g) {
        if (!$g || count($items) >= $want) continue;
        $items[] = array_shift($g);
        $took = true;
    }
    unset($g);
    if (!$took) break; // sada už nemá další úlohy
}

// Adaptivní opakování: co dítě naposled splétlo, dostane přednost. Vyměníme
// to za úlohu ze stejné skupiny odpovědí, aby kolo zůstalo vyvážené.
$practiceKeys = practiceKeys((int)($_SESSION['user_id'] ?? 0), 'czech', $cat, 4);
if ($practiceKeys) {
    $byText = array_column($all, null, 'text');
    $have   = array_column($items, 'text');
    foreach ($practiceKeys as $key) {
        if (!isset($byText[$key]) || in_array($key, $have, true)) continue;
        $group = czechAnswerGroup($byText[$key]['correct'], $cat);
        foreach ($items as $i => $cur) {
            if (czechAnswerGroup($cur['correct'], $cat) !== $group) continue;
            if (in_array($cur['text'], $practiceKeys, true)) continue;
            $items[$i] = $byText[$key];
            $have[$i]  = $key;
            break;
        }
    }
}

// Počítáme, kolik úloh z chybovníku v kole nakonec je — část se jich do
// malých sad dostane sama od sebe, i bez výměny
$practiceCount = count(array_intersect(array_column($items, 'text'), $practiceKeys));

shuffle($items);
$tasks = array_map(fn($it) => [
    'key'     => $it['text'],
    'text'    => $it['text'],
    'correct' => $it['correct'],
    'hint'    => $it['hint'],
    'options' => czechOptions($it['correct'], $cat),
], $items);

$pageTitle = 'Čeština';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>✍️ <span class="accent">Čeština</span></h1>
    <p class="page-subtitle">Doplň, co do věty patří</p>
</div>

<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Sada:</span>
        <?php foreach ($categories as $key => $c): ?>
        <a href="?cat=<?= urlencode($key) ?>" class="filter-btn <?= $key === $cat ? 'active' : '' ?>">
            <?= htmlspecialchars($c['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($practiceCount > 0): ?>
<div class="lesson-hint lesson-hint-practice" style="margin-bottom:1rem">
    🔁 <?= practiceNote($practiceCount) ?>
</div>
<?php endif; ?>

<?php if ($grade > 0): ?>
<div class="lesson-hint" style="margin-bottom:1.25rem">
    💡 Sady odpovídají <strong><?= $grade ?>. třídě</strong>. Změnit ročník může rodič v nastavení účtu.
</div>
<?php endif; ?>

<div class="game-container mc-container" id="gameContainer">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="statRemain"><?= count($tasks) ?></span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="statTime">0</span><span class="gstat-label">sekund</span></div>
    </div>

    <div id="startWrapper" style="text-align:center;padding:2.5rem 0">
        <p style="color:var(--muted);margin-bottom:1.25rem">
            Doplň do slova nebo věty správný tvar.<br>
            Po každé odpovědi uvidíš, proč to tak je.
        </p>
        <button id="startBtn" class="btn-primary" style="font-size:1.1rem;padding:.85rem 2.5rem">Začít ▶</button>
    </div>

    <div id="taskWrapper" style="display:none">
        <div class="mc-progress-dots" id="czDots"></div>
        <div class="cz-task" id="czTask"></div>
        <div class="cz-choices" id="czChoices"></div>
        <div class="cz-feedback" id="czFeedback"></div>
    </div>

    <div class="progress-bar-wrapper" style="margin-top:1.25rem">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>✍️ Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="czMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="?cat=<?= urlencode($cat) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const CZ_TASKS = <?= json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE) ?>;
const CZ_SET   = <?= json_encode($categories[$cat]['label'], JSON_UNESCAPED_UNICODE) ?>;
const CZ_CAT   = <?= json_encode($cat) ?>;
const SAVE_URL = '<?= BASE_URL ?>/games/czech.php';
</script>
<script src="<?= asset_url('/js/czech_game.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
