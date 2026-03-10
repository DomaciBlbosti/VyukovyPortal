<?php
/**
 * TypeMaster -- Instalacni pruvodce
 */

if (file_exists(__DIR__ . '/config/installed.lock')) {
    die('<div style="font-family:monospace;text-align:center;padding:4rem;color:#f87171;background:#0d1b2e;min-height:100vh">'
      . '<h1>TypeMaster je jiz nainstalovan.</h1>'
      . '<p style="color:#94a3b8">Smaz soubor <code>config/installed.lock</code> pro opakovani.</p>'
      . '<a href="index.php" style="color:#4ade80">Prihlaseni</a></div>');
}

$step   = intval($_GET['step'] ?? 1);
$errors = [];

// ── Krok 1: pozadavky ────────────────────────────────────────────────────
$checks = [
    ['PHP >= 8.0',         version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
    ['PDO',                extension_loaded('pdo'),                       ''],
    ['pdo_mysql',          extension_loaded('pdo_mysql'),                 ''],
    ['mbstring',           extension_loaded('mbstring'),                  ''],
    ['config/ zapisatelna',is_writable(__DIR__ . '/config'),              ''],
];
$reqOk = array_reduce($checks, fn($c, $r) => $c && $r[1], true);

// ── Krok 2: test DB ──────────────────────────────────────────────────────
$dbOk = false; $dbMessage = '';
// ── Auto-detekce BASE_URL ────────────────────────────────────────────────
// install.php je v kořeni aplikace, takže dirname(SCRIPT_NAME) = cesta k aplikaci
$autoBaseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$dbConfig = [
    'host'   => $_POST['db_host']   ?? 'localhost',
    'port'   => $_POST['db_port']   ?? '3306',
    'name'   => $_POST['db_name']   ?? 'typemaster',
    'user'   => $_POST['db_user']   ?? '',
    'pass'   => $_POST['db_pass']   ?? '',
    'prefix' => $_POST['base_url']  ?? $autoBaseUrl,
];

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $dbOk      = true;
        $dbMessage = 'Pripojeni k databazi uspesne!';
    } catch (PDOException $e) {
        $errors[]  = 'Pripojeni selhalo: ' . $e->getMessage();
        $dbMessage = $e->getMessage();
    }
}

// ── Krok 3: instalace ────────────────────────────────────────────────────
$installLog = [];
$installOk  = false;

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUsername    = trim($_POST['admin_username']  ?? '');
    $adminDisplayName = trim($_POST['admin_display']   ?? '');
    $adminPassword    = $_POST['admin_password']       ?? '';
    $adminPassword2   = $_POST['admin_password2']      ?? '';

    if (!$adminUsername)                    $errors[] = 'Zadej jmeno admina.';
    if (strlen($adminPassword) < 6)         $errors[] = 'Heslo musi mit alespon 6 znaku.';
    if ($adminPassword !== $adminPassword2) $errors[] = 'Hesla se neshoduji.';

    if (empty($errors)) {
        try {
            // 1. Pripojeni + vytvoreni DB
            $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbConfig['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $dbConfig['name'] . '`');
            $installLog[] = ['ok', 'Databaze ' . $dbConfig['name'] . ' vytvorena / existuje.'];

            // 2. Tabulky
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
                if ($sql) $pdo->exec($sql);
            }
            $installLog[] = ['ok', 'Tabulky vytvoreny (users, game_sessions, achievements).'];

            // 3. Admin ucet
            $dname = $adminDisplayName ?: $adminUsername;
            $pdo->prepare('INSERT INTO users (username, password_hash, display_name, is_admin) VALUES (?,?,?,1)')
                ->execute([$adminUsername, password_hash($adminPassword, PASSWORD_BCRYPT), $dname]);
            $installLog[] = ['ok', 'Admin ucet ' . $adminUsername . ' vytvoren.'];

            // 4. config/db.php
            $host   = $dbConfig['host'];
            $port   = $dbConfig['port'];
            $dbname = $dbConfig['name'];
            $user   = var_export($dbConfig['user'], true);
            $pass   = var_export($dbConfig['pass'], true);
            $dbPhp  = "<?php\nfunction getDB(): PDO {\n    static \$pdo;\n    if (\$pdo) return \$pdo;\n    \$pdo = new PDO('mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4', $user, $pass, [\n        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n    ]);\n    return \$pdo;\n}\n";
            file_put_contents(__DIR__ . '/config/db.php', $dbPhp);
            $installLog[] = ['ok', 'config/db.php zapsan.'];

            // 5. config/app.php
            $baseUrl = rtrim($dbConfig['prefix'], '/');
            $bue     = var_export($baseUrl, true);
            $appPhp  = "<?php\ndefine('BASE_URL', $bue);\ndefine('APP_NAME', 'TypeMaster');\ndefine('APP_VERSION', '2.0');\n";
            file_put_contents(__DIR__ . '/config/app.php', $appPhp);
            $installLog[] = ['ok', 'config/app.php zapsan (BASE_URL="' . $baseUrl . '").'];
            // Ověření zápisu
            $written = file_get_contents(__DIR__ . '/config/app.php');
            if (strpos($written, $bue) === false) {
                throw new Exception('config/app.php nebyl zapsan spravne! Zkontroluj prava ke slozce config/');
            }

            // 6. Zamek
            file_put_contents(__DIR__ . '/config/installed.lock', date('Y-m-d H:i:s') . ' - installed');
            $installLog[] = ['ok', 'installed.lock vytvoren.'];

            $installOk    = true;
            $installLog[] = ['yay', 'Instalace dokoncena!'];

        } catch (Exception $e) {
            $installLog[] = ['err', 'Chyba: ' . $e->getMessage()];
            $errors[]      = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TypeMaster &mdash; Instalace</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d1b2e;--bg2:#112240;--bg3:#162d4f;
  --text:#e4e4f0;--muted:#8892a4;--border:#1e3a5f;
  --green:#4ade80;--blue:#60a5fa;--red:#f87171;--orange:#fb923c;--gold:#fbbf24;
  --mono:'Space Mono','Courier New',monospace;
  --sans:'IBM Plex Sans',system-ui,sans-serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh}
.page{max-width:660px;margin:0 auto;padding:3rem 1.5rem}
.logo{text-align:center;margin-bottom:2.5rem}
.logo-icon{font-size:3rem}
.logo-title{font-family:var(--mono);font-size:1.8rem;color:var(--green);margin-top:.5rem}
.logo-sub{color:var(--muted);font-size:.9rem;margin-top:.3rem}
/* Stepper */
.stepper{display:flex;align-items:center;justify-content:center;margin-bottom:2.5rem;flex-wrap:wrap;gap:.25rem}
.snum{width:2rem;height:2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-family:var(--mono);font-size:.8rem;font-weight:700;
  background:var(--bg3);border:2px solid var(--border);color:var(--muted)}
.snum.active{background:var(--blue);border-color:var(--blue);color:#000}
.snum.done{background:var(--green);border-color:var(--green);color:#000}
.slabel{font-size:.75rem;color:var(--muted);margin:0 .3rem}
.slabel.active{color:var(--text)}
.sline{width:30px;height:2px;background:var(--border)}
/* Card */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:2rem;margin-bottom:1.5rem}
.card-title{font-family:var(--mono);font-size:1.1rem;margin-bottom:1.5rem;color:var(--text)}
/* Form */
.field{margin-bottom:1rem}
.field label{display:block;font-size:.78rem;color:var(--muted);font-family:var(--mono);margin-bottom:.3rem}
.field input{width:100%;padding:.65rem 1rem;background:var(--bg3);border:1px solid var(--border);
  border-radius:8px;color:var(--text);font-size:.95rem;transition:border-color .2s}
.field input:focus{border-color:var(--blue);outline:none}
.field .hint{font-size:.73rem;color:var(--muted);margin-top:.25rem}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
/* Buttons */
.btn{display:inline-block;padding:.75rem 1.75rem;border-radius:8px;font-family:var(--mono);
  font-size:.9rem;font-weight:700;cursor:pointer;border:none;text-decoration:none;transition:opacity .2s}
.btn:hover{opacity:.85}
.btn-primary{background:var(--blue);color:#000}
.btn-secondary{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn-row{display:flex;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap}
/* Checks */
.checks{list-style:none;display:flex;flex-direction:column;gap:.6rem}
.checks li{display:flex;align-items:center;gap:.6rem;font-size:.88rem}
.ci{width:1.2rem;text-align:center}
.ci.ok{color:var(--green)} .ci.fail{color:var(--red)}
.cv{margin-left:auto;font-family:var(--mono);font-size:.72rem;color:var(--muted)}
/* Alerts */
.alert{padding:.8rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.88rem}
.alert-err {background:rgba(248,113,113,.1);border:1px solid var(--red);  color:var(--red)}
.alert-ok  {background:rgba(74,222,128,.1); border:1px solid var(--green);color:var(--green)}
.alert-info{background:rgba(96,165,250,.1); border:1px solid var(--blue); color:var(--blue)}
hr{border:none;border-top:1px solid var(--border);margin:1.25rem 0}
/* Log */
.log{list-style:none;display:flex;flex-direction:column;gap:.45rem;margin:1rem 0}
.log li{display:flex;gap:.6rem;font-family:var(--mono);font-size:.82rem}
.li-ok {color:var(--green)} .li-err{color:var(--red)} .li-yay{color:var(--gold)}
/* Checklist */
.checklist{list-style:none;display:flex;flex-direction:column;gap:.6rem;font-size:.85rem}
.checklist li::before{content:'☑  ';color:var(--green)}
code{background:var(--bg3);padding:.1rem .35rem;border-radius:4px;font-size:.82rem;color:var(--blue)}
.center{text-align:center}
</style>
</head>
<body>
<div class="page">

<div class="logo">
  <div class="logo-icon">&#9000;</div>
  <div class="logo-title">TypeMaster</div>
  <div class="logo-sub">Instalacni pruvodce</div>
</div>

<!-- Stepper -->
<div class="stepper">
<?php
$stepLabels = ['Pozadavky','Databaze','Instalace','Hotovo'];
foreach ($stepLabels as $i => $lbl):
    $n   = $i + 1;
    $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
    $ico = $n < $step ? '&#10004;' : $n;
?>
<?php if ($i > 0): ?><div class="sline"></div><?php endif; ?>
<div class="snum <?= $cls ?>"><?= $ico ?></div>
<div class="slabel <?= $cls === 'active' ? 'active' : '' ?>"><?= $lbl ?></div>
<?php endforeach; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-err"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
<!-- ── KROK 1 ── -->
<div class="card">
  <div class="card-title">&#128269; Kontrola pozadavku</div>
  <ul class="checks">
  <?php foreach ($checks as [$name, $pass, $val]): ?>
    <li>
      <span class="ci <?= $pass ? 'ok' : 'fail' ?>"><?= $pass ? '&#10004;' : '&#10008;' ?></span>
      <span><?= htmlspecialchars($name) ?></span>
      <?php if ($val): ?><span class="cv"><?= htmlspecialchars($val) ?></span><?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
  <hr>
  <?php if ($reqOk): ?>
    <div class="alert alert-ok">Vsechny pozadavky splneny.</div>
    <a href="?step=2" class="btn btn-primary">Pokracovat &rarr;</a>
  <?php else: ?>
    <div class="alert alert-err">Nesplnene pozadavky. Oprav je pred pokracovanim.</div>
  <?php endif; ?>
</div>

<?php elseif ($step === 2): ?>
<!-- ── KROK 2 ── -->
<div class="card">
  <div class="card-title">&#128451; Nastaveni databaze</div>
  <form method="post" action="?step=2">
    <div class="frow">
      <div class="field">
        <label>Hostname</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($dbConfig['host']) ?>" required>
        <div class="hint">Obvykle localhost</div>
      </div>
      <div class="field">
        <label>Port</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars($dbConfig['port']) ?>" required>
        <div class="hint">Vychozi: 3306</div>
      </div>
    </div>
    <div class="field">
      <label>Nazev databaze</label>
      <input type="text" name="db_name" value="<?= htmlspecialchars($dbConfig['name']) ?>" required>
      <div class="hint">Databaze bude vytvorena automaticky</div>
    </div>
    <div class="frow">
      <div class="field">
        <label>Uzivatel MySQL</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($dbConfig['user']) ?>" required autocomplete="username">
      </div>
      <div class="field">
        <label>Heslo MySQL</label>
        <input type="password" name="db_pass" value="<?= htmlspecialchars($dbConfig['pass']) ?>" autocomplete="current-password">
        <div class="hint">Muze byt prazdne</div>
      </div>
    </div>
    <hr>
    <div class="field">
      <label>BASE_URL &mdash; cesta k aplikaci</label>
      <input type="text" name="base_url" id="baseUrlInput"
             value="<?= htmlspecialchars($dbConfig['prefix']) ?>"
             placeholder="napr. /games">
      <div class="hint">
        Auto-detekovano: <code id="autoUrl"><?= htmlspecialchars($autoBaseUrl ?: '/') ?></code>
        &nbsp;&rarr;&nbsp;
        Aplikace bude dostupna na:
        <code id="previewUrl"><?= htmlspecialchars((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].($dbConfig['prefix'] ?: $autoBaseUrl).'/') ?></code>
      </div>
    </div>
    <script>
    document.getElementById('baseUrlInput').addEventListener('input', function() {
        var host = '<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>';
        document.getElementById('previewUrl').textContent = host + (this.value || '/') + '/';
    });
    </script>
    <?php if ($dbOk): ?>
      <div class="alert alert-ok">&#10004; <?= htmlspecialchars($dbMessage) ?></div>
      <div class="btn-row">
        <button type="submit" class="btn btn-secondary">Otestovat znovu</button>
        <button type="submit" formaction="?step=3" class="btn btn-primary">Pokracovat &rarr;</button>
      </div>
    <?php else: ?>
      <div class="btn-row">
        <a href="?step=1" class="btn btn-secondary">&larr; Zpet</a>
        <button type="submit" class="btn btn-primary">Otestovat pripojeni</button>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php elseif ($step === 3): ?>
<!-- ── KROK 3 ── -->
<div class="card">
  <div class="card-title">&#128640; Instalace a admin ucet</div>

  <?php if ($installOk): ?>
    <ul class="log">
    <?php foreach ($installLog as [$t, $m]): ?>
      <li><span class="li-<?= $t ?>"><?= $t === 'ok' ? '&#10004;' : ($t === 'yay' ? '&#127881;' : '&#10008;') ?></span><span><?= htmlspecialchars($m) ?></span></li>
    <?php endforeach; ?>
    </ul>
    <div class="alert alert-ok" style="margin-top:1rem">&#127881; Instalace byla uspesne dokoncena!</div>
    <a href="?step=4" class="btn btn-primary" style="margin-top:.5rem">Zobrazit vysledek &rarr;</a>

  <?php else: ?>
    <?php if (!empty($installLog)): ?>
    <ul class="log">
    <?php foreach ($installLog as [$t, $m]): ?>
      <li><span class="li-<?= $t ?>"><?= $t === 'ok' ? '&#10004;' : '&#10008;' ?></span><span><?= htmlspecialchars($m) ?></span></li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <form method="post" action="?step=3">
      <?php
      $hiddenMap = [
          'db_host'  => $dbConfig['host'],
          'db_port'  => $dbConfig['port'],
          'db_name'  => $dbConfig['name'],
          'db_user'  => $dbConfig['user'],
          'db_pass'  => $dbConfig['pass'],
          'base_url' => $dbConfig['prefix'],
      ];
      foreach ($hiddenMap as $k => $v): ?>
      <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>

      <div class="alert alert-info">&#128230; Vytvori se databaze, tabulky a tvuj admin ucet.</div>

      <div class="frow">
        <div class="field">
          <label>Prihlasovaci jmeno admina *</label>
          <input type="text" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? 'admin') ?>" required autocomplete="off">
        </div>
        <div class="field">
          <label>Zobrazovane jmeno</label>
          <input type="text" name="admin_display" value="<?= htmlspecialchars($_POST['admin_display'] ?? '') ?>" placeholder="Admin">
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Heslo admina * (min. 6 znaku)</label>
          <input type="password" name="admin_password" required autocomplete="new-password">
        </div>
        <div class="field">
          <label>Heslo znovu *</label>
          <input type="password" name="admin_password2" required autocomplete="new-password">
        </div>
      </div>
      <div class="btn-row">
        <a href="?step=2" class="btn btn-secondary">&larr; Zpet</a>
        <button type="submit" class="btn btn-primary">&#128640; Instalovat</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php elseif ($step === 4): ?>
<!-- ── KROK 4 ── -->
<div class="card center">
  <div style="font-size:3.5rem;margin-bottom:1rem">&#127881;</div>
  <div class="card-title" style="font-size:1.4rem;color:var(--green);justify-content:center;display:flex">TypeMaster je nainstalovan!</div>
  <p style="color:var(--muted);margin-bottom:1.5rem;line-height:1.7">
    Databaze, tabulky i admin ucet byly uspesne vytvoreny.<br>
    Konfiguraci soubory byly zapsany automaticky.
  </p>
  <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:1.25rem;margin-bottom:1.5rem;text-align:left">
    <div style="font-family:var(--mono);font-size:.75rem;color:var(--muted);margin-bottom:.75rem">BEZPECNOSTNI CHECKLIST</div>
    <ul class="checklist">
      <li>Smaz nebo prejmenuj <code>install.php</code> ze serveru</li>
      <li>Slozka <code>config/</code> je chranena <code>.htaccess</code></li>
      <li><code>config/installed.lock</code> brani opakovane instalaci</li>
    </ul>
  </div>
  <a href="index.php" class="btn btn-primary" style="font-size:1rem;padding:1rem 2.5rem">
    &rarr; Prihlaseni
  </a>
</div>

<?php endif; ?>

</div>
</body>
</html>
