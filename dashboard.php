<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/db.php';

$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare('
    SELECT COUNT(*) AS total_games, ROUND(MAX(wpm),1) AS best_wpm,
           ROUND(AVG(wpm),1) AS avg_wpm, ROUND(AVG(accuracy),1) AS avg_accuracy
    FROM game_sessions WHERE user_id = ?
');
$stmt->execute([$user['id']]);
$myStats = $stmt->fetch();

$games = [
    [
        'id'          => 'classic',
        'title'       => 'Klasický režim',
        'icon'        => '📝',
        'description' => 'Přepiš zadaný text co nejrychleji. 32 lekcí seřazených od základní řady po interpunkci.',
        'url'         => BASE_URL . '/games/classic.php',
        'color'       => 'green',
        'available'   => true,
    ],
    [
        'id'          => 'timed',
        'title'       => 'Časový závod',
        'icon'        => '⏱',
        'description' => 'Máš 30, 60 nebo 120 sekund. Piš co nejrychleji — slova se generují donekonečna.',
        'url'         => BASE_URL . '/games/timed.php',
        'color'       => 'orange',
        'available'   => true,
    ],
    [
        'id'          => 'blind',
        'title'       => 'Slepý režim',
        'icon'        => '🙈',
        'description' => 'Píšeš bez jakékoli zpětné vazby. Výsledek uvidíš až po dokončení. Trénink svalové paměti.',
        'url'         => BASE_URL . '/games/blind.php',
        'color'       => 'purple',
        'available'   => true,
    ],
    [
        'id'          => 'math',
        'title'       => 'Matematika',
        'icon'        => '🔢',
        'description' => 'Napiš výsledek matematického příkladu. Procvičuj počty a psaní čísel zároveň.',
        'url'         => BASE_URL . '/games/math.php',
        'color'       => 'blue',
        'available'   => true,
    ],
    [
        'id'          => 'geography',
        'title'       => 'Zeměpis',
        'icon'        => '🌍',
        'description' => 'Hlavní města, státy, řeky, hory. Napiš správnou odpověď na zeměpisnou otázku.',
        'url'         => BASE_URL . '/games/geography.php',
        'color'       => 'teal',
        'available'   => true,
    ],
];

$pageTitle = 'Rozcestník';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Vítej, <span class="accent"><?= htmlspecialchars($user['display_name']) ?></span>!</h1>
    <p class="page-subtitle">Co dnes nacvičíme?</p>
</div>

<section class="quick-stats">
    <div class="stat-card"><div class="stat-value"><?= $myStats['total_games'] ?? 0 ?></div><div class="stat-label">odehraných her</div></div>
    <div class="stat-card"><div class="stat-value"><?= $myStats['best_wpm'] ?? '–' ?></div><div class="stat-label">nejlepší WPM</div></div>
    <div class="stat-card"><div class="stat-value"><?= $myStats['avg_wpm'] ?? '–' ?></div><div class="stat-label">průměrné WPM</div></div>
    <div class="stat-card"><div class="stat-value"><?= ($myStats['avg_accuracy'] ?? null) ? $myStats['avg_accuracy'] . '%' : '–' ?></div><div class="stat-label">průměrná přesnost</div></div>
</section>

<section class="games-section">
    <h2 class="section-title">Vyber hru</h2>
    <div class="games-grid">
        <?php foreach ($games as $game): ?>
        <div class="game-card <?= $game['available'] ? '' : 'game-card--soon' ?> game-card--<?= $game['color'] ?>">
            <div class="game-icon"><?= $game['icon'] ?></div>
            <h3 class="game-title"><?= htmlspecialchars($game['title']) ?></h3>
            <p class="game-desc"><?= htmlspecialchars($game['description']) ?></p>
            <?php if ($game['available']): ?>
                <a href="<?= $game['url'] ?>" class="btn-game">Hrát →</a>
            <?php else: ?>
                <span class="badge-soon">Brzy</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
