<?php
/**
 * Vykreslení chybovníku — sdílí ho stránka statistik dítěte i přehled
 * pro rodiče v adminu, ať oba vypadají stejně.
 */
require_once __DIR__ . '/mistakes.php';
require_once __DIR__ . '/levels.php';

/** Čitelný název předmětu podle herního typu (bere se ze správy multiplikátorů) */
function gameTypeLabel(string $type): string {
    $m = getMultipliers();
    return $m[$type]['label'] ?? $type;
}

/**
 * @param array $groups výstup mistakeOverview()
 * @param bool  $withHints u rodiče se vysvětlení hodí, dítěti stačí dvojice
 */
function renderMistakeGroups(array $groups, bool $withHints = true): void {
    foreach ($groups as $g) {
        $label = $g['topic_label'] !== '' ? $g['topic_label'] : gameTypeLabel($g['game_type']);
        ?>
        <div class="mistake-group">
            <div class="mistake-group-head">
                <span class="mistake-group-title">
                    <?= htmlspecialchars(gameTypeLabel($g['game_type'])) ?> · <?= htmlspecialchars($label) ?>
                </span>
                <span class="mistake-group-count">
                    <?= $g['open'] ?> <?= $g['open'] === 1 ? 'úloha' : ($g['open'] < 5 ? 'úlohy' : 'úloh') ?>
                    · <?= $g['wrong_total'] ?>× špatně
                </span>
            </div>
            <?php foreach ($g['items'] as $it):
                // U češtiny a angličtiny je odpověď součástí zadání („Bez mě to
                // nezvládneš."), u matematiky ji musíme doplnit („8 × 7 = 56").
                // Vlastní sady jsou obojí — doplňovačka má mezeru, dvojice ne.
                $shown = match (true) {
                    $g['game_type'] === 'math'  => trim($it['prompt'] . ' ' . $it['correct_answer']),
                    $g['game_type'] === 'sada'  => str_contains($it['prompt'], '_')
                        ? str_replace('_', $it['correct_answer'], $it['prompt'])
                        : $it['prompt'] . ' → ' . $it['correct_answer'],
                    default                     => $it['prompt'],
                };
            ?>
            <div class="mistake-row">
                <span>
                    <span class="mistake-prompt"><?= htmlspecialchars($shown) ?></span>
                    <?php if ($withHints && $it['hint'] !== ''): ?>
                    <div class="mistake-hint"><?= htmlspecialchars($it['hint']) ?></div>
                    <?php endif; ?>
                </span>
                <span class="mistake-times"><?= (int)$it['wrong_count'] ?>×</span>
            </div>
            <?php endforeach; ?>
            <?php if ($g['open'] > count($g['items'])): ?>
            <div class="mistake-hint" style="padding-top:.5rem">
                …a další <?= $g['open'] - count($g['items']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
