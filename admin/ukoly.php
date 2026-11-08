<?php
/**
 * Admin — domácí úkoly.
 *
 * Rodič každému dítěti založí plán: jaké úlohy (sady z učebnic, násobilka,
 * maraton …), kolikrát a jak přesně, ve které dny, a jestli se mají ostatní
 * hry zamknout, dokud úkol není hotový. Tady je i přehled posledních dnů —
 * co bylo splněno, co ne — a tlačítko na odpuštění restů.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/homework.php';

$db  = getDB();
$msg = ''; $msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'save_plan':
            $in = $_POST + ['created_by' => (int)getCurrentUser()['id']];
            $id = saveHomeworkPlan($in, (array)($_POST['task'] ?? []), (int)($_POST['id'] ?? 0), $err);
            if ($id) { $msg = 'Plán uložen.'; $_GET['edit'] = $id; }
            else     { $msg = 'Chyba: ' . $err; $msgType = 'err'; }
            break;
        case 'delete_plan':
            $msg = deleteHomeworkPlan((int)($_POST['id'] ?? 0)) ? 'Plán smazán.' : 'Plán se nepodařilo smazat.';
            break;
        case 'forgive':
            $n = forgiveHomework((int)($_POST['id'] ?? 0));
            $msg = $n ? 'Odpuštěno restů: ' . $n . '.' : 'Žádné resty k odpuštění.';
            break;
    }
}

$kids    = $db->query('SELECT id, display_name, username, grade FROM users WHERE is_active = 1 AND is_admin = 0 ORDER BY display_name')->fetchAll();
$plans   = homeworkPlans();
$editId  = (int)($_GET['edit'] ?? 0);
$editing = $editId ? getHomeworkPlan($editId) : null;
$newFor  = (int)($_GET['new'] ?? 0);
$showForm = $editing || $newFor || !$plans;

// Dnešní úkoly dětí (založí je, pokud ještě nejsou — rodič tak vidí totéž co dítě)
$todayByUser = [];
foreach ($kids as $k) $todayByUser[(int)$k['id']] = homeworkStatus((int)$k['id']);

$pageTitle = 'Domácí úkoly';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📌 Domácí <span class="accent">úkoly</span></h1>
    <p class="page-subtitle">Co má každé dítě splnit každý den</p>
</div>

<?php if ($msg): ?><div class="alert <?= $msgType === 'ok' ? 'alert-success' : 'alert-error' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<p style="margin-bottom:1rem"><a href="<?= BASE_URL ?>/admin/index.php">← Zpět na správu</a></p>

<?php if (!$kids): ?>
<section class="admin-card"><p class="mistake-hint">Zatím tu není žádné dítě — založ ho ve správě uživatelů.</p></section>
<?php endif; ?>

<?php foreach ($kids as $k): $mine = array_values(array_filter($plans, fn($p) => (int)$p['user_id'] === (int)$k['id'])); $st = $todayByUser[(int)$k['id']]; ?>
<section class="admin-card" style="margin-bottom:1.5rem">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0"><?= htmlspecialchars($k['display_name'] ?: $k['username']) ?>
            <span class="mistake-hint" style="font-weight:normal"><?= $k['grade'] ? (int)$k['grade'] . '. třída' : '' ?></span></h2>
        <a href="?new=<?= (int)$k['id'] ?>#plan" class="btn-secondary btn-sm">+ nový plán</a>
    </div>

    <?php if (!$mine): ?>
    <p class="mistake-hint" style="margin-top:.75rem">Bez domácího úkolu.</p>
    <?php else: ?>
    <?php if ($st['today'] || $st['overdue']): ?>
    <p style="margin:.75rem 0 .25rem;font-size:.9rem"><strong>Dnes:</strong>
        <?php foreach ($st['today'] as $r): ?>
        <span style="margin-right:.75rem"><?= $r['done'] ? '✔' : '▢' ?> <?= htmlspecialchars($r['label']) ?><?= $r['done'] ? '' : ' <span class="mistake-hint">(' . $r['done_rounds'] . '/' . $r['rounds'] . '×' . ((float)$r['best_accuracy'] > 0 ? ', zatím ' . round($r['best_accuracy']) . ' %' : '') . ')</span>' ?></span>
        <?php endforeach; ?>
        <?php if (!$st['today']): ?><span class="mistake-hint">dnes nic naplánováno</span><?php endif; ?>
        <?php if ($st['overdue']): ?><span style="color:var(--danger)">· resty: <?= count($st['overdue']) ?></span><?php endif; ?>
    </p>
    <?php endif; ?>

    <?php foreach ($mine as $p): $hist = homeworkHistory($p); $overdue = homeworkOverdueCount((int)$p['id']); ?>
    <div class="challenge-card" style="margin-top:.75rem <?= (int)$p['active'] ? '' : ';opacity:.6' ?>">
        <div class="challenge-head">
            <span class="challenge-title"><?= htmlspecialchars($p['title']) ?>
                <?php if (!(int)$p['active']): ?><span class="mistake-hint">· vypnutý</span><?php endif; ?>
                <?php if ((int)$p['lock_games']): ?><span class="mistake-hint" title="dokud není úkol hotový, ostatní hry jsou zamčené">· 🔒 zamyká hry</span><?php endif; ?>
            </span>
            <span style="display:flex;gap:.4rem;flex-wrap:wrap">
                <a href="?edit=<?= (int)$p['id'] ?>#plan" class="btn-sm btn-sm-blue">upravit</a>
                <?php if ($overdue): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Odpustit <?= $overdue ?> restů?')">
                    <input type="hidden" name="action" value="forgive"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-sm" title="smazat nesplněné úkoly z minulých dnů">odpustit resty (<?= $overdue ?>)</button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Smazat plán „<?= htmlspecialchars(addslashes($p['title'])) ?>“ i s historií?')">
                    <input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-sm btn-sm-red">✕</button>
                </form>
            </span>
        </div>
        <p class="mistake-hint" style="margin:.25rem 0 .5rem">
            <?= htmlspecialchars(HOMEWORK_MODES[$p['mode']] ?? $p['mode']) ?> ·
            <?= (int)$p['rounds'] ?>× · min <?= (int)$p['min_accuracy'] ?> % ·
            <?php $dl = []; foreach (HOMEWORK_WEEKDAYS as $i => $w) if (($p['weekdays'][$i] ?? '1') === '1') $dl[] = $w; echo count($dl) === 7 ? 'každý den' : htmlspecialchars(implode(' ', $dl)); ?>
        </p>
        <ol style="margin:0 0 .75rem 1.2rem;font-size:.9rem">
            <?php foreach ($p['items'] as $it): ?>
            <li><?= htmlspecialchars(catalogLabel($it['game_type'], $it['topic'])) ?></li>
            <?php endforeach; ?>
        </ol>
        <div class="hw-history" title="posledních 14 dní: zelená splněno, žlutá částečně, červená nesplněno, šedá nenaplánováno">
            <?php foreach ($hist as $h): ?>
            <div class="hw-day <?= $h['state'] ?>"><span><?= ['done' => '✔', 'partial' => '½', 'open' => '✘', 'missed' => '✘', 'today' => '…', 'off' => '·'][$h['state']] ?></span><?= $h['weekday'] ?><br><?= $h['label'] ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if ($showForm && $kids): $p = $editing ?: ['user_id' => $newFor ?: (int)$kids[0]['id'], 'title' => 'Domácí úkol', 'mode' => 'rotate', 'rounds' => 1, 'min_accuracy' => 80, 'weekdays' => '1111111', 'lock_games' => 1, 'active' => 1, 'items' => []]; ?>
<section class="admin-card" id="plan" style="margin-bottom:2rem">
    <h2 class="section-title"><?= $editing ? '✏️ Upravit plán' : '➕ Nový plán' ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="save_plan">
        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
        <div class="form-row" style="flex-direction:row;flex-wrap:wrap;gap:.75rem">
            <div class="form-group" style="margin:0">
                <label for="user_id">Dítě</label>
                <select id="user_id" name="user_id" class="form-input">
                    <?php foreach ($kids as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === (int)$p['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['display_name'] ?: $k['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:12rem">
                <label for="title">Název</label>
                <input type="text" id="title" name="title" class="form-input" value="<?= htmlspecialchars($p['title']) ?>" placeholder="Domácí úkol">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:16rem">
                <label for="mode">Jak zadávat</label>
                <select id="mode" name="mode" class="form-input">
                    <?php foreach (HOMEWORK_MODES as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $key === $p['mode'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row" style="flex-direction:row;flex-wrap:wrap;gap:.75rem;margin-top:.75rem;align-items:flex-end">
            <div class="form-group" style="margin:0"><label for="rounds">Kolikrát</label>
                <input type="number" id="rounds" name="rounds" class="form-input" style="width:5rem" min="1" max="20" value="<?= (int)$p['rounds'] ?>"></div>
            <div class="form-group" style="margin:0"><label for="min_accuracy">Min. přesnost %</label>
                <input type="number" id="min_accuracy" name="min_accuracy" class="form-input" style="width:5.5rem" min="0" max="100" step="5" value="<?= (int)$p['min_accuracy'] ?>"></div>
            <div class="form-group" style="margin:0"><label>Dny</label>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <?php foreach (HOMEWORK_WEEKDAYS as $i => $w): ?>
                    <label class="ocr-pick-line" style="padding:.35rem 0"><input type="checkbox" name="weekdays[<?= $i ?>]" value="1" <?= ($p['weekdays'][$i] ?? '1') === '1' ? 'checked' : '' ?>> <?= $w ?></label>
                <?php endforeach; ?>
                </div></div>
            <label class="ocr-pick-line" style="padding:.35rem 0"><input type="checkbox" name="lock_games" value="1" <?= (int)$p['lock_games'] ? 'checked' : '' ?>> 🔒 dokud není úkol hotový, ostatní hry jsou zamčené</label>
            <label class="ocr-pick-line" style="padding:.35rem 0"><input type="checkbox" name="active" value="1" <?= (int)$p['active'] ? 'checked' : '' ?>> plán je zapnutý</label>
        </div>

        <h3 class="section-title" style="font-size:1rem;margin-top:1.25rem">Úlohy</h3>
        <p class="mistake-hint" style="margin-bottom:.5rem">Při zadávání „postupně dokola“ dostane dítě každý naplánovaný den další úlohu v tomhle pořadí; po poslední se začne znovu od první.
            Sady z učebnic jsou v nabídce dole pod 📚.</p>
        <div id="stepList">
            <?php $items = $p['items'] ?: [['game_type' => 'math', 'topic' => 'nasobilka/2']];
            foreach ($items as $it): ?>
            <div class="step-row">
                <select name="task[]" class="form-input step-task"><?php catalogTaskOptions($it['game_type'] . '|' . $it['topic']); ?></select>
                <button type="button" class="btn-secondary step-remove" title="Odebrat úlohu">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
            <button type="button" id="addStep" class="btn-secondary">➕ Přidat úlohu</button>
            <button type="submit" class="btn-primary">💾 Uložit plán</button>
            <?php if ($editing || $newFor): ?><a href="<?= BASE_URL ?>/admin/ukoly.php" class="btn-secondary">Zpět</a><?php endif; ?>
        </div>
    </form>
</section>
<script>
document.getElementById('addStep').addEventListener('click', () => {
    const list = document.getElementById('stepList');
    const row  = list.querySelector('.step-row').cloneNode(true);
    row.querySelector('.step-task').selectedIndex = 0;
    list.appendChild(row);
});
document.getElementById('stepList').addEventListener('click', (e) => {
    if (!e.target.classList.contains('step-remove')) return;
    const list = document.getElementById('stepList');
    if (list.querySelectorAll('.step-row').length > 1) e.target.closest('.step-row').remove();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
