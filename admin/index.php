<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

$msg = ''; $msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = intval($_POST['uid'] ?? 0);
    $self   = getCurrentUser()['id'];

    if ($action === 'create_user') {
        $uname  = trim($_POST['username'] ?? '');
        $dname  = trim($_POST['display_name'] ?? '');
        $passwd = $_POST['password'] ?? '';
        $adm    = !empty($_POST['is_admin']) ? 1 : 0;
        $grade  = max(0, min(9, intval($_POST['grade'] ?? 0)));
        if ($uname && $passwd) {
            try {
                $db->prepare('INSERT INTO users (username, password_hash, display_name, is_admin, grade) VALUES (?,?,?,?,?)')
                   ->execute([$uname, password_hash($passwd, PASSWORD_BCRYPT), $dname ?: $uname, $adm, $grade]);
                $msg = 'Uzivatel ' . htmlspecialchars($uname) . ' byl vytvoren.';
            } catch (\PDOException $e) {
                $msg = 'Chyba: prihlasovaci jmeno jiz existuje.'; $msgType = 'err';
            }
        } else { $msg = 'Vyplnte prihlasovaci jmeno a heslo.'; $msgType = 'err'; }

    } elseif ($action === 'reset_password' && $uid) {
        $passwd = $_POST['new_password'] ?? '';
        if (strlen($passwd) >= 6) {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($passwd, PASSWORD_BCRYPT), $uid]);
            $stmt = $db->prepare('SELECT display_name FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $u = $stmt->fetch();
            $msg = 'Heslo uzivatele ' . htmlspecialchars($u['display_name'] ?? '') . ' bylo zmeneno.';
        } else { $msg = 'Heslo musi mit alespon 6 znaku.'; $msgType = 'err'; }

    } elseif ($action === 'set_grade' && $uid) {
        $grade = max(0, min(9, intval($_POST['grade'] ?? 0)));
        $db->prepare('UPDATE users SET grade = ? WHERE id = ?')->execute([$grade, $uid]);
        // Vlastní ročník se musí projevit hned, bez odhlášení
        if ($uid === $self) $_SESSION['grade'] = $grade;
        $msg = $grade > 0 ? "Rocnik nastaven na $grade. tridu." : 'Rocnik zrusen (zobrazi se vsechny sady).';

    } elseif ($action === 'toggle_admin' && $uid && $uid !== $self) {
        $stmt = $db->prepare('SELECT is_admin, display_name FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        $new = $u['is_admin'] ? 0 : 1;
        $db->prepare('UPDATE users SET is_admin = ? WHERE id = ?')->execute([$new, $uid]);
        $msg = 'Uzivatel ' . htmlspecialchars($u['display_name'] ?? '') . ($new ? ' je nyni administrator.' : ' neni administrator.');

    } elseif ($action === 'toggle_active' && $uid && $uid !== $self) {
        $stmt = $db->prepare('SELECT is_active, display_name FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        $new = $u['is_active'] ? 0 : 1;
        $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new, $uid]);
        $msg = 'Ucet ' . htmlspecialchars($u['display_name'] ?? '') . ($new ? ' byl aktivovan.' : ' byl deaktivovan.');

    } elseif ($action === 'delete_user' && $uid && $uid !== $self) {
        $stmt = $db->prepare('SELECT display_name FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        $msg = 'Uzivatel ' . htmlspecialchars($u['display_name'] ?? '') . ' byl smazan.';
        $msgType = 'warn';
    }
}

// Data
$users = $db->query("
    SELECT u.id, u.username, u.display_name, u.is_admin, u.is_active, u.grade,
           u.created_at, u.last_login,
           COUNT(gs.id)   AS total_games,
           ROUND(MAX(gs.wpm), 1) AS best_wpm
    FROM users u
    LEFT JOIN game_sessions gs ON gs.user_id = u.id
    GROUP BY u.id, u.username, u.display_name, u.is_admin, u.is_active, u.grade, u.created_at, u.last_login
    ORDER BY u.is_admin DESC, u.created_at ASC
")->fetchAll();

$stats = $db->query("
    SELECT COUNT(*) AS total_users,
           SUM(is_admin)   AS admin_count,
           SUM(1-is_active) AS disabled_count
    FROM users
")->fetch();

$gameStats = $db->query("
    SELECT COUNT(*)          AS total_sessions,
           ROUND(AVG(wpm),1) AS global_avg_wpm,
           ROUND(MAX(wpm),1) AS global_best_wpm
    FROM game_sessions
")->fetch();

$pageTitle = 'Admin panel';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
        <h1>&#9881; <span class="accent">Admin</span> panel</h1>
        <p class="page-subtitle">Sprava uzivatelu a systemu</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/admin/deti.php" class="btn-secondary">👨‍👩‍👧 Přehled dětí</a>
        <a href="<?= BASE_URL ?>/admin/levels.php" class="btn-secondary">🏆 Levely &amp; body</a>
        <a href="<?= BASE_URL ?>/admin/system.php" class="btn-secondary">🔄 Systém &amp; aktualizace</a>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="quick-stats" style="margin-bottom:2rem">
    <div class="stat-card"><div class="stat-value"><?= (int)$stats['total_users'] ?></div><div class="stat-label">uzivatelu</div></div>
    <div class="stat-card"><div class="stat-value"><?= (int)$stats['admin_count'] ?></div><div class="stat-label">adminu</div></div>
    <div class="stat-card"><div class="stat-value"><?= (int)$stats['disabled_count'] ?></div><div class="stat-label">deaktivovanych</div></div>
    <div class="stat-card"><div class="stat-value"><?= (int)$gameStats['total_sessions'] ?></div><div class="stat-label">odehranych her</div></div>
    <div class="stat-card"><div class="stat-value"><?= $gameStats['global_best_wpm'] ?? '-' ?></div><div class="stat-label">rekord WPM</div></div>
    <div class="stat-card"><div class="stat-value"><?= $gameStats['global_avg_wpm'] ?? '-' ?></div><div class="stat-label">prumer WPM</div></div>
</div>

<section style="margin-bottom:2.5rem">
    <h2 class="section-title">&#128101; Uzivatele</h2>
    <div class="table-wrapper">
    <table class="data-table admin-table">
        <thead>
            <tr>
                <th>#</th><th>Login</th><th>Jmeno</th><th>Role</th><th>Stav</th>
                <th>Rocnik</th><th>Hry</th><th>Nej WPM</th><th>Posledni prihlaseni</th><th>Akce</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
            $isSelf = ($u['id'] === getCurrentUser()['id']); ?>
        <tr class="<?= $isSelf ? 'highlight-row' : '' ?> <?= !$u['is_active'] ? 'row-disabled' : '' ?>">
            <td><?= (int)$u['id'] ?></td>
            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
            <td>
                <?= htmlspecialchars($u['display_name']) ?>
                <?php if ($isSelf): ?><span style="color:var(--muted);font-size:.75rem">(ty)</span><?php endif; ?>
            </td>
            <td>
                <?php if ($u['is_admin']): ?>
                    <span class="badge-role admin">&#9881; Admin</span>
                <?php else: ?>
                    <span class="badge-role user">&#128100; Hrac</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($u['is_active']): ?>
                    <span class="badge-status active">&#10004; Aktivni</span>
                <?php else: ?>
                    <span class="badge-status disabled">&#10008; Deaktivovan</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" style="display:flex;gap:.25rem;align-items:center">
                    <input type="hidden" name="action" value="set_grade">
                    <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                    <select name="grade" class="form-input" style="padding:.25rem .4rem;font-size:.8rem;width:auto"
                            onchange="this.form.submit()">
                        <option value="0" <?= (int)$u['grade'] === 0 ? 'selected' : '' ?>>-</option>
                        <?php for ($g = 1; $g <= 9; $g++): ?>
                        <option value="<?= $g ?>" <?= (int)$u['grade'] === $g ? 'selected' : '' ?>><?= $g ?>. trida</option>
                        <?php endfor; ?>
                    </select>
                </form>
            </td>
            <td><?= (int)$u['total_games'] ?></td>
            <td><?= $u['best_wpm'] ?? '-' ?></td>
            <td style="font-size:.8rem;color:var(--muted)">
                <?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '-' ?>
            </td>
            <td>
                <div class="admin-actions">
                    <button class="btn-sm btn-sm-blue"
                        onclick="openResetModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['display_name'], ENT_QUOTES) ?>')">
                        &#128273; Heslo
                    </button>
                    <?php if (!$isSelf): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle_admin">
                        <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                        <button class="btn-sm <?= $u['is_admin'] ? 'btn-sm-orange' : 'btn-sm-gray' ?>"
                            onclick="return confirm('<?= $u['is_admin'] ? 'Odebrat admin prava?' : 'Udelit admin prava?' ?>')">
                            <?= $u['is_admin'] ? 'Odebrat admin' : 'Udelit admin' ?>
                        </button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                        <button class="btn-sm <?= $u['is_active'] ? 'btn-sm-orange' : 'btn-sm-green' ?>"
                            onclick="return confirm('<?= $u['is_active'] ? 'Deaktivovat ucet?' : 'Aktivovat ucet?' ?>')">
                            <?= $u['is_active'] ? 'Deaktivovat' : 'Aktivovat' ?>
                        </button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                        <button class="btn-sm btn-sm-red"
                            onclick="return confirm('Opravdu smazat uzivatele <?= htmlspecialchars($u['display_name'], ENT_QUOTES) ?>?')">
                            &#128465; Smazat
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="admin-card" style="max-width:520px;margin-bottom:2rem">
    <h2 class="section-title">&#10133; Novy uzivatel</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="action" value="create_user">
        <div class="form-row">
            <label>Prihlasovaci jmeno *</label>
            <input type="text" name="username" class="form-input" required autocomplete="off">
        </div>
        <div class="form-row">
            <label>Zobrazovane jmeno</label>
            <input type="text" name="display_name" class="form-input" placeholder="(shodne s prihlasovacim)">
        </div>
        <div class="form-row">
            <label>Heslo *</label>
            <input type="password" name="password" class="form-input" required autocomplete="new-password">
        </div>
        <div class="form-row">
            <label>Rocnik (urcuje obtiznost uloh)</label>
            <select name="grade" class="form-input">
                <option value="0">- neuveden (vsechny sady) -</option>
                <?php for ($g = 1; $g <= 9; $g++): ?>
                <option value="<?= $g ?>"><?= $g ?>. trida</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-row form-row-check">
            <label><input type="checkbox" name="is_admin" value="1"> Udelit admin prava</label>
        </div>
        <button type="submit" class="btn-primary" style="margin-top:.5rem">&#10133; Vytvorit uzivatele</button>
    </form>
</section>

<div id="resetModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeResetModal()">
    <div class="modal-box">
        <h3 class="modal-title">&#128273; Reset hesla</h3>
        <p id="resetModalName" style="color:var(--muted);margin-bottom:1rem"></p>
        <form method="post" class="admin-form">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="uid" id="resetUid">
            <div class="form-row">
                <label>Nove heslo (min. 6 znaku)</label>
                <input type="password" name="new_password" id="resetPwd" class="form-input" required autocomplete="new-password">
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Nastavit heslo</button>
                <button type="button" class="btn-secondary" onclick="closeResetModal()">Zrusit</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(uid, name) {
    document.getElementById('resetUid').value = uid;
    document.getElementById('resetModalName').textContent = 'Uzivatel: ' + name;
    document.getElementById('resetPwd').value = '';
    document.getElementById('resetModal').style.display = 'flex';
    document.getElementById('resetPwd').focus();
}
function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeResetModal(); });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
