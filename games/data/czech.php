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
    return [

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
    if ($category === 'shoda') return ['i', 'y', 'a'];
    return in_array($correct, ['í', 'ý'], true) ? ['í', 'ý'] : ['i', 'y'];
}
