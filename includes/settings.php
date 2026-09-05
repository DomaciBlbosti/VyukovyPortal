<?php
/**
 * Nastavení aplikace uložená v databázi.
 *
 * Věci, které se liší instalaci od instalace a nemají co dělat v Gitu —
 * adresa Ollamy, klíč k API, volba modelu. Když hodnota v databázi není,
 * bere se stejnojmenná proměnná prostředí a teprve pak výchozí hodnota,
 * takže se dá všechno předvyplnit i z docker-compose.
 */
require_once __DIR__ . '/../config/db.php';

/**
 * Načtená nastavení. Držíme je pohromadě, ať se kvůli pár hodnotám nechodí
 * do databáze pokaždé znovu; zápis mezipaměť zahodí, aby se změna hned projevila.
 */
function &settingsCache(): ?array {
    static $cache = null;
    return $cache;
}

/** Hodnota nastavení; když není v databázi, bere se env proměnná a pak výchozí */
function getSetting(string $key, string $default = ''): string {
    $cache = &settingsCache();
    if ($cache === null) {
        $cache = [];
        try {
            foreach (getDB()->query('SELECT setting_key, setting_value FROM app_settings') as $r) {
                $cache[$r['setting_key']] = (string)$r['setting_value'];
            }
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    if (isset($cache[$key]) && $cache[$key] !== '') return $cache[$key];

    $env = getenv(strtoupper($key));
    if ($env !== false && $env !== '') return $env;

    return $default;
}

/** Uloží nastavení (dvoukrokově, ať dotaz nezávisí na SQL dialektu) */
function setSetting(string $key, string $value): bool {
    try {
        $db   = getDB();
        $now  = date('Y-m-d H:i:s');
        $find = $db->prepare('SELECT setting_key FROM app_settings WHERE setting_key = ?');
        $find->execute([$key]);
        if ($find->fetch()) {
            $db->prepare('UPDATE app_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?')
               ->execute([$value, $now, $key]);
        } else {
            $db->prepare('INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?,?,?)')
               ->execute([$key, $value, $now]);
        }
        $cache = &settingsCache();
        $cache = null;   // ať se změna projeví hned ve stejném požadavku
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Zamaskovaný klíč pro zobrazení („sk-…a1b2").
 *
 * Klíč se do stránky nikdy nevypisuje celý — kdo se dostane k HTML, dostane
 * se i ke klíči, a tomu se dá snadno předejít.
 */
function maskedSecret(string $value): string {
    if ($value === '') return '';
    return mb_strlen($value) <= 8
        ? str_repeat('•', mb_strlen($value))
        : mb_substr($value, 0, 3) . '…' . mb_substr($value, -4);
}
