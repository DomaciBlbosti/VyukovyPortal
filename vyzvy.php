<?php
/**
 * Výzvy zadané dítěti — co má procvičit a jak daleko je.
 * Každý nesplněný úkol má odkaz rovnou do správné sady, ať se k němu
 * dítě nemusí proklikávat přes filtry.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/challenges.php';

$user  = getCurrentUser();
$items = userChallenges((int)$user['id']);
$open  = array_values(array_filter($items, fn($c) => !$c['completed_at']));
$done  = array_values(array_filter($items, fn($c) => $c['completed_at']));

$pageTitle = 'Moje výzvy';
include __DIR__ . '/includes/header.php';

/** Vykreslí kartu jedné výzvy */
function renderChallengeCard(array $c): void { ?>
    <div class="challenge-card <?= $c['completed_at'] ? 'challenge-done' : '' ?>">
        <div class="challenge-head">
            <span class="challenge-title">
                <?= $c['completed_at'] ? '🏆' : '🎯' ?> <?= htmlspecialchars($c['title']) ?>
            </span>
            <span class="challenge-meta"><?= $c['done_steps'] ?>/<?= $c['total_steps'] ?> úkolů</span>
        </div>

        <?php if ($c['description']): ?>
        <div class="mistake-hint"><?= htmlspecialchars($c['description']) ?></div>
        <?php endif; ?>

        <div class="challenge-bar"><div class="challenge-fill" style="width:<?= $c['percent'] ?>%"></div></div>

        <?php foreach ($c['steps'] as $s): ?>
        <div class="challenge-step <?= $s['done'] ? 'challenge-step-done' : '' ?>">
            <span class="challenge-step-label">
                <?= $s['done'] ? '✔' : '▢' ?> <?= htmlspecialchars($s['label']) ?>
            </span>
            <span class="challenge-step-state">
                <?= $s['done_rounds'] ?>/<?= $s['rounds'] ?>× · min <?= (int)$s['min_accuracy'] ?> %
                <?php if (!$s['done']): ?>
                <a class="challenge-step-play" href="<?= htmlspecialchars($s['url']) ?>">hrát →</a>
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
<?php }
?>

<div class="page-header">
    <h1>🎯 Moje <span class="accent">výzvy</span></h1>
    <p class="page-subtitle">Úkoly, které ti zadal rodič</p>
</div>

<?php if (!$items): ?>
<div class="empty-state">
    <div class="empty-icon">🎯</div>
    <p>Zatím nemáš žádnou výzvu. <a href="<?= BASE_URL ?>/dashboard.php">Zahraj si, co tě baví!</a></p>
</div>
<?php endif; ?>

<?php if ($open): ?>
<section style="margin-bottom:2rem">
    <h2 class="section-title">Rozdělané</h2>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1rem">
        Úkol se odškrtne sám, jakmile dohraješ kolo s požadovanou přesností.
    </p>
    <?php foreach ($open as $c) renderChallengeCard($c); ?>
</section>
<?php endif; ?>

<?php if ($done): ?>
<section>
    <h2 class="section-title">Hotové</h2>
    <?php foreach ($done as $c) renderChallengeCard($c); ?>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
