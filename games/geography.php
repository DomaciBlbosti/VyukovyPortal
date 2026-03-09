<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => saveGameSession([
        'game_type'        => $_POST['game_type'] ?? 'geography',
        'wpm'              => floatval($_POST['wpm']       ?? 0),
        'accuracy'         => floatval($_POST['accuracy']  ?? 0),
        'duration_seconds' => intval($_POST['duration']    ?? 0),
        'chars_typed'      => intval($_POST['chars_typed'] ?? 0),
        'errors'           => intval($_POST['errors']      ?? 0),
        'text_snippet'     => 'zeměpis',
    ])]);
    exit;
}

// ─── ISO kódy pro world-atlas (numeric ISO 3166-1) ─────────────────────
$europeISO = [
    '250'=>'Francie','724'=>'Španělsko','620'=>'Portugalsko','826'=>'Velká Británie',
    '372'=>'Irsko','578'=>'Norsko','752'=>'Švédsko','246'=>'Finsko','208'=>'Dánsko',
    '276'=>'Německo','616'=>'Polsko','203'=>'Česká republika','703'=>'Slovensko',
    '348'=>'Maďarsko','642'=>'Rumunsko','100'=>'Bulharsko','300'=>'Řecko',
    '688'=>'Srbsko','191'=>'Chorvatsko','40'=>'Rakousko','756'=>'Švýcarsko',
    '380'=>'Itálie','112'=>'Bělorusko','440'=>'Litva','428'=>'Lotyšsko',
    '233'=>'Estonsko','804'=>'Ukrajina','352'=>'Island','56'=>'Belgie',
    '528'=>'Nizozemsko','705'=>'Slovinsko','70'=>'Bosna a Hercegovina',
    '807'=>'Severní Makedonie','8'=>'Albánie','498'=>'Moldavsko',
];

// ─── Otázky & odpovědi ────────────────────────────────────────────────────
$categories = [
    'Hlavní města Evropy' => [
        ['q'=>'Hlavní město Francie:',       'a'=>'Paříž',      'w'=>['Brusel','Lyon']],
        ['q'=>'Hlavní město Německa:',        'a'=>'Berlín',     'w'=>['Hamburg','Frankfurt']],
        ['q'=>'Hlavní město Itálie:',         'a'=>'Řím',        'w'=>['Milán','Neapol']],
        ['q'=>'Hlavní město Španělska:',      'a'=>'Madrid',     'w'=>['Barcelona','Sevilla']],
        ['q'=>'Hlavní město Polska:',         'a'=>'Varšava',    'w'=>['Krakov','Gdaňsk']],
        ['q'=>'Hlavní město Rakouska:',       'a'=>'Vídeň',      'w'=>['Salzburg','Linec']],
        ['q'=>'Hlavní město Maďarska:',       'a'=>'Budapešť',   'w'=>['Debrecín','Miskolc']],
        ['q'=>'Hlavní město Slovenska:',      'a'=>'Bratislava', 'w'=>['Košice','Prešov']],
        ['q'=>'Hlavní město Chorvatska:',     'a'=>'Záhřeb',     'w'=>['Split','Rijeka']],
        ['q'=>'Hlavní město Řecka:',          'a'=>'Atény',      'w'=>['Soluň','Patras']],
        ['q'=>'Hlavní město Švédska:',        'a'=>'Stockholm',  'w'=>['Göteborg','Malmö']],
        ['q'=>'Hlavní město Norska:',         'a'=>'Oslo',       'w'=>['Bergen','Trondheim']],
        ['q'=>'Hlavní město Dánska:',         'a'=>'Kodaň',      'w'=>['Aarhus','Odense']],
        ['q'=>'Hlavní město Finska:',         'a'=>'Helsinky',   'w'=>['Tampere','Turku']],
        ['q'=>'Hlavní město Belgie:',         'a'=>'Brusel',     'w'=>['Antverpy','Lutych']],
        ['q'=>'Hlavní město Nizozemska:',     'a'=>'Amsterdam',  'w'=>['Rotterdam','Haag']],
        ['q'=>'Hlavní město Švýcarska:',      'a'=>'Bern',       'w'=>['Curych','Ženeva']],
        ['q'=>'Hlavní město Portugalska:',    'a'=>'Lisabon',    'w'=>['Porto','Braga']],
        ['q'=>'Hlavní město Rumunska:',       'a'=>'Bukurešť',   'w'=>['Kluž','Temešvár']],
        ['q'=>'Hlavní město Bulharska:',      'a'=>'Sofie',      'w'=>['Plovdiv','Varna']],
    ],
    'Hlavní města světa' => [
        ['q'=>'Hlavní město USA:',             'a'=>'Washington', 'w'=>['New York','Los Angeles']],
        ['q'=>'Hlavní město Japonska:',        'a'=>'Tokio',      'w'=>['Osaka','Kjóto']],
        ['q'=>'Hlavní město Číny:',            'a'=>'Peking',     'w'=>['Šanghaj','Kanton']],
        ['q'=>'Hlavní město Brazílie:',        'a'=>'Brasília',   'w'=>['São Paulo','Rio de Janeiro']],
        ['q'=>'Hlavní město Austrálie:',       'a'=>'Canberra',   'w'=>['Sydney','Melbourne']],
        ['q'=>'Hlavní město Kanady:',          'a'=>'Ottawa',     'w'=>['Toronto','Vancouver']],
        ['q'=>'Hlavní město Ruska:',           'a'=>'Moskva',     'w'=>['Petrohrad','Novosibirsk']],
        ['q'=>'Hlavní město Egypta:',          'a'=>'Káhira',     'w'=>['Alexandrie','Luxor']],
        ['q'=>'Hlavní město Argentiny:',       'a'=>'Buenos Aires','w'=>['Córdoba','Rosario']],
        ['q'=>'Hlavní město Mexika:',          'a'=>'Mexico City','w'=>['Guadalajara','Monterrey']],
        ['q'=>'Hlavní město Turecka:',         'a'=>'Ankara',     'w'=>['Istanbul','Izmir']],
        ['q'=>'Hlavní město Jižní Koreje:',    'a'=>'Soul',       'w'=>['Pusan','Inčchon']],
        ['q'=>'Hlavní město Saudské Arábie:',  'a'=>'Rijád',      'w'=>['Džidda','Mekka']],
        ['q'=>'Hlavní město JAR:',             'a'=>'Pretoria',   'w'=>['Kapské Město','Johannesburg']],
        ['q'=>'Hlavní město Indie:',           'a'=>'Naní Dillí', 'w'=>['Bombaj','Kalkata']],
    ],
    'Česká republika' => [
        ['q'=>'Druhé největší město ČR:',      'a'=>'Brno',       'w'=>['Ostrava','Plzeň']],
        ['q'=>'Třetí největší město ČR:',      'a'=>'Ostrava',    'w'=>['Plzeň','Liberec']],
        ['q'=>'Nejvyšší hora ČR:',             'a'=>'Sněžka',     'w'=>['Praděd','Lysá hora']],
        ['q'=>'Nejdelší řeka ČR:',             'a'=>'Vltava',     'w'=>['Labe','Morava']],
        ['q'=>'Největší přehrada ČR:',         'a'=>'Lipno',      'w'=>['Orlík','Slapy']],
        ['q'=>'Počet krajů v ČR:',             'a'=>'14',         'w'=>['13','15']],
        ['q'=>'Sousední stát na severu ČR:',   'a'=>'Polsko',     'w'=>['Německo','Slovensko']],
        ['q'=>'Sousední stát na jihu ČR:',     'a'=>'Rakousko',   'w'=>['Slovensko','Polsko']],
        ['q'=>'Sousední stát na západ ČR:',    'a'=>'Německo',    'w'=>['Rakousko','Polsko']],
        ['q'=>'Sousední stát na východ ČR:',   'a'=>'Slovensko',  'w'=>['Polsko','Maďarsko']],
        ['q'=>'Řeka protékající Prahou:',      'a'=>'Vltava',     'w'=>['Labe','Berounka']],
        ['q'=>'Pohoří na jihozápadě ČR:',      'a'=>'Šumava',     'w'=>['Krkonoše','Jeseníky']],
        ['q'=>'Pohoří na severovýchodě ČR:',   'a'=>'Krkonoše',   'w'=>['Jeseníky','Beskydy']],
        ['q'=>'Řeka oddělující ČR a Německo:', 'a'=>'Labe',       'w'=>['Ohře','Morava']],
    ],
    'Řeky a hory světa' => [
        ['q'=>'Nejdelší řeka světa:',          'a'=>'Nil',         'w'=>['Amazonka','Mississippi']],
        ['q'=>'Druhá nejdelší řeka světa:',    'a'=>'Amazonka',    'w'=>['Nil','Jang-c\'tiang']],
        ['q'=>'Nejvyšší hora světa:',          'a'=>'Everest',     'w'=>['K2','Kančendžonga']],
        ['q'=>'Nejvyšší hora Evropy:',         'a'=>'Elbrus',      'w'=>['Mont Blanc','Dufour']],
        ['q'=>'Nejvyšší hora Afriky:',         'a'=>'Kilimandžáro','w'=>['Keňa','Rwenzori']],
        ['q'=>'Nejvyšší hora Ameriky:',        'a'=>'Aconcagua',   'w'=>['Denali','Chimborazo']],
        ['q'=>'Nejhlubší jezero světa:',       'a'=>'Bajkal',      'w'=>['Kaspické moře','Tanganika']],
        ['q'=>'Největší oceán:',               'a'=>'Tichý oceán', 'w'=>['Atlantský oceán','Indický oceán']],
        ['q'=>'Největší poušť světa:',         'a'=>'Sahara',      'w'=>['Gobi','Arabská poušť']],
        ['q'=>'Nejdelší řeka Evropy:',         'a'=>'Volha',       'w'=>['Dunaj','Rýn']],
        ['q'=>'Pohoří oddělující Evropu od Asie:','a'=>'Ural',     'w'=>['Kavkaz','Himaláje']],
        ['q'=>'Největší ostrov světa:',        'a'=>'Grónsko',     'w'=>['Nová Guinea','Borneo']],
        ['q'=>'Největší kontinent:',           'a'=>'Asie',        'w'=>['Afrika','Evropa']],
        ['q'=>'Nejmenší kontinent:',           'a'=>'Austrálie',   'w'=>['Evropa','Antarktida']],
    ],
];

$cat     = $_GET['cat']  ?? array_key_first($categories);
$mapType = $_GET['map']  ?? 'europe';
$mode    = $_GET['mode'] ?? 'questions';
if (!isset($categories[$cat])) $cat = array_key_first($categories);

// Připrav otázky s choices
$rawQ = $categories[$cat];
shuffle($rawQ);
$rawQ = array_slice($rawQ, 0, 12);
$questions = array_map(fn($q) => [
    'q'       => $q['q'],
    'a'       => $q['a'],
    'choices' => (function() use ($q) {
        $arr = array_merge($q['w'], [$q['a']]);
        shuffle($arr);
        return $arr;
    })(),
], $rawQ);

$pageTitle = 'Zeměpis';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($mode === 'map'): ?>
<!-- Leaflet CSS — načte se z CDN v prohlížeči uživatele -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
#mapLeaflet { height: 460px; border-radius: 10px; background: #0d1b2e; }
.leaflet-tile-pane { filter: grayscale(40%) brightness(0.7) hue-rotate(200deg); }
</style>
<?php endif; ?>

<div class="page-header">
    <h1>🌍 <span class="accent">Zeměpis</span></h1>
</div>

<div class="mode-tabs">
    <a href="?mode=questions&cat=<?= urlencode($cat) ?>" class="mode-tab <?= $mode==='questions'?'active':'' ?>">❓ Otázky</a>
    <a href="?mode=map&map=europe"                       class="mode-tab <?= $mode==='map'      ?'active':'' ?>">🗺 Slepé mapy</a>
</div>

<?php if ($mode === 'questions'): ?>
<!-- ══ OTÁZKY s 3 možnostmi ═══════════════════════════════════════════════ -->
<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Kategorie:</span>
        <?php foreach (array_keys($categories) as $c): ?>
        <a href="?mode=questions&cat=<?= urlencode($c) ?>" class="filter-btn <?= $c===$cat?'active':'' ?>"><?= htmlspecialchars($c) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="game-container mc-container" id="gameContainer">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="statRemain"><?= count($questions) ?></span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="statTime">0</span><span class="gstat-label">sekund</span></div>
    </div>

    <div id="startWrapper" style="text-align:center;padding:2.5rem 0">
        <p style="color:var(--muted);margin-bottom:1.25rem">Vyber správnou odpověď ze 3 možností.<br>Klávesy <kbd>A</kbd> <kbd>B</kbd> <kbd>C</kbd> nebo kliknutí.</p>
        <button id="startBtn" class="btn-primary" style="font-size:1.1rem;padding:.85rem 2.5rem">Začít ▶</button>
    </div>

    <div class="mc-question-wrapper" id="mcWrapper" style="display:none">
        <div class="mc-progress-dots" id="mcDots"></div>
        <div class="mc-question" id="mcQuestion"></div>
        <div class="mc-choices" id="mcChoices"></div>
        <div class="mc-feedback" id="mcFeedback"></div>
    </div>

    <div class="progress-bar-wrapper" style="margin-top:1.25rem">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>🌍 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="geoMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="?mode=questions&cat=<?= urlencode($cat) ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const GEO_QUESTIONS = <?= json_encode(array_values($questions)) ?>;
const SAVE_URL = '<?= BASE_URL ?>/games/geography.php';
</script>
<script src="<?= BASE_URL ?>/js/geo_mc.js"></script>

<?php else: ?>
<!-- ══ SLEPÉ MAPY — Leaflet + OpenStreetMap ════════════════════════════════ -->
<div class="filters" style="margin-bottom:1.25rem">
    <div class="filter-group">
        <span class="filter-label">Mapa:</span>
        <a href="?mode=map&map=europe" class="filter-btn <?= $mapType==='europe'?'active':'' ?>">🌍 Evropa — státy</a>
        <a href="?mode=map&map=czech"  class="filter-btn <?= $mapType==='czech' ?'active':'' ?>">🇨🇿 Kraje ČR</a>
    </div>
</div>

<div class="map-game-wrapper">
    <div class="map-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="mapScore">0</span><span class="gstat-label">správně</span></div>
        <div class="game-stat"><span class="gstat-value" id="mapErrors">0</span><span class="gstat-label">chyb</span></div>
        <div class="game-stat"><span class="gstat-value" id="mapRemain">–</span><span class="gstat-label">zbývá</span></div>
        <div class="game-stat"><span class="gstat-value" id="mapTime">0</span><span class="gstat-label">sekund</span></div>
    </div>

    <!-- Otázka nad mapou -->
    <div class="map-question-bar" style="margin-bottom:.75rem">
        <div class="map-hint" id="mapHint" style="font-size:1.05rem">
            Stiskni Začít — pojmenuj zvýrazněný <?= $mapType==='europe'?'stát':'kraj' ?>
        </div>
        <div id="mapFeedback" class="math-feedback" style="min-height:1.5rem;font-size:1rem"></div>
    </div>

    <!-- Leaflet mapa -->
    <div id="mapLeaflet"></div>

    <!-- Evropa: 3 tlačítka A/B/C -->
    <?php if ($mapType === 'europe'): ?>
    <div id="mapChoicesWrapper" style="max-width:560px;margin:1rem auto 0">
        <div class="mc-choices" id="mapChoices" style="display:none"></div>
        <div style="text-align:center;margin-top:.75rem">
            <button id="mapStartBtn" class="btn-primary">Začít ▶</button>
            <button id="mapResetBtn" class="btn-secondary" style="display:none">↺ Znovu</button>
        </div>
        <div style="text-align:center;margin-top:.4rem;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">
            Klávesy <kbd>A</kbd> <kbd>B</kbd> <kbd>C</kbd> nebo kliknutí
        </div>
    </div>
    <?php else: ?>
    <!-- Kraje ČR: textový vstup -->
    <div class="typing-input-wrapper" style="max-width:520px;margin:1rem auto 0">
        <input type="text" id="mapInput" class="typing-input"
               placeholder="napiš název a stiskni Enter..."
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" disabled>
        <button id="mapStartBtn" class="btn-primary">Začít ▶</button>
        <button id="mapResetBtn" class="btn-secondary" style="display:none">↺ Znovu</button>
    </div>
    <?php endif; ?>

    <div class="progress-bar-wrapper" style="margin-top:1rem">
        <div class="progress-bar" id="mapProgress" style="width:0%"></div>
    </div>
</div>

<!-- Výsledky -->
<div class="results-panel" id="mapResultsPanel" style="display:none">
    <h2>🗺 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="mapResFinalScore">–</div><div class="result-label">Správně</div></div>
        <div class="result-item"><div class="result-value" id="mapResFinalErrors">–</div><div class="result-label">Chyb</div></div>
        <div class="result-item"><div class="result-value" id="mapResFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="mapResFinalTime">–</div><div class="result-label">Čas</div></div>
    </div>
    <div id="mapMistakes" style="margin:1.5rem 0;text-align:left;max-width:460px;margin-inline:auto"></div>
    <div class="results-actions">
        <a href="?mode=map&map=<?= $mapType ?>" class="btn-primary">↺ Hrát znovu</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="mapSaveStatus" class="save-status"></div>
</div>

<script>
const MAP_TYPE   = '<?= $mapType ?>';
const EUROPE_ISO = <?= json_encode($europeISO) ?>;
const SAVE_URL   = '<?= BASE_URL ?>/games/geography.php';
</script>
<!-- Kraje ČR: bundlováno lokálně — MUSÍ být před blind_map.js -->
<script src="<?= BASE_URL ?>/js/czech_regions.js"></script>
<!-- Leaflet + topojson z CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/topojson/3.0.2/topojson.min.js"></script>
<script src="<?= BASE_URL ?>/js/blind_map.js"></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
