<?php
/**
 * Admin — Levely a body: kolik bodů je potřeba na daný level
 * a jakým multiplikátorem se hodnotí jednotlivé hry.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/levels.php';

$db  = getDB();
$msg = ''; $msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_levels') {
            // Ulož všechny řádky najednou (prázdné = smazat)
            $nums   = $_POST['level_number'] ?? [];
            $points = $_POST['points_required'] ?? [];
            $titles = $_POST['title'] ?? [];
            $icons  = $_POST['icon'] ?? [];

            $rows = [];
            foreach ($nums as $i => $num) {
                $num = (int)$num;
                if ($num < 1) continue;
                $rows[$num] = [
                    'points' => max(0, (int)($points[$i] ?? 0)),
                    'title'  => trim($titles[$i] ?? '') ?: ('Level ' . $num),
                    'icon'   => mb_substr(trim($icons[$i] ?? '') ?: '⭐', 0, 4),
                ];
            }
            if (!$rows) throw new Exception('Musí zůstat aspoň jeden level.');

            $db->beginTransaction();
            $db->exec('DELETE FROM levels');
            $stmt = $db->prepare('INSERT INTO levels (level_number, points_required, title, icon) VALUES (?,?,?,?)');
            foreach ($rows as $num => $r) {
                $stmt->execute([$num, $r['points'], $r['title'], $r['icon']]);
            }
            $db->commit();
            $msg = 'Levely uloženy (' . count($rows) . ').';

        } elseif ($action === 'add_level') {
            $max = (int)$db->query('SELECT COALESCE(MAX(level_number), 0) FROM levels')->fetchColumn();
            $pts = (int)$db->query('SELECT COALESCE(MAX(points_required), 0) FROM levels')->fetchColumn();
            $db->prepare('INSERT INTO levels (level_number, points_required, title, icon) VALUES (?,?,?,?)')
               ->execute([$max + 1, $pts + 1000, 'Level ' . ($max + 1), '⭐']);
            $msg = 'Level ' . ($max + 1) . ' přidán — uprav body a název.';

        } elseif ($action === 'save_multipliers') {
            $types = $_POST['game_type'] ?? [];
            $mults = $_POST['multiplier'] ?? [];
            $stmt  = $db->prepare('UPDATE game_multipliers SET multiplier = ? WHERE game_type = ?');
            foreach ($types as $i => $type) {
                $m = (float)str_replace(',', '.', $mults[$i] ?? '1');
                $stmt->execute([max(0.1, min(10, $m)), $type]);
            }
            $msg = 'Multiplikátory uloženy.';

        } elseif ($action === 'reset_defaults') {
            $db->beginTransaction();
            $db->exec('DELETE FROM levels');
            $stmt = $db->prepare('INSERT INTO levels (level_number, points_required, title, icon) VALUES (?,?,?,?)');
            foreach (DEFAULT_LEVELS as $num => [$pts, $title, $icon]) {
                $stmt->execute([$num, $pts, $title, $icon]);
            }
            $stmt = $db->prepare('UPDATE game_multipliers SET multiplier = ? WHERE game_type = ?');
            foreach (DEFAULT_MULTIPLIERS as $type => [$label, $mult]) {
                $stmt->execute([$mult, $type]);
            }
            $db->commit();
            $msg = 'Obnoveno výchozí nastavení.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $msg = 'Chyba: ' . $e->getMessage(); $msgType = 'err';
    }
}

$levels = $db->query('SELECT level_number, points_required, title, icon FROM levels ORDER BY level_number ASC')->fetchAll();
$mults  = $db->query('SELECT game_type, label, multiplier FROM game_multipliers ORDER BY label ASC')->fetchAll();

// Přehled hráčů podle bodů
$players = $db->query('
    SELECT u.id, u.display_name, COALESCE(SUM(gs.points), 0) AS points, COUNT(gs.id) AS games
    FROM users u
    LEFT JOIN game_sessions gs ON gs.user_id = u.id
    GROUP BY u.id, u.display_name
    ORDER BY points DESC
')->fetchAll();

$pageTitle = 'Levely a body';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🏆 <span class="accent">Levely</span> a body</h1>
    <p class="page-subtitle">Kolik bodů je potřeba na level a jak se hodnotí jednotlivé hry</p>
</div>

<p style="margin-bottom:1.5rem"><a href="<?= BASE_URL ?>/admin/index.php">← Zpět na správu uživatelů</a></p>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:1.5rem">
    <h2 class="section-title">📊 Jak se počítají body</h2>
    <p style="color:var(--muted);font-size:.9rem;line-height:1.7">
        <code>body = zvládnuté jednotky × přesnost × multiplikátor hry</code><br>
        <strong>Jednotky</strong>: u psaní počet napsaných slov (znaky ÷ 5), u matematiky a zeměpisu počet správných odpovědí.<br>
        <strong>Přesnost</strong>: 100 % → plný počet, 50 % → tři čtvrtiny, 0 % → polovina.<br>
        Body se uloží při dokončení hry — pozdější změna multiplikátoru už odehrané hry nepřepočítá.
    </p>
</div>

<!-- ── LEVELY ─────────────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
    <h2 class="section-title">🏅 Levely</h2>
    <form method="post">
        <input type="hidden" name="action" value="save_levels">
        <div class="table-wrapper">
        <table class="data-table admin-table">
            <thead>
                <tr><th>Level</th><th>Body na level</th><th>Název</th><th>Ikona</th><th>Hráčů</th></tr>
            </thead>
            <tbody>
            <?php foreach ($levels as $lvl):
                $inLevel = 0;
                foreach ($players as $p) {
                    if (levelForPoints((int)$p['points'])['level'] === (int)$lvl['level_number']) $inLevel++;
                }
            ?>
                <tr>
                    <td style="width:5rem">
                        <input type="number" class="form-input" name="level_number[]" value="<?= (int)$lvl['level_number'] ?>" min="1" style="width:4.5rem;padding:.4rem .5rem">
                    </td>
                    <td style="width:9rem">
                        <input type="number" class="form-input" name="points_required[]" value="<?= (int)$lvl['points_required'] ?>" min="0" style="width:8rem;padding:.4rem .5rem">
                    </td>
                    <td>
                        <input type="text" class="form-input" name="title[]" value="<?= htmlspecialchars($lvl['title']) ?>" style="padding:.4rem .5rem">
                    </td>
                    <td style="width:5rem">
                        <input type="text" class="form-input" name="icon[]" value="<?= htmlspecialchars($lvl['icon']) ?>" maxlength="4" style="width:4rem;padding:.4rem .5rem;text-align:center">
                    </td>
                    <td style="color:var(--muted);font-family:var(--font-mono)"><?= $inLevel ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="color:var(--muted);font-size:.8rem;margin:.75rem 0">
            Smazání levelu: vymaž číslo levelu a ulož. Levely se řadí podle počtu bodů, první má mít 0.
        </p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="btn-primary">💾 Uložit levely</button>
            <button type="submit" class="btn-secondary" name="action" value="add_level">➕ Přidat level</button>
        </div>
    </form>
</div>

<!-- ── MULTIPLIKÁTORY ─────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
    <h2 class="section-title">✖️ Multiplikátory her</h2>
    <form method="post">
        <input type="hidden" name="action" value="save_multipliers">
        <div class="table-wrapper">
        <table class="data-table admin-table">
            <thead>
                <tr><th>Hra</th><th>Multiplikátor</th><th>Typická hra vydá</th></tr>
            </thead>
            <tbody>
            <?php foreach ($mults as $m):
                // Modelový příklad: psaní 90 znaků / kvíz 15 odpovědí, 90 % přesnost
                $isTyping = in_array($m['game_type'], ['classic','timed','blind','duel'], true);
                $sample   = calculatePoints([
                    'game_type'   => $m['game_type'],
                    'accuracy'    => 90,
                    'chars_typed' => $isTyping ? 450 : 15,
                ]);
            ?>
                <tr>
                    <td><?= htmlspecialchars($m['label']) ?>
                        <div style="color:var(--muted);font-size:.75rem;font-family:var(--font-mono)"><?= htmlspecialchars($m['game_type']) ?></div>
                    </td>
                    <td style="width:8rem">
                        <input type="hidden" name="game_type[]" value="<?= htmlspecialchars($m['game_type']) ?>">
                        <input type="number" step="0.1" min="0.1" max="10" class="form-input" name="multiplier[]"
                               value="<?= rtrim(rtrim(number_format((float)$m['multiplier'], 2, '.', ''), '0'), '.') ?>"
                               style="width:6rem;padding:.4rem .5rem">
                    </td>
                    <td style="color:var(--accent);font-family:var(--font-mono)">
                        ~<?= $sample ?> b.
                        <span style="color:var(--muted);font-size:.75rem">
                            (<?= $isTyping ? '90 slov' : '15 odpovědí' ?>, 90 %)
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div style="margin-top:1rem">
            <button type="submit" class="btn-primary">💾 Uložit multiplikátory</button>
        </div>
    </form>
</div>

<!-- ── HRÁČI ──────────────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
    <h2 class="section-title">👥 Hráči podle bodů</h2>
    <div class="table-wrapper">
    <table class="data-table admin-table">
        <thead><tr><th>#</th><th>Hráč</th><th>Level</th><th>Body</th><th>Her</th></tr></thead>
        <tbody>
        <?php foreach ($players as $i => $p): $lv = levelForPoints((int)$p['points']); ?>
            <tr>
                <td style="color:var(--muted)"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($p['display_name']) ?></td>
                <td><?= $lv['icon'] ?> <?= $lv['level'] ?> — <?= htmlspecialchars($lv['title']) ?></td>
                <td style="font-family:var(--font-mono);color:var(--accent)"><?= (int)$p['points'] ?></td>
                <td style="color:var(--muted)"><?= (int)$p['games'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<form method="post" onsubmit="return confirm('Opravdu obnovit výchozí levely i multiplikátory?')">
    <input type="hidden" name="action" value="reset_defaults">
    <button type="submit" class="btn-sm btn-sm-orange">↺ Obnovit výchozí nastavení</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
