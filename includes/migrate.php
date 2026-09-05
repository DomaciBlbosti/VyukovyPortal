<?php
/**
 * Idempotentní databázové migrace.
 * Volá se při startu kontejneru (docker/init-db.php) i po ruční
 * aktualizaci z Gitu (admin/system.php), aby schéma vždy odpovídalo kódu.
 */
require_once __DIR__ . '/levels.php';

/** Spustí všechny migrace. Vrací seznam provedených kroků. */
function runMigrations(PDO $db): array {
    $done = [];

    // 1. Tabulky ze schema.sql (CREATE TABLE IF NOT EXISTS)
    $schema = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../schema.sql'));
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
        $db->exec($sql);
    }

    // 2. game_sessions.points — přibyl s level systémem
    // (dotaz místo SHOW COLUMNS, ať to nezávisí na SQL dialektu)
    $hasPoints = true;
    try {
        $db->query('SELECT points FROM game_sessions LIMIT 1');
    } catch (PDOException $e) {
        $hasPoints = false;
    }
    if (!$hasPoints) {
        $db->exec('ALTER TABLE game_sessions ADD COLUMN points INT NOT NULL DEFAULT 0');
        $done[] = 'game_sessions.points přidán';

        // Dopočítej body u her odehraných před zavedením systému
        $rows = $db->query('SELECT id, game_type, accuracy, chars_typed FROM game_sessions')->fetchAll();
        $stmt = $db->prepare('UPDATE game_sessions SET points = ? WHERE id = ?');
        foreach ($rows as $row) {
            $stmt->execute([calculatePoints($row), $row['id']]);
        }
        if ($rows) $done[] = 'body dopočítány u ' . count($rows) . ' starších her';
    }

    // 3. users.grade — ročník žáka, podle něj se nabízí obtížnost (0 = neuvedeno)
    try {
        $db->query('SELECT grade FROM users LIMIT 1');
    } catch (PDOException $e) {
        $db->exec('ALTER TABLE users ADD COLUMN grade TINYINT NOT NULL DEFAULT 0');
        $done[] = 'users.grade přidán';
    }

    // 4. Výchozí levely (jen do prázdné tabulky — admin si je může přepsat)
    if ((int)$db->query('SELECT COUNT(*) FROM levels')->fetchColumn() === 0) {
        $stmt = $db->prepare('INSERT INTO levels (level_number, points_required, title, icon) VALUES (?,?,?,?)');
        foreach (DEFAULT_LEVELS as $num => [$pts, $title, $icon]) {
            $stmt->execute([$num, $pts, $title, $icon]);
        }
        $done[] = count(DEFAULT_LEVELS) . ' výchozích levelů vloženo';
    }

    // 5. Multiplikátory — doplň chybějící herní typy, existující nech být
    $existing = $db->query('SELECT game_type FROM game_multipliers')->fetchAll(PDO::FETCH_COLUMN);
    $stmt     = $db->prepare('INSERT INTO game_multipliers (game_type, label, multiplier) VALUES (?,?,?)');
    $added    = 0;
    foreach (DEFAULT_MULTIPLIERS as $type => [$label, $mult]) {
        if (!in_array($type, $existing, true)) {
            $stmt->execute([$type, $label, $mult]);
            $added++;
        }
    }
    if ($added) $done[] = "$added multiplikátorů doplněno";

    // 6. Sloupce, které přibyly k už existujícím tabulkám skenování
    //    (CREATE TABLE IF NOT EXISTS je do hotové tabulky sám nedoplní)
    foreach ([
        ['ocr_jobs',  'provider',    "ALTER TABLE ocr_jobs ADD COLUMN provider VARCHAR(20) NOT NULL DEFAULT ''"],
        ['ocr_pages', 'edited_text', 'ALTER TABLE ocr_pages ADD COLUMN edited_text MEDIUMTEXT NULL'],
    ] as [$table, $column, $sql]) {
        try {
            $db->query("SELECT $column FROM $table LIMIT 1");
        } catch (PDOException $e) {
            try {
                $db->exec($sql);
                $done[] = "$table.$column přidán";
            } catch (PDOException $e2) {
                // Tabulka ještě neexistuje (čerstvá instalace) — vznikne ze schema.sql
            }
        }
    }

    // 7. Popisky her — hodnotu multiplikátoru nastavuje admin, název ne,
    //    takže ho smíme srovnat s kódem (čeština se rozrostla za i/y)
    $stmt    = $db->prepare('UPDATE game_multipliers SET label = ? WHERE game_type = ? AND label <> ?');
    $renamed = 0;
    foreach (DEFAULT_MULTIPLIERS as $type => [$label, $mult]) {
        $stmt->execute([$label, $type, $label]);
        $renamed += $stmt->rowCount();
    }
    if ($renamed) $done[] = "popisků her aktualizováno: $renamed";

    return $done;
}
