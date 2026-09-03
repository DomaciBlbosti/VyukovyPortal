<?php
/**
 * Výzvy — sady úkolů, které rodič poskládá a zadá dětem.
 *
 * Výzva je seznam kroků; krok říká „tuhle sadu, tolikrát, s přesností aspoň
 * tolik procent". Splnění se vyhodnocuje po každé dohrané hře: když sedí
 * předmět i sada a dítě dosáhlo požadované přesnosti, přičte se jedno kolo.
 *
 * Jedna hra posune každý krok nejvýš o jedno kolo — jinak by kolo hrané ve
 * dvou vybraných sadách naráz splnilo krok dvakrát.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/catalog.php';

/** Výzva i s kroky; null, když neexistuje */
function getChallenge(int $id): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM challenges WHERE id = ?');
        $stmt->execute([$id]);
        $ch = $stmt->fetch();
        if (!$ch) return null;

        $stmt = $db->prepare('SELECT * FROM challenge_steps WHERE challenge_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$id]);
        $ch['steps'] = $stmt->fetchAll();
        return $ch;
    } catch (PDOException $e) {
        return null;
    }
}

/** Všechny výzvy s počtem kroků a zadání */
function listChallenges(): array {
    try {
        return getDB()->query('
            SELECT c.*,
                   (SELECT COUNT(*) FROM challenge_steps s WHERE s.challenge_id = c.id)       AS step_count,
                   (SELECT COUNT(*) FROM challenge_assignments a WHERE a.challenge_id = c.id) AS assigned_count
            FROM challenges c
            ORDER BY c.id DESC
        ')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Výzvy zadané dítěti i s postupem.
 *
 * @param bool $onlyOpen jen nedokončené
 * @return array<array{assignment_id:int, challenge_id:int, title:string, description:string,
 *                     completed_at:?string, steps:array, done_steps:int, total_steps:int, percent:int}>
 */
function userChallenges(int $userId, bool $onlyOpen = false): array {
    if (!$userId) return [];
    try {
        $db = getDB();
        $sql = 'SELECT a.id AS assignment_id, a.challenge_id, a.completed_at, a.assigned_at,
                       c.title, c.description
                FROM challenge_assignments a
                JOIN challenges c ON c.id = a.challenge_id
                WHERE a.user_id = ?';
        if ($onlyOpen) $sql .= ' AND a.completed_at IS NULL';
        $sql .= ' ORDER BY a.completed_at IS NULL DESC, a.assigned_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) return [];

        $stepStmt = $db->prepare('
            SELECT s.*, COALESCE(p.done_rounds, 0) AS done_rounds, COALESCE(p.best_accuracy, 0) AS best_accuracy
            FROM challenge_steps s
            LEFT JOIN challenge_progress p ON p.step_id = s.id AND p.assignment_id = ?
            WHERE s.challenge_id = ?
            ORDER BY s.position ASC, s.id ASC
        ');

        $out = [];
        foreach ($rows as $r) {
            $stepStmt->execute([$r['assignment_id'], $r['challenge_id']]);
            $steps = $stepStmt->fetchAll();

            $doneRounds = $totalRounds = 0;
            $doneSteps  = 0;
            foreach ($steps as &$s) {
                $s['rounds']      = max(1, (int)$s['rounds']);
                $s['done_rounds'] = min((int)$s['done_rounds'], $s['rounds']);
                $s['done']        = $s['done_rounds'] >= $s['rounds'];
                $s['label']       = catalogLabel($s['game_type'], $s['topic']);
                $s['url']         = catalogUrl($s['game_type'], $s['topic']);
                $doneRounds  += $s['done_rounds'];
                $totalRounds += $s['rounds'];
                $doneSteps   += $s['done'] ? 1 : 0;
            }
            unset($s);

            $r['steps']       = $steps;
            $r['done_steps']  = $doneSteps;
            $r['total_steps'] = count($steps);
            $r['percent']     = $totalRounds ? (int)round($doneRounds / $totalRounds * 100) : 0;
            $out[] = $r;
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/** Kolik má dítě rozdělaných výzev */
function openChallengeCount(int $userId): int {
    if (!$userId) return 0;
    try {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM challenge_assignments WHERE user_id = ? AND completed_at IS NULL');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Započítá dohranou hru do všech otevřených výzev.
 *
 * @param array<string> $topics sady, které se ve hře opravdu hrály
 * @return array{steps:array<string>, challenges:array<string>} co se právě splnilo
 */
function recordChallengePlay(int $userId, string $gameType, array $topics, float $accuracy): array {
    $done = ['steps' => [], 'challenges' => []];
    if (!$userId || $gameType === '') return $done;

    // I hra bez konkrétní sady (psaní) musí umět splnit krok „jakékoliv kolo"
    if (!$topics) $topics = [''];

    try {
        $db = getDB();
        $stmt = $db->prepare('
            SELECT a.id AS assignment_id, a.challenge_id, c.title,
                   s.id AS step_id, s.game_type, s.topic, s.rounds, s.min_accuracy,
                   COALESCE(p.done_rounds, 0) AS done_rounds
            FROM challenge_assignments a
            JOIN challenges c      ON c.id = a.challenge_id
            JOIN challenge_steps s ON s.challenge_id = a.challenge_id
            LEFT JOIN challenge_progress p ON p.assignment_id = a.id AND p.step_id = s.id
            WHERE a.user_id = ? AND a.completed_at IS NULL AND s.game_type = ?
        ');
        $stmt->execute([$userId, $gameType]);
        $rows = $stmt->fetchAll();
        if (!$rows) return $done;

        // Zápis děláme dvoukrokově (najdi → vlož nebo uprav), ať dotazy nezávisí
        // na SQL dialektu — ON DUPLICATE KEY zná MySQL, ale ne SQLite
        $now    = date('Y-m-d H:i:s');
        $find   = $db->prepare('SELECT id, best_accuracy FROM challenge_progress
                                WHERE assignment_id = ? AND step_id = ?');
        $insert = $db->prepare('INSERT INTO challenge_progress
                                (assignment_id, step_id, done_rounds, best_accuracy, updated_at)
                                VALUES (?,?,?,?,?)');
        $update = $db->prepare('UPDATE challenge_progress
                                SET done_rounds = ?, best_accuracy = ?, updated_at = ? WHERE id = ?');
        $touched = [];
        foreach ($rows as $r) {
            $rounds = max(1, (int)$r['rounds']);
            if ((int)$r['done_rounds'] >= $rounds) continue;
            if ($accuracy < (float)$r['min_accuracy']) continue;

            $hit = false;
            foreach ($topics as $t) {
                if (catalogMatches($r['game_type'], (string)$r['topic'], $gameType, (string)$t)) { $hit = true; break; }
            }
            if (!$hit) continue;

            $next = (int)$r['done_rounds'] + 1;   // jedna hra = nejvýš jedno kolo na krok
            $find->execute([$r['assignment_id'], $r['step_id']]);
            $prev = $find->fetch();
            if ($prev) {
                $update->execute([$next, max((float)$prev['best_accuracy'], $accuracy), $now, $prev['id']]);
            } else {
                $insert->execute([$r['assignment_id'], $r['step_id'], $next, $accuracy, $now]);
            }
            $touched[(int)$r['assignment_id']] = $r['title'];
            if ($next >= $rounds) $done['steps'][] = catalogLabel($r['game_type'], (string)$r['topic']);
        }

        // Hotová výzva = všechny kroky mají odehraná všechna kola
        foreach ($touched as $assignmentId => $title) {
            $left = $db->prepare('
                SELECT COUNT(*) FROM challenge_steps s
                LEFT JOIN challenge_progress p ON p.step_id = s.id AND p.assignment_id = ?
                WHERE s.challenge_id = (SELECT challenge_id FROM challenge_assignments WHERE id = ?)
                  AND COALESCE(p.done_rounds, 0) < CASE WHEN s.rounds < 1 THEN 1 ELSE s.rounds END
            ');
            $left->execute([$assignmentId, $assignmentId]);
            if ((int)$left->fetchColumn() === 0) {
                $db->prepare('UPDATE challenge_assignments SET completed_at = ? WHERE id = ? AND completed_at IS NULL')
                   ->execute([$now, $assignmentId]);
                $done['challenges'][] = $title;
            }
        }
    } catch (PDOException $e) {
        // Výzvy jsou nadstavba — když se nepovedou, hra se kvůli tomu nezastaví
    }
    return $done;
}
