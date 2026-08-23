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

/** @return array<string, array{label:string, icon:string, grades:array<int>, items:array}> */
function czechCategories(): array {
    return [

    // ══ VYJMENOVANÁ SLOVA — po jednotlivých písmenech (3.–5. třída) ══════

    'vyjm_b' => ['label' => 'Vyjmenovaná slova po B', 'icon' => '🅱️', 'grades' => [3,4,5,6,7,8,9], 'items' => [
        ['text' => 'b_t',          'correct' => 'ý', 'hint' => 'být (existovat) — vyjmenované slovo'],
        ['text' => 'b_dlet',       'correct' => 'y', 'hint' => 'bydlet — vyjmenované slovo'],
        ['text' => 'ob_vatel',     'correct' => 'y', 'hint' => 'obyvatel — příbuzné se slovem bydlet'],
        ['text' => 'b_t (dům)',    'correct' => 'y', 'hint' => 'byt — vyjmenované slovo'],
        ['text' => 'příb_tek',     'correct' => 'y', 'hint' => 'příbytek — příbuzné se slovem byt'],
        ['text' => 'náb_tek',      'correct' => 'y', 'hint' => 'nábytek — vyjmenované slovo'],
        ['text' => 'dob_tek',      'correct' => 'y', 'hint' => 'dobytek — vyjmenované slovo'],
        ['text' => 'ob_čej',       'correct' => 'y', 'hint' => 'obyčej — vyjmenované slovo'],
        ['text' => 'b_strý',       'correct' => 'y', 'hint' => 'bystrý — vyjmenované slovo'],
        ['text' => 'b_lina',       'correct' => 'y', 'hint' => 'bylina — vyjmenované slovo'],
        ['text' => 'kob_la',       'correct' => 'y', 'hint' => 'kobyla — vyjmenované slovo'],
        ['text' => 'b_k',          'correct' => 'ý', 'hint' => 'býk — vyjmenované slovo'],
        ['text' => 'Přib_slav',    'correct' => 'y', 'hint' => 'Přibyslav — vyjmenované slovo'],
        ['text' => 'b_lo jich pět','correct' => 'y', 'hint' => 'bylo — od slova být'],
        ['text' => 'zb_tek',       'correct' => 'y', 'hint' => 'zbytek — příbuzné se slovem být'],
        ['text' => 'b_valý',       'correct' => 'ý', 'hint' => 'bývalý — příbuzné se slovem být'],
    ]],

    'vyjm_l' => ['label' => 'Vyjmenovaná slova po L', 'icon' => '🇱', 'grades' => [3,4,5,6,7,8,9], 'items' => [
        ['text' => 'sl_šet',   'correct' => 'y', 'hint' => 'slyšet — vyjmenované slovo'],
        ['text' => 'ml_n',     'correct' => 'ý', 'hint' => 'mlýn — vyjmenované slovo'],
        ['text' => 'bl_skat se','correct' => 'ý', 'hint' => 'blýskat se — vyjmenované slovo'],
        ['text' => 'pol_kat',  'correct' => 'y', 'hint' => 'polykat — vyjmenované slovo'],
        ['text' => 'pl_nout',  'correct' => 'y', 'hint' => 'plynout — vyjmenované slovo'],
        ['text' => 'pl_tvat',  'correct' => 'ý', 'hint' => 'plýtvat — vyjmenované slovo'],
        ['text' => 'vzl_kat',  'correct' => 'y', 'hint' => 'vzlykat — vyjmenované slovo'],
        ['text' => 'l_sý',     'correct' => 'y', 'hint' => 'lysý (bez vlasů) — vyjmenované slovo'],
        ['text' => 'l_tko',    'correct' => 'ý', 'hint' => 'lýtko — vyjmenované slovo'],
        ['text' => 'l_ko',     'correct' => 'ý', 'hint' => 'lýko — vyjmenované slovo'],
        ['text' => 'l_že',     'correct' => 'y', 'hint' => 'lyže — vyjmenované slovo'],
        ['text' => 'pel_něk',  'correct' => 'y', 'hint' => 'pelyněk — vyjmenované slovo'],
        ['text' => 'pl_š',     'correct' => 'y', 'hint' => 'plyš — vyjmenované slovo'],
        ['text' => 'l_žař',    'correct' => 'y', 'hint' => 'lyžař — příbuzné se slovem lyže'],
        ['text' => 'spl_nout', 'correct' => 'y', 'hint' => 'splynout — příbuzné se slovem plynout'],
    ]],

    'vyjm_m' => ['label' => 'Vyjmenovaná slova po M', 'icon' => 'Ⓜ️', 'grades' => [3,4,5,6,7,8,9], 'items' => [
        ['text' => 'm_ (zájmeno)', 'correct' => 'y', 'hint' => 'my — vyjmenované slovo'],
        ['text' => 'm_t (ruce)',   'correct' => 'ý', 'hint' => 'mýt (umývat) — vyjmenované slovo'],
        ['text' => 'm_slet',       'correct' => 'y', 'hint' => 'myslet — vyjmenované slovo'],
        ['text' => 'm_lit se',     'correct' => 'ý', 'hint' => 'mýlit se — vyjmenované slovo'],
        ['text' => 'hm_z',         'correct' => 'y', 'hint' => 'hmyz — vyjmenované slovo'],
        ['text' => 'm_š',          'correct' => 'y', 'hint' => 'myš — vyjmenované slovo'],
        ['text' => 'hlem_žď',      'correct' => 'ý', 'hint' => 'hlemýžď — vyjmenované slovo'],
        ['text' => 'zam_kat',      'correct' => 'y', 'hint' => 'zamykat — vyjmenované slovo'],
        ['text' => 'sm_kat',       'correct' => 'ý', 'hint' => 'smýkat — vyjmenované slovo'],
        ['text' => 'chm_ří',       'correct' => 'ý', 'hint' => 'chmýří — vyjmenované slovo'],
        ['text' => 'm_to',         'correct' => 'ý', 'hint' => 'mýto (poplatek) — vyjmenované slovo'],
        ['text' => 'm_dlo',        'correct' => 'ý', 'hint' => 'mýdlo — příbuzné se slovem mýt'],
        ['text' => 'm_šlenka',     'correct' => 'y', 'hint' => 'myšlenka — příbuzné se slovem myslet'],
        ['text' => 'um_vadlo',     'correct' => 'y', 'hint' => 'umyvadlo — příbuzné se slovem mýt'],
    ]],

    'vyjm_p' => ['label' => 'Vyjmenovaná slova po P', 'icon' => '🅿️', 'grades' => [4,5,6,7,8,9], 'items' => [
        ['text' => 'p_cha',      'correct' => 'ý', 'hint' => 'pýcha — vyjmenované slovo'],
        ['text' => 'p_tel',      'correct' => 'y', 'hint' => 'pytel — vyjmenované slovo'],
        ['text' => 'netop_r',    'correct' => 'ý', 'hint' => 'netopýr — vyjmenované slovo'],
        ['text' => 'slep_š',     'correct' => 'ý', 'hint' => 'slepýš — vyjmenované slovo'],
        ['text' => 'p_l (květní)','correct' => 'y', 'hint' => 'pyl — vyjmenované slovo'],
        ['text' => 'kop_to',     'correct' => 'y', 'hint' => 'kopyto — vyjmenované slovo'],
        ['text' => 'klop_tat',   'correct' => 'ý', 'hint' => 'klopýtat — vyjmenované slovo'],
        ['text' => 'třp_tit se', 'correct' => 'y', 'hint' => 'třpytit se — vyjmenované slovo'],
        ['text' => 'p_kat',      'correct' => 'y', 'hint' => 'pykat (nést trest) — vyjmenované slovo'],
        ['text' => 'čep_řit se', 'correct' => 'ý', 'hint' => 'čepýřit se — vyjmenované slovo'],
        ['text' => 'p_šný',      'correct' => 'y', 'hint' => 'pyšný — příbuzné se slovem pýcha'],
        ['text' => 'p_tlák',     'correct' => 'y', 'hint' => 'pytlák — příbuzné se slovem pytel'],
    ]],

    'vyjm_s' => ['label' => 'Vyjmenovaná slova po S', 'icon' => '🇸', 'grades' => [4,5,6,7,8,9], 'items' => [
        ['text' => 's_n',      'correct' => 'y', 'hint' => 'syn — vyjmenované slovo'],
        ['text' => 's_tý',     'correct' => 'y', 'hint' => 'sytý (najedený) — vyjmenované slovo'],
        ['text' => 's_r',      'correct' => 'ý', 'hint' => 'sýr — vyjmenované slovo'],
        ['text' => 's_rový',   'correct' => 'y', 'hint' => 'syrový (tepelně neupravený) — vyjmenované slovo'],
        ['text' => 's_chravý', 'correct' => 'y', 'hint' => 'sychravý (chladno a vlhko) — vyjmenované slovo'],
        ['text' => 'us_chat',  'correct' => 'y', 'hint' => 'usychat — vyjmenované slovo'],
        ['text' => 's_kora',   'correct' => 'ý', 'hint' => 'sýkora — vyjmenované slovo'],
        ['text' => 's_ček',    'correct' => 'ý', 'hint' => 'sýček (druh sovy) — vyjmenované slovo'],
        ['text' => 's_sel',    'correct' => 'y', 'hint' => 'sysel — vyjmenované slovo'],
        ['text' => 's_čet',    'correct' => 'y', 'hint' => 'syčet — vyjmenované slovo'],
        ['text' => 's_pat',    'correct' => 'y', 'hint' => 'sypat — vyjmenované slovo'],
        ['text' => 's_neček',  'correct' => 'y', 'hint' => 'syneček — příbuzné se slovem syn'],
    ]],

    'vyjm_v' => ['label' => 'Vyjmenovaná slova po V', 'icon' => '🇻', 'grades' => [4,5,6,7,8,9], 'items' => [
        ['text' => 'v_ (zájmeno)', 'correct' => 'y', 'hint' => 'vy — vyjmenované slovo'],
        ['text' => 'v_soký',       'correct' => 'y', 'hint' => 'vysoký — vyjmenované slovo'],
        ['text' => 'v_t (o vlku)', 'correct' => 'ý', 'hint' => 'výt (vydávat zvuk) — vyjmenované slovo'],
        ['text' => 'v_skat',       'correct' => 'ý', 'hint' => 'výskat (radostně křičet) — vyjmenované slovo'],
        ['text' => 'zv_kat',       'correct' => 'y', 'hint' => 'zvykat si — vyjmenované slovo'],
        ['text' => 'žv_kat',       'correct' => 'ý', 'hint' => 'žvýkat — vyjmenované slovo'],
        ['text' => 'v_dra',        'correct' => 'y', 'hint' => 'vydra — vyjmenované slovo'],
        ['text' => 'v_r (pták)',   'correct' => 'ý', 'hint' => 'výr (sova) — vyjmenované slovo'],
        ['text' => 'v_žle',        'correct' => 'y', 'hint' => 'vyžle (hubený tvor) — vyjmenované slovo'],
        ['text' => 'pov_k',        'correct' => 'y', 'hint' => 'povyk (hluk) — vyjmenované slovo'],
        ['text' => 'cav_ky',       'correct' => 'y', 'hint' => 'cavyky (zbytečné okolky) — vyjmenované slovo'],
        ['text' => 'v_ška',        'correct' => 'ý', 'hint' => 'výška — příbuzné se slovem vysoký'],
    ]],

    // Po Z jsou jen čtyři vyjmenovaná slova, proto sadu doplňují
    // protipříklady — slova, kde se naopak píše měkké i.
    'vyjm_z' => ['label' => 'Vyjmenovaná slova po Z', 'icon' => '🇿', 'grades' => [4,5,6,7,8,9], 'items' => [
        ['text' => 'brz_',       'correct' => 'y', 'hint' => 'brzy — vyjmenované slovo'],
        ['text' => 'jaz_k',      'correct' => 'y', 'hint' => 'jazyk — vyjmenované slovo'],
        ['text' => 'naz_vat se', 'correct' => 'ý', 'hint' => 'nazývat se — vyjmenované slovo'],
        ['text' => 'Ruz_ně',     'correct' => 'y', 'hint' => 'Ruzyně (část Prahy) — vyjmenované slovo'],
        ['text' => 'jaz_kověda', 'correct' => 'y', 'hint' => 'jazykověda — příbuzné se slovem jazyk'],
        ['text' => 'jaz_ček',    'correct' => 'ý', 'hint' => 'jazýček — příbuzné se slovem jazyk'],
        ['text' => 'naz_val ho', 'correct' => 'ý', 'hint' => 'nazýval — příbuzné se slovem nazývat'],
        ['text' => 'z_ma',       'correct' => 'i', 'hint' => 'zima — NENÍ vyjmenované slovo, píše se měkké i'],
        ['text' => 'z_skat',     'correct' => 'i', 'hint' => 'získat — NENÍ vyjmenované slovo, píše se měkké i'],
        ['text' => 'z_tra',      'correct' => 'í', 'hint' => 'zítra — NENÍ vyjmenované slovo, píše se měkké í'],
    ]],

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
