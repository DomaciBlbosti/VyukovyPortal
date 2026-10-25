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
function marathonRecord(int $userId): array {
    $out = ['best' => null, 'last' => null, 'count' => 0];
    if (!$userId) return $out;
    try {
        $db  = getDB();
        $row = fn(array $r) => ['id' => (int)$r['id'], 'seconds' => (int)$r['duration_seconds'],
                                'errors' => (int)$r['errors'], 'accuracy' => (float)$r['accuracy'],
                                'played_at' => (string)$r['played_at']];

        $stmt = $db->prepare("SELECT id, duration_seconds, errors, accuracy, played_at FROM game_sessions
                              WHERE user_id = ? AND game_type = 'math_marathon' AND duration_seconds > 0
                              ORDER BY errors ASC, duration_seconds ASC, id ASC LIMIT 1");
        $stmt->execute([$userId]);
        if ($r = $stmt->fetch()) $out['best'] = $row($r);

        $stmt = $db->prepare("SELECT id, duration_seconds, errors, accuracy, played_at FROM game_sessions
                              WHERE user_id = ? AND game_type = 'math_marathon'
                              ORDER BY played_at DESC, id DESC LIMIT 1");
        $stmt->execute([$userId]);
        if ($r = $stmt->fetch()) $out['last'] = $row($r);

        $stmt = $db->prepare("SELECT COUNT(*) FROM game_sessions WHERE user_id = ? AND game_type = 'math_marathon'");
        $stmt->execute([$userId]);
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
