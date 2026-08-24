<?php
/**
 * Odznaky — jednorázové odměny za konkrétní úspěchy.
 *
 * Doplňují levely: levely odměňují vytrvalost (body se sčítají),
 * odznaky konkrétní milníky. Vyhodnocují se po každé dohrané hře
 * z jedné agregace, aby to nezdržovalo ukládání výsledku.
 */
require_once __DIR__ . '/../config/db.php';

/**
 * Definice odznaků. Podmínka dostane statistiky hráče a poslední hru,
 * vrací true, když si hráč odznak právě zasloužil.
 *
 * @return array<string, array{title:string, desc:string, icon:string, cond:callable}>
 */
function achievementDefs(): array {
    return [
        'first_game' => [
            'title' => 'První krok', 'desc' => 'Dokonči první hru', 'icon' => '🎯',
            'cond' => fn($s, $g) => $s['games'] >= 1,
        ],
        'games_10' => [
            'title' => 'Rozehřátý', 'desc' => 'Odehraj 10 her', 'icon' => '🔥',
            'cond' => fn($s, $g) => $s['games'] >= 10,
        ],
        'games_50' => [
            'title' => 'Vytrvalec', 'desc' => 'Odehraj 50 her', 'icon' => '💪',
            'cond' => fn($s, $g) => $s['games'] >= 50,
        ],
        'games_100' => [
            'title' => 'Stovkař', 'desc' => 'Odehraj 100 her', 'icon' => '💯',
            'cond' => fn($s, $g) => $s['games'] >= 100,
        ],
        'perfect' => [
            'title' => 'Bez jediné chyby', 'desc' => 'Dokonči hru se 100% přesností', 'icon' => '✨',
            'cond' => fn($s, $g) => ($g['accuracy'] ?? 0) >= 100 && ($g['chars_typed'] ?? 0) > 0,
        ],
        'perfect_5' => [
            'title' => 'Pětkrát čistě', 'desc' => 'Pětkrát hra se 100% přesností', 'icon' => '🌟',
            'cond' => fn($s, $g) => $s['perfect_games'] >= 5,
        ],
        'speed_40' => [
            'title' => 'Svižné prsty', 'desc' => 'Napiš 40 WPM nebo víc', 'icon' => '⚡',
            'cond' => fn($s, $g) => $s['best_typing_wpm'] >= 40,
        ],
        'speed_60' => [
            'title' => 'Blesk', 'desc' => 'Napiš 60 WPM nebo víc', 'icon' => '🚀',
            'cond' => fn($s, $g) => $s['best_typing_wpm'] >= 60,
        ],
        'streak_3' => [
            'title' => 'Tři dny v řadě', 'desc' => 'Hraj tři dny po sobě', 'icon' => '📅',
            'cond' => fn($s, $g) => $s['streak'] >= 3,
        ],
        'streak_7' => [
            'title' => 'Celý týden', 'desc' => 'Hraj sedm dní po sobě', 'icon' => '🗓️',
            'cond' => fn($s, $g) => $s['streak'] >= 7,
        ],
        'allrounder' => [
            'title' => 'Všeuměl', 'desc' => 'Zahraj si hry ze čtyř různých předmětů', 'icon' => '🎓',
            'cond' => fn($s, $g) => $s['subjects'] >= 4,
        ],
        'points_500' => [
            'title' => 'Sběratel bodů', 'desc' => 'Nasbírej 500 bodů', 'icon' => '⭐',
            'cond' => fn($s, $g) => $s['points'] >= 500,
        ],
        'points_2000' => [
            'title' => 'Bodový magnát', 'desc' => 'Nasbírej 2 000 bodů', 'icon' => '👑',
            'cond' => fn($s, $g) => $s['points'] >= 2000,
        ],
        'czech_master' => [
            'title' => 'Češtinář', 'desc' => 'Zahraj 10 kol češtiny', 'icon' => '✍️',
            'cond' => fn($s, $g) => $s['czech_games'] >= 10,
        ],
        'math_master' => [
            'title' => 'Počtář', 'desc' => 'Zahraj 10 kol matematiky', 'icon' => '🧮',
            'cond' => fn($s, $g) => $s['math_games'] >= 10,
        ],
        'english_master' => [
            'title' => 'Angličtinář', 'desc' => 'Zahraj 10 kol angličtiny', 'icon' => '🇬🇧',
            'cond' => fn($s, $g) => $s['english_games'] >= 10,
        ],
    ];
}

/** Souhrnné statistiky hráče pro vyhodnocení odznaků (jeden průchod tabulkou) */
function achievementStats(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*)                                            AS games,
               COALESCE(SUM(points), 0)                            AS points,
               COALESCE(MAX(CASE WHEN game_type IN ('classic','timed','blind','duel')
                                 THEN wpm END), 0)                 AS best_typing_wpm,
               SUM(CASE WHEN accuracy >= 100 THEN 1 ELSE 0 END)    AS perfect_games,
               SUM(CASE WHEN game_type = 'czech' THEN 1 ELSE 0 END) AS czech_games,
               SUM(CASE WHEN game_type = 'math'  THEN 1 ELSE 0 END) AS math_games,
               SUM(CASE WHEN game_type = 'english' THEN 1 ELSE 0 END) AS english_games,
               COUNT(DISTINCT CASE
                     WHEN game_type IN ('classic','timed','blind','duel') THEN 'psani'
                     WHEN game_type LIKE 'geography%'                     THEN 'zemepis'
                     ELSE game_type END)                           AS subjects
        FROM game_sessions WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $s = $stmt->fetch() ?: [];

    return [
        'games'           => (int)($s['games'] ?? 0),
        'points'          => (int)($s['points'] ?? 0),
        'best_typing_wpm' => (float)($s['best_typing_wpm'] ?? 0),
        'perfect_games'   => (int)($s['perfect_games'] ?? 0),
        'czech_games'     => (int)($s['czech_games'] ?? 0),
        'math_games'      => (int)($s['math_games'] ?? 0),
        'english_games'   => (int)($s['english_games'] ?? 0),
        'subjects'        => (int)($s['subjects'] ?? 0),
        'streak'          => currentStreak($userId),
    ];
}

/** Kolik dní po sobě (včetně dneška) hráč hrál */
function currentStreak(int $userId): int {
    $stmt = getDB()->prepare('SELECT DISTINCT DATE(played_at) AS d FROM game_sessions WHERE user_id = ? ORDER BY d DESC LIMIT 60');
    $stmt->execute([$userId]);
    $days = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$days) return 0;

    $today     = new DateTimeImmutable('today');
    $yesterday = $today->modify('-1 day');
    $first     = new DateTimeImmutable($days[0]);
    // Série se počítá jen když hráč hrál dnes nebo včera
    if ($first->format('Y-m-d') !== $today->format('Y-m-d')
        && $first->format('Y-m-d') !== $yesterday->format('Y-m-d')) return 0;

    $streak   = 1;
    $expected = $first->modify('-1 day');
    foreach (array_slice($days, 1) as $d) {
        if ($d === $expected->format('Y-m-d')) {
            $streak++;
            $expected = $expected->modify('-1 day');
        } else {
            break;
        }
    }
    return $streak;
}

/** Odznaky, které už hráč má (klíč => datum získání) */
function earnedAchievements(int $userId): array {
    try {
        $stmt = getDB()->prepare('SELECT achievement_key, earned_at FROM achievements WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Vyhodnotí odznaky po dohrané hře a nové uloží.
 * @return array<array{key:string, title:string, desc:string, icon:string}> nově získané
 */
function checkAchievements(int $userId, array $lastGame): array {
    try {
        $earned = earnedAchievements($userId);
        $defs   = achievementDefs();
        $todo   = array_diff_key($defs, $earned);
        if (!$todo) return [];

        $stats = achievementStats($userId);
        $new   = [];
        $stmt  = getDB()->prepare('INSERT INTO achievements (user_id, achievement_key) VALUES (?, ?)');

        foreach ($todo as $key => $def) {
            if (($def['cond'])($stats, $lastGame)) {
                try {
                    $stmt->execute([$userId, $key]);
                } catch (PDOException $e) {
                    continue; // souběžné uložení téhož odznaku — přeskoč
                }
                $new[] = ['key' => $key, 'title' => $def['title'], 'desc' => $def['desc'], 'icon' => $def['icon']];
            }
        }
        return $new;
    } catch (PDOException $e) {
        return []; // odznaky nikdy nesmí shodit ukládání výsledku
    }
}
