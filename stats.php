<?php
// stats.php - statistiky hráče
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mistakes_view.php';

$user = getCurrentUser();
$db   = getDB();

// Celkové statistiky
$stmt = $db->prepare('
    SELECT
        COUNT(*) AS total_games,
        ROUND(MAX(wpm), 1) AS best_wpm,
        ROUND(AVG(wpm), 1) AS avg_wpm,
        ROUND(MIN(wpm), 1) AS worst_wpm,
        ROUND(AVG(accuracy), 1) AS avg_accuracy,
        ROUND(MAX(accuracy), 1) AS best_accuracy,
        SUM(chars_typed) AS total_chars,
        SUM(duration_seconds) AS total_seconds
    FROM game_sessions
    WHERE user_id = ?
');
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Posledních 20 her pro graf
$stmt = $db->prepare('
    SELECT wpm, accuracy, played_at, game_type
    FROM game_sessions
    WHERE user_id = ?
    ORDER BY played_at DESC
    LIMIT 20
');
$stmt->execute([$user['id']]);
$lastGames = array_reverse($stmt->fetchAll());

// Nejlepší výsledky per game_type
$stmt = $db->prepare('
    SELECT game_type, MAX(wpm) AS best_wpm, ROUND(AVG(wpm), 1) AS avg_wpm, COUNT(*) AS games
    FROM game_sessions
    WHERE user_id = ?
    GROUP BY game_type
');
$stmt->execute([$user['id']]);
$byType = $stmt->fetchAll();

$mistakes  = mistakeOverview((int)$user['id']);

$pageTitle = 'Moje statistiky';
include __DIR__ . '/includes/header.php';

$minutes = intdiv($stats['total_seconds'] ?? 0, 60);
$hours   = intdiv($minutes, 60);
?>

<div class="page-header">
    <h1>Moje <span class="accent">statistiky</span></h1>
    <p class="page-subtitle"><?= htmlspecialchars($user['display_name']) ?></p>
</div>

<!-- Přehledové karty -->
<section class="quick-stats">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['best_wpm'] ?? '–' ?></div>
        <div class="stat-label">nejlepší WPM</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['avg_wpm'] ?? '–' ?></div>
        <div class="stat-label">průměr WPM</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['best_accuracy'] ?? '–' ?><?= ($stats['best_accuracy'] ?? null) ? '%' : '' ?></div>
        <div class="stat-label">nejlepší přesnost</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $hours > 0 ? $hours . 'h' : $minutes . 'm' ?></div>
        <div class="stat-label">celkem odehráno</div>
    </div>
</section>

<!-- Graf vývoje -->
<?php if (!empty($lastGames)): ?>
<section class="chart-section">
    <h2 class="section-title">Vývoj WPM (posledních <?= count($lastGames) ?> her)</h2>
    <div class="chart-wrapper">
        <canvas id="wpmChart" height="120"></canvas>
    </div>
    <script>
    window.chartData = {
        labels: <?= json_encode(array_map(fn($g) => date('d.m H:i', strtotime($g['played_at'])), $lastGames)) ?>,
        wpm:    <?= json_encode(array_map(fn($g) => round($g['wpm'], 1), $lastGames)) ?>,
        acc:    <?= json_encode(array_map(fn($g) => round($g['accuracy'], 1), $lastGames)) ?>,
    };
    </script>
</section>
<?php endif; ?>

<!-- Co si zopakovat -->
<?php if ($mistakes): ?>
<section class="stats-table-section" id="chyby">
    <h2 class="section-title">🔁 Co si zopakovat</h2>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1rem">
        Úlohy, které ti naposled nešly. Hry je samy zařadí do dalších kol —
        po třech správných odpovědích za sebou ze seznamu zmizí.
    </p>
    <?php renderMistakeGroups($mistakes); ?>
</section>
<?php endif; ?>

<!-- Statistiky podle typu hry -->
<?php if (!empty($byType)): ?>
<section class="stats-table-section">
    <h2 class="section-title">Podle typu hry</h2>
    <div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Hra</th>
                <th>Her odehráno</th>
                <th>Nejlepší WPM</th>
                <th>Průměr WPM</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($byType as $row): ?>
            <tr>
                <td><?= htmlspecialchars(gameTypeLabel($row['game_type'])) ?></td>
                <td><?= $row['games'] ?></td>
                <td class="accent"><?= $row['best_wpm'] ?></td>
                <td><?= $row['avg_wpm'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php endif; ?>

<?php if (!$stats['total_games']): ?>
<div class="empty-state">
    <div class="empty-icon">⌨</div>
    <p>Zatím žádné statistiky. <a href="<?= BASE_URL ?>/dashboard.php">Zahraj první hru!</a></p>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/js/charts.js') ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
