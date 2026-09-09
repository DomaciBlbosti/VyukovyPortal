<?php
/**
 * Idempotentní databázové migrace.
 * Volá se při startu kontejneru (docker/init-db.php) i po ruční
 * aktualizaci z Gitu (admin/system.php), aby schéma vždy odpovídalo kódu.
 */
require_once __DIR__ . '/levels.php';
require_once __DIR__ . '/settings.php';

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
        ['ocr_jobs',  'built_json',  'ALTER TABLE ocr_jobs ADD COLUMN built_json MEDIUMTEXT NULL'],
        ['ocr_jobs',  'built_error', "ALTER TABLE ocr_jobs ADD COLUMN built_error VARCHAR(255) NOT NULL DEFAULT ''"],
        ['ocr_jobs',  'building',    'ALTER TABLE ocr_jobs ADD COLUMN building TINYINT(1) NOT NULL DEFAULT 0'],
        ['ocr_pages', 'thumb_b64',   'ALTER TABLE ocr_pages ADD COLUMN thumb_b64 MEDIUMTEXT NULL'],
        ['ocr_jobs',  'subject',     "ALTER TABLE ocr_jobs ADD COLUMN subject VARCHAR(40) NOT NULL DEFAULT ''"],
        ['ocr_jobs',  'grade',       'ALTER TABLE ocr_jobs ADD COLUMN grade TINYINT NOT NULL DEFAULT 0'],
        ['custom_sets', 'job_id',    'ALTER TABLE custom_sets ADD COLUMN job_id INT NULL'],
    ] as [$table, $column, $sql]) {
        try {
            $db->query("SELECT $column FROM $table LIMIT 1");
        } catch (PDOException $e) {
            try {
                $db->exec($sql);
                $done[] = "$table.$column přidán";
            } catch (PDOException $e2) {
                // Tabulky už ze schema.sql existují, takže tohle je skutečná
                // chyba — musí být vidět, jinak by aplikace padala na
                // chybějícím sloupci a nikdo by nevěděl proč
                $done[] = "$table.$column se nepodařilo přidat: " . $e2->getMessage();
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

    // 8. Přepisy z doby před historií běhů — každá hotová stránka dostane
    //    jeden „starý" běh, ať se v novém rozhraní neztratí a jde ho vybrat
    try {
        $rows = $db->query("SELECT p.id, p.status, p.text, p.error, p.seconds, j.provider
                            FROM ocr_pages p JOIN ocr_jobs j ON j.id = p.job_id
                            WHERE p.status IN ('hotovo', 'chyba')
                              AND NOT EXISTS (SELECT 1 FROM ocr_runs r WHERE r.page_id = p.id)")->fetchAll();
        $ins = $db->prepare('INSERT INTO ocr_runs (page_id, batch, provider, model, prompt_key, prompt, status, text, error, seconds, chosen, created_at)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) {
            $ins->execute([$r['id'], '', (string)$r['provider'], '', 'legacy', null, $r['status'],
                           $r['text'], (string)$r['error'], (int)$r['seconds'],
                           $r['status'] === 'hotovo' ? 1 : 0, date('Y-m-d H:i:s')]);
        }
        if ($rows) $done[] = 'starších přepisů převedeno na běhy: ' . count($rows);
    } catch (PDOException $e) {
        // tabulky skenování ještě nejsou — nic k převedení
    }

    // 9. Bloky k běhům z doby, kdy se rámečky z modelu jen ukládaly do
    //    textu — dopočítají se z něj a text se vyčistí od souřadnic
    try {
        require_once __DIR__ . '/ocr.php';
        $rows = $db->query("SELECT r.id, r.page_id, r.text, r.chosen FROM ocr_runs r
                            WHERE r.status = 'hotovo' AND r.text LIKE '%[[%'
                              AND NOT EXISTS (SELECT 1 FROM ocr_blocks b WHERE b.run_id = r.id)")->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            $blocks = parseOcrBlocks((string)$r['text']);
            if (!$blocks) continue;
            $page = getOcrPage((int)$r['page_id']);
            saveOcrBlocks((int)$r['id'], (int)$r['page_id'], $blocks, (string)($page['image_b64'] ?? ''));
            $clean = blocksToText($blocks);
            $db->prepare('UPDATE ocr_runs SET text = ? WHERE id = ?')->execute([$clean, $r['id']]);
            if ((int)$r['chosen']) $db->prepare('UPDATE ocr_pages SET text = ? WHERE id = ?')->execute([$clean, $r['page_id']]);
            $n++;
        }
        if ($n) $done[] = "bloky dopočítány u běhů: $n";
    } catch (Throwable $e) {
        $done[] = 'bloky se nepodařilo dopočítat: ' . $e->getMessage();
    }

    // 10. Bloky ze zacykleného běhu (model chrlil image[[0, 0, 0, 0]] až do
    //     limitu) — prázdné rámečky pryč, text běhu znovu z toho, co zbylo
    try {
        require_once __DIR__ . '/ocr.php';
        //     Jen obrázky: textový blok bez rámečku (zbytek z druhého přepisu
        //     kombinovaného zadání) má souřadnice 0,0,0,0 taky a je v pořádku
        $runs = $db->query("SELECT DISTINCT run_id FROM ocr_blocks WHERE kind IN ('image', 'figure') AND (x2 <= x1 OR y2 <= y1)")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($runs as $runId) {
            $del = $db->prepare("DELETE FROM ocr_blocks WHERE run_id = ? AND kind IN ('image', 'figure') AND (x2 <= x1 OR y2 <= y1)");
            $del->execute([$runId]);
            $blocks = array_map(fn($b) => ['kind' => $b['kind'], 'box' => null, 'text' => (string)$b['text']], runBlocks((int)$runId));
            $text   = blocksToText($blocks);
            $warn   = 'Model se zacyklil na rámečcích (' . $del->rowCount() . ' opakování zahozeno) — konec stránky nejspíš chybí.';
            $db->prepare('UPDATE ocr_runs SET text = ?, warning = ? WHERE id = ?')->execute([$text, $warn, $runId]);
            $run = getOcrRun((int)$runId);
            if ($run && (int)$run['chosen']) {
                $db->prepare('UPDATE ocr_pages SET text = ? WHERE id = ?')->execute([$text, $run['page_id']]);
            }
        }
        if ($runs) $done[] = 'zacyklené bloky uklizeny u běhů: ' . count($runs);
    } catch (Throwable $e) {
        $done[] = 'úklid zacyklených bloků selhal: ' . $e->getMessage();
    }

    // 11. Přechod na Ollama Proxy: jedna adresa, jeden klíč, model rozhoduje
    //     o tom, kdo stránku přečte. Staré nastavení dvou poskytovatelů
    //     přeneseme, ať admin nemusí nic vyplňovat znovu.
    try {
        foreach ([
            'ollama_url'          => 'proxy_url',
            'ollama_vision_model' => 'vision_model',
            'ollama_text_model'   => 'text_model',
            'ollama_num_ctx'      => 'num_ctx',
        ] as $old => $new) {
            if (getSetting($new) === '' && getSetting($old) !== '') {
                setSetting($new, getSetting($old));
                $done[] = "nastavení $old přeneseno do $new";
            }
        }
    } catch (Throwable $e) {
        $done[] = 'nastavení proxy se nepodařilo přenést: ' . $e->getMessage();
    }

    return $done;
}
