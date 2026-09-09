<?php
/**
 * Tvorba sad z přepsaného textu.
 *
 * Třetí krok: z přepisů (už zkontrolovaných v záložce Přepis) textový model
 * složí JSON sady. Výsledek nejde uložit rovnou — posílá se do stejného
 * validátoru jako ručně vložená sada, takže se do databáze nedostane nic
 * nezkontrolovaného.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr_ui.php';
require_once __DIR__ . '/../includes/sets.php';

// ── AJAX: skládání sady a dotaz na výsledek ──
if (($_POST['ajax'] ?? '') !== '') {
    header('Content-Type: application/json');
    set_time_limit(900);
    ignore_user_abort(true);
    session_write_close();

    $albumId = (int)($_POST['album_id'] ?? 0);
    switch ($_POST['ajax']) {
        case 'build_status':
            echo json_encode(['ok' => true] + buildStatus($albumId));
            exit;

        case 'build':
            // Prázdný text neukládáme — přepsal by ruční opravy, kdyby ho
            // prohlížeč z jakéhokoli důvodu neposlal
            $text = trim((string)($_POST['text'] ?? ''));
            if ($text === '') { echo json_encode(['ok' => false, 'error' => 'Text je prázdný — není z čeho skládat.']); exit; }
            saveOcrText($albumId, $text);

            $model = trim((string)($_POST['model'] ?? ''));
            startBuild($albumId);
            $r = llmBuildSet($text, [
                'subject' => (string)($_POST['subject'] ?? 'ostatni'),
                'grade'   => (int)($_POST['grade'] ?? 0),
                'title'   => (string)($_POST['set_title'] ?? ''),
                'source'  => (string)($_POST['source'] ?? ''),
                'kind'    => (string)($_POST['kind'] ?? 'dvojice'),
            ], $model);

            // Výsledek se ukládá k albu, ne jen do odpovědi — když spojení
            // mezitím spadlo, prohlížeč si ho vyzvedne dotazem na stav
            finishBuild($albumId, $r['ok'] ? $r['json'] : '', $r['ok'] ? '' : $r['error']);

            if (!$r['ok']) { echo json_encode(['ok' => false, 'error' => $r['error'], 'warning' => $r['warning']]); exit; }

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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_text') {
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text !== '') saveOcrText((int)($_POST['album_id'] ?? 0), $text);
    $message = 'Text uložen.';
}

$albumId = (int)($_GET['album'] ?? $_POST['album_id'] ?? 0);
$album   = $albumId ? getOcrJob($albumId) : null;
$pages   = $album ? array_values(array_filter(ocrPages($albumId), fn($p) => pageText($p) !== '')) : [];
$albums  = !$album ? array_filter(listOcrJobs(), fn($a) => (int)$a['done_count'] > 0) : [];

$text     = $album ? ocrJobText($albumId) : '';
$estTokens = estimateTokens($text);
$ctxSize   = proxyContextSize();

$pageTitle = 'Tvorba sad';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🧩 Tvorba <span class="accent">sad</span></h1>
    <p class="page-subtitle">Z přepsaného textu složit sadu otázek k procvičování</p>
</div>
<?php ocrNav('tvorba'); ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<?php if ($album): ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0">
            <?= htmlspecialchars($album['title'] ?: 'Album #' . $albumId) ?>
            <span class="mistake-hint" style="font-weight:normal"><?= htmlspecialchars(subjectGradeLabel((string)$album['subject'], (int)$album['grade'])) ?></span>
        </h2>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/admin/ocr.php?album=<?= $albumId ?>" class="btn-secondary btn-sm">← přepis</a>
            <a href="<?= BASE_URL ?>/admin/tvorba.php" class="btn-secondary btn-sm">jiné album</a>
        </div>
    </div>

    <?php if (!$pages): ?>
    <p class="mistake-hint" style="margin-top:1rem">Žádná stránka alba ještě nemá přepis —
        <a href="<?= BASE_URL ?>/admin/ocr.php?album=<?= $albumId ?>">pusť nejdřív OCR</a>.</p>
    <?php else: ?>
    <p class="mistake-hint" style="margin:1rem 0 .5rem">
        Zaškrtni, co se má do textu vzít — celé stránky, nebo jen jednotlivá cvičení (podle bloků z modelu) —
        a dej <strong>Načíst text</strong>. Text níž pak můžeš ještě upravit; co je špatně v něm, bude špatně i v sadě.
    </p>
    <label class="mistake-hint" style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
        <input type="checkbox" id="pickAll" checked> vybrat vše
    </label>
    <?php $groups = []; foreach ($pages as $p): $ex = pageExercises($p); foreach ($ex as $e) $groups[$e['key']] = $e['text']; ?>
    <div class="ocr-exercise-page">
        <div class="ocr-exercise-page-head">
            <?= pageThumb($p) ?>
            <div>
                <strong>Stránka <?= (int)$p['position'] + 1 ?></strong>
                <span class="mistake-hint"><?= htmlspecialchars($p['filename']) ?><?php if (trim((string)$p['edited_text']) !== ''): ?> · ✎ ručně opraveno<?php endif; ?></span><br>
                <a href="<?= BASE_URL ?>/admin/ocr.php?page=<?= (int)$p['id'] ?>" class="btn-sm btn-sm-blue">Porovnat</a>
            </div>
        </div>
        <?php foreach ($ex as $e): ?>
        <label class="ocr-exercise-pick">
            <input type="checkbox" class="page-pick" value="<?= htmlspecialchars($e['key']) ?>" checked>
            <span><?= htmlspecialchars($e['label']) ?>
                <span class="mistake-hint">· <?= mb_strlen($e['text']) ?> zn.<?= $e['images'] ? ' · 🖼 ' . $e['images'] : '' ?></span></span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <button type="button" id="loadTextBtn" class="btn-secondary" style="margin-top:.75rem">Načíst text ze stránek ↓</button>
    <?php endif; ?>
</section>

<?php if ($pages): ?>
<section class="admin-card">
    <h2 class="section-title">Text pro model — přečti a oprav</h2>
    <form method="post">
        <input type="hidden" name="action" value="save_text">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <textarea id="ocrText" name="text" rows="14" class="form-input"
                  style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars($text) ?></textarea>
        <p class="mistake-hint" style="margin-top:.5rem">
            Odhadem <strong id="tokenEstimate"><?= $estTokens ?></strong> tokenů, kontext Ollamy je <?= $ctxSize ?>.
            <span id="tokenWarn" style="color:var(--danger)" <?= $estTokens * 2 + 500 > $ctxSize ? '' : 'hidden' ?>>
                Na sestavení přes Ollamu to nemusí stačit — zvyš kontext, vyber míň stránek, nebo sadu nech složit přes komerční API.
            </span>
        </p>
        <button type="submit" class="btn-secondary" style="margin-top:.75rem">Uložit text</button>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Sestavit sadu</h2>
    <div class="form-row">
        <div class="form-group">
            <label for="set_title">Název sady</label>
            <input type="text" id="set_title" class="form-input" value="<?= htmlspecialchars($album['title']) ?>">
        </div>
        <div class="form-group">
            <label for="source">Zdroj</label>
            <input type="text" id="source" class="form-input" value="<?= htmlspecialchars($album['title']) ?>"
                   placeholder="Project 1, 4. vydání, Unit 3">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="subject">Předmět</label>
            <select id="subject" class="form-input">
                <?php foreach (SET_SUBJECTS as $key => $s): ?>
                <option value="<?= $key ?>" <?= $key === (string)$album['subject'] ? 'selected' : '' ?>><?= htmlspecialchars($s['icon'] . ' ' . $s['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="mistake-hint">Zděděno z alba; v galerii se to dá změnit.</p>
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
                <option value="<?= $g ?>" <?= $g === (int)$album['grade'] ? 'selected' : '' ?>><?= $g ?>. třída</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="build_model">Skládá model</label>
            <?php proxyModelPicker('build_model', llmModel('text')); ?>
            <p class="mistake-hint">Sadu skládá textový model, ne ten na čtení obrázků.</p>
        </div>
    </div>
    <p class="mistake-hint" style="margin-bottom:1rem">
        Typ sady vybírej podle toho, co na stránkách je: <strong>dvojice</strong> pro seznam slovíček,
        <strong>vyber</strong> pro otázky s možnostmi, <strong>doplnovacka</strong> pro věty s vynechaným
        slovem, <strong>cteni</strong> pro souvislý text s otázkami.
    </p>
    <button type="button" id="buildBtn" class="btn-primary">Sestavit JSON →</button>
    <span id="buildProgress" class="mistake-hint" style="margin-left:.75rem"></span>

    <div id="buildWarning" class="alert alert-error" style="margin-top:1rem;display:none"></div>

    <div id="buildResult" style="margin-top:1.25rem;display:none">
        <div id="buildErrors"></div>
        <textarea id="buildJson" rows="12" class="form-input" style="font-family:monospace;font-size:.8rem"></textarea>
        <form method="post" action="<?= BASE_URL ?>/admin/sady.php" style="margin-top:.75rem">
            <input type="hidden" name="action" value="check">
            <input type="hidden" name="json" id="handoffJson">
            <input type="hidden" name="job_id" value="<?= $albumId ?>">
            <button type="submit" class="btn-primary">Otevřít v importu sad →</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php else: ?>
<section class="admin-card">
    <h2 class="section-title">Vyber album s přepisem</h2>
    <?php if (!$albums): ?>
    <p class="mistake-hint">Žádné album zatím nemá přepsanou stránku —
        <a href="<?= BASE_URL ?>/admin/ocr.php">pusť nejdřív OCR</a>.</p>
    <?php else: ?>
    <?php foreach (groupAlbumsBySubject($albums) as $label => $group): ?>
    <h3 class="section-title" style="font-size:1rem;margin-top:1.25rem"><?= htmlspecialchars($label) ?></h3>
    <table class="data-table">
        <thead><tr><th>Album</th><th>Přepsáno</th><th>Sad</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($group as $a): ?>
            <tr>
                <td><a href="?album=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['title'] ?: 'Album #' . (int)$a['id']) ?></a></td>
                <td><?= (int)$a['done_count'] ?>/<?= (int)$a['page_count'] ?></td>
                <td><?= (int)$a['set_count'] ?: '–' ?></td>
                <td><a href="?album=<?= (int)$a['id'] ?>" class="btn-sm btn-sm-blue">Otevřít</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<script>
const OCR_AJAX_URL = '<?= BASE_URL ?>/admin/tvorba.php';
const OCR_ALBUM_ID = <?= (int)$albumId ?>;
const OCR_CTX      = <?= (int)$ctxSize ?>;
const PAGE_TEXTS   = <?= json_encode($groups ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_FORCE_OBJECT) ?>;
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
