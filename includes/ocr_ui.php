<?php
/**
 * Kousky rozhraní společné pro stránky skenování — záložky, výběr modelu
 * a zadání. Samotná logika je v includes/ocr.php a includes/llm.php.
 */
require_once __DIR__ . '/ocr.php';

/** Záložky mezi čtyřmi částmi skenování */
function ocrNav(string $active): void {
    $tabs = [
        'galerie' => ['🖼️ Galerie',          'Nahrát, přesunout, smazat fotky'],
        'ocr'     => ['🔍 Přepis (OCR)',      'Pustit model na vybrané fotky'],
        'tvorba'  => ['🧩 Tvorba sad',        'Z přepisu složit sadu otázek'],
        'modely'  => ['⚙️ Modely a zadání',   'Ollama, API, prompty'],
    ]; ?>
    <nav class="ocr-tabs">
        <?php foreach ($tabs as $key => [$label, $hint]): ?>
        <a href="<?= BASE_URL ?>/admin/<?= $key ?>.php" class="ocr-tab <?= $key === $active ? 'active' : '' ?>"
           title="<?= htmlspecialchars($hint) ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
<?php }

/**
 * Dostupné modely obou poskytovatelů. Ptáme se jen tam, kde je co nastavené —
 * jinak by se stránka zbytečně zdržovala čekáním na spojení, které nemůže vyjít.
 *
 * @return array{ollama:array, openai:array}
 */
function ocrProbes(): array {
    static $probes = null;
    if ($probes === null) {
        $probes = [
            'ollama' => ollamaUrl() !== '' ? ollamaModels()
                : ['ok' => false, 'models' => [], 'error' => 'Adresa Ollamy není nastavená.'],
            'openai' => openaiConfigured() ? openaiModels()
                : ['ok' => false, 'models' => [], 'error' => 'Adresa nebo klíč nejsou vyplněné.'],
        ];
    }
    return $probes;
}

/** Rozbalovací seznam modelů, nebo textové pole, když se seznam nepodařilo načíst */
function modelPicker(string $name, string $current, array $models, string $placeholder): void { ?>
    <?php if ($models): ?>
    <select id="<?= $name ?>" name="<?= $name ?>" class="form-input">
        <option value="">— vyber —</option>
        <?php foreach ($models as $m): ?>
        <option value="<?= htmlspecialchars($m) ?>" <?= $m === $current ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
        <?php endforeach; ?>
    </select>
    <?php else: ?>
    <input type="text" id="<?= $name ?>" name="<?= $name ?>" class="form-input"
           value="<?= htmlspecialchars($current) ?>" placeholder="<?= htmlspecialchars($placeholder) ?>">
    <?php endif; ?>
<?php }

/**
 * Jeden seznam přes oba poskytovatele. Hodnota je „poskytovatel|model",
 * protože názvy modelů samy obsahují dvojtečky (gemma4:12b).
 */
function providerModelPicker(string $name, string $provider, string $model): void {
    $probes  = ocrProbes();
    $current = $provider . '|' . $model; ?>
    <select id="<?= $name ?>" name="<?= $name ?>" class="form-input">
        <?php foreach (['ollama' => '🏠 Ollama', 'openai' => '☁️ Komerční API'] as $p => $label): ?>
        <optgroup label="<?= $label ?>">
            <?php
            $models = $probes[$p]['models'];
            // Nastavený model nabídneme i když ho seznam nezná (třeba když API modely nevypisuje)
            $def = llmModel($p, 'vision');
            if ($def !== '' && !in_array($def, $models, true)) array_unshift($models, $def);
            if ($p === $provider && $model !== '' && !in_array($model, $models, true)) array_unshift($models, $model);
            ?>
            <?php if (!$models): ?>
            <option disabled><?= htmlspecialchars($probes[$p]['error'] ?: 'žádný model') ?></option>
            <?php endif; ?>
            <?php foreach ($models as $m): $v = $p . '|' . $m; ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= $v === $current ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
            <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
    </select>
<?php }

/** Rozloží hodnotu z providerModelPicker na poskytovatele a model */
function splitModelPick(string $value): array {
    [$provider, $model] = array_pad(explode('|', $value, 2), 2, '');
    return [llmProvider($provider), trim($model)];
}

/** Výběr zadání: preset plus editovatelný text, který se posílá doslova */
function promptPicker(string $currentKey, string $currentText = ''): void {
    $text = $currentText !== '' ? $currentText : ocrPromptText($currentKey); ?>
    <div class="form-group">
        <label for="prompt_key">Zadání pro model</label>
        <select id="prompt_key" name="prompt_key" class="form-input">
            <?php foreach (OCR_PROMPTS as $key => $p): ?>
            <option value="<?= $key ?>" <?= $key === $currentKey ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="mistake-hint" id="prompt_note"><?= htmlspecialchars(OCR_PROMPTS[$currentKey]['note'] ?? '') ?></p>
    </div>
    <div class="form-group">
        <label for="prompt_text">Přesné znění (posílá se doslova, dá se upravit)</label>
        <textarea id="prompt_text" name="prompt_text" rows="3" class="form-input"
                  style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars($text) ?></textarea>
    </div>
<?php }

/** Data presetů pro JavaScript (přepínání textu při změně výběru) */
function promptPresetsJson(): string {
    $out = [];
    foreach (OCR_PROMPTS as $key => $p) {
        $out[$key] = ['prompt' => $key === 'custom' ? trim(getSetting('ocr_custom_prompt')) : $p['prompt'],
                      'note'   => $p['note']];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}

/** Náhled stránky do tabulek a mřížek */
function pageThumb(array $page, string $class = 'ocr-thumb'): string {
    $src = !empty($page['thumb_b64'])
        ? 'data:image/jpeg;base64,' . $page['thumb_b64']
        : BASE_URL . '/admin/galerie.php?image=' . (int)$page['id'];
    return '<img src="' . htmlspecialchars($src) . '" class="' . $class . '" alt="" loading="lazy">';
}

/** Popisek zadání v historii běhů */
function promptLabel(array $run): string {
    $key = (string)$run['prompt_key'];
    if ($key === 'legacy') return 'původní zadání';
    if (isset(OCR_PROMPTS[$key]) && $key !== 'custom') return OCR_PROMPTS[$key]['label'];
    return 'vlastní: ' . mb_substr((string)$run['prompt'], 0, 40) . (mb_strlen((string)$run['prompt']) > 40 ? '…' : '');
}
