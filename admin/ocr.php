<?php
/**
 * Naskenované stránky → sada, přes Ollamu běžící u tebe doma.
 *
 * Postup má dva kroky a mezi nimi tebe:
 *   1. vision model přepíše každou stránku zvlášť
 *   2. textový model z přepisu sestaví JSON sady
 * Mezitím si přepis přečteš a můžeš ho opravit — model, který spletl slovíčko,
 * by ho jinak protáhl až do sady.
 *
 * Výsledný JSON nejde uložit rovnou; posílá se do stejného validátoru jako
 * ručně vložená sada, takže se do databáze nedostane nic nezkontrolovaného.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr.php';
require_once __DIR__ . '/../includes/sets.php';

$user = getCurrentUser();

// ── AJAX: prohlížeč nahrává stránky a pak si říká o jejich zpracování ──
if (($_POST['ajax'] ?? '') !== '') {
    header('Content-Type: application/json');
    set_time_limit(900);   // vision model si na jednu stránku klidně vezme minuty

    switch ($_POST['ajax']) {
        case 'upload':
            $jobId = (int)($_POST['job_id'] ?? 0);
            if (!$jobId) {
                $jobId = createOcrJob((string)($_POST['title'] ?? ''), (string)($_POST['note'] ?? ''), (int)$user['id']);
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
            $r = ollamaBuildSet(ocrJobText($jobId), [
                'subject' => (string)($_POST['subject'] ?? 'ostatni'),
                'grade'   => (int)($_POST['grade'] ?? 0),
                'title'   => (string)($_POST['set_title'] ?? ''),
                'source'  => (string)($_POST['source'] ?? ''),
                'kind'    => (string)($_POST['kind'] ?? 'dvojice'),
            ]);
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
            setSetting('ollama_url',          trim((string)($_POST['ollama_url'] ?? '')));
            setSetting('ollama_vision_model', trim((string)($_POST['vision_model'] ?? '')));
            setSetting('ollama_text_model',   trim((string)($_POST['text_model'] ?? '')));
            setSetting('ollama_num_ctx',      (string)max(2048, (int)($_POST['num_ctx'] ?? 8192)));
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

        case 'save_text':
            saveOcrText((int)($_POST['job_id'] ?? 0), (string)($_POST['text'] ?? ''));
            $message = 'Text uložen.';
            break;
    }
}

pruneOcrJobs();

$jobId = (int)($_GET['job'] ?? $_POST['job_id'] ?? 0);
$job   = $jobId ? getOcrJob($jobId) : null;
$pages = $job ? ocrPages($jobId) : [];
$jobs  = listOcrJobs();

// Modely se ptáme jen když je nastavená adresa — jinak by se stránka
// zbytečně zdržovala čekáním na spojení, které nemůže vyjít
$probe = ollamaUrl() !== '' ? ollamaModels() : ['ok' => false, 'models' => [], 'error' => 'Adresa Ollamy není nastavená.'];

$pageTitle = 'Skenování učebnic';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🔍 Skenování <span class="accent">učebnic</span></h1>
    <p class="page-subtitle">Nahraj vyfocené stránky, Ollama je přepíše a složí z nich sadu</p>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="admin-card">
    <h2 class="section-title">Připojení na Ollamu</h2>
    <?php if ($probe['ok']): ?>
        <div class="alert alert-success">✔ Ollama odpovídá, stažených modelů: <?= count($probe['models']) ?>.</div>
    <?php else: ?>
        <div class="alert alert-error">✘ <?= htmlspecialchars($probe['error']) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="settings">
        <div class="form-group">
            <label for="ollama_url">Adresa Ollamy</label>
            <input type="text" id="ollama_url" name="ollama_url" class="form-input"
                   value="<?= htmlspecialchars(getSetting('ollama_url')) ?>" placeholder="http://ollama:11434">
            <p class="mistake-hint">Běží-li Ollama vedle jako aplikace na TrueNASu, bývá to <code>http://ollama:11434</code>;
               na jiném stroji v síti třeba <code>http://192.168.1.10:11434</code>.</p>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="vision_model">Model na čtení obrázků</label>
                <?php $vm = getSetting('ollama_vision_model'); ?>
                <?php if ($probe['models']): ?>
                <select id="vision_model" name="vision_model" class="form-input">
                    <option value="">— vyber —</option>
                    <?php foreach ($probe['models'] as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $m === $vm ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" id="vision_model" name="vision_model" class="form-input"
                       value="<?= htmlspecialchars($vm) ?>" placeholder="např. llama3.2-vision">
                <?php endif; ?>
                <p class="mistake-hint">Musí umět obrázky — třeba <code>llama3.2-vision</code>, <code>minicpm-v</code>, <code>qwen2.5vl</code>.</p>
            </div>

            <div class="form-group">
                <label for="text_model">Model na sestavení sady</label>
                <?php $tm = getSetting('ollama_text_model'); ?>
                <?php if ($probe['models']): ?>
                <select id="text_model" name="text_model" class="form-input">
                    <option value="">— vyber —</option>
                    <?php foreach ($probe['models'] as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $m === $tm ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" id="text_model" name="text_model" class="form-input"
                       value="<?= htmlspecialchars($tm) ?>" placeholder="např. qwen2.5">
                <?php endif; ?>
                <p class="mistake-hint">Stačí běžný textový model. Skládání JSON zvládne líp než přepis obrázku.
                   Máš-li málo paměti na kartě, dej sem i do čtení obrázků <strong>tentýž model</strong> —
                   nebude se pak mezi kroky přenačítat.</p>
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

        <button type="submit" class="btn-primary">Uložit nastavení</button>
    </form>
</section>

<?php if (!$job): ?>
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
    </div>
    <div class="form-group">
        <label for="pages">Stránky</label>
        <input type="file" id="pages" class="form-input" accept="image/*" multiple>
    </div>
    <button type="button" id="uploadBtn" class="btn-primary" <?= $probe['ok'] ? '' : 'disabled' ?>>
        Nahrát a přepsat →
    </button>
    <?php if (!$probe['ok']): ?>
    <p class="mistake-hint">Nejdřív nastav funkční adresu Ollamy.</p>
    <?php endif; ?>
    <div id="uploadProgress" class="mistake-hint" style="margin-top:1rem"></div>
</section>
<?php endif; ?>

<?php if ($job): ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0"><?= htmlspecialchars($job['title'] ?: 'Dávka #' . $jobId) ?></h2>
        <a href="<?= BASE_URL ?>/admin/ocr.php" class="btn-secondary btn-sm">＋ nová dávka</a>
    </div>
    <?php if ($job['note']): ?><p class="mistake-hint"><?= htmlspecialchars($job['note']) ?></p><?php endif; ?>

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
                </td>
                <td><?= match (true) {
                        (int)$p['seconds'] > 0     => (int)$p['seconds'] . ' s',
                        $p['status'] === 'hotovo'  => '<1 s',
                        default                    => '–',
                    } ?></td>
                <td>
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

    <?php
    $waiting = count(array_filter($pages, fn($p) => in_array($p['status'], ['ceka', 'bezi'], true)));
    ?>
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
        Tohle model vyčetl ze stránek. Co je tady špatně, bude špatně i v sadě — vyplatí se to projet očima.
    </p>
    <form method="post">
        <input type="hidden" name="action" value="save_text">
        <input type="hidden" name="job_id" value="<?= $jobId ?>">
        <textarea id="ocrText" name="text" rows="14" class="form-input"
                  style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars(ocrJobText($jobId)) ?></textarea>
        <?php
        $estTokens = estimateTokens(ocrJobText($jobId));
        $ctxSize   = ollamaContextSize();
        $tooLong   = $estTokens * 2 + 500 > $ctxSize;
        ?>
        <p class="mistake-hint" style="margin-top:.5rem">
            Odhadem <strong><?= $estTokens ?></strong> tokenů, nastavený kontext je <?= $ctxSize ?>.
            <?php if ($tooLong): ?>
            <span style="color:var(--danger)">Na sestavení sady to nemusí stačit — zvyš kontext,
            nebo dávku rozděl na míň stránek.</span>
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
        <thead><tr><th>Název</th><th>Stránek</th><th>Přepsáno</th><th>Založeno</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($jobs as $j): ?>
            <tr>
                <td><a href="?job=<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['title'] ?: 'Dávka #' . (int)$j['id']) ?></a></td>
                <td><?= (int)$j['page_count'] ?></td>
                <td><?= (int)$j['done_count'] ?>/<?= (int)$j['page_count'] ?></td>
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
