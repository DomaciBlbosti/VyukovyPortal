<?php
/**
 * Admin — sestavy matematického maratonu.
 *
 * Rodič si poskládá vlastní maraton: zapne kategorie (sčítání do 100,
 * velká násobilka, desetinné číslo × 10 …), řekne kolik příkladů a jestli
 * je na to časový limit. Sestava se pak dětem nabídne na stránce maratonu
 * a dá se zadat jako úkol ve výzvě.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/marathon.php';
require_once __DIR__ . '/../games/data/math.php';

$msg = ''; $msgType = 'ok';
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = saveMarathonPreset(
            (int)($_POST['id'] ?? 0),
            (string)($_POST['title'] ?? ''),
            (array)($_POST['cat'] ?? []),
            (int)($_POST['count'] ?? 100),
            (int)round((float)($_POST['limit_min'] ?? 0) * 60),
        );
        if ($id) { $msg = 'Sestava uložena.'; $editId = $id; }
        else     { $msg = 'Sestava potřebuje název a aspoň jednu zapnutou kategorii.'; $msgType = 'err'; $editId = (int)($_POST['id'] ?? 0); }
    } elseif ($action === 'delete') {
        $msg = deleteMarathonPreset((int)($_POST['id'] ?? 0)) ? 'Sestava smazána.' : 'Sestavu se nepodařilo smazat.';
        $editId = 0;
    }
}

$presets = marathonPresets();
$editing = $editId ? getMarathonPreset($editId) : null;
$cats    = mathMarathonCategories();
$groups  = [];
foreach ($cats as $key => $c) $groups[$c['group']][$key] = $c['label'];
$on = $editing ? array_flip($editing['categories']) : [];

$pageTitle = 'Sestavy maratonu';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
        <h1>🏃 Sestavy <span class="accent">maratonu</span></h1>
        <p class="page-subtitle">Vlastní maratony: které kategorie, kolik příkladů, jaký limit</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/admin/vyzvy.php" class="btn-secondary">🎯 Výzvy</a>
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn-secondary">👥 Uživatelé</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<section class="admin-card" style="margin-bottom:2rem">
    <h2 class="section-title"><?= $editing ? '✏️ Úprava sestavy' : '➕ Nová sestava' ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

        <div class="form-row">
            <label>Název
                <input type="text" name="title" class="form-input" required maxlength="120"
                       placeholder="Např. Sčítání a odčítání do 1000" value="<?= htmlspecialchars($editing['title'] ?? '') ?>">
            </label>
        </div>
        <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap">
            <label class="step-num">Počet příkladů
                <input type="number" name="count" class="form-input" min="<?= MARATHON_MIN_COUNT ?>" max="<?= MARATHON_MAX_COUNT ?>" step="5"
                       value="<?= (int)($editing['count'] ?? 50) ?>">
            </label>
            <label class="step-num">Limit (minut, 0 = bez limitu)
                <input type="number" name="limit_min" class="form-input" min="0" max="<?= MARATHON_MAX_LIMIT / 60 ?>" step="0.5"
                       value="<?= $editing ? rtrim(rtrim(number_format($editing['time_limit'] / 60, 1, '.', ''), '0'), '.') : 0 ?>">
            </label>
        </div>
        <p class="mistake-hint">Když limit vyprší, kolo skončí a nestihnuté příklady se počítají jako chyby — rekord je pak poctivý.</p>

        <h3 class="section-title" style="font-size:.95rem;margin:1.25rem 0 .5rem">Kategorie</h3>
        <div class="marathon-cats">
            <?php foreach ($groups as $group => $items): ?>
            <fieldset class="marathon-cat-group">
                <legend><?= htmlspecialchars($group) ?>
                    <button type="button" class="btn-sm btn-sm-gray cat-all" title="zapnout / vypnout celou skupinu">vše</button>
                </legend>
                <?php foreach ($items as $key => $label): ?>
                <label><input type="checkbox" name="cat[]" value="<?= $key ?>" <?= isset($on[$key]) ? 'checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="btn-primary">💾 Uložit sestavu</button>
            <?php if ($editing): ?>
            <a href="<?= BASE_URL ?>/games/maraton.php?sestava=<?= (int)$editing['id'] ?>" class="btn-secondary">▶ Vyzkoušet</a>
            <a href="<?= BASE_URL ?>/admin/maraton.php" class="btn-secondary">Nová sestava</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-card">
    <h2 class="section-title">📋 Sestavy (<?= count($presets) ?>)</h2>
    <?php if (!$presets): ?>
    <p class="mistake-hint">Zatím žádná. Bez sestavy jede maraton „100 příkladů ze všech témat ročníku".</p>
    <?php else: ?>
    <?php foreach ($presets as $p): ?>
    <div class="mistake-row">
        <span>
            <a href="?edit=<?= (int)$p['id'] ?>"><strong><?= htmlspecialchars($p['title']) ?></strong></a>
            <div class="mistake-hint">
                <?= htmlspecialchars(marathonPresetMeta($p)) ?> ·
                <?= htmlspecialchars(implode(', ', array_map(fn($k) => $cats[$k]['label'] ?? $k, $p['categories']))) ?>
            </div>
        </span>
        <form method="post" onsubmit="return confirm('Opravdu smazat sestavu <?= htmlspecialchars($p['title'], ENT_QUOTES) ?>? Kroky výzev, které na ni míří, zůstanou bez úlohy.')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="btn-secondary" style="padding:.2rem .5rem;font-size:.75rem">smazat</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <p class="mistake-hint" style="margin-top:1rem">Sestavu zadáš dětem jako úkol ve <a href="<?= BASE_URL ?>/admin/vyzvy.php">Výzvách</a> — v nabídce úloh je pod 🏃 Matematický maraton.</p>
</section>

<script>
// „vše" u skupiny: když je něco vypnuté, zapne všechno; když je všechno zapnuté, vypne
document.querySelectorAll('.cat-all').forEach(btn => btn.addEventListener('click', () => {
    const boxes = btn.closest('fieldset').querySelectorAll('input[type=checkbox]');
    const allOn = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allOn);
}));
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
