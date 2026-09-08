<?php
/**
 * Galerie naskenovaných stránek.
 *
 * Tady se fotky jen spravují: nahrávají do alb, přesouvají, řadí a mažou.
 * Přepis se pouští v admin/ocr.php, sada se skládá v admin/tvorba.php —
 * záměrně odděleně, ať jde stejný obrázek přepsat víckrát různými modely,
 * aniž by se musel nahrávat znovu.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/ocr_ui.php';

$user = getCurrentUser();

// ── Fotka stránky (plná i náhled) — odkazují sem i ostatní stránky skenování ──
foreach (['image' => 'image_b64', 'thumb' => 'thumb_b64'] as $param => $column) {
    if (($_GET[$param] ?? '') === '') continue;
    $page = getOcrPage((int)$_GET[$param]);
    $data = (string)($page[$column] ?? '') ?: (string)($page['image_b64'] ?? '');
    if ($data === '') { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=3600');
    echo base64_decode($data);
    exit;
}

// ── Výřez obrázku z bloku stránky ──
if (($_GET['crop'] ?? '') !== '') {
    $block = getOcrBlock((int)$_GET['crop']);
    if (!$block || !$block['image_b64']) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=3600');
    echo base64_decode((string)$block['image_b64']);
    exit;
}

// ── AJAX: nahrání jedné fotky ──
if (($_POST['ajax'] ?? '') === 'upload') {
    header('Content-Type: application/json');
    session_write_close();

    $albumId = (int)($_POST['album_id'] ?? 0);
    if (!$albumId) {
        $albumId = createOcrJob((string)($_POST['title'] ?? ''), (string)($_POST['note'] ?? ''), (int)$user['id']);
        if (!$albumId) { echo json_encode(['ok' => false, 'error' => 'Album se nepodařilo založit: ' . ocrLastError()]); exit; }
    } elseif (!getOcrJob($albumId)) {
        echo json_encode(['ok' => false, 'error' => 'Album neexistuje.']); exit;
    }
    $id = addOcrPage($albumId, (string)($_POST['filename'] ?? ''), (string)($_POST['image'] ?? ''), (string)($_POST['thumb'] ?? ''));
    // Důvod posíláme dál — je to admin a bez něj by se nedalo zjistit, jestli
    // chybí sloupec po nedoběhlé migraci, nebo je fotka moc velká
    echo json_encode(['ok' => $id > 0, 'album_id' => $albumId, 'page_id' => $id,
                      'error' => $id ? '' : 'Fotku se nepodařilo uložit: ' . ocrLastError()]);
    exit;
}

// ── Běžné formuláře ──
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'create_album':
            $id = createOcrJob((string)($_POST['title'] ?? ''), (string)($_POST['note'] ?? ''), (int)$user['id']);
            if ($id) { header('Location: ' . BASE_URL . '/admin/galerie.php?album=' . $id); exit; }
            $error = 'Album se nepodařilo založit.';
            break;

        case 'rename_album':
            $message = renameOcrJob((int)($_POST['album_id'] ?? 0), (string)($_POST['title'] ?? ''), (string)($_POST['note'] ?? ''))
                ? 'Album přejmenováno.' : 'Album se nepodařilo přejmenovat.';
            break;

        case 'delete_album':
            $message = deleteOcrJob((int)($_POST['album_id'] ?? 0))
                ? 'Album smazáno i s fotkami.' : 'Album se nepodařilo smazat.';
            unset($_GET['album']);
            break;

        case 'delete_page':
            $message = deleteOcrPage((int)($_POST['page_id'] ?? 0))
                ? 'Fotka smazána.' : 'Fotku se nepodařilo smazat.';
            break;

        case 'move_page':
            $message = moveOcrPage((int)($_POST['page_id'] ?? 0), (int)($_POST['target_album'] ?? 0))
                ? 'Fotka přesunuta.' : 'Fotku se nepodařilo přesunout.';
            break;

        case 'shift_page':
            shiftOcrPage((int)($_POST['page_id'] ?? 0), (int)($_POST['dir'] ?? 0));
            break;
    }
}

$albums  = listOcrJobs();
$albumId = (int)($_GET['album'] ?? $_POST['album_id'] ?? 0);
$album   = $albumId ? getOcrJob($albumId) : null;
$pages   = $album ? ocrPages($albumId) : [];

$pageTitle = 'Galerie stránek';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🖼️ Galerie <span class="accent">stránek</span></h1>
    <p class="page-subtitle">Vyfocené stránky učebnic v albech — nahrát, přesunout, smazat</p>
</div>
<?php ocrNav('galerie'); ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (!ocrSchemaReady()): ?>
<div class="alert alert-error">
    Databáze není zmigrovaná na tuhle verzi (chybí <code>ocr_pages.thumb_b64</code> nebo tabulka <code>ocr_runs</code>),
    nahrávání fotek by selhalo. Spusť migraci v
    <a href="<?= BASE_URL ?>/admin/system.php">Systém &amp; aktualizace</a> tlačítkem <em>Aktualizovat</em>.
</div>
<?php endif; ?>

<?php if ($album): ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0"><?= htmlspecialchars($album['title'] ?: 'Album #' . $albumId) ?></h2>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/admin/ocr.php?album=<?= $albumId ?>" class="btn-primary btn-sm">🔍 Přepsat →</a>
            <a href="<?= BASE_URL ?>/admin/galerie.php" class="btn-secondary btn-sm">← všechna alba</a>
        </div>
    </div>

    <form method="post" class="form-row" style="margin-top:1rem">
        <input type="hidden" name="action" value="rename_album">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <div class="form-group" style="margin:0">
            <label for="title">Název alba</label>
            <input type="text" id="title" name="title" class="form-input" value="<?= htmlspecialchars($album['title']) ?>">
        </div>
        <div class="form-group" style="margin:0">
            <label for="note">Poznámka</label>
            <input type="text" id="note" name="note" class="form-input" value="<?= htmlspecialchars($album['note']) ?>">
        </div>
        <button type="submit" class="btn-secondary">Přejmenovat</button>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Nahrát fotky</h2>
    <p class="mistake-hint" style="margin-bottom:1rem">
        Vyfoť stránky telefonem a vyber je všechny naráz. Prohlížeč je před odesláním zmenší
        (delší strana 1600 px), takže velké fotky z telefonu vadit nebudou.
    </p>
    <div class="form-group">
        <input type="file" id="pages" class="form-input" accept="image/*" multiple>
    </div>
    <button type="button" id="uploadBtn" class="btn-primary" data-album="<?= $albumId ?>">Nahrát do alba</button>
    <div id="uploadProgress" class="mistake-hint" style="margin-top:1rem"></div>
</section>

<section class="admin-card">
    <h2 class="section-title">Fotky (<?= count($pages) ?>)</h2>
    <?php if (!$pages): ?>
    <p class="mistake-hint">Album je prázdné — nahraj fotky výš.</p>
    <?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($pages as $i => $p): ?>
        <div class="gallery-card">
            <a href="<?= BASE_URL ?>/admin/galerie.php?image=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">
                <?= pageThumb($p, 'gallery-img') ?>
            </a>
            <div class="gallery-meta">
                <strong><?= $i + 1 ?>.</strong> <?= htmlspecialchars($p['filename']) ?><br>
                <span class="mistake-hint"><?= htmlspecialchars(pageStatusLabel($p)) ?>
                    <?php if ((int)$p['run_count']): ?> · běhů: <?= (int)$p['run_count'] ?><?php endif; ?>
                    <?php if (trim((string)$p['edited_text']) !== ''): ?> ✎<?php endif; ?>
                </span>
            </div>
            <div class="gallery-actions">
                <a href="<?= BASE_URL ?>/admin/ocr.php?page=<?= (int)$p['id'] ?>" class="btn-sm btn-sm-blue">Přepis</a>
                <form method="post"><input type="hidden" name="action" value="shift_page"><input type="hidden" name="album_id" value="<?= $albumId ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="dir" value="-1">
                    <button type="submit" class="btn-sm btn-sm-gray" title="posunout dřív" <?= $i === 0 ? 'disabled' : '' ?>>◀</button></form>
                <form method="post"><input type="hidden" name="action" value="shift_page"><input type="hidden" name="album_id" value="<?= $albumId ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="dir" value="1">
                    <button type="submit" class="btn-sm btn-sm-gray" title="posunout později" <?= $i === count($pages) - 1 ? 'disabled' : '' ?>>▶</button></form>
                <?php if (count($albums) > 1): ?>
                <form method="post" style="display:flex;gap:.25rem">
                    <input type="hidden" name="action" value="move_page"><input type="hidden" name="album_id" value="<?= $albumId ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                    <select name="target_album" class="form-input" style="padding:.2rem .4rem;font-size:.75rem;width:auto">
                        <?php foreach ($albums as $a): if ((int)$a['id'] === $albumId) continue; ?>
                        <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['title'] ?: 'Album #' . (int)$a['id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-sm btn-sm-orange" title="přesunout do jiného alba">→</button>
                </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Smazat fotku i s přepisy?')">
                    <input type="hidden" name="action" value="delete_page"><input type="hidden" name="album_id" value="<?= $albumId ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-sm btn-sm-red">✕</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="admin-card">
    <form method="post" onsubmit="return confirm('Smazat celé album i s fotkami a přepisy?')">
        <input type="hidden" name="action" value="delete_album">
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <button type="submit" class="btn-secondary">Smazat album</button>
        <span class="mistake-hint" style="margin-left:.75rem">Sady, které z něj vznikly, zůstanou — jsou uložené zvlášť.</span>
    </form>
</section>

<?php else: ?>
<section class="admin-card">
    <h2 class="section-title">Nové album</h2>
    <form method="post" class="form-row">
        <input type="hidden" name="action" value="create_album">
        <div class="form-group" style="margin:0">
            <label for="title">Název</label>
            <input type="text" id="title" name="title" class="form-input" placeholder="Project 1 — Unit 3" required>
        </div>
        <div class="form-group" style="margin:0">
            <label for="note">Poznámka</label>
            <input type="text" id="note" name="note" class="form-input" placeholder="slovíčka ze strany 34–35">
        </div>
        <button type="submit" class="btn-primary">Založit a nahrát fotky →</button>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">Alba (<?= count($albums) ?>)</h2>
    <?php if (!$albums): ?>
    <p class="mistake-hint">Zatím žádné album.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Album</th><th>Fotek</th><th>Přepsáno</th><th>Místo</th><th>Založeno</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($albums as $a): ?>
            <tr>
                <td><a href="?album=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['title'] ?: 'Album #' . (int)$a['id']) ?></a>
                    <?php if ($a['note']): ?><br><span class="mistake-hint"><?= htmlspecialchars($a['note']) ?></span><?php endif; ?></td>
                <td><?= (int)$a['page_count'] ?></td>
                <td><?= (int)$a['done_count'] ?>/<?= (int)$a['page_count'] ?></td>
                <td style="font-size:.8rem"><?= number_format((int)$a['bytes'] * 3 / 4 / 1048576, 1, ',', ' ') ?> MB</td>
                <td style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars((string)$a['created_at']) ?></td>
                <td style="display:flex;gap:.4rem">
                    <a href="?album=<?= (int)$a['id'] ?>" class="btn-sm btn-sm-blue">Otevřít</a>
                    <a href="<?= BASE_URL ?>/admin/ocr.php?album=<?= (int)$a['id'] ?>" class="btn-sm btn-sm-green">Přepsat</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endif; ?>

<script>
const OCR_AJAX_URL = '<?= BASE_URL ?>/admin/galerie.php';
</script>
<script src="<?= asset_url('/js/ocr_admin.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
