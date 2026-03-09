<?php
/**
 * TypeMaster — Instalační průvodce
 * Spusť jednou, pak smaž nebo přejmenuj!
 */

// Pokud už je nainstalováno, blokuj
if (file_exists(__DIR__ . '/config/installed.lock')) {
    die('<div style="font-family:monospace;text-align:center;padding:4rem;color:#f87171;background:#0d1b2e;min-height:100vh">
        <h1>⚠ TypeMaster je již nainstalován.</h1>
        <p style="color:#94a3b8">Z bezpečnostních důvodů smaž soubor <code>config/installed.lock</code> pro opakovanou instalaci.</p>
        <a href="index.php" style="color:#4ade80">→ Přejít na přihlášení</a>
    </div>');
}

$step   = intval($_GET['step'] ?? 1);
$errors = [];
$ok     = true;

// ═══════════════════════════════════════════════════════════════════════
// KROK 1 — Kontrola požadavků
// ═══════════════════════════════════════════════════════════════════════
$checks = [
    ['PHP ≥ 8.0',        version_compare(PHP_VERSION, '8.0.0', '>='),     PHP_VERSION],
    ['Rozšíření PDO',    extension_loaded('pdo'),                          ''],
    ['PDO MySQL driver', extension_loaded('pdo_mysql'),                    ''],
    ['Rozšíření mbstring',extension_loaded('mbstring'),                    ''],
    ['Zápis do config/', is_writable(__DIR__ . '/config'),                 ''],
];
$reqOk = array_reduce($checks, fn($c, $r) => $c && $r[1], true);

// ═══════════════════════════════════════════════════════════════════════
// KROK 2 — Zpracování formuláře databáze
// ═══════════════════════════════════════════════════════════════════════
$dbConfig  = [];
$dbOk      = false;
$dbMessage = '';

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbConfig = [
        'host'   => trim($_POST['db_host']   ?? 'localhost'),
        'port'   => trim($_POST['db_port']   ?? '3306'),
        'name'   => trim($_POST['db_name']   ?? 'typemaster'),
        'user'   => trim($_POST['db_user']   ?? ''),
        'pass'   => $_POST['db_pass']        ?? '',
        'prefix' => trim($_POST['base_url']  ?? ''),
    ];

    // Otestuj připojení
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $dbOk = true;
        $dbMessage = '✔ Připojení k databázi úspěšné!';
    } catch (PDOException $e) {
        $errors[]  = 'Připojení selhalo: ' . $e->getMessage();
        $dbMessage = '✘ ' . $e->getMessage();
    }
}

// ═══════════════════════════════════════════════════════════════════════
// KROK 3 — Instalace
// ═══════════════════════════════════════════════════════════════════════
$installLog = [];
$installOk  = false;

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Načti DB config z hidden polí
    $dbConfig = [
        'host'   => $_POST['db_host']   ?? 'localhost',
        'port'   => $_POST['db_port']   ?? '3306',
        'name'   => $_POST['db_name']   ?? 'typemaster',
        'user'   => $_POST['db_user']   ?? '',
        'pass'   => $_POST['db_pass']   ?? '',
        'prefix' => $_POST['base_url']  ?? '',
    ];
    $adminUsername    = trim($_POST['admin_username']    ?? '');
    $adminDisplayName = trim($_POST['admin_display']     ?? '');
    $adminPassword    = $_POST['admin_password']         ?? '';
    $adminPassword2   = $_POST['admin_password2']        ?? '';

    // Validace
    if (!$adminUsername)              $errors[] = 'Zadej přihlašovací jméno admina.';
    if (strlen($adminPassword) < 6)   $errors[] = 'Heslo musí mít alespoň 6 znaků.';
    if ($adminPassword !== $adminPassword2) $errors[] = 'Hesla se neshodují.';

    if (empty($errors)) {
        try {
            // 1. Připoj se a vytvoř databázi
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbConfig['name']}`");
            $installLog[] = ['✔', "Databáze „{$dbConfig['name']}\" vytvořena / existuje."];

            // 2. Vytvoř tabulky
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            // Spusť SQL příkazy jeden po jednom
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
                if ($sql) $pdo->exec($sql);
            }
            $installLog[] = ['✔', 'Tabulky (users, game_sessions, achievements) vytvořeny.'];

            // 3. Vytvoř admin účet
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $dname = $adminDisplayName ?: $adminUsername;
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, is_admin) VALUES (?,?,?,1)');
            $stmt->execute([$adminUsername, $hash, $dname]);
            $installLog[] = ['✔', "Admin účet „$adminUsername" vytvořen."];

            // 4. Zapiš config/db.php
            $dbPhp = "<?php\n// Databázové připojení — generováno instalačním průvodcem\nfunction getDB(): PDO {\n    static \$pdo;\n    if (\$pdo) return \$pdo;\n    \$dsn = 'mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4';\n    \$pdo = new PDO(\$dsn, " . var_export($dbConfig['user'], true) . ", " . var_export($dbConfig['pass'], true) . ", [\n        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n    ]);\n    return \$pdo;\n}\n";
            file_put_contents(__DIR__ . '/config/db.php', $dbPhp);
            $installLog[] = ['✔', 'Soubor config/db.php zapsán.'];

            // 5. Zapiš config/app.php
            $baseUrl = rtrim($dbConfig['prefix'], '/');
            $appPhp  = "<?php\n// Aplikační konfigurace — generováno instalačním průvodcem\ndefine('BASE_URL', " . var_export($baseUrl, true) . ");\ndefine('APP_NAME', 'TypeMaster');\ndefine('APP_VERSION', '2.0');\n";
            file_put_contents(__DIR__ . '/config/app.php', $appPhp);
            $installLog[] = ['✔', "Soubor config/app.php zapsán (BASE_URL = \"$baseUrl\")."];

            // 6. Napiš zámek
            file_put_contents(__DIR__ . '/config/installed.lock', date('Y-m-d H:i:s') . ' - installed by wizard');
            $installLog[] = ['✔', 'Zámek installed.lock vytvořen.'];

            $installOk = true;
            $installLog[] = ['🎉', 'Instalace dokončena!'];

        } catch (Exception $e) {
            $installLog[] = ['✘', 'Chyba: ' . $e->getMessage()];
            $errors[]      = $e->getMessage();
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// HTML
// ═══════════════════════════════════════════════════════════════════════
?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TypeMaster — Instalace</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:      #0d1b2e; --bg2: #112240; --bg3: #162d4f;
    --text:    #e4e4f0; --muted: #8892a4; --border: #1e3a5f;
    --accent:  #4ade80; --accent2: #60a5fa;
    --danger:  #f87171; --warn: #fb923c;
    --mono:    'Space Mono', 'Courier New', monospace;
    --sans:    'IBM Plex Sans', system-ui, sans-serif;
    --radius:  10px;
}
body { background: var(--bg); color: var(--text); font-family: var(--sans); min-height: 100vh; }
.page { max-width: 680px; margin: 0 auto; padding: 3rem 1.5rem; }
.logo { text-align: center; margin-bottom: 2.5rem; }
.logo-icon { font-size: 3rem; }
.logo-title { font-family: var(--mono); font-size: 1.8rem; color: var(--accent); margin-top: .5rem; }
.logo-sub { color: var(--muted); font-size: .95rem; margin-top: .3rem; }
/* Stepper */
.stepper { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 2.5rem; }
.step-item { display: flex; align-items: center; gap: .5rem; }
.step-num {
    width: 2rem; height: 2rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--mono); font-size: .85rem; font-weight: 700;
    background: var(--bg3); border: 2px solid var(--border); color: var(--muted);
    transition: all .3s;
}
.step-num.active  { background: var(--accent2); border-color: var(--accent2); color: #000; }
.step-num.done    { background: var(--accent);  border-color: var(--accent);  color: #000; }
.step-label { font-size: .8rem; color: var(--muted); }
.step-label.active { color: var(--text); }
.step-line { width: 40px; height: 2px; background: var(--border); margin: 0 .5rem; }
/* Card */
.card { background: var(--bg2); border: 1px solid var(--border); border-radius: 14px; padding: 2rem; }
.card-title { font-family: var(--mono); font-size: 1.15rem; margin-bottom: 1.5rem; }
/* Form */
.field { margin-bottom: 1.1rem; }
.field label { display: block; font-size: .8rem; color: var(--muted); font-family: var(--mono); margin-bottom: .35rem; }
.field input[type=text], .field input[type=password], .field input[type=number] {
    width: 100%; padding: .7rem 1rem; background: var(--bg3);
    border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--text); font-size: .95rem; font-family: var(--sans);
    transition: border-color .2s;
}
.field input:focus { border-color: var(--accent2); outline: none; }
.field .hint { font-size: .75rem; color: var(--muted); margin-top: .3rem; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
/* Buttons */
.btn-primary {
    display: inline-block; padding: .8rem 2rem; background: var(--accent2);
    color: #000; border: none; border-radius: var(--radius); font-family: var(--mono);
    font-size: .95rem; font-weight: 700; cursor: pointer; text-decoration: none;
    transition: opacity .2s;
}
.btn-primary:hover { opacity: .85; }
.btn-secondary {
    display: inline-block; padding: .8rem 1.5rem; background: transparent;
    color: var(--muted); border: 1px solid var(--border); border-radius: var(--radius);
    font-family: var(--mono); font-size: .95rem; cursor: pointer; text-decoration: none;
    transition: border-color .2s, color .2s;
}
.btn-secondary:hover { border-color: var(--text); color: var(--text); }
.btn-row { display: flex; gap: 1rem; align-items: center; margin-top: 1.5rem; }
/* Checks */
.check-list { list-style: none; display: flex; flex-direction: column; gap: .6rem; }
.check-item { display: flex; align-items: center; gap: .75rem; font-size: .9rem; }
.check-icon { font-size: 1rem; width: 1.25rem; text-align: center; }
.check-ok   .check-icon { color: var(--accent); }
.check-fail .check-icon { color: var(--danger); }
.check-name { color: var(--muted); font-family: var(--mono); font-size: .8rem; }
.check-val  { margin-left: auto; font-family: var(--mono); font-size: .75rem; color: var(--muted); }
/* Alerts */
.alert { padding: .85rem 1.1rem; border-radius: var(--radius); margin-bottom: 1.25rem; font-size: .9rem; }
.alert-err  { background: rgba(248,113,113,.1); border: 1px solid var(--danger); color: var(--danger); }
.alert-ok   { background: rgba(74,222,128,.1);  border: 1px solid var(--accent); color: var(--accent); }
.alert-info { background: rgba(96,165,250,.1);  border: 1px solid var(--accent2); color: var(--accent2); }
/* Log */
.install-log { list-style: none; display: flex; flex-direction: column; gap: .5rem; margin: 1rem 0; }
.install-log li { font-family: var(--mono); font-size: .85rem; display: flex; gap: .75rem; }
.log-icon { width: 1.25rem; }
.log-icon.ok   { color: var(--accent); }
.log-icon.err  { color: var(--danger); }
.log-icon.yay  { color: #fbbf24; }
/* Section divider */
.section-divider { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
</style>
</head>
<body>
<div class="page">

<div class="logo">
    <div class="logo-icon">⌨</div>
    <div class="logo-title">TypeMaster</div>
    <div class="logo-sub">Instalační průvodce</div>
</div>

<!-- Stepper -->
<div class="stepper">
    <?php
    $steps = ['Požadavky', 'Databáze', 'Instalace', 'Hotovo'];
    foreach ($steps as $i => $label):
        $n = $i + 1;
        $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
        $icon = $n < $step ? '✔' : $n;
    ?>
    <?php if ($i > 0): ?><div class="step-line"></div><?php endif; ?>
    <div class="step-item">
        <div class="step-num <?= $cls ?>"><?= $icon ?></div>
        <div class="step-label <?= $cls === 'active' ? 'active' : '' ?>"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-err">
    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
</div>
<?php endif; ?>

<!-- ══ KROK 1: Požadavky ══════════════════════════════════════════════════ -->
<?php if ($step === 1): ?>
<div class="card">
    <div class="card-title">🔍 Kontrola požadavků</div>
    <ul class="check-list">
        <?php foreach ($checks as [$name, $pass, $val]): ?>
        <li class="check-item <?= $pass ? 'check-ok' : 'check-fail' ?>">
            <span class="check-icon"><?= $pass ? '✔' : '✘' ?></span>
            <span><?= $name ?></span>
            <?php if ($val): ?><span class="check-val"><?= htmlspecialchars($val) ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <hr class="section-divider">

    <?php if ($reqOk): ?>
    <div class="alert alert-ok">✔ Všechny požadavky splněny. Pokračuj dále.</div>
    <a href="?step=2" class="btn-primary">Pokračovat →</a>
    <?php else: ?>
    <div class="alert alert-err">✘ Nesplněné požadavky. Oprav je před pokračováním.</div>
    <?php endif; ?>
</div>

<!-- ══ KROK 2: Databáze ═══════════════════════════════════════════════════ -->
<?php elseif ($step === 2): ?>
<div class="card">
    <div class="card-title">🗄 Nastavení databáze</div>
    <form method="post" action="?step=2">
        <div class="field-row">
            <div class="field">
                <label>Hostname databáze</label>
                <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                <div class="hint">Obvykle <code>localhost</code> nebo IP</div>
            </div>
            <div class="field">
                <label>Port</label>
                <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required>
                <div class="hint">Výchozí MySQL port: 3306</div>
            </div>
        </div>
        <div class="field">
            <label>Název databáze</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'typemaster') ?>" required>
            <div class="hint">Databáze bude vytvořena automaticky (MySQL uživatel musí mít právo CREATE DATABASE)</div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Uživatel MySQL</label>
                <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required autocomplete="username">
            </div>
            <div class="field">
                <label>Heslo MySQL</label>
                <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" autocomplete="current-password">
                <div class="hint">Může být prázdné</div>
            </div>
        </div>
        <hr class="section-divider">
        <div class="field">
            <label>BASE_URL — URL cesta k aplikaci</label>
            <input type="text" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? '') ?>" placeholder="např. /games nebo /typemaster nebo prázdné">
            <div class="hint">
                Příklady: <code>/games</code> → <code>http://localhost/games</code> &nbsp;|&nbsp;
                prázdné → <code>http://localhost</code> &nbsp;|&nbsp;
                <code>/typemaster</code> → <code>https://web.cz/typemaster</code>
            </div>
        </div>

        <?php if ($dbOk): ?>
        <div class="alert alert-ok"><?= htmlspecialchars($dbMessage) ?></div>
        <div class="btn-row">
            <button type="submit" class="btn-secondary">Otestovat znovu</button>
            <button type="submit" formaction="?step=3" class="btn-primary">Pokračovat →</button>
        </div>
        <?php else: ?>
        <div class="btn-row">
            <a href="?step=1" class="btn-secondary">← Zpět</a>
            <button type="submit" class="btn-primary">Otestovat připojení</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ══ KROK 3: Instalace ══════════════════════════════════════════════════ -->
<?php elseif ($step === 3): ?>
<div class="card">
    <div class="card-title">🚀 Instalace a vytvoření admin účtu</div>

    <?php if ($installOk): ?>
    <!-- Úspěch — přesměruj na krok 4 -->
    <ul class="install-log">
        <?php foreach ($installLog as [$icon, $msg]): ?>
        <li>
            <span class="log-icon <?= $icon === '✔' ? 'ok' : ($icon === '✘' ? 'err' : 'yay') ?>"><?= $icon ?></span>
            <span><?= htmlspecialchars($msg) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="alert alert-ok" style="margin-top:1rem">🎉 Instalace byla úspěšně dokončena!</div>
    <a href="?step=4" class="btn-primary" style="margin-top:.5rem">Zobrazit výsledek →</a>

    <?php else: ?>

    <?php if (!empty($installLog)): ?>
    <ul class="install-log" style="margin-bottom:1rem">
        <?php foreach ($installLog as [$icon, $msg]): ?>
        <li>
            <span class="log-icon <?= $icon === '✔' ? 'ok' : 'err' ?>"><?= $icon ?></span>
            <span><?= htmlspecialchars($msg) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <form method="post" action="?step=3">
        <!-- Předej DB config skrytými poli -->
        <?php foreach (['db_host','db_port','db_name','db_user','db_pass','base_url'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_POST[$k] ?? '') ?>">
        <?php endforeach; ?>

        <div class="alert alert-info">
            📦 Vytvoří se databáze, tabulky a tvůj admin účet. Formulář stačí odeslat jednou.
        </div>

        <div class="field-row">
            <div class="field">
                <label>Přihlašovací jméno admina *</label>
                <input type="text" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? 'admin') ?>" required autocomplete="off">
            </div>
            <div class="field">
                <label>Zobrazované jméno</label>
                <input type="text" name="admin_display" value="<?= htmlspecialchars($_POST['admin_display'] ?? '') ?>" placeholder="Admin">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Heslo admina * (min. 6 znaků)</label>
                <input type="password" name="admin_password" required autocomplete="new-password">
            </div>
            <div class="field">
                <label>Heslo znovu *</label>
                <input type="password" name="admin_password2" required autocomplete="new-password">
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=2" class="btn-secondary">← Zpět</a>
            <button type="submit" class="btn-primary">🚀 Instalovat</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- ══ KROK 4: Hotovo ════════════════════════════════════════════════════ -->
<?php elseif ($step === 4): ?>
<div class="card" style="text-align:center">
    <div style="font-size:3.5rem;margin-bottom:1rem">🎉</div>
    <div class="card-title" style="font-size:1.4rem;color:var(--accent)">TypeMaster je nainstalován!</div>
    <p style="color:var(--muted);margin-bottom:1.5rem;line-height:1.7">
        Databáze, tabulky i admin účet byly úspěšně vytvořeny.<br>
        Soubory konfigurace byly zapsány automaticky.
    </p>

    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem;text-align:left">
        <div style="font-family:var(--mono);font-size:.8rem;color:var(--muted);margin-bottom:.75rem">📋 BEZPEČNOSTNÍ CHECKLIST</div>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.6rem;font-size:.875rem">
            <li>☑ Smaž nebo přejmenuj soubor <code style="color:var(--accent2)">install.php</code> ze serveru</li>
            <li>☑ Soubor <code style="color:var(--accent2)">config/installed.lock</code> chrání před opakovanou instalací</li>
            <li>☑ Přihlašovací údaje k DB jsou uloženy v <code style="color:var(--accent2)">config/db.php</code></li>
            <li>☑ Přidej <code style="color:var(--accent2)">.htaccess</code> ochranu pro složku <code style="color:var(--accent2)">config/</code></li>
        </ul>
    </div>

    <a href="index.php" class="btn-primary" style="font-size:1.05rem;padding:1rem 2.5rem">
        → Přejít na přihlášení
    </a>
</div>
<?php endif; ?>

</div><!-- /page -->
</body>
</html>
