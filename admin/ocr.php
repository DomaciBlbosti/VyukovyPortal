<?php
/**
 * Přepis stránek (OCR).
 *
 * Fotky už jsou v galerii; tady se vybere, které z nich se pustí modelu,
 * kterému a s jakým zadáním. Každé spuštění je „běh" a u stránky se drží
 * všechny — stejný obrázek se dá zkusit několika modely a zadáními a pak
 * vybrat ten nejlepší, aniž by se fotilo znovu.
 *
 * Přepis jedné stránky trvá minuty, takže se nedá viset na jednom HTTP
 * spojení — reverzní proxy ho utne. Práce proto běží nezávisle na spojení
 * a prohlížeč se jen ptá na postup.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr_ui.php';

// ── AJAX: fronta, stav, zpracování ──
if (($_POST['ajax'] ?? '') !== '') {
    header('Content-Type: application/json');
    set_time_limit(900);      // vision model si na jednu stránku klidně vezme minuty
    ignore_user_abort(true);  // proxy spojení utne, ale práce musí doběhnout a uložit se
    session_write_close();    // PHP drží session zamčenou po celý požadavek — dotaz na stav by čekal za přepisem

    switch ($_POST['ajax']) {
        case 'queue':
            $ids = json_decode((string)($_POST['pages'] ?? '[]'), true);
            if (!is_array($ids) || !$ids) { echo json_encode(['ok' => false, 'error' => 'Nevybral jsi žádnou stránku.']); exit; }

            [$provider, $model] = splitModelPick((string)($_POST['model_pick'] ?? ''));
            if ($model === '') { echo json_encode(['ok' => false, 'error' => 'Vyber model.']); exit; }

            $key  = (string)($_POST['prompt_key'] ?? ocrDefaultPromptKey());
            $text = trim((string)($_POST['prompt_text'] ?? ''));
            if ($text === '') { echo json_encode(['ok' => false, 'error' => 'Zadání pro model je prázdné.']); exit; }
            // Upravený preset už není preset — ať je v historii vidět, co se doopravdy poslalo
            if (!isset(OCR_PROMPTS[$key]) || ($key !== 'custom' && $text !== OCR_PROMPTS[$key]['prompt'])) $key = 'custom';

            $batch = queueOcrRuns($ids, ['provider' => $provider, 'model' => $model, 'prompt_key' => $key, 'prompt' => $text]);
            echo json_encode(['ok' => true, 'batch' => $batch] + batchStatus($batch));
            exit;

        case 'status':
            echo json_encode(['ok' => true] + batchStatus((string)($_POST['batch'] ?? '')));
            exit;

        case 'process':
            echo json_encode(['ok' => true] + processNextOcrRun((string)($_POST['batch'] ?? '')));
            exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Neznámá akce.']);
    exit;
}

// ── Běžné formuláře ──
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'save_page_text':
            $message = saveOcrPageText((int)($_POST['page_id'] ?? 0), (string)($_POST['text'] ?? ''))
                ? 'Oprava uložena.' : 'Opravu se nepodařilo uložit.';
            break;

        case 'choose_run':
            $message = chooseOcrRun((int)($_POST['run_id'] ?? 0))
                ? 'Tenhle přepis je teď platný.' : 'Běh se nepodařilo vybrat.';
            break;

        case 'delete_run':
            $message = deleteOcrRun((int)($_POST['run_id'] ?? 0))
                ? 'Běh smazán.' : 'Vybraný běh smazat nejde — nejdřív vyber jiný.';
            break;
    }
}

$detailId = (int)($_GET['page'] ?? $_POST['page_id'] ?? 0);
$detail   = $detailId ? getOcrPage($detailId) : null;
$runs     = $detail ? pageRuns($detailId) : [];

$albumId = (int)($_GET['album'] ?? $_POST['album_id'] ?? ($detail['job_id'] ?? 0));
$album   = $albumId ? getOcrJob($albumId) : null;
$pages   = $album && !$detail ? ocrPages($albumId) : [];
$albums  = !$album ? listOcrJobs() : [];

$provider  = llmProvider();
$promptKey = ocrDefaultPromptKey();

$pageTitle = 'Přepis stránek';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🔍 Přepis <span class="accent">stránek</span></h1>
    <p class="page-subtitle">Vyber fotky z galerie, model a zadání — a pusť to</p>
</div>
<?php ocrNav('ocr'); ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($detail): /* ── Detail stránky: originál, přepis, historie běhů, nový běh ── */ ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0">
            <?= htmlspecialchars($album['title'] ?? '') ?> — stránka <?= (int)$detail['position'] + 1 ?>
            <span class="mistake-hint" style="font-weight:normal"><?= htmlspecialchars($detail['filename']) ?></span>
        </h2>
        <a href="?album=<?= (int)$detail['job_id'] ?>" class="btn-secondary btn-sm">← zpět na album</a>
    </div>
    <p class="mistake-hint">
        Porovnej přepis s originálem a co model spletl, oprav. Oprava má přednost před
        jakýmkoli dalším během — dokud si sám nevybereš jiný běh tlačítkem <strong>Použít</strong>.
    </p>

    <div class="ocr-compare">
        <div class="ocr-original">
            <a href="<?= BASE_URL ?>/admin/galerie.php?image=<?= (int)$detail['id'] ?>" target="_blank" rel="noopener">
                <img src="<?= BASE_URL ?>/admin/galerie.php?image=<?= (int)$detail['id'] ?>" alt="Originální fotka stránky">
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
                    <span class="mistake-hint">✎ ručně upraveno — má přednost před běhy níž</span>
                    <?php elseif ($detail['status'] === 'hotovo'): ?>
                    <span class="mistake-hint">text vybraného běhu</span>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($detail['status'] === 'chyba' && $detail['error']): ?>
            <div class="alert alert-error" style="margin-top:.75rem"><?= htmlspecialchars($detail['error']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="admin-card">
    <h2 class="section-title">Spustit model znovu na tuhle stránku</h2>
    <p class="mistake-hint" style="margin-bottom:1rem">
        Vyzkoušej jiný model nebo jiné zadání. Výsledek přibude do historie níž a stane se
        platným přepisem, pokud stránka nemá ruční opravu.
    </p>
    <div class="form-group">
        <label for="model_pick">Model</label>
        <?php providerModelPicker('model_pick', $provider, llmModel($provider, 'vision')); ?>
    </div>
    <?php promptPicker($promptKey); ?>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <button type="button" id="rerunBtn" class="btn-primary" data-page="<?= (int)$detail['id'] ?>">Spustit znovu →</button>
        <span id="runProgress" class="mistake-hint"></span>
    </div>
</section>

<section class="admin-card">
    <h2 class="section-title">Historie běhů (<?= count($runs) ?>)</h2>
    <?php if (!$runs): ?>
    <p class="mistake-hint">Na téhle stránce ještě žádný model neběžel.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Kdy</th><th>Model</th><th>Zadání</th><th>Stav</th><th>Čas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($runs as $r): ?>
            <tr data-run="<?= (int)$r['id'] ?>" <?= (int)$r['chosen'] ? 'style="background:rgba(74,222,128,.06)"' : '' ?>>
                <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars((string)$r['created_at']) ?></td>
                <td style="font-size:.85rem"><?= $r['provider'] === 'openai' ? '☁️' : '🏠' ?> <?= htmlspecialchars($r['model'] ?: '—') ?></td>
                <td style="font-size:.85rem" title="<?= htmlspecialchars((string)$r['prompt']) ?>"><?= htmlspecialchars(promptLabel($r)) ?></td>
                <td class="run-status">
                    <?= match ($r['status']) {
                        'hotovo' => ((int)$r['chosen'] ? '★ ' : '✔ ') . mb_strlen((string)$r['text']) . ' znaků',
                        'chyba'  => '✘ ' . htmlspecialchars($r['error']),
                        'bezi'   => '⏳ běží',
                        default  => '· ve frontě',
                    } ?>
                    <?php if ($r['warning']): ?><br><span style="color:var(--danger);font-size:.8rem">⚠ <?= htmlspecialchars($r['warning']) ?></span><?php endif; ?>
                </td>
                <td style="font-size:.85rem"><?= (int)$r['seconds'] ? (int)$r['seconds'] . ' s' : '–' ?>
                    <?php if ((int)$r['tokens']): ?><br><span class="mistake-hint"><?= (int)$r['tokens'] ?> tok.</span><?php endif; ?></td>
                <td>
                    <div class="admin-actions">
                    <?php if ($r['status'] === 'hotovo'): ?>
                        <button type="button" class="btn-sm btn-sm-gray run-toggle" data-run="<?= (int)$r['id'] ?>">Text</button>
                        <?php if (!(int)$r['chosen']): ?>
                        <form method="post"><input type="hidden" name="action" value="choose_run"><input type="hidden" name="page_id" value="<?= (int)$detail['id'] ?>">
                            <input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn-sm btn-sm-green" title="udělat z tohohle běhu platný přepis">Použít</button></form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!(int)$r['chosen'] && !in_array($r['status'], OCR_OPEN, true)): ?>
                        <form method="post"><input type="hidden" name="action" value="delete_run"><input type="hidden" name="page_id" value="<?= (int)$detail['id'] ?>">
                            <input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn-sm btn-sm-red">✕</button></form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php if ($r['status'] === 'hotovo'): ?>
            <tr class="run-text" id="runText<?= (int)$r['id'] ?>" hidden>
                <td colspan="6"><pre class="ocr-run-text"><?= htmlspecialchars((string)$r['text']) ?></pre></td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php elseif ($album): /* ── Výběr stránek alba a spuštění ── */ ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0"><?= htmlspecialchars($album['title'] ?: 'Album #' . $albumId) ?></h2>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/admin/galerie.php?album=<?= $albumId ?>" class="btn-secondary btn-sm">🖼️ upravit v galerii</a>
            <a href="<?= BASE_URL ?>/admin/tvorba.php?album=<?= $albumId ?>" class="btn-secondary btn-sm">🧩 tvorba sad →</a>
            <a href="<?= BASE_URL ?>/admin/ocr.php" class="btn-secondary btn-sm">← jiné album</a>
        </div>
    </div>

    <?php if (!$pages): ?>
    <p class="mistake-hint" style="margin-top:1rem">Album nemá žádné fotky —
        <a href="<?= BASE_URL ?>/admin/galerie.php?album=<?= $albumId ?>">nahraj je v galerii</a>.</p>
    <?php else: ?>
    <table class="data-table" style="margin-top:1rem">
        <thead><tr>
            <th><input type="checkbox" id="pickAll" title="vybrat vše"></th>
            <th></th><th>#</th><th>Soubor</th><th>Stav</th><th>Čas</th><th>Běhů</th><th></th>
        </tr></thead>
        <tbody id="pageRows">
        <?php foreach ($pages as $p): $noText = pageText($p) === ''; ?>
            <tr data-page="<?= (int)$p['id'] ?>">
                <td><input type="checkbox" class="page-pick" value="<?= (int)$p['id'] ?>" <?= $noText ? 'checked' : '' ?>></td>
                <td><a href="?page=<?= (int)$p['id'] ?>"><?= pageThumb($p) ?></a></td>
                <td><?= (int)$p['position'] + 1 ?></td>
                <td style="font-size:.8rem"><?= htmlspecialchars($p['filename']) ?></td>
                <td class="page-status"><?= htmlspecialchars(pageStatusLabel($p)) ?>
                    <?php if (trim((string)$p['edited_text']) !== ''): ?> <span class="mistake-hint">✎</span><?php endif; ?></td>
                <td class="page-time"><?= (int)$p['seconds'] > 0 ? (int)$p['seconds'] . ' s' : '–' ?></td>
                <td><?= (int)$p['run_count'] ?></td>
                <td><a href="?page=<?= (int)$p['id'] ?>" class="btn-sm btn-sm-blue">Detail</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="mistake-hint" style="margin-top:.5rem">Předvybrané jsou stránky bez přepisu. Zaškrtni i hotové, když je chceš zkusit jinak.</p>
    <?php endif; ?>
</section>

<?php if ($pages): ?>
<section class="admin-card">
    <h2 class="section-title">Čím a jak číst</h2>
    <div class="form-group">
        <label for="model_pick">Model</label>
        <?php providerModelPicker('model_pick', $provider, llmModel($provider, 'vision')); ?>
        <p class="mistake-hint">Výchozí model a zadání se nastavují v záložce <a href="<?= BASE_URL ?>/admin/modely.php">Modely a zadání</a>.</p>
    </div>
    <?php promptPicker($promptKey); ?>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <button type="button" id="runBtn" class="btn-primary">Přepsat vybrané →</button>
        <span id="runProgress" class="mistake-hint"></span>
    </div>
</section>
<?php endif; ?>

<?php else: /* ── Výběr alba ── */ ?>
<section class="admin-card">
    <h2 class="section-title">Vyber album</h2>
    <?php if (!$albums): ?>
    <p class="mistake-hint">Zatím žádné album — <a href="<?= BASE_URL ?>/admin/galerie.php">nahraj fotky v galerii</a>.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Album</th><th>Fotek</th><th>Přepsáno</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($albums as $a): ?>
            <tr>
                <td><a href="?album=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['title'] ?: 'Album #' . (int)$a['id']) ?></a>
                    <?php if ($a['note']): ?><br><span class="mistake-hint"><?= htmlspecialchars($a['note']) ?></span><?php endif; ?></td>
                <td><?= (int)$a['page_count'] ?></td>
                <td><?= (int)$a['done_count'] ?>/<?= (int)$a['page_count'] ?></td>
                <td><a href="?album=<?= (int)$a['id'] ?>" class="btn-sm btn-sm-blue">Otevřít</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endif; ?>

<script>
const OCR_AJAX_URL = '<?= BASE_URL ?>/admin/ocr.php';
const OCR_PROMPTS  = <?= promptPresetsJson() ?>;
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
