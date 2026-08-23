<?php
/**
 * Přehled odznaků — získané i ty, které na hráče teprve čekají.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/achievements.php';
require_once __DIR__ . '/includes/levels.php';

$user   = getCurrentUser();
$defs   = achievementDefs();
$earned = earnedAchievements((int)$user['id']);
$stats  = achievementStats((int)$user['id']);
$lvl    = getUserLevel((int)$user['id']);

$pageTitle = 'Odznaky';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🏅 Moje <span class="accent">odznaky</span></h1>
    <p class="page-subtitle">Získáno <?= count($earned) ?> z <?= count($defs) ?></p>
</div>

<section class="quick-stats" style="margin-bottom:2rem">
    <div class="stat-card"><div class="stat-value"><?= $lvl['icon'] ?> <?= $lvl['level'] ?></div><div class="stat-label"><?= htmlspecialchars($lvl['title']) ?></div></div>
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['points'], 0, ',', ' ') ?></div><div class="stat-label">bodů celkem</div></div>
    <div class="stat-card"><div class="stat-value"><?= $stats['games'] ?></div><div class="stat-label">odehraných her</div></div>
    <div class="stat-card"><div class="stat-value"><?= $stats['streak'] ?></div><div class="stat-label">dní v řadě</div></div>
</section>

<div class="ach-grid">
    <?php foreach ($defs as $key => $def):
        $has = isset($earned[$key]); ?>
    <div class="ach-card <?= $has ? 'ach-card-earned' : '' ?>">
        <div class="ach-card-icon"><?= $has ? $def['icon'] : '🔒' ?></div>
        <div class="ach-card-body">
            <div class="ach-card-title"><?= htmlspecialchars($def['title']) ?></div>
            <div class="ach-card-desc"><?= htmlspecialchars($def['desc']) ?></div>
            <?php if ($has): ?>
            <div class="ach-card-date">získáno <?= date('j. n. Y', strtotime($earned[$key])) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
