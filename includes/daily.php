<?php
/**
 * Denní úkol a série.
 *
 * Sérii (kolik dní po sobě se hrálo) už počítá chybovník odznaků; tady ji
 * jen zpřístupníme rozcestníku, aby ji dítě vidělo. Bez toho se o ní dozví
 * až ve chvíli, kdy dostane odznak — a to je na budování návyku pozdě.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/achievements.php';

/** Kolik kol denně považujeme za splněný úkol */
const DAILY_GOAL = 3;

/**
 * @return array{streak:int, today:int, goal:int, done:bool, percent:int, played_today:bool}
 */
function dailyStats(int $userId): array {
    $today = 0;
    if ($userId) {
        try {
            // Půlnoc počítáme v PHP, ať dotaz nezávisí na SQL dialektu
            $stmt = getDB()->prepare(
                'SELECT COUNT(*) FROM game_sessions WHERE user_id = ? AND played_at >= ?'
            );
            $stmt->execute([$userId, (new DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);
            $today = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $today = 0;
        }
    }

    $streak = $userId ? currentStreak($userId) : 0;

    return [
        'streak'       => $streak,
        'today'        => $today,
        'goal'         => DAILY_GOAL,
        'done'         => $today >= DAILY_GOAL,
        'percent'      => (int)round(min(1, $today / DAILY_GOAL) * 100),
        'played_today' => $today > 0,
    ];
}

/** „4 dny", „1 den", „5 dní" */
function dayWord(int $n): string {
    if ($n === 1) return 'den';
    return $n < 5 ? 'dny' : 'dní';
}
