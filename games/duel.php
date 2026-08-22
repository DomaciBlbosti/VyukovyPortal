<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    $db   = getDB();
    $user = getCurrentUser();

    // Ulož výsledky obou hráčů
    $results = [];
    foreach (['p1','p2'] as $p) {
        $name = $_POST[$p . '_name'] ?? $p;
        // Najdi user_id podle jména
        $stmt = $db->prepare('SELECT id FROM users WHERE display_name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        $uid = $row ? $row['id'] : $user['id'];

        $stmt2 = $db->prepare('
            INSERT INTO game_sessions (user_id, game_type, wpm, accuracy, duration_seconds, chars_typed, errors, text_snippet)
            VALUES (?, "duel", ?, ?, ?, ?, ?, ?)
        ');
        $stmt2->execute([
            $uid,
            floatval($_POST[$p . '_wpm']      ?? 0),
            floatval($_POST[$p . '_accuracy']  ?? 0),
            intval($_POST[$p . '_duration']    ?? 0),
            intval($_POST[$p . '_chars']       ?? 0),
            intval($_POST[$p . '_errors']      ?? 0),
            substr($_POST['text_snippet'] ?? '', 0, 200),
        ]);
        $results[$p] = ['wpm' => $_POST[$p . '_wpm'], 'name' => $name];
    }
    echo json_encode(['ok' => true, 'results' => $results]);
    exit;
}

// Texty pro souboj
$texts = [
    'Rychlá hnědá liška přeskočila přes líného psa. Pak se otočila a skočila zpět přes plot.',
    'Procvičování psaní každý den po dobu dvaceti minut výrazně zlepší tvou rychlost a přesnost.',
    'Klávesnice je nástroj jako každý jiný. Čím více cvičíš, tím přirozenější tvoje psaní bude.',
    'Praha je hlavní město České republiky. Leží na řece Vltavě a má přes jeden milion obyvatel.',
    'Správné držení těla při psaní je stejně důležité jako správná technika psaní na klávesnici.',
];

$text = $texts[array_rand($texts)];

// Načti uživatele pro výběr jmen
$db    = getDB();
$users = $db->query('SELECT display_name FROM users ORDER BY display_name')->fetchAll();

$pageTitle = 'Souboj hráčů';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>⚔️ Souboj <span class="accent">hráčů</span></h1>
    <p class="page-subtitle">Kdo zvládne text rychleji a přesněji?</p>
</div>

<!-- Výběr hráčů -->
<div class="duel-setup" id="duelSetup">
    <div class="duel-players">
        <div class="duel-player-select">
            <label>Hráč 1</label>
            <select id="p1name" class="duel-select">
                <?php foreach ($users as $u): ?>
                <option><?= htmlspecialchars($u['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="duel-vs">VS</div>
        <div class="duel-player-select">
            <label>Hráč 2</label>
            <select id="p2name" class="duel-select">
                <?php foreach (array_reverse($users) as $u): ?>
                <option><?= htmlspecialchars($u['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button id="startDuelBtn" class="btn-primary" style="margin-top:1.5rem">Začít souboj ⚔️</button>
</div>

<!-- Hra hráče 1 -->
<div class="game-container" id="p1Container" style="display:none">
    <div class="duel-player-header p1-header">
        <span id="p1Label">Hráč 1</span> — tvůj čas!
    </div>
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="p1Wpm">0</span><span class="gstat-label">WPM</span></div>
        <div class="game-stat"><span class="gstat-value" id="p1Accuracy">100</span><span class="gstat-label">% přesnost</span></div>
        <div class="game-stat"><span class="gstat-value" id="p1Time">0</span><span class="gstat-label">sekund</span></div>
        <div class="game-stat"><span class="gstat-value" id="p1Errors">0</span><span class="gstat-label">chyb</span></div>
    </div>
    <div class="typing-text-wrapper">
        <div class="typing-text" id="p1Text"></div>
    </div>
    <div class="typing-input-wrapper">
        <input type="text" id="p1Input" class="typing-input"
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" disabled
               placeholder="Klikni a začni psát...">
        <button id="p1StartBtn" class="btn-primary">Začít ▶</button>
    </div>
    <div class="progress-bar-wrapper"><div class="progress-bar" id="p1Progress" style="width:0%"></div></div>
</div>

<!-- Mezivýsledek -->
<div class="duel-intermission" id="duelIntermission" style="display:none">
    <div class="duel-inter-content">
        <h2>✅ <span id="p1DoneName">Hráč 1</span> hotov!</h2>
        <p>WPM: <strong id="p1DoneWpm">–</strong> | Přesnost: <strong id="p1DoneAcc">–</strong></p>
        <p class="inter-hint">Předej klávesnici hráči 2.</p>
        <button id="startP2Btn" class="btn-primary">Hráč 2 je připraven ▶</button>
    </div>
</div>

<!-- Hra hráče 2 -->
<div class="game-container" id="p2Container" style="display:none">
    <div class="duel-player-header p2-header">
        <span id="p2Label">Hráč 2</span> — tvůj čas!
    </div>
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="p2Wpm">0</span><span class="gstat-label">WPM</span></div>
        <div class="game-stat"><span class="gstat-value" id="p2Accuracy">100</span><span class="gstat-label">% přesnost</span></div>
        <div class="game-stat"><span class="gstat-value" id="p2Time">0</span><span class="gstat-label">sekund</span></div>
        <div class="game-stat"><span class="gstat-value" id="p2Errors">0</span><span class="gstat-label">chyb</span></div>
    </div>
    <div class="typing-text-wrapper">
        <div class="typing-text" id="p2Text"></div>
    </div>
    <div class="typing-input-wrapper">
        <input type="text" id="p2Input" class="typing-input"
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" disabled
               placeholder="Klikni a začni psát...">
        <button id="p2StartBtn" class="btn-primary">Začít ▶</button>
    </div>
    <div class="progress-bar-wrapper"><div class="progress-bar" id="p2Progress" style="width:0%"></div></div>
</div>

<!-- Výsledky souboje -->
<div class="results-panel" id="duelResults" style="display:none">
    <h2 id="duelWinnerTitle">🏆 Výsledek souboje</h2>
    <div class="duel-final-grid">
        <div class="duel-final-card" id="p1ResultCard">
            <div class="dfcard-name" id="p1ResultName">Hráč 1</div>
            <div class="dfcard-wpm" id="p1ResultWpm">–</div>
            <div class="dfcard-label">WPM</div>
            <div class="dfcard-acc" id="p1ResultAcc">–</div>
        </div>
        <div class="duel-final-card" id="p2ResultCard">
            <div class="dfcard-name" id="p2ResultName">Hráč 2</div>
            <div class="dfcard-wpm" id="p2ResultWpm">–</div>
            <div class="dfcard-label">WPM</div>
            <div class="dfcard-acc" id="p2ResultAcc">–</div>
        </div>
    </div>
    <div class="results-actions">
        <a href="<?= BASE_URL ?>/games/duel.php" class="btn-primary">↺ Nový souboj</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const DUEL_TEXT = <?= json_encode($text) ?>;
const SAVE_URL  = '<?= BASE_URL ?>/games/duel.php';
</script>
<script src="<?= BASE_URL ?>/js/duel_game.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
