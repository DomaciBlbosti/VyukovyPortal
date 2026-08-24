<?php
/**
 * Admin — přehled dětí pro rodiče.
 *
 * Statistiky pro dítě měří rychlost; tenhle přehled má odpovědět na jinou
 * otázku: hraje se pravidelně a v čem dělá dítě pořád stejné chyby.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/levels.php';
require_once __DIR__ . '/../includes/daily.php';
require_once __DIR__ . '/../includes/mistakes_view.php';

$db    = getDB();
$since = (new DateTimeImmutable('-6 days'))->format('Y-m-d 00:00:00');

$users = $db->query('SELECT id, display_name, username, grade, is_admin
                     FROM users WHERE is_active = 1 ORDER BY is_admin ASC, display_name ASC')->fetchAll();

// Aktivita za posledních 7 dní — jedním dotazem pro všechny
$week = [];
$stmt = $db->prepare('SELECT user_id, COUNT(*) AS games, COALESCE(SUM(points),0) AS points,
                             ROUND(AVG(accuracy),1) AS accuracy, MAX(played_at) AS last_played
                      FROM game_sessions WHERE played_at >= ? GROUP BY user_id');
$stmt->execute([$since]);
foreach ($stmt->fetchAll() as $r) $week[(int)$r['user_id']] = $r;

$only = (int)($_GET['u'] ?? 0);

$pageTitle = 'Přehled dětí';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
        <h1>👨‍👩‍👧 <span class="accent">Přehled</span> dětí</h1>
        <p class="page-subtitle">Jak se hraje a v čem se chybuje — posledních 7 dní</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn-secondary">👥 Uživatelé</a>
        <a href="<?= BASE_URL ?>/admin/levels.php" class="btn-secondary">🏆 Levely &amp; body</a>
    </div>
</div>

<?php foreach ($users as $u):
    $uid = (int)$u['id'];
    if ($only && $only !== $uid) continue;

    $w        = $week[$uid] ?? null;
    $mistakes = mistakeOverview($uid, 5);
    // Rodičovský účet, na kterém se nehraje, do přehledu dětí nepatří
    if ($u['is_admin'] && !$w && !$mistakes) continue;

    $lvl   = getUserLevel($uid);
    $daily = dailyStats($uid);
?>
<section class="admin-card" style="margin-bottom:2rem">
    <div class="mistake-group-head" style="margin-bottom:1rem">
        <span class="mistake-group-title" style="font-size:1.1rem">
            <?= $lvl['icon'] ?> <?= htmlspecialchars($u['display_name'] ?: $u['username']) ?>
            <?php if ((int)$u['grade'] > 0): ?>
            <span class="mistake-group-count"><?= (int)$u['grade'] ?>. třída</span>
            <?php endif; ?>
        </span>
        <span class="mistake-group-count">
            Level <?= $lvl['level'] ?> · <?= number_format($lvl['points'], 0, ',', ' ') ?> bodů
        </span>
    </div>

    <div class="quick-stats" style="margin-bottom:1.25rem">
        <div class="stat-card">
            <div class="stat-value"><?= $w ? (int)$w['games'] : 0 ?></div>
            <div class="stat-label">kol za 7 dní</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $w && $w['accuracy'] !== null ? $w['accuracy'] . '%' : '–' ?></div>
            <div class="stat-label">průměrná přesnost</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $daily['streak'] ?: '–' ?></div>
            <div class="stat-label">dní v řadě</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $daily['today'] ?>/<?= $daily['goal'] ?></div>
            <div class="stat-label">dnešní úkol</div>
        </div>
    </div>

    <?php if (!$w): ?>
    <p style="color:var(--muted);font-size:.875rem">
        Za posledních 7 dní nic neodehráno.
    </p>
    <?php else: ?>
    <p style="color:var(--muted);font-size:.8rem;margin-bottom:1rem">
        Naposledy hráno <?= date('j. n. H:i', strtotime($w['last_played'])) ?>
        · <?= (int)$w['points'] ?> bodů za týden
    </p>
    <?php endif; ?>

    <?php if ($mistakes): ?>
    <h2 class="section-title" style="font-size:.95rem;margin-bottom:.75rem">🔁 V čem se chybuje</h2>
    <?php renderMistakeGroups($mistakes); ?>
    <?php elseif ($w): ?>
    <p style="color:var(--muted);font-size:.875rem">Žádné otevřené chyby — všechno zvládnuté 👍</p>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if (!$users): ?>
<div class="empty-state"><p>Zatím tu nejsou žádné účty.</p></div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
