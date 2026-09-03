<?php
/**
 * Vícenásobný výběr sad ve hrách.
 *
 * Do teď šlo vybrat vždycky jen jednu sadu — buď „řada 6", nebo rovnou celá
 * násobilka. Přitom se ve škole trénuje typicky pár řad dohromady. Výběr se
 * proto v adrese píše jako seznam („v=6,7") a tlačítka se přepínají.
 */

/**
 * Rozparsuje parametr se seznamem klíčů. Bere jen ty, které v nabídce
 * opravdu existují; když nezbude nic, vrátí výchozí (první) položku.
 *
 * @param array $available nabídka (klíč => cokoliv)
 * @return array<string> vybrané klíče v pořadí nabídky
 */
function parseSelection(?string $raw, array $available, ?string $default = null): array {
    $wanted = [];
    foreach (explode(',', (string)$raw) as $k) {
        $k = trim($k);
        if ($k !== '' && array_key_exists($k, $available)) $wanted[(string)$k] = true;
    }

    // Zachovej pořadí nabídky, ne pořadí v adrese — ať tlačítka a popisek sedí
    $out = [];
    foreach (array_keys($available) as $k) {
        if (isset($wanted[(string)$k])) $out[] = (string)$k;
    }
    if ($out) return $out;

    if ($default !== null && array_key_exists($default, $available)) return [$default];
    $first = array_key_first($available);
    return $first === null ? [] : [(string)$first];
}

/**
 * Výběr po klepnutí na tlačítko: položku přidá, nebo odebere.
 * Poslední vybranou položku odebrat nejde — prázdný výběr nedává smysl.
 *
 * @return string seznam pro adresu („6,7")
 */
function toggleSelection(array $current, string $key, array $available): string {
    $current = array_map('strval', $current);
    $next = in_array($key, $current, true)
        ? array_values(array_diff($current, [$key]))
        : array_merge($current, [$key]);

    if (!$next) $next = [$key];   // odkliknout poslední sadu nedovolíme

    // Seřaď podle pořadí v nabídce, ať adresa vypadá pokaždé stejně
    $order = array_flip(array_map('strval', array_keys($available)));
    usort($next, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
    return implode(',', $next);
}

/** Popisek vybraných sad: „řada 6 + řada 7" */
function selectionLabel(array $keys, array $available, int $maxParts = 3): string {
    $labels = [];
    foreach ($keys as $k) {
        $v = $available[$k] ?? null;
        $labels[] = is_array($v) ? ($v['label'] ?? (string)$k) : (string)($v ?? $k);
    }
    if (count($labels) > $maxParts) {
        $rest = count($labels) - $maxParts;
        $labels = array_slice($labels, 0, $maxParts);
        return implode(' + ', $labels) . ' + ' . $rest . ' další';
    }
    return implode(' + ', $labels);
}
