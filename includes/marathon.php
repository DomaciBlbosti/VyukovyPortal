<?php
/**
 * Matematický maraton — rekordy.
 *
 * Maraton má sto příkladů a měří se, ne jen zda se povedl. Kolo se ukládá
 * jako každé jiné do game_sessions pod vlastním herním typem; rekord je to
 * kolo s nejmíň chybami a při stejném počtu chyb nejrychlejší. Samotná
 * rychlost nestačí — jinak by vyhrálo kolo proklikané naslepo.
 */
require_once __DIR__ . '/../config/db.php';

/**
 * @return array{best:?array, last:?array, count:int}
 *   best/last: ['id'=>int, 'seconds'=>int, 'errors'=>int, 'accuracy'=>float, 'played_at'=>string]
 */
function marathonRecord(int $userId, string $variant = ''): array {
    $out = ['best' => null, 'last' => null, 'count' => 0];
    if (!$userId) return $out;
    try {
        $db  = getDB();
        // Rekord se drží pro každou sestavu zvlášť — „50× sčítání" a „vše
        // pro ročník" se porovnat nedají
        $where = "user_id = ? AND game_type = 'math_marathon' AND variant = ?";
        $args  = [$userId, $variant];
        $row = fn(array $r) => ['id' => (int)$r['id'], 'seconds' => (int)$r['duration_seconds'],
                                'errors' => (int)$r['errors'], 'accuracy' => (float)$r['accuracy'],
                                'played_at' => (string)$r['played_at']];

        $stmt = $db->prepare("SELECT id, duration_seconds, errors, accuracy, played_at FROM game_sessions
                              WHERE $where AND duration_seconds > 0
                              ORDER BY errors ASC, duration_seconds ASC, id ASC LIMIT 1");
        $stmt->execute($args);
        if ($r = $stmt->fetch()) $out['best'] = $row($r);

        $stmt = $db->prepare("SELECT id, duration_seconds, errors, accuracy, played_at FROM game_sessions
                              WHERE $where ORDER BY played_at DESC, id DESC LIMIT 1");
        $stmt->execute($args);
        if ($r = $stmt->fetch()) $out['last'] = $row($r);

        $stmt = $db->prepare("SELECT COUNT(*) FROM game_sessions WHERE $where");
        $stmt->execute($args);
        $out['count'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
    }
    return $out;
}

/** Čas jako 4:32 */
function marathonTime(int $seconds): string {
    return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

/** „4:32 · 2 chyby" */
function marathonLabel(array $run): string {
    $e = (int)$run['errors'];
    return marathonTime((int)$run['seconds']) . ' · ' . $e . ' ' . ($e === 1 ? 'chyba' : ($e >= 2 && $e <= 4 ? 'chyby' : 'chyb'));
}

/* ── Sestavy ──────────────────────────────────────────────────────────
 * Rodič si poskládá vlastní maraton: které kategorie, kolik příkladů
 * a jestli je na to limit. Bez sestavy jede maraton „vše pro ročník".
 */

const MARATHON_MIN_COUNT = 10;
const MARATHON_MAX_COUNT = 300;
const MARATHON_MAX_LIMIT = 3600;   // sekund

/** @return array<array{id:int, title:string, categories:array<string>, count:int, time_limit:int}> */
function marathonPresets(): array {
    try {
        $rows = getDB()->query('SELECT * FROM marathon_presets ORDER BY title ASC, id ASC')->fetchAll();
        return array_map('marathonPresetRow', $rows);
    } catch (PDOException $e) {
        return [];
    }
}

function getMarathonPreset(int $id): ?array {
    if (!$id) return null;
    try {
        $stmt = getDB()->prepare('SELECT * FROM marathon_presets WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ? marathonPresetRow($r) : null;
    } catch (PDOException $e) {
        return null;
    }
}

function marathonPresetRow(array $r): array {
    $cats = json_decode((string)$r['categories'], true);
    return [
        'id'         => (int)$r['id'],
        'title'      => (string)$r['title'],
        'categories' => is_array($cats) ? array_values(array_map('strval', $cats)) : [],
        'count'      => (int)$r['count'],
        'time_limit' => (int)$r['time_limit'],
    ];
}

/**
 * Uloží sestavu; vrací id, nebo 0 při chybě. Neznámé kategorie zahodí,
 * počet a limit přistřihne na rozumné meze.
 */
function saveMarathonPreset(int $id, string $title, array $categories, int $count, int $timeLimit): int {
    require_once __DIR__ . '/../games/data/math.php';
    $known = mathMarathonCategories();
    $cats  = array_values(array_filter(array_unique(array_map('strval', $categories)), fn($k) => isset($known[$k])));
    $title = trim($title);
    if ($title === '' || !$cats) return 0;

    $count     = max(MARATHON_MIN_COUNT, min(MARATHON_MAX_COUNT, $count));
    $timeLimit = max(0, min(MARATHON_MAX_LIMIT, $timeLimit));
    $json      = json_encode($cats, JSON_UNESCAPED_UNICODE);
    try {
        $db = getDB();
        if ($id) {
            $db->prepare('UPDATE marathon_presets SET title = ?, categories = ?, count = ?, time_limit = ? WHERE id = ?')
               ->execute([mb_substr($title, 0, 120), $json, $count, $timeLimit, $id]);
            return $id;
        }
        $db->prepare('INSERT INTO marathon_presets (title, categories, count, time_limit, created_at) VALUES (?,?,?,?,?)')
           ->execute([mb_substr($title, 0, 120), $json, $count, $timeLimit, date('Y-m-d H:i:s')]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        return 0;
    }
}

function deleteMarathonPreset(int $id): bool {
    try {
        $stmt = getDB()->prepare('DELETE FROM marathon_presets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** „50 příkladů · limit 5 min" */
function marathonPresetMeta(array $p): string {
    $out = $p['count'] . ' příkladů';
    if ($p['time_limit'] > 0) $out .= ' · limit ' . marathonTime($p['time_limit']);
    return $out;
}
