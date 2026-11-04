<?php
/**
 * Kapitoly učebnice a hromadné skládání sad.
 *
 * Mezi přepisem a tvorbou sad: album se rozdělí na kapitoly (navrhne je
 * textový model z nadpisů stránek, admin je opraví) a z kapitol se pak
 * skládají sady — jedna ručně v Tvorbě sad, nebo všechny naráz přes frontu,
 * kterou dojíždí prohlížeč nebo cron.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr_ui.php';
require_once __DIR__ . '/../includes/chapters.php';

$user = getCurrentUser();

// ── AJAX: fronta skládání ──
if (($_POST['ajax'] ?? '') !== '') {
    header('Content-Type: application/json');
    set_time_limit(900);
    ignore_user_abort(true);
    session_write_close();

    $albumId = (int)($_POST['album_id'] ?? 0);
    switch ($_POST['ajax']) {
        case 'build_process':
            // Skládání z fronty tlačí prohlížeč, dokud je stránka otevřená;
            // cron si vezme, co zbyde. Rozpracované se nebere dvakrát.
            $r = processNextSetBuild();
            echo json_encode(['ok' => true] + $r + ['cron' => ocrCronStatus()['alive']]);
            exit;

        case 'build_list':
            $rows = array_map(fn($b) => [
                'id'      => (int)$b['id'],
                'chapter' => chapterLabel(['label' => $b['chapter_label'] ?? '', 'title' => $b['chapter_title'] ?? 'smazaná kapitola']),
                'kind'    => SET_KIND_SUFFIX[$b['kind']] ?? $b['kind'],
                'status'  => $b['status'],
                'label'   => setBuildStatusLabel($b),
                'set_id'  => (int)$b['set_id'],
                'items'   => (int)$b['item_count'],
                'seconds' => (int)$b['seconds'],
                'error'   => (string)$b['error'],
                'warning' => (string)$b['warning'],
                'has_json'=> trim((string)$b['json']) !== '',
            ], setBuilds($albumId));
            echo json_encode(['ok' => true, 'builds' => $rows, 'open' => openSetBuildCount($albumId), 'cron' => ocrCronStatus()['alive']]);
            exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Neznámá akce.']);
    exit;
}

$message = '';
$error   = '';
$suggest = null;   // návrh od modelu k potvrzení

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $albumId = (int)($_POST['album_id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'add':
        case 'update':
            $id = saveChapter($albumId, $_POST, (int)($_POST['chapter_id'] ?? 0));
            if ($id) $message = ($_POST['action'] === 'add' ? 'Kapitola přidána.' : 'Kapitola uložena.');
            else     $error   = ocrLastError() ?: 'Kapitolu se nepodařilo uložit.';
            break;

        case 'delete':
            $message = deleteChapter((int)($_POST['chapter_id'] ?? 0)) ? 'Kapitola smazána.' : 'Kapitolu se nepodařilo smazat.';
            break;

        case 'suggest':
            set_time_limit(600);
            $r = llmSuggestChapters($albumId, (string)($_POST['granularity'] ?? 'lesson'), (string)($_POST['model'] ?? ''));
            if ($r['ok']) {
                $suggest = $r;
            } else {
                $error = 'Návrh kapitol se nepovedl: ' . $r['error'] . ($r['warning'] ? ' ' . $r['warning'] : '');
            }
            break;

        case 'apply_suggest':
            $list = json_decode((string)($_POST['chapters_json'] ?? ''), true);
            if (!is_array($list) || !$list) { $error = 'Návrh je prázdný.'; break; }
            $n = replaceChapters($albumId, $list);
            $message = $n ? 'Kapitoly nahrazeny návrhem modelu: ' . $n . '.' : (ocrLastError() ?: 'Návrh se nepodařilo uložit.');
            break;

        case 'queue_builds':
            $kinds = array_keys(array_filter((array)($_POST['kinds'] ?? [])));
            $ids   = array_map('intval', (array)($_POST['chapter_ids'] ?? []));
            if (!$ids)   { $error = 'Vyber aspoň jednu kapitolu.'; break; }
            if (!$kinds) { $error = 'Vyber aspoň jeden typ sady.'; break; }
            $n = queueSetBuilds($albumId, $ids, $kinds, (string)($_POST['model'] ?? ''), (int)$user['id']);
            $message = $n ? 'Do fronty zařazeno sad: ' . $n . '. Skládají se, dokud je stránka otevřená; cron je dojede i po zavření.'
                          : (ocrLastError() ?: 'Nic se nezařadilo.');
            break;

        case 'cancel_builds':
            $n = cancelSetBuilds($albumId);
            $message = $n ? 'Z fronty zrušeno: ' . $n . '. Co se zrovna skládá, dojede.' : 'Ve frontě nic nečekalo.';
            break;

        case 'delete_build':
            $message = deleteSetBuild((int)($_POST['build_id'] ?? 0)) ? 'Záznam smazán.' : 'Běžící skládání smazat nejde.';
            break;

        case 'retry_build':
            $message = retrySetBuild((int)($_POST['build_id'] ?? 0)) ? 'Skládání vráceno do fronty.' : 'Vrátit jde jen skládání, které skončilo chybou.';
            break;
    }
}

$albumId  = (int)($_GET['album'] ?? $_POST['album_id'] ?? 0);
$album    = $albumId ? getOcrJob($albumId) : null;
$pages    = $album ? ocrPages($albumId) : [];
$withText = count(array_filter($pages, fn($p) => pageText($p) !== ''));
$list     = $album ? chapters($albumId) : [];
$builds   = $album ? setBuilds($albumId) : [];
$albums   = !$album ? array_filter(listOcrJobs(), fn($a) => (int)$a['done_count'] > 0) : [];
$textModel = llmModel('text');

$pageTitle = 'Kapitoly';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📖 <span class="accent">Kapitoly</span> učebnice</h1>
    <p class="page-subtitle">Rozdělit album na kapitoly a z každé složit sady</p>
</div>
<?php ocrNav('kapitoly'); ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($album): ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0">
            <?= htmlspecialchars($album['title'] ?: 'Album #' . $albumId) ?>
            <span class="mistake-hint" style="font-weight:normal"><?= htmlspecialchars(subjectGradeLabel((string)$album['subject'], (int)$album['grade'])) ?>
                · stránek <?= count($pages) ?>, s přepisem <?= $withText ?></span>
        </h2>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/admin/ocr.php?album=<?= $albumId ?>" class="btn-secondary btn-sm">← přepis</a>
            <a href="<?= BASE_URL ?>/admin/tvorba.php?album=<?= $albumId ?>" class="btn-secondary btn-sm">tvorba sad →</a>
            <a href="<?= BASE_URL ?>/admin/kapitoly.php" class="btn-secondary btn-sm">jiné album</a>
        </div>
    </div>

    <?php if ($suggest): ?>
    <div class="alert alert-ok" style="margin-top:1rem">
        <strong>Model navrhl <?= count($suggest['chapters']) ?> kapitol.</strong> Projdi je; po potvrzení <strong>nahradí</strong>
        stávající kapitoly alba (<?= count($list) ?>). Co nesedí, opravíš potom v tabulce níž.
        <?php if ($suggest['warning']): ?><br><?= htmlspecialchars($suggest['warning']) ?><?php endif; ?>
    </div>
    <table class="data-table">
        <thead><tr><th>Označení</th><th>Název</th><th>Stránky</th><th>Poznámka</th></tr></thead>
        <tbody>
        <?php foreach ($suggest['chapters'] as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['label']) ?></td>
                <td><?= htmlspecialchars($c['title']) ?></td>
                <td><?= (int)$c['page_from'] ?>–<?= (int)$c['page_to'] ?></td>
                <td class="mistake-hint"><?= htmlspecialchars($c['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post" style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <input type="hidden" name="action" value="apply_suggest">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <input type="hidden" name="chapters_json" value="<?= htmlspecialchars(json_encode($suggest['chapters'], JSON_UNESCAPED_UNICODE)) ?>">
        <button type="submit" class="btn-primary">Použít návrh (nahradit kapitoly)</button>
        <a href="?album=<?= $albumId ?>" class="btn-secondary">Zahodit</a>
    </form>
    <?php endif; ?>

    <h3 class="section-title" style="font-size:1rem;margin-top:1.25rem">Kapitoly (<?= count($list) ?>)</h3>
    <?php if (!$list): ?>
    <p class="mistake-hint">Album ještě nemá kapitoly — nech je navrhnout modelem níž, nebo je přidej ručně.</p>
    <?php else: ?>
    <p class="mistake-hint" style="margin-bottom:.5rem">Stránky jsou číslované podle pořadí v albu (1 = první fotka).
        Každý řádek se ukládá zvlášť tlačítkem ✔.</p>
    <table class="data-table">
        <thead><tr><th>Označení</th><th>Název</th><th>Od</th><th>Do</th><th>Poznámka</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $c): $cp = chapterPages($c, $pages); $chars = mb_strlen(chapterText($c, $pages)); ?>
            <tr>
                <td><input type="text" name="label" form="ch<?= (int)$c['id'] ?>" class="form-input" style="width:5rem" value="<?= htmlspecialchars($c['label']) ?>"></td>
                <td><input type="text" name="title" form="ch<?= (int)$c['id'] ?>" class="form-input" style="min-width:12rem" value="<?= htmlspecialchars($c['title']) ?>" required></td>
                <td><input type="number" name="page_from" form="ch<?= (int)$c['id'] ?>" class="form-input" style="width:4.5rem" min="1" max="<?= count($pages) ?>" value="<?= (int)$c['page_from'] ?>"></td>
                <td><input type="number" name="page_to" form="ch<?= (int)$c['id'] ?>" class="form-input" style="width:4.5rem" min="1" max="<?= count($pages) ?>" value="<?= (int)$c['page_to'] ?>"></td>
                <td><input type="text" name="note" form="ch<?= (int)$c['id'] ?>" class="form-input" style="min-width:12rem" value="<?= htmlspecialchars($c['note']) ?>">
                    <span class="mistake-hint"><?= count($cp) ?> str. · <?= $chars ?> zn.<?= $chars === 0 ? ' · ⚠ bez přepisu' : '' ?></span></td>
                <td style="white-space:nowrap">
                    <button type="submit" form="ch<?= (int)$c['id'] ?>" class="btn-sm" title="uložit změny">✔</button>
                    <a href="<?= BASE_URL ?>/admin/tvorba.php?album=<?= $albumId ?>&amp;chapter=<?= (int)$c['id'] ?>" class="btn-sm btn-sm-blue" title="složit sadu ručně v Tvorbě sad">sada ručně</a>
                    <button type="submit" form="del<?= (int)$c['id'] ?>" class="btn-sm btn-sm-red" title="smazat kapitolu"
                            onclick="return confirm('Smazat kapitolu „<?= htmlspecialchars(addslashes($c['title'])) ?>“?')">✕</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php // Formuláře řádků stojí mimo tabulku (uvnitř <tr> by je prohlížeč vyhodil); pole se k nim hlásí atributem form=
          foreach ($list as $c): ?>
    <form method="post" id="ch<?= (int)$c['id'] ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <input type="hidden" name="chapter_id" value="<?= (int)$c['id'] ?>">
    </form>
    <form method="post" id="del<?= (int)$c['id'] ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <input type="hidden" name="chapter_id" value="<?= (int)$c['id'] ?>">
    </form>
    <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" style="margin-top:1rem">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <div class="form-row" style="flex-direction:row;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div class="form-group" style="margin:0"><label>Označení</label><input type="text" name="label" class="form-input" style="width:5rem" placeholder="1A"></div>
            <div class="form-group" style="margin:0;flex:1;min-width:12rem"><label>Název</label><input type="text" name="title" class="form-input" placeholder="My daily life" required></div>
            <div class="form-group" style="margin:0"><label>Od</label><input type="number" name="page_from" class="form-input" style="width:4.5rem" min="1" max="<?= count($pages) ?>" value="1"></div>
            <div class="form-group" style="margin:0"><label>Do</label><input type="number" name="page_to" class="form-input" style="width:4.5rem" min="1" max="<?= count($pages) ?>" value="<?= max(1, count($pages)) ?>"></div>
            <div class="form-group" style="margin:0;flex:1;min-width:10rem"><label>Poznámka</label><input type="text" name="note" class="form-input" placeholder="mluvnice, slovní zásoba"></div>
            <button type="submit" class="btn-secondary">+ Přidat kapitolu</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Navrhnout kapitoly modelem</h2>
    <p class="mistake-hint" style="margin-bottom:.75rem">
        Model dostane ke každé stránce prvních pár řádků přepisu (nadpisy lekcí, čísla cvičení) a vrátí
        rozdělení na kapitoly s rozsahem stránek. Návrh uvidíš a potvrdíš — teprve pak nahradí stávající kapitoly.
        <?php if ($withText < count($pages)): ?>Bez přepisu je stránek <?= count($pages) - $withText ?>, ty model zařadit neumí.<?php endif; ?>
    </p>
    <form method="post">
        <input type="hidden" name="action" value="suggest">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <div class="form-row" style="flex-direction:row;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
            <div class="form-group" style="margin:0">
                <label for="granularity">Jemnost</label>
                <select id="granularity" name="granularity" class="form-input">
                    <option value="lesson">části lekcí (1A, 1B, Culture, Revision …)</option>
                    <option value="unit">celé lekce (Unit 1, Unit 2 …)</option>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label for="suggest_model">Navrhuje model</label>
                <?php proxyModelPicker('suggest_model', $textModel); ?>
            </div>
            <button type="submit" class="btn-primary" <?= $withText ? '' : 'disabled' ?>
                    onclick="this.textContent='Model přemýšlí…';this.form.submit();this.disabled=true">Navrhnout kapitoly →</button>
        </div>
        <?php if (preg_match('/ocr|deepseek/i', $textModel)): ?>
        <p class="mistake-hint" style="color:var(--danger);margin-top:.5rem">Nastavený textový model vypadá jako OCR model — kapitoly ani sady nesloží, vyber obecný (claude, gpt, gemma, qwen).</p>
        <?php endif; ?>
    </form>
    <script>
    // name="model" chce formulář, picker ho pojmenoval suggest_model
    document.getElementById('suggest_model').name = 'model';
    </script>
</section>

<section class="admin-card" id="builds">
    <h2 class="section-title">Sestavit sady z kapitol</h2>
    <?php if (!$list): ?>
    <p class="mistake-hint">Nejdřív potřebuješ kapitoly.</p>
    <?php else: ?>
    <p class="mistake-hint" style="margin-bottom:.75rem">
        Pro každou zaškrtnutou kapitolu a typ vznikne jedna sada; skládá je textový model z přepisu stránek kapitoly
        a hotová sada se rovnou uloží do <a href="<?= BASE_URL ?>/admin/sady.php">Sad</a> (jde pak upravit po položkách).
        Položky, které neprojdou kontrolou, se vynechají; když jich nezbude dost, skládání skončí chybou a JSON si otevřeš v importu.
    </p>
    <form method="post">
        <input type="hidden" name="action" value="queue_builds">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem">
            <div style="flex:1;min-width:14rem">
                <div class="mistake-hint" style="font-family:var(--font-mono);margin-bottom:.4rem">Kapitoly
                    <label class="ocr-pick-line" style="display:inline;margin-left:.75rem"><input type="checkbox" id="chAll" checked> vše</label></div>
                <?php foreach ($list as $c): ?>
                <label class="ocr-pick-line"><input type="checkbox" name="chapter_ids[]" class="ch-pick" value="<?= (int)$c['id'] ?>" checked>
                    <?= htmlspecialchars(chapterLabel($c)) ?> <span class="mistake-hint">(<?= (int)$c['page_from'] ?>–<?= (int)$c['page_to'] ?>)</span></label>
                <?php endforeach; ?>
            </div>
            <div style="min-width:14rem">
                <div class="mistake-hint" style="font-family:var(--font-mono);margin-bottom:.4rem">Typy sad</div>
                <?php foreach (SET_KINDS as $key => $label): ?>
                <label class="ocr-pick-line"><input type="checkbox" name="kinds[<?= $key ?>]" value="1" <?= in_array($key, ['dvojice', 'doplnovacka'], true) ? 'checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
                <?php endforeach; ?>
                <p class="mistake-hint" style="margin-top:.5rem"><strong>dvojice</strong> = slovíčka lekce s překladem,
                    <strong>doplnovacka</strong> = věty s mezerou, <strong>vyber</strong> = otázky s možnostmi,
                    <strong>cteni</strong> = text a otázky k němu.</p>
                <div class="form-group" style="margin:.75rem 0 0">
                    <label for="build_model">Skládá model</label>
                    <?php proxyModelPicker('build_model', $textModel); ?>
                </div>
            </div>
        </div>
        <button type="submit" class="btn-primary" style="margin-top:1rem">Zařadit do fronty →</button>
    </form>
    <script>
    document.getElementById('build_model').name = 'model';
    document.getElementById('chAll').addEventListener('change', e => document.querySelectorAll('.ch-pick').forEach(c => { c.checked = e.target.checked; }));
    </script>
    <?php endif; ?>

    <?php if ($builds): ?>
    <div class="challenge-head" style="margin-top:1.5rem">
        <h3 class="section-title" style="font-size:1rem;margin:0">Fronta a výsledky <span id="buildOpen" class="mistake-hint"></span></h3>
        <form method="post">
            <input type="hidden" name="action" value="cancel_builds">
            <input type="hidden" name="album_id" value="<?= $albumId ?>">
            <button type="submit" class="btn-sm btn-sm-red">Zrušit čekající</button>
        </form>
    </div>
    <table class="data-table" style="margin-top:.5rem">
        <thead><tr><th>Kapitola</th><th>Typ</th><th>Stav</th><th>Výsledek</th><th></th></tr></thead>
        <tbody id="buildRows">
        <?php foreach ($builds as $b): ?>
            <tr data-build="<?= (int)$b['id'] ?>">
                <td><?= htmlspecialchars(chapterLabel(['label' => $b['chapter_label'] ?? '', 'title' => $b['chapter_title'] ?? 'smazaná kapitola'])) ?></td>
                <td><?= htmlspecialchars(SET_KIND_SUFFIX[$b['kind']] ?? $b['kind']) ?></td>
                <td class="b-status"><?= setBuildStatusLabel($b) ?></td>
                <td class="b-result">
                    <?php if ($b['status'] === 'hotovo' && $b['set_id']): ?>
                        <a href="<?= BASE_URL ?>/admin/sady.php?edit=<?= (int)$b['set_id'] ?>"><?= htmlspecialchars($b['title']) ?></a>
                        <span class="mistake-hint">· <?= (int)$b['item_count'] ?> položek · <?= (int)$b['seconds'] ?> s</span>
                        <?php if ($b['warning']): ?><br><span class="mistake-hint">⚠ <?= htmlspecialchars($b['warning']) ?></span><?php endif; ?>
                    <?php elseif ($b['status'] === 'chyba'): ?>
                        <span style="color:var(--danger)"><?= htmlspecialchars($b['error']) ?></span>
                        <?php if (trim((string)$b['json']) !== ''): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/sady.php" style="display:inline">
                            <input type="hidden" name="action" value="check">
                            <input type="hidden" name="job_id" value="<?= $albumId ?>">
                            <input type="hidden" name="json" value="<?= htmlspecialchars((string)$b['json']) ?>">
                            <button type="submit" class="btn-sm btn-sm-blue">otevřít v importu</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="mistake-hint"><?= htmlspecialchars($b['model'] ?: 'výchozí model') ?></span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <?php if ($b['status'] === 'chyba'): ?>
                    <form method="post" style="display:inline"><input type="hidden" name="action" value="retry_build"><input type="hidden" name="album_id" value="<?= $albumId ?>"><input type="hidden" name="build_id" value="<?= (int)$b['id'] ?>"><button type="submit" class="btn-sm">znovu</button></form>
                    <?php endif; ?>
                    <?php if ($b['status'] !== 'bezi'): ?>
                    <form method="post" style="display:inline"><input type="hidden" name="action" value="delete_build"><input type="hidden" name="album_id" value="<?= $albumId ?>"><input type="hidden" name="build_id" value="<?= (int)$b['id'] ?>"><button type="submit" class="btn-sm btn-sm-red" title="smazat záznam (sada zůstane)">✕</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<script>
const OCR_AJAX_URL = '<?= BASE_URL ?>/admin/kapitoly.php';
const OCR_ALBUM_ID = <?= (int)$albumId ?>;
const BUILDS_OPEN  = <?= openSetBuildCount($albumId) ?>;
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>

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
        <thead><tr><th>Album</th><th>Přepsáno</th><th>Kapitol</th><th>Sad</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($group as $a): ?>
            <tr>
                <td><a href="?album=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['title'] ?: 'Album #' . (int)$a['id']) ?></a></td>
                <td><?= (int)$a['done_count'] ?>/<?= (int)$a['page_count'] ?></td>
                <td><?= count(chapters((int)$a['id'])) ?: '–' ?></td>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
