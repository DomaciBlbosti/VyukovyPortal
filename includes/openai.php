<?php
/**
 * Komerční API mluvící „OpenAI dialektem" (chat completions).
 *
 * Kromě OpenAI umí totéž rozhraní i OpenRouter, Groq a další — a taky sama
 * Ollama na /v1. Stačí proto přepsat adresu a klíč, žádný další kód.
 *
 * Oproti modelu doma je přepis stránky výrazně přesnější, hlavně u české
 * diakritiky. Za to fotky učebnice opouštějí domácí síť; co si vybrat,
 * je rozhodnutí uživatele, ne tohohle souboru.
 */
require_once __DIR__ . '/settings.php';

const OPENAI_DEFAULT_URL = 'https://api.openai.com/v1';

/** Adresa API; jen http(s), jinak prázdno */
function openaiUrl(): string {
    $url    = rtrim(getSetting('openai_url', OPENAI_DEFAULT_URL), '/');
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

/** Je API vůbec nastavené? */
function openaiConfigured(): bool {
    return openaiUrl() !== '' && getSetting('openai_key') !== '';
}

/**
 * Zavolá chat completions.
 *
 * @param array $messages zprávy v OpenAI tvaru
 * @return array{ok:bool, text:string, error:string}
 */
function openaiChat(string $model, array $messages, bool $wantJson = false, int $timeout = 300): array {
    $base = openaiUrl();
    $key  = getSetting('openai_key');
    if ($base === '') return ['ok' => false, 'text' => '', 'error' => 'Adresa API není nastavená nebo není http(s).'];
    if ($key === '')  return ['ok' => false, 'text' => '', 'error' => 'Není vyplněný API klíč.'];
    if ($model === '') return ['ok' => false, 'text' => '', 'error' => 'Není vybraný model.'];

    $payload = ['model' => $model, 'messages' => $messages, 'temperature' => 0];
    if ($wantJson) $payload['response_format'] = ['type' => 'json_object'];

    $ch = curl_init($base . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) return ['ok' => false, 'text' => '', 'error' => 'API neodpovědělo: ' . $err];

    $body = json_decode((string)$raw, true);
    if ($code >= 400) {
        // Chybu radši vypíšeme z pole „message", ať uživatel vidí důvod
        // („neplatný klíč", „došel kredit") a ne kus JSONu
        $msg = $body['error']['message'] ?? mb_substr((string)$raw, 0, 200);
        return ['ok' => false, 'text' => '', 'error' => 'API vrátilo chybu ' . $code . ': ' . $msg];
    }
    if (!is_array($body)) return ['ok' => false, 'text' => '', 'error' => 'Odpověď API nešla přečíst.'];

    $text = trim((string)($body['choices'][0]['message']['content'] ?? ''));
    return $text === ''
        ? ['ok' => false, 'text' => '', 'error' => 'Model vrátil prázdnou odpověď.']
        : ['ok' => true,  'text' => $text, 'error' => ''];
}

/**
 * Zadání i s obrázkem — obrázek se posílá jako data URL.
 *
 * @return array{ok:bool, text:string, error:string}
 */
function openaiVision(string $model, string $prompt, string $imageB64): array {
    return openaiChat($model, [[
        'role'    => 'user',
        'content' => [
            ['type' => 'text',      'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageB64]],
        ],
    ]]);
}

/**
 * Test spojení — necháme si vypsat dostupné modely.
 *
 * @return array{ok:bool, models:array<string>, error:string}
 */
function openaiModels(): array {
    $base = openaiUrl();
    $key  = getSetting('openai_key');
    if ($base === '') return ['ok' => false, 'models' => [], 'error' => 'Adresa API není nastavená nebo není http(s).'];
    if ($key === '')  return ['ok' => false, 'models' => [], 'error' => 'Není vyplněný API klíč.'];

    $ch = curl_init($base . '/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) return ['ok' => false, 'models' => [], 'error' => 'API neodpovědělo: ' . $err];

    $body = json_decode((string)$raw, true);
    if ($code >= 400) {
        $msg = $body['error']['message'] ?? mb_substr((string)$raw, 0, 200);
        return ['ok' => false, 'models' => [], 'error' => 'API vrátilo chybu ' . $code . ': ' . $msg];
    }

    $models = [];
    foreach ($body['data'] ?? [] as $m) {
        if (!empty($m['id'])) $models[] = (string)$m['id'];
    }
    sort($models);
    return ['ok' => true, 'models' => $models, 'error' => ''];
}
