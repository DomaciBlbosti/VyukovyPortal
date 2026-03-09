<?php
// games/classic.php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => saveGameSession([
        'game_type'        => 'classic',
        'wpm'              => floatval($_POST['wpm']          ?? 0),
        'accuracy'         => floatval($_POST['accuracy']     ?? 0),
        'duration_seconds' => intval($_POST['duration']       ?? 0),
        'chars_typed'      => intval($_POST['chars_typed']    ?? 0),
        'errors'           => intval($_POST['errors']         ?? 0),
        'text_snippet'     => substr($_POST['text_snippet']   ?? '', 0, 500),
    ])]);
    exit;
}

// ─── LEKCE — metodika psaní všemi deseti ──────────────────────────────────
// Princip: začínáme kotevními klávesami F+J (hmatová vodítka),
// pak přidáváme vždy jeden pár symetricky z obou rukou.
$lessons = [

    // ════ MODUL 1 — Základní řada (home row) ════════════════════════════════

    [
        'module' => 'Základní řada',
        'title'  => 'F + J — kotevní klávesy',
        'keys'   => 'F  J',
        'hint'   => 'Ukazováčky. Na obou klávesách cítíš hrbolek — to je tvůj domov.',
        'text'   => 'fj fj fj jf jf jf ff jj fj jf fjf jfj ffj jjf fjj jff fj jf fj jf ffjj jjff fjfj jfjf',
    ],
    [
        'module' => 'Základní řada',
        'title'  => 'D + K — prostředníčky',
        'keys'   => 'D  K',
        'hint'   => 'Prostředníčky. Přidáváme k F a J.',
        'text'   => 'dk kd dk kd dd kk dkd kdk ddk kkd fdjk kjdf jkfd dkfj fj dk jf kd fjdk dkfj jkfd fdkj',
    ],
    [
        'module' => 'Základní řada',
        'title'  => 'S + L — prstníčky',
        'keys'   => 'S  L',
        'hint'   => 'Prstníčky. Pozor — levý prstníček je slabší, trénuj ho pečlivě.',
        'text'   => 'sl ls sl ls ss ll sls lsl ssl lls fj dk sl jf kd ls fjdk sldk fjsl dksl fjdksl lkjfds',
    ],
    [
        'module' => 'Základní řada',
        'title'  => 'A — levý malíček',
        'keys'   => 'A',
        'hint'   => 'Levý malíček. Nejslabší prst — věnuj mu pozornost.',
        'text'   => 'aa aa aaa aas aad aaf asa ada afa sal dal fal lad sad ask alas flask salad dallas alaska',
    ],
    [
        'module' => 'Základní řada',
        'title'  => 'Celá základní řada',
        'keys'   => 'A S D F J K L',
        'hint'   => 'Všechny klávesy základní řady. Vracíme se vždy do výchozí pozice.',
        'text'   => 'asdfjkl lkjfdsa fj dk sl aj sk dl fajl daks slfa kjda sad lad ask fall flask salad dallas',
    ],
    [
        'module' => 'Základní řada',
        'title'  => 'G + H — ukazováčky dovnitř',
        'keys'   => 'G  H',
        'hint'   => 'Ukazováčky se táhnou ke středu. G levý, H pravý.',
        'text'   => 'gh hg gh hg gg hh ghg hgh ggh hhg glad hash gash half flag dash gala hall flash ashfall',
    ],

    // ════ MODUL 2 — Horní řada ══════════════════════════════════════════════

    [
        'module' => 'Horní řada',
        'title'  => 'E + I — prostředníčky nahoru',
        'keys'   => 'E  I',
        'hint'   => 'Prostředníčky se zvedají do horní řady. Krátký pohyb.',
        'text'   => 'ei ie ei ie ee ii eie iei eei iie aide idea side hide file like life disk silk idle diesel',
    ],
    [
        'module' => 'Horní řada',
        'title'  => 'R + U — ukazováčky nahoru',
        'keys'   => 'R  U',
        'hint'   => 'Ukazováčky nahoru. R vlevo, U vpravo.',
        'text'   => 'ru ur ru ur rr uu rur uru rru uur rural fur rude rule lure sure user rust dust just guru',
    ],
    [
        'module' => 'Horní řada',
        'title'  => 'T + Y — ukazováčky dál nahoru',
        'keys'   => 'T  Y',
        'hint'   => 'Ukazováčky se natahují dále. T levý, Y pravý.',
        'text'   => 'ty yt ty yt tt yy tyt yty tty yyt try yet they stay duty truly style their teeth healthy',
    ],
    [
        'module' => 'Horní řada',
        'title'  => 'W + O — prstníčky nahoru',
        'keys'   => 'W  O',
        'hint'   => 'Prstníčky do horní řady. W vlevo, O vpravo.',
        'text'   => 'wo ow wo ow ww oo wow owo wwо oow work word flow slow glow crow grow wood good food wolf',
    ],
    [
        'module' => 'Horní řada',
        'title'  => 'Q + P — malíčky nahoru',
        'keys'   => 'Q  P',
        'hint'   => 'Malíčky do horní řady. Nejdelší skok — buď přesný.',
        'text'   => 'qp pq qp pq qq pp qpq pqp qqp ppq quip trip drip prep drop prop plot plus quit equal equip',
    ],
    [
        'module' => 'Horní řada',
        'title'  => 'Celá horní řada',
        'keys'   => 'Q W E R T Y U I O P',
        'hint'   => 'Celá horní řada dohromady.',
        'text'   => 'quite write their fruit tiger quote power tower outer where properquierurity poetry quiet',
    ],

    // ════ MODUL 3 — Dolní řada ══════════════════════════════════════════════

    [
        'module' => 'Dolní řada',
        'title'  => 'V + M — ukazováčky dolů',
        'keys'   => 'V  M',
        'hint'   => 'Ukazováčky se skloní dolů. V vlevo, M vpravo.',
        'text'   => 'vm mv vm mv vv mm vmv mvm vvm mmv vam vim move live give have made male fame valve marvel',
    ],
    [
        'module' => 'Dolní řada',
        'title'  => 'C + vírgula — prostředníčky dolů',
        'keys'   => 'C  ,',
        'hint'   => 'Prostředníčky dolů. Čárka je důležitá v textu.',
        'text'   => 'cc cc ccc ace ice rice mice dice face lace race voice civic cycle crack creek clear slice',
    ],
    [
        'module' => 'Dolní řada',
        'title'  => 'X + tečka — prstníčky dolů',
        'keys'   => 'X  .',
        'hint'   => 'Prstníčky dolů. Tečka ukončuje věty.',
        'text'   => 'xx xx xxx hex sex fox mix fix axe exit exam exact exert oxide expel expert exceed excel',
    ],
    [
        'module' => 'Dolní řada',
        'title'  => 'Z + B + N — zbývající klávesy',
        'keys'   => 'Z  B  N',
        'hint'   => 'Z malíčkem dolů. B a N ukazováčky.',
        'text'   => 'zz bb nn zinc zone zone burn bone born name none bank bind blend bronze zombie cabin number',
    ],
    [
        'module' => 'Dolní řada',
        'title'  => 'Celá dolní řada',
        'keys'   => 'Z X C V B N M',
        'hint'   => 'Celá dolní řada dohromady.',
        'text'   => 'zombie venom climb bench vanish mexico bronze cabin number combine maximize vibrant examine',
    ],

    // ════ MODUL 4 — Česká diakritika ════════════════════════════════════════

    [
        'module' => 'Diakritika',
        'title'  => 'Dlouhé samohlásky á é í',
        'keys'   => 'Á  É  Í',
        'hint'   => 'Háček prodlužuje samohlásku. Pravý malíček na klávese vpravo.',
        'text'   => 'Praha máma táta sáně láska péče réva síla víra díra lípa bílá mísa píle sníh pálí',
    ],
    [
        'module' => 'Diakritika',
        'title'  => 'Dlouhé samohlásky ó ú ů ý',
        'keys'   => 'Ó  Ú  Ů  Ý',
        'hint'   => 'Ú na začátku slova, Ů uprostřed a na konci.',
        'text'   => 'ústa úkol útok únor úřad dům důl půda růže kůže být výr výše výlet dýchat nový starý',
    ],
    [
        'module' => 'Diakritika',
        'title'  => 'Háčkované souhlásky č š ž',
        'keys'   => 'Č  Š  Ž',
        'hint'   => 'Tři nejčastější háčkované souhlásky v češtině.',
        'text'   => 'čas čelo číslo šel šedý šaty žák žába žízeň česky šedivý žlutý čerstvý švestka žebřík',
    ],
    [
        'module' => 'Diakritika',
        'title'  => 'Háčkované souhlásky ř ň ě',
        'keys'   => 'Ř  Ň  Ě',
        'hint'   => 'Ř je unikátní česká hláska. Ě se skoro nevyskytuje na začátku.',
        'text'   => 'řeka řepa řídí řemeslo něco něha někdo věc věda větaště děti pěst přes třetí čtvrtý',
    ],
    [
        'module' => 'Diakritika',
        'title'  => 'Smíšená diakritika',
        'keys'   => 'Celá česká klávesnice',
        'hint'   => 'Kombinace všech háčků a čárek.',
        'text'   => 'Příliš žluťoučký kůň úpěl ďábelské ódy. Češi píší česky. Šiška není žiška. Řeřicha.',
    ],

    // ════ MODUL 5 — Plné věty ═══════════════════════════════════════════════

    [
        'module' => 'Plné věty',
        'title'  => 'Věty o psaní',
        'keys'   => 'Celá klávesnice',
        'hint'   => 'Soustřeď se na rytmus, ne na rychlost.',
        'text'   => 'Správné držení těla při psaní je stejně důležité jako správná technika. Záda rovně, lokty v úhlu devadesáti stupňů.',
    ],
    [
        'module' => 'Plné věty',
        'title'  => 'Motivační text',
        'keys'   => 'Celá klávesnice',
        'hint'   => 'Plynulý text bez zastavování.',
        'text'   => 'Procvičování psaní každý den po dobu dvaceti minut výrazně zlepší tvou rychlost. Nezapomínej se vracet na základní řadu po každém stisku.',
    ],
    [
        'module' => 'Plné věty',
        'title'  => 'Rychlý hnědý fox',
        'keys'   => 'Celá klávesnice',
        'hint'   => 'Klasický pangram — obsahuje téměř všechna písmena.',
        'text'   => 'Rychlá hnědá liška přeskočila přes líného psa. Pak se otočila a skočila zpět. Byl to skvělý výkon.',
    ],
    [
        'module' => 'Plné věty',
        'title'  => 'Delší odstavec',
        'keys'   => 'Celá klávesnice',
        'hint'   => 'Test vytrvalosti. Udržuj stejné tempo po celou dobu.',
        'text'   => 'Klávesnice je nástroj jako každý jiný. Čím více cvičíš, tím přirozenější tvoje psaní bude. Soustřeď se na přesnost a rychlost přijde sama.',
    ],

    // ════ MODUL 6 — Čísla a interpunkce ════════════════════════════════════

    [
        'module' => 'Čísla',
        'title'  => 'Čísla 1–5 (levá ruka)',
        'keys'   => '1  2  3  4  5',
        'hint'   => 'Čísla jsou v horní řadě. Levá ruka pokrývá 1 až 5.',
        'text'   => '1 2 3 4 5 11 22 33 44 55 12 21 34 43 15 51 123 321 12345 54321 1234 2345 345 245 135',
    ],
    [
        'module' => 'Čísla',
        'title'  => 'Čísla 6–0 (pravá ruka)',
        'keys'   => '6  7  8  9  0',
        'hint'   => 'Pravá ruka pokrývá 6 až 0.',
        'text'   => '6 7 8 9 0 66 77 88 99 00 67 76 89 98 60 06 678 987 67890 09876 6789 7890 890 790 680',
    ],
    [
        'module' => 'Čísla',
        'title'  => 'Čísla ve větách',
        'keys'   => 'Čísla + text',
        'hint'   => 'Kombinace čísel a písmen — přepínání vyžaduje pozornost.',
        'text'   => 'V roce 2024 se narodilo 12 000 dětí. Cesta trvala 3 hodiny a 45 minut. Cena je 199 korun.',
    ],
    [
        'module' => 'Interpunkce',
        'title'  => 'Tečka a čárka',
        'keys'   => '.  ,',
        'hint'   => 'Nejčastější interpunkce. Prstníčky pravé ruky.',
        'text'   => 'Ahoj, jak se máš. Dobře, díky. Kde bydlíš. V Praze, blízko centra. Chodíš do školy, nebo do práce.',
    ],
    [
        'module' => 'Interpunkce',
        'title'  => 'Otazník a vykřičník',
        'keys'   => '?  !',
        'hint'   => 'Shift + klávesa. Pozor na správný malíček.',
        'text'   => 'Jak se jmenuješ? Kolik ti je let? Výborně! Gratulujeme! Kdy přijdeš? Nevím! Snad brzy? Určitě!',
    ],
];

// Skupiny modulů
$modules = [];
foreach ($lessons as $i => $l) {
    $modules[$l['module']][] = array_merge($l, ['index' => $i]);
}

// Výběr lekce
$selectedIndex  = isset($_GET['lesson']) ? max(0, min(intval($_GET['lesson']), count($lessons)-1)) : 0;
$selectedLesson = $lessons[$selectedIndex];

$pageTitle = 'Klasický režim';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📝 Klasický <span class="accent">režim</span></h1>
    <p class="page-subtitle">Přepiš text co nejrychleji a nejpřesněji</p>
</div>

<!-- Výběr lekce -->
<div class="lesson-picker">
    <?php foreach ($modules as $modName => $lsns): ?>
    <div class="lesson-group">
        <div class="lesson-group-title"><?= htmlspecialchars($modName) ?></div>
        <div class="lesson-buttons">
            <?php foreach ($lsns as $l): ?>
            <a href="?lesson=<?= $l['index'] ?>"
               class="lesson-btn <?= $l['index'] === $selectedIndex ? 'active' : '' ?>"
               title="<?= htmlspecialchars($l['title']) ?>">
                <?= $l['index'] + 1 ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Info o lekci -->
<div class="lesson-info">
    <span class="lesson-info-num">Lekce <?= $selectedIndex + 1 ?></span>
    <span class="lesson-info-title"><?= htmlspecialchars($selectedLesson['title']) ?></span>
    <span class="lesson-info-keys">⌨ <?= htmlspecialchars($selectedLesson['keys']) ?></span>
</div>
<?php if (!empty($selectedLesson['hint'])): ?>
<div class="lesson-hint">💡 <?= htmlspecialchars($selectedLesson['hint']) ?></div>
<?php endif; ?>

<div class="game-container" id="gameContainer">
    <div class="game-stats-bar">
        <div class="game-stat"><span class="gstat-value" id="statWpm">0</span><span class="gstat-label">WPM</span></div>
        <div class="game-stat"><span class="gstat-value" id="statAccuracy">100</span><span class="gstat-label">% přesnost</span></div>
        <div class="game-stat"><span class="gstat-value" id="statTime">0</span><span class="gstat-label">sekund</span></div>
        <div class="game-stat"><span class="gstat-value" id="statErrors">0</span><span class="gstat-label">chyb</span></div>
    </div>

    <div class="typing-text-wrapper">
        <div class="typing-text" id="typingText">
            <?php foreach (mb_str_split($selectedLesson['text']) as $char): ?>
                <span class="tchar"><?= $char === ' ' ? '&nbsp;' : htmlspecialchars($char) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="typing-input-wrapper">
        <input type="text" id="typingInput" class="typing-input"
               placeholder="Klikni sem a začni psát..."
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" disabled>
        <button id="startBtn" class="btn-primary">Začít hru ▶</button>
        <button id="resetBtn" class="btn-secondary" style="display:none">↺ Znovu</button>
    </div>

    <div class="progress-bar-wrapper">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>
</div>

<div class="results-panel" id="resultsPanel" style="display:none">
    <h2>🎉 Výsledek</h2>
    <div class="results-stats">
        <div class="result-item"><div class="result-value" id="resFinalWpm">–</div><div class="result-label">WPM</div></div>
        <div class="result-item"><div class="result-value" id="resFinalAccuracy">–</div><div class="result-label">Přesnost</div></div>
        <div class="result-item"><div class="result-value" id="resFinalTime">–</div><div class="result-label">Čas</div></div>
        <div class="result-item"><div class="result-value" id="resFinalErrors">–</div><div class="result-label">Chyb</div></div>
    </div>
    <div class="results-actions">
        <button id="playAgainBtn" class="btn-secondary">↺ Znovu</button>
        <?php if (isset($lessons[$selectedIndex + 1])): ?>
        <a href="?lesson=<?= $selectedIndex + 1 ?>" class="btn-primary">Další lekce →</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">← Rozcestník</a>
    </div>
    <div id="saveStatus" class="save-status"></div>
</div>

<script>
const GAME_TEXT = <?= json_encode($selectedLesson['text']) ?>;
const SAVE_URL  = '<?= BASE_URL ?>/games/classic.php';
</script>
<script src="<?= BASE_URL ?>/js/typing_game.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
