<?php
/**
 * Co se říká modelu — a kterému.
 *
 * Zadání je pro oba poskytovatele stejné, liší se jen přenos. Ollama běží
 * doma a nic z ní neodchází; komerční API bývá u přepisu přesnější, hlavně
 * v české diakritice, ale fotky učebnice odejdou ven. Volí se globálně
 * v nastavení a dá se přebít u každého spuštění.
 */
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/openai.php';

const LLM_PROVIDERS = [
    'ollama' => 'Ollama (doma, nic neodchází)',
    'openai' => 'Komerční API (přesnější, fotky odejdou ven)',
];

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

/** Výchozí poskytovatel; $override je volba u konkrétního spuštění */
function llmProvider(string $override = ''): string {
    if (isset(LLM_PROVIDERS[$override])) return $override;
    $p = getSetting('llm_provider', 'ollama');
    return isset(LLM_PROVIDERS[$p]) ? $p : 'ollama';
}

/** Model pro daný krok a poskytovatele */
function llmModel(string $provider, string $step): string {
    $key = ($provider === 'openai' ? 'openai_' : 'ollama_') . ($step === 'vision' ? 'vision_model' : 'text_model');
    $default = $provider === 'openai' ? 'gpt-4o-mini' : '';
    return getSetting($key, $default);
}

/**
 * Stav poskytovatele — používá se k testu spojení i k nabídce modelů.
 *
 * @return array{ok:bool, models:array<string>, error:string}
 */
function llmStatus(string $provider): array {
    return $provider === 'openai' ? openaiModels() : ollamaModels();
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
function parseOcrBlocks(string $raw): array {
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
    return array_values(array_filter($blocks, fn($b) => $b['text'] !== '' || ocrBlockIsImage($b['kind'])));
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
function ocrTidyText(string $text): string {
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
        $isTitle  = $b['kind'] === 'title';
        $isHeader = preg_match('/^(\*\*\d{1,2}\*\*|\d{1,2}\s+\*\*)/u', $b['text'])
                 || preg_match('/^(cvičení|exercise|úloha|úkol)\s*\d/iu', $b['text']);
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
            $label = trim(preg_replace('/[*#_]+/', '', strtok($label, "\n")));
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
 * @param array{provider?:string, model?:string, prompt?:string} $opts
 *        co není vyplněné, bere se z nastavení
 * @return array{ok:bool, text:string, blocks:array, error:string, warning:string, tokens:int}
 */
function llmOcrPage(string $imageB64, array $opts = []): array {
    $provider = llmProvider((string)($opts['provider'] ?? ''));
    $model    = trim((string)($opts['model'] ?? '')) ?: llmModel($provider, 'vision');
    $prompt   = trim((string)($opts['prompt'] ?? '')) ?: ocrPromptText(ocrDefaultPromptKey());
    $fail     = fn(string $e) => ['ok' => false, 'text' => '', 'blocks' => [], 'error' => $e, 'warning' => '', 'tokens' => 0];

    if ($model === '')  return $fail('Není vybraný model pro čtení obrázků.');
    if ($prompt === '') return $fail('Zadání pro přepis je prázdné.');

    if ($provider === 'openai') {
        $r = openaiVision($model, $prompt, $imageB64);
    } else {
        // Textový model by fotku mlčky zahodil a něco si vymyslel — radši
        // se zeptáme dopředu. Když se /api/show nepovede, jedeme dál.
        $show = ollamaShow($model);
        if ($show['ok'] && !$show['vision']) {
            return $fail('Model ' . $model . ' neumí obrázky (podle Ollamy nemá schopnost „vision"). Vyber vision model.');
        }
        $r = ollamaGenerate($model, $prompt, $imageB64);
    }
    if (!$r['ok']) return $fail($r['error']);

    $blocks = parseOcrBlocks($r['text']);
    $text   = $blocks ? blocksToText($blocks) : cleanOcrText($r['text']);
    if ($text === '') return $fail('Model vrátil prázdný přepis.');

    return ['ok' => true, 'text' => $text, 'blocks' => $blocks, 'error' => '',
            'warning' => ocrTextWarning($text, $prompt), 'tokens' => (int)($r['tokens'] ?? 0)];
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
        . ($kind === 'doplnovacka' ? "- v každé větě musí být přesně jedno podtržítko\n" : '')
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
function llmBuildSet(string $text, array $meta, string $providerOverride = ''): array {
    $provider = llmProvider($providerOverride);
    $model    = llmModel($provider, 'text');
    if ($model === '') return ['ok' => false, 'json' => '', 'error' => 'Není vybraný model pro sestavení sady.', 'warning' => ''];

    // Kontext hlídáme jen u Ollamy — komerční API mají okno tak velké,
    // že se na tenhle problém nedá narazit
    $warning = '';
    if ($provider === 'ollama') {
        // Model dostane zadání i text a musí se vejít i odpověď — počítáme
        // s tím, že sada bývá zhruba stejně dlouhá jako text, ze kterého vznikla
        $ctx    = ollamaContextSize();
        $needed = estimateTokens($text) * 2 + 500;
        if ($needed > $ctx) {
            $warning = 'Text je na nastavený kontext (' . $ctx . ' tokenů) dlouhý — odhadem je potřeba kolem '
                . $needed . '. Konec sady může chybět. Zvyš kontext v nastavení, vyber míň stránek,'
                . ' nebo sadu nech sestavit přes komerční API.';
        }
    }

    $prompt = buildSetPrompt($text, $meta);
    $r = $provider === 'openai'
        ? openaiChat($model, [['role' => 'user', 'content' => $prompt]], true)
        : ollamaGenerate($model, $prompt, null, true);

    if (!$r['ok']) return ['ok' => false, 'json' => '', 'error' => $r['error'], 'warning' => $warning];

    // Některé modely JSON i přes vynucený formát zabalí do ```json bloku
    $json = $r['text'];
    if (preg_match('/\{.*\}/s', $json, $m)) $json = $m[0];

    $pretty = json_decode($json, true);
    if (is_array($pretty)) $json = json_encode($pretty, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return ['ok' => true, 'json' => $json, 'error' => '', 'warning' => $warning];
}
