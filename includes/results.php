<?php
/**
 * Výsledky her — všechny odpovědi z jednoho kola.
 *
 * Chybovník si pamatuje jen to, co bylo špatně a kolikrát. Tady se ke každé
 * hře ukládá celé kolo: zadání, co dítě odpovědělo, co bylo správně. Dítě si
 * tak může výsledek projít a rodič ověřit, že se vyhodnotilo správně.
 */
require_once __DIR__ . '/../config/db.php';

/** Uloží odpovědi ze hry ('answers' i 'answer_groups' po sadách) */
function saveGameAnswers(int $sessionId, int $userId, array $data): int {
    if (!$sessionId || !$userId) return 0;
    $rows = [];
    foreach ($data['answers'] ?? [] as $it) $rows[] = [(string)($data['topic_label'] ?? ''), $it];
    foreach ($data['answer_groups'] ?? [] as $g) {
        foreach ($g['items'] ?? [] as $it) $rows[] = [(string)($g['topic_label'] ?? ''), $it];
    }
    if (!$rows) return 0;
    try {
        $ins = getDB()->prepare('INSERT INTO game_answers (session_id, user_id, position, topic_label, prompt, given, answer, ok) VALUES (?,?,?,?,?,?,?,?)');
        $n = 0;
        foreach ($rows as $i => [$label, $it]) {
            if (!is_array($it)) continue;
            $ins->execute([$sessionId, $userId, $i, mb_substr($label, 0, 120),
                           mb_substr((string)($it['prompt'] ?? ''), 0, 500), mb_substr((string)($it['given'] ?? ''), 0, 255),
                           mb_substr((string)($it['answer'] ?? ''), 0, 255), !empty($it['ok']) ? 1 : 0]);
            $n++;
        }
        return $n;
    } catch (PDOException $e) {
        return 0;
    }
}

/** Jedna hra i se jménem hráče; null, když není */
function getGameSession(int $id): ?array {
    try {
        $stmt = getDB()->prepare('SELECT s.*, u.display_name, u.username FROM game_sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Odpovědi ke hře v pořadí, jak padly */
function gameAnswers(int $sessionId): array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM game_answers WHERE session_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Odehrané hry hráče, nejnovější první, s počtem uložených odpovědí
 * (0 = hra z doby, kdy se odpovědi ještě neukládaly, nebo psaní).
 */
function listGameSessions(int $userId, int $limit = 100, string $gameType = ''): array {
    try {
        $sql = 'SELECT s.*, (SELECT COUNT(*) FROM game_answers a WHERE a.session_id = s.id) AS answer_count,
                       (SELECT COUNT(*) FROM game_answers a WHERE a.session_id = s.id AND a.ok = 0) AS wrong_count
                FROM game_sessions s WHERE s.user_id = ?' . ($gameType !== '' ? ' AND s.game_type = ?' : '')
             . ' ORDER BY s.played_at DESC, s.id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = getDB()->prepare($sql);
        $stmt->execute($gameType !== '' ? [$userId, $gameType] : [$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Smí tenhle uživatel vidět tuhle hru? Své ano, admin všechny. */
function canSeeSession(array $session, array $user): bool {
    return !empty($user['is_admin']) || (int)$session['user_id'] === (int)$user['id'];
}

/** Čitelný čas trvání: 1:05 */
function durationLabel(int $seconds): string {
    return $seconds >= 60 ? intdiv($seconds, 60) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT) : $seconds . ' s';
}
