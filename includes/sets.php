<?php
/**
 * Vlastní sady úloh — obsah, který nejde z kódu, ale z učebnice.
 *
 * Do teď byla každá sada natvrdo v PHP (games/data/czech.php a spol.), takže
 * nová slovíčka znamenala commit a nasazení. Tady se sada uloží do databáze
 * a rovnou je hratelná i zadatelná ve výzvě.
 *
 * Čtyři formáty pokryjí prakticky všechno, co se doma procvičuje:
 *   dvojice      slovíčko ↔ překlad, veličina ↔ jednotka, událost ↔ letopočet
 *   vyber        otázka a nabídnuté odpovědi (dějepis, fyzika, zeměpis)
 *   doplnovacka  věta s mezerou (anglické fráze, gramatika)
 *   cteni        text a otázky k němu
 *
 * Uvnitř se všechny čtyři převedou na stejný tvar úlohy (zadání, správná
 * odpověď, možnosti), takže je hraje jediná hra a jediný kus JavaScriptu.
 */
require_once __DIR__ . '/../config/db.php';

const SET_KINDS = [
    'dvojice'     => 'Dvojice (slovíčko ↔ překlad)',
    'vyber'       => 'Výběr z možností',
    'doplnovacka' => 'Doplňovačka (věta s mezerou)',
    'cteni'       => 'Čtení s porozuměním',
];

const SET_SUBJECTS = [
    'anglictina' => ['label' => 'Angličtina', 'icon' => '🇬🇧'],
    'cestina'    => ['label' => 'Čeština',    'icon' => '✍️'],
    'matematika' => ['label' => 'Matematika', 'icon' => '🔢'],
    'fyzika'     => ['label' => 'Fyzika',     'icon' => '🔬'],
    'dejepis'    => ['label' => 'Dějepis',    'icon' => '🏛'],
    'zemepis'    => ['label' => 'Zeměpis',    'icon' => '🌍'],
    'prirodopis' => ['label' => 'Přírodopis', 'icon' => '🌿'],
    'ostatni'    => ['label' => 'Ostatní',    'icon' => '📚'],
];

/** Kolik úloh má jedno kolo */
const SET_ROUND_SIZE = 12;

/**
 * Klíč položky pro chybovník.
 *
 * Počítá se z obsahu, ne z ID řádku — když sadu smažeš a nahraješ opravenou,
 * u nezměněných položek zůstane historie chyb zachovaná.
 */
function setItemKey(string $subject, string $title, string $prompt, string $answer): string {
    return substr(md5($subject . '|' . $title . '|' . $prompt . '|' . $answer), 0, 16);
}

/** Popisek předmětu i s ikonou */
function setSubjectLabel(string $subject): string {
    $s = SET_SUBJECTS[$subject] ?? SET_SUBJECTS['ostatni'];
    return $s['icon'] . ' ' . $s['label'];
}

/**
 * Zkontroluje a převede JSON z importu do tvaru, který jde uložit.
 *
 * Vrací ['set' => …, 'items' => […], 'errors' => […]]. Když jsou v errors
 * nějaké položky, neukládá se nic — půlka nahrané sady je horší než žádná.
 */
function parseSetPayload(string $json): array {
    $out = ['set' => null, 'items' => [], 'errors' => []];

    $data = json_decode($json, true);
    if (!is_array($data)) {
        $out['errors'][] = 'Tohle není platný JSON: ' . json_last_error_msg();
        return $out;
    }

    $subject = (string)($data['predmet'] ?? 'ostatni');
    if (!isset(SET_SUBJECTS[$subject])) {
        $out['errors'][] = 'Neznámý předmět „' . $subject . '". Použij: ' . implode(', ', array_keys(SET_SUBJECTS));
        $subject = 'ostatni';
    }

    $kind = (string)($data['typ'] ?? 'dvojice');
    if (!isset(SET_KINDS[$kind])) {
        $out['errors'][] = 'Neznámý typ „' . $kind . '". Použij: ' . implode(', ', array_keys(SET_KINDS));
        $kind = 'dvojice';
    }

    $title = trim((string)($data['nazev'] ?? ''));
    if ($title === '') $out['errors'][] = 'Chybí „nazev" sady.';

    // Zdroj je povinný — u obsahu z učebnice chceme vždycky vědět, odkud je
    $source = trim((string)($data['zdroj'] ?? ''));
    if ($source === '') $out['errors'][] = 'Chybí „zdroj" — napiš, ze které učebnice a lekce sada je.';

    $grade = (int)($data['rocnik'] ?? 0);
    if ($grade < 0 || $grade > 9) {
        $out['errors'][] = 'Ročník musí být 0–9 (0 = pro všechny).';
        $grade = 0;
    }

    $passage = trim((string)($data['text'] ?? ''));
    if ($kind === 'cteni' && $passage === '') {
        $out['errors'][] = 'U typu „cteni" musí být vyplněný „text" k přečtení.';
    }

    $raw = $data['polozky'] ?? [];
    if (!is_array($raw) || !$raw) {
        $out['errors'][] = 'Sada nemá žádné položky.';
        $raw = [];
    }

    $seen = [];
    foreach (array_values($raw) as $i => $it) {
        $n = $i + 1;
        if (!is_array($it)) { $out['errors'][] = "Položka $n není objekt."; continue; }

        // Každý formát pojmenovává pole po svém, ať se JSON píše přirozeně
        [$prompt, $answer] = match ($kind) {
            'dvojice' => [trim((string)($it['a'] ?? '')), trim((string)($it['b'] ?? ''))],
            default   => [trim((string)($it['otazka'] ?? $it['veta'] ?? '')),
                          trim((string)($it['odpoved'] ?? ''))],
        };

        if ($prompt === '') { $out['errors'][] = "Položka $n nemá zadání."; continue; }
        if ($answer === '') { $out['errors'][] = "Položka $n nemá odpověď."; continue; }
        if ($kind === 'doplnovacka' && !str_contains($prompt, '_')) {
            $out['errors'][] = "Položka $n: doplňovačka musí mít v textu podtržítko jako mezeru.";
            continue;
        }

        $key = mb_strtolower($prompt);
        if (isset($seen[$key])) { $out['errors'][] = "Položka $n má stejné zadání jako položka {$seen[$key]}."; continue; }
        $seen[$key] = $n;

        // Možnosti: buď je autor uvede, nebo se u dvojic dolosují ze sady
        $options = [];
        foreach ((array)($it['moznosti'] ?? []) as $o) {
            $o = trim((string)$o);
            if ($o !== '' && !in_array($o, $options, true)) $options[] = $o;
        }
        if ($options && !in_array($answer, $options, true)) $options[] = $answer;
        if ($kind === 'vyber' && count($options) < 2) {
            $out['errors'][] = 'Položka ' . $n . ': u výběru z možností uveď aspoň dvě „moznosti".';
            continue;
        }

        $out['items'][] = [
            'position' => count($out['items']),
            'item_key' => setItemKey($subject, $title, $prompt, $answer),
            'prompt'   => mb_substr($prompt, 0, 500),
            'answer'   => mb_substr($answer, 0, 255),
            'options'  => $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
            'hint'     => mb_substr(trim((string)($it['napoveda'] ?? '')), 0, 255),
        ];
    }

    if (count($out['items']) < 4 && !$out['errors']) {
        $out['errors'][] = 'Sada má míň než čtyři použitelné položky — na kolo to nestačí.';
    }

    $out['set'] = [
        'subject' => $subject,
        'grade'   => $grade,
        'title'   => mb_substr($title, 0, 120),
        'source'  => mb_substr($source, 0, 180),
        'kind'    => $kind,
        'passage' => $passage !== '' ? $passage : null,
    ];
    return $out;
}

/** Uloží ověřenou sadu. Vrací ID, nebo 0 při selhání. */
function saveSet(array $set, array $items, int $userId): int {
    try {
        $db  = getDB();
        $now = date('Y-m-d H:i:s');
        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO custom_sets
            (subject, grade, title, source, kind, passage, created_by, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$set['subject'], $set['grade'], $set['title'], $set['source'],
                        $set['kind'], $set['passage'], $userId ?: null, $now, $now]);
        $id = (int)$db->lastInsertId();

        $stmt = $db->prepare('INSERT INTO custom_set_items
            (set_id, position, item_key, prompt, answer, options, hint) VALUES (?,?,?,?,?,?,?)');
        foreach ($items as $it) {
            $stmt->execute([$id, $it['position'], $it['item_key'], $it['prompt'],
                            $it['answer'], $it['options'], $it['hint']]);
        }

        $db->commit();
        return $id;
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        return 0;
    }
}

/** Jedna sada bez položek; null, když neexistuje */
function getSet(int $id): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM custom_sets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Položky sady v pořadí, jak byly nahrané */
function setItems(int $setId): array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM custom_set_items WHERE set_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$setId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Seznam sad i s počtem položek.
 *
 * @param int $grade 0 = bez omezení; jinak sady daného ročníku a sady bez ročníku
 */
function listSets(int $grade = 0, string $subject = ''): array {
    try {
        $sql    = 'SELECT s.*, (SELECT COUNT(*) FROM custom_set_items i WHERE i.set_id = s.id) AS item_count
                   FROM custom_sets s WHERE 1=1';
        $params = [];
        if ($grade > 0)     { $sql .= ' AND (s.grade = ? OR s.grade = 0)'; $params[] = $grade; }
        if ($subject !== '') { $sql .= ' AND s.subject = ?';               $params[] = $subject; }
        $sql .= ' ORDER BY s.subject ASC, s.title ASC';

        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Smaže sadu i její položky (chybovník si historii nechá — klíče jsou z obsahu) */
function deleteSet(int $id): bool {
    try {
        $stmt = getDB()->prepare('DELETE FROM custom_sets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Připraví kolo úloh: doplní možnosti tam, kde chybí, a promíchá je.
 *
 * U dvojic se špatné možnosti losují z odpovědí téže sady — jsou tím pádem
 * ze stejného okruhu a nejde je uhodnout podle toho, že jediná dává smysl.
 *
 * U dvojic jde kolo hrát i obráceně (z překladu na slovíčko). Opačný směr má
 * vlastní klíč do chybovníku — „kitchen → kuchyně" umí dítě dřív než naopak.
 *
 * @param array<string> $practiceKeys co dítě naposled splétlo — dostane přednost
 * @return array<array{key:string, prompt:string, correct:string, options:array, hint:string}>
 */
function buildSetRound(array $items, string $kind, array $practiceKeys = [],
                       bool $reverse = false, int $want = SET_ROUND_SIZE): array {
    if (!$items) return [];

    if ($reverse && $kind === 'dvojice') {
        foreach ($items as &$it) {
            [$it['prompt'], $it['answer']] = [$it['answer'], $it['prompt']];
            $it['item_key'] .= ':r';
            $it['options']   = null;   // možnosti se dolosují z druhé strany dvojic
        }
        unset($it);
    }

    // Nejdřív chybné položky, zbytek náhodně — pak se celé kolo promíchá
    $practice = $rest = [];
    foreach ($items as $it) {
        if (in_array($it['item_key'], $practiceKeys, true)) $practice[] = $it;
        else                                                $rest[]     = $it;
    }
    shuffle($practice);
    shuffle($rest);

    // Z chybovníku bereme nejvýš třetinu kola, ať procvičování nezhoustne
    $practice = array_slice($practice, 0, max(1, (int)floor($want / 3)));
    $chosen   = array_slice(array_merge($practice, $rest), 0, $want);
    shuffle($chosen);

    $allAnswers = array_values(array_unique(array_column($items, 'answer')));

    $tasks = [];
    foreach ($chosen as $it) {
        $options = $it['options'] ? (json_decode($it['options'], true) ?: []) : [];

        if (!$options) {
            // Dolosuj tři jiné odpovědi ze stejné sady
            $pool = array_values(array_diff($allAnswers, [$it['answer']]));
            shuffle($pool);
            $options = array_merge([$it['answer']], array_slice($pool, 0, 3));
        }
        shuffle($options);

        $tasks[] = [
            'key'     => $it['item_key'],
            'prompt'  => $it['prompt'],
            'correct' => $it['answer'],
            'options' => array_values($options),
            'hint'    => $it['hint'],
        ];
    }
    return $tasks;
}
