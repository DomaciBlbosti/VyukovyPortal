<?php
/**
 * Dávky naskenovaných stránek.
 *
 * Jedna dávka = jedna učebnicová lekce, tedy pár vyfocených stránek. Stránky
 * se drží v databázi i s obrázkem, dokud dávku nesmažeš — díky tomu jde
 * neúspěšnou stránku přepsat znovu, aniž bys ji fotil podruhé.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/llm.php';

/** Jak dlouho dávky držíme, než se uklidí samy (fotky zabírají místo) */
const OCR_KEEP_DAYS = 14;

/** Založí dávku a vrátí její ID; 0 při selhání */
function createOcrJob(string $title, string $note, int $userId, string $provider = ''): int {
    try {
        $now = date('Y-m-d H:i:s');
        $db  = getDB();
        $db->prepare('INSERT INTO ocr_jobs (title, note, provider, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?)')
           ->execute([mb_substr($title, 0, 120), mb_substr($note, 0, 255),
                      isset(LLM_PROVIDERS[$provider]) ? $provider : '', $userId ?: null, $now, $now]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Přidá stránku do dávky.
 *
 * @param string $imageB64 obrázek v base64 — prohlížeč ho posílá už zmenšený,
 *                         velké fotky z telefonu by model jen zdržovaly
 */
function addOcrPage(int $jobId, string $filename, string $imageB64): bool {
    try {
        $db  = getDB();
        $pos = $db->prepare('SELECT COUNT(*) FROM ocr_pages WHERE job_id = ?');
        $pos->execute([$jobId]);
        $db->prepare('INSERT INTO ocr_pages (job_id, position, filename, image_b64, status) VALUES (?,?,?,?,?)')
           ->execute([$jobId, (int)$pos->fetchColumn(), mb_substr($filename, 0, 180), $imageB64, 'ceka']);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Dávka bez stránek; null, když neexistuje */
function getOcrJob(int $id): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM ocr_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Stránky dávky. Obrázek se nenačítá — je velký a k výpisu není potřeba.
 */
function ocrPages(int $jobId): array {
    try {
        $stmt = getDB()->prepare('SELECT id, job_id, position, filename, status, text, edited_text, error, seconds
                                  FROM ocr_pages WHERE job_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Dávky i s počtem stránek a kolik jich je hotových */
function listOcrJobs(int $limit = 20): array {
    try {
        $stmt = getDB()->prepare('
            SELECT j.*,
                   (SELECT COUNT(*) FROM ocr_pages p WHERE p.job_id = j.id) AS page_count,
                   (SELECT COUNT(*) FROM ocr_pages p WHERE p.job_id = j.id AND p.status = ?) AS done_count
            FROM ocr_jobs j ORDER BY j.id DESC LIMIT ' . max(1, $limit));
        $stmt->execute(['hotovo']);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Smaže dávku i s fotkami */
function deleteOcrJob(int $id): bool {
    try {
        $stmt = getDB()->prepare('DELETE FROM ocr_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** Úklid starých dávek — fotky učebnic nemá smysl držet napořád */
function pruneOcrJobs(): int {
    try {
        $cut  = date('Y-m-d H:i:s', time() - OCR_KEEP_DAYS * 86400);
        $stmt = getDB()->prepare('DELETE FROM ocr_jobs WHERE created_at IS NOT NULL AND created_at < ?');
        $stmt->execute([$cut]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Přepíše jednu čekající stránku dávky.
 *
 * Vrací, co se stalo, aby prohlížeč mohl ukázat postup a říct si o další.
 * Když už čekající stránka není, vrátí done = true.
 *
 * @return array{done:bool, page:?array, remaining:int}
 */
function processNextOcrPage(int $jobId): array {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, image_b64, filename, position FROM ocr_pages
                          WHERE job_id = ? AND status IN (?, ?) ORDER BY position ASC, id ASC LIMIT 1');
    $stmt->execute([$jobId, 'ceka', 'bezi']);
    $page = $stmt->fetch();

    $left = $db->prepare('SELECT COUNT(*) FROM ocr_pages WHERE job_id = ? AND status IN (?, ?)');

    if (!$page) {
        $left->execute([$jobId, 'ceka', 'bezi']);
        return ['done' => true, 'page' => null, 'remaining' => (int)$left->fetchColumn()];
    }

    $db->prepare('UPDATE ocr_pages SET status = ? WHERE id = ?')->execute(['bezi', $page['id']]);

    $job     = getOcrJob($jobId);
    $started = microtime(true);
    $res     = llmOcrPage((string)$page['image_b64'], (string)($job['provider'] ?? ''));
    $secs    = (int)round(microtime(true) - $started);

    if ($res['ok']) {
        $db->prepare('UPDATE ocr_pages SET status = ?, text = ?, error = ?, seconds = ? WHERE id = ?')
           ->execute(['hotovo', $res['text'], '', $secs, $page['id']]);
    } else {
        $db->prepare('UPDATE ocr_pages SET status = ?, error = ?, seconds = ? WHERE id = ?')
           ->execute(['chyba', mb_substr($res['error'], 0, 255), $secs, $page['id']]);
    }

    $db->prepare('UPDATE ocr_jobs SET updated_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $jobId]);

    $left->execute([$jobId, 'ceka', 'bezi']);
    return [
        'done' => false,
        'page' => [
            'id'       => (int)$page['id'],
            'position' => (int)$page['position'] + 1,
            'filename' => $page['filename'],
            'status'   => $res['ok'] ? 'hotovo' : 'chyba',
            'text'     => $res['text'],
            'error'    => $res['error'],
            'seconds'  => $secs,
        ],
        'remaining' => (int)$left->fetchColumn(),
    ];
}

/** Vrátí stránku zpátky mezi čekající, ať jde přepis zkusit znovu */
function retryOcrPage(int $pageId): bool {
    try {
        $stmt = getDB()->prepare('UPDATE ocr_pages SET status = ?, error = ? WHERE id = ?');
        $stmt->execute(['ceka', '', $pageId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Text celé dávky — buď ručně upravený, nebo slepený z jednotlivých stránek.
 */
function ocrJobText(int $jobId): string {
    $job = getOcrJob($jobId);
    if ($job && trim((string)$job['edited_text']) !== '') return (string)$job['edited_text'];

    $parts = [];
    foreach (ocrPages($jobId) as $p) {
        $t = pageText($p);
        if ($p['status'] === 'hotovo' && $t !== '') $parts[] = $t;
    }
    return implode("\n\n", $parts);
}

/** Platný text stránky — ruční oprava má přednost před tím, co vrátil model */
function pageText(array $page): string {
    $edited = trim((string)($page['edited_text'] ?? ''));
    return $edited !== '' ? $edited : trim((string)($page['text'] ?? ''));
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

/**
 * Uloží ruční opravu přepisu jedné stránky.
 *
 * Zároveň zahodí text uložený u celé dávky — ten vznikl slepením stránek
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

/** Uloží ručně upravený text dávky */
function saveOcrText(int $jobId, string $text): bool {
    try {
        $stmt = getDB()->prepare('UPDATE ocr_jobs SET edited_text = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$text, date('Y-m-d H:i:s'), $jobId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
