<?php
/**
 * Domácí úkoly — co má dítě splnit každý den.
 *
 * Rodič dítěti založí plán: seznam úloh z katalogu (sada z učebnice, řada
 * násobilky, maraton …), kolikrát a s jakou přesností, ve které dny. Každý
 * naplánovaný den z toho vznikne úkol: buď další úloha ze seznamu (rotace —
 * „každý den jedna sada“), nebo všechny naráz. Splnění se vyhodnocuje po
 * každé dohrané hře stejně jako u výzev.
 *
 * Nesplněný úkol nezmizí — zůstává jako rest, dokud ho dítě nedožene nebo
 * rodič neodpustí. Plán může mít zámek: dokud je úkol otevřený, ostatní hry
 * se neotevřou (requireLogin pošle dítě zpět na rozcestník).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/catalog.php';

/** Jak dlouho se resty drží v přehledu a počítají do zámku */
const HOMEWORK_OVERDUE_DAYS = 30;

const HOMEWORK_MODES = [
    'rotate' => 'každý den další úloha ze seznamu (postupně dokola)',
    'all'    => 'každý den všechny úlohy ze seznamu',
];

const HOMEWORK_WEEKDAYS = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];

// ── Plány ──

/** Plány (všechny, nebo jednoho dítěte) i s úlohami a jménem dítěte */
function homeworkPlans(int $userId = 0, bool $onlyActive = false): array {
    try {
        $sql = 'SELECT p.*, u.display_name, u.username FROM homework_plans p JOIN users u ON u.id = p.user_id';
        $where = [];
        $args  = [];
        if ($userId)    { $where[] = 'p.user_id = ?'; $args[] = $userId; }
        if ($onlyActive) { $where[] = 'p.active = 1'; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY u.display_name ASC, p.id ASC';
        $stmt = getDB()->prepare($sql);
        $stmt->execute($args);
        $plans = $stmt->fetchAll();
        $items = getDB()->prepare('SELECT * FROM homework_items WHERE plan_id = ? ORDER BY position ASC, id ASC');
        foreach ($plans as &$p) {
            $items->execute([$p['id']]);
            $p['items'] = $items->fetchAll();
        }
        unset($p);
        return $plans;
    } catch (PDOException $e) {
        return [];
    }
}

function getHomeworkPlan(int $id): ?array {
    foreach (homeworkPlans() as $p) {
        if ((int)$p['id'] === $id) return $p;
    }
    return null;
}

/**
 * Uloží plán i s úlohami. $items: seznam „game_type|topic“ z katalogu.
 * Vrací ID, nebo 0 s chybou v $error.
 */
function saveHomeworkPlan(array $in, array $items, int $id, ?string &$error = null): int {
    $error  = null;
    $userId = (int)($in['user_id'] ?? 0);
    $title  = trim((string)($in['title'] ?? '')) ?: 'Domácí úkol';
    $mode   = isset(HOMEWORK_MODES[(string)($in['mode'] ?? '')]) ? (string)$in['mode'] : 'rotate';
    $rounds = max(1, min(20, (int)($in['rounds'] ?? 1)));
    $minAcc = max(0, min(100, (int)($in['min_accuracy'] ?? 80)));
    $days   = '';
    for ($d = 0; $d < 7; $d++) $days .= !empty($in['weekdays'][$d]) ? '1' : '0';
    if ($days === '0000000') { $error = 'Vyber aspoň jeden den v týdnu.'; return 0; }
    if (!$userId) { $error = 'Vyber dítě.'; return 0; }

    $clean = [];
    foreach ($items as $task) {
        [$game, $topic] = array_pad(explode('|', (string)$task, 2), 2, '');
        if (catalogHas($game, $topic)) $clean[] = [$game, $topic];
    }
    if (!$clean) { $error = 'Přidej do plánu aspoň jednu úlohu.'; return 0; }

    try {
        $db = getDB();
        $db->beginTransaction();
        if ($id) {
            $db->prepare('UPDATE homework_plans SET user_id = ?, title = ?, mode = ?, rounds = ?, min_accuracy = ?, weekdays = ?, lock_games = ?, active = ? WHERE id = ?')
               ->execute([$userId, mb_substr($title, 0, 120), $mode, $rounds, $minAcc, $days, !empty($in['lock_games']) ? 1 : 0, !empty($in['active']) ? 1 : 0, $id]);
            $db->prepare('DELETE FROM homework_items WHERE plan_id = ?')->execute([$id]);
        } else {
            $db->prepare('INSERT INTO homework_plans (user_id, title, mode, rounds, min_accuracy, weekdays, lock_games, active, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
               ->execute([$userId, mb_substr($title, 0, 120), $mode, $rounds, $minAcc, $days, !empty($in['lock_games']) ? 1 : 0, !empty($in['active']) ? 1 : 0,
                          (int)($in['created_by'] ?? 0) ?: null, date('Y-m-d H:i:s')]);
            $id = (int)$db->lastInsertId();
        }
        $ins = $db->prepare('INSERT INTO homework_items (plan_id, position, game_type, topic) VALUES (?,?,?,?)');
        foreach ($clean as $i => [$game, $topic]) $ins->execute([$id, $i, $game, $topic]);
        $db->commit();
        return $id;
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        $error = 'Plán se nepodařilo uložit.';
        return 0;
    }
}

function deleteHomeworkPlan(int $id): bool {
    try {
        return getDB()->prepare('DELETE FROM homework_plans WHERE id = ?')->execute([$id]);
    } catch (PDOException $e) {
        return false;
    }
}

/** Je na tenhle den plán naplánovaný? (pondělí = první znak) */
function homeworkScheduled(array $plan, DateTimeInterface $day): bool {
    $w = str_pad((string)$plan['weekdays'], 7, '1');
    return $w[(int)$day->format('N') - 1] === '1';
}

// ── Denní úkoly ──

/**
 * Založí dnešní úkoly dítěte, pokud ještě nejsou. Volá se při každém
 * pohledu na rozcestník a před vyhodnocením hry — den, kdy se dítě
 * nepřihlásí, tak žádný úkol nedostane a nepočítá se mu jako rest, ale
 * v přehledu rodiče je vidět jako vynechaný.
 */
function ensureHomeworkToday(int $userId): void {
    if (!$userId) return;
    $today = new DateTimeImmutable('today');
    try {
        $db    = getDB();
        $count = $db->prepare('SELECT COUNT(*) FROM homework_days WHERE plan_id = ? AND day = ?');
        $prev  = $db->prepare('SELECT COUNT(DISTINCT day) FROM homework_days WHERE plan_id = ? AND day < ?');
        $ins   = $db->prepare('INSERT INTO homework_days (plan_id, user_id, day, item_pos, game_type, topic, rounds, min_accuracy) VALUES (?,?,?,?,?,?,?,?)');
        foreach (homeworkPlans($userId, true) as $plan) {
            if (!$plan['items'] || !homeworkScheduled($plan, $today)) continue;
            $count->execute([$plan['id'], $today->format('Y-m-d')]);
            if ((int)$count->fetchColumn() > 0) continue;

            $items = $plan['items'];
            if ($plan['mode'] === 'rotate') {
                $prev->execute([$plan['id'], $today->format('Y-m-d')]);
                $items = [$items[(int)$prev->fetchColumn() % count($items)]];
            }
            foreach ($items as $it) {
                $ins->execute([$plan['id'], $userId, $today->format('Y-m-d'), (int)$it['position'], $it['game_type'], $it['topic'],
                               max(1, (int)$plan['rounds']), (int)$plan['min_accuracy']]);
            }
        }
    } catch (PDOException $e) {
    }
}

/** Řádek úkolu doplněný o popisek, odkaz a pár odvozených hodnot */
function homeworkDecorate(array $row): array {
    $row['label']       = catalogLabel((string)$row['game_type'], (string)$row['topic']);
    $row['url']         = catalogUrl((string)$row['game_type'], (string)$row['topic']);
    $row['rounds']      = max(1, (int)$row['rounds']);
    $row['done_rounds'] = min((int)$row['done_rounds'], $row['rounds']);
    $row['done']        = $row['completed_at'] !== null;
    $row['is_today']    = $row['day'] === date('Y-m-d');
    return $row;
}

/**
 * Co má dítě splnit: dnešní úkoly (hotové i ne) a resty z minulých dnů.
 *
 * @return array{today:array, overdue:array, open:int, lock:bool, plans:int}
 */
function homeworkStatus(int $userId, bool $ensure = true): array {
    $out = ['today' => [], 'overdue' => [], 'open' => 0, 'lock' => false, 'plans' => 0];
    if (!$userId) return $out;
    if ($ensure) ensureHomeworkToday($userId);
    try {
        $since = (new DateTimeImmutable('today'))->modify('-' . HOMEWORK_OVERDUE_DAYS . ' days')->format('Y-m-d');
        $stmt  = getDB()->prepare('SELECT d.*, p.title AS plan_title, p.lock_games, p.active
                                   FROM homework_days d JOIN homework_plans p ON p.id = d.plan_id
                                   WHERE d.user_id = ? AND d.day >= ? AND (d.day = ? OR d.completed_at IS NULL)
                                   ORDER BY d.day ASC, d.item_pos ASC');
        $stmt->execute([$userId, $since, date('Y-m-d')]);
        foreach ($stmt->fetchAll() as $r) {
            $r = homeworkDecorate($r);
            if ($r['is_today']) $out['today'][] = $r; else $out['overdue'][] = $r;
            if (!$r['done']) {
                $out['open']++;
                if ((int)$r['lock_games'] === 1 && (int)$r['active'] === 1) $out['lock'] = true;
            }
        }
        $out['plans'] = count(homeworkPlans($userId, true));
    } catch (PDOException $e) {
    }
    return $out;
}

/**
 * Smí dítě otevřít právě požadovanou hru? Když má otevřený úkol z plánu se
 * zámkem, smí jen na hry svých otevřených úkolů (dnešních i restů).
 */
function homeworkAllowsCurrentGame(int $userId): bool {
    $st = homeworkStatus($userId);
    if (!$st['lock']) return true;
    foreach (array_merge($st['today'], $st['overdue']) as $r) {
        if (!$r['done'] && homeworkUrlMatches($r)) return true;
    }
    return false;
}

/** Vede právě otevřená adresa na úkol (stejná hra, stejné parametry)? */
function homeworkUrlMatches(array $row): bool {
    $want = parse_url((string)$row['url']);
    if (basename((string)($want['path'] ?? '')) !== basename((string)($_SERVER['SCRIPT_NAME'] ?? ''))) return false;
    parse_str((string)($want['query'] ?? ''), $params);
    foreach ($params as $k => $v) {
        if ((string)($_GET[$k] ?? '') !== (string)$v) return false;
    }
    return true;
}

/**
 * Započítá dohranou hru do úkolů. Jedna hra posune nejvýš jeden úkol
 * (dnešní má přednost, pak nejstarší rest).
 *
 * @param array<string> $topics sady, které se ve hře opravdu hrály
 * @return array{done:array<string>, all_done:bool}
 */
function recordHomeworkPlay(int $userId, string $gameType, array $topics, float $accuracy): array {
    $out = ['done' => [], 'all_done' => false];
    if (!$userId || $gameType === '') return $out;
    if (!$topics) $topics = [''];

    $st = homeworkStatus($userId);
    if ($st['open'] === 0) return $out;

    foreach (array_merge($st['today'], $st['overdue']) as $r) {
        if ($r['done']) continue;
        $hit = false;
        foreach ($topics as $t) {
            if (catalogMatches((string)$r['game_type'], (string)$r['topic'], $gameType, (string)$t)) { $hit = true; break; }
        }
        if (!$hit) continue;

        try {
            $db   = getDB();
            $best = max((float)$r['best_accuracy'], $accuracy);
            if ($accuracy + 0.0001 < (int)$r['min_accuracy']) {
                $db->prepare('UPDATE homework_days SET best_accuracy = ? WHERE id = ?')->execute([$best, $r['id']]);
                return $out;   // hrálo se, ale málo přesně — další pokus
            }
            $next = $r['done_rounds'] + 1;
            $done = $next >= $r['rounds'];
            $db->prepare('UPDATE homework_days SET done_rounds = ?, best_accuracy = ?, completed_at = ? WHERE id = ?')
               ->execute([$next, $best, $done ? date('Y-m-d H:i:s') : null, $r['id']]);
            if ($done) {
                $out['done'][]   = $r['label'];
                $out['all_done'] = $st['open'] <= 1;
            }
        } catch (PDOException $e) {
        }
        return $out;
    }
    return $out;
}

// ── Přehled pro rodiče ──

/**
 * Posledních N dní plánu: pro každý den stav
 * done / partial / open / missed / today / off (nenaplánovaný).
 */
function homeworkHistory(array $plan, int $days = 14): array {
    $out   = [];
    $today = new DateTimeImmutable('today');
    $rows  = [];
    try {
        $stmt = getDB()->prepare('SELECT day, COUNT(*) AS n, SUM(CASE WHEN completed_at IS NULL THEN 0 ELSE 1 END) AS done
                                  FROM homework_days WHERE plan_id = ? AND day >= ? GROUP BY day');
        $stmt->execute([$plan['id'], $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d')]);
        foreach ($stmt->fetchAll() as $r) $rows[(string)$r['day']] = $r;
    } catch (PDOException $e) {
    }
    $since = substr((string)($plan['created_at'] ?? ''), 0, 10);   // před založením plánu nebylo co plnit
    for ($i = $days - 1; $i >= 0; $i--) {
        $d   = $today->modify('-' . $i . ' days');
        $key = $d->format('Y-m-d');
        $r   = $rows[$key] ?? null;
        if (!$r) {
            $state = (!homeworkScheduled($plan, $d) || ($since !== '' && $key < $since)) ? 'off' : ($i === 0 ? 'today' : 'missed');
        } elseif ((int)$r['done'] >= (int)$r['n']) {
            $state = 'done';
        } elseif ((int)$r['done'] > 0) {
            $state = 'partial';
        } else {
            $state = $i === 0 ? 'today' : 'open';
        }
        $out[] = ['day' => $key, 'label' => $d->format('j.n.'), 'weekday' => HOMEWORK_WEEKDAYS[(int)$d->format('N') - 1], 'state' => $state];
    }
    return $out;
}

/** Odpustí resty plánu (smaže nesplněné úkoly z minulých dnů) */
function forgiveHomework(int $planId): int {
    try {
        $stmt = getDB()->prepare('DELETE FROM homework_days WHERE plan_id = ? AND day < ? AND completed_at IS NULL');
        $stmt->execute([$planId, date('Y-m-d')]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Kolik restů má plán */
function homeworkOverdueCount(int $planId): int {
    try {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM homework_days WHERE plan_id = ? AND day < ? AND completed_at IS NULL');
        $stmt->execute([$planId, date('Y-m-d')]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/** <option> se všemi úlohami katalogu, seskupené po předmětech */
function catalogTaskOptions(string $selected = ''): void {
    foreach (catalogTasks() as $gameType => $cat) {
        echo '<optgroup label="' . htmlspecialchars($cat['icon'] . ' ' . $cat['label']) . '">';
        foreach ($cat['items'] as $topic => $item) {
            $value = $gameType . '|' . $topic;
            echo '<option value="' . htmlspecialchars($value) . '"'
               . ($value === $selected ? ' selected' : '') . '>'
               . htmlspecialchars($item['label']) . '</option>';
        }
        echo '</optgroup>';
    }
}
