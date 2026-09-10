<?php
/**
 * Nastavení modelů a zadání.
 *
 * Všechno jde přes jednu adresu — Ollama Proxy. Aplikace se prokáže svým
 * klíčem a vybere model; jestli běží doma, nebo u komerčního poskytovatele,
 * řeší proxy, která k němu drží přístup. Aplikace tedy nikdy nedrží klíč
 * k OpenAI ani k jinému API, jen ten svůj.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr_ui.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settings') {
    setSetting('proxy_url',    trim((string)($_POST['proxy_url'] ?? '')));
    setSetting('vision_model', trim((string)($_POST['vision_model'] ?? '')));
    setSetting('text_model',   trim((string)($_POST['text_model'] ?? '')));
    setSetting('num_ctx',      (string)max(2048, (int)($_POST['num_ctx'] ?? 8192)));

    $key = (string)($_POST['prompt_key'] ?? 'vlm_cs');
    setSetting('ocr_prompt_key', isset(OCR_PROMPTS[$key]) ? $key : 'vlm_cs');
    // Upravený text presetu si uložíme jako vlastní zadání, ať se neztratí.
    // Prohlížeč posílá z textarea konce řádků jako CRLF — jinak by se
    // dvouřádkové zadání nikdy nerovnalo presetu.
    $text = str_replace("\r\n", "\n", trim((string)($_POST['prompt_text'] ?? '')));
    if ($key === 'custom' || ($text !== '' && isset(OCR_PROMPTS[$key]) && $text !== OCR_PROMPTS[$key]['prompt'])) {
        setSetting('ocr_custom_prompt', $text);
        if ($text !== '') setSetting('ocr_prompt_key', 'custom');
    }

    // Klíč přepisujeme jen když uživatel opravdu něco vyplnil — do formuláře
    // se nikdy nevypisuje, takže prázdné pole znamená „nech ho být", ne
    // „smaž ho". Na smazání je zvlášť zaškrtávátko.
    $proxyKey = trim((string)($_POST['proxy_key'] ?? ''));
    if (!empty($_POST['clear_key'])) setSetting('proxy_key', '');
    elseif ($proxyKey !== '')        setSetting('proxy_key', $proxyKey);

    $message = 'Nastavení uloženo.';
}

$probe  = ocrProbe();
$local  = count(array_filter($probe['models'], fn($m) => $m['local']));
$remote = count($probe['models']) - $local;

$pageTitle = 'Modely a zadání';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>⚙️ Modely a <span class="accent">zadání</span></h1>
    <p class="page-subtitle">Jedna adresa, jeden klíč — model rozhoduje, kdo stránku přečte</p>
</div>
<?php ocrNav('modely'); ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<section class="admin-card">
    <?php if ($probe['ok']): ?>
        <div class="alert alert-success">
            ✔ Proxy odpovídá — modelů <?= count($probe['models']) ?>
            (<?= $local ?> u tebe doma<?= $remote ? ', ' . $remote . ' komerčních' : '' ?>).
            <?php if (!$probe['full'] && proxyKey() === ''): ?>
            <br><span class="mistake-hint">Bez platného klíče vidíš jen lokální modely. Komerční se objeví, až klíč vyplníš.</span>
            <?php elseif (!$probe['full']): ?>
            <br><span class="mistake-hint">Správcovský seznam proxy se nepodařilo přečíst, tak je tu jen to, co běží doma.
            Co proxy odpověděla, se dá rozbalit níž.</span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-error">✘ <?= htmlspecialchars($probe['error']) ?></div>
    <?php endif; ?>

    <?php if (!empty($probe['raw'])): ?>
    <details style="margin:.4rem 0 1rem">
        <summary class="mistake-hint" style="cursor:pointer">Co proxy odpověděla (když v seznamu chybí model)</summary>
        <?php foreach ($probe['raw'] as $path => $body): ?>
        <p class="mistake-hint" style="margin:.6rem 0 .2rem"><code><?= htmlspecialchars($path) ?></code></p>
        <?php
            $dump = is_array($body) ? (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : (string)$body;
            // kdyby proxy někdy vracela i klíče, do stránky se nedostanou
            $dump = preg_replace('/\b(opx_|sk-|sk_)[A-Za-z0-9_\-]{6,}/', '$1…', $dump);
        ?>
        <pre style="max-height:14rem;overflow:auto;font-size:.75rem;white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars(mb_substr($dump, 0, 4000)) ?></pre>
        <?php endforeach; ?>
    </details>
    <?php endif; ?>

    <?php
        $known   = array_column($probe['models'], 'name');
        $missing = array_values(array_filter([llmModel('vision'), llmModel('text')],
                                 fn($m) => $m !== '' && $known && !in_array($m, $known, true)));
    ?>
    <?php if ($missing): ?>
    <div class="alert alert-error">
        Proxy nezná <?= count($missing) > 1 ? 'nastavené modely' : 'nastavený model' ?>
        <strong><?= htmlspecialchars(implode(', ', array_unique($missing))) ?></strong> —
        přepis i sada na něm spadnou na <code>model not found</code>. Vyber níž model ze seznamu a ulož.
    </div>
    <?php endif; ?>

    <?php $textModel = llmModel('text'); if (preg_match('/ocr/i', $textModel)): ?>
    <div class="alert alert-error">
        Na sestavení sady je nastavený <strong><?= htmlspecialchars($textModel) ?></strong>. To je specializovaný OCR model —
        umí jen přepsat obrázek, JSON sady z textu nesloží. Do pole <em>Model na sestavení sady</em> dej obecný model.
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="settings">

        <h3 class="section-title" style="font-size:1rem">Připojení</h3>
        <div class="form-group">
            <label for="proxy_url">Adresa proxy</label>
            <input type="text" id="proxy_url" name="proxy_url" class="form-input"
                   value="<?= htmlspecialchars(getSetting('proxy_url', PROXY_DEFAULT_URL)) ?>" placeholder="http://192.168.1.10:11435">
            <p class="mistake-hint">
                Sem chodí všechno — přepis stránek i skládání sad. Komerční modely se posílají na tutéž adresu,
                proxy je pozná podle názvu a klíč k poskytovateli přidá sama.
            </p>
        </div>
        <div class="form-group">
            <label for="proxy_key">API klíč proxy</label>
            <input type="password" id="proxy_key" name="proxy_key" class="form-input" autocomplete="off"
                   placeholder="<?= getSetting('proxy_key') !== ''
                        ? 'uloženo (' . htmlspecialchars(maskedSecret(getSetting('proxy_key'))) . ') — nech prázdné, když ho neměníš'
                        : 'opx_…' ?>">
            <?php if (getSetting('proxy_key') !== ''): ?>
            <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;font-weight:normal">
                <input type="checkbox" name="clear_key" value="1"> smazat uložený klíč
            </label>
            <?php endif; ?>
            <p class="mistake-hint">
                Posílá se jako <code>Authorization: Bearer opx_…</code>. Vytvoříš ho ve správě proxy
                a nastavíš mu tam, které modely smí. Do stránky se nikdy nevypisuje celý.
            </p>
        </div>

        <h3 class="section-title" style="font-size:1rem;margin-top:1.5rem">Modely</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="vision_model">Model na čtení obrázků</label>
                <?php proxyModelPicker('vision_model', getSetting('vision_model')); ?>
                <p class="mistake-hint">Musí umět obrázky. Aplikace to u lokálních modelů ověří dřív, než pošle fotku.</p>
            </div>
            <div class="form-group">
                <label for="text_model">Model na sestavení sady</label>
                <?php proxyModelPicker('text_model', getSetting('text_model')); ?>
                <p class="mistake-hint">
                    Obecný model, ne OCR. Máš-li málo paměti na kartě, dej sem i do čtení obrázků
                    <strong>tentýž lokální model</strong> — nebude se pak mezi kroky přenačítat.
                </p>
            </div>
        </div>
        <div class="form-group">
            <label for="num_ctx">Velikost kontextu (tokenů)</label>
            <input type="number" id="num_ctx" name="num_ctx" class="form-input" min="2048" step="1024"
                   value="<?= (int)proxyContextSize() ?>" style="max-width:12rem">
            <p class="mistake-hint">
                Týká se modelů běžících doma. Ollama má ve výchozím stavu jen pár tisíc tokenů a co se
                nevejde, tiše zahodí. Větší kontext zabere víc paměti na kartě; na 12 GB je 8192 rozumný začátek.
                Komerční modely mají okno tak velké, že se na to nedá narazit.
            </p>
        </div>

        <h3 class="section-title" style="font-size:1rem;margin-top:1.5rem">Výchozí zadání pro přepis</h3>
        <p class="mistake-hint" style="margin-bottom:.75rem">
            Specializované OCR modely (deepseek-ocr, strike-ocr) chtějí jednu krátkou anglickou větu —
            na dlouhé české instrukce reagují tím, že je opakují dokola. Obecné vision modely
            (gemma, qwen3-vl, gpt-4o-mini) naopak delší zadání potřebují.
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
        Co aplikace dělá sama: obrázek posílá zvlášť, uvažování vypíná, teplotu drží na nule,
        u lokálních modelů si předem ověří, že model umí obrázky, a z výstupu odstraní
        souřadnicové značky <code>&lt;|ref|&gt;</code>/<code>&lt;|det|&gt;</code>.
    </p>
</section>

<script>
const OCR_PROMPTS = <?= promptPresetsJson() ?>;
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
