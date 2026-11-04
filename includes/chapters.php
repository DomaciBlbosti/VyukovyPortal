<?php
/**
 * Kapitoly učebnice a hromadné skládání sad.
 *
 * Album je hromada stránek; kapitola je souvislý rozsah stránek, který patří
 * k sobě (lekce 1A, Unit 3, Revision …). Rozdělení navrhne textový model
 * z nadpisů stránek a admin si ho opraví. Z kapitol pak jde skládat sady —
 * jednu ručně v Tvorbě sad, nebo hromadně: do fronty se zařadí „kapitola ×
 * typ sady“, dojíždí ji prohlížeč nebo cron a hotová sada se rovnou uloží.
 */
require_once __DIR__ . '/ocr.php';
require_once __DIR__ . '/sets.php';
require_once __DIR__ . '/llm.php';

/** Po kolika minutách se rozpracované skládání bere jako sirotek */
const SET_BUILD_STALE = 15 * 60;

/** Jak se jmenuje sada podle typu — přípona za názvem kapitoly */
const SET_KIND_SUFFIX = [
    'dvojice'     => 'slovíčka',
    'vyber'       => 'výběr',
    'doplnovacka' => 'doplňovačka',
    'cteni'       => 'čtení',
];

// ── Kapitoly ──

/** Kapitoly alba v pořadí */
function chapters(int $jobId): array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_chapters WHERE job_id = ? ORDER BY position ASC, page_from ASC, id ASC');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getChapter(int $id): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_chapters WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Srovná kapitolu do tvaru k uložení: název povinný, rozsah stránek
 * v mezích alba a od ≤ do (prohozené meze se otočí, ne odmítnou).
 *
 * @return array{ok:bool, error:string, data:array}
 */
function tidyChapter(array $in, int $pageCount): array {
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') return ['ok' => false, 'error' => 'Kapitola musí mít název.', 'data' => []];
    if ($pageCount < 1) return ['ok' => false, 'error' => 'Album nemá žádné stránky.', 'data' => []];

    $from = (int)($in['page_from'] ?? 0);
    $to   = (int)($in['page_to'] ?? 0);
    if ($from < 1 || $to < 1) return ['ok' => false, 'error' => 'Zadej rozsah stránek (od–do).', 'data' => []];
    if ($from > $to) [$from, $to] = [$to, $from];
    if ($from > $pageCount) return ['ok' => false, 'error' => 'Album má jen ' . $pageCount . ' stránek.', 'data' => []];
    $to = min($to, $pageCount);

    return ['ok' => true, 'error' => '', 'data' => [
        'title'     => mb_substr($title, 0, 160),
        'label'     => mb_substr(trim((string)($in['label'] ?? '')), 0, 20),
        'note'      => mb_substr(trim((string)($in['note'] ?? '')), 0, 255),
        'page_from' => $from,
        'page_to'   => $to,
    ]];
}

/** Uloží kapitolu (nová nebo změna). Vrací ID, nebo 0 s chybou v ocrLastError. */
function saveChapter(int $jobId, array $in, int $id = 0): int {
    $t = tidyChapter($in, count(ocrPages($jobId)));
    if (!$t['ok']) { $err = &ocrLastError(); $err = $t['error']; return 0; }
    $d = $t['data'];
    try {
        $db = getDB();
        if ($id) {
            $db->prepare('UPDATE ocr_chapters SET title = ?, label = ?, note = ?, page_from = ?, page_to = ? WHERE id = ? AND job_id = ?')
               ->execute([$d['title'], $d['label'], $d['note'], $d['page_from'], $d['page_to'], $id, $jobId]);
            return $id;
        }
        $pos = $db->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM ocr_chapters WHERE job_id = ?');
        $pos->execute([$jobId]);
        $db->prepare('INSERT INTO ocr_chapters (job_id, position, label, title, note, page_from, page_to, created_at) VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$jobId, (int)$pos->fetchColumn(), $d['label'], $d['title'], $d['note'], $d['page_from'], $d['page_to'], date('Y-m-d H:i:s')]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        ocrFail($e);
        return 0;
    }
}

function deleteChapter(int $id): bool {
    try {
        return getDB()->prepare('DELETE FROM ocr_chapters WHERE id = ?')->execute([$id]);
    } catch (PDOException $e) {
        return ocrFail($e);
    }
}

/**
 * Nahradí kapitoly alba novým seznamem (návrh od modelu). Dělá se v jedné
 * transakci — když se nepovede vložit, staré kapitoly zůstanou.
 */
function replaceChapters(int $jobId, array $list): int {
    $pageCount = count(ocrPages($jobId));
    try {
        $db = getDB();
        $db->beginTransaction();
        $db->prepare('DELETE FROM ocr_chapters WHERE job_id = ?')->execute([$jobId]);
        $ins = $db->prepare('INSERT INTO ocr_chapters (job_id, position, label, title, note, page_from, page_to, created_at) VALUES (?,?,?,?,?,?,?,?)');
        $n = 0;
        foreach ($list as $c) {
            $t = tidyChapter($c, $pageCount);
            if (!$t['ok']) continue;
            $d = $t['data'];
            $ins->execute([$jobId, $n, $d['label'], $d['title'], $d['note'], $d['page_from'], $d['page_to'], date('Y-m-d H:i:s')]);
            $n++;
        }
        $db->commit();
        return $n;
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        ocrFail($e);
        return 0;
    }
}

/** Stránky kapitoly (podle pořadí v albu, počítáno od 1) */
function chapterPages(array $chapter, ?array $pages = null): array {
    $pages ??= ocrPages((int)$chapter['job_id']);
    $from = (int)$chapter['page_from'];
    $to   = (int)$chapter['page_to'];
    $out  = [];
    foreach (array_values($pages) as $i => $p) {
        $n = $i + 1;
        if ($n >= $from && $n <= $to) $out[] = $p;
    }
    return $out;
}

/** Text kapitoly — přepisy jejích stránek za sebou (ručně opravené mají přednost) */
function chapterText(array $chapter, ?array $pages = null): string {
    $parts = [];
    foreach (chapterPages($chapter, $pages) as $p) {
        $t = pageText($p);
        if ($t !== '') $parts[] = $t;
    }
    return implode("\n\n", $parts);
}

/** „1A My daily life“ nebo jen název, když kapitola označení nemá */
function chapterLabel(array $chapter): string {
    $label = trim((string)$chapter['label']);
    $title = trim((string)$chapter['title']);
    return $label !== '' && !str_starts_with($title, $label) ? $label . ' ' . $title : $title;
}

// ── Návrh kapitol modelem ──

/**
 * Osnova alba pro model: ke každé stránce pár prvních řádků. Celé přepisy
 * by byly na desítky tisíc tokenů; na rozdělení do kapitol stačí nadpisy.
 */
function chapterOutline(array $pages, int $linesPerPage = 6, int $lineLen = 70): string {
    $out = [];
    foreach (array_values($pages) as $i => $p) {
        $text  = pageText($p);
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim((string)preg_replace('/[#*_|~`>]+/u', ' ', $line));
            $line = trim((string)preg_replace('/\s+/u', ' ', $line));
            if ($line === '' || preg_match('/^\[obrázek \d+\]$/u', $line) || preg_match('/^-+$/', $line)) continue;
            $lines[] = mb_strlen($line) > $lineLen ? mb_substr($line, 0, $lineLen) . '…' : $line;
            if (count($lines) >= $linesPerPage) break;
        }
        $out[] = 'Stránka ' . ($i + 1) . ': ' . ($lines ? implode(' | ', $lines) : '(bez textu)');
    }
    return implode("\n", $out);
}

function chapterSuggestPrompt(string $outline, string $granularity, array $job): string {
    $what = $granularity === 'unit'
        ? "Kapitola = jedna celá lekce učebnice (Unit 1, Unit 2 …, případně Úvod a závěrečné přehledy),\n"
          . "všechny její části (A, B, C, D, Culture, Revision, Project) patří dohromady."
        : "Kapitola = jedna část lekce (1A, 1B, 1C, 1D, Culture, English Across the Curriculum, Revision,\n"
          . "Your Project …). Úvodní a závěrečné stránky (obsah, slovníček, přehled mluvnice, nepravidelná\n"
          . "slovesa, příprava na testování) rozděl podle lekcí, ke kterým patří, nebo z nich udělej\n"
          . "samostatné kapitoly.";
    $subject = subjectGradeLabel((string)($job['subject'] ?? ''), (int)($job['grade'] ?? 0));

    return "Tohle je osnova naskenované učebnice (" . $subject . ", „" . ($job['title'] ?? '') . "“):\n"
        . "ke každé stránce v pořadí, jak jdou v albu, prvních pár řádků přepisu.\n"
        . "Rozděl stránky do kapitol.\n\n"
        . $what . "\n\n"
        . "Vrať POUZE JSON v tomhle tvaru:\n"
        . "{\"kapitoly\": [{\"oznaceni\": \"1A\", \"nazev\": \"My daily life\", \"od\": 6, \"do\": 7, \"poznamka\": \"present simple, řadové číslovky\"}]}\n\n"
        . "Pravidla:\n"
        . "- „od“ a „do“ jsou čísla stránek z osnovy (včetně), kapitoly jdou za sebou a nepřekrývají se\n"
        . "- každá stránka s textem má patřit do nějaké kapitoly; stránku bez textu vynech\n"
        . "- „oznaceni“ je krátké označení z učebnice (1A, Unit 3, Revision 2), když není, nech prázdné\n"
        . "- „nazev“ je název tak, jak ho má učebnice; nepřekládej ho\n"
        . "- „poznamka“ je jedna krátká věta česky: jaká mluvnice a slovní zásoba se tam probírá\n"
        . "- stránky mohou být naskenované pozpátku — pak jdou čísla kapitol v osnově sestupně, to je v pořádku,\n"
        . "  rozsah „od“–„do“ dej i tak od menšího čísla k většímu\n\n"
        . "Osnova:\n" . $outline;
}

/**
 * Nechá model navrhnout kapitoly alba.
 *
 * @return array{ok:bool, chapters:array, error:string, warning:string}
 */
function llmSuggestChapters(int $jobId, string $granularity = 'lesson', string $modelOverride = ''): array {
    $model = trim($modelOverride) ?: llmModel('text');
    if ($model === '') return ['ok' => false, 'chapters' => [], 'error' => 'Není vybraný textový model.', 'warning' => ''];

    $job   = getOcrJob($jobId);
    $pages = ocrPages($jobId);
    if (!$job || !$pages) return ['ok' => false, 'chapters' => [], 'error' => 'Album nemá stránky.', 'warning' => ''];
    if (!array_filter($pages, fn($p) => pageText($p) !== '')) {
        return ['ok' => false, 'chapters' => [], 'error' => 'Žádná stránka alba ještě nemá přepis.', 'warning' => ''];
    }

    $outline = chapterOutline($pages);
    $prompt  = chapterSuggestPrompt($outline, $granularity, $job);

    $warning = '';
    if (modelIsLocal($model)) {
        $needed = estimateTokens($prompt) + 1500;
        if ($needed > proxyContextSize()) {
            $warning = 'Osnova je na nastavený kontext (' . proxyContextSize() . ' tokenů) dlouhá — odhadem je potřeba kolem '
                . $needed . '. Konec návrhu může chybět; zvyš kontext nebo to nech navrhnout komerčním modelem.';
        }
    }

    $r = proxyGenerate($model, $prompt, null, true);
    if (!$r['ok']) return ['ok' => false, 'chapters' => [], 'error' => $r['error'], 'warning' => $warning];

    $json = $r['text'];
    if (preg_match('/\{.*\}/s', $json, $m)) $json = $m[0];
    $data = json_decode($json, true);
    $raw  = is_array($data) ? ($data['kapitoly'] ?? $data['chapters'] ?? null) : null;
    if (!is_array($raw) || !$raw) {
        return ['ok' => false, 'chapters' => [], 'error' => 'Model nevrátil seznam kapitol (odpověď: '
            . mb_substr(trim($r['text']), 0, 120) . '…).', 'warning' => $warning];
    }

    $count = count($pages);
    $list  = [];
    foreach ($raw as $c) {
        if (!is_array($c)) continue;
        $t = tidyChapter([
            'title'     => (string)($c['nazev'] ?? $c['title'] ?? ''),
            'label'     => (string)($c['oznaceni'] ?? $c['label'] ?? ''),
            'note'      => (string)($c['poznamka'] ?? $c['note'] ?? ''),
            'page_from' => (int)($c['od'] ?? $c['from'] ?? 0),
            'page_to'   => (int)($c['do'] ?? $c['to'] ?? 0),
        ], $count);
        if ($t['ok']) $list[] = $t['data'];
    }
    usort($list, fn($a, $b) => $a['page_from'] <=> $b['page_from']);
    if (!$list) return ['ok' => false, 'chapters' => [], 'error' => 'Z odpovědi modelu nešla přečíst ani jedna kapitola.', 'warning' => $warning];

    return ['ok' => true, 'chapters' => $list, 'error' => '', 'warning' => $warning];
}

// ── Fronta skládání sad ──

/**
 * Zařadí do fronty sadu pro každou kombinaci kapitola × typ.
 * Vrací počet zařazených.
 */
function queueSetBuilds(int $jobId, array $chapterIds, array $kinds, string $model, int $userId): int {
    $job = getOcrJob($jobId);
    if (!$job) return 0;
    $kinds = array_values(array_filter($kinds, fn($k) => isset(SET_KINDS[$k])));
    if (!$kinds) return 0;

    $n = 0;
    try {
        $ins = getDB()->prepare('INSERT INTO set_builds (job_id, chapter_id, kind, title, source, model, status, created_by, created_at)
                                 VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($chapterIds as $cid) {
            $ch = getChapter((int)$cid);
            if (!$ch || (int)$ch['job_id'] !== $jobId) continue;
            $label = chapterLabel($ch);
            foreach ($kinds as $kind) {
                $title  = $label . ' — ' . SET_KIND_SUFFIX[$kind];
                $source = trim((string)$job['title']) !== '' ? $job['title'] . ', ' . $label : $label;
                $ins->execute([$jobId, (int)$ch['id'], $kind, mb_substr($title, 0, 120), mb_substr($source, 0, 180),
                               mb_substr(trim($model), 0, 120), 'ceka', $userId ?: null, date('Y-m-d H:i:s')]);
                $n++;
            }
        }
    } catch (PDOException $e) {
        ocrFail($e);
    }
    return $n;
}

/** Fronta a výsledky skládání u alba, nejnovější nahoře */
function setBuilds(int $jobId): array {
    try {
        $stmt = getDB()->prepare('SELECT b.*, c.title AS chapter_title, c.label AS chapter_label,
                                         (SELECT COUNT(*) FROM custom_set_items i WHERE i.set_id = b.set_id) AS item_count
                                  FROM set_builds b LEFT JOIN ocr_chapters c ON c.id = b.chapter_id
                                  WHERE b.job_id = ? ORDER BY b.id DESC');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getSetBuild(int $id): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM set_builds WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Kolik skládání čeká nebo běží (napříč alby, pro cron) */
function openSetBuildCount(int $jobId = 0): int {
    try {
        $sql = 'SELECT COUNT(*) FROM set_builds WHERE status IN (?, ?)' . ($jobId ? ' AND job_id = ?' : '');
        $stmt = getDB()->prepare($sql);
        $stmt->execute($jobId ? ['ceka', 'bezi', $jobId] : ['ceka', 'bezi']);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Zruší čekající skládání alba; co se počítá, dojede */
function cancelSetBuilds(int $jobId): int {
    try {
        $stmt = getDB()->prepare('DELETE FROM set_builds WHERE job_id = ? AND status = ?');
        $stmt->execute([$jobId, 'ceka']);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Smaže záznam o skládání (sada, pokud vznikla, zůstává) */
function deleteSetBuild(int $id): bool {
    try {
        return getDB()->prepare('DELETE FROM set_builds WHERE id = ? AND status <> ?')->execute([$id, 'bezi']);
    } catch (PDOException $e) {
        return ocrFail($e);
    }
}

/** Neúspěšné skládání vrátí do fronty */
function retrySetBuild(int $id): bool {
    try {
        return getDB()->prepare('UPDATE set_builds SET status = ?, error = ?, warning = ?, json = NULL, started_at = NULL, finished_at = NULL WHERE id = ? AND status = ?')
                      ->execute(['ceka', '', '', $id, 'chyba']);
    } catch (PDOException $e) {
        return ocrFail($e);
    }
}

/**
 * Vezme jedno skládání z fronty, složí sadu a uloží ji.
 *
 * Položky, které neprojdou kontrolou (duplicita, chybí odpověď …), se
 * vynechají a sada se uloží bez nich s varováním — u desítek sad naráz by
 * jedna špatná položka neměla shodit celou sadu. Když je chyba v hlavičce
 * nebo zbyde míň než čtyři položky, skládání skončí chybou a JSON zůstane
 * uložený, ať se dá opravit ručně v importu sad.
 *
 * @return array{done:bool, remaining:int, id:int, status:string}
 */
function processNextSetBuild(): array {
    try {
        $db = getDB();
        $stale = date('Y-m-d H:i:s', time() - SET_BUILD_STALE);
        $stmt  = $db->prepare("SELECT * FROM set_builds
                               WHERE status = 'ceka' OR (status = 'bezi' AND (started_at IS NULL OR started_at < ?))
                               ORDER BY id ASC LIMIT 1");
        $stmt->execute([$stale]);
        $build = $stmt->fetch();
        if (!$build) return ['done' => true, 'remaining' => 0, 'id' => 0, 'status' => ''];

        $db->prepare('UPDATE set_builds SET status = ?, started_at = ? WHERE id = ?')
           ->execute(['bezi', date('Y-m-d H:i:s'), $build['id']]);
    } catch (PDOException $e) {
        ocrFail($e);
        return ['done' => true, 'remaining' => 0, 'id' => 0, 'status' => ''];
    }

    $t0     = microtime(true);
    $finish = function (string $status, string $json, string $error, string $warning, int $setId) use ($db, $build, $t0): array {
        $db->prepare('UPDATE set_builds SET status = ?, json = ?, error = ?, warning = ?, set_id = ?, seconds = ?, finished_at = ? WHERE id = ?')
           ->execute([$status, $json !== '' ? $json : null, mb_substr($error, 0, 255), mb_substr($warning, 0, 255),
                      $setId ?: null, (int)round(microtime(true) - $t0), date('Y-m-d H:i:s'), $build['id']]);
        return ['done' => false, 'remaining' => openSetBuildCount(), 'id' => (int)$build['id'], 'status' => $status];
    };

    $job     = getOcrJob((int)$build['job_id']);
    $chapter = getChapter((int)$build['chapter_id']);
    if (!$job || !$chapter) return $finish('chyba', '', 'Kapitola už neexistuje.', '', 0);

    $text = chapterText($chapter);
    if (trim($text) === '') return $finish('chyba', '', 'Stránky kapitoly nemají přepis.', '', 0);

    $r = llmBuildSet($text, [
        'subject' => (string)$job['subject'] ?: 'ostatni',
        'grade'   => (int)$job['grade'],
        'title'   => (string)$build['title'],
        'source'  => (string)$build['source'],
        'kind'    => (string)$build['kind'],
    ], (string)$build['model']);
    if (!$r['ok']) return $finish('chyba', '', $r['error'], $r['warning'], 0);

    $parsed    = parseSetPayload($r['json']);
    $itemErrs  = array_filter($parsed['errors'], fn($e) => str_starts_with($e, 'Položka '));
    $headErrs  = array_diff($parsed['errors'], $itemErrs);
    if ($headErrs || !$parsed['set'] || count($parsed['items']) < 4) {
        $why = $headErrs ? implode(' ', $headErrs) : 'Použitelných položek: ' . count($parsed['items']) . ' — na kolo je potřeba aspoň čtyři.';
        return $finish('chyba', $r['json'], $why, $r['warning'], 0);
    }

    $setId = saveSet($parsed['set'], $parsed['items'], (int)($build['created_by'] ?? 0), (int)$build['job_id']);
    if (!$setId) return $finish('chyba', $r['json'], 'Sadu se nepodařilo uložit do databáze.', $r['warning'], 0);

    $warning = $r['warning'];
    if ($itemErrs) {
        $warning = trim('Vynecháno položek: ' . count($itemErrs) . ' (' . mb_substr(implode(' ', $itemErrs), 0, 160) . ') ' . $warning);
    }
    return $finish('hotovo', '', '', $warning, $setId);
}

/** Lidsky čitelný stav skládání */
function setBuildStatusLabel(array $b): string {
    return match ((string)$b['status']) {
        'ceka'   => '⏳ čeká',
        'bezi'   => '⚙️ skládá se',
        'hotovo' => '✔ hotovo',
        'chyba'  => '✘ chyba',
        default  => (string)$b['status'],
    };
}
