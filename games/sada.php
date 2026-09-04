<?php
/**
 * Hraní vlastní sady — obsahu, který přišel z učebnice, ne z kódu.
 *
 * Jedna hra pro všechny čtyři formáty: uvnitř jsou z nich stejné úlohy
 * (zadání, správná odpověď, možnosti), liší se jen tím, co se ukáže nahoře.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/sets.php';
require_once __DIR__ . '/../includes/mistakes.php';

$setId   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$reverse = ($_GET['dir'] ?? $_POST['dir'] ?? '') === 'ba';
$set     = getSet($setId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    if (!$set) { echo json_encode(['ok' => false]); exit; }

    // Sada je jedna, takže se nic netřídí — jen ověříme, že klíče opravdu
    // patří téhle sadě; prohlížeči v tom nevěříme.
    $valid = [];
    foreach (setItems($setId) as $it) {
        $valid[$it['item_key']]        = true;
        $valid[$it['item_key'] . ':r'] = true;
    }
    $items = array_values(array_filter(
        parseAnswerPayload($_POST['answers'] ?? null),
        fn($a) => isset($valid[$a['key']])
    ));

    echo json_encode(saveGameResult([
        'game_type'        => 'sada',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => substr($set['title'], 0, 100),
        'topic'            => (string)$setId,
        'topic_label'      => $set['title'],
        'answers'          => $items,
    ]));
    exit;
}

if (!$set) {
    $pageTitle = 'Sada nenalezena';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">📚</div><p>Tahle sada už neexistuje. '
       . '<a href="' . BASE_URL . '/dashboard.php">Zpátky na rozcestník</a></p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$items = setItems($setId);
$tasks = buildSetRound(
    $items,
    $set['kind'],
    practiceKeys((int)($_SESSION['user_id'] ?? 0), 'sada', (string)$setId, 4),
    $reverse
);

// Kolik úloh z chybovníku v kole nakonec je — u malých sad se jich část
// dostane dovnitř sama, i bez záměrného přednostního zařazení
$practiceCount = count(array_intersect(
    array_column($tasks, 'key'),
    practiceKeys((int)($_SESSION['user_id'] ?? 0), 'sada', (string)$setId, 99)
));

$canReverse = $set['kind'] === 'dvojice';

$pageTitle = $set['title'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(setSubjectLabel($set['subject'])) ?> — <span class="accent"><?= htmlspecialchars($set['title']) ?></span></h1>
    <p class="page-subtitle"><?= htmlspecialchars(SET_KINDS[$set['kind']] ?? $set['kind']) ?></p>
</div>

<?php if ($canReverse): ?>
<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Směr:</span>
        <a href="?id=<?= $setId ?>" class="filter-btn <?= $reverse ? '' : 'active' ?>">zadání → odpověď</a>
        <a href="?id=<?= $setId ?>&amp;dir=ba" class="filter-btn <?= $reverse ? 'active' : '' ?>">odpověď → zadání</a>
    </div>
</div>
<?php endif; ?>

<?php if ($practiceCount > 0): ?>
<div class="lesson-hint lesson-hint-practice" style="margin-bottom:1rem">
    🔁 <?= practiceNote($practiceCount) ?>
</div>
<?php endif; ?>

<?php if ($set['kind'] === 'cteni' && $set['passage']): ?>
<div class="set-passage" id="setPassage">
    <div class="section-title" style="margin-bottom:.5rem">📖 Přečti si</div>
    <?php foreach (preg_split('/\n\s*\n/', trim($set['passage'])) as $par): ?>
    <p><?= nl2br(htmlspecialchars($par)) ?></p>
    <?php endforeach; ?>
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
            <?php $n = count($tasks); ?>
            <?= $n ?> <?= $n === 1 ? 'úloha' : ($n < 5 ? 'úlohy' : 'úloh') ?>
            ze sady <strong><?= htmlspecialchars($set['title']) ?></strong><br>
            Vyber správnou odpověď — po každé uvidíš, jak to je.
        </p>
        <button id="startBtn" class="btn-primary" style="font-size:1.1rem;padding:.85rem 2.5rem">Začít ▶</button>
    </div>

    <div id="taskWrapper" style="display:none">
        <div class="mc-progress-dots" id="setDots"></div>
        <div class="cz-task" id="setTask"></div>
        <div class="cz-choices" id="setChoices"></div>
        <div class="cz-feedback" id="setFeedback"></div>
    </div>

    <div class="progress-bar-wrapper" style="margin-top:1.25rem">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>📚 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="setMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="?id=<?= $setId ?><?= $reverse ? '&amp;dir=ba' : '' ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/sady.php" class="btn-secondary">← Další sady</a>
    </div>
    <div class="mistake-hint" style="margin-top:1.25rem">Zdroj: <?= htmlspecialchars($set['source']) ?></div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const SET_TASKS = <?= json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE) ?>;
const SET_ID    = <?= json_encode((string)$setId) ?>;
const SET_DIR   = <?= json_encode($reverse ? 'ba' : 'ab') ?>;
const SET_NAME  = <?= json_encode($set['title'], JSON_UNESCAPED_UNICODE) ?>;
const SAVE_URL  = '<?= BASE_URL ?>/games/sada.php';
</script>
<script src="<?= asset_url('/js/sada_game.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
