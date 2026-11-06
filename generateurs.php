<?php

declare(strict_types=1);

/**
 * Générateurs de questions : chaque appel fabrique une question avec des valeurs NEUVES
 * (calculées par PHP lui-même, donc toujours exactes).
 *
 * Format : 'nom' => [chapitre, fonction]. Les fonctions creerQcm() / creerSaisie() / alea() / tirer() /
 * melanger() viennent de quiz.php. Une ligne qui commence par « | » est affichée comme du code.
 */

/** @param list<int|string> $t */
function listePhp(array $t): string
{
    return '[' . implode(', ', array_map(static fn (int|string $v): string => is_string($v) ? "'$v'" : (string) $v, $t)) . ']';
}

/** @param list<int|string> $t */
function variantesListe(array $t): array
{
    $avec = '[' . implode(',', array_map(static fn (int|string $v): string => (string) $v, $t)) . ']';

    return [$avec, implode(',', array_map(static fn (int|string $v): string => (string) $v, $t))];
}

/** Valeur PHP écrite comme la console JavaScript l'affiche : "texte", 12, 2.5, true, null, NaN, Infinity. */
function litteralJs(mixed $v): string
{
    return match (true) {
        is_string($v) => '"' . $v . '"',
        is_bool($v) => $v ? 'true' : 'false',
        $v === null => 'null',
        is_float($v) && is_nan($v) => 'NaN',
        is_float($v) && is_infinite($v) => $v > 0 ? 'Infinity' : '-Infinity',
        is_float($v) && floor($v) === $v => (string) (int) $v,
        default => (string) $v,
    };
}

/** @param list<int|string> $t */
function listeJs(array $t): string
{
    return '[' . implode(', ', array_map('litteralJs', $t)) . ']';
}

return [

    // ───────── 01 · Variables, types, calculs ─────────
    'modulo' => ['01', static function (): array {
        $a = alea(11, 99);
        $b = alea(3, 9);

        return creerSaisie('', ['Que vaut le résultat de cette expression ?', "|echo $a % $b;"], (string) ($a % $b),
            sprintf('%d = %d × %d + %d : le modulo est le reste de la division entière.', $a, $b, intdiv($a, $b), $a % $b));
    }],

    'intdiv' => ['01', static function (): array {
        $a = alea(20, 200);
        $b = alea(3, 12);

        return creerSaisie('', ['Que retourne cet appel ?', "|echo intdiv($a, $b);"], (string) intdiv($a, $b),
            sprintf('intdiv() renvoie le quotient entier : %d / %d = %.2f, partie entière %d.', $a, $b, $a / $b, intdiv($a, $b)));
    }],

    'duree' => ['01', static function (): array {
        $m = alea(61, 239);
        $h = intdiv($m, 60);
        $r = $m % 60;
        $bonne = sprintf('%d h %02d min', $h, $r);
        $candidats = [
            sprintf('%d h %d min', $h, $r),
            sprintf('%d h %02d min', $h + 1, $r),
            sprintf('%d h %02d min', $h, 60 - $r),
            sprintf('%d h %02d min', $r, $h),
            sprintf('%d h %02d min', intdiv($m, 100), $m % 100),
            sprintf('%d h %02d min', $h, $m % 100),
        ];
        $candidats = array_values(array_unique(array_filter($candidats, static fn (string $c): bool => $c !== $bonne)));
        $mauvaises = array_slice(melanger($candidats), 0, 3);

        return creerQcm('', ['`convertirDuree` retourne « H h MM min » (minutes sur deux chiffres). Pour cet appel :', "|convertirDuree($m);"], $bonne, $mauvaises,
            sprintf('%d min = %d h et %d min (intdiv et modulo 60) ; sprintf(\'%%02d\') force deux chiffres.', $m, $h, $r));
    }],

    'sprintf' => ['01', static function (): array {
        $variante = alea(1, 5);
        switch ($variante) {
            case 1:
                $n = alea(1, 9);
                $code = "sprintf('%02d', $n)";
                $res = sprintf('%02d', $n);
                break;
            case 2:
                $n = alea(1, 99);
                $code = "sprintf('%03d', $n)";
                $res = sprintf('%03d', $n);
                break;
            case 3:
                do {
                    $milli = alea(1000, 99999);
                } while ($milli % 10 === 5); // évite les arrondis « à la moitié », sources de doute
                $x = $milli / 1000;
                $code = "sprintf('%.2f', $x)";
                $res = sprintf('%.2f', $x);
                break;
            case 4:
                $h = alea(0, 4);
                $mn = alea(0, 59);
                $code = "sprintf('%d h %02d', $h, $mn)";
                $res = sprintf('%d h %02d', $h, $mn);
                break;
            default:
                $x = alea(100, 999) + alea(0, 99) / 100;
                $code = "number_format($x, 2, ',', ' ')";
                $res = number_format($x, 2, ',', ' ');
        }

        return creerSaisie('', ['Que produit cet appel ?', "|echo $code;"], $res,
            '%d = entier, 0 = remplissage par des zéros, la largeur suit, %.2f = 2 décimales arrondies ; number_format(nombre, décimales, séparateur décimal, séparateur de milliers).');
    }],

    'precedence' => ['01', static function (): array {
        $a = alea(2, 9);
        $b = alea(2, 9);
        $c = alea(2, 9);
        $modeles = [
            ['$a + $b * $c', $a + $b * $c, 'La multiplication est prioritaire sur l\'addition.'],
            ['($a + $b) * $c', ($a + $b) * $c, 'Les parenthèses forcent l\'addition en premier.'],
            ['$a * $b % $c', $a * $b % $c, '* et % ont la même priorité : évaluation de gauche à droite, soit ($a * $b) % $c.'],
            ['$a + $b % $c', $a + $b % $c, '% est prioritaire sur + : $a + ($b % $c).'],
            ['$a ** 2 + $b', $a ** 2 + $b, '** (puissance) est prioritaire sur +.'],
            ['$a - $b - $c', $a - $b - $c, 'La soustraction est associative à gauche : ($a - $b) - $c.'],
            ['$a . $b + $c', $a . ($b + $c), 'Depuis PHP 8, + et - sont prioritaires sur le point de concaténation : $a . ($b + $c).'],
        ];
        [$modele, $valeur, $why] = tirer($modeles);
        $affiche = strtr($modele, ['$a' => (string) $a, '$b' => (string) $b, '$c' => (string) $c]);

        return creerSaisie('', ['Que affiche ce code ?', "|echo $affiche;"], (string) $valeur, $why);
    }],

    // ───────── 02 · Conditions et logique ─────────
    'bissextile' => ['02', static function (): array {
        $categorie = alea(0, 3);
        $annee = match ($categorie) {
            0 => tirer([1600, 2000, 2400]),
            1 => tirer([1700, 1800, 1900, 2100, 2200, 2300]),
            2 => 4 * alea(451, 590),
            default => 4 * alea(451, 590) + alea(1, 3),
        };
        if ($categorie === 2 && $annee % 100 === 0) {
            $annee += 4;
        }
        $bissextile = ($annee % 4 === 0 && $annee % 100 !== 0) || $annee % 400 === 0;
        $why = match (true) {
            $annee % 400 === 0 => "$annee est divisible par 400 : bissextile.",
            $annee % 100 === 0 => "$annee est divisible par 100 mais pas par 400 : pas bissextile.",
            $annee % 4 === 0 => "$annee est divisible par 4 mais pas par 100 : bissextile.",
            default => "$annee n'est pas divisible par 4 : pas bissextile.",
        };
        $oui = 'Oui, bissextile';
        $non = 'Non, pas bissextile';

        return creerQcm('', "Selon le calendrier grégorien, l'année $annee est-elle bissextile ?", $bissextile ? $oui : $non, [$bissextile ? $non : $oui], $why);
    }],

    'tarif' => ['02', static function (): array {
        $age = tirer([5, 12, 17, 18, 25, 40, 64, 65, 70, 82]);
        $membre = alea(0, 1) === 1;
        $prix = $age < 18 ? 6 : ($age >= 65 ? 7 : 9);
        if ($membre) {
            $prix -= 2;
        }
        $prix = max(0, $prix);

        return creerSaisie('', [
            'Règles : plein tarif 9 € ; moins de 18 ans 6 € ; 65 ans et plus 7 € ; un membre a ensuite 2 € de réduction ; jamais négatif.',
            sprintf('Quel est le prix (en €) pour une personne de %d ans, %s ?', $age, $membre ? 'membre' : 'non membre'),
        ], (string) $prix, sprintf('Tarif de base %d €%s.', $age < 18 ? 6 : ($age >= 65 ? 7 : 9), $membre ? ', moins 2 € pour un membre' : ''));
    }],

    'comparaisons' => ['02', static function (): array {
        $cas = [
            ['0 == "a"', static fn (): bool => 0 == 'a', 'Depuis PHP 8, un entier est comparé à une chaîne non numérique en tant que chaîne : "0" == "a" est faux.'],
            ['"1" == "01"', static fn (): bool => '1' == '01', 'Deux chaînes numériques sont comparées comme des nombres : 1 == 1.'],
            ['"10" == "1e1"', static fn (): bool => '10' == '1e1', 'Deux chaînes numériques : 10 == 1e1 (notation scientifique) est vrai.'],
            ['100 == "1e2"', static fn (): bool => 100 == '1e2', '"1e2" est une chaîne numérique valant 100.'],
            ['"abc" == 0', static fn (): bool => 'abc' == 0, 'PHP 8 : 0 est converti en "0" et comparé à "abc" : faux (en PHP 7, c\'était vrai !).'],
            ['null == false', static fn (): bool => null == false, 'Comparaison lâche : null et false sont tous deux « falsy ».'],
            ['null === false', static fn (): bool => null === false, '=== compare aussi le type : null n\'est pas un booléen.'],
            ['[] == false', static fn (): bool => [] == false, 'Un tableau vide est « falsy ».'],
            ['"0" == false', static fn (): bool => '0' == false, '"0" est « falsy » en PHP.'],
            ['"" == null', static fn (): bool => '' == null, 'La chaîne vide et null sont égales en comparaison lâche.'],
            ['0 == ""', static fn (): bool => 0 == '', 'PHP 8 : "" n\'est pas numérique, 0 devient "0" et "0" == "" est faux.'],
            ['"1" === 1', static fn (): bool => '1' === 1, '=== compare aussi le type : string contre int.'],
            ['1.0 === 1', static fn (): bool => 1.0 === 1, 'float et int sont deux types différents.'],
            ['"a" == "A"', static fn (): bool => 'a' == 'A', 'La comparaison de chaînes est sensible à la casse.'],
            ['"abc" <=> "abd"', static fn (): int => 'abc' <=> 'abd', 'L\'opérateur <=> renvoie -1, 0 ou 1.'],
        ];
        [$expr, $fn, $why] = tirer($cas);
        $valeur = $fn();
        if (is_int($valeur)) {
            return creerSaisie('', ['Que vaut cette expression ?', "|var_dump($expr);"], (string) $valeur, $why, false, 'un entier : -1, 0 ou 1');
        }
        $vrai = 'bool(true)';
        $faux = 'bool(false)';

        return creerQcm('', ['Que produit ce code avec PHP 8 ?', "|var_dump($expr);"], $valeur ? $vrai : $faux, [$valeur ? $faux : $vrai], $why);
    }],

    // ───────── 03 · Boucles ─────────
    'fizzbuzz' => ['03', static function (): array {
        $n = alea(1, 150);
        $rep = $n % 15 === 0 ? 'FizzBuzz' : ($n % 3 === 0 ? 'Fizz' : ($n % 5 === 0 ? 'Buzz' : (string) $n));
        $options = array_values(array_unique(['Fizz', 'Buzz', 'FizzBuzz', (string) $n]));
        $mauvaises = array_values(array_filter($options, static fn (string $o): bool => $o !== $rep));

        return creerQcm('', "Dans le FizzBuzz classique (Fizz = multiple de 3, Buzz = multiple de 5, FizzBuzz = les deux), que s'affiche-t-il pour $n ?", $rep, $mauvaises,
            "$n % 3 = " . ($n % 3) . ' et ' . "$n % 5 = " . ($n % 5) . '.');
    }],

    'collatz' => ['03', static function (): array {
        if (alea(0, 1) === 0) {
            $x = alea(3, 99);
            $suivant = $x % 2 === 0 ? intdiv($x, 2) : 3 * $x + 1;

            return creerSaisie('', "Dans la suite de Collatz (pair : divisé par 2 ; impair : ×3 puis +1), quel est le terme qui suit $x ?", (string) $suivant,
                $x % 2 === 0 ? "$x est pair : $x / 2 = $suivant." : "$x est impair : 3 × $x + 1 = $suivant.");
        }
        $n = alea(3, 30);
        $terme = $n;
        $taille = 1;
        while ($terme !== 1) {
            $terme = $terme % 2 === 0 ? intdiv($terme, 2) : 3 * $terme + 1;
            $taille++;
        }

        return creerSaisie('', "Combien de valeurs (départ et 1 compris) retourne `suiteCollatz($n)` ?", (string) $taille,
            "La suite part de $n et atteint 1 après " . ($taille - 1) . " étapes, soit $taille valeurs.");
    }],

    'iterations' => ['03', static function (): array {
        $debut = alea(0, 5);
        $pas = tirer([1, 2, 3, 5]);
        $fin = alea(10, 40);
        $op = tirer(['<', '<=']);
        $c = 0;
        for ($i = $debut; $op === '<' ? $i < $fin : $i <= $fin; $i += $pas) {
            $c++;
        }

        return creerSaisie('', [
            'Combien de fois le corps de cette boucle est-il exécuté ?',
            sprintf('|for ($i = %d; $i %s %d; $i += %d) {', $debut, $op, $fin, $pas),
            '|    // ...',
            '|}',
        ], (string) $c, sprintf('$i prend les valeurs %d, %d, %d… tant que $i %s %d : %d tours.', $debut, $debut + $pas, $debut + 2 * $pas, $op, $fin, $c));
    }],

    // ───────── 04 · Tableaux et textes ─────────
    'tableaux' => ['04', static function (): array {
        $t = [];
        while (count($t) < 5) {
            $v = alea(1, 20);
            if (!in_array($v, $t, true)) {
                $t[] = $v;
            }
        }
        $liste = listePhp($t);
        $choix = alea(1, 7);
        $indice = '';
        switch ($choix) {
            case 1:
                $code = "count($liste)";
                $rep = [(string) count($t)];
                break;
            case 2:
                $code = "array_sum($liste)";
                $rep = [(string) array_sum($t)];
                break;
            case 3:
                $code = "max($liste)";
                $rep = [(string) max($t)];
                break;
            case 4:
                $code = "min($liste)";
                $rep = [(string) min($t)];
                break;
            case 5:
                $o = alea(0, 2);
                $l = alea(1, 2);
                $code = "array_slice($liste, $o, $l)";
                $rep = variantesListe(array_slice($t, $o, $l));
                $indice = 'format : [1, 2]';
                break;
            case 6:
                $code = "array_reverse($liste)";
                $rep = variantesListe(array_reverse($t));
                $indice = 'format : [1, 2, 3, 4, 5]';
                break;
            default:
                $dup = [$t[0], $t[1], $t[0], $t[2], $t[1]];
                $code = 'array_values(array_unique(' . listePhp($dup) . '))';
                $rep = variantesListe(array_values(array_unique($dup)));
                $indice = 'format : [1, 2, 3]';
        }

        return creerSaisie('', ['Que retourne cette expression ? (pour une liste, respecte le format indiqué)', "|$code"], $rep,
            'À connaître : count, array_sum, max, min, array_slice(tableau, début, longueur), array_reverse, array_unique (+ array_values pour réindexer).', false, $indice);
    }],

    'chaines' => ['04', static function (): array {
        $mot = tirer(['été', 'café', 'garçon', 'élève', 'forêt', 'cœur', 'Noël', 'île']);
        $variante = alea(1, 5);
        switch ($variante) {
            case 1:
                $code = "strlen('$mot')";
                $rep = (string) strlen($mot);
                $why = 'strlen compte les OCTETS : un caractère accentué UTF-8 en occupe 2. Pour compter des caractères : mb_strlen.';
                break;
            case 2:
                $code = "mb_strlen('$mot')";
                $rep = (string) mb_strlen($mot);
                $why = 'mb_strlen compte les caractères (UTF-8) : c\'est la fonction à utiliser pour les règles de longueur.';
                break;
            case 3:
                $code = "mb_strtoupper('$mot')";
                $rep = mb_strtoupper($mot);
                $why = 'mb_strtoupper gère les accents en UTF-8.';
                break;
            case 4:
                $n = alea(1, 3);
                $code = "mb_substr('$mot', 0, $n)";
                $rep = mb_substr($mot, 0, $n);
                $why = 'mb_substr(chaîne, début, longueur) découpe en caractères, sans couper un caractère multi-octets.';
                break;
            default:
                $n = alea(2, 3);
                $code = "str_repeat('ab', $n)";
                $rep = str_repeat('ab', $n);
                $why = 'str_repeat répète la chaîne n fois.';
        }

        return creerSaisie('', ['Que retourne cet appel ?', "|echo $code;"], $rep, $why);
    }],

    // ───────── 05 · Algorithmes classiques ─────────
    'fibonacci' => ['05', static function (): array {
        $n = alea(7, 22);
        [$a, $b] = [0, 1];
        for ($i = 0; $i < $n; $i++) {
            [$a, $b] = [$b, $a + $b];
        }

        return creerSaisie('', "Avec F(0) = 0 et F(1) = 1, que vaut F($n) dans la suite de Fibonacci ?", (string) $a,
            "Chaque terme est la somme des deux précédents : F($n) = $a.");
    }],

    'premier' => ['05', static function (): array {
        $n = alea(20, 250);
        $diviseur = null;
        for ($d = 2; $d * $d <= $n; $d++) {
            if ($n % $d === 0) {
                $diviseur = $d;
                break;
            }
        }
        $premier = $diviseur === null;
        $oui = 'Oui, c\'est un nombre premier';
        $non = 'Non, il est composé';

        return creerQcm('', "Le nombre $n est-il premier ?", $premier ? $oui : $non, [$premier ? $non : $oui],
            $premier ? "Aucun diviseur entre 2 et √$n (≈ " . round(sqrt($n), 1) . ') : il est premier.' : "$n est divisible par $diviseur ($n = $diviseur × " . intdiv($n, $diviseur) . ').');
    }],

    // ───────── 06 · Fonctions, contrats, tests ─────────
    'isbn' => ['06', static function (): array {
        $chiffres = '';
        for ($i = 0; $i < 9; $i++) {
            $chiffres .= (string) alea(0, 9);
        }
        $somme = 0;
        for ($i = 0; $i < 9; $i++) {
            $somme += (int) $chiffres[$i] * (10 - $i);
        }
        $cle = (11 - ($somme % 11)) % 11;
        $rep = $cle === 10 ? 'X' : (string) $cle;
        $format = substr($chiffres, 0, 1) . '-' . substr($chiffres, 1, 3) . '-' . substr($chiffres, 4);

        return creerSaisie('', ["Clé ISBN-10 : poids 10 à 2 sur les neuf chiffres, clé = (11 - (somme % 11)) % 11, « X » si 10.", "|calculerCleIsbn10('$format');"], $rep,
            "Somme pondérée = $somme ; $somme % 11 = " . ($somme % 11) . " ; clé = (11 - " . ($somme % 11) . ') % 11 = ' . $cle . ($cle === 10 ? ' → « X »' : '') . '.', false, 'un chiffre ou X');
    }],

    // ───────── 07 · Récursivité ─────────
    'factorielle' => ['07', static function (): array {
        $n = alea(3, 9);

        return creerSaisie('', "Que retourne `factorielleRecursive($n)` ?", (string) array_product(range(1, $n)),
            "$n! = " . implode(' × ', range($n, 1)) . ' = ' . array_product(range(1, $n)) . '.');
    }],

    'fib_appels' => ['07', static function (): array {
        $n = alea(3, 10);
        $appels = static function (int $k) use (&$appels): int {
            return $k < 2 ? 1 : 1 + $appels($k - 1) + $appels($k - 2);
        };
        $nb = $appels($n);

        return creerSaisie('', [
            "Avec cette version récursive naïve, combien d'appels à `fib` (le premier compris) pour `fib($n)` ?",
            '|function fib(int $n): int {',
            '|    return $n < 2 ? $n : fib($n - 1) + fib($n - 2);',
            '|}',
        ], (string) $nb, "calls(n) = 1 + calls(n-1) + calls(n-2), avec calls(0) = calls(1) = 1 : $nb appels. Les sous-problèmes sont recalculés → croissance exponentielle.");
    }],

    'somme_chiffres' => ['07', static function (): array {
        $n = alea(100, 99999);
        $chiffres = str_split((string) $n);

        return creerSaisie('', "Que retourne la somme récursive des chiffres de $n ?", (string) array_sum($chiffres),
            implode(' + ', $chiffres) . ' = ' . array_sum($chiffres) . ' (dernier chiffre : $n % 10 ; reste : intdiv($n, 10)).');
    }],

    // ───────── 09 · HTTP et superglobales ─────────
    'coalesce' => ['09', static function (): array {
        $t = ['a' => 1, 'b' => null, 'c' => 0];
        $cas = [
            ['$t[\'a\'] ?? \'défaut\'', $t['a'] ?? 'défaut', 'La clé existe et vaut 1 (non null).'],
            ['$t[\'b\'] ?? \'défaut\'', $t['b'] ?? 'défaut', '?? renvoie la valeur de droite si la clé est absente OU si la valeur est null.'],
            ['$t[\'z\'] ?? \'défaut\'', $t['z'] ?? 'défaut', 'Clé absente : pas d\'avertissement, valeur par défaut.'],
            ['$t[\'c\'] ?? \'défaut\'', $t['c'] ?? 'défaut', '?? ne teste que null/absent : 0 est conservé (contrairement à ?:).'],
            ['isset($t[\'b\'])', isset($t['b']), 'isset est faux pour une valeur null.'],
            ['isset($t[\'c\'])', isset($t['c']), 'La clé existe et sa valeur (0) n\'est pas null.'],
            ['array_key_exists(\'b\', $t)', array_key_exists('b', $t), 'array_key_exists teste l\'existence de la clé, même si la valeur est null.'],
            ['empty($t[\'c\'])', empty($t['c']), 'empty est vrai pour 0.'],
            ['empty($t[\'z\'])', empty($t['z']), 'empty est vrai pour une clé absente, sans avertissement.'],
        ];
        [$expr, $valeur, $why] = tirer($cas);
        $rep = is_bool($valeur) ? ($valeur ? 'true' : 'false') : (string) $valeur;

        return creerSaisie('', [
            'Avec ce tableau, que donne l\'expression ? (true, false, un nombre ou un texte)',
            '|$t = [\'a\' => 1, \'b\' => null, \'c\' => 0];',
            "|var_dump($expr);   // réponse = la valeur obtenue",
        ], $rep, $why);
    }],

    // ───────── 10 · Formulaires robustes ─────────
    'echappement' => ['10', static function (): array {
        $s = tirer(['<b>Dune</b>', 'Tom & Jerry', '"Alien"', '5 > 3', "L'Armée", '<script>alert(1)</script>', 'a<b', '"x" & \'y\'']);
        $rep = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return creerSaisie('', ['Que retourne cet appel ? (tape la chaîne HTML obtenue)', "|htmlspecialchars('" . str_replace("'", "\\'", $s) . "', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');"], $rep,
            '< → &lt; · > → &gt; · & → &amp; · " → &quot; · \' → &#039; (avec ENT_QUOTES).', false, 'entités : &lt; &gt; &amp; &quot; &#039;');
    }],

    // ───────── 11 · Cookies ─────────
    'cookie_secondes' => ['11', static function (): array {
        $j = tirer([1, 7, 14, 30, 60, 90]);

        return creerSaisie('', "Un cookie doit expirer dans $j jour(s) : quelle valeur (en secondes) ajoute-t-on à `time()` ?", (string) ($j * 86400),
            "$j × 24 × 3600 = " . ($j * 86400) . ' secondes (86 400 s par jour).');
    }],

    // ───────── 12 · Sessions et sécurité ─────────
    'csrf_hex' => ['12', static function (): array {
        $n = tirer([8, 16, 24, 32, 48, 64]);

        return creerSaisie('', "Combien de caractères retourne `bin2hex(random_bytes($n))` ?", (string) (2 * $n),
            "Chaque octet s'écrit avec 2 caractères hexadécimaux : $n × 2 = " . (2 * $n) . ' (random_bytes(32) → jeton de 64 caractères).');
    }],

    // ───────── 15 · PDO : lire ─────────
    'pagination' => ['15', static function (): array {
        $limite = tirer([6, 10, 12, 20, 24]);
        if (alea(0, 1) === 0) {
            $page = alea(2, 12);
            $offset = ($page - 1) * $limite;

            return creerSaisie('', "Pagination à $limite éléments par page : quelle valeur d'OFFSET SQL faut-il pour afficher la page $page ?", (string) $offset,
                "OFFSET = (page - 1) × limite = ($page - 1) × $limite = $offset.");
        }
        $total = alea(25, 200);
        $pages = (int) ceil($total / $limite);

        return creerSaisie('', "Il y a $total films, $limite par page : combien de pages au total ?", (string) $pages,
            "ceil($total / $limite) = $pages (un COUNT(*) préalable donne le total).");
    }],

    // ───────── 17 · Transactions ─────────
    'places' => ['17', static function (): array {
        $p = alea(1, 4);
        $k = alea($p + 1, 9);

        return creerSaisie('', [
            "Il reste $p place(s). $k utilisateurs réservent EN MÊME TEMPS ; chacun exécute (dans une transaction) :",
            '|UPDATE seances SET places_restantes = places_restantes - 1',
            '|WHERE id = :id AND places_restantes > 0;',
            'Au maximum, combien de réservations peuvent réussir ?',
        ], (string) $p, "La mise à jour conditionnelle est atomique : seules les $p premières trouvent places_restantes > 0 ; les autres ont rowCount() = 0 → refus.");
    }],

    // ───────── 19 · Composer ─────────
    'psr4' => ['19', static function (): array {
        $classes = [
            ['App\\Domain\\Film', 'src/Domain/Film.php'],
            ['App\\Domain\\Duree', 'src/Domain/Duree.php'],
            ['App\\Repository\\FilmRepository', 'src/Repository/FilmRepository.php'],
            ['App\\Controller\\FilmController', 'src/Controller/FilmController.php'],
            ['App\\Service\\FilmValidator', 'src/Service/FilmValidator.php'],
            ['App\\Service\\EmpruntService', 'src/Service/EmpruntService.php'],
            ['App\\Http\\JsonResponse', 'src/Http/JsonResponse.php'],
            ['App\\Database', 'src/Database.php'],
        ];
        [$classe, $chemin] = tirer($classes);
        $parties = explode('/', $chemin);
        $fichier = array_pop($parties);
        $candidats = [
            strtolower($chemin),
            'src/App/' . substr($chemin, 4),
            substr($chemin, 4),
            'vendor/' . substr($chemin, 4),
            implode('/', $parties) . '/' . strtolower($fichier),
            str_replace('.php', '.class.php', $chemin),
        ];
        $candidats = array_values(array_unique(array_filter($candidats, static fn (string $c): bool => $c !== $chemin)));

        return creerQcm('', [
            'Avec cette configuration Composer :',
            '|"autoload": { "psr-4": { "App\\\\": "src/" } }',
            'Dans quel fichier doit se trouver la classe `' . $classe . '` ?',
        ], $chemin, array_slice(melanger($candidats), 0, 3),
            'PSR-4 : le préfixe `App\` est remplacé par `src/`, les `\` deviennent `/`, et le nom du fichier est identique au nom de la classe (casse comprise) + `.php`.');
    }],

    // ───────── 21 · Slim ─────────
    'route_args' => ['21', static function (): array {
        $id = alea(2, 999);
        $a = alea(2, 90);
        $b = alea(2, 90);
        $noms = ['Ada', 'Linus', 'Grace', 'Alan', 'Margaret'];
        $nom = tirer($noms);
        $cas = [
            ['/bonjour/{nom}', "/bonjour/$nom", 'nom', $nom],
            ['/addition/{a}/{b}', "/addition/$a/$b", 'b', (string) $b],
            ['/addition/{a}/{b}', "/addition/$a/$b", 'a', (string) $a],
            ['/films/{id}', "/films/$id", 'id', (string) $id],
            ['/films/{id:[0-9]+}/avis/{num}', "/films/$id/avis/$a", 'num', (string) $a],
        ];
        [$motif, $url, $cle, $valeur] = tirer($cas);

        return creerSaisie('', ["Route Slim : `$motif`", "Requête : `GET $url`", "Que contient `\$args['$cle']` dans le callback ?"], $valeur,
            'Les segments entre accolades sont capturés dans $args (toujours sous forme de chaîne) ; il faut les valider avant usage.');
    }],

    // ───────── 24 · API REST ─────────
    'http_statut' => ['24', static function (): array {
        $libelles = [200 => '200 OK', 201 => '201 Created', 204 => '204 No Content', 303 => '303 See Other', 400 => '400 Bad Request',
            401 => '401 Unauthorized', 403 => '403 Forbidden', 404 => '404 Not Found', 415 => '415 Unsupported Media Type',
            422 => '422 Unprocessable Content', 500 => '500 Internal Server Error', 501 => '501 Not Implemented'];
        $scenarios = [
            ['Un client demande GET /api/films/999999, qui n\'existe pas.', 404],
            ['Un POST JSON valide crée un nouveau film.', 201],
            ['Un DELETE réussit et il n\'y a rien à renvoyer.', 204],
            ['Le corps JSON envoyé est syntaxiquement mal formé.', 400],
            ['Le client envoie Content-Type: text/plain alors que l\'API attend du JSON.', 415],
            ['Le JSON est lisible mais le titre est vide après trim.', 422],
            ['Un formulaire POST contient un jeton CSRF absent ou invalide.', 403],
            ['Après un POST de formulaire réussi, on redirige vers la fiche créée (Post/Redirect/Get).', 303],
            ['GET /films : la liste est renvoyée sans problème.', 200],
            ['La base de données est tombée en panne de façon inattendue.', 500],
            ['Un visiteur non connecté demande une ressource protégée.', 401],
            ['Un membre connecté, non administrateur, tente de supprimer un film.', 403],
            ['L\'URL /films/abc est demandée (identifiant mal formé).', 400],
            ['Le starter contient une route pas encore programmée.', 501],
        ];
        [$texte, $code] = tirer($scenarios);
        $autres = array_values(array_filter(array_keys($libelles), static fn (int $c): bool => $c !== $code));
        $mauvaises = array_map(static fn (int $c): string => $libelles[$c], array_slice(melanger($autres), 0, 3));

        return creerQcm('', "Quel statut HTTP convient ? $texte", $libelles[$code], $mauvaises,
            'À retenir : 400 = requête inexploitable, 404 = ressource absente, 415 = média non géré, 422 = règle métier violée, 403 = interdit, 401 = non authentifié, 303 = redirection vers un GET, 204 = succès sans corps, 201 = créé, 500 = panne serveur.');
    }],

    'json_encode' => ['24', static function (): array {
        $cas = [
            ['json_encode([\'id\' => 7, \'titre\' => \'Dune\'])', json_encode(['id' => 7, 'titre' => 'Dune']), 'Un tableau associatif devient un objet JSON.'],
            ['json_encode([])', json_encode([]), 'Un tableau vide devient `[]` : une liste vide reste `[]` dans le contrat.'],
            ['json_encode([\'a\', \'b\'])', json_encode(['a', 'b']), 'Un tableau indexé de 0 à n-1 devient une liste JSON.'],
            ['json_encode([1 => \'a\'])', json_encode([1 => 'a']), 'Les clés ne commencent pas à 0 : PHP produit un objet JSON.'],
            ['json_encode(new stdClass())', json_encode(new stdClass()), 'Un objet vide devient `{}` (à ne pas confondre avec `[]`).'],
            ['json_encode(null)', json_encode(null), 'null devient le littéral JSON null.'],
            ['json_encode(true)', json_encode(true), 'Les booléens deviennent true/false en minuscules.'],
            ['json_encode([\'ok\' => false, \'n\' => 3])', json_encode(['ok' => false, 'n' => 3]), 'Les types sont conservés : booléen et entier sans guillemets.'],
        ];
        [$code, $rep, $why] = tirer($cas);

        return creerSaisie('', ['Que retourne cet appel ? (tape le JSON obtenu)', "|echo $code;"], (string) $rep, $why);
    }],

    // ───────── 25 · Médiathèque ─────────
    'echeance' => ['25', static function (): array {
        $debut = new DateTimeImmutable('2026-01-01');
        $date = $debut->modify('+' . alea(0, 360) . ' days');
        $echeance = $date->modify('+21 days');

        return creerSaisie('', 'Un adhérent emprunte un document le ' . $date->format('d/m/Y') . '. Avec 21 jours de prêt, quelle est la date d\'échéance ? (JJ/MM/AAAA ou AAAA-MM-JJ)',
            [$echeance->format('d/m/Y'), $echeance->format('Y-m-d')], 'Calcul côté serveur : `$maintenant->modify(\'+21 days\')` (DateTimeImmutable renvoie une nouvelle date) → ' . $echeance->format('d/m/Y') . '.');
    }],

    // ───────── 26 · Sécuriser une API : JWT ─────────
    'jwt_exp' => ['26', static function (): array {
        $h = alea(8, 20);
        $m = alea(0, 59);
        $duree = tirer([300, 600, 900, 1200, 1800, 3600]);
        $total = $h * 60 + $m + intdiv($duree, 60);
        $hh = intdiv($total, 60);
        $mm = $total % 60;
        $fin = sprintf('%02d:%02d', $hh, $mm);

        return creerSaisie('', sprintf('Un JWT est émis à %02d:%02d (iat) avec exp = iat + %d. À quelle heure expire-t-il ? (format HH:MM)', $h, $m, $duree),
            [$fin, sprintf('%d:%02d', $hh, $mm), sprintf('%02dh%02d', $hh, $mm), sprintf('%dh%02d', $hh, $mm)],
            sprintf("iat et exp s'expriment en secondes : %d s = %d min, donc %02d:%02d + %d min = %s. Passé cette heure, l'API répond 401, même si la signature est correcte.", $duree, intdiv($duree, 60), $h, $m, intdiv($duree, 60), $fin),
            false, 'format HH:MM');
    }],

    'jwt_verification' => ['26', static function (): array {
        $fmt = static fn (int $t): string => sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
        $maintenant = alea(9, 18) * 60 + alea(0, 59);
        $probleme = tirer(['aucun', 'aucun', 'signature', 'expire', 'iss', 'aud']);
        $exp = $probleme === 'expire' ? $maintenant - alea(1, 30) : $maintenant + alea(1, 30);
        $raisons = [
            'aucun' => 'tous les contrôles passent : le jeton est accepté (le contrôleur vérifie ensuite les droits sur la ressource).',
            'signature' => 'la signature ne correspond plus au contenu : jeton refusé.',
            'expire' => 'exp est dépassé : jeton refusé, même avec une signature valide.',
            'iss' => "l'émetteur (iss) n'est pas celui attendu : jeton refusé.",
            'aud' => "le jeton n'est pas destiné à cette API (aud) : jeton refusé.",
        ];
        $accepte = '200 : jeton accepté, la requête continue';
        $refuse = '401 : jeton refusé';
        $bonne = $probleme === 'aucun' ? $accepte : $refuse;

        return creerQcm('', [
            "L'API attend iss = lectures-app et aud = lectures-api. Il est " . $fmt($maintenant) . '. Que répond-elle à une requête protégée qui présente ce jeton ?',
            '|signature : ' . ($probleme === 'signature' ? 'invalide (payload modifié)' : 'valide'),
            '|iss : ' . ($probleme === 'iss' ? tirer(['test-app', 'autre-app']) : 'lectures-app'),
            '|aud : ' . ($probleme === 'aud' ? tirer(['admin-api', 'autre-api']) : 'lectures-api'),
            '|exp : ' . $fmt($exp),
        ], $bonne, [$bonne === $accepte ? $refuse : $accepte, '403 : droits insuffisants', '429 : trop de requêtes'],
            'Le middleware vérifie la signature, exp, iss et aud (et sub). Ici, ' . $raisons[$probleme]);
    }],

    'jwt_statut' => ['26', static function (): array {
        $libelles = [200 => '200 OK', 204 => '204 No Content', 400 => '400 Bad Request', 401 => '401 Unauthorized',
            403 => '403 Forbidden', 422 => '422 Unprocessable Content', 429 => '429 Too Many Requests'];
        $scenarios = [
            ['DELETE /api/livres/7 sans en-tête Authorization (route protégée).', 401],
            ['GET /api/livres/7 avec un jeton dont le payload a été modifié.', 401],
            ['POST /api/livres avec un jeton expiré depuis cinq minutes.', 401],
            ['POST /api/login avec un mot de passe incorrect.', 401],
            ['DELETE /api/livres/7 avec un jeton valide, mais le livre appartient à un autre compte.', 403],
            ['DELETE /api/livres/7 avec un jeton valide et le droit de supprimer ce livre.', 204],
            ['Cinquantième POST /api/login raté en une minute depuis la même adresse.', 429],
            ['POST /api/login avec un corps JSON syntaxiquement mal formé.', 400],
            ['POST /api/login avec un JSON valide, mais sans le champ email.', 422],
            ['GET /api/livres, une route publique, appelée sans jeton.', 200],
        ];
        [$texte, $code] = tirer($scenarios);
        $autres = array_values(array_filter(array_keys($libelles), static fn (int $c): bool => $c !== $code));
        $mauvaises = array_map(static fn (int $c): string => $libelles[$c], array_slice(melanger($autres), 0, 3));

        return creerQcm('', "Quel statut HTTP convient ? $texte", $libelles[$code], $mauvaises,
            "401 = jeton absent, invalide ou expiré (ou identifiants faux) ; 403 = identité reconnue mais action interdite ; 204 = suppression réussie sans corps ; 429 = trop de tentatives ; 400 = JSON illisible ; 422 = JSON lisible mais incomplet ou invalide.");
    }],

    // ───────── 27 · JavaScript : les fondamentaux ─────────
    'js_expression' => ['27', static function (): array {
        $a = alea(2, 9);
        $b = alea(2, 9);
        $c = alea(2, 9);
        [$code, $bonne, $mauvaises, $why] = match (alea(1, 9)) {
            1 => ["\"$a\" + $b", "$a$b", [$a + $b, (string) ($a + $b), NAN], "Avec +, dès qu'un opérande est une chaîne, l'autre est converti en texte : concaténation."],
            2 => ["\"$a\" - $b", $a - $b, [(string) ($a - $b), "$a$b", NAN], "- n'existe que pour les nombres : la chaîne est convertie et le résultat est un number."],
            3 => ["\"$a\" * \"$b\"", $a * $b, [(string) ($a * $b), "$a$b", NAN], '* convertit les deux chaînes en nombres : le résultat est un number.'],
            4 => ["$a + \"$b\"", "$a$b", [$a + $b, (string) ($a + $b), NAN], "Avec +, une chaîne d'un côté suffit : le nombre est converti en texte, puis concaténé."],
            5 => ["$a + $b + \"$c\"", ($a + $b) . "$c", ["$a$b$c", $a + $b + $c, (string) ($a + $b + $c)], "Évaluation de gauche à droite : $a + $b vaut " . ($a + $b) . " (deux nombres), puis + \"$c\" concatène."],
            6 => ["\"$a\" + $b + $c", "$a$b$c", ["$a" . ($b + $c), $a + $b + $c, (string) ($a + $b + $c)], "De gauche à droite : \"$a\" + $b donne \"$a$b\", puis + $c concatène encore."],
            7 => ["Number(\"$a\") + $b", $a + $b, ["$a$b", (string) ($a + $b), NAN], 'Number() convertit explicitement la chaîne : on additionne deux nombres.'],
            8 => ["\"$a\" == $a", true, [false, 'true', NAN], '== convertit implicitement la chaîne en nombre avant de comparer.'],
            default => ["\"$a\" === $a", false, [true, 'false', NAN], "=== compare la valeur ET le type : une chaîne n'est jamais strictement égale à un nombre."],
        };

        return creerQcm('', ['Que vaut cette expression JavaScript ?', "|$code"], litteralJs($bonne), array_map('litteralJs', $mauvaises),
            $why . " Pour calculer à partir d'une saisie, on convertit d'abord avec Number().");
    }],

    'js_typeof' => ['27', static function (): array {
        $n = alea(2, 99);
        $cas = [
            [(string) $n, 'number'], ["\"$n\"", 'string'], ['true', 'boolean'], ['undefined', 'undefined'],
            ['[1, 2, 3]', 'object'], ['{ nom: "Gants" }', 'object'], ['NaN', 'number'], ["Number(\"$n\")", 'number'],
            ["String($n)", 'string'], ["\"$n\" * 1", 'number'], ["$n + \"\"", 'string'], ["\"$n\" === $n", 'boolean'],
            ["$n / 0", 'number'], ['function () {}', 'function'],
        ];
        [$expr, $type] = tirer($cas);
        $autres = array_values(array_diff(['number', 'string', 'boolean', 'undefined', 'object', 'function'], [$type]));
        $mauvaises = array_map(static fn (string $t): string => "\"$t\"", array_slice(melanger($autres), 0, 3));

        return creerQcm('', ['Que renvoie cette expression ?', "|typeof ($expr)"], "\"$type\"", $mauvaises,
            'typeof renvoie une chaîne : "number" (y compris NaN et Infinity), "string", "boolean", "undefined", "object" (les tableaux et les objets ne sont pas des primitives) ou "function".');
    }],

    'js_falsy' => ['27', static function (): array {
        $falsy = ['0', '""', 'null', 'undefined', 'NaN', 'false', '-0'];
        $truthy = ['"0"', '"false"', '[]', '{}', '1', '-1', '" "', 'Infinity', '"null"'];
        $nbFalsy = alea(1, 4);
        $valeurs = melanger(array_merge(array_slice(melanger($falsy), 0, $nbFalsy), array_slice(melanger($truthy), 0, 6 - $nbFalsy)));

        return creerSaisie('', ['Combien de ces six valeurs sont falsy (considérées comme fausses par un if) ?', '|' . implode('    ', $valeurs)], (string) $nbFalsy,
            'Falsy : false, 0, -0, "", null, undefined et NaN. Tout le reste est truthy : une chaîne non vide (même "0", "false" ou " "), [] et {}, les nombres non nuls.');
    }],

    'js_boucle' => ['27', static function (): array {
        $debut = alea(0, 3);
        $pas = alea(1, 3);
        $fin = $debut + alea(4, 12);
        $strict = alea(0, 1) === 1;
        $op = $strict ? '<' : '<=';
        $valeurs = [];
        for ($i = $debut; $strict ? $i < $fin : $i <= $fin; $i += $pas) {
            $valeurs[] = $i;
        }
        $suite = implode(', ', $valeurs);
        if (alea(0, 1) === 0) {
            return creerSaisie('', ["Que s'affiche-t-il ?", '|let total = 0;', "|for (let i = $debut; i $op $fin; i += $pas) {", '|    total += i;', '|}', '|console.log(total);'],
                (string) array_sum($valeurs), "i prend les valeurs $suite (la boucle s'arrête dès que i $op $fin est faux) : leur somme vaut " . array_sum($valeurs) . '.');
        }

        return creerSaisie('', ['Combien de fois « Tour » s\'affiche-t-il ?', "|for (let i = $debut; i $op $fin; i += $pas) {", '|    console.log("Tour");', '|}'],
            (string) count($valeurs), "i prend les valeurs $suite : la boucle fait " . count($valeurs) . " tours. Attention à < ou <=, et au pas i += $pas.");
    }],

    'js_divisions' => ['27', static function (): array {
        do {
            $k = alea(0, 7);
            $n = (2 * alea(1, 60) + 1) * (2 ** $k);
        } while ($n <= 100 || $n > 9999);
        $etapes = [$n];
        for ($x = $n; $x % 2 === 0;) {
            $x = intdiv($x, 2);
            $etapes[] = $x;
        }

        return creerSaisie('', ["Que s'affiche-t-il ? (exercice « divisions successives par deux »)", "|let n = $n;", '|let divisions = 0;', '|while (n % 2 === 0) {', '|    n = n / 2;', '|    divisions++;', '|}', '|console.log(divisions);'],
            (string) $k, 'Suite : ' . implode(' → ', $etapes) . ($k === 0 ? " : $n est impair, la boucle ne tourne pas." : " : $k division(s), on s'arrête sur un nombre impair."));
    }],

    'js_tableau' => ['27', static function (): array {
        $t = [];
        while (count($t) < 3) {
            $v = alea(1, 9);
            if (!in_array($v, $t, true)) {
                $t[] = $v;
            }
        }
        if (alea(0, 1) === 0) {
            $lignes = ['|const t = ' . listeJs($t) . ';'];
            $nb = alea(2, 4);
            for ($i = 0; $i < $nb; $i++) {
                switch (tirer(count($t) > 1 ? ['push', 'pop', 'shift', 'unshift', 'splice'] : ['push', 'unshift'])) {
                    case 'push':
                        $x = alea(10, 30);
                        $t[] = $x;
                        $lignes[] = "|t.push($x);";
                        break;
                    case 'unshift':
                        $x = alea(10, 30);
                        array_unshift($t, $x);
                        $lignes[] = "|t.unshift($x);";
                        break;
                    case 'pop':
                        array_pop($t);
                        $lignes[] = '|t.pop();';
                        break;
                    case 'shift':
                        array_shift($t);
                        $lignes[] = '|t.shift();';
                        break;
                    default:
                        $p = alea(0, count($t) - 1);
                        array_splice($t, $p, 1);
                        $lignes[] = "|t.splice($p, 1);";
                }
            }
            $lignes[] = '|console.log(t);';

            return creerSaisie('', array_merge(['Que contient t à la fin ? (tape le tableau)'], $lignes), variantesListe($t),
                "push et pop agissent à la fin, unshift et shift au début ; splice(i, 1) retire l'élément d'indice i. Toutes modifient le tableau lui-même.", false, 'format : [1, 2, 3]');
        }
        $x = alea(10, 30);
        $i = alea(0, 2);
        [$appel, $rep, $why] = match (tirer(['push', 'unshift', 'pop', 'shift', 'indexOf', 'absent', 'splice'])) {
            'push' => ["t.push($x)", (string) (count($t) + 1), "push renvoie la nouvelle longueur du tableau, pas l'élément ajouté."],
            'unshift' => ["t.unshift($x)", (string) (count($t) + 1), 'unshift renvoie, comme push, la nouvelle longueur du tableau.'],
            'pop' => ['t.pop()', (string) $t[count($t) - 1], 'pop retire le dernier élément et le renvoie.'],
            'shift' => ['t.shift()', (string) $t[0], 'shift retire le premier élément et le renvoie.'],
            'indexOf' => ["t.indexOf({$t[$i]})", (string) $i, "indexOf renvoie l'indice de la première occurrence (le premier élément est à l'indice 0)."],
            'absent' => ["t.indexOf($x)", '-1', 'Élément absent : indexOf renvoie -1. À tester avant un splice !'],
            default => ["t.splice($i, 1)", '[' . $t[$i] . ']', "splice renvoie un TABLEAU des éléments retirés, ici [{$t[$i]}]."],
        };

        return creerSaisie('', ['Que vaut r ?', '|const t = ' . listeJs($t) . ';', "|const r = $appel;", '|console.log(r);'], $rep, $why, false, str_starts_with($rep, '[') ? 'format : [1]' : '');
    }],

    // ───────── 28 · JavaScript : fonctions, stockage, JSON ─────────
    'js_json' => ['28', static function (): array {
        $q = alea(0, 60);
        $p = alea(2, 9);
        $nom = tirer(['Gants', 'Compresses', 'Pansements', 'Sirop']);
        [$code, $rep, $why] = tirer([
            ["{ nom: \"$nom\", quantite: $q }", "{\"nom\":\"$nom\",\"quantite\":$q}", 'Les clés passent entre guillemets doubles ; les nombres restent sans guillemets.'],
            ["[$q, \"$nom\", true, null]", "[$q,\"$nom\",true,null]", 'Un tableau devient une liste JSON ; true et null restent des littéraux.'],
            ["{ nom: \"$nom\", note: undefined }", "{\"nom\":\"$nom\"}", 'Une propriété qui vaut undefined est omise par JSON.stringify.'],
            ["{ prix: NaN, quantite: $q }", "{\"prix\":null,\"quantite\":$q}", "NaN (comme Infinity) n'existe pas en JSON : il devient null."],
            ["{ stock: [$q, $p] }", "{\"stock\":[$q,$p]}", 'Les tableaux imbriqués sont conservés.'],
            ["{ actif: false, quantite: \"$q\" }", "{\"actif\":false,\"quantite\":\"$q\"}", "Une chaîne reste une chaîne : ses guillemets sont conservés, même si elle contient un nombre."],
        ]);

        return creerSaisie('', ['Que produit cet appel ? (tape le texte JSON obtenu)', "|JSON.stringify($code)"], $rep, $why . " JSON.parse fait l'inverse : texte → valeur.");
    }],

    'js_quantite' => ['28', static function (): array {
        $n = alea(1, 40);
        [$arg, $rep, $why] = tirer([
            ["\"$n\"", (string) $n, 'la chaîne représente un entier positif : acceptée.'],
            ['"0"', '0', '0 est un entier positif ou nul : accepté (à ne pas confondre avec null).'],
            ['""', 'null', 'la chaîne vide est refusée avant toute conversion.'],
            ['"   "', 'null', 'après trim(), la chaîne est vide : refusée.'],
            ["\" $n \"", (string) $n, "Number() ignore les espaces de début et de fin : la saisie devient $n."],
            ["\"$n.5\"", 'null', "$n.5 n'est pas un entier : Number.isInteger renvoie false."],
            ["\"-$n\"", 'null', 'un entier négatif est refusé (nombre < 0).'],
            ['"abc"', 'null', 'Number("abc") vaut NaN, et Number.isInteger(NaN) est faux.'],
            ['null', 'null', "c'est l'annulation de prompt() : null est refusé dès le premier test."],
            ['"1e2"', '100', 'piège : Number("1e2") vaut 100 (notation scientifique), un entier positif, donc accepté !'],
            ["\"{$n}kg\"", 'null', "Number(\"{$n}kg\") vaut NaN : refusé."],
        ]);

        return creerSaisie('', ['Que renvoie le dernier appel ? (un nombre, ou null)', '|function convertirQuantite(saisie) {', '|    if (saisie === null || saisie.trim() === "") {', '|        return null;', '|    }', '|    const nombre = Number(saisie);', '|    if (!Number.isInteger(nombre) || nombre < 0) {', '|        return null;', '|    }', '|    return nombre;', '|}', "|convertirQuantite($arg);"],
            $rep, 'Contrat : un entier positif ou nul, sinon null. Ici, ' . $why);
    }],

    'js_json_parse' => ['28', static function (): array {
        $n = alea(2, 99);
        [$arg, $bonne, $why] = tirer([
            ["'{\"quantite\": $n}'", "Renvoie un objet dont quantite vaut $n", 'Objet JSON valide : clés entre guillemets doubles.'],
            ["'[1, 2, $n]'", 'Renvoie un tableau de 3 nombres', 'Liste JSON valide.'],
            ["\"{'quantite': $n}\"", 'Lève une SyntaxError', 'JSON impose les guillemets doubles : les apostrophes sont refusées.'],
            ["'[1, 2, $n,]'", 'Lève une SyntaxError', 'JSON refuse la virgule finale.'],
            ["'{quantite: $n}'", 'Lève une SyntaxError', 'En JSON, une clé est obligatoirement entre guillemets doubles.'],
            ["'\"texte\"'", 'Renvoie la chaîne texte', 'Un texte JSON peut être une simple chaîne entre guillemets doubles.'],
            ["'null'", 'Renvoie null', '« null » est un texte JSON valide.'],
            ["'undefined'", 'Lève une SyntaxError', "undefined n'existe pas en JSON."],
            ["'$n'", "Renvoie le nombre $n", 'Un nombre seul est un texte JSON valide.'],
            ["'True'", 'Lève une SyntaxError', "Les littéraux JSON s'écrivent en minuscules : true, false, null."],
        ]);
        $pool = ['Lève une SyntaxError', 'Renvoie null', 'Renvoie undefined', 'Lève une TypeError', "Renvoie le nombre $n", 'Renvoie un objet vide',
            'Renvoie la chaîne texte', 'Renvoie un tableau de 3 nombres', "Renvoie un objet dont quantite vaut $n"];
        $mauvaises = array_slice(melanger(array_values(array_diff($pool, [$bonne]))), 0, 3);

        return creerQcm('', ['Que fait cet appel ?', "|JSON.parse($arg);"], $bonne, $mauvaises,
            $why . " D'où le try / catch autour de JSON.parse quand on relit une donnée stockée.");
    }],

    // ───────── 29 · JavaScript : DOM et événements ─────────
    'js_selecteur' => ['29', static function (): array {
        $elements = [];
        $nb = alea(4, 6);
        for ($i = 0; $i < $nb; $i++) {
            $elements[] = ['tag' => 'li', 'classes' => tirer([['a'], ['b'], ['a', 'b'], ['a'], ['b'], []]), 'row' => alea(1, 3), 'liste' => true];
        }
        $elements[] = ['tag' => 'p', 'classes' => ['a'], 'row' => alea(1, 3), 'liste' => false];
        $a = static fn (array $e): bool => in_array('a', $e['classes'], true);
        $b = static fn (array $e): bool => in_array('b', $e['classes'], true);
        $selecteurs = [
            '.a' => $a,
            '.b' => $b,
            '.a.b' => static fn (array $e): bool => $a($e) && $b($e),
            'li.a' => static fn (array $e): bool => $e['tag'] === 'li' && $a($e),
            '#liste .a' => static fn (array $e): bool => $e['liste'] && $a($e),
            "[data-row='2']" => static fn (array $e): bool => $e['row'] === 2,
            "li[data-row='1']" => static fn (array $e): bool => $e['tag'] === 'li' && $e['row'] === 1,
            'li' => static fn (array $e): bool => $e['tag'] === 'li',
        ];
        $sel = tirer(array_keys($selecteurs));
        $n = count(array_filter($elements, $selecteurs[$sel]));
        $attributs = static fn (array $e): string => ($e['classes'] === [] ? '' : ' class="' . implode(' ', $e['classes']) . '"') . ' data-row="' . $e['row'] . '"';
        $lignes = ['|<ul id="liste">'];
        foreach ($elements as $e) {
            if ($e['tag'] === 'li') {
                $lignes[] = '|    <li' . $attributs($e) . '>…</li>';
            }
        }
        $lignes[] = '|</ul>';
        $lignes[] = '|<p' . $attributs($elements[count($elements) - 1]) . '>…</p>';

        return creerSaisie('', array_merge(["Sur ce document, combien d'éléments renvoie document.querySelectorAll(\"$sel\") ?"], $lignes), (string) $n,
            "Le point vise une classe, # un id, [attribut='valeur'] un attribut ; l'espace désigne un descendant et .a.b exige les deux classes sur le même élément. Ici : $n élément(s).");
    }],

    'js_matrice' => ['29', static function (): array {
        $type = static fn (int $l, int $c): string => $c === 0 ? 'carré' : ($l === 1 && $c === 1 ? 'losange' : 'rond');
        $etat = array_fill(0, 3, array_fill(0, 3, false));
        $lignes = [];
        $nb = alea(2, 3);
        for ($i = 0; $i < $nb; $i++) {
            $l = alea(0, 2);
            $c = alea(0, 2);
            $t = $type($l, $c);
            $nouvel = !$etat[$l][$c];
            $etat[$l][$c] = $nouvel;
            if ($t !== 'rond') {
                for ($k = 0; $k < 3; $k++) {
                    $etat[$l][$k] = $nouvel;
                }
            }
            if ($t === 'losange') {
                for ($k = 0; $k < 3; $k++) {
                    $etat[$k][$c] = $nouvel;
                }
            }
            $lignes[] = sprintf('|clic %d : %s en ligne %d, colonne %d', $i + 1, $t, $l, $c);
        }
        $pleines = 0;
        $dessin = [];
        foreach ($etat as $rangee) {
            $dessin[] = implode('', array_map(static fn (bool $x): string => $x ? '■' : '□', $rangee));
            $pleines += count(array_filter($rangee));
        }

        return creerSaisie('', array_merge([
            'Matrice 3 × 3 (lignes et colonnes numérotées de 0 à 2), toutes les formes vides au départ. Colonne 0 : des carrés ; case (1, 1) : un losange ; autres cases : des ronds.',
            "Rond : inverse son état. Carré : inverse son état puis l'impose à toute sa ligne. Losange : inverse son état puis l'impose à sa ligne et à sa colonne. Après ces clics, combien de formes sont pleines ?",
        ], $lignes), (string) $pleines,
            'État final, ligne par ligne (■ plein, □ vide) : ' . implode(' / ', $dessin) . ". Le nouvel état de la forme cliquée est imposé aux autres, pas inversé une seconde fois.");
    }],

    'js_classlist' => ['29', static function (): array {
        $classes = ['carte'];
        $lignes = ['|// au départ : <div id="zone" class="carte">', '|const zone = document.querySelector("#zone");'];
        $nb = alea(3, 4);
        for ($i = 0; $i < $nb; $i++) {
            $x = tirer(['active', 'visible', 'erreur']);
            $present = in_array($x, $classes, true);
            switch (tirer(['add', 'remove', 'toggle', 'vrai', 'faux'])) {
                case 'add':
                    if (!$present) {
                        $classes[] = $x;
                    }
                    $lignes[] = "|zone.classList.add(\"$x\");";
                    break;
                case 'remove':
                    $classes = array_values(array_diff($classes, [$x]));
                    $lignes[] = "|zone.classList.remove(\"$x\");";
                    break;
                case 'toggle':
                    $classes = $present ? array_values(array_diff($classes, [$x])) : [...$classes, $x];
                    $lignes[] = "|zone.classList.toggle(\"$x\");";
                    break;
                case 'vrai':
                    if (!$present) {
                        $classes[] = $x;
                    }
                    $lignes[] = "|zone.classList.toggle(\"$x\", true);";
                    break;
                default:
                    $classes = array_values(array_diff($classes, [$x]));
                    $lignes[] = "|zone.classList.toggle(\"$x\", false);";
            }
        }
        $trier = static function (array $c): string {
            sort($c);

            return implode(', ', $c);
        };
        $bonne = $trier($classes);
        $candidats = [];
        foreach ([[], ['active'], ['visible'], ['erreur'], ['active', 'visible'], ['active', 'erreur'], ['visible', 'erreur'], ['active', 'visible', 'erreur']] as $extra) {
            $candidats[] = $trier(['carte', ...$extra]);
        }
        $mauvaises = array_slice(melanger(array_values(array_diff($candidats, [$bonne]))), 0, 3);

        return creerQcm('', array_merge(["Quelles classes porte l'élément à la fin ? (classées par ordre alphabétique)"], $lignes), $bonne, $mauvaises,
            "add ajoute (sans doublon), remove retire, toggle inverse ; toggle(classe, true) ajoute et toggle(classe, false) retire, quel que soit l'état. Résultat : $bonne.");
    }],
];
