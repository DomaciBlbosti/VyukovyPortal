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
 * Uklidí výstup OCR modelu.
 *
 * DeepSeek-OCR se zadáním <|grounding|> vrací ke každému bloku souřadnice
 * v podobě <|ref|>…<|/ref|><|det|>[[x,y,x,y]]<|/det|>. K sadě jsou k ničemu
 * a dítě by je nemělo vidět.
 */
function cleanOcrText(string $text): string {
    $text = preg_replace('/<\|ref\|>.*?<\|\/ref\|>/su', '', $text);
    $text = preg_replace('/<\|det\|>.*?<\|\/det\|>/su', '', $text);
    $text = str_replace(['<|grounding|>', '<image>'], '', $text);
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
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
 * @return array{ok:bool, text:string, error:string, warning:string, tokens:int}
 */
function llmOcrPage(string $imageB64, array $opts = []): array {
    $provider = llmProvider((string)($opts['provider'] ?? ''));
    $model    = trim((string)($opts['model'] ?? '')) ?: llmModel($provider, 'vision');
    $prompt   = trim((string)($opts['prompt'] ?? '')) ?: ocrPromptText(ocrDefaultPromptKey());
    $fail     = fn(string $e) => ['ok' => false, 'text' => '', 'error' => $e, 'warning' => '', 'tokens' => 0];

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

    $text = cleanOcrText($r['text']);
    if ($text === '') return $fail('Model vrátil prázdný přepis.');

    return ['ok' => true, 'text' => $text, 'error' => '',
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
