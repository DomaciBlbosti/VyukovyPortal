<?php
/**
 * Chybovník — pamatuje si konkrétní úlohy, které dítěti nešly.
 *
 * Řádek vzniká teprve při chybě. Správné odpovědi jen aktualizují to, co
 * už v tabulce je; bez toho by matematika (kde se příklady losují z velkého
 * rozsahu) zaplnila tabulku statisíci řádků, které nikdy nikdo neuvidí.
 *
 * Po MASTERED_STREAK správných odpovědích v řadě položka vypadne z
 * procvičování, ale řádek zůstává — v přehledu je pak vidět i to, co už
 * dítě dohnalo.
 */
require_once __DIR__ . '/../config/db.php';

/** Kolikrát po sobě musí dítě odpovědět správně, aby se položka přestala nabízet */
const MASTERED_STREAK = 3;

/**
 * Zapíše výsledky jednoho kola.
 *
 * Sada (topic) přichází ze serveru, ne z prohlížeče — hra ji zná z adresy.
 * Zbytek popisu položky posílá klient (u matematiky se příklady losují, takže
 * je server zpětně nesestaví); všude se vypisuje escapovaně a zkrácený.
 *
 * @param array $items položky ve tvaru ['key'=>…, 'ok'=>bool, 'prompt'=>…, 'answer'=>…, 'hint'=>…]
 * @return int kolik chyb se zapsalo
 */
function recordAnswers(int $userId, string $gameType, array $items,
                       string $topic = '', string $topicLabel = ''): int {
    if (!$userId || !$items) return 0;

    try {
        $db = getDB();
        $sel = $db->prepare('SELECT id, wrong_count FROM mistakes
                             WHERE user_id = ? AND game_type = ? AND item_key = ?');
        // Čas bereme z PHP, ať dotazy nezávisí na SQL dialektu
        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare('INSERT INTO mistakes
            (user_id, game_type, topic, topic_label, item_key, prompt, correct_answer, hint,
             wrong_count, right_streak, last_wrong_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?, 1, 0, ?, ?)');
        $onWrong = $db->prepare('UPDATE mistakes
            SET wrong_count = wrong_count + 1, right_streak = 0,
                last_wrong_at = ?, updated_at = ?
            WHERE id = ?');
        $onRight = $db->prepare('UPDATE mistakes
            SET right_streak = right_streak + 1, updated_at = ?
            WHERE id = ?');

        $wrong = 0;
        foreach ($items as $it) {
            $key = trim((string)($it['key'] ?? ''));
            if ($key === '') continue;
            $key = mb_substr($key, 0, 180);

            $sel->execute([$userId, $gameType, $key]);
            $row = $sel->fetch();
            $ok  = !empty($it['ok']);

            if ($row) {
                $ok ? $onRight->execute([$now, $row['id']])
                    : $onWrong->execute([$now, $now, $row['id']]);
                if (!$ok) $wrong++;
            } elseif (!$ok) {
                $insert->execute([
                    $userId, $gameType,
                    mb_substr($topic, 0, 80),
                    mb_substr($topicLabel, 0, 120),
                    $key,
                    mb_substr((string)($it['prompt'] ?? ''), 0, 255),
                    mb_substr((string)($it['answer'] ?? ''), 0, 255),
                    mb_substr((string)($it['hint']   ?? ''), 0, 255),
                    $now, $now,
                ]);
                $wrong++;
            }
        }
        return $wrong;
    } catch (PDOException $e) {
        // Chybovník je bonus — když se nepovede, hra se kvůli tomu nesmí zastavit
        return 0;
    }
}

/**
 * Položky k procvičení: co dítě splétlo a ještě to nedohnalo.
 * Nejdřív to, co plete nejčastěji, pak nejčerstvější chyby.
 *
 * @return array<array{item_key:string, prompt:string, correct_answer:string, hint:string, wrong_count:int}>
 */
function mistakesForPractice(int $userId, string $gameType, string $topic = '', int $limit = 6): array {
    if (!$userId || $limit < 1) return [];
    try {
        $sql = 'SELECT item_key, prompt, correct_answer, hint, wrong_count
                FROM mistakes
                WHERE user_id = ? AND game_type = ? AND right_streak < ?';
        $args = [$userId, $gameType, MASTERED_STREAK];
        if ($topic !== '') {
            $sql .= ' AND topic = ?';
            $args[] = $topic;
        }
        $sql .= ' ORDER BY wrong_count DESC, last_wrong_at DESC LIMIT ' . (int)$limit;

        $stmt = getDB()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Klíče položek k procvičení — pro rychlé prosívání datové sady */
function practiceKeys(int $userId, string $gameType, string $topic = '', int $limit = 6): array {
    return array_column(mistakesForPractice($userId, $gameType, $topic, $limit), 'item_key');
}

/**
 * Přehled „co nejde" seskupený po sadách.
 *
 * @return array<array{game_type:string, topic:string, topic_label:string,
 *                     open:int, wrong_total:int, items:array}>
 */
function mistakeOverview(int $userId, int $itemsPerTopic = 5): array {
    if (!$userId) return [];
    try {
        $stmt = getDB()->prepare('SELECT game_type, topic, topic_label, prompt, correct_answer,
                                         hint, wrong_count, right_streak
                                  FROM mistakes
                                  WHERE user_id = ? AND right_streak < ?
                                  ORDER BY wrong_count DESC, last_wrong_at DESC');
        $stmt->execute([$userId, MASTERED_STREAK]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $groups = [];
    foreach ($rows as $r) {
        $k = $r['game_type'] . '|' . $r['topic'];
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'game_type'   => $r['game_type'],
                'topic'       => $r['topic'],
                'topic_label' => $r['topic_label'] !== '' ? $r['topic_label'] : $r['topic'],
                'open'        => 0,
                'wrong_total' => 0,
                'items'       => [],
            ];
        }
        $groups[$k]['open']++;
        $groups[$k]['wrong_total'] += (int)$r['wrong_count'];
        if (count($groups[$k]['items']) < $itemsPerTopic) $groups[$k]['items'][] = $r;
    }

    usort($groups, fn($a, $b) => $b['wrong_total'] <=> $a['wrong_total']);
    return $groups;
}

/** Počet položek, které dítě ještě nemá zvládnuté */
function openMistakeCount(int $userId): int {
    if (!$userId) return 0;
    try {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM mistakes WHERE user_id = ? AND right_streak < ?');
        $stmt->execute([$userId, MASTERED_STREAK]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Rozparsuje seznam odpovědí poslaný hrou (JSON v POST).
 * Nedůvěřujeme mu — bere se jen to, co má správný tvar.
 */
function parseAnswerPayload(?string $json): array {
    if (!$json) return [];
    $data = json_decode($json, true);
    if (!is_array($data)) return [];

    $out = [];
    foreach (array_slice($data, 0, 60) as $it) {
        if (!is_array($it) || !isset($it['key'])) continue;
        $out[] = [
            'key'    => (string)$it['key'],
            'ok'     => !empty($it['ok']),
            'prompt' => (string)($it['prompt'] ?? ''),
            'answer' => (string)($it['answer'] ?? ''),
            'hint'   => (string)($it['hint']   ?? ''),
        ];
    }
    return $out;
}

/**
 * Věta nad hrou o tom, kolik úloh v kole je z chybovníku.
 * Čeština skloňuje podle počtu, tak si každou variantu napíšeme celou.
 */
function practiceNote(int $n, string $kind = 'task'): string {
    if ($n < 1) return '';
    return match ($kind) {
        'word' => $n === 1
            ? 'V tomhle kole je <strong>1</strong> slovíčko, které ti minule nešlo.'
            : ($n < 5 ? "V tomhle kole jsou <strong>$n</strong> slovíčka, která ti minule nešla."
                      : "V tomhle kole je <strong>$n</strong> slovíček, která ti minule nešla."),
        'ex' => $n === 1
            ? 'V tomhle kole je <strong>1</strong> příklad, který ti minule nešel.'
            : ($n < 5 ? "V tomhle kole jsou <strong>$n</strong> příklady, které ti minule nešly."
                      : "V tomhle kole je <strong>$n</strong> příkladů, které ti minule nešly."),
        default => $n === 1
            ? 'V tomhle kole je <strong>1</strong> úloha, která ti minule nešla.'
            : ($n < 5 ? "V tomhle kole jsou <strong>$n</strong> úlohy, které ti minule nešly."
                      : "V tomhle kole je <strong>$n</strong> úloh, které ti minule nešly."),
    };
}
