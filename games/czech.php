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
require_once __DIR__ . '/../includes/selection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    // Hraje-li se víc sad najednou, roztřídíme odpovědi zpátky podle toho,
    // ze které sady úloha pochází — server si to dohledá sám, prohlížeči
    // v tom nevěříme.
    $allCats    = czechCategories();
    $saveCats   = parseSelection((string)($_POST['cat'] ?? ''), $allCats);
    $catOfText  = [];
    foreach ($saveCats as $c) {
        foreach ($allCats[$c]['items'] as $it) $catOfText[$it['text']] ??= $c;
    }
    $saveGroups = [];
    foreach (parseAnswerPayload($_POST['answers'] ?? null) as $item) {
        $c = $catOfText[$item['key']] ?? null;
        if ($c === null) continue;
        $saveGroups[$c] ??= ['topic' => $c, 'topic_label' => $allCats[$c]['label'], 'items' => []];
        $saveGroups[$c]['items'][] = $item;
    }
    echo json_encode(saveGameResult([
        'game_type'        => 'czech',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? 'cestina', 0, 100),
        'answer_groups'    => array_values($saveGroups),
    ]));
    exit;
}

$grade      = getUserGrade();
$categories = czechCategoriesForGrade($grade);
if (!$categories) $categories = czechCategories(); // pojistka pro nezvyklý ročník

// Vybraných sad může být víc — „vyjmenovaná po B + po L"
$cats = parseSelection($_GET['cat'] ?? null, $categories);
$cat  = $cats[0];   // pro nabídku tlačítek u sad, které ji mají vlastní

// Slij úlohy ze všech vybraných sad; každá si nese, odkud je, kvůli
// nabídce tlačítek i chybovníku. Stejné zadání ve dvou sadách bereme jednou.
$all = [];
foreach ($cats as $c) {
    foreach ($categories[$c]['items'] as $it) {
        if (isset($all[$it['text']])) continue;
        $it['_cat'] = $c;
        $all[$it['text']] = $it;
    }
}
$all = array_values($all);

// Vyber 12 úloh vyváženě: kdyby se losovalo čistě náhodně, mohla by vyjít
// samá slova s ypsilonem a dítě by prošlo strategií „mačkej pořád y".
// Proto se ze skupin odpovědí bere střídavě.
$want   = 12;
$groups = [];
foreach ($all as $it) $groups[czechAnswerGroup($it['correct'], $it['_cat'])][] = $it;
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
    if (!$took) break; // sady už nemají další úlohy
}

// Adaptivní opakování: co dítě naposled splétlo, dostane přednost. Vyměníme
// to za úlohu ze stejné skupiny odpovědí, aby kolo zůstalo vyvážené.
$practiceKeys = [];
foreach ($cats as $c) {
    $practiceKeys = array_merge($practiceKeys, practiceKeys((int)($_SESSION['user_id'] ?? 0), 'czech', $c, 4));
}
shuffle($practiceKeys);
$practiceKeys = array_slice($practiceKeys, 0, 4);

if ($practiceKeys) {
    $byText = array_column($all, null, 'text');
    $have   = array_column($items, 'text');
    foreach ($practiceKeys as $key) {
        if (!isset($byText[$key]) || in_array($key, $have, true)) continue;
        $group = czechAnswerGroup($byText[$key]['correct'], $byText[$key]['_cat']);
        foreach ($items as $i => $cur) {
            if (czechAnswerGroup($cur['correct'], $cur['_cat']) !== $group) continue;
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
    'options' => czechOptions($it['correct'], $it['_cat']),
], $items);

$setLabel = selectionLabel($cats, $categories);

$pageTitle = 'Čeština';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>✍️ <span class="accent">Čeština</span></h1>
    <p class="page-subtitle">Doplň, co do věty patří</p>
</div>

<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Sady:</span>
        <?php foreach ($categories as $key => $c):
            $on = in_array((string)$key, $cats, true); ?>
        <a href="?cat=<?= urlencode(toggleSelection($cats, (string)$key, $categories)) ?>"
           class="filter-btn filter-btn-multi <?= $on ? 'active' : '' ?>">
            <?= $on ? '✓ ' : '' ?><?= $c['icon'] ?> <?= htmlspecialchars($c['label']) ?>
        </a>
        <?php endforeach; ?>
        <?php if (count($cats) > 1): ?>
        <a href="?cat=<?= urlencode($cats[0]) ?>" class="filter-btn filter-btn-reset">✕ jen jednu</a>
        <?php endif; ?>
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
        <a href="?cat=<?= urlencode(implode(',', $cats)) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const CZ_TASKS = <?= json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE) ?>;
const CZ_SET   = <?= json_encode($setLabel, JSON_UNESCAPED_UNICODE) ?>;
const CZ_CAT   = <?= json_encode(implode(',', $cats)) ?>;
const SAVE_URL = '<?= BASE_URL ?>/games/czech.php';
</script>
<script src="<?= asset_url('/js/czech_game.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
