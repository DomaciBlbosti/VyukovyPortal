<?php
/**
 * Správa vlastních sad — import obsahu z učebnic.
 *
 * Nahrává se JSON. Nejdřív se ukáže náhled: co se rozpoznalo, kolik položek
 * a jestli něco nesedí. Uloží se až na druhé potvrzení, aby se do databáze
 * nedostala sada s překlepem v půlce slovíček.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/sets.php';

$user    = getCurrentUser();
$message = $error = '';
$preview = null;
$rawJson = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $message = deleteSet((int)($_POST['id'] ?? 0))
            ? 'Sada smazána.'
            : 'Sadu se nepodařilo smazat.';

    } elseif ($action === 'check' || $action === 'save') {
        $rawJson = (string)($_POST['json'] ?? '');
        $parsed  = parseSetPayload($rawJson);

        if ($parsed['errors']) {
            $error   = 'Sada se nedá uložit — oprav tohle:';
            $preview = $parsed;
        } elseif ($action === 'save') {
            $id = saveSet($parsed['set'], $parsed['items'], (int)$user['id']);
            if ($id) {
                $message = 'Uloženo: ' . $parsed['set']['title'] . ' (' . count($parsed['items']) . ' položek).';
                $rawJson = '';
            } else {
                $error   = 'Uložení selhalo — zkus to znovu.';
                $preview = $parsed;
            }
        } else {
            $preview = $parsed;   // vše v pořádku, ukaž náhled k potvrzení
        }
    }
}

$sets = listSets();

$pageTitle = 'Sady z učebnic';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📚 Sady z <span class="accent">učebnic</span></h1>
    <p class="page-subtitle">Nahraj slovíčka nebo učivo jako JSON — hned je půjde hrát i zadat ve výzvě</p>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($preview && $preview['errors']): ?>
<div class="alert alert-error">
    <ul style="margin:.25rem 0 0 1.1rem">
        <?php foreach (array_slice($preview['errors'], 0, 15) as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
        <?php if (count($preview['errors']) > 15): ?>
        <li>… a dalších <?= count($preview['errors']) - 15 ?></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($preview && !$preview['errors']): ?>
<section class="admin-card">
    <h2 class="section-title">Náhled — zkontroluj a potvrď</h2>
    <div class="challenge-card">
        <div class="challenge-head">
            <span class="challenge-title">
                <?= htmlspecialchars(setSubjectLabel($preview['set']['subject'])) ?>
                · <?= htmlspecialchars($preview['set']['title']) ?>
            </span>
            <span class="challenge-meta"><?= count($preview['items']) ?> položek</span>
        </div>
        <div class="mistake-hint">
            <?= htmlspecialchars(SET_KINDS[$preview['set']['kind']]) ?>
            · zdroj: <?= htmlspecialchars($preview['set']['source']) ?>
            · <?= $preview['set']['grade'] ? (int)$preview['set']['grade'] . '. třída' : 'pro všechny ročníky' ?>
        </div>

        <table class="data-table" style="margin-top:1rem">
            <thead><tr><th>#</th><th>Zadání</th><th>Odpověď</th><th>Možnosti</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($preview['items'], 0, 12) as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($it['prompt']) ?></td>
                    <td><strong><?= htmlspecialchars($it['answer']) ?></strong></td>
                    <td><?= $it['options'] ? htmlspecialchars(implode(' · ', json_decode($it['options'], true))) : '<span style="color:var(--muted)">dolosují se ze sady</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($preview['items']) > 12): ?>
        <div class="mistake-hint">… a dalších <?= count($preview['items']) - 12 ?> položek</div>
        <?php endif; ?>

        <form method="post" style="margin-top:1rem">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="json" value="<?= htmlspecialchars($rawJson) ?>">
            <button type="submit" class="btn-primary">✔ Uložit sadu</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="admin-card">
    <h2 class="section-title">Nahrát sadu</h2>
    <form method="post">
        <input type="hidden" name="action" value="check">
        <div class="form-group">
            <label for="json">JSON se sadou</label>
            <textarea id="json" name="json" rows="14" class="form-input" style="font-family:monospace;font-size:.85rem"
                      placeholder='{"predmet":"anglictina", ...}'><?= htmlspecialchars($rawJson) ?></textarea>
        </div>
        <button type="submit" class="btn-primary">Zkontrolovat →</button>
    </form>

    <details style="margin-top:1.25rem">
        <summary style="cursor:pointer;color:var(--accent)">Jak má JSON vypadat</summary>
        <p class="mistake-hint" style="margin:.75rem 0">
            Předmět: <?= implode(', ', array_keys(SET_SUBJECTS)) ?>.
            Typ: <?= implode(', ', array_keys(SET_KINDS)) ?>.
            Ročník 0 znamená „pro všechny". Zdroj je povinný.
        </p>
<pre style="background:var(--bg3);padding:1rem;border-radius:8px;overflow-x:auto;font-size:.8rem">{
  "predmet": "anglictina",
  "rocnik": 6,
  "nazev": "Unit 3 — slovíčka",
  "zdroj": "Project 1, 4. vydání, Unit 3",
  "typ": "dvojice",
  "polozky": [
    {"a": "kitchen", "b": "kuchyně"},
    {"a": "upstairs", "b": "nahoře (v patře)", "napoveda": "opak je downstairs"}
  ]
}

{
  "predmet": "fyzika", "rocnik": 6,
  "nazev": "Jednotky délky", "zdroj": "Fyzika 6, kap. 2",
  "typ": "vyber",
  "polozky": [
    {"otazka": "Jaká je jednotka délky v SI?",
     "odpoved": "metr", "moznosti": ["metr", "litr", "kilogram"]}
  ]
}

{
  "predmet": "anglictina", "rocnik": 6,
  "nazev": "Unit 3 — fráze", "zdroj": "Project 1, Unit 3",
  "typ": "doplnovacka",
  "polozky": [
    {"veta": "How _ are you?", "odpoved": "old",
     "moznosti": ["old", "much", "many"]}
  ]
}</pre>
    </details>
</section>

<section class="admin-card">
    <h2 class="section-title">Nahrané sady (<?= count($sets) ?>)</h2>
    <?php if (!$sets): ?>
    <p class="mistake-hint">Zatím žádná sada.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Předmět</th><th>Název</th><th>Typ</th><th>Ročník</th><th>Položek</th><th>Zdroj</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sets as $s): ?>
            <tr>
                <td><?= htmlspecialchars(setSubjectLabel($s['subject'])) ?></td>
                <td><a href="<?= BASE_URL ?>/games/sada.php?id=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a></td>
                <td><?= htmlspecialchars(SET_KINDS[$s['kind']] ?? $s['kind']) ?></td>
                <td><?= (int)$s['grade'] ?: '–' ?></td>
                <td><?= (int)$s['item_count'] ?></td>
                <td style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars($s['source']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Opravdu smazat sadu <?= htmlspecialchars($s['title'], ENT_QUOTES) ?>?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn-secondary btn-sm">Smazat</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
