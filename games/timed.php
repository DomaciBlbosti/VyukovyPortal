<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => saveGameSession([
        'game_type'        => 'timed',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? '', 0, 500),
    ])]);
    exit;
}

// Wordlist — česká slova
$words = [
    'jak','dal','rok','den','čas','pak','jen','tam','tak','ale','pro','při','pod','nad','bez',
    'město','dítě','škola','práce','rodina','příroda','světlo','noc','den','voda','oheň',
    'strom','louka','řeka','hora','moře','sníh','déšť','vítr','slunce','měsíc','hvězda',
    'pes','kočka','pták','ryba','kůň','medvěd','vlk','liška','jelen','zajíc',
    'chleba','mléko','máslo','vejce','maso','polévka','salát','ovoce','zelenina',
    'auto','vlak','letadlo','loď','kolo','most','cesta','ulice','dům','okno','dveře',
    'kniha','pero','papír','stůl','židle','lampa','hodiny','telefon','počítač',
    'dobrý','nový','starý','velký','malý','rychlý','pomalý','krásný','těžký','lehký',
    'Praha','Brno','Ostrava','Plzeň','Liberec','Olomouc','Hradec','Pardubice','Zlín',
    'psát','číst','mluvit','běžet','skočit','plavat','jíst','spát','pracovat','učit',
    'jeden','dva','tři','čtyři','pět','šest','sedm','osm','devět','deset',
    'leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen',
    'pondělí','úterý','středa','čtvrtek','pátek','sobota','neděle',
    'přesnost','rychlost','cvičení','klávesnice','prst','ruka','rytmus','paměť',
];

$duration = isset($_GET['time']) ? intval($_GET['time']) : 60;
$duration = in_array($duration, [30, 60, 120]) ? $duration : 60;

// Vygeneruj dostatek slov předem (JS bude brát postupně)
shuffle($words);
$wordPool = array_merge($words, $words, $words); // trojnásobek pro jistotu

$pageTitle = 'Časový závod';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>⏱ Časový <span class="accent">závod</span></h1>
    <p class="page-subtitle">Napiš co nejvíce slov za daný čas</p>
</div>

<!-- Výběr délky -->
<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Čas:</span>
        <?php foreach ([30 => '30s', 60 => '60s', 120 => '120s'] as $t => $label): ?>
        <a href="?time=<?= $t ?>" class="filter-btn <?= $duration === $t ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="game-container" id="gameContainer">
    <div class="game-stats-bar">
        <div class="game-stat">
            <span class="gstat-value accent-big" id="statCountdown"><?= $duration ?></span>
            <span class="gstat-label">sekund zbývá</span>
        </div>
        <div class="game-stat">
            <span class="gstat-value" id="statWpm">0</span>
            <span class="gstat-label">WPM</span>
        </div>
        <div class="game-stat">
            <span class="gstat-value" id="statWords">0</span>
            <span class="gstat-label">slov</span>
        </div>
        <div class="game-stat">
            <span class="gstat-value" id="statErrors">0</span>
            <span class="gstat-label">chyb</span>
        </div>
    </div>

    <!-- Proudící slova -->
    <div class="timed-words-wrapper">
        <div class="timed-words" id="timedWords"></div>
    </div>

    <!-- Input -->
    <div class="typing-input-wrapper">
        <input type="text" id="typingInput" class="typing-input"
               placeholder="Klikni sem a začni psát slova..."
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" disabled>
        <button id="startBtn" class="btn-primary">Začít ▶</button>
        <button id="resetBtn" class="btn-secondary" style="display:none">↺ Znovu</button>
    </div>

    <div class="progress-bar-wrapper">
        <div class="progress-bar" id="progressBar" style="width:100%"></div>
    </div>
</div>

<!-- Výsledky -->
<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>⏱ Čas vypršel!</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalWpm">–</div><div class="result-label">WPM</div></div>
        <div class="result-item"><div class="result-value" id="resFinalWords">–</div><div class="result-label">Slov</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
    </div>
    <div class="results-actions">
        <a href="?time=<?= $duration ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const WORD_POOL = <?= json_encode(array_values($wordPool)) ?>;
const DURATION  = <?= $duration ?>;
const SAVE_URL  = '<?= BASE_URL ?>/games/timed.php';
</script>
<script src="<?= asset_url('/js/timed_game.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
