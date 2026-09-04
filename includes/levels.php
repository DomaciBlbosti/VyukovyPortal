<?php
/**
 * Level systém — body za odehrané hry, levely a multiplikátory.
 *
 * Body se počítají při ukládání výsledku a rovnou se uloží do
 * game_sessions.points, takže pozdější změna multiplikátoru nepřepíše
 * historii (děti by přišly o už získané body).
 */
require_once __DIR__ . '/../config/db.php';

// Výchozí multiplikátory — použijí se při první instalaci a jako záloha
// pro herní typ, který ještě v tabulce není.
const DEFAULT_MULTIPLIERS = [
    'classic'       => ['Klasický režim',  1.00],
    'timed'         => ['Časový závod',    1.20],
    'blind'         => ['Slepý režim',     1.50],
    'duel'          => ['Souboj hráčů',    1.20],
    'math'          => ['Matematika',      1.00],
    'geography'     => ['Zeměpis — otázky', 1.00],
    'geography_map' => ['Zeměpis — slepé mapy', 1.30],
    'czech'         => ['Čeština — pravopis', 1.20],
    'english'       => ['Angličtina — slovíčka', 1.10],
    'sada'          => ['Sady z učebnic',  1.20],
];

// Výchozí levely (level => [body, název, ikona])
const DEFAULT_LEVELS = [
    1  => [0,     'Nováček',        '🌱'],
    2  => [100,   'Učeň',           '📗'],
    3  => [250,   'Pokročilý',      '📘'],
    4  => [500,   'Zkušený',        '📙'],
    5  => [900,   'Šikula',         '⭐'],
    6  => [1400,  'Expert',         '🌟'],
    7  => [2000,  'Mistr',          '🏅'],
    8  => [3000,  'Velmistr',       '🥈'],
    9  => [4500,  'Šampion',        '🥇'],
    10 => [6500,  'Legenda',        '👑'],
];

/** Multiplikátory všech her (game_type => ['label' => …, 'multiplier' => …]) */
function getMultipliers(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    foreach (DEFAULT_MULTIPLIERS as $type => [$label, $mult]) {
        $cache[$type] = ['label' => $label, 'multiplier' => (float)$mult];
    }
    try {
        foreach (getDB()->query('SELECT game_type, label, multiplier FROM game_multipliers') as $row) {
            $cache[$row['game_type']] = [
                'label'      => $row['label'],
                'multiplier' => (float)$row['multiplier'],
            ];
        }
    } catch (PDOException $e) {
        // Tabulka ještě neexistuje (před migrací) — jedeme na výchozích
    }
    return $cache;
}

/** Levely seřazené vzestupně: [['level_number'=>…, 'points_required'=>…, 'title'=>…, 'icon'=>…], …] */
function getLevels(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $rows = getDB()->query('SELECT level_number, points_required, title, icon FROM levels ORDER BY points_required ASC')->fetchAll();
    } catch (PDOException $e) {
        $rows = [];
    }
    if (!$rows) {
        $rows = [];
        foreach (DEFAULT_LEVELS as $num => [$pts, $title, $icon]) {
            $rows[] = ['level_number' => $num, 'points_required' => $pts, 'title' => $title, 'icon' => $icon];
        }
    }
    $cache = array_map(fn($r) => [
        'level_number'    => (int)$r['level_number'],
        'points_required' => (int)$r['points_required'],
        'title'           => $r['title'],
        'icon'            => $r['icon'],
    ], $rows);
    return $cache;
}

/**
 * Body za jednu odehranou hru.
 * Základ = zvládnuté „jednotky" (u psaní slova = znaky/5, u kvízů počet
 * správných odpovědí), krát faktor přesnosti (0.5–1.0), krát multiplikátor hry.
 */
function calculatePoints(array $data): int {
    $type     = $data['game_type'] ?? 'classic';
    $accuracy = max(0, min(100, (float)($data['accuracy'] ?? 0)));
    $units    = max(0, (int)($data['chars_typed'] ?? 0));

    // Psací hry posílají počet znaků, kvízy rovnou počet správných odpovědí
    if (in_array($type, ['classic', 'timed', 'blind', 'duel'], true)) {
        $units = $units / 5; // slova
    }

    $mults      = getMultipliers();
    $multiplier = $mults[$type]['multiplier'] ?? 1.0;
    $accFactor  = 0.5 + $accuracy / 200; // 0 % → 0.5, 100 % → 1.0

    $points = (int)round($units * $accFactor * $multiplier);
    return $units > 0 ? max(1, $points) : 0;
}

/** Celkový počet bodů uživatele */
function getUserPoints(int $userId): int {
    try {
        $stmt = getDB()->prepare('SELECT COALESCE(SUM(points), 0) FROM game_sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0; // sloupec points ještě není (před migrací)
    }
}

/**
 * Level odpovídající počtu bodů + postup k dalšímu.
 * Vrací: level, title, icon, points, next_level, next_points, progress (0–100)
 */
function levelForPoints(int $points): array {
    $levels  = getLevels();
    $current = $levels[0] ?? ['level_number' => 1, 'points_required' => 0, 'title' => 'Nováček', 'icon' => '🌱'];
    $next    = null;

    foreach ($levels as $lvl) {
        if ($points >= $lvl['points_required']) {
            $current = $lvl;
        } else {
            $next = $lvl;
            break;
        }
    }

    $span     = $next ? max(1, $next['points_required'] - $current['points_required']) : 1;
    $progress = $next ? (int)round(($points - $current['points_required']) / $span * 100) : 100;

    return [
        'level'       => $current['level_number'],
        'title'       => $current['title'],
        'icon'        => $current['icon'],
        'points'      => $points,
        'next_level'  => $next['level_number']    ?? null,
        'next_points' => $next['points_required'] ?? null,
        'remaining'   => $next ? $next['points_required'] - $points : 0,
        'progress'    => max(0, min(100, $progress)),
    ];
}

/** Level uživatele (zkratka pro getUserPoints + levelForPoints) */
function getUserLevel(int $userId): array {
    return levelForPoints(getUserPoints($userId));
}
