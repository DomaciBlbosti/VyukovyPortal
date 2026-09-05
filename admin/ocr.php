<?php
/**
 * Naskenované stránky → sada.
 *
 * Postup má dva kroky a mezi nimi tebe:
 *   1. vision model přepíše každou stránku zvlášť
 *   2. textový model z přepisu sestaví JSON sady
 * Mezitím si přepis přečteš a můžeš ho opravit — model, který spletl slovíčko,
 * by ho jinak protáhl až do sady. U každé stránky jde otevřít detail s
 * originální fotkou vedle přepisu, ať se dá porovnávat, ne hádat.
 *
 * Výsledný JSON nejde uložit rovnou; posílá se do stejného validátoru jako
 * ručně vložená sada, takže se do databáze nedostane nic nezkontrolovaného.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr.php';
require_once __DIR__ . '/../includes/sets.php';

$user = getCurrentUser();

// ── Originální fotka stránky ──
if (($_GET['image'] ?? '') !== '') {
    $page = getOcrPage((int)$_GET['image']);
    if (!$page || !$page['image_b64']) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=3600');
    echo base64_decode((string)$page['image_b64']);
    exit;
}

// ── AJAX: prohlížeč nahrává stránky a pak si říká o jejich zpracování ──
if (($_POST['ajax'] ?? '') !== '') {
    header('Content-Type: application/json');
    set_time_limit(900);   // vision model si na jednu stránku klidně vezme minuty

    switch ($_POST['ajax']) {
        case 'upload':
            $jobId = (int)($_POST['job_id'] ?? 0);
            if (!$jobId) {
                $jobId = createOcrJob((string)($_POST['title'] ?? ''), (string)($_POST['note'] ?? ''),
                                      (int)$user['id'], (string)($_POST['provider'] ?? ''));
                if (!$jobId) { echo json_encode(['ok' => false, 'error' => 'Dávku se nepodařilo založit.']); exit; }
            }
            $ok = addOcrPage($jobId, (string)($_POST['filename'] ?? ''), (string)($_POST['image'] ?? ''));
            echo json_encode(['ok' => $ok, 'job_id' => $jobId]);
            exit;

        case 'process':
            $r = processNextOcrPage((int)($_POST['job_id'] ?? 0));
            echo json_encode(['ok' => true] + $r);
            exit;

        case 'build':
            $jobId = (int)($_POST['job_id'] ?? 0);
            // Prázdný text neukládáme — přepsal by ruční opravy, kdyby ho
            // prohlížeč z jakéhokoli důvodu neposlal
            $edited = trim((string)($_POST['text'] ?? ''));
            if ($edited !== '') saveOcrText($jobId, $edited);

            $job = getOcrJob($jobId);
            $r   = llmBuildSet(ocrJobText($jobId), [
                'subject' => (string)($_POST['subject'] ?? 'ostatni'),
                'grade'   => (int)($_POST['grade'] ?? 0),
                'title'   => (string)($_POST['set_title'] ?? ''),
                'source'  => (string)($_POST['source'] ?? ''),
                'kind'    => (string)($_POST['kind'] ?? 'dvojice'),
            ], (string)($job['provider'] ?? ''));

            if (!$r['ok']) { echo json_encode(['ok' => false, 'error' => $r['error'], 'warning' => $r['warning']]); exit; }

            // Rovnou zkontroluj stejným validátorem jako ruční vstup, ať uživatel
            // nemusí přecházet jinam, aby zjistil, že model něco zkomolil
            $checked = parseSetPayload($r['json']);
            echo json_encode([
                'ok'      => true,
                'json'    => $r['json'],
                'errors'  => $checked['errors'],
                'count'   => count($checked['items']),
                'warning' => $r['warning'],
            ]);
            exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Neznámá akce.']);
    exit;
}

// ── Běžné formuláře ──
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'settings':
            setSetting('llm_provider',        (string)($_POST['provider'] ?? 'ollama'));
            setSetting('ollama_url',          trim((string)($_POST['ollama_url'] ?? '')));
            setSetting('ollama_vision_model', trim((string)($_POST['vision_model'] ?? '')));
            setSetting('ollama_text_model',   trim((string)($_POST['text_model'] ?? '')));
            setSetting('ollama_num_ctx',      (string)max(2048, (int)($_POST['num_ctx'] ?? 8192)));
            setSetting('openai_url',          trim((string)($_POST['openai_url'] ?? '')));
            setSetting('openai_vision_model', trim((string)($_POST['openai_vision_model'] ?? '')));
            setSetting('openai_text_model',   trim((string)($_POST['openai_text_model'] ?? '')));

            // Klíč přepisujeme jen když uživatel opravdu něco vyplnil — do
            // formuláře se nikdy nevypisuje, takže prázdné pole znamená
            // „nech ho být", ne „smaž ho". Na smazání je zvlášť zaškrtávátko.
            $key = trim((string)($_POST['openai_key'] ?? ''));
            if (!empty($_POST['clear_key']))  setSetting('openai_key', '');
            elseif ($key !== '')              setSetting('openai_key', $key);

            $message = 'Nastavení uloženo.';
            break;

        case 'delete_job':
            $message = deleteOcrJob((int)($_POST['job_id'] ?? 0))
                ? 'Dávka smazána i s fotkami.' : 'Dávku se nepodařilo smazat.';
            break;

        case 'retry_page':
            $message = retryOcrPage((int)($_POST['page_id'] ?? 0))
                ? 'Stránka půjde přepsat znovu.' : 'Stránku se nepodařilo vrátit.';
            break;

        case 'save_page_text':
            $message = saveOcrPageText((int)($_POST['page_id'] ?? 0), (string)($_POST['text'] ?? ''))
                ? 'Oprava uložena.' : 'Opravu se nepodařilo uložit.';
            break;

        case 'save_text':
            $edited = trim((string)($_POST['text'] ?? ''));
            if ($edited !== '') saveOcrText((int)($_POST['job_id'] ?? 0), $edited);
            $message = 'Text uložen.';
            break;
    }
}

pruneOcrJobs();

// ── Detail jedné stránky: originál vedle přepisu ──
$detailId = (int)($_GET['page'] ?? $_POST['page_id'] ?? 0);
$detail   = $detailId ? getOcrPage($detailId) : null;

$jobId = (int)($_GET['job'] ?? $_POST['job_id'] ?? ($detail['job_id'] ?? 0));
$job   = $jobId ? getOcrJob($jobId) : null;
$pages = $job ? ocrPages($jobId) : [];
$jobs  = listOcrJobs();

$provider = llmProvider();

// Modely se ptáme jen když je poskytovatel nastavený — jinak by se stránka
// zbytečně zdržovala čekáním na spojení, které nemůže vyjít
$canProbeOllama = ollamaUrl() !== '';
$probeOllama = $canProbeOllama ? ollamaModels()
    : ['ok' => false, 'models' => [], 'error' => 'Adresa Ollamy není nastavená.'];
$probeOpenai = openaiConfigured() ? openaiModels()
    : ['ok' => false, 'models' => [], 'error' => 'Adresa nebo klíč nejsou vyplněné.'];

$activeProbe = $provider === 'openai' ? $probeOpenai : $probeOllama;

$pageTitle = 'Skenování učebnic';
include __DIR__ . '/../includes/header.php';

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
?>

<div class="page-header">
    <h1>🔍 Skenování <span class="accent">učebnic</span></h1>
    <p class="page-subtitle">Nahraj vyfocené stránky, model je přepíše a složí z nich sadu</p>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($detail): /* ── Detail stránky ── */ ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0">
            Stránka <?= (int)$detail['position'] + 1 ?> — <?= htmlspecialchars($detail['filename']) ?>
        </h2>
        <a href="?job=<?= (int)$detail['job_id'] ?>" class="btn-secondary btn-sm">← zpět na dávku</a>
    </div>
    <p class="mistake-hint">
        Porovnej přepis s originálem a co model spletl, oprav. Oprava se propíše
        do textu celé dávky, ze kterého se pak skládá sada.
    </p>

    <div class="ocr-compare">
        <div class="ocr-original">
            <a href="?image=<?= (int)$detail['id'] ?>" target="_blank" rel="noopener">
                <img src="?image=<?= (int)$detail['id'] ?>" alt="Originální fotka stránky">
            </a>
            <p class="mistake-hint">Klepnutím se fotka otevře ve velkém.</p>
        </div>
        <div class="ocr-transcript">
            <form method="post">
                <input type="hidden" name="action" value="save_page_text">
                <input type="hidden" name="page_id" value="<?= (int)$detail['id'] ?>">
                <textarea name="text" rows="18" class="form-input"
                          style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars(pageText($detail)) ?></textarea>
                <div style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem;flex-wrap:wrap">
                    <button type="submit" class="btn-primary">Uložit opravu</button>
                    <?php if (trim((string)$detail['edited_text']) !== ''): ?>
                    <span class="mistake-hint">✎ ručně upraveno</span>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($detail['error']): ?>
            <div class="alert alert-error" style="margin-top:.75rem"><?= htmlspecialchars($detail['error']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!$job && !$detail): ?>
<section class="admin-card">
    <h2 class="section-title">Kdo bude číst</h2>
    <?php if ($activeProbe['ok']): ?>
        <div class="alert alert-success">✔ <?= htmlspecialchars(LLM_PROVIDERS[$provider]) ?> odpovídá,
            dostupných modelů: <?= count($activeProbe['models']) ?>.</div>
    <?php else: ?>
        <div class="alert alert-error">✘ <?= htmlspecialchars(LLM_PROVIDERS[$provider]) ?>: <?= htmlspecialchars($activeProbe['error']) ?></div>
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
            <p class="mistake-hint">U jednotlivé dávky se dá zvolit jinak.</p>
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
                <label for="vision_model">Model na čtení obrázků</label>
                <?php modelPicker('vision_model', getSetting('ollama_vision_model'), $probeOllama['models'], 'např. gemma3:12b'); ?>
            </div>
            <div class="form-group">
                <label for="text_model">Model na sestavení sady</label>
                <?php modelPicker('text_model', getSetting('ollama_text_model'), $probeOllama['models'], 'např. gemma3:12b'); ?>
                <p class="mistake-hint">Máš-li málo paměti na kartě, dej sem i do čtení obrázků
                   <strong>tentýž model</strong> — nebude se pak mezi kroky přenačítat.</p>
            </div>
        </div>
        <div class="form-group">
            <label for="num_ctx">Velikost kontextu (tokenů)</label>
            <input type="number" id="num_ctx" name="num_ctx" class="form-input" min="2048" step="1024"
                   value="<?= (int)ollamaContextSize() ?>" style="max-width:12rem">
            <p class="mistake-hint">
                Kolik textu model uvidí najednou. Ollama má ve výchozím stavu jen pár tisíc tokenů a co se
                nevejde, tiše zahodí — u sady z několika stránek by pak chyběla poslední slovíčka.
                Větší kontext ale zabere víc paměti na kartě; na 12 GB je 8192 rozumný začátek.
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

        <button type="submit" class="btn-primary" style="margin-top:1rem">Uložit nastavení</button>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Nová dávka</h2>
    <p class="mistake-hint" style="margin-bottom:1rem">
        Vyfoť stránky telefonem a nahraj je sem naráz. Prohlížeč je před odesláním zmenší,
        takže fotky z telefonu vadit nebudou.
    </p>
    <div class="form-row">
        <div class="form-group">
            <label for="job_title">Název dávky</label>
            <input type="text" id="job_title" class="form-input" placeholder="Project 1 — Unit 3">
        </div>
        <div class="form-group">
            <label for="job_note">Poznámka</label>
            <input type="text" id="job_note" class="form-input" placeholder="slovíčka ze strany 34–35">
        </div>
        <div class="form-group">
            <label for="job_provider">Číst přes</label>
            <select id="job_provider" class="form-input">
                <?php foreach (LLM_PROVIDERS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $provider === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="pages">Stránky</label>
        <input type="file" id="pages" class="form-input" accept="image/*" multiple>
    </div>
    <button type="button" id="uploadBtn" class="btn-primary">Nahrát a přepsat →</button>
    <div id="uploadProgress" class="mistake-hint" style="margin-top:1rem"></div>
</section>
<?php endif; ?>

<?php if ($job): ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0"><?= htmlspecialchars($job['title'] ?: 'Dávka #' . $jobId) ?></h2>
        <a href="<?= BASE_URL ?>/admin/ocr.php" class="btn-secondary btn-sm">＋ nová dávka</a>
    </div>
    <p class="mistake-hint">
        <?php if ($job['note']): ?><?= htmlspecialchars($job['note']) ?> · <?php endif; ?>
        čte přes <?= htmlspecialchars(LLM_PROVIDERS[llmProvider((string)$job['provider'])]) ?>
    </p>

    <table class="data-table" style="margin-top:1rem">
        <thead><tr><th>#</th><th>Soubor</th><th>Stav</th><th>Čas</th><th></th></tr></thead>
        <tbody id="pageRows">
        <?php foreach ($pages as $p): ?>
            <tr data-page="<?= (int)$p['id'] ?>">
                <td><?= (int)$p['position'] + 1 ?></td>
                <td style="font-size:.8rem"><?= htmlspecialchars($p['filename']) ?></td>
                <td class="page-status">
                    <?= match ($p['status']) {
                        'hotovo' => '✔ přepsáno',
                        'chyba'  => '✘ ' . htmlspecialchars($p['error']),
                        'bezi'   => '⏳ běží',
                        default  => '· čeká',
                    } ?>
                    <?php if (trim((string)$p['edited_text']) !== ''): ?><span class="mistake-hint">✎</span><?php endif; ?>
                </td>
                <td><?= match (true) {
                        (int)$p['seconds'] > 0    => (int)$p['seconds'] . ' s',
                        $p['status'] === 'hotovo' => '&lt;1 s',
                        default                   => '–',
                    } ?></td>
                <td style="display:flex;gap:.4rem">
                    <a href="?page=<?= (int)$p['id'] ?>" class="btn-secondary btn-sm">Porovnat</a>
                    <?php if ($p['status'] === 'chyba'): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="retry_page">
                        <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="job_id" value="<?= $jobId ?>">
                        <button type="submit" class="btn-secondary btn-sm">Znovu</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php $waiting = count(array_filter($pages, fn($p) => in_array($p['status'], ['ceka', 'bezi'], true))); ?>
    <div style="margin-top:1rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <button type="button" id="processBtn" class="btn-primary" <?= $waiting ? '' : 'disabled' ?>>
            <?= $waiting ? 'Přepsat zbývající (' . $waiting . ')' : 'Vše přepsáno' ?>
        </button>
        <span id="processProgress" class="mistake-hint"></span>
    </div>
</section>

<section class="admin-card">
    <h2 class="section-title">Přepsaný text — přečti a oprav</h2>
    <p class="mistake-hint" style="margin-bottom:1rem">
        Slepený text ze všech stránek. Co je tady špatně, bude špatně i v sadě.
        Jednotlivou stránku jde porovnat s originálem přes <strong>Porovnat</strong> v tabulce výš.
    </p>
    <form method="post">
        <input type="hidden" name="action" value="save_text">
        <input type="hidden" name="job_id" value="<?= $jobId ?>">
        <textarea id="ocrText" name="text" rows="14" class="form-input"
                  style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars(ocrJobText($jobId)) ?></textarea>
        <?php
        $estTokens = estimateTokens(ocrJobText($jobId));
        $ctxSize   = ollamaContextSize();
        $tooLong   = llmProvider((string)$job['provider']) === 'ollama' && $estTokens * 2 + 500 > $ctxSize;
        ?>
        <p class="mistake-hint" style="margin-top:.5rem">
            Odhadem <strong><?= $estTokens ?></strong> tokenů<?php if (llmProvider((string)$job['provider']) === 'ollama'): ?>,
            nastavený kontext je <?= $ctxSize ?><?php endif; ?>.
            <?php if ($tooLong): ?>
            <span style="color:var(--danger)">Na sestavení sady to nemusí stačit — zvyš kontext,
            dávku rozděl na míň stránek, nebo ji nech sestavit přes komerční API.</span>
            <?php endif; ?>
        </p>
        <button type="submit" class="btn-secondary" style="margin-top:.75rem">Uložit text</button>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Sestavit sadu</h2>
    <div class="form-row">
        <div class="form-group">
            <label for="set_title">Název sady</label>
            <input type="text" id="set_title" class="form-input" value="<?= htmlspecialchars($job['title']) ?>">
        </div>
        <div class="form-group">
            <label for="source">Zdroj</label>
            <input type="text" id="source" class="form-input" value="<?= htmlspecialchars($job['title']) ?>"
                   placeholder="Project 1, 4. vydání, Unit 3">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="subject">Předmět</label>
            <select id="subject" class="form-input">
                <?php foreach (SET_SUBJECTS as $key => $s): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($s['icon'] . ' ' . $s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="kind">Typ sady</label>
            <select id="kind" class="form-input">
                <?php foreach (SET_KINDS as $key => $label): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="grade">Ročník</label>
            <select id="grade" class="form-input">
                <option value="0">pro všechny</option>
                <?php for ($g = 1; $g <= 9; $g++): ?>
                <option value="<?= $g ?>"><?= $g ?>. třída</option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    <button type="button" id="buildBtn" class="btn-primary">Sestavit JSON →</button>
    <span id="buildProgress" class="mistake-hint" style="margin-left:.75rem"></span>

    <div id="buildWarning" class="alert alert-error" style="margin-top:1rem;display:none"></div>

    <div id="buildResult" style="margin-top:1.25rem;display:none">
        <div id="buildErrors"></div>
        <textarea id="buildJson" rows="12" class="form-input" style="font-family:monospace;font-size:.8rem"></textarea>
        <form method="post" action="<?= BASE_URL ?>/admin/sady.php" style="margin-top:.75rem">
            <input type="hidden" name="action" value="check">
            <input type="hidden" name="json" id="handoffJson">
            <button type="submit" class="btn-primary">Otevřít v importu sad →</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="admin-card">
    <h2 class="section-title">Dávky (<?= count($jobs) ?>)</h2>
    <?php if (!$jobs): ?>
    <p class="mistake-hint">Zatím žádná dávka. Staré se po <?= OCR_KEEP_DAYS ?> dnech uklidí samy.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Název</th><th>Stránek</th><th>Přepsáno</th><th>Čte přes</th><th>Založeno</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($jobs as $j): ?>
            <tr>
                <td><a href="?job=<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['title'] ?: 'Dávka #' . (int)$j['id']) ?></a></td>
                <td><?= (int)$j['page_count'] ?></td>
                <td><?= (int)$j['done_count'] ?>/<?= (int)$j['page_count'] ?></td>
                <td style="font-size:.8rem"><?= $j['provider'] === 'openai' ? '☁️ API' : '🏠 Ollama' ?></td>
                <td style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars((string)$j['created_at']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Smazat dávku i s fotkami?')">
                        <input type="hidden" name="action" value="delete_job">
                        <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                        <button type="submit" class="btn-secondary btn-sm">Smazat</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<script>
const OCR_URL    = '<?= BASE_URL ?>/admin/ocr.php';
const OCR_JOB_ID = <?= (int)$jobId ?>;
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
