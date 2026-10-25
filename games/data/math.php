<?php
/**
 * Generátory matematických příkladů rozdělené podle témat.
 *
 * Každé téma má vlastní generátor a seznam ročníků, kterým se nabízí.
 * Odpovědi jsou vždy řetězce, aby se porovnání v JS chovalo předvídatelně
 * (u zlomků a desetinných čísel na tvaru záleží).
 */

/** @return array<string, array{label:string, icon:string, grades:array<int>, variants:array}> */
function mathTopics(): array {
    return [

        'plus_minus' => [
            'label'    => 'Sčítání a odčítání',
            'icon'     => '➕',
            'grades'   => [1,2,3,4,5,6,7,8,9],
            'variants' => ['20' => 'do 20', '100' => 'do 100', '1000' => 'do 1000'],
        ],

        'nasobilka' => [
            'label'    => 'Malá násobilka',
            'icon'     => '✖️',
            'grades'   => [2,3,4,5,6,7,8,9],
            // Trénink po jednotlivých řadách — přesně jak se učí ve škole
            'variants' => ['2'=>'řada 2','3'=>'řada 3','4'=>'řada 4','5'=>'řada 5','6'=>'řada 6',
                           '7'=>'řada 7','8'=>'řada 8','9'=>'řada 9','10'=>'řada 10','mix'=>'celá násobilka'],
        ],

        'velka_nasobilka' => [
            'label'    => 'Velká násobilka',
            'icon'     => '✖️',
            'grades'   => [5,6,7,8,9],
            // 11–20 krát jednociferné — ještě se to dá spočítat z hlavy
            // rozkladem (17 × 6 = 10 × 6 + 7 × 6), násobit 11–20 mezi sebou už ne
            'variants' => ['11_15' => 'řady 11–15', '16_20' => 'řady 16–20', 'mix' => 'celá velká násobilka'],
        ],

        'deleni' => [
            'label'    => 'Dělení se zbytkem',
            'icon'     => '➗',
            'grades'   => [4,5,6,7,8,9],
            'variants' => ['bez' => 'beze zbytku', 'se' => 'se zbytkem'],
        ],

        'desetinna' => [
            'label'    => 'Desetinná čísla',
            'icon'     => '🔟',
            'grades'   => [5,6,7,8,9],
            'variants' => ['scitani'         => 'sčítání a odčítání',
                           'scitani2'        => 'sčítání a odčítání se setinami',
                           'nasobeni'        => 'násobení a dělení 10, 100',
                           'nasobeni_cislem' => 'násobení a dělení jednociferným číslem'],
        ],

        'zlomky' => [
            'label'    => 'Zlomky',
            'icon'     => '½',
            'grades'   => [5,6,7,8,9],
            'variants' => ['cast'    => 'část z celku',
                           'kraceni' => 'krácení zlomků',
                           'scitani' => 'sčítání a odčítání se stejným jmenovatelem',
                           'prevod'  => 'zlomek na desetinné číslo'],
        ],

        'delitelnost' => [
            'label'    => 'Dělitelnost',
            'icon'     => '🔢',
            'grades'   => [6,7,8,9],
            'variants' => ['prvocislo' => 'prvočísla', 'nsd' => 'největší společný dělitel'],
        ],

        'mocniny' => [
            'label'    => 'Mocniny a odmocniny',
            'icon'     => '²',
            'grades'   => [6,7,8,9],
            'variants' => ['druha' => 'druhá mocnina do 20²', 'odmocnina' => 'odmocnina do √400'],
        ],

    ];
}

/** Témata dostupná pro daný ročník (0 = neuvedeno → všechna) */
function mathTopicsForGrade(int $grade): array {
    $all = mathTopics();
    if ($grade < 1) return $all;
    return array_filter($all, fn($t) => in_array($grade, $t['grades'], true));
}

/** Největší společný dělitel (pro zlomky a dělitelnost) */
function mathGcd(int $a, int $b): int {
    $a = abs($a); $b = abs($b);
    while ($b) { [$a, $b] = [$b, $a % $b]; }
    return $a ?: 1;
}

/**
 * Vygeneruje příklady pro téma a variantu.
 * @return array<array{q:string, a:string}>
 */
function generateMathExamples(string $topic, string $variant, int $count = 15): array {
    $out = [];
    $guard = 0;

    while (count($out) < $count && $guard++ < $count * 20) {
        $ex = match ($topic) {
            'nasobilka'       => mathExNasobilka($variant),
            'velka_nasobilka' => mathExVelkaNasobilka($variant),
            'deleni'          => mathExDeleni($variant),
            'desetinna'       => mathExDesetinna($variant),
            'zlomky'          => mathExZlomky($variant),
            'delitelnost'     => mathExDelitelnost($variant),
            'mocniny'         => mathExMocniny($variant),
            default           => mathExPlusMinus($variant),
        };
        // Nechceme dvakrát stejné zadání za sebou ani v jedné sadě
        if ($ex && !in_array($ex['q'], array_column($out, 'q'), true)) {
            $out[] = $ex;
        }
    }
    // Kdyby varianta neuměla dost různých příkladů (např. řada 2 má jen 10),
    // doplň opakováním — lepší kratší sada než nekonečná smyčka.
    while (count($out) < $count && $out) {
        $out[] = $out[array_rand($out)];
    }
    return $out;
}

function mathExPlusMinus(string $variant): array {
    $max = match ($variant) { '20' => 20, '1000' => 1000, default => 100 };
    $a   = rand(1, $max);
    $b   = rand(1, $max);
    if (rand(0, 1)) {
        // sčítání — součet nesmí přesáhnout rozsah
        $a = rand(1, (int)($max * 0.7));
        $b = rand(1, $max - $a);
        return ['q' => "$a + $b =", 'a' => (string)($a + $b)];
    }
    if ($b > $a) [$a, $b] = [$b, $a];   // v tomto věku bez záporných výsledků
    return ['q' => "$a − $b =", 'a' => (string)($a - $b)];
}

function mathExNasobilka(string $variant): array {
    $row = $variant === 'mix' ? rand(2, 10) : max(2, min(10, (int)$variant));
    $b   = rand(1, 10);
    // U konkrétní řady střídej násobení a dělení, ať se procvičí obojí
    if (rand(0, 2) === 0) {
        $product = $row * $b;
        return ['q' => "$product ÷ $row =", 'a' => (string)$b];
    }
    return rand(0, 1)
        ? ['q' => "$row × $b =", 'a' => (string)($row * $b)]
        : ['q' => "$b × $row =", 'a' => (string)($row * $b)];
}

function mathExVelkaNasobilka(string $variant): array {
    [$lo, $hi] = match ($variant) { '11_15' => [11, 15], '16_20' => [16, 20], default => [11, 20] };
    $row = rand($lo, $hi);
    $b   = rand(2, 9);                                   // ×1 a ×10 nic neprocvičí
    if (rand(0, 2) === 0) {
        return ['q' => ($row * $b) . " ÷ $row =", 'a' => (string)$b];
    }
    return rand(0, 1)
        ? ['q' => "$row × $b =", 'a' => (string)($row * $b)]
        : ['q' => "$b × $row =", 'a' => (string)($row * $b)];
}

function mathExDeleni(string $variant): array {
    $divisor = rand(2, 9);
    $result  = rand(2, 20);
    if ($variant === 'se') {
        $rest = rand(1, $divisor - 1);
        $dividend = $divisor * $result + $rest;
        // Zbytek se píše za dvojtečku: 17 ÷ 5 = 3 zb. 2
        return ['q' => "$dividend ÷ $divisor = ?", 'a' => "$result zb $rest"];
    }
    return ['q' => ($divisor * $result) . " ÷ $divisor =", 'a' => (string)$result];
}

function mathExDesetinna(string $variant): array {
    // Počítáme v desetinách/setinách jako s celými čísly a převádíme až na
    // konci — jinak by 0,1 + 0,2 dalo 0,30000000000000004 a rozhodlo o chybě.
    if ($variant === 'nasobeni_cislem') {
        $k = rand(2, 9);
        $tenths = mathTenths();                          // 2,5 × 4
        if (rand(0, 1)) {
            return ['q' => mathFmt($tenths / 10) . " × $k =", 'a' => mathFmt($tenths * $k / 10)];
        }
        // výsledek má jedno desetinné místo
        return ['q' => mathFmt($tenths * $k / 10) . " ÷ $k =", 'a' => mathFmt($tenths / 10)];
    }
    if ($variant === 'scitani2') {
        $a = rand(1, 999);                               // setiny: 3,25
        $b = mathTenths() * 10;                          // desetiny: 1,5 — obojí najednou už není z hlavy
        if (rand(0, 1)) {
            return ['q' => mathFmt($a / 100) . ' + ' . mathFmt($b / 100) . ' =', 'a' => mathFmt(($a + $b) / 100)];
        }
        if ($b > $a) [$a, $b] = [$b, $a];
        return ['q' => mathFmt($a / 100) . ' − ' . mathFmt($b / 100) . ' =', 'a' => mathFmt(($a - $b) / 100)];
    }
    if ($variant === 'nasobeni') {
        $num  = rand(1, 999) / 10;                       // jedno desetinné místo
        $mult = [10, 100][rand(0, 1)];
        if (rand(0, 1)) {
            $res = $num * $mult;
            return ['q' => mathFmt($num) . " × $mult =", 'a' => mathFmt($res)];
        }
        $res = $num / $mult;
        return ['q' => mathFmt($num) . " ÷ $mult =", 'a' => mathFmt($res)];
    }
    $a = rand(1, 500) / 10;
    $b = rand(1, 500) / 10;
    if (rand(0, 1)) {
        return ['q' => mathFmt($a) . ' + ' . mathFmt($b) . ' =', 'a' => mathFmt($a + $b)];
    }
    if ($b > $a) [$a, $b] = [$b, $a];
    return ['q' => mathFmt($a) . ' − ' . mathFmt($b) . ' =', 'a' => mathFmt($a - $b)];
}

/** Desetiny 0,1–9,9 bez celých čísel — „4 ÷ 4" by nic neprocvičilo */
function mathTenths(): int {
    do { $t = rand(1, 99); } while ($t % 10 === 0);
    return $t;
}

/** Desetinné číslo česky (s čárkou) a bez zbytečných nul */
function mathFmt(float $n): string {
    $s = rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    return $s === '' ? '0' : $s;
}

function mathExZlomky(string $variant): array {
    if ($variant === 'scitani') {
        $den = [3, 4, 5, 6, 8, 10, 12][rand(0, 6)];
        if (rand(0, 1)) {
            $n1  = rand(1, $den - 1);
            $n2  = rand(1, $den - $n1);                  // součet nejvýš celek
            $sum = $n1 + $n2;
            return ['q' => "$n1/$den + $n2/$den =", 'a' => mathFrac($sum, $den)];
        }
        $n1 = rand(2, $den - 1);
        $n2 = rand(1, $n1 - 1);
        return ['q' => "$n1/$den − $n2/$den =", 'a' => mathFrac($n1 - $n2, $den)];
    }
    if ($variant === 'prevod') {
        // Jen jmenovatele, které dělí 100 — výsledek má nejvýš dvě desetinná místa
        $den = [2, 4, 5, 10, 20, 25, 50, 100][rand(0, 7)];
        $num = rand(1, $den - 1);
        return ['q' => "$num/$den = ? (des. číslo)", 'a' => mathFmt($num / $den)];
    }
    if ($variant === 'kraceni') {
        $base = rand(2, 9);
        $num  = rand(1, 8);
        $den  = $num + rand(1, 6);
        $g    = mathGcd($num, $den);
        $num /= $g; $den /= $g;                          // základní tvar
        $k = rand(2, 6);
        return ['q' => ($num * $k) . '/' . ($den * $k) . ' = ?', 'a' => "$num/$den"];
    }
    // Část z celku: 3/4 z 20
    $den   = [2, 3, 4, 5, 6, 10][rand(0, 5)];
    $num   = rand(1, $den - 1);
    $whole = $den * rand(2, 12);
    return ['q' => "$num/$den z $whole =", 'a' => (string)($whole / $den * $num)];
}

/** Zlomek v základním tvaru; celek jako „1", nula jako „0" */
function mathFrac(int $num, int $den): string {
    if ($num === 0) return '0';
    $g = mathGcd($num, $den);
    $num = intdiv($num, $g); $den = intdiv($den, $g);
    return $den === 1 ? (string)$num : "$num/$den";
}

function mathExMocniny(string $variant): array {
    $n = rand(2, 20);
    if ($variant === 'odmocnina') {
        return ['q' => '√' . ($n * $n) . ' =', 'a' => (string)$n];
    }
    return ['q' => $n . '² =', 'a' => (string)($n * $n)];
}

function mathExDelitelnost(string $variant): array {
    if ($variant === 'nsd') {
        $g = rand(2, 12);
        $a = $g * rand(2, 9);
        $b = $g * rand(2, 9);
        return ['q' => "NSD($a, $b) =", 'a' => (string)mathGcd($a, $b)];
    }
    // Prvočíslo: kolik má číslo dělitelů? → ptáme se jinak, ano/ne je moc snadné
    $n = rand(10, 99);
    $divisors = 0;
    for ($i = 1; $i <= $n; $i++) if ($n % $i === 0) $divisors++;
    return ['q' => "$n → kolik dělitelů?", 'a' => (string)$divisors];
}

/**
 * Jak má dítě napsat odpověď — tam, kde to z příkladu není vidět.
 * Hra ji ukazuje pod políčkem, maraton u každého příkladu zvlášť, protože
 * se v něm témata střídají.
 */
function mathAnswerHint(string $topic, string $variant): string {
    return match (true) {
        $topic === 'deleni'      && $variant === 'se'        => 'piš ve tvaru „3 zb 2"',
        $topic === 'zlomky'      && $variant === 'prevod'    => 'desetinné číslo s čárkou, např. 0,25',
        $topic === 'zlomky'      && $variant === 'cast'      => '',
        $topic === 'zlomky'                                  => 'zlomek piš ve tvaru 3/4, celek jako 1',
        $topic === 'desetinna'                               => 'desetinné číslo piš s čárkou, např. 4,5',
        $topic === 'delitelnost' && $variant === 'nsd'       => 'největší společný dělitel',
        $topic === 'delitelnost'                             => 'kolik má číslo dělitelů (včetně 1 a sebe sama)',
        default => '',
    };
}

/**
 * Nabídka šesti možností pro režim výběru.
 * U číselných odpovědí vytváří blízké hodnoty, u textových (zbytky, zlomky)
 * pozmění jednu složku — aby byly plausibilní, ale jednoznačně chybné.
 */
function mathChoices(string $answer): array {
    // Celé číslo → blízké hodnoty
    if (preg_match('/^-?\d+$/', $answer)) {
        $n   = (int)$answer;
        $set = [$answer => true];
        $cand = [$n+1, $n-1, $n+2, $n-2, $n+10, $n-10, $n+5, $n-5, $n+3, $n-3, $n*2];
        shuffle($cand);
        foreach ($cand as $c) {
            if (count($set) >= 6) break;
            if ($c >= 0) $set[(string)$c] = true;
        }
        $i = 1;
        while (count($set) < 6) $set[(string)($n + 20 + $i++)] = true;
        $out = array_map('strval', array_keys($set));
        shuffle($out);
        return $out;
    }

    // Zbytek po dělení: "3 zb 2"
    if (preg_match('/^(\d+) zb (\d+)$/', $answer, $m)) {
        [$r, $q, $rest] = [$answer, (int)$m[1], (int)$m[2]];
        $set = [$r => true];
        foreach ([[$q+1,$rest],[$q-1,$rest],[$q,$rest+1],[$q,max(0,$rest-1)],[$q+1,max(0,$rest-1)],[$q-1,$rest+1]] as [$qq,$rr]) {
            if (count($set) >= 6) break;
            if ($qq > 0 && $rr >= 0) $set["$qq zb $rr"] = true;
        }
        $i = 2;
        while (count($set) < 6) { $set[($q + $i) . " zb $rest"] = true; $i++; }
        $out = array_map('strval', array_keys($set));
        shuffle($out);
        return $out;
    }

    // Zlomek: "3/4"
    if (preg_match('#^(\d+)/(\d+)$#', $answer, $m)) {
        [$num, $den] = [(int)$m[1], (int)$m[2]];
        $set = [$answer => true];
        foreach ([[$num+1,$den],[$num,$den+1],[$den,$num],[$num+1,$den+1],[max(1,$num-1),$den],[$num,max(2,$den-1)]] as [$nn,$dd]) {
            if (count($set) >= 6) break;
            if ($nn > 0 && $dd > 1 && $nn !== $num || $dd !== $den) $set["$nn/$dd"] = true;
        }
        $i = 2;
        while (count($set) < 6) { $set[($num + $i) . '/' . ($den + $i)] = true; $i++; }
        $out = array_map('strval', array_keys($set));
        shuffle($out);
        return $out;
    }

    // Desetinné číslo s čárkou
    $val = (float)str_replace(',', '.', $answer);
    $set = [$answer => true];
    foreach ([0.1, -0.1, 1, -1, 10, 0.5] as $d) {
        if (count($set) >= 6) break;
        $c = $val + $d;
        if ($c >= 0) $set[mathFmt($c)] = true;
    }
    $i = 2;
    while (count($set) < 6) { $set[mathFmt($val + $i)] = true; $i++; }
    $out = array_map('strval', array_keys($set));
    shuffle($out);
    return $out;
}

/**
 * Vymění v kole příklady za ty, které dítě naposled splétlo.
 *
 * Příklady se losují, takže je server zpětně nesestaví z datové sady —
 * zadání i výsledek si ale pamatuje sám chybovník, což pro obnovení
 * příkladu stačí.
 *
 * @param array $examples kolo, mění se na místě
 * @param array $rows     řádky z mistakesForPractice()
 * @return int kolik příkladů se povedlo vyměnit
 */
function mathInjectPractice(array &$examples, array $rows): int {
    if (!$rows || !$examples) return 0;

    $present = array_column($examples, 'q');
    $wanted  = array_column($rows, 'prompt');
    $done    = 0;

    foreach ($rows as $r) {
        $q = (string)($r['prompt'] ?? '');
        $a = (string)($r['correct_answer'] ?? '');
        if ($q === '' || $a === '' || in_array($q, $present, true)) continue;

        foreach ($examples as $i => $ex) {
            if (in_array($ex['q'], $wanted, true)) continue; // tuhle už opakujeme
            $examples[$i] = ['q' => $q, 'a' => $a];
            $present[$i]  = $q;
            $done++;
            break;
        }
    }
    return $done;
}

/**
 * Příklady z několika variant dohromady (např. řada 6 + řada 7).
 * Z každé varianty se vezme stejný díl a výsledek se zamíchá, ať se řady
 * střídají a dítě nemůže jet jednu řadu ze setrvačnosti.
 *
 * @param array<string> $variants klíče variant
 * @return array<array{q:string,a:string,variant:string}>
 */
function generateMathExamplesMulti(string $topic, array $variants, int $count): array {
    $variants = array_values(array_filter($variants, fn($v) => isset(mathTopics()[$topic]['variants'][$v])));
    if (!$variants) return [];

    $per = (int)ceil($count / count($variants));
    $all = [];
    foreach ($variants as $v) {
        foreach (generateMathExamples($topic, (string)$v, $per) as $ex) {
            $ex['variant'] = (string)$v;
            $all[] = $ex;
        }
    }
    shuffle($all);
    return array_slice($all, 0, $count);
}

/** Klíč příkladu pro chybovník — nese téma i variantu, ať jde chyba dohledat */
function mathItemKey(string $topic, string $variant, string $question): string {
    return $topic . ':' . $variant . ':' . $question;
}

/** Z klíče zpátky variantu; vrací null, když klíč nepatří k povoleným */
function mathVariantFromKey(string $key, string $topic, array $allowed): ?string {
    $prefix = $topic . ':';
    if (!str_starts_with($key, $prefix)) return null;
    $rest = substr($key, strlen($prefix));
    $pos  = strpos($rest, ':');
    if ($pos === false) return null;
    $variant = substr($rest, 0, $pos);
    return in_array($variant, array_map('strval', $allowed), true) ? $variant : null;
}

/**
 * Z klíče příkladu zpátky téma a variantu. Otázka v klíči dvojtečku
 * neobsahuje, tak stačí rozdělit podle prvních dvou.
 *
 * @return ?array{topic:string, variant:string}
 */
function mathSplitKey(string $key): ?array {
    $parts = explode(':', $key, 3);
    if (count($parts) < 3) return null;
    [$topic, $variant] = $parts;
    if (!isset(mathTopics()[$topic]['variants'][$variant])) return null;
    return ['topic' => $topic, 'variant' => $variant];
}

/**
 * Z čeho se skládá maraton pro daný ročník: každé téma, které ročník má,
 * a z něj varianty, které se nepřekrývají. Řady násobilky zastupuje
 * „celá násobilka", jinak by jich bylo deset a zbytek témat by zanikl.
 * Sčítání do 20 od čtvrté třídy vynecháváme — v maratonu má být co počítat.
 *
 * @return array<array{string,string}> dvojice [téma, varianta]
 */
function mathMarathonPairs(int $grade): array {
    $pairs = [];
    foreach (mathTopicsForGrade($grade) as $tKey => $t) {
        $variants = isset($t['variants']['mix']) ? ['mix'] : array_keys($t['variants']);
        foreach ($variants as $v) {
            if ($tKey === 'plus_minus' && (string)$v === '20' && $grade >= 4) continue;
            $pairs[] = [$tKey, (string)$v];
        }
    }
    return $pairs;
}

/**
 * Sto příkladů napříč všemi tématy ročníku, rovným dílem a zamíchaných.
 * Každý příklad nese téma, variantu a klíč jako v běžné hře, takže chyby
 * padají do chybovníku ke správné sadě a objeví se při jejím tréninku.
 *
 * @return array<array{q:string, a:string, topic:string, variant:string, key:string, hint:string}>
 */
function generateMathMarathon(int $grade, int $count = 100): array {
    $pairs = mathMarathonPairs($grade);
    if (!$pairs) return [];

    // Díl se počítá na téma, ne na variantu — jinak by desetinná čísla se
    // čtyřmi variantami zabrala čtyřikrát víc místa než násobilka s jednou
    $byTopic = [];
    foreach ($pairs as [$topic, $variant]) $byTopic[$topic][] = $variant;
    $perTopic = (int)ceil($count / count($byTopic));

    $all = [];
    foreach ($byTopic as $topic => $variants) {
        $per = (int)ceil($perTopic / count($variants));
        foreach ($variants as $variant) {
            foreach (generateMathExamples($topic, $variant, $per) as $ex) {
                $ex['topic']   = $topic;
                $ex['variant'] = $variant;
                $ex['key']     = mathItemKey($topic, $variant, $ex['q']);
                $ex['hint']    = mathAnswerHint($topic, $variant);
                $all[] = $ex;
            }
        }
    }
    shuffle($all);
    return array_slice($all, 0, $count);
}
