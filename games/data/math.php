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
            'variants' => ['scitani' => 'sčítání a odčítání', 'nasobeni' => 'násobení a dělení 10, 100'],
        ],

        'zlomky' => [
            'label'    => 'Zlomky',
            'icon'     => '½',
            'grades'   => [5,6,7,8,9],
            'variants' => ['cast' => 'část z celku', 'kraceni' => 'krácení zlomků'],
        ],

        'delitelnost' => [
            'label'    => 'Dělitelnost',
            'icon'     => '🔢',
            'grades'   => [6,7,8,9],
            'variants' => ['prvocislo' => 'prvočísla', 'nsd' => 'největší společný dělitel'],
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
            'nasobilka'   => mathExNasobilka($variant),
            'deleni'      => mathExDeleni($variant),
            'desetinna'   => mathExDesetinna($variant),
            'zlomky'      => mathExZlomky($variant),
            'delitelnost' => mathExDelitelnost($variant),
            default       => mathExPlusMinus($variant),
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

/** Desetinné číslo česky (s čárkou) a bez zbytečných nul */
function mathFmt(float $n): string {
    $s = rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    return $s === '' ? '0' : $s;
}

function mathExZlomky(string $variant): array {
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
