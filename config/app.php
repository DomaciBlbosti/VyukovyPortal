<?php
// ── Docker / env režim ──────────────────────────────────────────────────
// Když běžíme v kontejneru (TrueNAS), konfigurace přichází z env proměnných
// a instalační průvodce se vůbec nepoužívá.
if (getenv('DB_HOST') !== false) {
    define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));
    define('APP_NAME', getenv('APP_NAME') ?: 'TypeMaster');
    define('APP_VERSION', '2.0');
    return;
}

// ── Klasický hosting: konfigurace zapsaná instalátorem ──────────────────
if (file_exists(__DIR__ . '/app.local.php')) {
    require __DIR__ . '/app.local.php';
    return;
}

// Pokud instalace nebyla dokoncena, presmeruj na install.php
if (!file_exists(__DIR__ . '/installed.lock')) {
    // __DIR__ = /absolutni/cesta/k/games/config
    // Document root z $_SERVER['DOCUMENT_ROOT'] = /absolutni/cesta/k/www
    // Relativni cesta aplikace = rozdil mezi nimi, bez /config na konci
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $appDir  = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $appPath = str_replace($docRoot, '', $appDir); // napr. /games
    $scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    header('Location: ' . $scheme . '://' . $_SERVER['HTTP_HOST'] . $appPath . '/install.php');
    exit;
}

// Fallback pro starší instalace, kde install.php přepsal přímo tento soubor
define('BASE_URL', '/games');
define('APP_NAME', 'TypeMaster');
define('APP_VERSION', '2.0');

// URL statického souboru s verzí (?v=<commit>) — css/js nemají hash ve
// jméně, takže bez toho prohlížeče drží staré verze v cache. Nová verze
// appky = nová URL = vždy čerstvý soubor. (PHP hoistuje deklarace funkcí,
// definice platí i pro větve výše ukončené return.)
function asset_url(string $path): string {
    static $v = null;
    if ($v === null) {
        $root = dirname(__DIR__);
        $head = @file_get_contents($root . '/.git/HEAD');
        if ($head && preg_match('#^ref:\s*(.+)$#m', $head, $m)) {
            $v = substr(trim((string)@file_get_contents($root . '/.git/' . trim($m[1]))), 0, 7);
        } elseif ($head) {
            $v = substr(trim($head), 0, 7);
        }
        if (!$v) $v = (string)(@filemtime($root . $path) ?: APP_VERSION);
    }
    return BASE_URL . $path . '?v=' . $v;
}
