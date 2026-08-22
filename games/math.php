<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => saveGameSession([
        'game_type'        => 'math',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => 'matematika',
    ])]);
    exit;
}

$level = isset($_GET['level']) ? intval($_GET['level']) : 1;
$level = max(1, min(3, $level));

// Režim: 'input' = psaní výsledku, 'choice' = výběr ze 6 možností
$mode = ($_GET['mode'] ?? 'input') === 'choice' ? 'choice' : 'input';

// 6 možností: správný výsledek + 5 věrohodných chybných (blízké hodnoty)
function makeChoices(int $answer): array {
    $set = [$answer => true];
    $near = [$answer + 1, $answer - 1, $answer + 2, $answer - 2, $answer + 10,
             $answer - 10, $answer + 5, $answer - 5, $answer + 3, $answer - 3];
    shuffle($near);
    foreach ($near as $c) {
        if (count($set) >= 6) break;
        if ($c >= 0 && !isset($set[$c])) $set[$c] = true;
    }
    while (count($set) < 6) {
        $c = $answer + rand(-20, 20);
        if ($c >= 0 && !isset($set[$c])) $set[$c] = true;
    }
    $choices = array_map('strval', array_keys($set));
    shuffle($choices);
    return $choices;
}

// Generuj příklady na serveru (JS si je vezme)
function generateExamples(int $level, int $count = 20): array {
    $examples = [];
    for ($i = 0; $i < $count; $i++) {
        switch ($level) {
            case 1: // Sčítání a odčítání do 100
                $a  = rand(1, 50);
                $b  = rand(1, 50);
                $op = rand(0,1) ? '+' : '-';
                if ($op === '-' && $b > $a) [$a, $b] = [$b, $a];
                $result = $op === '+' ? $a + $b : $a - $b;
                $examples[] = ['q' => "$a $op $b =", 'a' => (string)$result];
                break;
            case 2: // Násobení a dělení
                $a = rand(2, 12);
                $b = rand(2, 12);
                $op = rand(0,1) ? '×' : '÷';
                if ($op === '÷') {
                    $result = $a;
                    $a = $a * $b;
                    $examples[] = ['q' => "$a $op $b =", 'a' => (string)$result];
                } else {
                    $result = $a * $b;
                    $examples[] = ['q' => "$a $op $b =", 'a' => (string)$result];
                }
                break;
            case 3: // Smíšené s většími čísly
                $ops = ['+','-','×'];
                $op  = $ops[array_rand($ops)];
                if ($op === '×') {
                    $a = rand(5, 25); $b = rand(5, 15);
                    $result = $a * $b;
                } elseif ($op === '-') {
                    $a = rand(50, 200); $b = rand(1, $a);
                    $result = $a - $b;
                } else {
                    $a = rand(50, 500); $b = rand(50, 500);
                    $result = $a + $b;
                }
                $examples[] = ['q' => "$a $op $b =", 'a' => (string)$result];
                break;
        }
    }
    return $examples;
}

$examples = generateExamples($level, 15);
if ($mode === 'choice') {
    foreach ($examples as &$ex) $ex['choices'] = makeChoices((int)$ex['a']);
    unset($ex);
}
$levelNames = [1 => 'Sčítání a odčítání', 2 => 'Násobení a dělení', 3 => 'Pokročilé příklady'];

$pageTitle = 'Matematika';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🔢 <span class="accent">Matematika</span></h1>
    <p class="page-subtitle"><?= $mode === 'choice' ? 'Vypočítej a vyber správný výsledek' : 'Vypočítej a napiš výsledek' ?></p>
</div>

<div class="mode-tabs">
    <a href="?mode=input&level=<?= $level ?>"  class="mode-tab <?= $mode==='input' ?'active':'' ?>">⌨ Psaní výsledku</a>
    <a href="?mode=choice&level=<?= $level ?>" class="mode-tab <?= $mode==='choice'?'active':'' ?>">🎯 Výběr z možností</a>
</div>

<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Obtížnost:</span>
        <?php foreach ($levelNames as $l => $name): ?>
        <a href="?mode=<?= $mode ?>&level=<?= $l ?>" class="filter-btn <?= $level === $l ? 'active' : '' ?>"><?= $name ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="game-container" id="gameContainer" style="max-width:600px">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="statRemain">15</span><span class="gstat-label">zbývá</span></div>
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
        <input type="text" inputmode="numeric" pattern="[0-9]*" id="mathInput" class="typing-input"
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
    <h2>🔢 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div class="results-actions">
        <a href="?mode=<?= $mode ?>&level=<?= $level ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const MATH_EXAMPLES = <?= json_encode($examples) ?>;
const SAVE_URL      = '<?= BASE_URL ?>/games/math.php';
</script>
<?php if ($mode === 'input'): ?>
<script src="<?= BASE_URL ?>/js/math_game.js"></script>
<?php else: ?>
<script src="<?= BASE_URL ?>/js/math_choice.js"></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
