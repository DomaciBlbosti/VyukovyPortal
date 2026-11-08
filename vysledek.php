<?php
/**
 * Detail jedné odehrané hry: všechny odpovědi vedle správných.
 * Dítě vidí svoje hry, rodič (admin) všechny — kvůli kontrole vyhodnocení.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/results.php';
require_once __DIR__ . '/includes/mistakes_view.php';

$user    = getCurrentUser();
$session = getGameSession((int)($_GET['id'] ?? 0));
if (!$session || !canSeeSession($session, $user)) {
    header('Location: ' . BASE_URL . '/vysledky.php');
    exit;
}
$answers = gameAnswers((int)$session['id']);
$wrong   = count(array_filter($answers, fn($a) => !(int)$a['ok']));
$mine    = (int)$session['user_id'] === (int)$user['id'];
$backUrl = BASE_URL . '/vysledky.php' . ($mine ? '' : '?user=' . (int)$session['user_id']);

$pageTitle = 'Výsledek hry';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>📋 <span class="accent"><?= htmlspecialchars(gameTypeLabel((string)$session['game_type'])) ?></span>
        <?php if (trim((string)$session['text_snippet']) !== ''): ?><span style="font-size:.7em;color:var(--muted)">· <?= htmlspecialchars((string)$session['text_snippet']) ?></span><?php endif; ?></h1>
    <p class="page-subtitle">
        <?php if (!$mine): ?><strong><?= htmlspecialchars($session['display_name'] ?: $session['username']) ?></strong> · <?php endif; ?>
        <?= date('j. n. Y H:i', strtotime((string)$session['played_at'])) ?> ·
        přesnost <strong><?= round((float)$session['accuracy']) ?> %</strong> ·
        chyb <strong><?= (int)$session['errors'] ?></strong> ·
        čas <?= htmlspecialchars(durationLabel((int)$session['duration_seconds'])) ?>
        <?php if ((int)$session['points'] > 0): ?>· ⭐ <?= (int)$session['points'] ?> bodů<?php endif; ?>
    </p>
</div>

<p style="margin-bottom:1rem"><a href="<?= htmlspecialchars($backUrl) ?>">← všechny hry</a></p>

<?php if (!$answers): ?>
<section class="admin-card">
    <p class="mistake-hint">K téhle hře nejsou uložené jednotlivé odpovědi — buď je to psaní (tam se hodnotí text jako celek),
        nebo byla odehraná před verzí, která odpovědi ukládá.</p>
</section>
<?php else: ?>
<section class="admin-card">
    <h2 class="section-title" style="margin-bottom:.75rem">Odpovědi (<?= count($answers) ?>) · správně <?= count($answers) - $wrong ?> · špatně <?= $wrong ?></h2>
    <div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>#</th><th>Zadání</th><th>Odpověď</th><th>Správně</th><th></th></tr></thead>
        <tbody>
        <?php $lastLabel = null; foreach ($answers as $i => $a): ?>
            <?php if ((string)$a['topic_label'] !== '' && $a['topic_label'] !== $lastLabel): $lastLabel = $a['topic_label']; ?>
            <tr><td colspan="5" class="mistake-hint" style="font-family:var(--font-mono)"><?= htmlspecialchars($a['topic_label']) ?></td></tr>
            <?php endif; ?>
            <tr class="<?= (int)$a['ok'] ? '' : 'answer-wrong' ?>">
                <td style="color:var(--muted)"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars((string)$a['prompt']) ?></td>
                <td style="font-family:var(--font-mono)"><?= (string)$a['given'] !== '' ? htmlspecialchars((string)$a['given']) : '<span class="mistake-hint">—</span>' ?></td>
                <td style="font-family:var(--font-mono)"><?= (int)$a['ok'] && (string)$a['given'] !== '' ? '<span class="mistake-hint">=</span>' : htmlspecialchars((string)$a['answer']) ?></td>
                <td><?= (int)$a['ok'] ? '<span class="answer-ok">✔</span>' : '<span class="answer-err">✘</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="mistake-hint" style="margin-top:.75rem">„=“ znamená, že odpověď byla správně; u chyb je vedle správné řešení.
        Odpovědi se porovnávají bez ohledu na velikost písmen a mezery, u matematiky i na tečku místo desetinné čárky.</p>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
