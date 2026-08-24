<?php
/**
 * Datová sada pro češtinu — doplňování i/y.
 *
 * Formát položky:
 *   'text'    — zadání s podtržítkem na místě chybějícího písmene
 *   'correct' — správné písmeno (i, í, y, ý); podle délky se odvodí
 *               dvojice tlačítek (i/y nebo í/ý)
 *   'hint'    — vysvětlení, které se ukáže po odpovědi
 *
 * Sady jsou rozdělené podle ročníku: vyjmenovaná slova se probírají
 * od 3. třídy, dvojice podle významu od 4., koncovky a shoda od 6.
 */

require_once __DIR__ . '/czech_vyjmenovana.php';

/** @return array<string, array{label:string, icon:string, grades:array<int>, items:array}> */
function czechCategories(): array {
    // Sady se skládají z několika set položek — sestav je jednou za požadavek
    static $cache = null;
    if ($cache !== null) return $cache;

    return $cache = [

    // ══ VYJMENOVANÁ SLOVA — po jednotlivých písmenech (3.–5. třída) ══════

    'vyjm_b' => ['label' => 'Vyjmenovaná slova po B', 'icon' => '🅱️', 'grades' => [3,4,5,6,7,8,9], 'items' => czechWordsB()],

    'vyjm_l' => ['label' => 'Vyjmenovaná slova po L', 'icon' => '🇱', 'grades' => [3,4,5,6,7,8,9], 'items' => czechWordsL()],

    'vyjm_m' => ['label' => 'Vyjmenovaná slova po M', 'icon' => 'Ⓜ️', 'grades' => [3,4,5,6,7,8,9], 'items' => czechWordsM()],

    'vyjm_p' => ['label' => 'Vyjmenovaná slova po P', 'icon' => '🅿️', 'grades' => [4,5,6,7,8,9], 'items' => czechWordsP()],

    'vyjm_s' => ['label' => 'Vyjmenovaná slova po S', 'icon' => '🇸', 'grades' => [4,5,6,7,8,9], 'items' => czechWordsS()],

    'vyjm_v' => ['label' => 'Vyjmenovaná slova po V', 'icon' => '🇻', 'grades' => [4,5,6,7,8,9], 'items' => czechWordsV()],

    'vyjm_z' => ['label' => 'Vyjmenovaná slova po Z', 'icon' => '🇿', 'grades' => [4,5,6,7,8,9], 'items' => czechWordsZ()],

    'predpona_vy' => ['label' => 'Předpona vy-/vý-', 'icon' => '➡️', 'grades' => [4,5,6,7,8,9],
                      'items' => czechWordsPredponaVy()],

    'vyjm_vse' => ['label' => 'Všechna vyjmenovaná slova', 'icon' => '🎲', 'grades' => [4,5,6,7,8,9],
                   'items' => array_merge(czechWordsB(), czechWordsL(), czechWordsM(), czechWordsP(),
                                          czechWordsS(), czechWordsV(), czechWordsZ())],

    // ══ DVOJICE PODLE VÝZNAMU — rozhoduje kontext (4.–9. třída) ═══════════

    'dvojice' => ['label' => 'Dvojice podle významu', 'icon' => '🔀', 'grades' => [4,5,6,7,8,9], 'items' => [
        ['text' => 'Chlapec b_l míčem do zdi.',        'correct' => 'i', 'hint' => 'bil = tloukl (od bít)'],
        ['text' => 'Včera b_l doma celý den.',         'correct' => 'y', 'hint' => 'byl = od slovesa být'],
        ['text' => 'Maminka nab_la peněženku drobnými.','correct' => 'i', 'hint' => 'nabila = naplnila (od bít/nabít)'],
        ['text' => 'M_ jdeme do kina.',                'correct' => 'y', 'hint' => 'my = zájmeno, vyjmenované slovo'],
        ['text' => 'Půjč m_ tu knihu.',                'correct' => 'i', 'hint' => 'mi = tvar zájmena já'],
        ['text' => 'Musíš si um_t ruce.',              'correct' => 'ý', 'hint' => 'umýt = od mýt'],
        ['text' => 'Chtěl bych m_t nové kolo.',        'correct' => 'í', 'hint' => 'mít = vlastnit'],
        ['text' => 'Vítr v_l celou noc.',              'correct' => 'y', 'hint' => 'vyl = od výt (vydávat zvuk)'],
        ['text' => 'Dědeček v_l provaz ze slámy.',     'correct' => 'i', 'hint' => 'vil = od vít (splétat)'],
        ['text' => 'L_ška se plížila k slepicím.',     'correct' => 'i', 'hint' => 'liška — není vyjmenované slovo'],
        ['text' => 'Na jaře v_kvetly bledule.',        'correct' => 'y', 'hint' => 'vykvetly — předpona vy-'],
        ['text' => 'Sl_šel jsem podivný zvuk.',        'correct' => 'y', 'hint' => 'slyšel — vyjmenované slovo'],
        ['text' => 'Ptáci se s_tili na krmítku.',      'correct' => 'y', 'hint' => 'sytili se = od sytý'],
        ['text' => 'Pes se p_šní novým obojkem.',      'correct' => 'y', 'hint' => 'pyšní se = od pýcha'],
        ['text' => 'Zahradník za_l semínka.',          'correct' => 'i', 'hint' => 'zasil/zasel — od sít (sázet semena)'],
    ]],

    // ══ MĚ / MNĚ — rozhoduje pád (4.–9. třída) ═══════════════════════════

    'me_mne' => ['label' => 'Mě / mně', 'icon' => '🙋', 'grades' => [4,5,6,7,8,9],
                 'options' => ['mě', 'mně'], 'items' => [
        ['text' => 'Bez _ to nezvládneš.',            'correct' => 'mě',  'hint' => '2. pád — dosaď „tebe": bez tebe'],
        ['text' => 'Řekl to _ , ne tobě.',            'correct' => 'mně', 'hint' => '3. pád — dosaď „tobě": řekl tobě'],
        ['text' => 'Podívej se na _.',               'correct' => 'mě',  'hint' => '4. pád — dosaď „tebe": na tebe'],
        ['text' => 'Mluvili o _ celý večer.',         'correct' => 'mně', 'hint' => '6. pád — dosaď „tobě": o tobě'],
        ['text' => 'Počkej na _ u školy.',            'correct' => 'mě',  'hint' => '4. pád — dosaď „tebe": na tebe'],
        ['text' => 'Ke _ se choval hezky.',           'correct' => 'mně', 'hint' => '3. pád — dosaď „tobě": k tobě'],
        ['text' => 'Viděl _ ve městě.',               'correct' => 'mě',  'hint' => '4. pád — dosaď „tebe": viděl tebe'],
        ['text' => 'Pomoz _ s úkolem.',               'correct' => 'mně', 'hint' => '3. pád — dosaď „tobě": pomoz tobě'],
        ['text' => 'Nezlob _ , prosím.',              'correct' => 'mě',  'hint' => '4. pád — dosaď „tebe": nezlob tebe'],
        ['text' => 'Sedni si vedle _.',              'correct' => 'mě',  'hint' => '2. pád — dosaď „tebe": vedle tebe'],
        ['text' => 'Udělal to kvůli _.',             'correct' => 'mně', 'hint' => '3. pád — dosaď „tobě": kvůli tobě'],
        ['text' => 'Ptali se _ na cestu.',            'correct' => 'mě',  'hint' => '2. pád — dosaď „tebe": ptali se tebe'],
        ['text' => 'Ke _ domů je to kousek.',         'correct' => 'mně', 'hint' => '3. pád — dosaď „tobě": k tobě domů'],
        ['text' => 'Vzali _ s sebou na výlet.',       'correct' => 'mě',  'hint' => '4. pád — dosaď „tebe": vzali tebe'],
        ['text' => 'Slyšeli jste o _ něco?',          'correct' => 'mně', 'hint' => '6. pád — dosaď „tobě": o tobě'],
        ['text' => 'Ta zpráva se ke _ nedostala.',    'correct' => 'mně', 'hint' => '3. pád — dosaď „tobě": k tobě'],
    ]],

    // ══ PŘEDLOŽKY S / Z — rozhoduje pád (5.–9. třída) ════════════════════

    'predlozky_sz' => ['label' => 'Předložky s / z', 'icon' => '🔤', 'grades' => [5,6,7,8,9],
                       'options' => ['s', 'z'], 'items' => [
        ['text' => 'Přišel _ kamarádem.',             'correct' => 's', 'hint' => '7. pád (s kým?) → s'],
        ['text' => 'Vrátil se _ výletu.',             'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Mluvil _ učitelem.',              'correct' => 's', 'hint' => '7. pád (s kým?) → s'],
        ['text' => 'Spadl _ kola.',                   'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Jedl polévku _ chutí.',           'correct' => 's', 'hint' => '7. pád (s čím?) → s'],
        ['text' => 'Vyndal knihu _ tašky.',           'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Šli jsme _ sestrou do kina.',     'correct' => 's', 'hint' => '7. pád (s kým?) → s'],
        ['text' => 'Vystoupil _ autobusu.',           'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Hrál si _ míčem na zahradě.',     'correct' => 's', 'hint' => '7. pád (s čím?) → s'],
        ['text' => 'Utekl _ hřiště domů.',            'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Souhlasím _ tebou.',              'correct' => 's', 'hint' => '7. pád (s kým?) → s'],
        ['text' => 'Sundej ten hrnek _ police.',      'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Setkal se _ ředitelem školy.',    'correct' => 's', 'hint' => '7. pád (s kým?) → s'],
        ['text' => 'Vyšel _ domu ven.',               'correct' => 'z', 'hint' => '2. pád (odkud?) → z'],
        ['text' => 'Pracuje _ počítačem.',            'correct' => 's', 'hint' => '7. pád (s čím?) → s'],
        ['text' => 'Přeložil to _ angličtiny.',       'correct' => 'z', 'hint' => '2. pád (z čeho?) → z'],
    ]],

    // ══ VELKÁ PÍSMENA (3.–9. třída) ══════════════════════════════════════

    'velka_pismena' => ['label' => 'Velká písmena', 'icon' => '🔠', 'grades' => [3,4,5,6,7,8,9], 'items' => [
        ['text' => 'Hlavní město je _raha.',          'correct' => 'P', 'hint' => 'Praha — vlastní jméno města'],
        ['text' => 'Chodím do _koly každý den.',      'correct' => 'š', 'hint' => 'škola — obecné podstatné jméno'],
        ['text' => 'Naše řeka se jmenuje _ltava.',    'correct' => 'V', 'hint' => 'Vltava — vlastní jméno řeky'],
        ['text' => 'V zimě padá _níh.',               'correct' => 's', 'hint' => 'sníh — obecné podstatné jméno'],
        ['text' => 'Mám rád _eskou republiku.',       'correct' => 'Č', 'hint' => 'Česká republika — název státu'],
        ['text' => 'Na zahradě roste _abloň.',        'correct' => 'j', 'hint' => 'jabloň — obecné podstatné jméno'],
        ['text' => 'Píšeme dopis _ovákovi.',          'correct' => 'N', 'hint' => 'Novák — příjmení'],
        ['text' => 'Vedle nás bydlí _oused.',         'correct' => 's', 'hint' => 'soused — obecné podstatné jméno'],
        ['text' => 'Byli jsme na _lovensku.',         'correct' => 'S', 'hint' => 'Slovensko — název státu'],
        ['text' => 'Koupili jsme nové _uto.',         'correct' => 'a', 'hint' => 'auto — obecné podstatné jméno'],
        ['text' => 'Sejdeme se v _rně.',              'correct' => 'B', 'hint' => 'Brno — vlastní jméno města'],
        ['text' => 'Ráno vychází _lunce.',            'correct' => 's', 'hint' => 'slunce — obecné podstatné jméno'],
        ['text' => 'Učíme se _nglicky.',              'correct' => 'a', 'hint' => 'anglicky — příslovce, píše se malé'],
        ['text' => 'Bratr studuje na _arlově univerzitě.', 'correct' => 'K', 'hint' => 'Karlova univerzita — vlastní název'],
        ['text' => 'V pondělí jsou _elikonoce.',      'correct' => 'V', 'hint' => 'Velikonoce — název svátku'],
        ['text' => 'Zítra je _terý.',                 'correct' => 'ú', 'hint' => 'úterý — dny v týdnu se píšou malým'],
    ]],

    // ══ Ú / Ů (3.–9. třída) ══════════════════════════════════════════════

    'u_kruzek' => ['label' => 'Ú / ů', 'icon' => '🔵', 'grades' => [3,4,5,6,7,8,9],
                   'options' => ['ú', 'ů'], 'items' => [
        ['text' => '_kol jsme splnili včas.',         'correct' => 'ú', 'hint' => 'úkol — na začátku slova se píše ú'],
        ['text' => 'Na dvoře stojí k_ň.',             'correct' => 'ů', 'hint' => 'kůň — uvnitř slova se píše ů'],
        ['text' => 'Dej mi to do _schovy.',           'correct' => 'ú', 'hint' => 'úschova — na začátku slova se píše ú'],
        ['text' => 'Koupili jsme nový st_l.',         'correct' => 'ů', 'hint' => 'stůl — uvnitř slova se píše ů'],
        ['text' => 'Je to velká _spora času.',        'correct' => 'ú', 'hint' => 'úspora — na začátku slova se píše ú'],
        ['text' => 'Vzal si sv_j batoh.',             'correct' => 'ů', 'hint' => 'svůj — uvnitř slova se píše ů'],
        ['text' => 'Ten výhled byl _žasný.',          'correct' => 'ú', 'hint' => 'úžasný — na začátku slova se píše ú'],
        ['text' => 'Přišel dom_ pozdě.',              'correct' => 'ů', 'hint' => 'domů — na konci slova se píše ů'],
        ['text' => 'Máme doma _tulný pokoj.',         'correct' => 'ú', 'hint' => 'útulný — na začátku slova se píše ú'],
        ['text' => 'Šli jsme dol_ ze schodů.',        'correct' => 'ů', 'hint' => 'dolů — na konci slova se píše ů'],
        ['text' => 'Měl v testu velký _spěch.',       'correct' => 'ú', 'hint' => 'úspěch — na začátku slova se píše ú'],
        ['text' => 'Do polévky patří s_l.',           'correct' => 'ů', 'hint' => 'sůl — uvnitř slova se píše ů'],
        ['text' => 'Dopis má tři _seky.',             'correct' => 'ú', 'hint' => 'úseky — na začátku slova se píše ú'],
        ['text' => 'Uklidil jsem si p_du.',           'correct' => 'ů', 'hint' => 'půdu — uvnitř slova se píše ů'],
        ['text' => 'Naštěstí se mu nestal _raz.',     'correct' => 'ú', 'hint' => 'úraz — na začátku slova se píše ú'],
        ['text' => 'Postavili u nás nový d_m.',       'correct' => 'ů', 'hint' => 'dům — uvnitř slova se píše ů'],
    ]],

    // ══ KONCOVKY PODSTATNÝCH JMEN (6. třída a výš) ════════════════════════

    'koncovky' => ['label' => 'Koncovky podstatných jmen', 'icon' => '📐', 'grades' => [6,7,8,9], 'items' => [
        ['text' => 'Na zahradě rostou květ_.',        'correct' => 'y', 'hint' => 'květy — rod mužský neživotný, vzor hrad'],
        ['text' => 'Viděli jsme dva ps_.',            'correct' => 'y', 'hint' => 'psy — 4. pád mn. č., vzor pán'],
        ['text' => 'Ps_ štěkali na kolemjdoucí.',     'correct' => 'i', 'hint' => 'psi — 1. pád mn. č. životný, vzor pán'],
        ['text' => 'Na louce se pásly kráv_.',        'correct' => 'y', 'hint' => 'krávy — rod ženský, vzor žena'],
        ['text' => 'Sousedé mají tři kočk_.',         'correct' => 'y', 'hint' => 'kočky — vzor žena, 4. pád mn. č.'],
        ['text' => 'V lese jsme našli hřib_.',        'correct' => 'y', 'hint' => 'hřiby — rod mužský neživotný, vzor hrad'],
        ['text' => 'Na poli pracovali traktor_.',      'correct' => 'y', 'hint' => 'traktory — rod mužský neživotný, vzor hrad'],
        ['text' => 'Na stromě sedí sov_.',            'correct' => 'y', 'hint' => 'sovy — rod ženský, vzor žena'],
        ['text' => 'Koupili jsme nové stol_.',        'correct' => 'y', 'hint' => 'stoly — rod mužský neživotný, vzor hrad'],
        ['text' => 'Pod postelí byly bot_.',          'correct' => 'y', 'hint' => 'boty — rod ženský, vzor žena'],
        ['text' => 'Dva chlapc_ si hráli na písku.',   'correct' => 'i', 'hint' => 'chlapci — 1. pád mn. č. životný, vzor pán'],
        ['text' => 'V rybníce plavou kapř_.',          'correct' => 'i', 'hint' => 'kapři — 1. pád mn. č. životný, vzor muž'],
        ['text' => 'Před domem stáli hasič_.',         'correct' => 'i', 'hint' => 'hasiči — 1. pád mn. č. životný, vzor muž'],
        ['text' => 'Na poli pracovali muž_.',          'correct' => 'i', 'hint' => 'muži — 1. pád mn. č. životný, vzor muž'],
        ['text' => 'Ve výběhu odpočívali lv_.',        'correct' => 'i', 'hint' => 'lvi — 1. pád mn. č. životný, vzor pán'],
    ]],

    // ══ SHODA PŘÍSUDKU S PODMĚTEM (6. třída a výš) ════════════════════════

    'shoda' => ['label' => 'Shoda přísudku s podmětem', 'icon' => '🔗', 'grades' => [6,7,8,9], 'items' => [
        ['text' => 'Chlapci si hrál_ na hřišti.',       'correct' => 'i', 'hint' => 'rod mužský životný → měkké i'],
        ['text' => 'Stromy se ohýbal_ ve větru.',       'correct' => 'y', 'hint' => 'rod mužský neživotný → tvrdé y'],
        ['text' => 'Dívky zpíval_ ve sboru.',           'correct' => 'y', 'hint' => 'rod ženský → tvrdé y'],
        ['text' => 'Koťata si spokojeně předla, byl_ sytá.', 'correct' => 'a', 'hint' => 'rod střední mn. č. → koncovka -a'],
        ['text' => 'Ženy se sešl_ v knihovně.',         'correct' => 'y', 'hint' => 'rod ženský → tvrdé y'],
        ['text' => 'Psi hlídal_ dvůr.',                 'correct' => 'i', 'hint' => 'rod mužský životný → měkké i'],
        ['text' => 'Auta stál_ v koloně.',              'correct' => 'a', 'hint' => 'rod střední mn. č. → koncovka -a'],
        ['text' => 'Kluci a holky běžel_ o závod.',     'correct' => 'i', 'hint' => 'je-li mezi podměty rod mužský životný, píše se i'],
        ['text' => 'Talíře spadl_ ze stolu.',           'correct' => 'y', 'hint' => 'rod mužský neživotný → tvrdé y'],
        ['text' => 'Naše kočky rád_ spí na okně.',      'correct' => 'y', 'hint' => 'rod ženský → tvrdé y'],
    ]],

    ];
}

/** Sady dostupné pro daný ročník (0 = neuvedeno → všechny) */
function czechCategoriesForGrade(int $grade): array {
    $all = czechCategories();
    if ($grade < 1) return $all;
    return array_filter($all, fn($c) => in_array($grade, $c['grades'], true));
}

/**
 * Nabídka tlačítek pro úlohu. U shody přísudku se vždy nabízí i/y/a,
 * aby počet možností sám neprozradil odpověď; jinde rozhoduje délka
 * samohlásky (krátké i/y vs. dlouhé í/ý).
 */
function czechOptions(string $correct, string $category = ''): array {
    // U velkých písmen se nabídka odvozuje z konkrétního slova
    if ($category === 'velka_pismena') {
        return [mb_strtoupper($correct, 'UTF-8'), mb_strtolower($correct, 'UTF-8')];
    }
    // Sada si může nabídku určit sama (mě/mně, s/z, ú/ů)
    $cat = czechCategories()[$category] ?? null;
    if ($cat && !empty($cat['options'])) return $cat['options'];

    if ($category === 'shoda') return ['i', 'y', 'a'];
    return in_array($correct, ['í', 'ý'], true) ? ['í', 'ý'] : ['i', 'y'];
}

/**
 * Skupina odpovědi pro vyvážení kola. Kdyby se úlohy losovaly čistě náhodně,
 * mohla by vyjít samá slova s ypsilonem a dítě by prošlo strategií „mačkej
 * pořád y". Krátké a dlouhé samohlásky patří do stejné skupiny, u velkých
 * písmen rozhoduje jen velikost, ne konkrétní písmeno.
 */
function czechAnswerGroup(string $correct, string $category = ''): string {
    if ($category === 'velka_pismena') {
        return mb_strtoupper($correct, 'UTF-8') === $correct ? 'velke' : 'male';
    }
    if (in_array($correct, ['y', 'ý'], true)) return 'y';
    if (in_array($correct, ['i', 'í'], true)) return 'i';
    return $correct;
}
