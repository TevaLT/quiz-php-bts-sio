<?php

declare(strict_types=1);

/**
 * Petits assistants d'affichage. Chacun échappe ce qu'il produit : leur résultat est du HTML sûr.
 */

/** Texte → HTML : échappe, puis met en forme les `extraits de code` entre accents graves. */
function texteHtml(string $texte): string
{
    return (string) preg_replace('/`([^`]+)`/u', '<code>$1</code>', e($texte));
}

/** Espaces insécables à l'intérieur des guillemets français : « et » ne restent jamais seuls en bout de ligne. */
function typographie(string $texte): string
{
    return str_replace(['« ', ' »'], ["«\u{00A0}", "\u{00A0}»"], $texte);
}

/** Énoncé d'une question : paragraphes de texte + blocs de code (lignes qui commencent par « | » dans la banque). */
function enonceHtml(array $q): string
{
    $html = '';
    $code = [];
    $viderCode = static function () use (&$html, &$code): void {
        if ($code !== []) {
            $html .= '<pre class="code"><code>' . colorerCode(implode("\n", $code)) . '</code></pre>';
            $code = [];
        }
    };
    foreach ($q['q'] as $segment) {
        if ($segment['code']) {
            $code[] = $segment['t'];
        } else {
            $viderCode();
            $html .= '<p>' . texteHtml($segment['t']) . '</p>';
        }
    }
    $viderCode();

    return $html;
}

/**
 * Étiquette visible d'une proposition corrigée : « Ta réponse » ou « Bonne réponse », en texte (pas seulement en
 * couleur), pour comprendre la correction d'un coup d'œil. Chaîne vide pour une proposition fausse non choisie.
 */
function etiquetteChoix(bool $correcte, bool $choisie): string
{
    return match (true) {
        $correcte && $choisie => '<span class="etiquette-choix ok"><span aria-hidden="true">✔</span> Ta réponse<span class="sr-only"> (juste)</span></span>',
        $correcte => '<span class="etiquette-choix ok"><span aria-hidden="true">✔</span> Bonne réponse</span>',
        $choisie => '<span class="etiquette-choix ko"><span aria-hidden="true">✘</span> Ta réponse<span class="sr-only"> (fausse)</span></span>',
        default => '',
    };
}

/** Petit symbole « code » de chaque bloc de chapitres (tuiles de l'accueil). */
const ICONES_BLOCS = [1 => '$x', 2 => 'new', 3 => 'GET', 4 => 'SQL', 5 => 'API', 6 => 'JS'];

// ───────────────────────────── Coloration syntaxique ─────────────────────────────

/** Mots-clés colorés : PHP et JavaScript, puis SQL (en majuscules seulement, pour ne pas colorer du texte ordinaire). */
const MOTS_CLES_CODE = [
    'abstract', 'array', 'as', 'async', 'await', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'const', 'continue',
    'declare', 'default', 'do', 'echo', 'else', 'elseif', 'enum', 'extends', 'false', 'final', 'finally', 'float', 'fn', 'for',
    'foreach', 'function', 'if', 'implements', 'include', 'Infinity', 'instanceof', 'int', 'interface', 'iterable', 'let', 'match',
    'mixed', 'namespace', 'NaN', 'never', 'new', 'null', 'object', 'of', 'parent', 'print', 'private', 'protected', 'public',
    'readonly', 'require', 'require_once', 'return', 'self', 'static', 'string', 'switch', 'this', 'throw', 'true', 'try',
    'typeof', 'undefined', 'use', 'var', 'void', 'while', 'yield',
    'AND', 'ASC', 'BEGIN', 'BY', 'CHECK', 'COMMIT', 'COUNT', 'CREATE', 'DEFAULT', 'DELETE', 'DESC', 'DISTINCT', 'FOREIGN', 'FROM',
    'GROUP', 'HAVING', 'IN', 'INNER', 'INSERT', 'INTEGER', 'INTO', 'IS', 'JOIN', 'KEY', 'LEFT', 'LIKE', 'LIMIT', 'NOT', 'NULL',
    'OFFSET', 'ON', 'OR', 'ORDER', 'PRIMARY', 'REFERENCES', 'ROLLBACK', 'SELECT', 'SET', 'TABLE', 'TEXT', 'TRANSACTION', 'UNIQUE',
    'UPDATE', 'VALUES', 'WHERE',
];

/**
 * Coloration syntaxique légère d'un bloc de code (PHP, JavaScript, HTML, SQL), calculée par le serveur :
 * pas de bibliothèque, pas de script, compatible avec la politique CSP.
 *
 * Le code est découpé en morceaux (commentaire, chaîne, variable, balise…) ; CHAQUE morceau est échappé avec e(),
 * seules nos propres balises <span class="c-…"> sont ajoutées. Enlever ces balises redonne exactement le code échappé.
 */
function colorerCode(string $code): string
{
    static $motif = null;
    $motif ??= str_replace('MOTS', implode('|', MOTS_CLES_CODE), <<<'RE'
        ~(?<com>/\*[\s\S]*?\*/|<!--[\s\S]*?-->|(?<![:\w])//[^\n]*)|(?<str>"(?:\\.|[^"\\\n])*"|'(?:\\.|[^'\\\n])*'|`(?:\\.|[^`\\])*`)|(?<var>\$[A-Za-z_]\w*)|(?<tag><\?php\b|\?>|</?[A-Za-z][\w-]*)|(?<attr>\b[A-Za-z][\w-]*(?==["']))|(?<num>\b\d+(?:\.\d+)?\b)|(?<kw>\b(?:MOTS)\b)|(?<fn>\b[A-Za-z_]\w*(?=\())~
        RE);

    if (preg_match_all($motif, $code, $trouves, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === false) {
        return e($code); // filet de sécurité : la coloration ne doit jamais empêcher l'affichage
    }

    $html = '';
    $position = 0;
    foreach ($trouves as $morceau) {
        [$texte, $debut] = $morceau[0];
        $classe = 'fn';
        foreach (['com', 'str', 'var', 'tag', 'attr', 'num', 'kw'] as $nom) {
            if ($morceau[$nom][0] !== null) {
                $classe = $nom;
                break;
            }
        }
        $html .= e(substr($code, $position, $debut - $position)) . '<span class="c-' . $classe . '">' . e($texte) . '</span>';
        $position = $debut + strlen($texte);
    }

    return $html . e(substr($code, $position));
}

// ───────────────────────────── Petits graphiques SVG ─────────────────────────────

/** Barre de progression en SVG (pas de style en ligne : compatible avec la politique CSP du site). */
function barreSvg(int $pourcent, string $classe = ''): string
{
    $p = max(0, min(100, $pourcent));

    return '<svg class="barre ' . e($classe) . '" viewBox="0 0 100 8" preserveAspectRatio="none" role="img" aria-label="' . $p . ' %">'
        . '<rect class="barre-fond" width="100" height="8" rx="4"/>'
        . ($p > 0 ? '<rect class="barre-val" width="' . $p . '" height="8" rx="4"/>' : '')
        . '</svg>';
}

/** Anneau de score en SVG. */
function anneauSvg(int $pourcent, string $legende): string
{
    $p = max(0, min(100, $pourcent));
    $classe = $p >= 80 ? 'bon' : ($p >= 50 ? 'moyen' : 'faible');

    return '<svg class="anneau ' . $classe . '" viewBox="0 0 120 120" role="img" aria-label="' . e($legende) . '">'
        . '<circle class="anneau-fond" cx="60" cy="60" r="52" fill="none" stroke-width="10"/>'
        . '<circle class="anneau-val" cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round" pathLength="100" stroke-dasharray="' . $p . ' 100" transform="rotate(-90 60 60)"/>'
        . '<text class="anneau-pct" x="60" y="68" text-anchor="middle">' . $p . ' %</text>'
        . '</svg>';
}

/**
 * Graphique de progression : un point gris par partie (son score), une ligne colorée pour la moyenne glissante
 * (la série qui porte le message), une étiquette sur la dernière valeur. Tout est en SVG statique ;
 * app.js ajoute le survol et la navigation au clavier, et le tableau placé à côté donne les mêmes valeurs.
 *
 * @param list<array{n:int, date:string, score:int, total:int, pct:int, moyenne:int}> $points au moins deux parties
 */
function graphiqueProgression(array $points, int $largeur, int $hauteur, string $classe): string
{
    $n = count($points);
    if ($n < 2) {
        return '';
    }
    [$gauche, $droite, $haut, $bas] = [46, 50, 14, 30]; // marges : graduations, étiquette finale, respiration, numéros de partie
    $lp = $largeur - $gauche - $droite;
    $hp = $hauteur - $haut - $bas;
    $x = static fn (int $i): float => $gauche + $lp * $i / ($n - 1);
    $y = static fn (int $pct): float => $haut + $hp * (1 - max(0, min(100, $pct)) / 100);
    $f = static fn (float $v): string => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    $large = $largeur >= 500;

    $svg = '';
    foreach ($large ? [0, 25, 50, 75, 100] : [0, 50, 100] as $pct) {
        $svg .= '<line class="grille" x1="' . $gauche . '" x2="' . ($gauche + $lp) . '" y1="' . $f($y($pct)) . '" y2="' . $f($y($pct)) . '"/>'
            . '<text class="graduation" x="' . ($gauche - 8) . '" y="' . $f($y($pct) + 4) . '" text-anchor="end">' . $pct . ' %</text>';
    }

    // Numéros de partie : le premier, le dernier, et quelques-uns entre les deux (jamais collés au dernier).
    $pas = max(1, (int) ceil(($n - 1) / ($large ? 7 : 3)));
    for ($i = 0; $i < $n; $i++) {
        if ($i === $n - 1 || ($i % $pas === 0 && $n - 1 - $i >= max(1, intdiv($pas, 2)))) {
            $svg .= '<text class="graduation" x="' . $f($x($i)) . '" y="' . ($hauteur - 9) . '" text-anchor="middle">' . (int) $points[$i]['n'] . '</text>';
        }
    }

    $svg .= '<line class="reticule" x1="0" x2="0" y1="' . $haut . '" y2="' . ($haut + $hp) . '" data-reticule=""/>';

    $trace = [];
    foreach ($points as $i => $p) {
        $trace[] = $f($x($i)) . ',' . $f($y($p['moyenne']));
    }
    $svg .= '<polyline class="ligne-moyenne" points="' . implode(' ', $trace) . '"/>';

    $donnees = [];
    foreach ($points as $i => $p) {
        $svg .= '<circle class="point-partie" cx="' . $f($x($i)) . '" cy="' . $f($y($p['pct'])) . '" r="4"/>';
        $donnees[] = $p + [
            'dateAffichee' => formaterDate($p['date']),
            'x' => (float) $f($x($i)),
            'yScore' => (float) $f($y($p['pct'])),
            'yMoyenne' => (float) $f($y($p['moyenne'])),
        ];
    }

    $fin = $points[$n - 1];
    $svg .= '<circle class="point-moyenne" cx="' . $f($x($n - 1)) . '" cy="' . $f($y($fin['moyenne'])) . '" r="4.5"/>'
        . '<text class="etiquette-fin" x="' . $f($x($n - 1) + 10) . '" y="' . $f($y($fin['moyenne']) + 4) . '">' . (int) $fin['moyenne'] . ' %</text>'
        . '<circle class="survol survol-partie" r="6" cx="0" cy="0" data-survol-partie=""/>'
        . '<circle class="survol survol-moyenne" r="6" cx="0" cy="0" data-survol-moyenne=""/>';

    $resume = sprintf(
        'Progression sur %d parties : moyenne glissante de %d %% au départ, %d %% aujourd\'hui. Flèches gauche et droite pour parcourir les parties.',
        $n,
        $points[0]['moyenne'],
        $fin['moyenne']
    );

    return '<svg class="graphe-svg ' . e($classe) . '" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" role="img" tabindex="0"'
        . ' aria-label="' . e($resume) . '" data-points="' . e((string) json_encode($donnees, JSON_UNESCAPED_UNICODE)) . '">'
        . $svg . '</svg>';
}

function classeTaux(?int $taux): string
{
    return $taux === null ? 'neutre' : ($taux >= 80 ? 'bon' : ($taux >= 50 ? 'moyen' : 'faible'));
}

function formaterDuree(int $secondes): string
{
    return $secondes >= 60 ? intdiv($secondes, 60) . ' min ' . sprintf('%02d', $secondes % 60) . ' s' : $secondes . ' s';
}

/** Durée courte avec une décimale, à la française : 8.4 → « 8,4 s » ; au-delà d'une minute, « 1 min 05 s ». */
function formaterSecondes(float $secondes): string
{
    return $secondes >= 60 ? formaterDuree((int) round($secondes)) : number_format($secondes, 1, ',', '') . ' s';
}

/** « 2026-11-06 14:43 » → « 06/11/2026 14:43 » (date de l'historique). */
function formaterDate(string $date): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date);

    return $d === false ? $date : $d->format('d/m/Y H:i');
}
