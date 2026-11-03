<?php
/**
 * Cron — posune frontu přepisů bez otevřené stránky.
 *
 * Volá ho cron na serveru (TrueNAS) každou minutu:
 *   curl -fsS -m 110 "http://server/cron.php?token=…" >/dev/null
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
ignore_user_abort(true);   // curl odejde po timeoutu, rozpracovaná stránka musí dojet a uložit se

$r = ocrCronTick();
echo json_encode(['ok' => true] + $r);
