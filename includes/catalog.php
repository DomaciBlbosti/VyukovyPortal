<?php
/**
 * Katalog úloh — jedno místo, které vyjmenuje všechno, co jde ve hrách hrát.
 *
 * Do teď věděla o svých sadách jen každá hra sama. Editor výzev ale potřebuje
 * nabídnout „matematika → řada 7" nebo „zeměpis → kraje ČR" z jednoho seznamu
 * a po dohrané hře podle stejného klíče poznat, který krok výzvy se splnil.
 *
 * Klíč sady (topic) je přesně ten, který si hry ukládají do chybovníku:
 *   matematika  nasobilka/7
 *   čeština     vyjm_b
 *   angličtina  zvirata:cs_en
 *   zeměpis     europe  (mapy i otázky mají vlastní herní typ)
 * Prázdný klíč znamená „jakékoliv kolo téhle hry" — používá se u psaní,
 * kde se nic konkrétního vybírat nedá.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../games/data/math.php';
require_once __DIR__ . '/../games/data/czech.php';
require_once __DIR__ . '/../games/data/english.php';

/** Okruhy zeměpisných otázek — drží se stranou, ať je nemusíme tahat z games/geography.php */
function geographyQuizTopics(): array {
    return [
        'Hlavní města Evropy' => 'Hlavní města Evropy',
        'Hlavní města světa'  => 'Hlavní města světa',
        'Česká republika'     => 'Česká republika',
        'Řeky a hory světa'   => 'Řeky a hory světa',
    ];
}

/** Slepé mapy */
function geographyMapTopics(): array {
    return [
        'europe'    => 'Evropa — státy',
        'czech'     => 'Kraje ČR',
        'world'     => 'Státy světa',
        'cities_cz' => 'Česká města',
        'rivers_eu' => 'Evropské řeky',
        'seas'      => 'Moře a oceány',
    ];
}

/**
 * Celý katalog.
 *
 * @return array<string, array{label:string, icon:string,
 *                             items:array<string, array{label:string, url:string}>}>
 */
function catalogTasks(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $base = BASE_URL;
    $c = [];

    // ── Matematika: téma × varianta ──
    $items = [];
    foreach (mathTopics() as $tKey => $t) {
        foreach ($t['variants'] as $vKey => $vLabel) {
            $items[$tKey . '/' . $vKey] = [
                'label' => $t['label'] . ' — ' . $vLabel,
                'url'   => $base . '/games/math.php?topic=' . urlencode($tKey) . '&v=' . urlencode((string)$vKey),
            ];
        }
    }
    $c['math'] = ['label' => 'Matematika', 'icon' => '🔢', 'items' => $items];

    // ── Čeština: pravopisné sady ──
    $items = [];
    foreach (czechCategories() as $key => $cat) {
        $items[$key] = [
            'label' => $cat['label'],
            'url'   => $base . '/games/czech.php?cat=' . urlencode($key),
        ];
    }
    $c['czech'] = ['label' => 'Čeština — pravopis', 'icon' => '✍️', 'items' => $items];

    // ── Angličtina: okruh × směr překladu ──
    $items = [];
    foreach (englishThemes() as $key => $theme) {
        foreach (['cs_en' => 'CZ→EN', 'en_cs' => 'EN→CZ'] as $dir => $dirLabel) {
            $items[$key . ':' . $dir] = [
                'label' => $theme['label'] . ' (' . $dirLabel . ')',
                'url'   => $base . '/games/english.php?theme=' . urlencode($key) . '&dir=' . $dir,
            ];
        }
    }
    $c['english'] = ['label' => 'Angličtina — slovíčka', 'icon' => '🇬🇧', 'items' => $items];

    // ── Zeměpis ──
    $items = [];
    foreach (geographyQuizTopics() as $key => $label) {
        $items[$key] = [
            'label' => $label,
            'url'   => $base . '/games/geography.php?mode=questions&cat=' . urlencode($key),
        ];
    }
    $c['geography'] = ['label' => 'Zeměpis — otázky', 'icon' => '🌍', 'items' => $items];

    $items = [];
    foreach (geographyMapTopics() as $key => $label) {
        $items[$key] = [
            'label' => $label,
            'url'   => $base . '/games/geography.php?mode=map&map=' . urlencode($key),
        ];
    }
    $c['geography_map'] = ['label' => 'Zeměpis — slepé mapy', 'icon' => '🗺', 'items' => $items];

    // ── Psaní: nedá se vybrat konkrétní sada, bere se jakékoliv kolo ──
    $c['classic'] = ['label' => 'Psaní — klasický režim', 'icon' => '📝',
                     'items' => ['' => ['label' => 'jakékoliv kolo', 'url' => $base . '/games/classic.php']]];
    $c['timed']   = ['label' => 'Psaní — časový závod', 'icon' => '⏱',
                     'items' => ['' => ['label' => 'jakékoliv kolo', 'url' => $base . '/games/timed.php']]];
    $c['blind']   = ['label' => 'Psaní — slepý režim', 'icon' => '🙈',
                     'items' => ['' => ['label' => 'jakékoliv kolo', 'url' => $base . '/games/blind.php']]];

    return $cache = $c;
}

/** Popisek jedné úlohy: „🔢 Matematika · Malá násobilka — řada 7" */
function catalogLabel(string $gameType, string $topic): string {
    $cat = catalogTasks()[$gameType] ?? null;
    if (!$cat) return $gameType . ($topic !== '' ? ' · ' . $topic : '');
    $item = $cat['items'][$topic] ?? null;
    return $cat['icon'] . ' ' . $cat['label'] . ($item ? ' · ' . $item['label'] : '');
}

/** Odkaz, kde se úloha hraje */
function catalogUrl(string $gameType, string $topic): string {
    $item = catalogTasks()[$gameType]['items'][$topic] ?? null;
    return $item['url'] ?? (BASE_URL . '/dashboard.php');
}

/** Existuje taková úloha? */
function catalogHas(string $gameType, string $topic): bool {
    return isset(catalogTasks()[$gameType]['items'][$topic]);
}

/**
 * Sedí odehraná hra na krok výzvy?
 *
 * Matematika hlásí sadu po jednotlivých řadách; hraje-li dítě „6 + 7"
 * najednou, dorazí obojí a krok na řadu 7 se započítá.
 */
function catalogMatches(string $stepGame, string $stepTopic, string $playedGame, string $playedTopic): bool {
    if ($stepGame !== $playedGame) return false;
    return $stepTopic === '' || $stepTopic === $playedTopic;
}
