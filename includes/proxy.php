<?php
/**
 * Ollama Proxy — jediná cesta k modelům.
 *
 * Aplikace mluví vždycky s jedním serverem a modely si nevybírá podle toho,
 * kde běží: lokální model na domácí kartě i komerční API jsou jen jiné názvy
 * v seznamu. O klíče k poskytovatelům a přeposlání požadavku se stará proxy,
 * aplikace jí posílá jen svůj klíč (`Authorization: Bearer opx_…`).
 *
 * Co se modelu říká, sestavuje includes/llm.php.
 */
require_once __DIR__ . '/settings.php';

/** Kde proxy poslouchá, když není nastaveno jinak */
const PROXY_DEFAULT_URL = 'http://ollama:11434';

/**
 * Strop na délku odpovědi.
 *
 * Model, který se zacyklí, jinak mele tak dlouho, dokud nevyčerpá celý
 * kontext — a to jsou u velkého modelu na domácí kartě klidně čtyři minuty
 * čekání na nic. Přepis stránky ani sada tolik textu nepotřebují.
 */
const PROXY_MAX_TOKENS = 4096;

/**
 * Adresa proxy. Pouštíme se jen na http(s) — jinam se server obracet nemá.
 * Vrací prázdný řetězec, když je adresa nesmyslná.
 */
function proxyUrl(): string {
    $url    = rtrim(getSetting('proxy_url', PROXY_DEFAULT_URL), '/');
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

/** Klíč k proxy (opx_…). Prázdný, dokud ho admin nevyplní. */
function proxyKey(): string {
    return trim(getSetting('proxy_key'));
}

/**
 * Zavolá proxy.
 *
 * @return array{ok:bool, body:array, error:string, code:int}
 */
function proxyCall(string $path, ?array $payload = null, int $timeout = 600): array {
    $base = proxyUrl();
    if ($base === '') return ['ok' => false, 'body' => [], 'error' => 'Adresa proxy není nastavená nebo není http(s).', 'code' => 0];

    $headers = ['Content-Type: application/json'];
    $key     = proxyKey();
    if ($key !== '') $headers[] = 'Authorization: Bearer ' . $key;

    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) return ['ok' => false, 'body' => [], 'error' => 'Proxy neodpověděla: ' . $err, 'code' => 0];

    if ($code === 401 || $code === 403) {
        return ['ok' => false, 'body' => [], 'code' => $code,
                'error' => $key === ''
                    ? 'Proxy chce API klíč — vyplň ho v nastavení (klíč začíná „opx_").'
                    : 'Proxy klíč odmítla (' . $code . '). Zkontroluj ho v nastavení, nebo mu v proxy povol tenhle model.'];
    }
    if ($code >= 400) return ['ok' => false, 'body' => [], 'error' => 'Proxy vrátila chybu ' . $code . ': ' . mb_substr((string)$raw, 0, 200), 'code' => $code];

    $body = json_decode((string)$raw, true);
    if (!is_array($body)) return ['ok' => false, 'body' => [], 'error' => 'Odpověď proxy nešla přečíst.', 'code' => $code];

    return ['ok' => true, 'body' => $body, 'error' => '', 'code' => $code];
}

/**
 * Seznam modelů. Slouží zároveň jako test spojení.
 *
 * Ptáme se obou seznamů a slučujeme je. /api/tags vrací prostý výpis lokální
 * Ollamy a je jistota — správcovský /mgmt/v1/models přidá modely komerčních
 * poskytovatelů, ale jeho tvar se může měnit. Kdyby ho nešlo přečíst, zůstane
 * aspoň to, co běží doma, a picker nezůstane prázdný.
 *
 * V `raw` je syrová odpověď obou volání kvůli ladění v adminu.
 *
 * @return array{ok:bool, models:array<int, array{name:string, provider:string, local:bool}>, error:string, full:bool, raw:array}
 */
function proxyModels(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    // Lokální modely bereme vždycky z /api/tags — ten klíč nepotřebuje a vrací
    // prostý seznam. Kdyby se /mgmt/v1/models nepodařilo přečíst, picker přesto
    // nabídne to, co běží doma.
    $tags   = proxyCall('/api/tags', null, 15);
    $models = $tags['ok'] ? parseProxyModels($tags['body']) : [];
    $error  = $tags['ok'] ? '' : $tags['error'];
    $full   = false;
    $raw    = ['/api/tags' => $tags['ok'] ? $tags['body'] : $tags['error']];

    // S klíčem přidáme i modely zapnutých poskytovatelů
    if (proxyKey() !== '') {
        $mgmt = proxyCall('/mgmt/v1/models', null, 20);
        $raw['/mgmt/v1/models'] = $mgmt['ok'] ? $mgmt['body'] : $mgmt['error'];
        if ($mgmt['ok']) {
            $extra = parseProxyModels($mgmt['body']);
            if ($extra) {
                $models = mergeProxyModels($models, $extra);
                $full   = true;
                $error  = '';
            }
        } elseif (!$models) {
            $error = $mgmt['error'];
        }
    }

    if ($models) $error = '';
    elseif ($error === '') $error = 'Proxy odpověděla, ale nenabídla žádný model.';

    return $cache = ['ok' => (bool)$models, 'models' => $models, 'error' => $error, 'full' => $full, 'raw' => $raw];
}

/** Sloučí dva seznamy; lokální příznak z /api/tags má přednost */
function mergeProxyModels(array $local, array $all): array {
    $out = [];
    foreach ($local as $m) $out[$m['name']] = $m;
    foreach ($all as $m) {
        if (isset($out[$m['name']])) continue;
        $out[$m['name']] = $m;
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values($out);
}

/**
 * Ze seznamu modelů vytáhne jména bez ohledu na tvar odpovědi. Proxy i Ollama
 * si každá vrací něco jiného — plochý seznam, seznam objektů, mapu jméno=>detail
 * nebo skupiny po poskytovatelích — a všechny tyhle tvary tady projdou.
 */
function parseProxyModels(array $body): array {
    $rows = $body;
    foreach (['models', 'data', 'items', 'result', 'providers'] as $key) {
        if (isset($body[$key]) && is_array($body[$key])) { $rows = $body[$key]; break; }
    }

    $models = [];
    collectProxyModels($rows, '', $models);
    ksort($models, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values($models);
}

/** Rekurzivně projde odpověď; klíč nad skupinou bereme jako poskytovatele */
function collectProxyModels($rows, string $provider, array &$models, int $depth = 0): void {
    if ($depth > 4 || !is_array($rows)) return;

    foreach ($rows as $key => $row) {
        $keyName = is_string($key) ? trim($key) : '';

        if (is_string($row)) { addProxyModel($row, $provider, $models); continue; }
        if (!is_array($row)) continue;

        // Prostý seznam nikdy není popis modelu — je to skupina
        if (array_is_list($row)) {
            collectProxyModels($row, $keyName !== '' ? $keyName : $provider, $models, $depth + 1);
            continue;
        }

        // Vnořené seznamy modelů: záznam je poskytovatel, ne model
        $nested = array_filter($row, 'is_array');
        $group  = false;
        foreach (['models', 'data', 'items'] as $f) if (isset($nested[$f])) $group = true;

        if (!$group) {
            $name = '';
            foreach (['name', 'id', 'model'] as $f) {
                if ($name === '' && !empty($row[$f]) && is_string($row[$f])) $name = $row[$f];
            }
            if ($name !== '') {
                $p = $provider;
                foreach (['provider', 'provider_slug', 'slug', 'source', 'owned_by'] as $f) {
                    if ($p === '' && !empty($row[$f]) && is_string($row[$f])) $p = (string)$row[$f];
                }
                addProxyModel($name, $p, $models);
                continue;
            }
        }

        // Záznam bez jména: buď je jméno v klíči („gpt-4o-mini": {...}),
        // nebo je pod ním celá skupina („ollama": {...} / {"slug":"anthropic","models":[...]}).
        if (!$nested) { addProxyModel($keyName, $provider, $models); continue; }

        $p = $provider;
        foreach (['provider', 'provider_slug', 'slug', 'source', 'owned_by', 'name'] as $f) {
            if (!empty($row[$f]) && is_string($row[$f])) { $p = (string)$row[$f]; break; }
        }
        if ($p === '' && $keyName !== '') $p = $keyName;

        if ($group) {
            // Skupina má seznam vedle svých vlastních údajů — projdeme jen ten seznam
            foreach (['models', 'data', 'items'] as $f) {
                if (isset($nested[$f])) collectProxyModels($nested[$f], $p, $models, $depth + 1);
            }
            continue;
        }
        collectProxyModels($row, $p, $models, $depth + 1);
    }
}

function addProxyModel(string $name, string $provider, array &$models): void {
    $name = trim($name);
    if ($name === '' || isset($models[$name])) return;
    // „library" a „ollama" znamenají model stažený doma, ne poskytovatele
    $local = $provider === '' || in_array(strtolower($provider), ['ollama', 'local', 'library'], true);
    $models[$name] = ['name' => $name, 'provider' => $local ? '' : $provider, 'local' => $local];
}

/** Co proxy ví o modelu; prázdný řetězec u lokálního */
function modelProvider(string $model): string {
    foreach (proxyModels()['models'] as $m) {
        if ($m['name'] === $model) return $m['provider'];
    }
    return '';
}

/**
 * Běží model doma? Rozhoduje o tom, jestli má smysl hlídat velikost kontextu.
 * Když model v seznamu není, řekneme radši ano — varování nikoho nezraní.
 */
function modelIsLocal(string $model): bool {
    foreach (proxyModels()['models'] as $m) {
        if ($m['name'] === $model) return $m['local'];
    }
    return true;
}

/**
 * Velikost kontextu, se kterou se model pouští.
 *
 * Ollama má ve výchozím stavu jen pár tisíc tokenů a co se nevejde, tiše
 * zahodí — u sestavení sady z několika stránek by pak potichu chyběla
 * poslední slovíčka. Radši si ji řekneme sami.
 *
 * Větší kontext znamená víc obsazené paměti grafické karty, takže je to
 * nastavitelné: na 12 GB je 8192 rozumný začátek.
 */
function proxyContextSize(): int {
    return max(2048, min(131072, (int)getSetting('num_ctx', '8192')));
}

/**
 * Pošle modelu zadání a vrátí odpověď jako text.
 *
 * Uvažování vypínáme. Uvažovací modely posílají myšlenkový postup do pole
 * „thinking" a do „response" až závěr — a když se mezitím vyčerpá kontext,
 * dorazí prázdná odpověď po několika minutách počítání. Přepis stránky ani
 * sestavení sady uvažování nepotřebují.
 *
 * @param ?string $imageB64 obrázek v base64 (bez „data:" prefixu), když jde o čtení stránky
 * @param bool    $wantJson vynutit JSON na výstupu
 * @return array{ok:bool, text:string, error:string, tokens:int, truncated:bool}
 */
function proxyGenerate(string $model, string $prompt, ?string $imageB64 = null, bool $wantJson = false): array {
    if ($model === '') return ['ok' => false, 'text' => '', 'error' => 'Není vybraný model.', 'tokens' => 0, 'truncated' => false];

    $ctx     = proxyContextSize();
    $payload = [
        'model'   => $model,
        'prompt'  => $prompt,
        'stream'  => false,
        'think'   => false,
        // Načtení modelu do karty trvá klidně půl minuty; Ollama ho jinak po
        // pěti minutách nečinnosti uvolní a při další stránce ho načítá znovu.
        // Když bude potřebovat místo pro jiný model, vyhodí ho i tak.
        'keep_alive' => '30m',
        // přepis ani skládání sady není tvorba — chceme nudnou přesnost
        'options' => [
            'temperature' => 0,
            'num_ctx'     => $ctx,
            'num_predict' => min(PROXY_MAX_TOKENS, $ctx),
        ],
    ];
    if ($imageB64 !== null) $payload['images'] = [$imageB64];
    if ($wantJson)          $payload['format'] = 'json';

    $r = proxyCall('/api/generate', $payload);

    // Modely bez podpory uvažování odmítnou parametr „think" chybou 400.
    // Pro ty ho prostě vynecháme.
    if (!$r['ok'] && $r['code'] === 400 && stripos($r['error'], 'think') !== false) {
        unset($payload['think']);
        $r = proxyCall('/api/generate', $payload);
    }
    if (!$r['ok']) return ['ok' => false, 'text' => '', 'error' => $r['error'], 'tokens' => 0, 'truncated' => false];

    $text   = trim((string)($r['body']['response'] ?? ''));
    $tokens = (int)($r['body']['eval_count'] ?? 0);
    // „length" = model narazil na num_predict; odpověď je useknutá (nebo se zacyklil)
    $cut    = (string)($r['body']['done_reason'] ?? '') === 'length';
    if ($text !== '') return ['ok' => true, 'text' => $text, 'error' => '', 'tokens' => $tokens, 'truncated' => $cut];

    return ['ok' => false, 'text' => '', 'error' => emptyAnswerReason($r['body'], $ctx, $model), 'tokens' => $tokens, 'truncated' => $cut];
}

/**
 * Co model umí — hlavně jestli vidí obrázky.
 *
 * Textový model dostane fotku stránky, mlčky ji zahodí a „přepíše" něco
 * z hlavy. Tohle se dá zjistit dopředu a ušetřit minuty čekání na nesmysl.
 * U komerčních modelů se ptát nemá koho, ty proxy jen přeposílá.
 *
 * @return array{ok:bool, vision:bool, family:string, error:string}
 */
function proxyShow(string $model): array {
    $r = proxyCall('/api/show', ['model' => $model], 20);
    if (!$r['ok']) return ['ok' => false, 'vision' => false, 'family' => '', 'error' => $r['error']];

    $caps = array_map('strval', (array)($r['body']['capabilities'] ?? []));
    return [
        'ok'     => true,
        'vision' => in_array('vision', $caps, true),
        'family' => (string)($r['body']['details']['family'] ?? ''),
        'error'  => '',
    ];
}

/**
 * Proč přišla prázdná odpověď.
 *
 * Samotné „model nic nevrátil" uživateli nepomůže — tohle rozliší vyčerpaný
 * kontext od modelu, který se upovídal v uvažování, a rovnou poradí, co s tím.
 */
function emptyAnswerReason(array $body, int $ctx, string $model = ''): string {
    $thinking = trim((string)($body['thinking'] ?? ''));
    $reason   = (string)($body['done_reason'] ?? '');
    $used     = (int)($body['prompt_eval_count'] ?? 0) + (int)($body['eval_count'] ?? 0);

    // Uvažující model si celý strop na odpověď vypotřebuje na přemýšlení a
    // k odpovědi se nedostane. Vypnout mu to jde parametrem „think", jenže
    // některé (qwen3-vl) ho ignorují — tam nezbývá než sáhnout po jiném.
    if ($thinking !== '') {
        return ($model !== '' ? 'Model ' . $model . ' spotřeboval' : 'Model spotřeboval')
             . ' celou odpověď na uvažování (' . mb_strlen($thinking) . ' znaků) a k výsledku se nedostal. '
             . 'Vypnout uvažování se u něj nedaří — vezmi model, který neuvažuje (deepseek-ocr, strike-ocr).';
    }
    if ($reason === 'length' || ($ctx > 0 && $used >= $ctx - 8)) {
        return 'Modelu došel kontext (' . $used . ' z ' . $ctx . ' tokenů) dřív, než odpověděl. '
             . 'Zvyš kontext v nastavení, nebo zkus menší model.';
    }
    return 'Model vrátil prázdnou odpověď'
         . ($reason !== '' ? ' (důvod ukončení: ' . $reason . ')' : '') . '.';
}
