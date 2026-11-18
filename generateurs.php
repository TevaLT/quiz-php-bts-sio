<?php

declare(strict_types=1);

/**
 * Générateurs de questions : chaque appel fabrique une question avec des valeurs NEUVES.
 * La réponse est calculée par PHP lui-même, en exécutant la même opération : elle est toujours exacte.
 *
 * Format : 'nom' => [chapitre, fonction]. Les fonctions creerQcm() / creerSaisie() / alea() / tirer() /
 * melanger() viennent de moteur.php. Une ligne qui commence par « | » est affichée comme du code.
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

/** Ce qu'afficherait var_dump() pour une valeur scalaire, sur une ligne (indépendant d'éventuelles extensions de débogage). */
function texteVarDump(mixed $v): string
{
    return match (true) {
        $v === null => 'NULL',
        is_bool($v) => 'bool(' . ($v ? 'true' : 'false') . ')',
        is_int($v) => 'int(' . $v . ')',
        is_float($v) => 'float(' . (floor($v) === $v && abs($v) < 1e15 ? (string) (int) $v : (string) $v) . ')',
        is_string($v) => 'string(' . strlen($v) . ') "' . $v . '"',
        default => get_debug_type($v),
    };
}

return [

    // ───────── 01 · Variables, types et opérateurs ─────────
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

    'conversion' => ['01', static function (): array {
        $m = alea(61, 599);
        $attendu = intdiv($m, 60) . ' h ' . sprintf('%02d', $m % 60);

        return creerSaisie('', ['Qu\'affiche ce code ?', "|\$minutes = $m;", "|echo intdiv(\$minutes, 60) . ' h ' . sprintf('%02d', \$minutes % 60);"], $attendu,
            sprintf('intdiv(%d, 60) = %d heure(s) ; %d %% 60 = %d minute(s), que %%02d écrit sur deux chiffres : « %s ».', $m, intdiv($m, 60), $m, $m % 60, $attendu), false, 'format : 1 h 05');
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

        return creerSaisie('', ['Qu\'affiche ce code ?', "|echo $affiche;"], (string) $valeur, $why);
    }],

    // ───────── 02 · Conditions et comparaisons ─────────
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

    'coalesce' => ['02', static function (): array {
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

    'logique' => ['02', static function (): array {
        $a = alea(0, 1) === 1;
        $b = alea(0, 1) === 1;
        $c = alea(0, 1) === 1;
        $modeles = [
            ['$a || $b && $c', static fn (bool $a, bool $b, bool $c): bool => $a || $b && $c, '&& est prioritaire sur || : on calcule d\'abord $b && $c, puis le ||.'],
            ['$a && $b || $c', static fn (bool $a, bool $b, bool $c): bool => $a && $b || $c, '&& est prioritaire sur || : l\'expression vaut ($a && $b) || $c.'],
            ['!$a && $b', static fn (bool $a, bool $b, bool $c): bool => !$a && $b, '! ne s\'applique qu\'à $a : l\'expression vaut (!$a) && $b.'],
            ['!($a && $b)', static fn (bool $a, bool $b, bool $c): bool => !($a && $b), 'Les parenthèses calculent d\'abord $a && $b, puis ! inverse le résultat.'],
            ['!$a || !$b', static fn (bool $a, bool $b, bool $c): bool => !$a || !$b, 'Loi de De Morgan : !$a || !$b équivaut à !($a && $b).'],
            ['$a xor $b', static fn (bool $a, bool $b, bool $c): bool => $a xor $b, 'xor (ou exclusif) est vrai quand exactement un des deux opérandes est vrai.'],
            ['($a || $b) && !$c', static fn (bool $a, bool $b, bool $c): bool => ($a || $b) && !$c, 'Les parenthèses d\'abord ($a || $b), puis !$c, puis le &&.'],
        ];
        [$expr, $fn, $why] = tirer($modeles);
        $valeur = $fn($a, $b, $c);
        $litteral = static fn (bool $x): string => $x ? 'true' : 'false';

        return creerQcm('', ['Que produit ce code ?', '|$a = ' . $litteral($a) . ';', '|$b = ' . $litteral($b) . ';', '|$c = ' . $litteral($c) . ';', "|var_dump($expr);"],
            $valeur ? 'bool(true)' : 'bool(false)', [$valeur ? 'bool(false)' : 'bool(true)'], $why);
    }],

    'match_valeur' => ['02', static function (): array {
        $note = alea(0, 20);
        $mention = match (true) {
            $note >= 16 => 'Très bien',
            $note >= 14 => 'Bien',
            $note >= 10 => 'Passable',
            default => 'Insuffisant',
        };
        $autres = array_values(array_diff(['Très bien', 'Bien', 'Passable', 'Insuffisant'], [$mention]));

        return creerQcm('', ['Qu\'affiche ce code ?', "|\$note = $note;", '|echo match (true) {', "|    \$note >= 16 => 'Très bien',", "|    \$note >= 14 => 'Bien',", "|    \$note >= 10 => 'Passable',", "|    default => 'Insuffisant',", '|};'],
            $mention, $autres, "match (true) renvoie la valeur du PREMIER bras dont la condition vaut true : les seuils sont rangés du plus exigeant au plus large. Pour $note : $mention.");
    }],

    // ───────── 03 · Boucles ─────────
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

    'boucle_controle' => ['03', static function (): array {
        $n = alea(6, 9);
        $k = alea(2, 4);
        $m = alea(3, $n - 1);
        $sortie = '';
        for ($i = 1; $i <= $n; $i++) {
            if ($i % $k === 0) {
                continue;
            }
            if ($i > $m) {
                break;
            }
            $sortie .= $i;
        }
        $testContinue = ["|    if (\$i % $k === 0) {", '|        continue;', '|    }'];
        $testBreak = ["|    if (\$i > $m) {", '|        break;', '|    }'];
        $corps = alea(0, 1) === 1 ? array_merge($testContinue, $testBreak) : array_merge($testBreak, $testContinue);

        return creerSaisie('', array_merge(['Qu\'affiche ce code ?', "|for (\$i = 1; \$i <= $n; \$i++) {"], $corps, ['|    echo $i;', '|}']), $sortie,
            "continue saute la fin du tour : les multiples de $k ne sont pas affichés. break quitte la boucle dès que \$i dépasse $m. Résultat : $sortie.");
    }],

    // ───────── 04 · Tableaux ─────────
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

    'tableaux_fonctions' => ['04', static function (): array {
        $t = [];
        while (count($t) < 4) {
            $v = alea(1, 9);
            if (!in_array($v, $t, true)) {
                $t[] = $v;
            }
        }
        $liste = listePhp($t);
        $k = alea(2, 7);
        $indice = '';
        switch (alea(1, 6)) {
            case 1:
                $code = "count(array_filter($liste, fn(\$x) => \$x > $k))";
                $rep = [(string) count(array_filter($t, static fn (int $x): bool => $x > $k))];
                $why = "array_filter garde les éléments pour lesquels la fonction renvoie true (ici ceux > $k) ; count les compte.";
                break;
            case 2:
                $k = alea(2, 5);
                $code = "array_map(fn(\$x) => \$x * $k, $liste)";
                $rep = variantesListe(array_map(static fn (int $x): int => $x * $k, $t));
                $why = 'array_map applique la fonction à chaque élément et renvoie un nouveau tableau, dans le même ordre.';
                $indice = 'format : [1, 2, 3, 4]';
                break;
            case 3:
                $cherche = tirer($t);
                $code = "array_search($cherche, $liste)";
                $rep = [(string) array_search($cherche, $t, true)];
                $why = 'array_search renvoie la CLÉ de la première occurrence ; la première clé d\'une liste vaut 0.';
                break;
            case 4:
                $code = "array_sum(array_slice($liste, 1, 2))";
                $rep = [(string) array_sum(array_slice($t, 1, 2))];
                $why = 'array_slice($t, 1, 2) prend 2 éléments à partir de l\'indice 1 ; array_sum les additionne.';
                break;
            case 5:
                $code = "implode('-', array_reverse($liste))";
                $rep = [implode('-', array_reverse($t))];
                $why = 'array_reverse inverse l\'ordre ; implode colle les éléments avec le séparateur.';
                $indice = 'format : 1-2-3-4';
                break;
            default:
                $code = "array_keys(['a' => {$t[0]}, 'b' => {$t[1]}, 'c' => {$t[0]}], {$t[0]})";
                $cles = array_keys(['a' => $t[0], 'b' => $t[1], 'c' => $t[0]], $t[0]);
                $rep = array_merge(variantesListe($cles), [listePhp($cles), '["' . implode('", "', $cles) . '"]']);
                $why = 'Avec un second argument, array_keys ne renvoie que les clés dont la valeur est égale à celle cherchée.';
                $indice = "format : ['a', 'b']";
        }

        return creerSaisie('', ['Que retourne cette expression ?', "|$code"], $rep, $why, false, $indice);
    }],

    // ───────── 05 · Chaînes de caractères ─────────
    'chaines' => ['05', static function (): array {
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

    'chaines_fonctions' => ['05', static function (): array {
        $mot = tirer(['bonjour', 'tableau', 'variable', 'fonction', 'boucle', 'serveur', 'session', 'requete']);
        switch (alea(1, 6)) {
            case 1:
                $debut = alea(0, 2);
                $longueur = alea(2, 4);
                $code = "substr('$mot', $debut, $longueur)";
                $rep = substr($mot, $debut, $longueur);
                $why = 'substr(chaîne, début, longueur) : le premier caractère est à la position 0.';
                break;
            case 2:
                $code = "strrev('$mot')";
                $rep = strrev($mot);
                $why = 'strrev inverse la chaîne, octet par octet (à éviter sur du texte accentué).';
                break;
            case 3:
                $n = alea(1, 99);
                $longueur = alea(4, 6);
                $code = "str_pad('$n', $longueur, '0', STR_PAD_LEFT)";
                $rep = str_pad((string) $n, $longueur, '0', STR_PAD_LEFT);
                $why = 'str_pad complète la chaîne à gauche (STR_PAD_LEFT) jusqu\'à la longueur demandée.';
                break;
            case 4:
                $lettre = tirer(str_split($mot));
                $code = "substr_count('$mot', '$lettre')";
                $rep = (string) substr_count($mot, $lettre);
                $why = 'substr_count compte les occurrences de la sous-chaîne.';
                break;
            case 5:
                $lettre = $mot[alea(1, strlen($mot) - 1)];
                $code = "strpos('$mot', '$lettre')";
                $rep = (string) strpos($mot, $lettre);
                $why = 'strpos renvoie la position de la PREMIÈRE occurrence, en comptant à partir de 0 (ou false si elle est absente).';
                break;
            default:
                $code = "strlen(trim('  $mot  '))";
                $rep = (string) strlen($mot);
                $why = 'trim retire les espaces du début et de la fin ; strlen compte ensuite les octets (une lettre sans accent = un octet).';
        }

        return creerSaisie('', ['Que retourne cet appel ?', "|echo $code;"], $rep, $why);
    }],

    // ───────── 06 · Fonctions et récursivité ─────────
    'factorielle' => ['06', static function (): array {
        $n = alea(3, 8);
        $resultat = array_product(range(1, $n));

        return creerSaisie('', ['Qu\'affiche ce code ?', '|function f(int $n): int {', '|    return $n <= 1 ? 1 : $n * f($n - 1);', '|}', "|echo f($n);"], (string) $resultat,
            "f calcule une factorielle : f($n) = " . implode(' × ', range($n, 1)) . " = $resultat. Le cas de base (\$n <= 1) arrête la récursion.");
    }],

    'fib_appels' => ['06', static function (): array {
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

    'somme_chiffres' => ['06', static function (): array {
        $n = alea(100, 99999);
        $chiffres = str_split((string) $n);

        return creerSaisie('', ['Qu\'affiche ce code ?', '|function s(int $n): int {', '|    return $n < 10 ? $n : $n % 10 + s(intdiv($n, 10));', '|}', "|echo s($n);"], (string) array_sum($chiffres),
            'Chaque appel garde le dernier chiffre ($n % 10) et rappelle s() sur le reste (intdiv($n, 10)) : ' . implode(' + ', $chiffres) . ' = ' . array_sum($chiffres) . '.');
    }],

    // ───────── 07 · Erreurs et exceptions ─────────
    'exceptions_flux' => ['07', static function (): array {
        $x = alea(-9, 9);
        if ($x === 0) {
            $x = 4;
        }
        $classe = tirer(['InvalidArgumentException', 'LogicException', 'Exception', 'Throwable']);
        $sortie = 'A' . ($x < 0 ? 'D' : 'BC') . 'EF';
        $why = $x < 0
            ? "verifier($x) lève une exception : la fin du try (C) est sautée. catch ($classe) l'attrape, car InvalidArgumentException " . ($classe === 'InvalidArgumentException' ? 'est exactement cette classe' : 'en hérite') . ' : D. finally s\'exécute toujours (E), puis le script continue (F).'
            : "Aucune exception : le try s'exécute en entier (A, B, C) et le catch est ignoré. finally s'exécute toujours (E), puis le script continue (F).";

        return creerSaisie('', [
            'Qu\'affiche ce code ? (les lettres, dans l\'ordre)',
            '|function verifier(int $x): string {',
            '|    if ($x < 0) {',
            "|        throw new InvalidArgumentException('négatif');",
            '|    }',
            "|    return 'B';",
            '|}',
            '|try {',
            "|    echo 'A';",
            "|    echo verifier($x);",
            "|    echo 'C';",
            "|} catch ($classe \$e) {",
            "|    echo 'D';",
            '|} finally {',
            "|    echo 'E';",
            '|}',
            "|echo 'F';",
        ], $sortie, $why, false, 'des lettres, par exemple ABC');
    }],

    // ───────── 08 · Classes et objets ─────────
    'objets_copie' => ['08', static function (): array {
        $x = alea(1, 9);
        $y = alea(10, 99);
        $classe = '|class Boite { public int $v = 0; }';
        $cas = [
            [[$classe, '|$a = new Boite();', "|\$a->v = $x;", '|$b = $a;', "|\$b->v = $y;", '|echo $a->v;'], $y,
                '$b = $a ne copie pas l\'objet : $a et $b désignent le même objet, la modification se voit des deux côtés.'],
            [[$classe, '|$a = new Boite();', "|\$a->v = $x;", '|$b = clone $a;', "|\$b->v = $y;", '|echo $a->v;'], $x,
                'clone crée un second objet : modifier $b ne touche pas $a.'],
            [["|\$a = ['v' => $x];", '|$b = $a;', "|\$b['v'] = $y;", "|echo \$a['v'];"], $x,
                'Un tableau est copié à l\'affectation : $b est indépendant de $a (contrairement à un objet).'],
            [[$classe, "|function changer(Boite \$o): void { \$o->v = $y; }", '|$a = new Boite();', "|\$a->v = $x;", '|changer($a);', '|echo $a->v;'], $y,
                'La fonction reçoit le même objet que l\'appelant : sa modification reste visible après l\'appel.'],
            [["|function changer(array \$t): void { \$t['v'] = $y; }", "|\$a = ['v' => $x];", '|changer($a);', "|echo \$a['v'];"], $x,
                'La fonction travaille sur une copie du tableau : l\'original n\'est pas modifié (il faudrait array &$t).'],
        ];
        [$lignes, $rep, $why] = tirer($cas);

        return creerSaisie('', array_merge(['Qu\'affiche ce code ?'], $lignes), (string) $rep, $why);
    }],

    // ───────── 09 · Héritage et polymorphisme ─────────
    'heritage' => ['09', static function (): array {
        $variante = alea(1, 4);
        $lignes = [
            '|class Animal {',
            '|    ' . ($variante === 4 ? 'private' : 'public') . " function parler(): string { return 'Bruit'; }",
            "|    public function presenter(): string { return 'Je dis ' . \$this->parler(); }",
            '|}',
            '|class Chien extends Animal {',
            "|    public function parler(): string { return 'Ouaf'; }",
        ];
        if ($variante === 3) {
            $lignes[] = "|    public function presenter(): string { return parent::presenter() . ' !'; }";
        }
        $lignes[] = '|}';
        $lignes[] = $variante === 2 ? '|echo (new Animal())->presenter();' : '|echo (new Chien())->presenter();';
        $bonne = match ($variante) {
            1 => 'Je dis Ouaf',
            2 => 'Je dis Bruit',
            3 => 'Je dis Ouaf !',
            default => 'Je dis Bruit',
        };
        $why = match ($variante) {
            1 => 'Polymorphisme : $this désigne un Chien, donc $this->parler() exécute la version redéfinie dans Chien.',
            2 => 'L\'objet est un Animal : c\'est sa propre méthode parler() qui s\'exécute.',
            3 => 'Chien::presenter() appelle parent::presenter(), qui utilise $this->parler() — la version de Chien — puis ajoute « ! ».',
            default => 'Une méthode private n\'est pas redéfinissable : appelée depuis Animal, $this->parler() exécute Animal::parler(). Chien::parler() est une méthode distincte.',
        };
        $options = ['Je dis Ouaf', 'Je dis Bruit', 'Je dis Ouaf !', 'Je dis Bruit !', 'Une erreur fatale'];

        return creerQcm('', array_merge(['Qu\'affiche ce code ?'], $lignes), $bonne, array_slice(melanger(array_values(array_diff($options, [$bonne]))), 0, 3), $why);
    }],

    // ───────── 11 · Namespaces et membres statiques ─────────
    'statique_compteur' => ['11', static function (): array {
        $k = alea(2, 5);
        $j = alea(1, $k);
        $noms = ['$a', '$b', '$c', '$d', '$e'];
        $lignes = ['|class Ticket {', '|    public static int $total = 0;', '|    public int $numero;', '|    public function __construct() {', '|        self::$total++;', '|        $this->numero = self::$total;', '|    }', '|}'];
        for ($i = 0; $i < $k; $i++) {
            $lignes[] = '|' . $noms[$i] . ' = new Ticket();';
        }
        $lignes[] = "|echo Ticket::\$total . '-' . " . $noms[$j - 1] . '->numero;';

        return creerSaisie('', array_merge(['Qu\'affiche ce code ?'], $lignes), "$k-$j",
            "La propriété static \$total est partagée par toute la classe : elle vaut $k après $k créations. Chaque objet garde son propre \$numero : $j pour " . $noms[$j - 1] . '.', false, 'format : 3-1');
    }],

    // ───────── 12 · HTTP et superglobales ─────────
    'query_string' => ['12', static function (): array {
        $page = alea(2, 9);
        $tri = tirer(['prix', 'nom', 'date']);
        $tags = array_slice(melanger(['php', 'sql', 'js', 'css', 'html']), 0, alea(2, 3));
        $query = "page=$page&tri=$tri&" . implode('&', array_map(static fn (string $t): string => "tags[]=$t", $tags));
        parse_str($query, $get);
        $cas = [
            ["count(\$_GET['tags'])", (string) count($get['tags']), 'Les crochets de tags[] demandent à PHP de regrouper les valeurs dans un tableau.'],
            ["\$_GET['tags'][1]", (string) $get['tags'][1], 'Les valeurs de tags[] sont rangées dans l\'ordre de l\'URL, à partir de l\'indice 0.'],
            ["\$_GET['tri'] ?? 'nom'", (string) $get['tri'], 'La clé tri existe : ?? garde sa valeur.'],
            ["\$_GET['q'] ?? 'aucune'", 'aucune', 'La clé q est absente : ?? fournit la valeur par défaut, sans avertissement.'],
            ["gettype(\$_GET['page'])", 'string', 'Tout ce qui arrive dans l\'URL est une chaîne : "' . $page . '", pas ' . $page . '. On convertit après validation (filter_var).'],
        ];
        [$expr, $rep, $why] = tirer($cas);

        return creerSaisie('', ["Requête reçue : GET /recherche?$query", 'Qu\'affiche ce code côté PHP ?', "|echo $expr;"], $rep, $why);
    }],

    // ───────── 13 · Formulaires et validation ─────────
    'filter_var' => ['13', static function (): array {
        $cas = [
            ['42', 'FILTER_VALIDATE_INT'], [' 42 ', 'FILTER_VALIDATE_INT'], ['4.2', 'FILTER_VALIDATE_INT'], ['abc', 'FILTER_VALIDATE_INT'],
            ['-7', 'FILTER_VALIDATE_INT'], ['007', 'FILTER_VALIDATE_INT'], ['0', 'FILTER_VALIDATE_INT'], ['12abc', 'FILTER_VALIDATE_INT'],
            ['4.2', 'FILTER_VALIDATE_FLOAT'], ['4,2', 'FILTER_VALIDATE_FLOAT'], ['1e3', 'FILTER_VALIDATE_FLOAT'],
            ['oui', 'FILTER_VALIDATE_BOOLEAN'], ['on', 'FILTER_VALIDATE_BOOLEAN'], ['1', 'FILTER_VALIDATE_BOOLEAN'],
        ];
        [$valeur, $filtre] = tirer($cas);
        $bonne = texteVarDump(filter_var($valeur, constant($filtre)));
        $pool = ['bool(false)', 'bool(true)', 'NULL', 'int(42)', 'int(0)', 'float(4.2)', 'string(' . strlen($valeur) . ') "' . $valeur . '"'];
        $mauvaises = array_slice(melanger(array_values(array_unique(array_diff($pool, [$bonne])))), 0, 3);
        $why = match ($filtre) {
            'FILTER_VALIDATE_INT' => 'FILTER_VALIDATE_INT renvoie un int si la chaîne représente exactement un entier (espaces autour tolérés, pas de zéro en tête), sinon false.',
            'FILTER_VALIDATE_FLOAT' => 'FILTER_VALIDATE_FLOAT accepte le point décimal et la notation scientifique, pas la virgule française : il renvoie un float ou false.',
            default => 'FILTER_VALIDATE_BOOLEAN reconnaît "1", "true", "on" et "yes" comme vrais ; toute autre valeur donne false (ou null avec FILTER_NULL_ON_FAILURE).',
        };

        return creerQcm('', ['Que produit cet appel ?', "|var_dump(filter_var('$valeur', $filtre));"], $bonne, $mauvaises, $why . ' On teste toujours le résultat avec === false.');
    }],

    // ───────── 14 · Cookies et sessions ─────────
    'cookie_secondes' => ['14', static function (): array {
        $j = tirer([1, 7, 14, 30, 60, 90]);

        return creerSaisie('', "Un cookie doit expirer dans $j jour(s) : quelle valeur (en secondes) ajoute-t-on à `time()` ?", (string) ($j * 86400),
            "$j × 24 × 3600 = " . ($j * 86400) . ' secondes (86 400 s par jour).');
    }],

    // ───────── 15 · Sécurité des applications web ─────────
    'csrf_hex' => ['15', static function (): array {
        $n = tirer([8, 16, 24, 32, 48, 64]);

        return creerSaisie('', "Combien de caractères retourne `bin2hex(random_bytes($n))` ?", (string) (2 * $n),
            "Chaque octet s'écrit avec 2 caractères hexadécimaux : $n × 2 = " . (2 * $n) . ' (random_bytes(32) → jeton de 64 caractères).');
    }],

    'echappement' => ['15', static function (): array {
        $s = tirer(['<b>Dune</b>', 'Tom & Jerry', '"Alien"', '5 > 3', "L'Armée", '<script>alert(1)</script>', 'a<b', '"x" & \'y\'']);
        $rep = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return creerSaisie('', ['Que retourne cet appel ? (tape la chaîne HTML obtenue)', "|htmlspecialchars('" . str_replace("'", "\\'", $s) . "', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');"], $rep,
            '< → &lt; · > → &gt; · & → &amp; · " → &quot; · \' → &#039; (avec ENT_QUOTES).', false, 'entités : &lt; &gt; &amp; &quot; &#039;');
    }],

    // ───────── 16 · Fichiers, dates et JSON ─────────
    'json_encode' => ['16', static function (): array {
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

    'json_decode' => ['16', static function (): array {
        $age = alea(18, 80);
        $nom = tirer(['Ada', 'Alan', 'Grace', 'Linus']);
        $n = alea(2, 6);
        $json = (string) json_encode(['nom' => $nom, 'age' => $age]);
        $cas = [
            ["\$d = json_decode('$json', true);", "echo \$d['age'];", (string) $age, 'Avec true, json_decode renvoie un tableau associatif : $d[\'age\'].'],
            ["\$d = json_decode('$json');", 'echo $d->nom;', $nom, 'Sans second argument, json_decode renvoie un objet stdClass : on lit $d->nom.'],
            ["\$d = json_decode('" . json_encode(range(1, $n)) . "', true);", 'echo count($d);', (string) $n, 'Une liste JSON devient un tableau indexé de 0 à n-1.'],
            ["\$d = json_decode('{\"age\": $age');", "echo \$d === null ? 'null' : 'tableau';", 'null', 'Le JSON est mal formé (accolade fermante absente) : json_decode renvoie null. Avec JSON_THROW_ON_ERROR, on obtiendrait une JsonException.'],
        ];
        [$ligne1, $ligne2, $rep, $why] = tirer($cas);

        return creerSaisie('', ['Qu\'affiche ce code ?', "|$ligne1", "|$ligne2"], $rep, $why);
    }],

    'date_ajout' => ['16', static function (): array {
        $depart = (new DateTimeImmutable('2026-01-01'))->modify('+' . alea(0, 364) . ' days');
        $modif = tirer(['+' . alea(2, 40) . ' days', '+' . alea(1, 4) . ' weeks', '-' . alea(2, 30) . ' days']);
        $arrivee = $depart->modify($modif);

        return creerSaisie('', ['Qu\'affiche ce code ? (JJ/MM/AAAA)', "|\$d = new DateTimeImmutable('" . $depart->format('Y-m-d') . "');", "|echo \$d->modify('$modif')->format('d/m/Y');"],
            [$arrivee->format('d/m/Y'), $arrivee->format('Y-m-d')],
            'modify() comprend les expressions relatives (+N days, +N weeks, -N days) et tient compte de la longueur des mois. Avec DateTimeImmutable, $d lui-même ne change pas. Résultat : ' . $arrivee->format('d/m/Y') . '.', false, 'format JJ/MM/AAAA');
    }],

    // ───────── 18 · PDO : connexion et lecture ─────────
    'pagination' => ['18', static function (): array {
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

    // ───────── 19 · PDO : écriture et transactions ─────────
    'places' => ['19', static function (): array {
        $p = alea(1, 4);
        $k = alea($p + 1, 9);

        return creerSaisie('', [
            "Il reste $p place(s). $k utilisateurs réservent EN MÊME TEMPS ; chacun exécute (dans une transaction) :",
            '|UPDATE seances SET places_restantes = places_restantes - 1',
            '|WHERE id = :id AND places_restantes > 0;',
            'Au maximum, combien de réservations peuvent réussir ?',
        ], (string) $p, "La mise à jour conditionnelle est atomique : seules les $p premières trouvent places_restantes > 0 ; les autres ont rowCount() = 0 → refus.");
    }],

    // ───────── 20 · Composer et autoload ─────────
    'psr4' => ['20', static function (): array {
        $classes = [
            ['App\\Domain\\Film', 'src/Domain/Film.php'],
            ['App\\Domain\\Duree', 'src/Domain/Duree.php'],
            ['App\\Repository\\FilmRepository', 'src/Repository/FilmRepository.php'],
            ['App\\Controller\\FilmController', 'src/Controller/FilmController.php'],
            ['App\\Service\\FilmValidator', 'src/Service/FilmValidator.php'],
            ['App\\Service\\Facturation', 'src/Service/Facturation.php'],
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

    // ───────── 21 · MVC, routage et frameworks ─────────
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

    // ───────── 22 · API REST ─────────
    'http_statut' => ['22', static function (): array {
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
            ['Une route est déclarée, mais pas encore programmée.', 501],
        ];
        [$texte, $code] = tirer($scenarios);
        $autres = array_values(array_filter(array_keys($libelles), static fn (int $c): bool => $c !== $code));
        $mauvaises = array_map(static fn (int $c): string => $libelles[$c], array_slice(melanger($autres), 0, 3));

        return creerQcm('', "Quel statut HTTP convient ? $texte", $libelles[$code], $mauvaises,
            'À retenir : 400 = requête inexploitable, 404 = ressource absente, 415 = média non géré, 422 = règle métier violée, 403 = interdit, 401 = non authentifié, 303 = redirection vers un GET, 204 = succès sans corps, 201 = créé, 500 = panne serveur.');
    }],

    // ───────── 23 · Sécuriser une API : JWT ─────────
    'jwt_exp' => ['23', static function (): array {
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

    'jwt_verification' => ['23', static function (): array {
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

    'jwt_statut' => ['23', static function (): array {
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

    // ───────── 24 · JavaScript : les fondamentaux ─────────
    'js_expression' => ['24', static function (): array {
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

    'js_typeof' => ['24', static function (): array {
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

    'js_falsy' => ['24', static function (): array {
        $falsy = ['0', '""', 'null', 'undefined', 'NaN', 'false', '-0'];
        $truthy = ['"0"', '"false"', '[]', '{}', '1', '-1', '" "', 'Infinity', '"null"'];
        $nbFalsy = alea(1, 4);
        $valeurs = melanger(array_merge(array_slice(melanger($falsy), 0, $nbFalsy), array_slice(melanger($truthy), 0, 6 - $nbFalsy)));

        return creerSaisie('', ['Combien de ces six valeurs sont falsy (considérées comme fausses par un if) ?', '|' . implode('    ', $valeurs)], (string) $nbFalsy,
            'Falsy : false, 0, -0, "", null, undefined et NaN. Tout le reste est truthy : une chaîne non vide (même "0", "false" ou " "), [] et {}, les nombres non nuls.');
    }],

    'js_boucle' => ['24', static function (): array {
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

    'js_divisions' => ['24', static function (): array {
        do {
            $k = alea(0, 7);
            $n = (2 * alea(1, 60) + 1) * (2 ** $k);
        } while ($n <= 100 || $n > 9999);
        $etapes = [$n];
        for ($x = $n; $x % 2 === 0;) {
            $x = intdiv($x, 2);
            $etapes[] = $x;
        }

        return creerSaisie('', ["Que s'affiche-t-il ?", "|let n = $n;", '|let divisions = 0;', '|while (n % 2 === 0) {', '|    n = n / 2;', '|    divisions++;', '|}', '|console.log(divisions);'],
            (string) $k, 'Suite : ' . implode(' → ', $etapes) . ($k === 0 ? " : $n est impair, la boucle ne tourne pas." : " : $k division(s), on s'arrête sur un nombre impair."));
    }],

    'js_tableau' => ['24', static function (): array {
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

    // ───────── 25 · JavaScript : fonctions, objets et stockage ─────────
    'js_json' => ['25', static function (): array {
        $q = alea(0, 60);
        $p = alea(2, 9);
        $nom = tirer(['Stylo', 'Cahier', 'Gomme', 'Règle']);
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

    'js_json_parse' => ['25', static function (): array {
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

    'js_conversion' => ['25', static function (): array {
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

        return creerSaisie('', ['Que renvoie le dernier appel ? (un nombre, ou null)', '|function convertirEntier(saisie) {', '|    if (saisie === null || saisie.trim() === "") {', '|        return null;', '|    }', '|    const nombre = Number(saisie);', '|    if (!Number.isInteger(nombre) || nombre < 0) {', '|        return null;', '|    }', '|    return nombre;', '|}', "|convertirEntier($arg);"],
            $rep, 'Contrat : un entier positif ou nul, sinon null. Ici, ' . $why);
    }],

    // ───────── 26 · JavaScript : DOM et événements ─────────
    'js_selecteur' => ['26', static function (): array {
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

    'js_classlist' => ['26', static function (): array {
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

    // ───────── 27 · JavaScript : asynchrone et fetch ─────────
    'js_ordre' => ['27', static function (): array {
        [$a, $b, $c, $d] = array_slice(melanger(['A', 'B', 'C', 'D', 'E']), 0, 4);
        $log = static fn (string $x): string => 'console.log("' . $x . '")';
        $modeles = [
            [[$log($a) . ';', 'setTimeout(() => ' . $log($b) . ', 0);', $log($c) . ';'], $a . $c . $b,
                'setTimeout place le rappel dans la file des tâches : il attend la fin du code synchrone, même avec un délai de 0.'],
            [[$log($a) . ';', 'Promise.resolve().then(() => ' . $log($b) . ');', $log($c) . ';'], $a . $c . $b,
                'Le rappel d\'une promesse est une microtâche : il s\'exécute après le code synchrone.'],
            [[$log($a) . ';', 'setTimeout(() => ' . $log($b) . ', 0);', 'Promise.resolve().then(() => ' . $log($c) . ');', $log($d) . ';'], $a . $d . $c . $b,
                'Le code synchrone d\'abord, puis les microtâches (promesses), puis les tâches (setTimeout).'],
            [['setTimeout(() => ' . $log($a) . ', 0);', 'Promise.resolve().then(() => ' . $log($b) . ');', $log($c) . ';'], $c . $b . $a,
                'Seul le dernier console.log est synchrone ; la promesse (microtâche) passe ensuite avant setTimeout (tâche).'],
            [['setTimeout(() => ' . $log($a) . ', 50);', 'setTimeout(() => ' . $log($b) . ', 0);', $log($c) . ';'], $c . $b . $a,
                'Le code synchrone d\'abord ; les minuteurs se déclenchent ensuite selon leur délai (0 ms avant 50 ms).'],
            [['async function f() {', '    ' . $log($a) . ';', '    await null;', '    ' . $log($b) . ';', '}', 'f();', $log($c) . ';'], $a . $c . $b,
                'Une fonction async s\'exécute de façon synchrone jusqu\'au premier await ; la suite reprend plus tard, en microtâche.'],
        ];
        [$code, $ordre, $why] = tirer($modeles);
        $lettres = str_split($ordre);

        return creerSaisie('', array_merge(['Dans quel ordre les lettres s\'affichent-elles dans la console ?'], array_map(static fn (string $l): string => '|' . $l, $code)),
            [$ordre, implode(', ', $lettres)], $why . ' Ordre : ' . implode(', ', $lettres) . '.', false, 'les lettres dans l\'ordre, par exemple ABC');
    }],
];
