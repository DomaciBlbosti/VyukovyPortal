<?php
/**
 * Co se říká modelu — a kterému.
 *
 * Zadání je pro každý model stejné a posílá se vždycky na tutéž adresu —
 * Ollama Proxy. Jestli si ho přečte model na domácí kartě, nebo komerční
 * API, rozhoduje jen jeho název; klíče k poskytovatelům drží proxy.
 */
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/proxy.php';

/**
 * Zadání pro přepis stránky — sada k vyzkoušení.
 *
 * Specializované OCR modely (DeepSeek-OCR a co je na něm postavené, třeba
 * strike-ocr) jsou naučené na pár krátkých anglických vět a na jiné zadání
 * reagují špatně: dlouhé české instrukce místo přepisu opakují dokola.
 * Obecné vision modely (gemma, qwen2.5vl, minicpm-v, gpt-4o-mini) naopak
 * dlouhé zadání potřebují, jinak stránku shrnou nebo přeloží.
 *
 * Model je na přesné znění citlivý — i chybějící tečka na konci mění výstup,
 * proto se prompty posílají doslova tak, jak jsou tady.
 */
const OCR_PROMPTS = [
    'deepseek_free' => [
        'label'  => 'DeepSeek-OCR — Free OCR.',
        'for'    => 'deepseek-ocr, strike-ocr a další na DeepSeek-OCR',
        'prompt' => 'Free OCR.',
        'note'   => 'Holý text bez rozvržení. Nejrychlejší a nejmíň náchylné na zacyklení — začni tímhle.',
    ],
    'deepseek_markdown' => [
        'label'  => 'DeepSeek-OCR — markdown',
        'for'    => 'deepseek-ocr, strike-ocr',
        'prompt' => '<|grounding|>Convert the document to markdown.',
        'note'   => 'Zachová nadpisy, tabulky a seznamy. Souřadnicové značky, které model přidá, aplikace odstraní.',
    ],
    'deepseek_combo' => [
        'label'  => 'DeepSeek-OCR — rámečky + vynechávky (2 volání)',
        'for'    => 'deepseek-ocr, strike-ocr — pracovní sešity',
        'prompt' => "<|grounding|>Convert the document to markdown.\nFree OCR.",
        'note'   => 'Dvě volání na stejnou fotku: první dá rozvržení a obrázky, druhé text s vynechávkami (______). '
                  . 'Řádky z druhého nahradí texty bloků z prvního. Trvá dvakrát déle.',
    ],
    'deepseek_layout' => [
        'label'  => 'DeepSeek-OCR — OCR this image',
        'for'    => 'deepseek-ocr, strike-ocr',
        'prompt' => '<|grounding|>OCR this image.',
        'note'   => 'Text s rozvržením, ale bez převodu na markdown.',
    ],
    'deepseek_extract' => [
        'label'  => 'DeepSeek-OCR — Extract the text',
        'for'    => 'deepseek-ocr, strike-ocr',
        'prompt' => 'Extract the text in the image.',
        'note'   => 'Alternativa k Free OCR, když ten vrací málo.',
    ],
    'strike' => [
        'label'  => 'Strike-OCR — doporučené autorem',
        'for'    => 'HSR-DeepThink/strike-ocr',
        'prompt' => 'Extract text from this image',
        'note'   => 'Znění, které uvádí autor modelu. Bez tečky na konci — schválně.',
    ],
    'vlm_en' => [
        'label'  => 'Obecný vision model — anglicky',
        'for'    => 'gemma, qwen2.5vl, minicpm-v, llama3.2-vision, gpt-4o-mini',
        'prompt' => "Transcribe all the text on this textbook page exactly as printed, including Czech diacritics (háčky, čárky). "
                  . "Keep the line breaks. If the page has a two-column vocabulary list, write each pair on its own line as: english = česky. "
                  . "Do not translate, summarize, or comment. Output only the transcription.",
        'note'   => 'Obecné modely rozumí anglickému zadání spolehlivěji než českému.',
    ],
    'vlm_cs' => [
        'label'  => 'Obecný vision model — česky',
        'for'    => 'gemma, qwen2.5vl, gpt-4o-mini',
        'prompt' => "Přepiš text z téhle stránky učebnice. Piš přesně to, co na stránce je, včetně české diakritiky. "
                  . "Nic nepřidávej, nekomentuj a nepřekládej.\n\n"
                  . "Když je na stránce dvousloupcový seznam slovíček, zapiš každou dvojici na vlastní řádek ve tvaru: anglicky = česky\n\n"
                  . "Když je na stránce běžný text, přepiš ho po odstavcích. Obrázky a čísla stránek vynech.",
        'note'   => 'Původní zadání aplikace. Specializované OCR modely na něj reagují zacyklením.',
    ],
    'custom' => [
        'label'  => 'Vlastní zadání',
        'for'    => 'cokoli',
        'prompt' => '',
        'note'   => 'Napiš si vlastní. U DeepSeek modelů drž jednu krátkou anglickou větu.',
    ],
];

/** Výchozí zadání z nastavení (klíč presetu) */
function ocrDefaultPromptKey(): string {
    $k = getSetting('ocr_prompt_key', 'vlm_cs');
    return isset(OCR_PROMPTS[$k]) ? $k : 'vlm_cs';
}

/**
 * Text zadání pro daný preset. U „custom" se bere text z formuláře,
 * a když chybí, uložené vlastní zadání z nastavení.
 */
function ocrPromptText(string $key, string $custom = ''): string {
    if ($key === 'custom' || !isset(OCR_PROMPTS[$key])) {
        $t = trim($custom);
        return $t !== '' ? $t : trim(getSetting('ocr_custom_prompt'));
    }
    return OCR_PROMPTS[$key]['prompt'];
}

/** Model pro daný krok; prázdný, dokud si ho admin nevybere */
function llmModel(string $step): string {
    return getSetting($step === 'vision' ? 'vision_model' : 'text_model');
}

/**
 * Hrubý odhad počtu tokenů. Čeština má kolem tří znaků na token, což na
 * varování „tohle se nevejde" bohatě stačí — přesnost tady nepotřebujeme.
 */
function estimateTokens(string $text): int {
    return (int)ceil(mb_strlen($text) / 3);
}

/**
 * Rozloží výstup OCR modelu na bloky.
 *
 * DeepSeek-OCR se zadáním <|grounding|> vrací ke každému kusu stránky
 * i rámeček v souřadnicích 0–999 (podíl šířky a výšky obrázku). Pod Ollamou
 * to vypadá takhle (speciální značky <|ref|>/<|det|> Ollama sama odstraní):
 *
 *   title[[67, 67, 424, 92]]
 *   # 1C Mickey, Millie and Mut
 *
 *   image[[81, 165, 227, 240]]
 *
 *   text[[81, 241, 444, 264]]
 *   1 What do children in the USA do on Thanksgiving Day, Casey?
 *
 * Zadání „OCR this image." dává místo toho řádek po řádku: obsah[[rámeček]].
 * Z toho se skládají odstavce podle toho, jak řádky na sebe navazují.
 *
 * Bloky jsou k tomu, aby šlo ze stránky vybrat jedno cvičení a aby se
 * obrázky daly vyříznout a uložit. Bez rámečků (Free OCR.) bloky nejsou.
 *
 * @return array<int, array{kind:string, box:?array{int,int,int,int}, text:string}>
 */
function parseOcrBlocks(string $raw, ?array &$stats = null): array {
    $stats = ['dropped' => 0];
    $s = preg_replace('/<\|ref\|>(.*?)<\|\/ref\|>\s*<\|det\|>(\[\[.*?\]\])<\|\/det\|>/su', '$1$2', $raw);
    $s = str_replace(['<|grounding|>', '<image>'], '', $s);
    if (!str_contains($s, '[[')) return [];

    $box    = '\[\[(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\](?:,\s*\[[^\]]*\])*\]';
    $blocks = [];
    $cur    = null;
    $flush  = function () use (&$blocks, &$cur) {
        if ($cur !== null) { $cur['text'] = trim($cur['text']); $blocks[] = $cur; }
        $cur = null;
    };

    foreach (explode("\n", $s) as $line) {
        $t = trim($line);
        // štítek bloku: druh + rámeček, obsah následuje na dalších řádcích
        if (preg_match('/^([a-z_]+)' . $box . '$/', $t, $m)) {
            $flush();
            $cur = ['kind' => $m[1], 'box' => [(int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5]], 'text' => ''];
            continue;
        }
        // řádkový režim: obsah[[rámeček]]
        if (preg_match('/^(.*?)\s*' . $box . '$/', $t, $m)) {
            $flush();
            $b = [(int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5]];
            // Řádky, které na sebe navazují (stejný levý okraj, těsně pod
            // sebou), patří do jednoho odstavce
            $last = $blocks ? $blocks[count($blocks) - 1] : null;
            if ($last && $last['kind'] === 'text' && $last['box'] && !empty($last['_line'])
                && abs($b[0] - $last['box'][0]) <= 40 && $b[1] - $last['box'][3] <= 12) {
                $blocks[count($blocks) - 1]['text'] .= "\n" . trim($m[1]);
                $blocks[count($blocks) - 1]['box'][2] = max($last['box'][2], $b[2]);
                $blocks[count($blocks) - 1]['box'][3] = $b[3];
            } else {
                $blocks[] = ['kind' => 'text', 'box' => $b, 'text' => trim($m[1]), '_line' => true];
            }
            continue;
        }
        if ($cur !== null) $cur['text'] .= ($cur['text'] === '' ? '' : "\n") . $t;
    }
    $flush();

    foreach ($blocks as &$b) {
        unset($b['_line']);
        $b['text'] = ocrTidyText($b['text']);
    }
    unset($b);

    // Zacyklený model chrlí pořád tentýž blok (typicky image[[0, 0, 0, 0]]),
    // dokud mu nedojde limit odpovědi. Prázdné rámečky a opakování zahodíme
    // a spočítáme, ať se dá stránka označit jako podezřelá.
    $seen = [];
    $out  = [];
    foreach ($blocks as $b) {
        $box  = $b['box'];
        $bad  = $box && ($box[2] <= $box[0] || $box[3] <= $box[1]);
        // „Obrázek" přes celou stránku znamená, že model stránku nerozpoznal —
        // výřez by byl celá fotka a text by se schoval za jednu značku
        if ($box && ocrBlockIsImage($b['kind']) && ($box[2] - $box[0]) * ($box[3] - $box[1]) >= 900000) { $stats['dropped']++; continue; }
        $key  = $b['kind'] . '|' . json_encode($box) . '|' . $b['text'];
        if ($bad || isset($seen[$key]) || ($b['text'] === '' && !ocrBlockIsImage($b['kind']))) {
            if ($bad || isset($seen[$key])) $stats['dropped']++;
            continue;
        }
        $seen[$key] = true;
        $out[] = $b;
    }
    return $out;
}

/** Druhy bloků, které jsou obrázek (a mají se vyříznout) */
function ocrBlockIsImage(string $kind): bool {
    return in_array($kind, ['image', 'figure', 'picture', 'photo'], true);
}

/**
 * Drobný úklid textu: dělení slov na konci řádku („přita- hovány") a
 * přebytečné mezery. Jen malá písmena po obou stranách — pomlčka mezi
 * velkými písmeny nebo číslicemi bývá skutečná.
 */

/**
 * Křížovka nebo prázdná tabulka svede model k tomu, že vypíše stovky prázdných
 * buněk — na jedné stránce z toho byl přepis o 8 tisících znacích, ze kterého
 * se sada složit nedá a jen ujídá kontext. Necháme po mřížce značku.
 */
function collapseEmptyGrids(string $text): string {
    $text = preg_replace('/^\|(?:[^\S\n]*\|){11,}[^\S\n]*$/mu', '[mřížka]', $text);
    // stejný řádek tabulky několikrát za sebou stačí jednou
    $text = preg_replace('/^(\|[^\n]*\|)$(?:\n\1$)+/mu', '$1', $text);
    $text = preg_replace('/^(\[mřížka\])$(?:\n\[mřížka\]$)+/mu', '$1', $text);
    return $text;
}

function ocrTidyText(string $text): string {
    $text = collapseEmptyGrids($text);
    $text = preg_replace('/(\p{Ll})- (\p{Ll})/u', '$1$2', $text);
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

/**
 * Přepis stránky z bloků — obrázky nahradí značka, ať model při skládání
 * sady ví, že tam něco bylo, ale nezkouší to popisovat.
 */
function blocksToText(array $blocks): string {
    $parts = [];
    $img   = 0;
    foreach ($blocks as $b) {
        if (ocrBlockIsImage($b['kind'])) {
            $parts[] = '[obrázek ' . ++$img . ']';
        } elseif ($b['text'] !== '') {
            $parts[] = $b['text'];
        }
    }
    return implode("\n\n", $parts);
}

/**
 * Uklidí výstup OCR modelu na čistý přepis.
 *
 * S rámečky se text skládá z bloků (souřadnice pryč, obrázky jako značka);
 * bez nich se jen odstraní zbytky značek a srovná dělení slov.
 */
function cleanOcrText(string $text): string {
    $blocks = parseOcrBlocks($text);
    if ($blocks) return blocksToText($blocks);

    $text = preg_replace('/<\|ref\|>.*?<\|\/ref\|>/su', '', $text);
    $text = preg_replace('/<\|det\|>.*?<\|\/det\|>/su', '', $text);
    $text = str_replace(['<|grounding|>', '<image>'], '', $text);
    return ocrTidyText($text);
}

/**
 * Klíč pro porovnání dvou řádků: jen písmena a číslice, malá.
 * Vynechávky, hvězdičky, tečky za číslem — to všechno se mezi zadáními
 * liší a při hledání stejného řádku to jen překáží.
 */
function ocrLineKey(string $line): string {
    return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $line));
}

/**
 * Doplní do bloků vynechávky z druhého přepisu.
 *
 * Zadání s rámečky (<|grounding|>) dá rozvržení a obrázky, ale prázdné
 * řádky na doplnění („______") zahodí. „Free OCR." je naopak nechá, jenže
 * nedá rámečky. Tady se ke každému řádku bloku hledá stejný řádek ve druhém
 * přepisu — a když se najde, vezme se jeho znění i s vynechávkami.
 *
 * Hledá se postupně (druhý přepis jde po stránce stejným směrem), s malým
 * oknem dopředu a možností, že jeden řádek bloku odpovídá dvěma či třem
 * řádkům druhého přepisu.
 *
 * @return array{blocks:array, matched:int, total:int}
 */
function mergeBlanksIntoBlocks(array $blocks, string $freeText): array {
    $free = [];
    foreach (explode("\n", $freeText) as $l) {
        $l = trim($l);
        if ($l === '') continue;
        // odrážky a číslování ze seznamu pryč — v bloku bývá jen číslo
        $free[] = ['raw' => preg_replace('/^[-*]\s+/', '', $l), 'key' => ocrLineKey($l)];
    }
    $cursor  = 0;
    $matched = 0;
    $total   = 0;

    foreach ($blocks as &$b) {
        if (ocrBlockIsImage($b['kind']) || $b['text'] === '') continue;
        $lines = explode("\n", $b['text']);
        foreach ($lines as &$line) {
            $key = ocrLineKey($line);
            if (mb_strlen($key) < 3) continue;
            $total++;

            $best = null;
            $bestScore = 0.0;
            $end = min(count($free), $cursor + 40);
            for ($j = $cursor; $j < $end; $j++) {
                $candKey = '';
                $candRaw = [];
                for ($k = 0; $k < 3 && $j + $k < count($free); $k++) {
                    $candKey .= $free[$j + $k]['key'];
                    $candRaw[] = $free[$j + $k]['raw'];
                    if (mb_strlen($candKey) > mb_strlen($key) * 1.6 + 6) break;
                    similar_text($key, $candKey, $pct);
                    $score = $pct / 100;
                    if ($score > $bestScore) { $bestScore = $score; $best = [$j + $k + 1, implode("\n", $candRaw)]; }
                }
            }
            if ($best && $bestScore >= 0.72) {
                // Řádky druhého přepisu, které se přeskočily (typicky prázdná
                // linka na odpověď „________"), patří sem před nalezený řádek
                $start   = $best[0] - substr_count($best[1], "\n") - 1;
                $skipped = array_slice($free, $cursor, max(0, $start - $cursor));
                $extra   = count($skipped) <= 4 ? implode("\n", array_column($skipped, 'raw')) : '';
                $line    = ($extra !== '' ? $extra . "\n" : '') . $best[1];
                $cursor  = $best[0];
                $matched++;
            }
        }
        unset($line);
        $b['text'] = implode("\n", $lines);
    }
    unset($b);

    // Co ve druhém přepisu zbylo za posledním nalezeným řádkem, rámečky
    // nepokryly (zacyklení, nebo model půl stránky prohlásil za obrázek).
    // Ať to není ztracené — přidá se jako blok bez rámečku.
    $rest = array_column(array_slice($free, $cursor), 'raw');
    if (count($rest) >= 2 || mb_strlen(implode("\n", $rest)) >= 80) {
        // Rozdělit na hranicích cvičení („### 3", „**4**"), ať se zbytek
        // v tvorbě sad nabídne po cvičeních, ne jako jeden špalek
        $chunk = [];
        foreach ($rest as $l) {
            if ($chunk && preg_match('/^(#{1,6}\s*\d{1,2}\s*$|\*\*\d{1,2}\*\*)/u', $l)) {
                $blocks[] = ['kind' => 'text', 'box' => null, 'text' => trim(implode("\n", $chunk))];
                $chunk = [];
            }
            $chunk[] = $l;
        }
        if ($chunk) $blocks[] = ['kind' => 'text', 'box' => null, 'text' => trim(implode("\n", $chunk))];
    }

    // Když se řádek bloku spároval se dvěma řádky druhého přepisu, další blok
    // („Fun with Art.") už je jen jejich ocas — ten by byl v textu dvakrát
    $prevKey = '';
    foreach ($blocks as $i => $b) {
        if (ocrBlockIsImage($b['kind'])) continue;
        $key = ocrLineKey($b['text']);
        if ($key !== '' && $prevKey !== '' && mb_strlen($key) >= 3 && str_contains($prevKey, $key)) {
            $blocks[$i]['text'] = '';
            continue;
        }
        $prevKey = $key;
    }
    $blocks = array_values(array_filter($blocks, fn($b) => $b['text'] !== '' || ocrBlockIsImage($b['kind'])));
    return ['blocks' => splitBlocksAtExerciseHeaders($blocks), 'matched' => $matched,
            'total' => $total, 'leftover' => count($rest)];
}

/**
 * Je řádek začátkem cvičení? Pracovní sešit je čísluje „**1**", „### 2"
 * nebo „3 **Listen…"; učebnice občas píše „Cvičení 4".
 */
function isExerciseHeader(string $line): bool {
    $line = trim($line);
    return (bool)(preg_match('/^(\*\*\d{1,2}\*\*|\d{1,2}\s+\*\*|#{1,6}\s*\d{1,2}\s*$)/u', $line)
                || preg_match('/^(cvičení|exercise|úloha|úkol)\s*\d/iu', $line));
}

/**
 * Rozdělí bloky tak, aby nadpis cvičení vždycky stál na začátku bloku.
 *
 * Po spojení dvou přepisů se před nadpis může dostat poslední řádek
 * předchozího cvičení („7. ______" a hned pod tím „### 3"). Skupiny se pak
 * slijí do jedné a v tvorbě sad chybí cvičení k výběru.
 */
function splitBlocksAtExerciseHeaders(array $blocks): array {
    $out = [];
    foreach ($blocks as $b) {
        $lines = explode("\n", $b['text']);
        if (ocrBlockIsImage($b['kind']) || count($lines) < 2) { $out[] = $b; continue; }

        $chunk = [];
        foreach ($lines as $i => $line) {
            if ($i > 0 && $chunk && isExerciseHeader($line)) {
                $out[] = ['kind' => $b['kind'], 'box' => $b['box'], 'text' => trim(implode("\n", $chunk))];
                $chunk = [];
            }
            $chunk[] = $line;
        }
        if ($chunk) $out[] = ['kind' => $b['kind'], 'box' => $b['box'], 'text' => trim(implode("\n", $chunk))];
    }
    return array_values(array_filter($out, fn($b) => $b['text'] !== '' || ocrBlockIsImage($b['kind'])));
}

/**
 * Seskupí bloky do cvičení.
 *
 * Nadpis nebo blok začínající číslem cvičení („**1** Complete…", „3 T9 …")
 * otevírá novou skupinu; co následuje, patří k ní až do dalšího. Na stránce
 * bez cvičení vyjde jedna skupina za celou stránku.
 *
 * @return array<int, array{label:string, blocks:array<int,int>}> indexy do $blocks
 */
function groupOcrBlocks(array $blocks): array {
    $groups = [];
    $open   = null;
    foreach ($blocks as $i => $b) {
        $isTitle  = $b['kind'] === 'title' && preg_match('/[\p{L}\p{N}]/u', $b['text']);
        // Rozhoduje první řádek bloku — o to, aby tam nadpis opravdu stál,
        // se postaralo splitBlocksAtExerciseHeaders()
        $isHeader = isExerciseHeader(strtok($b['text'], "\n") ?: '');
        // Nadpis lekce zůstává s cvičením, které po něm následuje —
        // samostatná skupina jen s nadpisem by byla k ničemu
        $onlyTitles = $open !== null && !array_filter($groups[$open]['blocks'], fn($j) => $blocks[$j]['kind'] !== 'title');
        if ($open === null || $isTitle && !$onlyTitles || $isHeader && !$onlyTitles) {
            $groups[] = ['label' => '', 'blocks' => []];
            $open = count($groups) - 1;
        }
        $groups[$open]['blocks'][] = $i;
        if ($groups[$open]['label'] === '' || $isHeader) {
            $label = $b['text'] !== '' ? $b['text'] : (ocrBlockIsImage($b['kind']) ? 'obrázek' : $b['kind']);
            // Z „### 1\n**Complete…**" chceme „1 Complete…", ne jen „1"
            $ls    = array_values(array_filter(array_map('trim', explode("\n", $label)), fn($l) => $l !== ''));
            $label = trim(preg_replace('/[*#_]+/', '', $ls[0] ?? ''));
            if (mb_strlen($label) <= 3 && isset($ls[1])) $label .= ' ' . trim(preg_replace('/[*#_]+/', '', $ls[1]));
            $groups[$open]['label'] = mb_substr($label, 0, 70) . (mb_strlen($label) > 70 ? '…' : '');
        }
    }
    return $groups;
}

/**
 * Nápadné znaky toho, že přepis není přepis.
 *
 * Model, který se zacyklí, vrátí stovky řádků, ale jen pár různých; model,
 * který zadání nepochopil, ho zopakuje. Obojí projde jako „hotovo" a bez
 * kontroly by doputovalo až do sady. Vrací prázdný řetězec, když nic nesedí.
 */
function ocrTextWarning(string $text, string $prompt): string {
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
    $n     = count($lines);
    if ($n >= 8) {
        $distinct = count(array_unique($lines));
        if ($distinct * 2 < $n) {
            return "Přepis se opakuje ($n řádků, jen $distinct různých) — model se nejspíš zacyklil. Zkus jiné zadání nebo model.";
        }
    }
    // Opakuje-li text kus zadání, model přepisoval instrukci místo stránky
    $probe = mb_substr(trim($prompt), 0, 25);
    if (mb_strlen($probe) >= 12 && mb_stripos($text, $probe) !== false) {
        return 'Model zopakoval zadání místo přepisu stránky. Zkus kratší zadání nebo jiný model.';
    }
    if (mb_strlen($text) < 20) {
        return 'Přepis je podezřele krátký.';
    }
    return '';
}

/**
 * Přepíše jednu stránku.
 *
 * @param array{model?:string, prompt?:string} $opts
 *        co není vyplněné, bere se z nastavení
 * @return array{ok:bool, text:string, blocks:array, error:string, warning:string, tokens:int}
 */
function llmOcrPage(string $imageB64, array $opts = []): array {
    $model    = trim((string)($opts['model'] ?? '')) ?: llmModel('vision');
    $prompt   = trim((string)($opts['prompt'] ?? '')) ?: ocrPromptText(ocrDefaultPromptKey());
    $key      = (string)($opts['prompt_key'] ?? '');
    $fail     = fn(string $e) => ['ok' => false, 'text' => '', 'blocks' => [], 'error' => $e, 'warning' => '', 'tokens' => 0];

    if ($model === '')  return $fail('Není vybraný model pro čtení obrázků.');
    if ($prompt === '') return $fail('Zadání pro přepis je prázdné.');

    // Textový model by fotku mlčky zahodil a něco si vymyslel — radši se
    // zeptáme dopředu. U komerčního modelu se ptát nemá koho a /api/show
    // selže; pak jedeme dál.
    $show = proxyShow($model);
    if ($show['ok'] && !$show['vision']) {
        return $fail('Model ' . $model . ' neumí obrázky (nemá schopnost „vision"). Vyber vision model.');
    }
    $call = fn(string $p) => proxyGenerate($model, $p, $imageB64);

    // Kombinované zadání: první řádek dá rámečky, druhý text s vynechávkami
    $lines = array_values(array_filter(array_map('trim', explode("\n", $prompt)), fn($l) => $l !== ''));
    if ($key === 'deepseek_combo' && count($lines) >= 2) {
        return llmOcrPageCombo($call, $lines[0], $lines[1]);
    }

    $r = $call($prompt);
    if (!$r['ok']) return $fail($r['error']);

    $blocks = parseOcrBlocks($r['text'], $stats);
    $text   = $blocks ? blocksToText($blocks) : cleanOcrText($r['text']);
    if ($text === '') return $fail('Model vrátil prázdný přepis.');

    return ['ok' => true, 'text' => $text, 'blocks' => $blocks, 'error' => '',
            'warning' => ocrRunWarning($text, $prompt, $stats, $r), 'tokens' => (int)($r['tokens'] ?? 0)];
}

/**
 * Dvě volání na stejnou fotku a jejich spojení (viz mergeBlanksIntoBlocks).
 *
 * Když selže první, zůstane holý text bez bloků; když druhé, zůstanou
 * bloky bez vynechávek — v obou případech s varováním, ať je jasné, co chybí.
 */
function llmOcrPageCombo(callable $call, string $layoutPrompt, string $textPrompt): array {
    $fail = fn(string $e) => ['ok' => false, 'text' => '', 'blocks' => [], 'error' => $e, 'warning' => '', 'tokens' => 0];

    $a = $call($layoutPrompt);
    $b = $call($textPrompt);
    if (!$a['ok'] && !$b['ok']) return $fail($a['error']);

    $tokens = (int)($a['tokens'] ?? 0) + (int)($b['tokens'] ?? 0);
    $blocks = $a['ok'] ? parseOcrBlocks($a['text'], $stats) : [];
    $free   = $b['ok'] ? cleanOcrText($b['text']) : '';

    if (!$blocks) {
        if ($free === '') return $fail('Model vrátil prázdný přepis.');
        // Aspoň text rozdělený po cvičeních, i když bez rámečků a obrázků
        $blocks = mergeBlanksIntoBlocks([], $free)['blocks'];
        return ['ok' => true, 'text' => $free, 'blocks' => $blocks, 'error' => '', 'tokens' => $tokens,
                'warning' => 'Rámečky se nepovedly (' . ($a['ok'] ? 'model je nevrátil nebo celou stránku prohlásil za obrázek' : $a['error']) . ') — je jen text bez obrázků.'];
    }
    $warning = ocrRunWarning(blocksToText($blocks), $layoutPrompt, $stats, $a);
    if ($free === '') {
        $warning = $warning ?: 'Druhé volání (text s vynechávkami) selhalo: ' . $b['error'] . ' — bloky jsou bez vynechávek.';
    } else {
        $m = mergeBlanksIntoBlocks($blocks, $free);
        $blocks = $m['blocks'];
        if ($warning === '' && $m['total'] > 0 && $m['matched'] * 2 < $m['total']) {
            $warning = 'Texty obou volání se moc neshodují (spárováno ' . $m['matched'] . ' z ' . $m['total'] . ' řádků) — vynechávky mohou chybět.';
        }
        if ($m['leftover'] >= 5) {
            $warning = $warning ?: 'Rámečky pokryly jen část stránky — zbytek textu (' . $m['leftover'] . ' řádků) je na konci jako blok bez rámečku a bez obrázků.';
        }
    }
    return ['ok' => true, 'text' => blocksToText($blocks), 'blocks' => $blocks, 'error' => '',
            'warning' => $warning, 'tokens' => $tokens];
}

/** Varování k běhu: opakování, zacyklení na rámečcích, useknutá odpověď */
function ocrRunWarning(string $text, string $prompt, ?array $stats, array $r): string {
    $warning = ocrTextWarning($text, $prompt);
    if ($warning === '' && ($stats['dropped'] ?? 0) >= 10) {
        $warning = 'Model se zacyklil na rámečcích (' . $stats['dropped'] . ' opakování zahozeno) — konec stránky nejspíš chybí. Zkus jiné zadání, třeba Free OCR.';
    }
    if ($warning === '' && !empty($r['truncated'])) {
        $warning = 'Odpověď narazila na limit délky — konec stránky může chybět. Zkus kratší zadání nebo jiný model.';
    }
    return $warning;
}

/** Zadání pro sestavení sady z přepsaného textu */
function buildSetPrompt(string $text, array $meta): string {
    $kind  = $meta['kind'] ?? 'dvojice';
    $shape = match ($kind) {
        'vyber'       => '{"otazka": "…", "odpoved": "…", "moznosti": ["…", "…", "…"]}',
        'doplnovacka' => '{"veta": "věta s _ místo vynechaného slova", "odpoved": "…", "moznosti": ["…", "…"]}',
        'cteni'       => '{"otazka": "…", "odpoved": "…", "moznosti": ["…", "…"]}',
        default       => '{"a": "zadání", "b": "odpověď"}',
    };

    return "Z následujícího textu z učebnice vytvoř sadu na procvičování.\n\n"
        . "Vrať POUZE JSON v tomhle tvaru:\n"
        . "{\n"
        . '  "predmet": "' . ($meta['subject'] ?? 'ostatni') . "\",\n"
        . '  "rocnik": ' . (int)($meta['grade'] ?? 0) . ",\n"
        . '  "nazev": ' . json_encode($meta['title'] ?? '', JSON_UNESCAPED_UNICODE) . ",\n"
        . '  "zdroj": ' . json_encode($meta['source'] ?? '', JSON_UNESCAPED_UNICODE) . ",\n"
        . '  "typ": "' . $kind . "\",\n"
        . ($kind === 'cteni' ? '  "text": "text k přečtení",' . "\n" : '')
        . '  "polozky": [' . $shape . "]\n"
        . "}\n\n"
        . "Pravidla:\n"
        . "- každou položku uveď jen jednou, žádné duplicity\n"
        . "- zachovej českou diakritiku\n"
        . "- co v textu není, si nevymýšlej\n"
        . ($kind === 'doplnovacka' ? "- v každé větě musí být přesně jedno podtržítko a přesně jedno vynechané slovo\n"
                                   . "- větu, kde je vynechaných slov víc, do sady nedávej\n"
                                   . "- odpověď je to jedno slovo, které do vynechávky patří\n" : '')
        . ($kind === 'vyber' || $kind === 'cteni' ? "- u každé otázky uveď aspoň tři možnosti včetně správné\n" : '')
        . "\nText:\n" . $text;
}

/**
 * Sestaví z přepsaného textu JSON sady.
 *
 * Výsledek se pak ověřuje stejným validátorem jako ručně vložená sada, takže
 * i když model něco zkomolí, do databáze se to nedostane.
 *
 * @return array{ok:bool, json:string, error:string, warning:string}
 */
function llmBuildSet(string $text, array $meta, string $modelOverride = ''): array {
    $model = trim($modelOverride) ?: llmModel('text');
    if ($model === '') return ['ok' => false, 'json' => '', 'error' => 'Není vybraný model pro sestavení sady.', 'warning' => ''];

    // Kontext hlídáme jen u modelů běžících doma — komerční mají okno tak
    // velké, že se na tenhle problém nedá narazit
    $warning = '';
    if (modelIsLocal($model)) {
        // Model dostane zadání i text a musí se vejít i odpověď — počítáme
        // s tím, že sada bývá zhruba stejně dlouhá jako text, ze kterého vznikla
        $ctx    = proxyContextSize();
        $needed = estimateTokens($text) * 2 + 500;
        if ($needed > $ctx) {
            $warning = 'Text je na nastavený kontext (' . $ctx . ' tokenů) dlouhý — odhadem je potřeba kolem '
                . $needed . '. Konec sady může chybět. Zvyš kontext v nastavení, vyber míň stránek,'
                . ' nebo sadu nech složit komerčním modelem.';
        }
    }

    $prompt = buildSetPrompt($text, $meta);
    $r = proxyGenerate($model, $prompt, null, true);

    if (!$r['ok']) return ['ok' => false, 'json' => '', 'error' => $r['error'], 'warning' => $warning];

    // Některé modely JSON i přes vynucený formát zabalí do ```json bloku
    $json = $r['text'];
    if (preg_match('/\{.*\}/s', $json, $m)) $json = $m[0];

    $pretty = json_decode($json, true);
    if (is_array($pretty)) {
        // Hlavičku sady vyplnil admin ve formuláři — model do ní mluvit nemá.
        // Gemma třeba vrátila „doplnacka" místo „doplnovacka" a celá sada
        // pak neprošla kontrolou, i když položky byly v pořádku.
        $pretty = array_merge($pretty, [
            'predmet' => (string)($meta['subject'] ?? 'ostatni'),
            'rocnik'  => (int)($meta['grade'] ?? 0),
            'typ'     => (string)($meta['kind'] ?? 'dvojice'),
        ]);
        foreach (['nazev' => 'title', 'zdroj' => 'source'] as $key => $from) {
            if (trim((string)($meta[$from] ?? '')) !== '') $pretty[$key] = (string)$meta[$from];
        }
        $json = json_encode($pretty, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    return ['ok' => true, 'json' => $json, 'error' => '', 'warning' => $warning];
}
