<?php
/**
 * Seznam odehraných her s odkazem na všechny odpovědi.
 * Dítě vidí svoje; rodič (admin) si přes ?user=ID vybere dítě.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/results.php';
require_once __DIR__ . '/includes/mistakes_view.php';

$user   = getCurrentUser();
$db     = getDB();
$target = (int)$user['id'];
$kids   = [];
if (!empty($user['is_admin'])) {
    $kids = $db->query('SELECT id, display_name, username FROM users WHERE is_active = 1 ORDER BY is_admin ASC, display_name ASC')->fetchAll();
    $want = (int)($_GET['user'] ?? 0);
    if ($want && in_array($want, array_map(fn($k) => (int)$k['id'], $kids), true)) $target = $want;
}
$who = null;
foreach ($kids as $k) if ((int)$k['id'] === $target) $who = $k;

$gameType = (string)($_GET['hra'] ?? '');
$sessions = listGameSessions($target, 150, $gameType);
$types    = $db->prepare('SELECT DISTINCT game_type FROM game_sessions WHERE user_id = ? ORDER BY game_type');
$types->execute([$target]);
$types = $types->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Odehrané hry';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>📋 Odehrané <span class="accent">hry</span></h1>
    <p class="page-subtitle">Každou hru si můžeš otevřít a projít všechny odpovědi</p>
</div>

<?php if ($kids): ?>
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <?php foreach ($kids as $k): ?>
    <a href="?user=<?= (int)$k['id'] ?>" class="btn-sm <?= (int)$k['id'] === $target ? 'btn-sm-blue' : '' ?>"><?= htmlspecialchars($k['display_name'] ?: $k['username']) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (count($types) > 1): ?>
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <a href="?<?= $who ? 'user=' . $target : '' ?>" class="btn-sm <?= $gameType === '' ? 'btn-sm-blue' : '' ?>">vše</a>
    <?php foreach ($types as $t): ?>
    <a href="?<?= $who ? 'user=' . $target . '&amp;' : '' ?>hra=<?= urlencode($t) ?>" class="btn-sm <?= $gameType === $t ? 'btn-sm-blue' : '' ?>"><?= htmlspecialchars(gameTypeLabel($t)) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="admin-card">
    <?php if (!$sessions): ?>
    <p class="mistake-hint">Zatím žádná odehraná hra.</p>
    <?php else: ?>
    <div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Kdy</th><th>Hra</th><th>Sada</th><th>Přesnost</th><th>Chyb</th><th>Čas</th><th>Odpovědi</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
            <tr>
                <td style="white-space:nowrap"><?= date('j. n. H:i', strtotime((string)$s['played_at'])) ?></td>
                <td><?= htmlspecialchars(gameTypeLabel((string)$s['game_type'])) ?></td>
                <td class="mistake-hint"><?= htmlspecialchars(mb_substr((string)$s['text_snippet'], 0, 60)) ?></td>
                <td class="<?= (float)$s['accuracy'] >= 90 ? 'accent' : '' ?>"><?= round((float)$s['accuracy']) ?> %</td>
                <td><?= (int)$s['errors'] ?></td>
                <td><?= htmlspecialchars(durationLabel((int)$s['duration_seconds'])) ?></td>
                <td>
                    <?php if ((int)$s['answer_count'] > 0): ?>
                    <a href="<?= BASE_URL ?>/vysledek.php?id=<?= (int)$s['id'] ?>" class="btn-sm btn-sm-blue"><?= (int)$s['answer_count'] ?> odpovědí<?= (int)$s['wrong_count'] ? ' · ' . (int)$s['wrong_count'] . ' ✘' : '' ?></a>
                    <?php else: ?><span class="mistake-hint">—</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="mistake-hint" style="margin-top:.75rem">Odpovědi se ukládají od listopadu 2026; u starších her a u psaní všemi deseti nejsou.</p>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
