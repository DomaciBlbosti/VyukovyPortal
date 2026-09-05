<?php
/**
 * Ollama — model běžící u tebe doma.
 *
 * Tenhle soubor umí jen mluvit s Ollamou. Co se modelu říká, sestavuje
 * includes/llm.php, aby se stejné zadání dalo poslat i komerčnímu API.
 */
require_once __DIR__ . '/settings.php';

const OLLAMA_DEFAULT_URL = 'http://ollama:11434';

/**
 * Adresa Ollamy. Pouštíme se jen na http(s) — jinam se server obracet nemá.
 * Vrací prázdný řetězec, když je adresa nesmyslná.
 */
function ollamaUrl(): string {
    $url    = rtrim(getSetting('ollama_url', OLLAMA_DEFAULT_URL), '/');
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
function ollamaContextSize(): int {
    return max(2048, min(131072, (int)getSetting('ollama_num_ctx', '8192')));
}

/**
 * Pošle Ollamě zadání a vrátí odpověď jako text.
 *
 * @param ?string $imageB64 obrázek v base64 (bez „data:" prefixu), když jde o čtení stránky
 * @param bool    $wantJson vynutit JSON na výstupu
 * @return array{ok:bool, text:string, error:string}
 */
function ollamaGenerate(string $model, string $prompt, ?string $imageB64 = null, bool $wantJson = false): array {
    if ($model === '') return ['ok' => false, 'text' => '', 'error' => 'Není vybraný model pro Ollamu.'];

    $payload = [
        'model'   => $model,
        'prompt'  => $prompt,
        'stream'  => false,
        // přepis ani skládání sady není tvorba — chceme nudnou přesnost
        'options' => ['temperature' => 0, 'num_ctx' => ollamaContextSize()],
    ];
    if ($imageB64 !== null) $payload['images'] = [$imageB64];
    if ($wantJson)          $payload['format'] = 'json';

    $r = ollamaCall('/api/generate', $payload);
    if (!$r['ok']) return ['ok' => false, 'text' => '', 'error' => $r['error']];

    $text = trim((string)($r['body']['response'] ?? ''));
    return $text === ''
        ? ['ok' => false, 'text' => '', 'error' => 'Model vrátil prázdnou odpověď.']
        : ['ok' => true,  'text' => $text, 'error' => ''];
}
