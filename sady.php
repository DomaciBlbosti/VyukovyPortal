<?php
/**
 * Sady z učebnic — co rodič nahrál a dítě si může zahrát.
 * Nabídka se řídí ročníkem; kdo ročník nemá vyplněný, vidí všechno.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/sets.php';

$sets = listSets(getUserGrade());

// Seskup po předmětech, ať se v tom dá vyznat, až jich bude víc
$bySubject = [];
foreach ($sets as $s) $bySubject[$s['subject']][] = $s;

$pageTitle = 'Sady z učebnic';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>📚 Sady z <span class="accent">učebnic</span></h1>
    <p class="page-subtitle">Slovíčka a učivo přesně podle toho, co se probírá ve škole</p>
</div>

<?php if (!$sets): ?>
<div class="empty-state">
    <div class="empty-icon">📚</div>
    <p>Zatím tu žádná sada není. <a href="<?= BASE_URL ?>/dashboard.php">Zahraj si něco jiného!</a></p>
</div>
<?php endif; ?>

<?php foreach ($bySubject as $subject => $group): ?>
<section style="margin-bottom:2rem">
    <h2 class="section-title"><?= htmlspecialchars(setSubjectLabel($subject)) ?></h2>
    <div class="challenge-card-list">
        <?php foreach ($group as $s): ?>
        <a class="set-card" href="<?= BASE_URL ?>/games/sada.php?id=<?= (int)$s['id'] ?>">
            <div class="set-card-head">
                <span class="challenge-title"><?= htmlspecialchars($s['title']) ?></span>
                <?php $n = (int)$s['item_count']; ?>
                <span class="challenge-meta">
                    <?= $n ?> <?= $n === 1 ? 'úloha' : ($n < 5 ? 'úlohy' : 'úloh') ?>
                </span>
            </div>
            <div class="mistake-hint">
                <?= htmlspecialchars(SET_KINDS[$s['kind']] ?? $s['kind']) ?>
                · <?= htmlspecialchars($s['source']) ?>
                <?php if ((int)$s['grade'] > 0): ?> · <?= (int)$s['grade'] ?>. třída<?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
