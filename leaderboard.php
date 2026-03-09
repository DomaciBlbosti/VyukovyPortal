<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/db.php';

$db   = getDB();
$user = getCurrentUser();

// Definice herních typů
$gameTypes = [
    'all'          => ['label' => '🏠 Vše',          'icon' => '🏠'],
    'classic'      => ['label' => '📝 Psaní',        'icon' => '📝'],
    'timed'        => ['label' => '⏱ Časovka',       'icon' => '⏱'],
    'blind'        => ['label' => '🙈 Slepý',         'icon' => '🙈'],
    'math'         => ['label' => '🔢 Matematika',   'icon' => '🔢'],
    'geography'    => ['label' => '🌍 Zeměpis',      'icon' => '🌍'],
    'geography_map'=> ['label' => '🗺 Slepé mapy',   'icon' => '🗺'],
];

$period   = $_GET['period']   ?? 'all';
$gameType = $_GET['type']     ?? 'all';
if (!isset($gameTypes[$gameType])) $gameType = 'all';

$periodSQL = match($period) {
    'week'  => "AND gs.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND gs.played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => "",
};
$typeSQL = $gameType !== 'all'
    ? "AND gs.game_type = " . $db->quote($gameType)
    : "";

// Top WPM
$topWpm = $db->query("
    SELECT u.display_name, t.best_wpm,
           ROUND(AVG(gs.wpm),1)      AS avg_wpm,
           ROUND(AVG(gs.accuracy),1) AS avg_accuracy,
           COUNT(*)                  AS games,
           t.best_type
    FROM game_sessions gs
    JOIN users u ON u.id = gs.user_id
    JOIN (
        SELECT gs2.user_id,
               MAX(gs2.wpm) AS best_wpm,
               SUBSTRING_INDEX(GROUP_CONCAT(gs2.game_type ORDER BY gs2.wpm DESC), ',', 1) AS best_type
        FROM game_sessions gs2
        WHERE 1=1 $typeSQL $periodSQL
        GROUP BY gs2.user_id
    ) t ON t.user_id = gs.user_id
    WHERE 1=1 $typeSQL $periodSQL
    GROUP BY gs.user_id, u.display_name, t.best_wpm, t.best_type
    ORDER BY best_wpm DESC
    LIMIT 20
")->fetchAll();

// Posledních 10 výsledků
$recentGames = $db->query("
    SELECT u.display_name, gs.wpm, gs.accuracy, gs.played_at, gs.game_type
    FROM game_sessions gs
    JOIN users u ON u.id = gs.user_id
    WHERE 1=1 $typeSQL $periodSQL
    ORDER BY gs.played_at DESC
    LIMIT 10
")->fetchAll();

// Statistiky per typ hry (pro přehledová čísla nahoře)
$typeStats = $db->query("
    SELECT gs.game_type,
           COUNT(*) AS total,
           ROUND(MAX(gs.wpm),1) AS best_wpm,
           ROUND(AVG(gs.wpm),1) AS avg_wpm
    FROM game_sessions gs
    WHERE 1=1 $periodSQL
    GROUP BY gs.game_type
    ORDER BY total DESC
")->fetchAll();

$gameTypeLabels = [
    'classic'       => '📝 Psaní',
    'timed'         => '⏱ Časovka',
    'blind'         => '🙈 Slepý',
    'math'          => '🔢 Matematika',
    'geography'     => '🌍 Zeměpis — otázky',
    'geography_map' => '🗺 Slepé mapy',
    'duel'          => '⚔️ Souboj',
];

$pageTitle = 'Žebříček';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🏆 <span class="accent">Žebříček</span></h1>
</div>

<!-- Filtry — předmět -->
<div class="lb-filters">
    <div class="filter-row">
        <span class="filter-label">Předmět:</span>
        <div class="filter-pills">
            <?php foreach ($gameTypes as $k => $g): ?>
            <a href="?type=<?= $k ?>&period=<?= $period ?>"
               class="filter-btn <?= $gameType === $k ? 'active' : '' ?>">
                <?= $g['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="filter-row">
        <span class="filter-label">Období:</span>
        <div class="filter-pills">
            <?php foreach (['all' => 'Vše', 'month' => 'Měsíc', 'week' => 'Týden'] as $k => $label): ?>
            <a href="?type=<?= $gameType ?>&period=<?= $k ?>"
               class="filter-btn <?= $period === $k ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Přehled her (jen pro "Vše") -->
<?php if ($gameType === 'all' && !empty($typeStats)): ?>
<section class="lb-overview">
    <h2 class="section-title">Přehled podle předmětu</h2>
    <div class="lb-type-grid">
        <?php foreach ($typeStats as $ts): ?>
        <a href="?type=<?= urlencode($ts['game_type']) ?>&period=<?= $period ?>" class="lb-type-card">
            <div class="lb-type-icon"><?= $gameTypeLabels[$ts['game_type']] ?? $ts['game_type'] ?></div>
            <div class="lb-type-stats">
                <span class="lb-type-best"><?= $ts['best_wpm'] ?> WPM max</span>
                <span class="lb-type-count"><?= $ts['total'] ?> her</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="leaderboard-grid">
    <!-- Top WPM -->
    <section>
        <h2 class="section-title">
            <?= $gameTypes[$gameType]['icon'] ?>
            Nejlepší WPM
            <?= $gameType !== 'all' ? '— ' . $gameTypes[$gameType]['label'] : '' ?>
        </h2>
        <?php if (empty($topWpm)): ?>
            <div class="empty-state"><p>Žádné výsledky.</p></div>
        <?php else: ?>
        <table class="data-table leaderboard-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Hráč</th>
                    <th>Nejlepší WPM</th>
                    <th>Průměr</th>
                    <th>Přesnost</th>
                    <th>Her</th>
                    <?php if ($gameType === 'all'): ?><th>Předmět</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topWpm as $i => $row): ?>
                <tr class="<?= $row['display_name'] === $user['display_name'] ? 'highlight-row' : '' ?>">
                    <td class="rank">
                        <?php if ($i === 0) echo '🥇';
                        elseif ($i === 1) echo '🥈';
                        elseif ($i === 2) echo '🥉';
                        else echo '#' . ($i + 1); ?>
                    </td>
                    <td><?= htmlspecialchars($row['display_name']) ?></td>
                    <td class="accent bold"><?= $row['best_wpm'] ?></td>
                    <td><?= $row['avg_wpm'] ?></td>
                    <td><?= $row['avg_accuracy'] ?>%</td>
                    <td><?= $row['games'] ?></td>
                    <?php if ($gameType === 'all'): ?>
                    <td style="font-size:.8rem;color:var(--muted)"><?= $gameTypeLabels[$row['best_type']] ?? $row['best_type'] ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <!-- Poslední výsledky -->
    <section>
        <h2 class="section-title">Poslední výsledky</h2>
        <?php if (empty($recentGames)): ?>
            <div class="empty-state"><p>Žádné výsledky.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Hráč</th><th>WPM</th><th>Přesnost</th><th>Předmět</th><th>Kdy</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentGames as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['display_name']) ?></td>
                    <td class="accent"><?= round($row['wpm'], 1) ?></td>
                    <td><?= round($row['accuracy'], 1) ?>%</td>
                    <td style="font-size:.8rem;color:var(--muted)"><?= $gameTypeLabels[$row['game_type']] ?? $row['game_type'] ?></td>
                    <td><?= date('d.m. H:i', strtotime($row['played_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>