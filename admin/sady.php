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
require_once __DIR__ . '/../includes/ocr.php';

$user    = getCurrentUser();
$message = $error = '';
$preview  = null;
$rawJson  = '';
$editRows = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_items') {
        $editId  = (int)($_POST['set_id'] ?? 0);
        $editSet = getSet($editId);
        if (!$editSet) {
            $error = 'Taková sada tu není.';
        } else {
            // Z formuláře složíme tentýž JSON, jaký by přišel z importu —
            // ať úprava projde stejnou kontrolou jako nahrání
            $rows = [];
            foreach (array_values((array)($_POST['prompt'] ?? [])) as $i => $prompt) {
                if (isset($_POST['drop'][$i])) continue;
                $rows[] = [
                    'prompt'  => (string)$prompt,
                    'answer'  => (string)(($_POST['answer'][$i]) ?? ''),
                    'options' => array_map('trim', explode('|', (string)(($_POST['options'][$i]) ?? ''))),
                    'hint'    => (string)(($_POST['hint'][$i]) ?? ''),
                ];
            }
            $checked = parseSetPayload(setPayloadFromItems($editSet, $rows));
            if ($checked['errors']) {
                $error    = 'Sada se nedá uložit — oprav tohle:';
                $preview  = $checked;
                $editRows = $rows;
            } elseif (replaceSetItems($editId, $checked['items'])) {
                $message = 'Uloženo: ' . $editSet['title'] . ' (' . count($checked['items']) . ' položek).';
            } else {
                $error    = 'Uložení selhalo — zkus to znovu.';
                $editRows = $rows;
            }
        }

    } elseif ($action === 'delete') {
        $message = deleteSet((int)($_POST['id'] ?? 0))
            ? 'Sada smazána.'
            : 'Sadu se nepodařilo smazat.';

    } elseif ($action === 'check' || $action === 'save') {
        $rawJson = (string)($_POST['json'] ?? '');
        // Odkud sada pochází — přenáší se z tvorby sad přes náhled až k uložení
        $jobId   = (int)($_POST['job_id'] ?? 0);
        $parsed  = parseSetPayload($rawJson);

        if ($parsed['errors']) {
            $error   = 'Sada se nedá uložit — oprav tohle:';
            $preview = $parsed;
        } elseif ($action === 'save') {
            $id = saveSet($parsed['set'], $parsed['items'], (int)$user['id'], $jobId);
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

$editId  = (int)($_GET['edit'] ?? $_POST['set_id'] ?? 0);
$editSet = $editId ? getSet($editId) : null;
// Po neúspěšném uložení ukazujeme to, co admin napsal, ne to, co je v databázi
$editItems = $editSet ? ($editRows ?? setItems($editId)) : [];

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
            <?php foreach ($preview['items'] as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($it['prompt']) ?></td>
                    <td><strong><?= htmlspecialchars($it['answer']) ?></strong></td>
                    <td><?= $it['options'] ? htmlspecialchars(implode(' · ', json_decode($it['options'], true))) : '<span style="color:var(--muted)">dolosují se ze sady</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" style="margin-top:1rem">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="json" value="<?= htmlspecialchars($rawJson) ?>">
            <input type="hidden" name="job_id" value="<?= (int)($jobId ?? 0) ?>">
            <button type="submit" class="btn-primary">✔ Uložit sadu</button>
            <?php if (!empty($jobId) && ($srcAlbum = getOcrJob($jobId))): ?>
            <span class="mistake-hint" style="margin-left:.75rem">
                podklad: <?= htmlspecialchars($srcAlbum['title'] ?: 'album #' . $jobId) ?>
            </span>
            <?php endif; ?>
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

<?php if ($editSet): ?>
<section class="admin-card">
    <div class="challenge-head">
        <h2 class="section-title" style="margin:0">✎ <?= htmlspecialchars($editSet['title']) ?></h2>
        <a href="<?= BASE_URL ?>/admin/sady.php" class="btn-secondary btn-sm">← zpět na sady</a>
    </div>
    <p class="mistake-hint">
        <?= htmlspecialchars(setSubjectLabel($editSet['subject'])) ?>
        · <?= htmlspecialchars(SET_KINDS[$editSet['kind']] ?? $editSet['kind']) ?>
        · zdroj: <?= htmlspecialchars($editSet['source']) ?>
        <?php if ($editSet['kind'] === 'doplnovacka'): ?><br>Mezeru k doplnění piš podtržítky: <code>______</code><?php endif; ?>
        <br>Možnosti odděluj svislítkem: <code>plynném | kapalném | pevném</code>. Nech prázdné a u dvojic se dolosují ze sady.
        <br>Smazat položku: zaškrtni <strong>✕</strong> na jejím řádku. Sada musí po uložení zůstat aspoň o čtyřech položkách.
    </p>

    <form method="post">
        <input type="hidden" name="action" value="update_items">
        <input type="hidden" name="set_id" value="<?= $editId ?>">
        <table class="data-table" style="margin-top:1rem">
            <thead><tr>
                <th>#</th>
                <th><?= $editSet['kind'] === 'dvojice' ? 'Zadání (a)' : 'Zadání' ?></th>
                <th><?= $editSet['kind'] === 'dvojice' ? 'Odpověď (b)' : 'Odpověď' ?></th>
                <th>Možnosti</th><th>✕</th>
            </tr></thead>
            <tbody>
            <?php foreach ($editItems as $i => $it):
                $opts = $it['options'] ?? null;
                if (is_string($opts)) $opts = json_decode($opts, true);
                $opts = implode(' | ', array_map('strval', (array)$opts)); ?>
                <tr>
                    <td style="color:var(--muted)"><?= $i + 1 ?></td>
                    <td><textarea name="prompt[]" rows="2" class="form-input" style="font-size:.85rem;min-width:16rem"><?= htmlspecialchars((string)$it['prompt']) ?></textarea></td>
                    <td><input type="text" name="answer[]" class="form-input" style="font-size:.85rem" value="<?= htmlspecialchars((string)$it['answer']) ?>"></td>
                    <td><input type="text" name="options[]" class="form-input" style="font-size:.85rem" value="<?= htmlspecialchars($opts) ?>"></td>
                    <td style="text-align:center">
                        <input type="checkbox" name="drop[<?= $i ?>]" value="1" title="smazat položku">
                        <input type="hidden" name="hint[]" value="<?= htmlspecialchars((string)($it['hint'] ?? '')) ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="btn-primary" style="margin-top:1rem">✔ Uložit změny</button>
        <a href="<?= BASE_URL ?>/games/sada.php?id=<?= $editId ?>" class="btn-secondary btn-sm" style="margin-left:.5rem">Vyzkoušet ve hře →</a>
    </form>
</section>
<?php endif; ?>

<section class="admin-card">
    <h2 class="section-title">Nahrané sady (<?= count($sets) ?>)</h2>
    <?php if (!$sets): ?>
    <p class="mistake-hint">Zatím žádná sada.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Předmět</th><th>Název</th><th>Typ</th><th>Ročník</th><th>Položek</th><th>Zdroj</th><th>Podklady</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sets as $s): ?>
            <tr>
                <td><?= htmlspecialchars(setSubjectLabel($s['subject'])) ?></td>
                <td><a href="<?= BASE_URL ?>/games/sada.php?id=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a></td>
                <td><?= htmlspecialchars(SET_KINDS[$s['kind']] ?? $s['kind']) ?></td>
                <td><?= (int)$s['grade'] ?: '–' ?></td>
                <td><?= (int)$s['item_count'] ?></td>
                <td style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars($s['source']) ?></td>
                <td style="font-size:.8rem">
                    <?php if (!empty($s['job_id'])): ?>
                    <a href="<?= BASE_URL ?>/admin/galerie.php?album=<?= (int)$s['job_id'] ?>">otevřít</a>
                    <?php else: ?><span style="color:var(--muted)">–</span><?php endif; ?>
                </td>
                <td>
                    <div class="admin-actions">
                    <a href="?edit=<?= (int)$s['id'] ?>" class="btn-sm btn-sm-blue">Upravit</a>
                    <form method="post" onsubmit="return confirm('Opravdu smazat sadu <?= htmlspecialchars($s['title'], ENT_QUOTES) ?>?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn-secondary btn-sm">Smazat</button>
                    </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
