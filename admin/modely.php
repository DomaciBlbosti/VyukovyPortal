<?php
/**
 * Nastavení modelů a zadání.
 *
 * Kdo čte (Ollama doma, nebo komerční API), které modely, jak velký kontext
 * a jaké zadání se posílá s fotkou stránky. K zadání je tu i sada presetů
 * k vyzkoušení — specializované OCR modely chtějí úplně jiné než obecné.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr_ui.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settings') {
    setSetting('llm_provider',        (string)($_POST['provider'] ?? 'ollama'));
    setSetting('ollama_url',          trim((string)($_POST['ollama_url'] ?? '')));
    setSetting('ollama_vision_model', trim((string)($_POST['vision_model'] ?? '')));
    setSetting('ollama_text_model',   trim((string)($_POST['text_model'] ?? '')));
    setSetting('ollama_num_ctx',      (string)max(2048, (int)($_POST['num_ctx'] ?? 8192)));
    setSetting('openai_url',          trim((string)($_POST['openai_url'] ?? '')));
    setSetting('openai_vision_model', trim((string)($_POST['openai_vision_model'] ?? '')));
    setSetting('openai_text_model',   trim((string)($_POST['openai_text_model'] ?? '')));

    $key = (string)($_POST['prompt_key'] ?? 'vlm_cs');
    setSetting('ocr_prompt_key', isset(OCR_PROMPTS[$key]) ? $key : 'vlm_cs');
    // Upravený text presetu si uložíme jako vlastní zadání, ať se neztratí
    $text = trim((string)($_POST['prompt_text'] ?? ''));
    if ($key === 'custom' || ($text !== '' && isset(OCR_PROMPTS[$key]) && $text !== OCR_PROMPTS[$key]['prompt'])) {
        setSetting('ocr_custom_prompt', $text);
        if ($text !== '') setSetting('ocr_prompt_key', 'custom');
    }

    // Klíč přepisujeme jen když uživatel opravdu něco vyplnil — do
    // formuláře se nikdy nevypisuje, takže prázdné pole znamená
    // „nech ho být", ne „smaž ho". Na smazání je zvlášť zaškrtávátko.
    $apiKey = trim((string)($_POST['openai_key'] ?? ''));
    if (!empty($_POST['clear_key']))  setSetting('openai_key', '');
    elseif ($apiKey !== '')           setSetting('openai_key', $apiKey);

    $message = 'Nastavení uloženo.';
}

$provider    = llmProvider();
$probes      = ocrProbes();
$probeOllama = $probes['ollama'];
$probeOpenai = $probes['openai'];
$activeProbe = $probes[$provider];

$pageTitle = 'Modely a zadání';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>⚙️ Modely a <span class="accent">zadání</span></h1>
    <p class="page-subtitle">Kdo čte stránky, jakým modelem a s jakým zadáním</p>
</div>
<?php ocrNav('modely'); ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<section class="admin-card">
    <?php if ($activeProbe['ok']): ?>
        <div class="alert alert-success">✔ <?= htmlspecialchars(LLM_PROVIDERS[$provider]) ?> odpovídá,
            dostupných modelů: <?= count($activeProbe['models']) ?>.</div>
    <?php else: ?>
        <div class="alert alert-error">✘ <?= htmlspecialchars(LLM_PROVIDERS[$provider]) ?>: <?= htmlspecialchars($activeProbe['error']) ?></div>
    <?php endif; ?>

    <?php $textModel = llmModel($provider, 'text'); if (preg_match('/ocr/i', $textModel)): ?>
    <div class="alert alert-error">
        Na sestavení sady je nastavený <strong><?= htmlspecialchars($textModel) ?></strong>. To je specializovaný OCR model —
        umí jen přepsat obrázek, JSON sady z textu nesloží. Do pole <em>Model na sestavení sady</em> dej obecný model
        (gemma4:12b, qwen3:14b).
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="settings">

        <div class="form-group">
            <label>Výchozí poskytovatel</label>
            <?php foreach (LLM_PROVIDERS as $key => $label): ?>
            <label style="display:flex;align-items:center;gap:.5rem;margin:.35rem 0;font-weight:normal">
                <input type="radio" name="provider" value="<?= $key ?>" <?= $provider === $key ? 'checked' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </label>
            <?php endforeach; ?>
            <p class="mistake-hint">U každého spuštění se dá zvolit jinak.</p>
        </div>

        <h3 class="section-title" style="font-size:1rem;margin-top:1.5rem">Ollama</h3>
        <div class="form-group">
            <label for="ollama_url">Adresa</label>
            <input type="text" id="ollama_url" name="ollama_url" class="form-input"
                   value="<?= htmlspecialchars(getSetting('ollama_url', OLLAMA_DEFAULT_URL)) ?>" placeholder="http://ollama:11434">
            <p class="mistake-hint">
                <?= $probeOllama['ok'] ? '✔ odpovídá, modelů: ' . count($probeOllama['models'])
                                       : '✘ ' . htmlspecialchars($probeOllama['error']) ?>
            </p>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="vision_model">Výchozí model na čtení obrázků</label>
                <?php modelPicker('vision_model', getSetting('ollama_vision_model'), $probeOllama['models'], 'např. deepseek-ocr'); ?>
            </div>
            <div class="form-group">
                <label for="text_model">Model na sestavení sady</label>
                <?php modelPicker('text_model', getSetting('ollama_text_model'), $probeOllama['models'], 'např. gemma4:12b'); ?>
                <p class="mistake-hint">Specializovaný OCR model (deepseek-ocr) sadu nesloží — sem patří obecný model.</p>
            </div>
        </div>
        <div class="form-group">
            <label for="num_ctx">Velikost kontextu (tokenů)</label>
            <input type="number" id="num_ctx" name="num_ctx" class="form-input" min="2048" step="1024"
                   value="<?= (int)ollamaContextSize() ?>" style="max-width:12rem">
            <p class="mistake-hint">
                Kolik textu model uvidí najednou. Ollama má ve výchozím stavu jen pár tisíc tokenů a co se
                nevejde, tiše zahodí. Větší kontext zabere víc paměti na kartě; na 12 GB je 8192 rozumný začátek.
            </p>
        </div>

        <h3 class="section-title" style="font-size:1rem;margin-top:1.5rem">Komerční API</h3>
        <p class="mistake-hint" style="margin-bottom:.75rem">
            Rozhraní OpenAI umí i OpenRouter, Groq a další — stačí přepsat adresu.
            <strong>Fotky učebnice tímhle odejdou z domácí sítě.</strong>
        </p>
        <div class="form-group">
            <label for="openai_url">Adresa</label>
            <input type="text" id="openai_url" name="openai_url" class="form-input"
                   value="<?= htmlspecialchars(getSetting('openai_url', OPENAI_DEFAULT_URL)) ?>">
        </div>
        <div class="form-group">
            <label for="openai_key">API klíč</label>
            <input type="password" id="openai_key" name="openai_key" class="form-input" autocomplete="off"
                   placeholder="<?= getSetting('openai_key') !== ''
                        ? 'uloženo (' . htmlspecialchars(maskedSecret(getSetting('openai_key'))) . ') — nech prázdné, když ho neměníš'
                        : 'sk-…' ?>">
            <?php if (getSetting('openai_key') !== ''): ?>
            <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;font-weight:normal">
                <input type="checkbox" name="clear_key" value="1"> smazat uložený klíč
            </label>
            <?php endif; ?>
            <p class="mistake-hint">Klíč se do stránky nikdy nevypisuje celý.</p>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="openai_vision_model">Model na čtení obrázků</label>
                <?php modelPicker('openai_vision_model', getSetting('openai_vision_model', 'gpt-4o-mini'),
                                  $probeOpenai['models'], 'gpt-4o-mini'); ?>
            </div>
            <div class="form-group">
                <label for="openai_text_model">Model na sestavení sady</label>
                <?php modelPicker('openai_text_model', getSetting('openai_text_model', 'gpt-4o-mini'),
                                  $probeOpenai['models'], 'gpt-4o-mini'); ?>
            </div>
        </div>
        <p class="mistake-hint">
            <?= $probeOpenai['ok'] ? '✔ odpovídá, modelů: ' . count($probeOpenai['models'])
                                   : '✘ ' . htmlspecialchars($probeOpenai['error']) ?>
        </p>

        <h3 class="section-title" style="font-size:1rem;margin-top:1.5rem">Výchozí zadání pro přepis</h3>
        <p class="mistake-hint" style="margin-bottom:.75rem">
            Specializované OCR modely (deepseek-ocr, strike-ocr) chtějí jednu krátkou anglickou větu —
            na dlouhé české instrukce reagují tím, že je opakují dokola. Obecné vision modely
            (gemma, qwen2.5vl, gpt-4o-mini) naopak delší zadání potřebují.
        </p>
        <?php promptPicker(ocrDefaultPromptKey(), ocrDefaultPromptKey() === 'custom' ? getSetting('ocr_custom_prompt') : ''); ?>

        <button type="submit" class="btn-primary" style="margin-top:1rem">Uložit nastavení</button>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Sada zadání k vyzkoušení</h2>
    <p class="mistake-hint" style="margin-bottom:1rem">
        Jak to otestovat: v galerii otevři jednu typickou stránku, v záložce <strong>Přepis</strong> dej
        <em>Spustit znovu</em> postupně s každým zadáním a v historii běhů porovnej výsledky —
        délku, čas a případné varování o zacyklení. Zadání se posílá doslova; i tečka na konci hraje roli.
    </p>
    <table class="data-table">
        <thead><tr><th>Zadání</th><th>Pro modely</th><th>Přesné znění</th><th>Poznámka</th></tr></thead>
        <tbody>
        <?php foreach (OCR_PROMPTS as $key => $p): if ($key === 'custom') continue; ?>
            <tr>
                <td><?= htmlspecialchars($p['label']) ?></td>
                <td style="font-size:.8rem"><?= htmlspecialchars($p['for']) ?></td>
                <td><code style="font-size:.8rem;white-space:pre-wrap"><?= htmlspecialchars($p['prompt']) ?></code></td>
                <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($p['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="mistake-hint" style="margin-top:1rem">
        Co aplikace dělá sama: obrázek posílá zvlášť (Ollama si ho do zadání vloží), uvažování vypíná,
        teplotu drží na nule, u Ollamy si předem ověří, že model umí obrázky, a z výstupu odstraní
        souřadnicové značky <code>&lt;|ref|&gt;</code>/<code>&lt;|det|&gt;</code>.
    </p>
</section>

<script>
const OCR_PROMPTS = <?= promptPresetsJson() ?>;
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
