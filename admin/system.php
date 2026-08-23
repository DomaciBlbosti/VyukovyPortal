<?php
/**
 * Admin — Systém: verze aplikace a ruční aktualizace z Gitu
 * (stejný princip jako u Kuchařky; PHP se načítá per-request,
 * takže po `git pull` běží nová verze hned, bez restartu)
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$root   = dirname(__DIR__);
$hasGit = is_dir($root . '/.git') && trim((string)shell_exec('command -v git')) !== '';

function runGit(string $args): array {
    $root = dirname(__DIR__);
    putenv('HOME=/tmp'); // www-data nemá domovský adresář, git by spadl na configu
    $out  = [];
    $code = 0;
    exec('cd ' . escapeshellarg($root) . ' && git ' . $args . ' 2>&1', $out, $code);
    return [$code, trim(implode("\n", $out))];
}

$branch = $hasGit ? runGit('rev-parse --abbrev-ref HEAD')[1] : '';
$msg = ''; $msgType = 'ok'; $updateLog = '';

if ($hasGit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check') {
        [$fc, $fout] = runGit('fetch origin ' . escapeshellarg($branch));
        if ($fc !== 0) {
            $msg = 'Kontrola selhala: ' . $fout; $msgType = 'err';
        } else {
            $behind = (int)runGit('rev-list --count HEAD..origin/' . escapeshellarg($branch))[1];
            if ($behind > 0) {
                $msg = "K dispozici je novější verze ({$behind} " . ($behind === 1 ? 'commit' : ($behind < 5 ? 'commity' : 'commitů')) . ' za Gitem).';
                $msgType = 'warn';
            } else {
                $msg = 'Aplikace je aktuální. ✔';
            }
        }
    } elseif ($action === 'update') {
        [$fc, $fout] = runGit('fetch origin ' . escapeshellarg($branch));
        [$pc, $pout] = runGit('pull --ff-only origin ' . escapeshellarg($branch));
        $updateLog = trim($fout . "\n" . $pout);
        if ($pc === 0) {
            // Nová verze může přinést změny schématu — dožeň je hned,
            // jinak by appka běžela na starou databázi.
            try {
                require_once __DIR__ . '/../config/db.php';
                require_once __DIR__ . '/../includes/migrate.php';
                $steps = runMigrations(getDB());
                if ($steps) $updateLog .= "\n\n[db] " . implode("\n[db] ", $steps);
            } catch (Throwable $e) {
                $updateLog .= "\n\n[db] migrace selhala: " . $e->getMessage();
                $msgType = 'warn';
            }
            $msg = str_contains($pout, 'Already up to date')
                 ? 'Aplikace už byla aktuální.'
                 : 'Aktualizace proběhla — běží nová verze. Uživatelům se projeví po obnovení stránky.';
        } else {
            $msg = 'Aktualizace selhala — viz výpis níže.'; $msgType = 'err';
        }
    }
}

// Aktuální verze (po případném pullu, ať ukazuje čerstvý stav)
$fmt   = escapeshellarg('--format=%h|%ci|%s'); // | musí být v uvozovkách, jinak ho shell bere jako rouru
$local = $hasGit ? runGit("log -1 $fmt")[1] : '';
[$commitHash, $commitDate, $commitMsg] = $hasGit && $local ? array_pad(explode('|', $local, 3), 3, '') : ['', '', ''];
$behindNow = $hasGit ? (int)runGit('rev-list --count HEAD..origin/' . escapeshellarg($branch))[1] : 0;
$remote = $hasGit ? runGit("log -1 $fmt origin/" . escapeshellarg($branch))[1] : '';
[$remHash, $remDate, $remMsg] = $hasGit && $remote ? array_pad(explode('|', $remote, 3), 3, '') : ['', '', ''];

$pageTitle = 'Systém';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🔄 <span class="accent">Systém</span> — aktualizace</h1>
    <p class="page-subtitle">Verze aplikace a ruční update z Gitu</p>
</div>

<p style="margin-bottom:1.5rem"><a href="<?= BASE_URL ?>/admin/index.php">← Zpět na správu uživatelů</a></p>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (!$hasGit): ?>
<div class="admin-card">
    <p style="color:var(--muted)">Aktualizace z Gitu je dostupná jen v Docker/TrueNAS nasazení
    (aplikace musí být klonem Git repozitáře a na serveru musí být <code>git</code>).</p>
</div>
<?php else: ?>

<div class="admin-card" style="margin-bottom:1.5rem">
    <h2 class="section-title">Nainstalovaná verze</h2>
    <table class="data-table">
        <tr><td style="color:var(--muted)">Větev</td><td><code><?= htmlspecialchars($branch) ?></code></td></tr>
        <tr><td style="color:var(--muted)">Commit</td><td><code><?= htmlspecialchars($commitHash) ?></code> — <?= htmlspecialchars($commitMsg) ?></td></tr>
        <tr><td style="color:var(--muted)">Datum</td><td><?= htmlspecialchars($commitDate) ?></td></tr>
        <tr><td style="color:var(--muted)">Stav</td><td>
            <?php if ($behindNow > 0): ?>
                <span class="badge-status disabled">⬆ k dispozici novější verze (<?= $behindNow ?>)</span>
            <?php else: ?>
                <span class="badge-status active">✔ aktuální (podle posledního fetch)</span>
            <?php endif; ?>
        </td></tr>
        <?php if ($behindNow > 0 && $remHash): ?>
        <tr><td style="color:var(--muted)">Nejnovější</td><td><code><?= htmlspecialchars($remHash) ?></code> — <?= htmlspecialchars($remMsg) ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="admin-card" style="margin-bottom:1.5rem">
    <h2 class="section-title">Aktualizace</h2>
    <p style="color:var(--muted);font-size:.9rem;margin-bottom:1rem">
        Stáhne nejnovější verzi z GitHubu (<code>git pull</code>). PHP běží novou verzí okamžitě,
        restart není potřeba. Prohlížeče si nové skripty dotáhnou při obnovení stránky.
    </p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <form method="post"><input type="hidden" name="action" value="check">
            <button type="submit" class="btn-secondary">🔍 Zkontrolovat aktualizace</button>
        </form>
        <form method="post"><input type="hidden" name="action" value="update">
            <button type="submit" class="btn-primary">⬇ Aktualizovat z Gitu</button>
        </form>
    </div>
    <?php if ($updateLog): ?>
    <pre style="margin-top:1rem;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:1rem;font-size:.78rem;overflow-x:auto;white-space:pre-wrap"><?= htmlspecialchars($updateLog) ?></pre>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
