<?php
/**
 * Cron — posune frontu přepisů bez otevřené stránky.
 *
 * Volá ho cron na serveru (TrueNAS) každou minutu:
 *   curl -fsS -m 20 "http://server/cron.php?token=…" >/dev/null
 *
 * Token je v Admin → Systém. Bez něj endpoint nic neudělá a nic neprozradí —
 * ani to, jestli fronta existuje. Jeden tik zpracuje tolik stránek, kolik
 * stihne v OCR_CRON_BUDGET sekund; co nestihne, vezme další minuta.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/ocr.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$given = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$real  = getSetting('cron_token');
if ($real === '' || $given === '' || !hash_equals($real, $given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

set_time_limit(0);         // stránka umí trvat minuty; Apache by nás jinak po 30 s utnul
ignore_user_abort(true);   // curl odejde hned po odpovědi, práce musí dojet a uložit se

// Odpovíme hned a stránky dojedeme až potom: tik může trvat minuty (lokální
// model, pomalá stránka) a curl v cronu by jinak končil timeoutem a TrueNAS
// by hlásil chybu, i když se stránka ve skutečnosti přepsala. Co tik udělal,
// je vidět v Admin → Systém.
$lock = (int)getSetting('ocr_cron_lock', '0');
$busy = $lock > 0 && time() - $lock < OCR_CRON_LOCK_STALE;
$body = json_encode(['ok' => true, 'started' => !$busy, 'skipped' => $busy, 'remaining' => ocrOpenRunCount()]);
if (function_exists('apache_setenv')) apache_setenv('no-gzip', '1');   // komprese by odpověď držela v bufferu
header('Connection: close');
header('Content-Length: ' . strlen($body));
echo $body;
while (ob_get_level() > 0) ob_end_flush();
flush();

ocrCronTick();   // při drženém zámku jen zapíše, že cron žije
