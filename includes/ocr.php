<?php
/**
 * Galerie naskenovaných stránek a jejich přepisy.
 *
 * Tři vrstvy:
 *   album   (ocr_jobs)  — složka fotek, typicky jedna lekce učebnice
 *   stránka (ocr_pages) — fotka a její aktuálně platný přepis
 *   běh     (ocr_runs)  — jeden pokus modelu nad stránkou
 *
 * Běhy se drží všechny, aby šlo na stejném obrázku porovnat různé modely
 * a zadání. Stránka nese kopii vybraného běhu (status, text, error, seconds),
 * takže výpisy nemusí do historie sahat.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/sets.php';

/** Stavy, ve kterých se na běhu ještě pracuje */
const OCR_OPEN = ['ceka', 'bezi'];

/**
 * Poslední chyba databáze při ukládání.
 *
 * „Nepodařilo se uložit" nikomu nepomůže — admin potřebuje vědět, jestli
 * chybí sloupec po nedoběhlé migraci, nebo je fotka moc velká.
 */
function &ocrLastError(): string {
    static $error = '';
    return $error;
}

/** Zapamatuje si chybu a vrátí false/0, ať se dá použít přímo v catch */
function ocrFail(PDOException $e): bool {
    $err = &ocrLastError();
    $err = $e->getMessage();
    return false;
}

/**
 * Je databáze na úrovni kódu? Po aktualizaci z Gitu bez migrace by
 * nahrávání padalo na chybějícím sloupci a nikdo by nevěděl proč.
 */
function ocrSchemaReady(): bool {
    try {
        $db = getDB();
        $db->query('SELECT thumb_b64 FROM ocr_pages LIMIT 1');
        $db->query('SELECT id FROM ocr_runs LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ── Alba ──

/**
 * Založí album a vrátí jeho ID; 0 při selhání.
 *
 * Album je podklad k jednomu předmětu a ročníku — učebnice a pracovní sešit
 * jsou dvě alba téhož předmětu. Sady z něj pak předmět i ročník zdědí,
 * takže se nevyplňují znovu a je vidět, z čeho otázky čerpají.
 */
function createOcrJob(string $title, string $note, int $userId, string $subject = '', int $grade = 0): int {
    try {
        $now = date('Y-m-d H:i:s');
        $db  = getDB();
        $db->prepare('INSERT INTO ocr_jobs (title, note, subject, grade, provider, created_by, created_at, updated_at)
                      VALUES (?,?,?,?,?,?,?,?)')
           ->execute([mb_substr($title, 0, 120), mb_substr($note, 0, 255),
                      isset(SET_SUBJECTS[$subject]) ? $subject : '', max(0, min(9, $grade)),
                      '', $userId ?: null, $now, $now]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        ocrFail($e);
        return 0;
    }
}

/** Přejmenuje album a nastaví, ke kterému předmětu a ročníku patří */
function renameOcrJob(int $id, string $title, string $note, string $subject = '', int $grade = 0): bool {
    try {
        $stmt = getDB()->prepare('UPDATE ocr_jobs SET title = ?, note = ?, subject = ?, grade = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([mb_substr($title, 0, 120), mb_substr($note, 0, 255),
                        isset(SET_SUBJECTS[$subject]) ? $subject : '', max(0, min(9, $grade)),
                        date('Y-m-d H:i:s'), $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** Album bez stránek; null, když neexistuje */
function getOcrJob(int $id): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Alba i s počtem stránek, kolik jich má přepis a kolik místa zabírají */
function listOcrJobs(int $limit = 100): array {
    try {
        $stmt = getDB()->prepare('
            SELECT j.*,
                   (SELECT COUNT(*) FROM ocr_pages p WHERE p.job_id = j.id) AS page_count,
                   (SELECT COUNT(*) FROM ocr_pages p WHERE p.job_id = j.id AND p.status = ?) AS done_count,
                   (SELECT COALESCE(SUM(LENGTH(p.image_b64)), 0) FROM ocr_pages p WHERE p.job_id = j.id) AS bytes,
                   (SELECT COUNT(*) FROM custom_sets c WHERE c.job_id = j.id) AS set_count
            FROM ocr_jobs j ORDER BY j.subject ASC, j.grade ASC, j.id DESC LIMIT ' . max(1, $limit));
        $stmt->execute(['hotovo']);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Smaže album i s fotkami a historií běhů */
function deleteOcrJob(int $id): bool {
    try {
        $stmt = getDB()->prepare('DELETE FROM ocr_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// ── Stránky ──

/**
 * Přidá stránku do alba.
 *
 * @param string $imageB64 obrázek v base64 — prohlížeč ho posílá už zmenšený,
 *                         velké fotky z telefonu by model jen zdržovaly
 * @param string $thumbB64 náhled pro galerii, také z prohlížeče
 */
function addOcrPage(int $jobId, string $filename, string $imageB64, string $thumbB64 = ''): int {
    try {
        $db  = getDB();
        $pos = $db->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM ocr_pages WHERE job_id = ?');
        $pos->execute([$jobId]);
        $db->prepare('INSERT INTO ocr_pages (job_id, position, filename, image_b64, thumb_b64, status) VALUES (?,?,?,?,?,?)')
           ->execute([$jobId, (int)$pos->fetchColumn(), mb_substr($filename, 0, 180), $imageB64,
                      $thumbB64 !== '' ? $thumbB64 : null, 'nova']);
        $id = (int)$db->lastInsertId();
        $db->prepare('UPDATE ocr_jobs SET updated_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $jobId]);
        return $id;
    } catch (PDOException $e) {
        ocrFail($e);
        return 0;
    }
}

/**
 * Stránky alba i s náhledem. Plný obrázek se nenačítá — je velký a
 * k výpisu není potřeba.
 */
function ocrPages(int $jobId): array {
    try {
        $stmt = getDB()->prepare('SELECT id, job_id, position, filename, thumb_b64, status, text, edited_text, error, seconds,
                                         (SELECT COUNT(*) FROM ocr_runs r WHERE r.page_id = p.id) AS run_count
                                  FROM ocr_pages p WHERE job_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Jedna stránka i s obrázkem; null, když neexistuje */
function getOcrPage(int $pageId): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_pages WHERE id = ?');
        $stmt->execute([$pageId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Smaže stránku i s historií běhů */
function deleteOcrPage(int $pageId): bool {
    try {
        $stmt = getDB()->prepare('DELETE FROM ocr_pages WHERE id = ?');
        $stmt->execute([$pageId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** Přesune stránku do jiného alba (na konec) */
function moveOcrPage(int $pageId, int $jobId): bool {
    try {
        $db = getDB();
        if (!getOcrJob($jobId)) return false;
        $pos = $db->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM ocr_pages WHERE job_id = ?');
        $pos->execute([$jobId]);
        $stmt = $db->prepare('UPDATE ocr_pages SET job_id = ?, position = ? WHERE id = ?');
        $stmt->execute([$jobId, (int)$pos->fetchColumn(), $pageId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** Posune stránku v albu o jedno nahoru (-1) nebo dolů (+1) */
function shiftOcrPage(int $pageId, int $dir): bool {
    try {
        $db   = getDB();
        $page = getOcrPage($pageId);
        if (!$page) return false;

        // Pořadí nejdřív srovnáme na 0..n-1, ať prohození sedí i po mazání
        $ids = array_column(ocrPages((int)$page['job_id']), 'id');
        $i   = array_search($pageId, array_map('intval', $ids), true);
        $j   = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) return false;
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];

        $stmt = $db->prepare('UPDATE ocr_pages SET position = ? WHERE id = ?');
        foreach ($ids as $pos => $id) $stmt->execute([$pos, (int)$id]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Platný text stránky — ruční oprava má přednost před tím, co vrátil model */
function pageText(array $page): string {
    $edited = trim((string)($page['edited_text'] ?? ''));
    return $edited !== '' ? $edited : trim((string)($page['text'] ?? ''));
}

/**
 * Uloží ruční opravu přepisu jedné stránky.
 *
 * Zároveň zahodí text uložený u celého alba — ten vznikl slepením stránek
 * před opravou, takže by opravu přebil a uživatel by nechápal, proč se
 * změna neprojevila.
 */
function saveOcrPageText(int $pageId, string $text): bool {
    try {
        $db   = getDB();
        $page = getOcrPage($pageId);
        if (!$page) return false;

        $db->prepare('UPDATE ocr_pages SET edited_text = ? WHERE id = ?')
           ->execute([trim($text) !== '' ? $text : null, $pageId]);
        $db->prepare('UPDATE ocr_jobs SET edited_text = NULL, updated_at = ? WHERE id = ?')
           ->execute([date('Y-m-d H:i:s'), (int)$page['job_id']]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ── Běhy ──

/**
 * Zařadí stránky do fronty na přepis.
 *
 * Každá stránka dostane nový běh; kdyby už nějaký čekal, nepřidá se další.
 * Vrací značku dávky, podle které se prohlížeč ptá na postup.
 *
 * @param array{model?:string, prompt_key?:string, prompt?:string} $opts
 */
function queueOcrRuns(array $pageIds, array $opts): string {
    $batch    = date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    $model = trim((string)($opts['model'] ?? '')) ?: llmModel('vision');
    // U běhu si pamatujeme, kdo model provozuje — ať je i za měsíc vidět,
    // jestli fotka stránky opustila domácí síť
    $provider = modelProvider($model);
    $key      = (string)($opts['prompt_key'] ?? ocrDefaultPromptKey());
    // Upravené znění z formuláře má přednost před presetem — jinak by se
    // ruční úprava zadání tiše zahodila a poslalo by se něco jiného,
    // než co má admin před očima
    $prompt   = trim((string)($opts['prompt'] ?? '')) ?: ocrPromptText($key);
    $now      = date('Y-m-d H:i:s');

    try {
        $db   = getDB();
        $open = $db->prepare('SELECT COUNT(*) FROM ocr_runs WHERE page_id = ? AND status IN (?, ?)');
        $ins  = $db->prepare('INSERT INTO ocr_runs (page_id, batch, provider, model, prompt_key, prompt, status, created_at)
                              VALUES (?,?,?,?,?,?,?,?)');
        $mark = $db->prepare('UPDATE ocr_pages SET status = ?, error = ? WHERE id = ?');
        foreach (array_unique(array_map('intval', $pageIds)) as $id) {
            if (!$id) continue;
            $open->execute([$id, 'ceka', 'bezi']);
            if ((int)$open->fetchColumn() > 0) continue;
            $ins->execute([$id, $batch, $provider, mb_substr($model, 0, 120), mb_substr($key, 0, 40), $prompt, 'ceka', $now]);
            $mark->execute(['ceka', '', $id]);
        }
    } catch (PDOException $e) {
    }
    return $batch;
}

/** Jeden běh; null, když neexistuje */
function getOcrRun(int $runId): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_runs WHERE id = ?');
        $stmt->execute([$runId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Historie běhů nad stránkou, nejnovější první */
function pageRuns(int $pageId): array {
    try {
        $stmt = getDB()->prepare('SELECT r.*,
                                         (SELECT COUNT(*) FROM ocr_blocks b WHERE b.run_id = r.id) AS block_count,
                                         (SELECT COUNT(*) FROM ocr_blocks b WHERE b.run_id = r.id AND b.image_b64 IS NOT NULL OR b.run_id = r.id AND b.kind IN (?, ?)) AS image_count
                                  FROM ocr_runs r WHERE r.page_id = ? ORDER BY r.id DESC');
        $stmt->execute(['image', 'figure', $pageId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Zpracuje jeden čekající běh — z dané dávky, nebo jakýkoli, když je
 * dávka prázdná. Vrací, co se stalo, aby se prohlížeč mohl zeptat na další.
 *
 * @return array{done:bool, run_id:int, remaining:int}
 */
function processNextOcrRun(string $batch = ''): array {
    $db = getDB();

    $where = $batch !== '' ? 'batch = ? AND ' : '';
    $args  = $batch !== '' ? [$batch] : [];
    $stmt  = $db->prepare("SELECT * FROM ocr_runs WHERE {$where}status IN (?, ?) ORDER BY id ASC LIMIT 1");
    $stmt->execute([...$args, 'ceka', 'bezi']);
    $run = $stmt->fetch();

    $left = $db->prepare("SELECT COUNT(*) FROM ocr_runs WHERE {$where}status IN (?, ?)");

    if (!$run) {
        $left->execute([...$args, 'ceka', 'bezi']);
        return ['done' => true, 'run_id' => 0, 'remaining' => (int)$left->fetchColumn()];
    }

    $page = getOcrPage((int)$run['page_id']);
    if (!$page) {
        $db->prepare('DELETE FROM ocr_runs WHERE id = ?')->execute([$run['id']]);
        $left->execute([...$args, 'ceka', 'bezi']);
        return ['done' => false, 'run_id' => (int)$run['id'], 'remaining' => (int)$left->fetchColumn()];
    }

    $db->prepare('UPDATE ocr_runs SET status = ? WHERE id = ?')->execute(['bezi', $run['id']]);
    $db->prepare('UPDATE ocr_pages SET status = ? WHERE id = ?')->execute(['bezi', $page['id']]);

    $started = microtime(true);
    $res     = llmOcrPage((string)$page['image_b64'], [
        'model'      => (string)$run['model'],
        'prompt'     => (string)$run['prompt'],
        'prompt_key' => (string)$run['prompt_key'],
    ]);
    $secs = (int)round(microtime(true) - $started);

    if ($res['ok']) {
        $db->prepare('UPDATE ocr_runs SET status = ?, text = ?, error = ?, warning = ?, seconds = ?, tokens = ? WHERE id = ?')
           ->execute(['hotovo', $res['text'], '', mb_substr($res['warning'], 0, 255), $secs, $res['tokens'], $run['id']]);
        if (!empty($res['blocks'])) saveOcrBlocks((int)$run['id'], (int)$page['id'], $res['blocks'], (string)$page['image_b64']);
        // Podezřelý běh (zacyklení, zopakované zadání) nesmí přebít dobrý
        // přepis; platným se stane jen tam, kde zatím žádný pořádný není —
        // tedy i tam, kde ten dosavadní má varování sám nebo je jen značka
        $cur = $db->prepare('SELECT warning FROM ocr_runs WHERE page_id = ? AND chosen = 1 LIMIT 1');
        $cur->execute([$page['id']]);
        $curWarning = $cur->fetch();
        $weakCurrent = trim((string)$page['text']) === '' || mb_strlen(trim((string)$page['text'])) < 50
                    || ($curWarning !== false && (string)$curWarning['warning'] !== '');
        if ($res['warning'] === '' || $weakCurrent) {
            chooseOcrRun((int)$run['id'], false);
        } else {
            $db->prepare('UPDATE ocr_pages SET status = ?, error = ? WHERE id = ?')->execute(['hotovo', '', $page['id']]);
        }
    } else {
        $db->prepare('UPDATE ocr_runs SET status = ?, error = ?, seconds = ? WHERE id = ?')
           ->execute(['chyba', mb_substr($res['error'], 0, 255), $secs, $run['id']]);
        // Stránka zůstane u posledního dobrého přepisu, jen ukáže chybu
        $db->prepare('UPDATE ocr_pages SET status = ?, error = ?, seconds = ? WHERE id = ?')
           ->execute([trim((string)$page['text']) !== '' ? 'hotovo' : 'chyba', mb_substr($res['error'], 0, 255), $secs, $page['id']]);
    }
    $db->prepare('UPDATE ocr_jobs SET updated_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), (int)$page['job_id']]);

    $left->execute([...$args, 'ceka', 'bezi']);
    return ['done' => false, 'run_id' => (int)$run['id'], 'remaining' => (int)$left->fetchColumn()];
}

/**
 * Udělá z běhu platný přepis stránky.
 *
 * Nový úspěšný běh se vybírá sám, ale ruční opravu nechává být — ta má
 * pořád přednost a nikdo o ni nepřijde omylem. Když si uživatel běh vybere
 * sám ($byUser), oprava se zahodí: chce právě tenhle text.
 */
function chooseOcrRun(int $runId, bool $byUser = true): bool {
    try {
        $db  = getDB();
        $run = getOcrRun($runId);
        if (!$run || $run['status'] !== 'hotovo') return false;

        $db->prepare('UPDATE ocr_runs SET chosen = 0 WHERE page_id = ?')->execute([$run['page_id']]);
        $db->prepare('UPDATE ocr_runs SET chosen = 1 WHERE id = ?')->execute([$runId]);
        $db->prepare('UPDATE ocr_pages SET status = ?, text = ?, error = ?, seconds = ?' . ($byUser ? ', edited_text = NULL' : '') . ' WHERE id = ?')
           ->execute(['hotovo', $run['text'], '', (int)$run['seconds'], $run['page_id']]);

        $page = getOcrPage((int)$run['page_id']);
        if ($page) {
            $db->prepare('UPDATE ocr_jobs SET edited_text = NULL, updated_at = ? WHERE id = ?')
               ->execute([date('Y-m-d H:i:s'), (int)$page['job_id']]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Smaže jeden běh z historie; vybraný běh smazat nejde */
function deleteOcrRun(int $runId): bool {
    try {
        $run = getOcrRun($runId);
        if (!$run || (int)$run['chosen'] === 1) return false;
        $stmt = getDB()->prepare('DELETE FROM ocr_runs WHERE id = ?');
        $stmt->execute([$runId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Postup dávky pro dotazování z prohlížeče.
 *
 * Přepis jedné stránky trvá minuty, takže se nedá viset na jednom HTTP
 * spojení — reverzní proxy ho utne a vrátí HTML chybovou stránku. Práce
 * proto běží nezávisle na spojení a prohlížeč se ptá sem, jak to dopadlo.
 *
 * @return array{remaining:int, next_id:int, runs:array}
 */
function batchStatus(string $batch): array {
    $runs = [];
    $next = 0;
    try {
        $stmt = getDB()->prepare('SELECT id, page_id, status, error, warning, seconds, tokens FROM ocr_runs WHERE batch = ? ORDER BY id ASC');
        $stmt->execute([$batch]);
        foreach ($stmt->fetchAll() as $r) {
            $runs[] = [
                'id'      => (int)$r['id'],
                'page_id' => (int)$r['page_id'],
                'status'  => $r['status'],
                'error'   => $r['error'],
                'warning' => $r['warning'],
                'seconds' => (int)$r['seconds'],
                'tokens'  => (int)$r['tokens'],
            ];
            if (!$next && in_array($r['status'], OCR_OPEN, true)) $next = (int)$r['id'];
        }
    } catch (PDOException $e) {
    }
    return [
        'remaining' => count(array_filter($runs, fn($r) => in_array($r['status'], OCR_OPEN, true))),
        'next_id'   => $next,
        'runs'      => $runs,
    ];
}

// ── Bloky ──

/**
 * Uloží bloky běhu. U obrázků rovnou vyřízne kus stránky, když je k dispozici
 * GD; bez něj zůstanou jen souřadnice a výřez si udělá prohlížeč.
 */
function saveOcrBlocks(int $runId, int $pageId, array $blocks, string $pageImageB64): void {
    try {
        $db = getDB();
        $db->prepare('DELETE FROM ocr_blocks WHERE run_id = ?')->execute([$runId]);
        $ins = $db->prepare('INSERT INTO ocr_blocks (run_id, page_id, position, kind, x1, y1, x2, y2, text, image_b64)
                             VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach ($blocks as $i => $b) {
            $box  = $b['box'] ?? [0, 0, 0, 0];
            $crop = ocrBlockIsImage($b['kind']) && $b['box'] ? cropPageImage($pageImageB64, $box) : '';
            $ins->execute([$runId, $pageId, $i, mb_substr($b['kind'], 0, 20), $box[0], $box[1], $box[2], $box[3],
                           $b['text'] !== '' ? $b['text'] : null, $crop !== '' ? $crop : null]);
        }
    } catch (PDOException $e) {
        ocrFail($e);
    }
}

/**
 * Vyřízne z fotky stránky obdélník daný v tisícinách rozměru.
 * Vrací JPEG v base64; prázdný řetězec bez GD nebo při chybě.
 */
function cropPageImage(string $imageB64, array $box): string {
    if (!function_exists('imagecreatefromstring') || $imageB64 === '') return '';
    $img = @imagecreatefromstring(base64_decode($imageB64));
    if (!$img) return '';
    $w = imagesx($img);
    $h = imagesy($img);
    // Malý přesah, ať rámeček neuřízne okraj kresby
    $pad = 8;
    $x1 = max(0, (int)floor($box[0] / 1000 * $w) - $pad);
    $y1 = max(0, (int)floor($box[1] / 1000 * $h) - $pad);
    $x2 = min($w, (int)ceil($box[2] / 1000 * $w) + $pad);
    $y2 = min($h, (int)ceil($box[3] / 1000 * $h) + $pad);
    if ($x2 - $x1 < 8 || $y2 - $y1 < 8) { imagedestroy($img); return ''; }

    $crop = imagecrop($img, ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1]);
    imagedestroy($img);
    if (!$crop) return '';
    ob_start();
    imagejpeg($crop, null, 85);
    imagedestroy($crop);
    return base64_encode((string)ob_get_clean());
}

/** Bloky jednoho běhu v pořadí, bez obrázkových dat */
function runBlocks(int $runId): array {
    try {
        $stmt = getDB()->prepare('SELECT id, run_id, page_id, position, kind, x1, y1, x2, y2, text,
                                         (image_b64 IS NOT NULL) AS has_image
                                  FROM ocr_blocks WHERE run_id = ? ORDER BY position ASC');
        $stmt->execute([$runId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Bloky platného (vybraného) běhu stránky */
function pageBlocks(int $pageId): array {
    try {
        $stmt = getDB()->prepare('SELECT id FROM ocr_runs WHERE page_id = ? AND chosen = 1 LIMIT 1');
        $stmt->execute([$pageId]);
        $runId = (int)$stmt->fetchColumn();
        return $runId ? runBlocks($runId) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/** Jeden blok i s výřezem; null, když neexistuje */
function getOcrBlock(int $blockId): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_blocks WHERE id = ?');
        $stmt->execute([$blockId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Cvičení na stránce: skupiny bloků s popiskem a textem. Ručně opravená
 * stránka je jedna skupina — oprava platí pro celý text a bloky by ji obešly.
 *
 * @return array<int, array{key:string, label:string, text:string, images:int, blocks:array}>
 */
function pageExercises(array $page, bool $ignoreEdited = false): array {
    if (!$ignoreEdited && trim((string)($page['edited_text'] ?? '')) !== '') {
        return [['key' => $page['id'] . ':edited', 'label' => 'celá stránka (ručně opravený text)',
                 'text' => pageText($page), 'images' => 0, 'blocks' => []]];
    }
    $blocks = pageBlocks((int)$page['id']);
    if (!$blocks) {
        $t = pageText($page);
        return $t === '' ? [] : [['key' => $page['id'] . ':all', 'label' => 'celá stránka', 'text' => $t, 'images' => 0, 'blocks' => []]];
    }
    $shaped = array_map(fn($b) => ['kind' => $b['kind'], 'box' => [(int)$b['x1'], (int)$b['y1'], (int)$b['x2'], (int)$b['y2']],
                                   'text' => (string)$b['text']], $blocks);
    $out = [];
    foreach (groupOcrBlocks($shaped) as $i => $g) {
        $mine = array_map(fn($j) => $shaped[$j], $g['blocks']);
        $out[] = [
            'key'    => $page['id'] . ':' . $i,
            'label'  => $g['label'],
            'text'   => blocksToText($mine),
            'images' => count(array_filter($mine, fn($b) => ocrBlockIsImage($b['kind']))),
            'blocks' => array_map(fn($j) => $blocks[$j], $g['blocks']),
        ];
    }
    return $out;
}

// ── Text alba a sestavení sady ──

/**
 * Text alba — buď ručně upravený, nebo slepený z vybraných stránek
 * (bez výběru ze všech, které mají přepis).
 */
function ocrJobText(int $jobId, array $pageIds = []): string {
    $job = getOcrJob($jobId);
    if ($job && !$pageIds && trim((string)$job['edited_text']) !== '') return (string)$job['edited_text'];

    $want  = array_map('intval', $pageIds);
    $parts = [];
    foreach (ocrPages($jobId) as $p) {
        if ($want && !in_array((int)$p['id'], $want, true)) continue;
        $t = pageText($p);
        if ($t !== '') $parts[] = $t;
    }
    return implode("\n\n", $parts);
}

/** Uloží ručně upravený text alba */
function saveOcrText(int $jobId, string $text): bool {
    try {
        $stmt = getDB()->prepare('UPDATE ocr_jobs SET edited_text = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$text, date('Y-m-d H:i:s'), $jobId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Označí, že se sada začala skládat, a zahodí předchozí výsledek */
function startBuild(int $jobId): void {
    try {
        getDB()->prepare('UPDATE ocr_jobs SET building = 1, built_json = NULL, built_error = ? WHERE id = ?')
               ->execute(['', $jobId]);
    } catch (PDOException $e) {
    }
}

/** Uloží výsledek skládání (nebo důvod, proč se nepovedlo) */
function finishBuild(int $jobId, string $json, string $error): void {
    try {
        getDB()->prepare('UPDATE ocr_jobs SET building = 0, built_json = ?, built_error = ?, updated_at = ? WHERE id = ?')
               ->execute([$json !== '' ? $json : null, mb_substr($error, 0, 255), date('Y-m-d H:i:s'), $jobId]);
    } catch (PDOException $e) {
    }
}

/**
 * Jak dopadlo skládání sady.
 *
 * @return array{building:bool, json:string, error:string}
 */
function buildStatus(int $jobId): array {
    $job = getOcrJob($jobId);
    return [
        'building' => (bool)($job['building'] ?? false),
        'json'     => (string)($job['built_json'] ?? ''),
        'error'    => (string)($job['built_error'] ?? ''),
    ];
}

/** Lidsky čitelný stav stránky do výpisů */
function pageStatusLabel(array $page): string {
    return match ($page['status']) {
        'hotovo' => '✔ přepsáno',
        'chyba'  => '✘ ' . ($page['error'] ?: 'chyba'),
        'bezi'   => '⏳ běží',
        'ceka'   => '· ve frontě',
        default  => '– bez přepisu',
    };
}
