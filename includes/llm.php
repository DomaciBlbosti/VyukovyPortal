<?php
/**
 * Co se říká modelu — a kterému.
 *
 * Zadání je pro oba poskytovatele stejné, liší se jen přenos. Ollama běží
 * doma a nic z ní neodchází; komerční API bývá u přepisu přesnější, hlavně
 * v české diakritice, ale fotky učebnice odejdou ven. Volí se globálně
 * v nastavení a dá se přebít u jednotlivé dávky.
 */
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/openai.php';

const LLM_PROVIDERS = [
    'ollama' => 'Ollama (doma, nic neodchází)',
    'openai' => 'Komerční API (přesnější, fotky odejdou ven)',
];

/** Výchozí poskytovatel; $override je volba u konkrétní dávky */
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

/** Zadání pro přepis stránky */
function ocrPrompt(): string {
    return <<<TXT
        Přepiš text z téhle stránky učebnice. Piš přesně to, co na stránce je,
        včetně české diakritiky. Nic nepřidávej, nekomentuj a nepřekládej.

        Když je na stránce dvousloupcový seznam slovíček, zapiš každou dvojici
        na vlastní řádek ve tvaru: anglicky = česky

        Když je na stránce běžný text, přepiš ho po odstavcích.
        Obrázky a čísla stránek vynech.
        TXT;
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
 * Přepíše jednu stránku.
 *
 * @return array{ok:bool, text:string, error:string}
 */
function llmOcrPage(string $imageB64, string $providerOverride = ''): array {
    $provider = llmProvider($providerOverride);
    $model    = llmModel($provider, 'vision');
    if ($model === '') return ['ok' => false, 'text' => '', 'error' => 'Není vybraný model pro čtení obrázků.'];

    $r = $provider === 'openai'
        ? openaiVision($model, ocrPrompt(), $imageB64)
        : ollamaGenerate($model, ocrPrompt(), $imageB64);

    return $r['ok'] && trim($r['text']) === ''
        ? ['ok' => false, 'text' => '', 'error' => 'Model vrátil prázdný přepis.']
        : $r;
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
                . $needed . '. Konec sady může chybět. Zvyš kontext v nastavení, dávku rozděl na míň stránek,'
                . ' nebo ji nech sestavit přes komerční API.';
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
