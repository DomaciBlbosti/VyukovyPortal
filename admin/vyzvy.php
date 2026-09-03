<?php
/**
 * Admin — editor výzev.
 *
 * Rodič poskládá sadu úkolů („2× násobilka řada 7, vyjmenovaná po B,
 * slepá mapa krajů, všechno aspoň na 90 %") a zadá ji dětem. Postup se
 * pak vyhodnocuje sám po každé dohrané hře.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/challenges.php';

$db  = getDB();
$msg = ''; $msgType = 'ok';

$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_challenge') {
            $id    = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $desc  = trim((string)($_POST['description'] ?? ''));
            if ($title === '') throw new RuntimeException('Výzva musí mít název.');

            if ($id) {
                $db->prepare('UPDATE challenges SET title = ?, description = ? WHERE id = ?')
                   ->execute([mb_substr($title, 0, 120), mb_substr($desc, 0, 255), $id]);
            } else {
                $db->prepare('INSERT INTO challenges (title, description, created_by, created_at) VALUES (?,?,?,?)')
                   ->execute([mb_substr($title, 0, 120), mb_substr($desc, 0, 255),
                              (int)getCurrentUser()['id'], date('Y-m-d H:i:s')]);
                $id = (int)$db->lastInsertId();
            }

            // Kroky ukládáme celé znovu — je jich pár a je to jednodušší
            // než dopočítávat, co přibylo a co zmizelo
            $db->prepare('DELETE FROM challenge_steps WHERE challenge_id = ?')->execute([$id]);
            $stmt = $db->prepare('INSERT INTO challenge_steps
                                  (challenge_id, position, game_type, topic, rounds, min_accuracy)
                                  VALUES (?,?,?,?,?,?)');
            $pos = 0;
            foreach (($_POST['step_task'] ?? []) as $i => $task) {
                // hodnota přichází jako „math|nasobilka/7"
                [$gameType, $topic] = array_pad(explode('|', (string)$task, 2), 2, '');
                if (!catalogHas($gameType, $topic)) continue;
                $stmt->execute([
                    $id, $pos++, $gameType, $topic,
                    max(1, min(20, (int)($_POST['step_rounds'][$i] ?? 1))),
                    max(0, min(100, (int)($_POST['step_accuracy'][$i] ?? 90))),
                ]);
            }
            if ($pos === 0) throw new RuntimeException('Přidej do výzvy aspoň jeden úkol.');

            $msg = 'Výzva „' . htmlspecialchars($title) . '" uložena (' . $pos . ' úkolů).';
            $editId = $id;

        } elseif ($action === 'assign') {
            $id   = (int)($_POST['challenge_id'] ?? 0);
            $kids = array_map('intval', $_POST['user_id'] ?? []);
            $ins  = $db->prepare('INSERT INTO challenge_assignments (challenge_id, user_id, assigned_at) VALUES (?,?,?)');
            $has  = $db->prepare('SELECT id FROM challenge_assignments WHERE challenge_id = ? AND user_id = ?');
            $now  = date('Y-m-d H:i:s');
            $n = 0;
            foreach ($kids as $uid) {
                if (!$uid) continue;
                $has->execute([$id, $uid]);
                if ($has->fetch()) continue;   // zadané už to má
                $ins->execute([$id, $uid, $now]);
                $n++;
            }
            $msg = $n ? 'Výzva zadána ' . $n . '×.' : 'Vybrané děti už tuhle výzvu mají.';
            $editId = $id;

        } elseif ($action === 'unassign') {
            $db->prepare('DELETE FROM challenge_assignments WHERE id = ?')->execute([(int)($_POST['assignment_id'] ?? 0)]);
            $msg    = 'Zadání zrušeno.';
            $editId = (int)($_POST['challenge_id'] ?? 0);

        } elseif ($action === 'delete_challenge') {
            $db->prepare('DELETE FROM challenges WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            $msg    = 'Výzva smazána.';
            $editId = 0;
        }
    } catch (Exception $e) {
        $msg = 'Chyba: ' . $e->getMessage(); $msgType = 'err';
    }
}

$challenges = listChallenges();
$editing    = $editId ? getChallenge($editId) : null;
$kids       = $db->query('SELECT id, display_name, username, grade FROM users
                          WHERE is_active = 1 AND is_admin = 0 ORDER BY display_name')->fetchAll();

// Komu je právě upravovaná výzva zadaná
$assigned = [];
if ($editing) {
    $stmt = $db->prepare('SELECT a.id, a.user_id, a.completed_at, u.display_name
                          FROM challenge_assignments a JOIN users u ON u.id = a.user_id
                          WHERE a.challenge_id = ? ORDER BY u.display_name');
    $stmt->execute([$editing['id']]);
    $assigned = $stmt->fetchAll();
}

$pageTitle = 'Výzvy';
include __DIR__ . '/../includes/header.php';

/** <option> se všemi úlohami, seskupené po předmětech */
function taskOptions(string $selected = ''): void {
    foreach (catalogTasks() as $gameType => $cat) {
        echo '<optgroup label="' . htmlspecialchars($cat['icon'] . ' ' . $cat['label']) . '">';
        foreach ($cat['items'] as $topic => $item) {
            $value = $gameType . '|' . $topic;
            echo '<option value="' . htmlspecialchars($value) . '"'
               . ($value === $selected ? ' selected' : '') . '>'
               . htmlspecialchars($item['label']) . '</option>';
        }
        echo '</optgroup>';
    }
}
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
        <h1>🎯 <span class="accent">Výzvy</span></h1>
        <p class="page-subtitle">Sady úkolů, které dětem zadáš k procvičení</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn-secondary">👥 Uživatelé</a>
        <a href="<?= BASE_URL ?>/admin/deti.php" class="btn-secondary">👨‍👩‍👧 Přehled dětí</a>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- ── Editor ────────────────────────────────────────────────── -->
<section class="admin-card" style="margin-bottom:2rem">
    <h2 class="section-title"><?= $editing ? '✏️ Úprava výzvy' : '➕ Nová výzva' ?></h2>

    <form method="post" id="challengeForm">
        <input type="hidden" name="action" value="save_challenge">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">

        <div class="form-row">
            <label>Název
                <input type="text" name="title" class="form-input" required maxlength="120"
                       placeholder="Např. Týdenní trénink"
                       value="<?= htmlspecialchars($editing['title'] ?? '') ?>">
            </label>
        </div>
        <div class="form-row">
            <label>Popis (nepovinný)
                <input type="text" name="description" class="form-input" maxlength="255"
                       placeholder="Co se má procvičit a proč"
                       value="<?= htmlspecialchars($editing['description'] ?? '') ?>">
            </label>
        </div>

        <h3 class="section-title" style="font-size:.95rem;margin:1.5rem 0 .5rem">Úkoly ve výzvě</h3>
        <div id="stepList">
            <?php
            $steps = $editing['steps'] ?? [];
            if (!$steps) $steps = [['game_type' => 'math', 'topic' => 'nasobilka/2', 'rounds' => 1, 'min_accuracy' => 90]];
            foreach ($steps as $s): ?>
            <div class="step-row">
                <select name="step_task[]" class="form-input step-task">
                    <?php taskOptions($s['game_type'] . '|' . $s['topic']); ?>
                </select>
                <label class="step-num">kolikrát
                    <input type="number" name="step_rounds[]" class="form-input" min="1" max="20"
                           value="<?= (int)($s['rounds'] ?? 1) ?>">
                </label>
                <label class="step-num">min. %
                    <input type="number" name="step_accuracy[]" class="form-input" min="0" max="100" step="5"
                           value="<?= (int)($s['min_accuracy'] ?? 90) ?>">
                </label>
                <button type="button" class="btn-secondary step-remove" title="Odebrat úkol">✕</button>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
            <button type="button" id="addStep" class="btn-secondary">➕ Přidat úkol</button>
            <button type="submit" class="btn-primary">💾 Uložit výzvu</button>
            <?php if ($editing): ?>
            <a href="<?= BASE_URL ?>/admin/vyzvy.php" class="btn-secondary">Nová výzva</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- ── Zadání dětem ──────────────────────────────────────────── -->
<?php if ($editing): ?>
<section class="admin-card" style="margin-bottom:2rem">
    <h2 class="section-title">👧 Komu zadat</h2>

    <?php if ($assigned): ?>
    <div style="margin-bottom:1rem">
        <?php foreach ($assigned as $a): ?>
        <div class="mistake-row">
            <span>
                <strong><?= htmlspecialchars($a['display_name']) ?></strong>
                <?php if ($a['completed_at']): ?>
                <span class="daily-done"> · ✔ splněno <?= date('j. n.', strtotime($a['completed_at'])) ?></span>
                <?php else: ?>
                <span class="mistake-hint">rozdělané</span>
                <?php endif; ?>
            </span>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="unassign">
                <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="challenge_id" value="<?= (int)$editing['id'] ?>">
                <button type="submit" class="btn-secondary" style="padding:.2rem .5rem;font-size:.75rem">zrušit</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="challenge_id" value="<?= (int)$editing['id'] ?>">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <?php foreach ($kids as $k): ?>
            <label style="display:flex;align-items:center;gap:.4rem">
                <input type="checkbox" name="user_id[]" value="<?= (int)$k['id'] ?>">
                <?= htmlspecialchars($k['display_name'] ?: $k['username']) ?>
                <?php if ((int)$k['grade'] > 0): ?><span class="mistake-hint"><?= (int)$k['grade'] ?>. tř.</span><?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-primary">📨 Zadat vybraným</button>
    </form>
</section>
<?php endif; ?>

<!-- ── Seznam výzev ──────────────────────────────────────────── -->
<section class="admin-card">
    <h2 class="section-title">📋 Hotové výzvy</h2>
    <?php if (!$challenges): ?>
    <p style="color:var(--muted);font-size:.875rem">Zatím žádná výzva. Založ první nahoře.</p>
    <?php else: ?>
    <?php foreach ($challenges as $c): ?>
    <div class="mistake-row">
        <span>
            <a href="?edit=<?= (int)$c['id'] ?>"><strong><?= htmlspecialchars($c['title']) ?></strong></a>
            <div class="mistake-hint">
                <?= (int)$c['step_count'] ?> <?= (int)$c['step_count'] === 1 ? 'úkol' : ((int)$c['step_count'] < 5 ? 'úkoly' : 'úkolů') ?>
                · zadáno <?= (int)$c['assigned_count'] ?>×
                <?= $c['description'] ? ' · ' . htmlspecialchars($c['description']) : '' ?>
            </div>
        </span>
        <form method="post" onsubmit="return confirm('Opravdu smazat výzvu i její zadání?')">
            <input type="hidden" name="action" value="delete_challenge">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn-secondary" style="padding:.2rem .5rem;font-size:.75rem">smazat</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</section>

<script>
// Řádek úkolu se přidává klonováním prvního — nabídka úloh je dlouhá
// a nemá smysl ji posílat do JS ještě jednou
document.getElementById('addStep').addEventListener('click', () => {
    const list = document.getElementById('stepList');
    const row  = list.querySelector('.step-row').cloneNode(true);
    row.querySelector('.step-task').selectedIndex = 0;
    // klon by jinak zdědil počet kol i přesnost z předchozího řádku
    row.querySelector('input[name="step_rounds[]"]').value   = 1;
    row.querySelector('input[name="step_accuracy[]"]').value = 90;
    list.appendChild(row);
});
document.getElementById('stepList').addEventListener('click', (e) => {
    if (!e.target.classList.contains('step-remove')) return;
    const list = document.getElementById('stepList');
    if (list.querySelectorAll('.step-row').length > 1) e.target.closest('.step-row').remove();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
