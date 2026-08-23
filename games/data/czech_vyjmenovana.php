<?php
/**
 * Vyjmenovaná slova — rozsáhlá tréninková sada.
 *
 * Každé písmeno obsahuje:
 *   1) všechna vyjmenovaná slova podle školního seznamu,
 *   2) slova příbuzná (odvozená), kde se ypsilon drží,
 *   3) protipříklady s měkkým i — slova, která po stejné souhlásce
 *      vyjmenovaná nejsou. Bez nich by dítě prošlo mačkáním pořád y.
 *
 * Zadání s upřesněním v závorce nebo ve větě používáme tam, kde bez
 * kontextu nejde poznat, o které slovo jde (být/bít, mýt/mít, vír/výr…).
 */

/** Slova po B */
function czechWordsB(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 'b_t (existovat)', 'correct' => 'ý', 'hint' => 'být — vyjmenované slovo'],
        ['text' => 'b_dlet',          'correct' => 'y', 'hint' => 'bydlet — vyjmenované slovo'],
        ['text' => 'ob_vatel',        'correct' => 'y', 'hint' => 'obyvatel — vyjmenované slovo'],
        ['text' => 'b_t (byt v domě)','correct' => 'y', 'hint' => 'byt — vyjmenované slovo'],
        ['text' => 'příb_tek',        'correct' => 'y', 'hint' => 'příbytek — vyjmenované slovo'],
        ['text' => 'náb_tek',         'correct' => 'y', 'hint' => 'nábytek — vyjmenované slovo'],
        ['text' => 'dob_tek',         'correct' => 'y', 'hint' => 'dobytek — vyjmenované slovo'],
        ['text' => 'ob_čej',          'correct' => 'y', 'hint' => 'obyčej — vyjmenované slovo'],
        ['text' => 'b_strý',          'correct' => 'y', 'hint' => 'bystrý — vyjmenované slovo'],
        ['text' => 'b_lina',          'correct' => 'y', 'hint' => 'bylina — vyjmenované slovo'],
        ['text' => 'kob_la',          'correct' => 'y', 'hint' => 'kobyla — vyjmenované slovo'],
        ['text' => 'b_k',             'correct' => 'ý', 'hint' => 'býk — vyjmenované slovo'],
        ['text' => 'Přib_slav',       'correct' => 'y', 'hint' => 'Přibyslav — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 'b_lo jich pět',   'correct' => 'y', 'hint' => 'bylo — od slovesa být'],
        ['text' => 'b_li jsme tam',   'correct' => 'y', 'hint' => 'byli — od slovesa být'],
        ['text' => 'b_vat doma',      'correct' => 'ý', 'hint' => 'bývat — od slovesa být'],
        ['text' => 'b_valý',          'correct' => 'ý', 'hint' => 'bývalý — od slovesa být'],
        ['text' => 'zb_tek',          'correct' => 'y', 'hint' => 'zbytek — od slovesa být'],
        ['text' => 'zb_vat',          'correct' => 'ý', 'hint' => 'zbývat — od slovesa být'],
        ['text' => 'úb_tek',          'correct' => 'y', 'hint' => 'úbytek — od slovesa být'],
        ['text' => 'pob_t v lázních', 'correct' => 'y', 'hint' => 'pobyt — od slovesa být'],
        ['text' => 'nab_t vědomosti', 'correct' => 'ý', 'hint' => 'nabýt = získat, od být'],
        ['text' => 'ob_vací pokoj',   'correct' => 'ý', 'hint' => 'obývací — od slovesa bydlet'],
        ['text' => 'ob_dlí',          'correct' => 'y', 'hint' => 'obydlí — od slovesa bydlet'],
        ['text' => 'b_dliště',        'correct' => 'y', 'hint' => 'bydliště — od slovesa bydlet'],
        ['text' => 'b_tový dům',      'correct' => 'y', 'hint' => 'bytový — od slova byt'],
        ['text' => 'ob_čejný',        'correct' => 'y', 'hint' => 'obyčejný — od slova obyčej'],
        ['text' => 'b_střina',        'correct' => 'y', 'hint' => 'bystřina (dravý potok) — od slova bystrý'],
        ['text' => 'zb_střit pozornost','correct'=>'y', 'hint' => 'zbystřit — od slova bystrý'],
        ['text' => 'b_linkový čaj',   'correct' => 'y', 'hint' => 'bylinkový — od slova bylina'],
        ['text' => 'černob_l',        'correct' => 'ý', 'hint' => 'černobýl (rostlina) — od slova bylina'],
        ['text' => 'kob_lka',         'correct' => 'y', 'hint' => 'kobylka — od slova kobyla'],
        ['text' => 'b_ček',           'correct' => 'ý', 'hint' => 'býček — od slova býk'],
        ['text' => 'dob_tče',         'correct' => 'y', 'hint' => 'dobytče — od slova dobytek'],
        ['text' => 'B_střice',        'correct' => 'y', 'hint' => 'Bystřice — místní jméno od bystrý'],
        ['text' => 'Zb_něk',          'correct' => 'y', 'hint' => 'Zbyněk — jméno od slova být'],

        // ── protipříklady: po B se často píše měkké i ─────────────────
        ['text' => 'b_t (tlouct)',    'correct' => 'í', 'hint' => 'bít = tlouct — kdežto být = existovat'],
        ['text' => 'hodiny b_jí',     'correct' => 'i', 'hint' => 'bijí — od slovesa bít'],
        ['text' => 'b_dlo (tyč)',     'correct' => 'i', 'hint' => 'bidlo = tyč pro slepice — kdežto bydlo = bydlení'],
        ['text' => 'b_lý sníh',       'correct' => 'í', 'hint' => 'bílý — barva, není vyjmenované slovo'],
        ['text' => 'b_tva',           'correct' => 'i', 'hint' => 'bitva — není vyjmenované slovo'],
        ['text' => 'ob_lí na poli',   'correct' => 'i', 'hint' => 'obilí — není vyjmenované slovo'],
        ['text' => 'b_cykl',          'correct' => 'i', 'hint' => 'bicykl — není vyjmenované slovo'],
        ['text' => 'kab_na',          'correct' => 'i', 'hint' => 'kabina — není vyjmenované slovo'],
        ['text' => 'sb_rka známek',   'correct' => 'í', 'hint' => 'sbírka — není vyjmenované slovo'],
        ['text' => 'b_ograf',         'correct' => 'i', 'hint' => 'biograf (staré kino) — není vyjmenované slovo'],
        ['text' => 'b_olog',          'correct' => 'i', 'hint' => 'biolog — není vyjmenované slovo'],
        ['text' => 'b_ftek',          'correct' => 'i', 'hint' => 'biftek — není vyjmenované slovo'],
        ['text' => 'b_ble',           'correct' => 'i', 'hint' => 'bible — není vyjmenované slovo'],
        ['text' => 'ab_turient',      'correct' => 'i', 'hint' => 'abiturient — není vyjmenované slovo'],
        ['text' => 'amb_ce',          'correct' => 'i', 'hint' => 'ambice — není vyjmenované slovo'],
        ['text' => 'b_dýlko pro ptáka','correct' => 'i', 'hint' => 'bidýlko — od slova bidlo (tyč)'],
        ['text' => 'nab_dka',         'correct' => 'í', 'hint' => 'nabídka — od slovesa nabídnout, ne od nabýt'],
        ['text' => 'b_lance',         'correct' => 'i', 'hint' => 'bilance — není vyjmenované slovo'],
    ];
}

/** Slova po L */
function czechWordsL(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 'sl_šet',      'correct' => 'y', 'hint' => 'slyšet — vyjmenované slovo'],
        ['text' => 'ml_n',        'correct' => 'ý', 'hint' => 'mlýn — vyjmenované slovo'],
        ['text' => 'bl_skat se',  'correct' => 'ý', 'hint' => 'blýskat se — vyjmenované slovo'],
        ['text' => 'pol_kat',     'correct' => 'y', 'hint' => 'polykat — vyjmenované slovo'],
        ['text' => 'pl_nout',     'correct' => 'y', 'hint' => 'plynout — vyjmenované slovo'],
        ['text' => 'pl_tvat',     'correct' => 'ý', 'hint' => 'plýtvat — vyjmenované slovo'],
        ['text' => 'vzl_kat',     'correct' => 'y', 'hint' => 'vzlykat — vyjmenované slovo'],
        ['text' => 'l_sý (bez vlasů)','correct'=>'y', 'hint' => 'lysý — vyjmenované slovo'],
        ['text' => 'l_tko',       'correct' => 'ý', 'hint' => 'lýtko — vyjmenované slovo'],
        ['text' => 'l_ko',        'correct' => 'ý', 'hint' => 'lýko — vyjmenované slovo'],
        ['text' => 'l_že',        'correct' => 'y', 'hint' => 'lyže — vyjmenované slovo'],
        ['text' => 'pel_něk',     'correct' => 'y', 'hint' => 'pelyněk (hořká bylina) — vyjmenované slovo'],
        ['text' => 'pl_š',        'correct' => 'y', 'hint' => 'plyš — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 'sl_šitelný',  'correct' => 'y', 'hint' => 'slyšitelný — od slovesa slyšet'],
        ['text' => 'sl_chat',     'correct' => 'ý', 'hint' => 'slýchat — od slovesa slyšet'],
        ['text' => 'nedosl_chavý','correct' => 'ý', 'hint' => 'nedoslýchavý — od slovesa slyšet'],
        ['text' => 'ml_nek',      'correct' => 'ý', 'hint' => 'mlýnek — od slova mlýn'],
        ['text' => 'ml_nář',      'correct' => 'y', 'hint' => 'mlynář — od slova mlýn'],
        ['text' => 'zabl_sknout se','correct'=>'ý', 'hint' => 'zablýsknout se — od blýskat se'],
        ['text' => 'bl_skavý',    'correct' => 'ý', 'hint' => 'blýskavý — od blýskat se'],
        ['text' => 'spol_knout',  'correct' => 'y', 'hint' => 'spolknout — od slovesa polykat'],
        ['text' => 'pl_n',        'correct' => 'y', 'hint' => 'plyn — od slovesa plynout'],
        ['text' => 'pl_nulý',     'correct' => 'y', 'hint' => 'plynulý — od slovesa plynout'],
        ['text' => 'pl_novod',    'correct' => 'y', 'hint' => 'plynovod — od slova plyn'],
        ['text' => 'upl_nout',    'correct' => 'y', 'hint' => 'uplynout — od slovesa plynout'],
        ['text' => 'spl_nout',    'correct' => 'y', 'hint' => 'splynout — od slovesa plynout'],
        ['text' => 'vypl_nout',   'correct' => 'y', 'hint' => 'vyplynout — od slovesa plynout'],
        ['text' => 'pl_tvání',    'correct' => 'ý', 'hint' => 'plýtvání — od slovesa plýtvat'],
        ['text' => 'vzl_k',       'correct' => 'y', 'hint' => 'vzlyk — od slovesa vzlykat'],
        ['text' => 'l_sina',      'correct' => 'y', 'hint' => 'lysina — od slova lysý'],
        ['text' => 'l_žovat',     'correct' => 'y', 'hint' => 'lyžovat — od slova lyže'],
        ['text' => 'l_žař',       'correct' => 'y', 'hint' => 'lyžař — od slova lyže'],
        ['text' => 'l_kový provaz','correct' => 'ý', 'hint' => 'lýkový — od slova lýko'],
        ['text' => 'pl_šový medvěd','correct'=> 'y', 'hint' => 'plyšový — od slova plyš'],
        ['text' => 'L_sá nad Labem','correct'=> 'y', 'hint' => 'Lysá — místní jméno od slova lysý'],

        // ── protipříklady: po L je měkké i velmi časté ────────────────
        ['text' => 'l_dé',        'correct' => 'i', 'hint' => 'lidé — není vyjmenované slovo'],
        ['text' => 'l_ška',       'correct' => 'i', 'hint' => 'liška — není vyjmenované slovo'],
        ['text' => 'l_pa',        'correct' => 'í', 'hint' => 'lípa (strom) — není vyjmenované slovo'],
        ['text' => 'l_ný',        'correct' => 'í', 'hint' => 'líný — není vyjmenované slovo'],
        ['text' => 'kl_d',        'correct' => 'i', 'hint' => 'klid — není vyjmenované slovo'],
        ['text' => 'bl_zko',      'correct' => 'í', 'hint' => 'blízko — pozor na blýskat se'],
        ['text' => 'pl_vat',      'correct' => 'i', 'hint' => 'plivat — pozor na plynout'],
        ['text' => 'sl_na',       'correct' => 'i', 'hint' => 'slina — pozor na slyšet'],
        ['text' => 'l_st papíru', 'correct' => 'i', 'hint' => 'list — není vyjmenované slovo'],
        ['text' => 'l_bat',       'correct' => 'í', 'hint' => 'líbat — není vyjmenované slovo'],
        ['text' => 'l_tost',      'correct' => 'í', 'hint' => 'lítost — není vyjmenované slovo'],
        ['text' => 'l_monáda',    'correct' => 'i', 'hint' => 'limonáda — není vyjmenované slovo'],
        ['text' => 'l_nka',       'correct' => 'i', 'hint' => 'linka — není vyjmenované slovo'],
        ['text' => 'pl_ce',       'correct' => 'í', 'hint' => 'plíce — není vyjmenované slovo'],
        ['text' => 'kl_ka u dveří','correct' => 'i', 'hint' => 'klika — není vyjmenované slovo'],
        ['text' => 'l_lie',       'correct' => 'i', 'hint' => 'lilie (květina) — není vyjmenované slovo'],
        ['text' => 'l_šta',       'correct' => 'i', 'hint' => 'lišta — není vyjmenované slovo'],
        ['text' => 'l_tovat',     'correct' => 'i', 'hint' => 'litovat — není vyjmenované slovo'],
    ];
}

/** Slova po M */
function czechWordsM(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 'm_ (zájmeno)',  'correct' => 'y', 'hint' => 'my — vyjmenované slovo'],
        ['text' => 'm_t (umývat)',  'correct' => 'ý', 'hint' => 'mýt — vyjmenované slovo'],
        ['text' => 'm_slet',        'correct' => 'y', 'hint' => 'myslet — vyjmenované slovo'],
        ['text' => 'm_lit se',      'correct' => 'ý', 'hint' => 'mýlit se — vyjmenované slovo'],
        ['text' => 'hm_z',          'correct' => 'y', 'hint' => 'hmyz — vyjmenované slovo'],
        ['text' => 'm_š',           'correct' => 'y', 'hint' => 'myš — vyjmenované slovo'],
        ['text' => 'hlem_žď',       'correct' => 'ý', 'hint' => 'hlemýžď — vyjmenované slovo'],
        ['text' => 'm_tit les',     'correct' => 'ý', 'hint' => 'mýtit (kácet) — vyjmenované slovo'],
        ['text' => 'zam_kat',       'correct' => 'y', 'hint' => 'zamykat — vyjmenované slovo'],
        ['text' => 'sm_kat',        'correct' => 'ý', 'hint' => 'smýkat (vláčet) — vyjmenované slovo'],
        ['text' => 'dm_chat',       'correct' => 'ý', 'hint' => 'dmýchat (foukat) — vyjmenované slovo'],
        ['text' => 'chm_ří',        'correct' => 'ý', 'hint' => 'chmýří — vyjmenované slovo'],
        ['text' => 'nachom_tnout se','correct'=> 'ý', 'hint' => 'nachomýtnout se — vyjmenované slovo'],
        ['text' => 'm_to (poplatek)','correct'=> 'ý', 'hint' => 'mýto — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 'm_dlo',         'correct' => 'ý', 'hint' => 'mýdlo — od slovesa mýt'],
        ['text' => 'um_vadlo',      'correct' => 'y', 'hint' => 'umyvadlo — od slovesa mýt'],
        ['text' => 'um_t nádobí',   'correct' => 'ý', 'hint' => 'umýt — od slovesa mýt'],
        ['text' => 'm_čka nádobí',  'correct' => 'y', 'hint' => 'myčka — od slovesa mýt'],
        ['text' => 'pom_je',        'correct' => 'y', 'hint' => 'pomyje — od slovesa mýt'],
        ['text' => 'm_šlenka',      'correct' => 'y', 'hint' => 'myšlenka — od slovesa myslet'],
        ['text' => 'm_slivec',      'correct' => 'y', 'hint' => 'myslivec — od slovesa myslet'],
        ['text' => 'přem_šlet',     'correct' => 'ý', 'hint' => 'přemýšlet — od slovesa myslet'],
        ['text' => 'sm_sl',         'correct' => 'y', 'hint' => 'smysl — od slovesa myslet'],
        ['text' => 'nesm_sl',       'correct' => 'y', 'hint' => 'nesmysl — od slovesa myslet'],
        ['text' => 'prům_sl',       'correct' => 'y', 'hint' => 'průmysl — od slovesa myslet'],
        ['text' => 'úm_sl',         'correct' => 'y', 'hint' => 'úmysl — od slovesa myslet'],
        ['text' => 'om_l',          'correct' => 'y', 'hint' => 'omyl — od slovesa mýlit se'],
        ['text' => 'm_lka',         'correct' => 'ý', 'hint' => 'mýlka — od slovesa mýlit se'],
        ['text' => 'hm_zí bodnutí', 'correct' => 'y', 'hint' => 'hmyzí — od slova hmyz'],
        ['text' => 'm_ška',         'correct' => 'y', 'hint' => 'myška — od slova myš'],
        ['text' => 'm_tina v lese', 'correct' => 'ý', 'hint' => 'mýtina — od slovesa mýtit'],
        ['text' => 'vym_tit',       'correct' => 'ý', 'hint' => 'vymýtit — od slovesa mýtit'],
        ['text' => 'odem_kat',      'correct' => 'y', 'hint' => 'odemykat — od slovesa zamykat'],
        ['text' => 'sm_k na silnici','correct'=> 'y', 'hint' => 'smyk — od slovesa smýkat'],
        ['text' => 'sm_čka',        'correct' => 'y', 'hint' => 'smyčka — od slovesa smýkat'],
        ['text' => 'sm_čec',        'correct' => 'y', 'hint' => 'smyčec — od slovesa smýkat'],
        ['text' => 'rozdm_chat',    'correct' => 'ý', 'hint' => 'rozdmýchat — od slovesa dmýchat'],
        ['text' => 'Litom_šl',      'correct' => 'y', 'hint' => 'Litomyšl — místní jméno'],
        ['text' => 'Přem_sl',       'correct' => 'y', 'hint' => 'Přemysl — jméno od slovesa myslet'],

        // ── protipříklady: po M rozhoduje význam ──────────────────────
        ['text' => 'm_t (vlastnit)','correct' => 'í', 'hint' => 'mít = vlastnit — kdežto mýt = umývat'],
        ['text' => 'dej m_ to',     'correct' => 'i', 'hint' => 'mi = tvar zájmena já — kdežto my je zájmeno'],
        ['text' => 'm_ska (nádoba)','correct' => 'i', 'hint' => 'miska — kdežto myška je zvíře'],
        ['text' => 'm_lý',          'correct' => 'i', 'hint' => 'milý — není vyjmenované slovo'],
        ['text' => 'm_nuta',        'correct' => 'i', 'hint' => 'minuta — není vyjmenované slovo'],
        ['text' => 'm_mo',          'correct' => 'i', 'hint' => 'mimo — není vyjmenované slovo'],
        ['text' => 'm_č na fotbal', 'correct' => 'í', 'hint' => 'míč — není vyjmenované slovo'],
        ['text' => 'm_r a klid',    'correct' => 'í', 'hint' => 'mír — není vyjmenované slovo'],
        ['text' => 'm_nce',         'correct' => 'i', 'hint' => 'mince — není vyjmenované slovo'],
        ['text' => 'm_sto (město)', 'correct' => 'í', 'hint' => 'místo — není vyjmenované slovo'],
        ['text' => 'm_str světa',   'correct' => 'i', 'hint' => 'mistr — není vyjmenované slovo'],
        ['text' => 'm_nulý týden',  'correct' => 'i', 'hint' => 'minulý — není vyjmenované slovo'],
        ['text' => 'm_lovat',       'correct' => 'i', 'hint' => 'milovat — není vyjmenované slovo'],
        ['text' => 'm_řit na terč', 'correct' => 'í', 'hint' => 'mířit — není vyjmenované slovo'],
        ['text' => 'm_sa s ovocem', 'correct' => 'í', 'hint' => 'mísa — není vyjmenované slovo'],
        ['text' => 'm_le daleko',   'correct' => 'í', 'hint' => 'míle (jednotka) — není vyjmenované slovo'],
        ['text' => 'm_kroskop',     'correct' => 'i', 'hint' => 'mikroskop — není vyjmenované slovo'],
        ['text' => 'm_liliony',     'correct' => 'i', 'hint' => 'miliony — není vyjmenované slovo'],
    ];
}

/** Slova po P */
function czechWordsP(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 'p_cha',        'correct' => 'ý', 'hint' => 'pýcha — vyjmenované slovo'],
        ['text' => 'p_tel',        'correct' => 'y', 'hint' => 'pytel — vyjmenované slovo'],
        ['text' => 'p_sk (u úst)', 'correct' => 'y', 'hint' => 'pysk — vyjmenované slovo'],
        ['text' => 'netop_r',      'correct' => 'ý', 'hint' => 'netopýr — vyjmenované slovo'],
        ['text' => 'slep_š',       'correct' => 'ý', 'hint' => 'slepýš (beznohý ještěr) — vyjmenované slovo'],
        ['text' => 'p_l z květů',  'correct' => 'y', 'hint' => 'pyl — vyjmenované slovo'],
        ['text' => 'kop_to',       'correct' => 'y', 'hint' => 'kopyto — vyjmenované slovo'],
        ['text' => 'klop_tat',     'correct' => 'ý', 'hint' => 'klopýtat — vyjmenované slovo'],
        ['text' => 'třp_tit se',   'correct' => 'y', 'hint' => 'třpytit se — vyjmenované slovo'],
        ['text' => 'zp_tovat',     'correct' => 'y', 'hint' => 'zpytovat (zkoumat) — vyjmenované slovo'],
        ['text' => 'p_kat za chybu','correct'=> 'y', 'hint' => 'pykat = nést trest — vyjmenované slovo'],
        ['text' => 'p_r (plevel)', 'correct' => 'ý', 'hint' => 'pýr — vyjmenované slovo'],
        ['text' => 'p_řit se',     'correct' => 'ý', 'hint' => 'pýřit se (červenat se) — vyjmenované slovo'],
        ['text' => 'čep_řit se',   'correct' => 'ý', 'hint' => 'čepýřit se — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 'p_šný',        'correct' => 'y', 'hint' => 'pyšný — od slova pýcha'],
        ['text' => 'p_šnit se',    'correct' => 'y', 'hint' => 'pyšnit se — od slova pýcha'],
        ['text' => 'zp_chnout',    'correct' => 'y', 'hint' => 'zpychnout — od slova pýcha'],
        ['text' => 'p_tlík',       'correct' => 'y', 'hint' => 'pytlík — od slova pytel'],
        ['text' => 'p_tlák',       'correct' => 'y', 'hint' => 'pytlák — od slova pytel'],
        ['text' => 'p_tlovina',    'correct' => 'y', 'hint' => 'pytlovina — od slova pytel'],
        ['text' => 'ptakop_sk',    'correct' => 'y', 'hint' => 'ptakopysk — od slova pysk'],
        ['text' => 'netop_ří',     'correct' => 'ý', 'hint' => 'netopýří — od slova netopýr'],
        ['text' => 'p_lový prach', 'correct' => 'y', 'hint' => 'pylový — od slova pyl'],
        ['text' => 'op_lovat',     'correct' => 'y', 'hint' => 'opylovat — od slova pyl'],
        ['text' => 'kop_tník',     'correct' => 'y', 'hint' => 'kopytník — od slova kopyto'],
        ['text' => 'klop_tnout',   'correct' => 'ý', 'hint' => 'klopýtnout — od slovesa klopýtat'],
        ['text' => 'třp_t hvězd',  'correct' => 'y', 'hint' => 'třpyt — od slovesa třpytit se'],
        ['text' => 'třp_tivý',     'correct' => 'y', 'hint' => 'třpytivý — od slovesa třpytit se'],
        ['text' => 'třp_tka',      'correct' => 'y', 'hint' => 'třpytka — od slovesa třpytit se'],
        ['text' => 'odp_kat trest','correct' => 'y', 'hint' => 'odpykat — od slovesa pykat'],
        ['text' => 'jazykozp_t',   'correct' => 'y', 'hint' => 'jazykozpyt — od slovesa zpytovat'],
        ['text' => 'zap_řit se',   'correct' => 'ý', 'hint' => 'zapýřit se — od slovesa pýřit se'],
        ['text' => 'Sp_tihněv',    'correct' => 'y', 'hint' => 'Spytihněv — jméno od slovesa zpytovat'],
        ['text' => 'Chrop_ně',     'correct' => 'y', 'hint' => 'Chropyně — místní jméno'],

        // ── protipříklady ─────────────────────────────────────────────
        ['text' => 'p_l vodu',     'correct' => 'i', 'hint' => 'pil — od slovesa pít; kdežto pyl je z květů'],
        ['text' => 'p_seň',        'correct' => 'í', 'hint' => 'píseň — není vyjmenované slovo'],
        ['text' => 'p_lný žák',    'correct' => 'i', 'hint' => 'pilný — není vyjmenované slovo'],
        ['text' => 'p_chat',       'correct' => 'í', 'hint' => 'píchat — pozor na pýcha'],
        ['text' => 'p_vo',         'correct' => 'i', 'hint' => 'pivo — není vyjmenované slovo'],
        ['text' => 'p_lot',        'correct' => 'i', 'hint' => 'pilot — není vyjmenované slovo'],
        ['text' => 'p_skat',       'correct' => 'í', 'hint' => 'pískat — pozor na pysk'],
        ['text' => 'p_smeno',      'correct' => 'í', 'hint' => 'písmeno — není vyjmenované slovo'],
        ['text' => 'p_la na dřevo','correct' => 'i', 'hint' => 'pila — není vyjmenované slovo'],
        ['text' => 'p_tomý',       'correct' => 'i', 'hint' => 'pitomý — není vyjmenované slovo'],
        ['text' => 'p_le a snaha', 'correct' => 'í', 'hint' => 'píle — není vyjmenované slovo'],
        ['text' => 'p_lulka',      'correct' => 'i', 'hint' => 'pilulka — není vyjmenované slovo'],
        ['text' => 'p_sek na hřišti','correct'=> 'í', 'hint' => 'písek — není vyjmenované slovo'],
        ['text' => 'p_zza',        'correct' => 'i', 'hint' => 'pizza — není vyjmenované slovo'],
        ['text' => 'p_nzeta',      'correct' => 'i', 'hint' => 'pinzeta — není vyjmenované slovo'],
    ];
}

/** Slova po S */
function czechWordsS(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 's_n',          'correct' => 'y', 'hint' => 'syn — vyjmenované slovo'],
        ['text' => 's_tý (najedený)','correct'=>'y', 'hint' => 'sytý — vyjmenované slovo'],
        ['text' => 's_r (jídlo)',  'correct' => 'ý', 'hint' => 'sýr — vyjmenované slovo'],
        ['text' => 's_rový',       'correct' => 'y', 'hint' => 'syrový (tepelně neupravený) — vyjmenované slovo'],
        ['text' => 's_chravý',     'correct' => 'y', 'hint' => 'sychravý (chladno a vlhko) — vyjmenované slovo'],
        ['text' => 'us_chat',      'correct' => 'y', 'hint' => 'usychat — vyjmenované slovo'],
        ['text' => 's_kora',       'correct' => 'ý', 'hint' => 'sýkora — vyjmenované slovo'],
        ['text' => 's_ček (sova)', 'correct' => 'ý', 'hint' => 'sýček — vyjmenované slovo'],
        ['text' => 's_sel',        'correct' => 'y', 'hint' => 'sysel — vyjmenované slovo'],
        ['text' => 's_čet',        'correct' => 'y', 'hint' => 'syčet — vyjmenované slovo'],
        ['text' => 's_pat',        'correct' => 'y', 'hint' => 'sypat — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 's_neček',      'correct' => 'y', 'hint' => 'syneček — od slova syn'],
        ['text' => 's_novec',      'correct' => 'y', 'hint' => 'synovec — od slova syn'],
        ['text' => 'nas_tit se',   'correct' => 'y', 'hint' => 'nasytit se — od slova sytý'],
        ['text' => 'nenas_tný',    'correct' => 'y', 'hint' => 'nenasytný — od slova sytý'],
        ['text' => 'dos_ta',       'correct' => 'y', 'hint' => 'dosyta — od slova sytý'],
        ['text' => 's_rárna',      'correct' => 'ý', 'hint' => 'sýrárna — od slova sýr'],
        ['text' => 's_reček',      'correct' => 'y', 'hint' => 'syreček — od slova sýr'],
        ['text' => 's_rovátka',    'correct' => 'y', 'hint' => 'syrovátka — od slova syrový'],
        ['text' => 'vys_chat',     'correct' => 'y', 'hint' => 'vysychat — od slovesa usychat'],
        ['text' => 'zas_chat',     'correct' => 'y', 'hint' => 'zasychat — od slovesa usychat'],
        ['text' => 's_korka',      'correct' => 'ý', 'hint' => 'sýkorka — od slova sýkora'],
        ['text' => 's_slí nora',   'correct' => 'y', 'hint' => 'syslí — od slova sysel'],
        ['text' => 's_kot',        'correct' => 'y', 'hint' => 'sykot — od slovesa syčet'],
        ['text' => 'zas_čet',      'correct' => 'y', 'hint' => 'zasyčet — od slovesa syčet'],
        ['text' => 's_pký písek',  'correct' => 'y', 'hint' => 'sypký — od slovesa sypat'],
        ['text' => 's_pka na obilí','correct'=> 'ý', 'hint' => 'sýpka — od slovesa sypat'],
        ['text' => 'nas_pat',      'correct' => 'y', 'hint' => 'nasypat — od slovesa sypat'],
        ['text' => 'pos_pat solí', 'correct' => 'y', 'hint' => 'posypat — od slovesa sypat'],

        // ── protipříklady ─────────────────────────────────────────────
        ['text' => 's_lný',        'correct' => 'i', 'hint' => 'silný — pozor na sytý'],
        ['text' => 's_rup',        'correct' => 'i', 'hint' => 'sirup — pozor na sypat'],
        ['text' => 's_tko',        'correct' => 'í', 'hint' => 'sítko — pozor na sýkora'],
        ['text' => 's_ra (prvek)', 'correct' => 'í', 'hint' => 'síra — kdežto sýr je jídlo'],
        ['text' => 's_dlo firmy',  'correct' => 'í', 'hint' => 'sídlo — není vyjmenované slovo'],
        ['text' => 's_rka',        'correct' => 'i', 'hint' => 'sirka — není vyjmenované slovo'],
        ['text' => 's_ť',          'correct' => 'í', 'hint' => 'síť — není vyjmenované slovo'],
        ['text' => 's_la (moc)',   'correct' => 'í', 'hint' => 'síla — pozor na sýkora'],
        ['text' => 's_lnice',      'correct' => 'i', 'hint' => 'silnice — není vyjmenované slovo'],
        ['text' => 's_rotek',      'correct' => 'i', 'hint' => 'sirotek — není vyjmenované slovo'],
        ['text' => 's_gnál',       'correct' => 'i', 'hint' => 'signál — není vyjmenované slovo'],
        ['text' => 's_tuace',      'correct' => 'i', 'hint' => 'situace — není vyjmenované slovo'],
        ['text' => 's_ndrom',      'correct' => 'y', 'hint' => 'syndrom — cizí slovo, ale píše se s y'],
        ['text' => 's_stém',       'correct' => 'y', 'hint' => 'systém — cizí slovo, ale píše se s y'],
    ];
}

/** Slova po V */
function czechWordsV(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 'v_ (zájmeno)', 'correct' => 'y', 'hint' => 'vy — vyjmenované slovo'],
        ['text' => 'v_soký',       'correct' => 'y', 'hint' => 'vysoký — vyjmenované slovo'],
        ['text' => 'v_t (o vlku)', 'correct' => 'ý', 'hint' => 'výt = vydávat zvuk — vyjmenované slovo'],
        ['text' => 'v_skat radostí','correct'=> 'ý', 'hint' => 'výskat — vyjmenované slovo'],
        ['text' => 'zv_kat si',    'correct' => 'y', 'hint' => 'zvykat si — vyjmenované slovo'],
        ['text' => 'žv_kat',       'correct' => 'ý', 'hint' => 'žvýkat — vyjmenované slovo'],
        ['text' => 'v_dra',        'correct' => 'y', 'hint' => 'vydra — vyjmenované slovo'],
        ['text' => 'v_r (sova)',   'correct' => 'ý', 'hint' => 'výr — vyjmenované slovo'],
        ['text' => 'v_žle',        'correct' => 'y', 'hint' => 'vyžle (hubený tvor) — vyjmenované slovo'],
        ['text' => 'pov_k',        'correct' => 'y', 'hint' => 'povyk (hluk) — vyjmenované slovo'],
        ['text' => 'v_heň',        'correct' => 'ý', 'hint' => 'výheň (kovářská pec) — vyjmenované slovo'],
        ['text' => 'cav_ky',       'correct' => 'y', 'hint' => 'cavyky (zbytečné okolky) — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 'v_kat starším','correct' => 'y', 'hint' => 'vykat — od zájmena vy'],
        ['text' => 'v_soká hora',  'correct' => 'y', 'hint' => 'vysoká — od slova vysoký'],
        ['text' => 'v_ška',        'correct' => 'ý', 'hint' => 'výška — od slova vysoký'],
        ['text' => 'v_šina',       'correct' => 'ý', 'hint' => 'výšina — od slova vysoký'],
        ['text' => 'V_sočina',     'correct' => 'y', 'hint' => 'Vysočina — od slova vysoký'],
        ['text' => 'v_tí vlků',    'correct' => 'y', 'hint' => 'vytí — od slovesa výt'],
        ['text' => 'v_skot',       'correct' => 'ý', 'hint' => 'výskot — od slovesa výskat'],
        ['text' => 'zv_k',         'correct' => 'y', 'hint' => 'zvyk — od slovesa zvykat'],
        ['text' => 'náv_k',        'correct' => 'y', 'hint' => 'návyk — od slovesa zvykat'],
        ['text' => 'obv_klý',      'correct' => 'y', 'hint' => 'obvyklý — od slovesa zvykat'],
        ['text' => 'odv_kat',      'correct' => 'y', 'hint' => 'odvykat — od slovesa zvykat'],
        ['text' => 'žv_kačka',     'correct' => 'ý', 'hint' => 'žvýkačka — od slovesa žvýkat'],
        ['text' => 'přežv_kovat',  'correct' => 'y', 'hint' => 'přežvykovat — od slovesa žvýkat'],
        ['text' => 'v_dří kožich', 'correct' => 'y', 'hint' => 'vydří — od slova vydra'],
        ['text' => 'pov_kovat',    'correct' => 'y', 'hint' => 'povykovat — od slova povyk'],
        ['text' => 'V_škov',       'correct' => 'y', 'hint' => 'Vyškov — místní jméno'],

        // ── protipříklady ─────────────────────────────────────────────
        ['text' => 'v_dět',        'correct' => 'i', 'hint' => 'vidět — pozor na vydra'],
        ['text' => 'v_tr',         'correct' => 'í', 'hint' => 'vítr — není vyjmenované slovo'],
        ['text' => 'v_la (pohádková)','correct'=>'í', 'hint' => 'víla — není vyjmenované slovo'],
        ['text' => 'v_la (dům)',   'correct' => 'i', 'hint' => 'vila = dům — kdežto víla je pohádková bytost'],
        ['text' => 'v_r ve vodě',  'correct' => 'í', 'hint' => 'vír = točící se voda — kdežto výr je sova'],
        ['text' => 'had se v_ne',  'correct' => 'i', 'hint' => 'vine se — od slovesa vinout'],
        ['text' => 'v_tat hosty',  'correct' => 'í', 'hint' => 'vítat — není vyjmenované slovo'],
        ['text' => 'v_dle',        'correct' => 'i', 'hint' => 'vidle — není vyjmenované slovo'],
        ['text' => 'v_no',         'correct' => 'í', 'hint' => 'víno — není vyjmenované slovo'],
        ['text' => 'v_těz',        'correct' => 'í', 'hint' => 'vítěz — není vyjmenované slovo'],
        ['text' => 'v_set na hřebíku','correct'=>'i', 'hint' => 'viset — není vyjmenované slovo'],
        ['text' => 'v_dlička',     'correct' => 'i', 'hint' => 'vidlička — není vyjmenované slovo'],
        ['text' => 'v_na (provinění)','correct'=>'i', 'hint' => 'vina — není vyjmenované slovo'],
        ['text' => 'v_chr',        'correct' => 'i', 'hint' => 'vichr — není vyjmenované slovo'],
        ['text' => 'v_deo',        'correct' => 'i', 'hint' => 'video — není vyjmenované slovo'],
    ];
}

/** Slova po Z */
function czechWordsZ(): array {
    return [
        // ── vyjmenovaná slova ──────────────────────────────────────────
        ['text' => 'brz_',         'correct' => 'y', 'hint' => 'brzy — vyjmenované slovo'],
        ['text' => 'jaz_k',        'correct' => 'y', 'hint' => 'jazyk — vyjmenované slovo'],
        ['text' => 'naz_vat se',   'correct' => 'ý', 'hint' => 'nazývat se — vyjmenované slovo'],
        ['text' => 'Ruz_ně',       'correct' => 'y', 'hint' => 'Ruzyně (část Prahy) — vyjmenované slovo'],

        // ── příbuzná slova ────────────────────────────────────────────
        ['text' => 'jaz_ček',      'correct' => 'ý', 'hint' => 'jazýček — od slova jazyk'],
        ['text' => 'jaz_kový',     'correct' => 'y', 'hint' => 'jazykový — od slova jazyk'],
        ['text' => 'jaz_kověda',   'correct' => 'y', 'hint' => 'jazykověda — od slova jazyk'],
        ['text' => 'dvojjaz_čný',  'correct' => 'y', 'hint' => 'dvojjazyčný — od slova jazyk'],
        ['text' => 'naz_val ho',   'correct' => 'ý', 'hint' => 'nazýval — od slovesa nazývat'],
        ['text' => 'oz_vat se',    'correct' => 'ý', 'hint' => 'ozývat se — příbuzné s nazývat'],
        ['text' => 'vz_vat',       'correct' => 'ý', 'hint' => 'vzývat — příbuzné s nazývat'],
        ['text' => 'ruz_ňské letiště','correct'=>'y', 'hint' => 'ruzyňské — od slova Ruzyně'],

        // ── protipříklady ─────────────────────────────────────────────
        ['text' => 'z_ma',         'correct' => 'i', 'hint' => 'zima — není vyjmenované slovo'],
        ['text' => 'z_skat',       'correct' => 'i', 'hint' => 'získat — není vyjmenované slovo'],
        ['text' => 'z_tra',        'correct' => 'í', 'hint' => 'zítra — není vyjmenované slovo'],
        ['text' => 'z_sk',         'correct' => 'i', 'hint' => 'zisk — není vyjmenované slovo'],
        ['text' => 'z_val nudou',  'correct' => 'í', 'hint' => 'zíval — není vyjmenované slovo'],
        ['text' => 'z_ral na mě',  'correct' => 'í', 'hint' => 'zíral — není vyjmenované slovo'],
        ['text' => 'z_mní bunda',  'correct' => 'i', 'hint' => 'zimní — od slova zima'],
        ['text' => 'z_p u bundy',  'correct' => 'i', 'hint' => 'zip — není vyjmenované slovo'],
    ];
}

/**
 * Předpona vy-/vý- — po ní se vždy píše ypsilon.
 * Klíčové pravidlo pro 4.–5. třídu; protipříklady jsou slova,
 * kde vi- není předpona, ale součást kořene.
 */
function czechWordsPredponaVy(): array {
    return [
        ['text' => 'v_letět z hnízda', 'correct' => 'y', 'hint' => 'vyletět — předpona vy- se píše s y'],
        ['text' => 'v_let do lesa',    'correct' => 'ý', 'hint' => 'výlet — předpona vý- se píše s ý'],
        ['text' => 'v_světlit',        'correct' => 'y', 'hint' => 'vysvětlit — předpona vy-'],
        ['text' => 'v_brat si',        'correct' => 'y', 'hint' => 'vybrat — předpona vy-'],
        ['text' => 'v_běr ze dvou',    'correct' => 'ý', 'hint' => 'výběr — předpona vý-'],
        ['text' => 'v_hrát zápas',     'correct' => 'y', 'hint' => 'vyhrát — předpona vy-'],
        ['text' => 'v_hra v loterii',  'correct' => 'ý', 'hint' => 'výhra — předpona vý-'],
        ['text' => 'v_pracovat úkol',  'correct' => 'y', 'hint' => 'vypracovat — předpona vy-'],
        ['text' => 'v_sledek',         'correct' => 'ý', 'hint' => 'výsledek — předpona vý-'],
        ['text' => 'v_stava obrazů',   'correct' => 'ý', 'hint' => 'výstava — předpona vý-'],
        ['text' => 'v_stoupit z auta', 'correct' => 'y', 'hint' => 'vystoupit — předpona vy-'],
        ['text' => 'v_zkoušet',        'correct' => 'y', 'hint' => 'vyzkoušet — předpona vy-'],
        ['text' => 'v_uka ve škole',   'correct' => 'ý', 'hint' => 'výuka — předpona vý-'],
        ['text' => 'v_chovat děti',    'correct' => 'y', 'hint' => 'vychovat — předpona vy-'],
        ['text' => 'v_kres',           'correct' => 'ý', 'hint' => 'výkres — předpona vý-'],
        ['text' => 'v_právět příběh',  'correct' => 'y', 'hint' => 'vyprávět — předpona vy-'],
        ['text' => 'v_měnit baterie',  'correct' => 'y', 'hint' => 'vyměnit — předpona vy-'],
        ['text' => 'v_tah v domě',     'correct' => 'ý', 'hint' => 'výtah — předpona vý-'],
        ['text' => 'v_robek',          'correct' => 'ý', 'hint' => 'výrobek — předpona vý-'],
        ['text' => 'v_čistit boty',    'correct' => 'y', 'hint' => 'vyčistit — předpona vy-'],

        // protipříklady: vi- není předpona, ale součást slova
        ['text' => 'v_dět na tabuli',  'correct' => 'i', 'hint' => 'vidět — vi- tady není předpona'],
        ['text' => 'v_set na stromě',  'correct' => 'i', 'hint' => 'viset — vi- tady není předpona'],
        ['text' => 'v_na a trest',     'correct' => 'i', 'hint' => 'vina — vi- tady není předpona'],
        ['text' => 'v_tat hosty',      'correct' => 'í', 'hint' => 'vítat — ví- tady není předpona'],
        ['text' => 'v_ra v dobro',     'correct' => 'í', 'hint' => 'víra — ví- tady není předpona'],
        ['text' => 'v_tězství',        'correct' => 'í', 'hint' => 'vítězství — ví- tady není předpona'],
        ['text' => 'v_deoklip',        'correct' => 'i', 'hint' => 'videoklip — vi- tady není předpona'],
    ];
}
