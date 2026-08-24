<?php
/**
 * Angličtina — slovíčka. Okruhy podle ročníku, dva směry překladu
 * (česky → anglicky a zpět) a dva režimy (výběr z možností / psaní).
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/data/english.php';
require_once __DIR__ . '/../includes/mistakes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    // Chybovník vede každý směr překladu zvlášť — umět „dog → pes" ještě
    // neznamená umět „pes → dog"
    $saveTheme = (string)($_POST['theme'] ?? '');
    $saveDir   = ($_POST['dir'] ?? '') === 'en_cs' ? 'en_cs' : 'cs_en';
    $themeRow  = englishThemes()[$saveTheme] ?? null;
    $saveTopic = $themeRow ? $saveTheme . ':' . $saveDir : '';
    $saveLabel = $themeRow ? $themeRow['label'] . ' (' . ($saveDir === 'en_cs' ? 'EN→CZ' : 'CZ→EN') . ')' : '';
    echo json_encode(saveGameResult([
        'game_type'        => 'english',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? 'anglictina', 0, 100),
        'answers'          => parseAnswerPayload($_POST['answers'] ?? null),
        'topic'            => $saveTopic,
        'topic_label'      => $saveLabel,
    ]));
    exit;
}

$grade  = getUserGrade();
$themes = englishThemesForGrade($grade);
if (!$themes) $themes = englishThemes(); // pojistka pro nezvyklý ročník

$theme = $_GET['theme'] ?? array_key_first($themes);
if (!isset($themes[$theme])) $theme = array_key_first($themes);

$dir  = ($_GET['dir']  ?? 'cs_en') === 'en_cs'  ? 'en_cs'  : 'cs_en';
$mode = ($_GET['mode'] ?? 'choice') === 'input' ? 'input'  : 'choice';

$tasks    = englishTasks($theme, 12, $dir, 4);

// Adaptivní opakování: slovíčka, která dítě naposled splétlo, se do kola
// vloží přednostně místo náhodně vylosovaných.
$practiceList = practiceKeys((int)($_SESSION['user_id'] ?? 0), 'english', $theme . ':' . $dir, 4);
englishInjectPractice($tasks, $practiceList, $theme, $dir);
// Část slovíček z chybovníku se do kola dostane sama, i bez výměny
$practiceCount = count(array_intersect(array_column($tasks, 'key'), $practiceList));
$setLabel = $themes[$theme]['label'] . ' (' . ($dir === 'en_cs' ? 'EN→CZ' : 'CZ→EN') . ')';

$pageTitle = 'Angličtina';
include __DIR__ . '/../includes/header.php';

/** Odkaz se zachováním ostatních parametrů */
function enUrl(array $override): string {
    $q = array_merge([
        'theme' => $_GET['theme'] ?? '',
        'dir'   => $_GET['dir']   ?? 'cs_en',
        'mode'  => $_GET['mode']  ?? 'choice',
    ], $override);
    return '?' . http_build_query(array_filter($q, fn($x) => $x !== ''));
}
?>

<div class="page-header">
    <h1>🇬🇧 <span class="accent">Angličtina</span></h1>
    <p class="page-subtitle">
        <?= $dir === 'en_cs' ? 'Co znamená anglické slovíčko?' : 'Jak se řekne anglicky?' ?>
    </p>
</div>

<div class="mode-tabs">
    <a href="<?= enUrl(['dir' => 'cs_en']) ?>" class="mode-tab <?= $dir==='cs_en'?'active':'' ?>">🇨🇿 → 🇬🇧 Česky → anglicky</a>
    <a href="<?= enUrl(['dir' => 'en_cs']) ?>" class="mode-tab <?= $dir==='en_cs'?'active':'' ?>">🇬🇧 → 🇨🇿 Anglicky → česky</a>
</div>

<div class="mode-tabs">
    <a href="<?= enUrl(['mode' => 'choice']) ?>" class="mode-tab <?= $mode==='choice'?'active':'' ?>">🎯 Výběr z možností</a>
    <a href="<?= enUrl(['mode' => 'input']) ?>"  class="mode-tab <?= $mode==='input' ?'active':'' ?>">⌨ Psaní slovíčka</a>
</div>

<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Okruh:</span>
        <?php foreach ($themes as $key => $t): ?>
        <a href="<?= enUrl(['theme' => $key]) ?>" class="filter-btn <?= $key === $theme ? 'active' : '' ?>">
            <?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Nepravidelná slovesa mají jiné zadání než ostatní okruhy — v závorce je
// vždycky základní tvar, aby dítě vědělo, které sloveso se po něm chce.
$taskNote = $theme !== 'nepravidelna' ? '' : ($dir === 'en_cs'
    ? 'Co znamená sloveso v minulém čase? V závorce u odpovědi je základní tvar.'
    : 'Napiš tvar minulého času slovesa v závorce.');
?>
<?php if ($taskNote): ?>
<div class="lesson-hint" style="margin-bottom:1rem">📌 <?= $taskNote ?></div>
<?php endif; ?>

<?php if ($practiceCount > 0): ?>
<div class="lesson-hint lesson-hint-practice" style="margin-bottom:1rem">
    🔁 <?= practiceNote($practiceCount, 'word') ?>
</div>
<?php endif; ?>

<?php if ($grade > 0): ?>
<div class="lesson-hint" style="margin-bottom:1.25rem">
    💡 Okruhy odpovídají <strong><?= $grade ?>. třídě</strong>. Změnit ročník může rodič v nastavení účtu.
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
            <?= $mode === 'input'
                ? 'Napiš překlad slovíčka. Na diakritice nezáleží.'
                : 'Vyber správný překlad ze čtyř možností.' ?><br>
            Po každé odpovědi uvidíš celou dvojici slovíček.
        </p>
        <button id="startBtn" class="btn-primary" style="font-size:1.1rem;padding:.85rem 2.5rem">Začít ▶</button>
    </div>

    <div id="taskWrapper" style="display:none">
        <div class="mc-progress-dots" id="enDots"></div>
        <div class="en-prompt"><?= $dir === 'en_cs' ? 'anglicky' : 'česky' ?></div>
        <div class="en-task" id="enTask"></div>

        <?php if ($mode === 'input'): ?>
        <div class="typing-input-wrapper">
            <input type="text" id="enInput" class="typing-input en-input"
                   placeholder="<?= $dir === 'en_cs' ? 'napiš česky…' : 'napiš anglicky…' ?>"
                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                   lang="<?= $dir === 'en_cs' ? 'cs' : 'en' ?>">
            <button id="submitBtn" class="btn-primary">✓ OK</button>
        </div>
        <?php else: ?>
        <div class="en-choices" id="enChoices"></div>
        <?php endif; ?>

        <div class="cz-feedback" id="enFeedback"></div>
    </div>

    <div class="progress-bar-wrapper" style="margin-top:1.25rem">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>🇬🇧 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="enMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="<?= enUrl([]) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const EN_TASKS = <?= json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE) ?>;
const EN_SET   = <?= json_encode($setLabel, JSON_UNESCAPED_UNICODE) ?>;
const EN_MODE  = <?= json_encode($mode) ?>;
const EN_THEME = <?= json_encode($theme) ?>;
const EN_DIR   = <?= json_encode($dir) ?>;
const SAVE_URL = '<?= BASE_URL ?>/games/english.php';
</script>
<script src="<?= asset_url('/js/english_game.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
