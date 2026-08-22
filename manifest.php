<?php
// Web App Manifest — generuje se dynamicky kvůli BASE_URL
// (v Dockeru "" = kořen, na klasickém hostingu např. "/games")
require_once __DIR__ . '/config/app.php';
$base = BASE_URL;

header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name'             => 'TypeMaster — výukový portál',
    'short_name'       => 'TypeMaster',
    'description'      => 'Výukové hry: psaní všemi deseti, matematika, zeměpis.',
    'start_url'        => $base . '/dashboard.php',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#0a0a0f',
    'theme_color'      => '#111118',
    'lang'             => 'cs',
    'icons'            => [
        ['src' => $base . '/icon-192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/icon-512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
