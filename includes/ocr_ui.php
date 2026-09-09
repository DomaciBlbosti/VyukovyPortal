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
 * Modely, které proxy nabízí. Ptáme se jednou za požadavek.
 *
 * @return array{ok:bool, models:array, error:string, full:bool}
 */
function ocrProbe(): array {
    return proxyModels();
}

/**
 * Výběr modelu. Lokální i komerční jsou v jednom seznamu, protože se posílají
 * na stejnou adresu — liší se jen tím, kdo je provozuje.
 */
function proxyModelPicker(string $name, string $current): void {
    $probe  = ocrProbe();
    $groups = ['🏠 U tebe doma' => [], '☁️ Komerční (fotky odejdou ven)' => []];
    foreach ($probe['models'] as $m) {
        $groups[$m['local'] ? '🏠 U tebe doma' : '☁️ Komerční (fotky odejdou ven)'][] = $m;
    }
    // Nastavený model nabídneme i tehdy, když ho proxy zrovna nevypsala
    $known = array_column($probe['models'], 'name');
    ?>
    <select id="<?= $name ?>" name="<?= $name ?>" class="form-input">
        <?php if ($current !== '' && !in_array($current, $known, true)): ?>
        <option value="<?= htmlspecialchars($current) ?>" selected><?= htmlspecialchars($current) ?> (proxy ho teď nenabízí)</option>
        <?php endif; ?>
        <?php if (!$probe['models']): ?>
        <option value="">— <?= htmlspecialchars($probe['error'] ?: 'žádný model') ?> —</option>
        <?php endif; ?>
        <?php foreach ($groups as $label => $models): if (!$models) continue; ?>
        <optgroup label="<?= $label ?>">
            <?php foreach ($models as $m): ?>
            <option value="<?= htmlspecialchars($m['name']) ?>" <?= $m['name'] === $current ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['name']) ?><?= $m['provider'] !== '' ? ' · ' . htmlspecialchars($m['provider']) : '' ?>
            </option>
            <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
    </select>
<?php }

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

/** Rozbalovací seznam předmětů; prázdná hodnota = zatím nezařazeno */
function subjectSelect(string $name, string $current): void { ?>
    <select id="<?= $name ?>" name="<?= $name ?>" class="form-input">
        <option value="">— nezařazeno —</option>
        <?php foreach (SET_SUBJECTS as $key => $s): ?>
        <option value="<?= $key ?>" <?= $key === $current ? 'selected' : '' ?>><?= htmlspecialchars($s['icon'] . ' ' . $s['label']) ?></option>
        <?php endforeach; ?>
    </select>
<?php }

/** Rozbalovací seznam ročníků; 0 = pro všechny */
function gradeSelect(string $name, int $current): void { ?>
    <select id="<?= $name ?>" name="<?= $name ?>" class="form-input">
        <option value="0">pro všechny</option>
        <?php for ($g = 1; $g <= 9; $g++): ?>
        <option value="<?= $g ?>" <?= $g === $current ? 'selected' : '' ?>><?= $g ?>. třída</option>
        <?php endfor; ?>
    </select>
<?php }

/**
 * Rozdělí alba do skupin podle předmětu a ročníku.
 * Učebnice i pracovní sešit téhož předmětu tak stojí vedle sebe.
 */
function groupAlbumsBySubject(array $albums): array {
    $groups = [];
    foreach ($albums as $a) {
        $groups[subjectGradeLabel((string)$a['subject'], (int)$a['grade'])][] = $a;
    }
    // Nezařazená alba patří na konec, ať nepřekáží
    uksort($groups, fn($x, $y) => (str_starts_with($x, '📂') <=> str_starts_with($y, '📂')) ?: strcoll($x, $y));
    return $groups;
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
