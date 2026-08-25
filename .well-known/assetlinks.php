<?php
/**
 * Digital Asset Links — potvrzuje, že aplikace s daným podpisem smí tuhle
 * doménu zobrazovat jako svoji (Trusted Web Activity).
 *
 * Bez tohohle souboru se v aplikaci ukáže adresní řádek Chromu a vypadá to
 * jako prohlížeč, ne jako appka. Otisk podpisového klíče je veřejný údaj —
 * právě proto se publikuje sem.
 *
 * Servíruje se na /.well-known/assetlinks.json (přepis v .htaccess).
 * Generuje se dynamicky, aby se otisk dal nastavit proměnnou prostředí
 * a nemusel se kvůli němu upravovat repozitář:
 *
 *   TWA_SHA256_FINGERPRINT=AB:CD:...   (víc otisků odděl čárkou)
 *   TWA_PACKAGE=cz.aleshulek.vyuka     (nepovinné, když se změní balíček)
 */

header('Content-Type: application/json; charset=utf-8');
// Během nastavování se otisk mění; ať Chrome ani Cloudflare nedrží starou verzi
header('Cache-Control: no-store, no-cache, must-revalidate');

$package = getenv('TWA_PACKAGE') ?: 'cz.aleshulek.vyuka';

$fingerprints = array_values(array_filter(array_map(
    fn($f) => strtoupper(trim($f)),
    explode(',', (string)getenv('TWA_SHA256_FINGERPRINT'))
), fn($f) => $f !== ''));

// Prázdný seznam je platná odpověď — znamená „žádná aplikace není ověřená"
echo json_encode($fingerprints ? [[
    'relation' => ['delegate_permission/common.handle_all_urls'],
    'target'   => [
        'namespace'                => 'android_app',
        'package_name'             => $package,
        'sha256_cert_fingerprints' => $fingerprints,
    ],
]] : [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
