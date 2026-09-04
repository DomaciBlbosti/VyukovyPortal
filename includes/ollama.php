<?php
/**
 * Připojení na Ollamu — přepis naskenovaných stránek a jejich převod na sadu.
 *
 * Běží u tebe doma, takže se fotky učebnic nikam neposílají. Ollama je ale
 * pomalá: jedna stránka trvá desítky sekund až minuty, proto se stránky
 * zpracovávají po jedné a prohlížeč si o další říká sám.
 *
 * Adresa i modely se nastavují v adminu, protože každý si Ollamu pouští
 * jinde — vedle v kontejneru, na jiném stroji v síti.
 */
require_once __DIR__ . '/../config/db.php';

const OLLAMA_DEFAULTS = [
    'ollama_url'          => 'http://ollama:11434',
    'ollama_vision_model' => '',
    'ollama_text_model'   => '',
];

/**
 * Načtená nastavení. Držíme je pohromadě, ať se kvůli třem hodnotám nechodí
 * do databáze třikrát; zápis mezipaměť zahodí, aby se změna hned projevila.
 */
function &settingsCache(): ?array {
    static $cache = null;
    return $cache;
}

/** Hodnota nastavení; když není v databázi, bere se env proměnná a pak výchozí */
function getSetting(string $key, string $default = ''): string {
    $cache = &settingsCache();
    if ($cache === null) {
        $cache = [];
        try {
            foreach (getDB()->query('SELECT setting_key, setting_value FROM app_settings') as $r) {
                $cache[$r['setting_key']] = (string)$r['setting_value'];
            }
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    if (isset($cache[$key]) && $cache[$key] !== '') return $cache[$key];

    $env = getenv(strtoupper($key));
    if ($env !== false && $env !== '') return $env;

    return $default !== '' ? $default : (OLLAMA_DEFAULTS[$key] ?? '');
}

/** Uloží nastavení (dvoukrokově, ať dotaz nezávisí na SQL dialektu) */
function setSetting(string $key, string $value): bool {
    try {
        $db  = getDB();
        $now = date('Y-m-d H:i:s');
        $find = $db->prepare('SELECT setting_key FROM app_settings WHERE setting_key = ?');
        $find->execute([$key]);
        if ($find->fetch()) {
            $db->prepare('UPDATE app_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?')
               ->execute([$value, $now, $key]);
        } else {
            $db->prepare('INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?,?,?)')
               ->execute([$key, $value, $now]);
        }
        $cache = &settingsCache();
        $cache = null;   // ať se změna projeví hned ve stejném požadavku
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Adresa Ollamy. Pouštíme se jen na http(s) — jinam se server obracet nemá.
 * Vrací prázdný řetězec, když je adresa nesmyslná.
 */
function ollamaUrl(): string {
    $url    = rtrim(getSetting('ollama_url'), '/');
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

/**
 * Zavolá Ollamu.
 *
 * @return array{ok:bool, body:array, error:string}
 */
function ollamaCall(string $path, ?array $payload = null, int $timeout = 600): array {
    $base = ollamaUrl();
    if ($base === '') return ['ok' => false, 'body' => [], 'error' => 'Adresa Ollamy není nastavená nebo není http(s).'];

    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false)  return ['ok' => false, 'body' => [], 'error' => 'Ollama neodpověděla: ' . $err];
    if ($code >= 400)    return ['ok' => false, 'body' => [], 'error' => 'Ollama vrátila chybu ' . $code . ': ' . mb_substr((string)$raw, 0, 200)];

    $body = json_decode((string)$raw, true);
    if (!is_array($body)) return ['ok' => false, 'body' => [], 'error' => 'Odpověď Ollamy nešla přečíst.'];

    return ['ok' => true, 'body' => $body, 'error' => ''];
}

/**
 * Seznam stažených modelů. Slouží zároveň jako test spojení.
 *
 * @return array{ok:bool, models:array<string>, error:string}
 */
function ollamaModels(): array {
    $r = ollamaCall('/api/tags', null, 15);
    if (!$r['ok']) return ['ok' => false, 'models' => [], 'error' => $r['error']];

    $models = [];
    foreach ($r['body']['models'] ?? [] as $m) {
        if (!empty($m['name'])) $models[] = (string)$m['name'];
    }
    sort($models);
    return ['ok' => true, 'models' => $models, 'error' => $models ? '' : 'Ollama běží, ale nemá stažený žádný model.'];
}

/** Co se říká modelu při přepisu stránky */
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

/**
 * Přepíše jednu stránku vision modelem.
 *
 * @param string $imageB64 obrázek v base64 (bez „data:" prefixu)
 * @return array{ok:bool, text:string, error:string}
 */
function ollamaOcrPage(string $imageB64): array {
    $model = getSetting('ollama_vision_model');
    if ($model === '') return ['ok' => false, 'text' => '', 'error' => 'Není vybraný model pro čtení obrázků.'];

    $r = ollamaCall('/api/generate', [
        'model'   => $model,
        'prompt'  => ocrPrompt(),
        'images'  => [$imageB64],
        'stream'  => false,
        'options' => ['temperature' => 0],   // přepis, ne tvorba — chceme nudnou přesnost
    ]);
    if (!$r['ok']) return ['ok' => false, 'text' => '', 'error' => $r['error']];

    $text = trim((string)($r['body']['response'] ?? ''));
    return $text === ''
        ? ['ok' => false, 'text' => '', 'error' => 'Model vrátil prázdný přepis.']
        : ['ok' => true,  'text' => $text, 'error' => ''];
}

/**
 * Sestaví z přepsaného textu JSON sady.
 *
 * Schéma modelu diktujeme co nejstručněji a necháme ho vrátit rovnou JSON;
 * ověřuje se pak stejným validátorem jako ručně vložená sada, takže i když
 * model něco zkomolí, do databáze se to nedostane.
 *
 * @return array{ok:bool, json:string, error:string}
 */
function ollamaBuildSet(string $text, array $meta): array {
    $model = getSetting('ollama_text_model');
    if ($model === '') return ['ok' => false, 'json' => '', 'error' => 'Není vybraný model pro sestavení sady.'];

    $kind = $meta['kind'] ?? 'dvojice';
    $shape = match ($kind) {
        'vyber'       => '{"otazka": "…", "odpoved": "…", "moznosti": ["…", "…", "…"]}',
        'doplnovacka' => '{"veta": "věta s _ místo vynechaného slova", "odpoved": "…", "moznosti": ["…", "…"]}',
        'cteni'       => '{"otazka": "…", "odpoved": "…", "moznosti": ["…", "…"]}',
        default       => '{"a": "zadání", "b": "odpověď"}',
    };

    $prompt = "Z následujícího textu z učebnice vytvoř sadu na procvičování.\n\n"
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

    $r = ollamaCall('/api/generate', [
        'model'   => $model,
        'prompt'  => $prompt,
        'format'  => 'json',
        'stream'  => false,
        'options' => ['temperature' => 0],
    ]);
    if (!$r['ok']) return ['ok' => false, 'json' => '', 'error' => $r['error']];

    $json = trim((string)($r['body']['response'] ?? ''));
    if ($json === '') return ['ok' => false, 'json' => '', 'error' => 'Model vrátil prázdnou odpověď.'];

    // Některé modely JSON i přes format:json zabalí do ```json bloku
    if (preg_match('/\{.*\}/s', $json, $m)) $json = $m[0];

    $pretty = json_decode($json, true);
    if (is_array($pretty)) $json = json_encode($pretty, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return ['ok' => true, 'json' => $json, 'error' => ''];
}
