<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => saveGameSession([
        'game_type' => 'blind',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($_POST['text_snippet'] ?? '', 0, 500),
    ])]);
    exit;
}

$texts = [
    'Rychlá hnědá liška přeskočila přes líného psa.',
    'Procvičování psaní každý den výrazně zlepší tvou rychlost.',
    'Klávesnice je nástroj jako každý jiný — čím více cvičíš, tím lépe.',
    'Správné držení těla při psaní je stejně důležité jako technika.',
    'Praha je krásné město plné historie a kultury.',
    'Soustřeď se na rytmus a přesnost přijde sama.',
];

$text = $texts[array_rand($texts)];
$pageTitle = 'Slepý režim';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🙈 Slepý <span class="accent">režim</span></h1>
    <p class="page-subtitle">Žádná zpětná vazba — výsledek uvidíš až na konci</p>
</div>

<div class="lesson-hint" style="max-width:700px;margin-bottom:1.5rem">
    💡 Nevracíme prsty na správnou klávesu — to by tě rozptylovalo. Věř svým rukám a piš plynule. Chyby uvidíš až po dokončení.
</div>

<div class="game-container" id="gameContainer">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statTime">0</span><span class="gstat-label">sekund</span></div>
        <div class="game-stat"><span class="gstat-value" id="statProgress">0</span><span class="gstat-label">% hotovo</span></div>
    </div>

    <div class="typing-text-wrapper">
        <!-- Slepý režim: text vidíš, ale co píšeš ne -->
        <div class="typing-text" id="typingText">
            <?php foreach (mb_str_split($text) as $ch): ?>
                <span class="tchar"><?= $ch === ' ' ? '&nbsp;' : htmlspecialchars($ch) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="typing-input-wrapper">
        <!-- Input je vizuálně skrytý — píšeš, ale nevidíš co -->
        <div class="blind-input-wrapper">
            <input type="text" id="typingInput" class="typing-input blind-input"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" disabled
                   placeholder="Klikni sem — text se nezobrazí...">
        </div>
        <button id="startBtn" class="btn-primary">Začít ▶</button>
        <button id="resetBtn" class="btn-secondary" style="display:none">↺ Znovu</button>
    </div>

    <div class="progress-bar-wrapper">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>🎉 Výsledek</h2>

    <!-- Odhalení chyb -->
    <div class="blind-reveal" id="blindReveal"></div>

    <div class="results-stats" style="margin-top:1.5rem">
        <div class="result-item"><div class="result-value" id="resFinalWpm">–</div><div class="result-label">WPM</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
    </div>
    <div class="results-actions">
        <a href="<?= BASE_URL ?>/games/blind.php" class="btn-primary">↺ Nový text</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const GAME_TEXT = <?= json_encode($text) ?>;
const SAVE_URL  = '<?= BASE_URL ?>/games/blind.php';
</script>
<script src="<?= asset_url('/js/blind_game.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
