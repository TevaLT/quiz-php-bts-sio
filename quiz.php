<?php

declare(strict_types=1);

/**
 * Quiz BTS SIO SLAM — apprendre PHP (bases, objet, web, PDO, MVC, API REST, JWT) et JavaScript.
 *
 *   php quiz.php                 menu interactif
 *   php quiz.php --n=20          20 questions aléatoires sur tout le programme
 *   php quiz.php --chrono=30     30 secondes par question
 *   php quiz.php --serie         jusqu'à la première erreur
 *   php quiz.php --aide          toutes les options
 *
 * Chaque session tire des questions DIFFÉRENTES :
 *   - une graine aléatoire neuve à chaque lancement ;
 *   - les questions vues lors des dernières sessions sont fortement pénalisées ;
 *   - les questions jamais vues sont favorisées ;
 *   - l'ordre des questions ET des propositions est mélangé ;
 *   - des questions « générées » changent leurs chiffres à chaque fois.
 */

if (PHP_SAPI !== 'cli') {
    exit('Lance le quiz dans un terminal : php quiz.php');
}

mb_internal_encoding('UTF-8');
require __DIR__ . '/moteur.php';

// ───────────────────────────── Terminal ─────────────────────────────

final class Terminal
{
    public static bool $couleur = true;
    public static bool $ascii = false;

    public static function initialiser(bool $sansCouleur, bool $ascii): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            if (function_exists('sapi_windows_cp_set')) {
                @sapi_windows_cp_set(65001);
            } else {
                @exec('chcp 65001 >NUL');
            }
        }
        $tty = function_exists('stream_isatty') && @stream_isatty(STDOUT);
        $vt = true;
        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_vt100_support')) {
            $vt = (bool) @sapi_windows_vt100_support(STDOUT, true);
        }
        self::$couleur = !$sansCouleur && $tty && $vt && getenv('NO_COLOR') === false;
        self::$ascii = $ascii;
    }

    public static function style(string $texte, string ...$styles): string
    {
        if (!self::$couleur) {
            return $texte;
        }
        $codes = [
            'gras' => '1', 'pale' => '2', 'rouge' => '31', 'vert' => '32', 'jaune' => '33',
            'bleu' => '34', 'magenta' => '35', 'cyan' => '36', 'gris' => '90',
        ];
        $seq = [];
        foreach ($styles as $s) {
            if (isset($codes[$s])) {
                $seq[] = $codes[$s];
            }
        }

        return $seq === [] ? $texte : "\033[" . implode(';', $seq) . 'm' . $texte . "\033[0m";
    }

    public static function symbole(string $nom): string
    {
        $unicode = ['ok' => '✔', 'ko' => '✘', 'trait' => '─', 'fleche' => '→', 'puce' => '•', 'plein' => '█', 'vide' => '░'];
        $ascii = ['ok' => 'OK', 'ko' => 'X', 'trait' => '-', 'fleche' => '->', 'puce' => '*', 'plein' => '#', 'vide' => '.'];

        return (self::$ascii ? $ascii : $unicode)[$nom] ?? '';
    }

    public static function ligne(string $texte = ''): void
    {
        echo $texte, PHP_EOL;
    }

    public static function regle(string $titre = ''): void
    {
        $t = self::symbole('trait');
        $barre = str_repeat($t, 3) . ($titre === '' ? '' : ' ' . $titre . ' ');
        $reste = max(0, 78 - mb_strlen($barre));
        self::ligne(self::style($barre . str_repeat($t, $reste), 'gris'));
    }

    public static function lire(string $invite): ?string
    {
        echo self::style($invite, 'gras') . ' ';
        $ligne = fgets(STDIN);
        if ($ligne === false) {
            return null; // fin du flux
        }

        return trim($ligne);
    }

    public static function interactif(): bool
    {
        return function_exists('stream_isatty') && @stream_isatty(STDIN);
    }
}

// ───────────────────────────── Affichage d'une question ─────────────────────────────

function afficherEnonce(array $q, int $num, int $total): void
{
    $titre = TITRES[$q['ch']] ?? '?';
    $type = match ($q['type']) {
        'multi' => 'plusieurs réponses',
        'saisie' => 'réponse libre',
        default => 'choix unique',
    };
    Terminal::ligne();
    Terminal::regle(sprintf('Question %s · Ch. %s %s · %s', $total > 0 ? "$num/$total" : (string) $num, $q['ch'], $titre, $type));
    foreach ($q['q'] as $seg) {
        Terminal::ligne($seg['code'] ? '    ' . Terminal::style($seg['t'], 'cyan') : Terminal::style($seg['t'], 'gras'));
    }
    if ($q['type'] === 'multi') {
        Terminal::ligne(Terminal::style('  (coche toutes les bonnes réponses, ex : A,C)', 'pale'));
    }
}

/**
 * Pose une question et renvoie ['statut' => ok|ko|passee|temps|quitter, 'reponse' => string, 'duree' => float].
 *
 * Avec un minuteur ($chrono secondes), le terminal ne peut pas interrompre la saisie : le temps est mesuré, et une
 * réponse donnée après la limite (plus la marge TOLERANCE_CHRONO) est comptée « temps écoulé ».
 *
 * @return array{statut:string, reponse:string, question:array<string,mixed>, duree:float}
 */
function poser(array $q, int $num, int $total, bool $examen, int $chrono = 0): array
{
    if ($q['type'] !== 'saisie') {
        $q['choix'] = melanger($q['choix']);
    }
    afficherEnonce($q, $num, $total);

    if ($q['type'] !== 'saisie') {
        foreach ($q['choix'] as $i => $c) {
            Terminal::ligne('   ' . Terminal::style(lettre($i) . ')', 'jaune') . ' ' . $c['t']);
        }
    } elseif ($q['indice'] !== '') {
        Terminal::ligne(Terminal::style('  (' . $q['indice'] . ')', 'pale'));
    }
    if ($chrono > 0) {
        Terminal::ligne(Terminal::style("  ({$chrono} s pour répondre)", 'pale'));
    }

    $nbChoix = count($q['choix']);
    $aide = $q['type'] === 'saisie' ? '/s = passer, /q = quitter' : 's = passer, q = quitter';
    $debut = microtime(true);
    $fin = static function (array $q, string $statut, string $reponse) use ($examen, $chrono, $debut): array {
        $duree = round(microtime(true) - $debut, 1);
        if ($chrono > 0 && $duree > $chrono + TOLERANCE_CHRONO && in_array($statut, ['ok', 'ko'], true)) {
            Terminal::ligne(Terminal::style(sprintf('  Temps écoulé : %s s pour %d s autorisées, la réponse ne compte pas.', number_format($duree, 1, ',', ''), $chrono), 'rouge'));
            $statut = 'temps';
        }
        retour($q, $statut, $reponse, $examen);

        return ['statut' => $statut, 'reponse' => $reponse, 'question' => $q, 'duree' => $duree];
    };
    while (true) {
        $saisie = Terminal::lire(Terminal::symbole('fleche') . " Ta réponse ($aide) :");
        if ($saisie === null) {
            return ['statut' => 'quitter', 'reponse' => '', 'question' => $q, 'duree' => 0.0];
        }
        $commande = mb_strtolower($saisie);
        if ($commande === '/q' || ($q['type'] !== 'saisie' && $commande === 'q')) {
            return ['statut' => 'quitter', 'reponse' => '', 'question' => $q, 'duree' => 0.0];
        }
        if ($commande === '/s' || ($q['type'] !== 'saisie' && $commande === 's')) {
            return $fin($q, 'passee', '');
        }

        if ($q['type'] === 'saisie') {
            if ($saisie === '') {
                continue;
            }

            return $fin($q, reponseLibreCorrecte($saisie, $q['acceptees']) ? 'ok' : 'ko', $saisie);
        }

        $lettres = array_unique(str_split((string) preg_replace('/[^A-Z]/', '', strtoupper($saisie))));
        $valides = true;
        foreach ($lettres as $L) {
            if (ord($L) - ord('A') >= $nbChoix) {
                $valides = false;
            }
        }
        if ($lettres === [] || !$valides || ($q['type'] === 'qcm' && count($lettres) !== 1)) {
            Terminal::ligne(Terminal::style('  Réponse non reconnue. Tape ' . ($q['type'] === 'qcm'
                ? 'une lettre entre A et ' . lettre($nbChoix - 1) : 'des lettres entre A et ' . lettre($nbChoix - 1) . ' (ex : A,C)') . '.', 'rouge'));
            continue;
        }
        $donnees = [];
        foreach ($lettres as $L) {
            $donnees[] = ord($L) - ord('A');
        }
        sort($donnees);
        $q['choisies'] = $donnees;

        return $fin($q, evaluerChoix($q, $donnees) ? 'ok' : 'ko', implode(',', array_map('lettre', $donnees)));
    }
}

function retour(array $q, string $statut, string $saisie, bool $examen): void
{
    if ($examen) {
        Terminal::ligne(Terminal::style('  ' . Terminal::symbole('puce') . ' réponse enregistrée', 'pale'));

        return;
    }
    if ($statut === 'ok') {
        Terminal::ligne(Terminal::style('  ' . Terminal::symbole('ok') . ' Bonne réponse !', 'vert', 'gras'));
    } elseif ($statut === 'passee') {
        Terminal::ligne(Terminal::style('  ' . Terminal::symbole('puce') . ' Question passée.', 'jaune'));
    } elseif ($statut === 'temps') {
        Terminal::ligne(Terminal::style('  ' . Terminal::symbole('ko') . ' Hors délai.', 'rouge', 'gras'));
    } else {
        Terminal::ligne(Terminal::style('  ' . Terminal::symbole('ko') . ' Raté.', 'rouge', 'gras'));
        if ($q['type'] === 'multi' && isset($q['choisies'])) {
            $manques = [];
            $en_trop = [];
            foreach ($q['choix'] as $i => $c) {
                $coche = in_array($i, $q['choisies'], true);
                if ($c['ok'] && !$coche) {
                    $manques[] = lettre($i);
                }
                if (!$c['ok'] && $coche) {
                    $en_trop[] = lettre($i);
                }
            }
            if ($manques !== []) {
                Terminal::ligne('    oublié : ' . implode(', ', $manques));
            }
            if ($en_trop !== []) {
                Terminal::ligne('    en trop : ' . implode(', ', $en_trop));
            }
        }
    }
    if ($statut !== 'ok') {
        Terminal::ligne('  Bonne réponse : ' . Terminal::style(bonneReponse($q), 'vert'));
    }
    Terminal::ligne('  ' . Terminal::symbole('fleche') . ' ' . $q['why']);
    Terminal::ligne(Terminal::style('  À revoir : ' . conseilRevision($q['ch']), 'pale'));
}

// ───────────────────────────── Bilan ─────────────────────────────

/** Remplissage à droite compatible UTF-8 (sprintf compte des octets). */
function padDroite(string $texte, int $largeur): string
{
    return $texte . str_repeat(' ', max(0, $largeur - mb_strlen($texte)));
}

function barre(int $ok, int $total, int $largeur = 20): string
{
    $rempli = $total === 0 ? 0 : (int) round($largeur * $ok / $total);

    return str_repeat(Terminal::symbole('plein'), $rempli) . str_repeat(Terminal::symbole('vide'), $largeur - $rempli);
}

function bilan(array $resultats, bool $examen, int $graine, int $numSession): void
{
    $repondues = array_values(array_filter($resultats, static fn (array $r): bool => $r['statut'] !== 'quitter'));
    $total = count($repondues);
    $ok = count(array_filter($repondues, static fn (array $r): bool => $r['statut'] === 'ok'));
    Terminal::ligne();
    Terminal::regle('Résultats');

    if ($examen) {
        Terminal::ligne(Terminal::style('Correction :', 'gras'));
        foreach ($repondues as $k => $r) {
            $q = $r['question'];
            $marque = $r['statut'] === 'ok' ? Terminal::style(Terminal::symbole('ok'), 'vert') : Terminal::style(Terminal::symbole('ko'), 'rouge');
            $texte = implode(' ', array_column($q['q'], 't'));
            Terminal::ligne(sprintf(' %s %2d. [ch. %s] %s', $marque, $k + 1, $q['ch'], mb_strimwidth($texte, 0, 70, '…')));
            if ($r['statut'] !== 'ok') {
                Terminal::ligne('        bonne réponse : ' . Terminal::style(bonneReponse($q), 'vert'));
                Terminal::ligne('        ' . Terminal::symbole('fleche') . ' ' . $q['why']);
            }
        }
        Terminal::ligne();
    }

    $pct = $total === 0 ? 0 : (int) round(100 * $ok / $total);
    $note = $total === 0 ? 0.0 : round(20 * $ok / $total, 1);
    $couleur = $pct >= 80 ? 'vert' : ($pct >= 50 ? 'jaune' : 'rouge');
    Terminal::ligne(Terminal::style(sprintf('Score : %d / %d  (%d %%)  —  %s / 20', $ok, $total, $pct, number_format($note, 1, ',', '')), 'gras', $couleur));
    $mots = $pct >= 90 ? 'Excellent, tu maîtrises !' : ($pct >= 70 ? 'Très bien, continue !' : ($pct >= 50 ? 'Correct, mais il reste des zones à consolider.' : 'Pas de panique : relis les chapitres signalés puis relance une session.'));
    Terminal::ligne($mots);
    $statuts = array_column($repondues, 'statut');
    $horsDelai = count(array_filter($statuts, static fn (string $s): bool => $s === 'temps'));
    $durees = array_column($repondues, 'duree');
    Terminal::ligne(sprintf(
        'Meilleure série : %d bonne(s) réponse(s) d\'affilée%s%s',
        meilleureSerie($statuts),
        $horsDelai > 0 ? " · $horsDelai hors délai" : '',
        $durees === [] ? '' : ' · temps moyen : ' . number_format(array_sum($durees) / count($durees), 1, ',', '') . ' s'
    ));

    // Détail par chapitre
    $parChap = [];
    foreach ($repondues as $r) {
        $c = $r['question']['ch'];
        $parChap[$c] ??= [0, 0];
        $parChap[$c][1]++;
        if ($r['statut'] === 'ok') {
            $parChap[$c][0]++;
        }
    }
    ksort($parChap);
    Terminal::ligne();
    Terminal::ligne(Terminal::style('Par chapitre :', 'gras'));
    foreach ($parChap as $c => [$o, $t]) {
        $col = $o === $t ? 'vert' : ($o === 0 ? 'rouge' : 'jaune');
        Terminal::ligne(sprintf('  %s %s  %d/%d  %s', $c, Terminal::style(barre($o, $t, 10), $col), $o, $t, TITRES[$c] ?? ''));
    }

    $faibles = array_keys(array_filter($parChap, static fn (array $v): bool => $v[0] < $v[1]));
    if ($faibles !== []) {
        Terminal::ligne();
        Terminal::ligne(Terminal::style('À relire : ', 'gras') . implode(', ', array_map(static fn (string $c): string => "ch. $c", $faibles)));
        Terminal::ligne(Terminal::style('Astuce : php quiz.php --revision  rejoue uniquement tes erreurs.', 'pale'));
    }

    $rates = array_filter($repondues, static fn (array $r): bool => $r['statut'] !== 'ok');
    if (!$examen && $rates !== []) {
        Terminal::ligne();
        Terminal::ligne(Terminal::style('Récap des erreurs :', 'gras'));
        foreach ($rates as $r) {
            $q = $r['question'];
            $texte = implode(' ', array_column($q['q'], 't'));
            Terminal::ligne('  ' . Terminal::symbole('puce') . ' ' . mb_strimwidth($texte, 0, 72, '…'));
            Terminal::ligne('    ' . Terminal::symbole('fleche') . ' ' . Terminal::style(bonneReponse($q), 'vert'));
        }
    }
    Terminal::ligne();
    Terminal::ligne(Terminal::style("Session n°$numSession · graine $graine (rejouable avec --graine=$graine)", 'pale'));
}

// ───────────────────────────── Statistiques ─────────────────────────────

function afficherStats(array $histo, array $statiques): void
{
    Terminal::regle('Statistiques');
    $sessions = $histo['sessions'];
    if ($sessions === []) {
        Terminal::ligne('Aucune session enregistrée pour le moment. Lance : php quiz.php');

        return;
    }
    $ok = array_sum(array_column($sessions, 'score'));
    $tot = array_sum(array_column($sessions, 'total'));
    Terminal::ligne(sprintf('Sessions : %d · réponses : %d · réussite globale : %d %%', count($sessions), $tot, $tot ? (int) round(100 * $ok / $tot) : 0));

    $banque = [];
    foreach ($statiques as $q) {
        $banque[$q['ch']] = ($banque[$q['ch']] ?? 0) + 1;
    }
    $vus = [];
    $reussi = [];
    $essais = [];
    foreach ($statiques as $q) {
        $rec = $histo['questions'][$q['id']] ?? null;
        if ($rec !== null) {
            $vus[$q['ch']] = ($vus[$q['ch']] ?? 0) + 1;
            $reussi[$q['ch']] = ($reussi[$q['ch']] ?? 0) + (int) $rec['ok'];
            $essais[$q['ch']] = ($essais[$q['ch']] ?? 0) + (int) $rec['ok'] + (int) $rec['ko'];
        }
    }
    Terminal::ligne();
    Terminal::ligne(Terminal::style('Par chapitre (questions vues / banque · taux de réussite) :', 'gras'));
    ksort($banque);
    foreach ($banque as $c => $nb) {
        $taux = ($essais[$c] ?? 0) > 0 ? (int) round(100 * $reussi[$c] / $essais[$c]) : null;
        $col = $taux === null ? 'gris' : ($taux >= 80 ? 'vert' : ($taux >= 50 ? 'jaune' : 'rouge'));
        Terminal::ligne(sprintf(
            '  %s %s %2d/%s %s %s',
            $c,
            Terminal::style(barre($vus[$c] ?? 0, $nb, 10), $col),
            $vus[$c] ?? 0,
            str_pad((string) $nb, 2),
            padDroite(TITRES[$c] ?? '', 34),
            $taux === null ? Terminal::style('—', 'gris') : Terminal::style($taux . ' %', $col)
        ));
    }

    $pires = [];
    foreach ($statiques as $q) {
        $rec = $histo['questions'][$q['id']] ?? null;
        if ($rec !== null && (int) $rec['ko'] > 0) {
            $pires[] = [(int) $rec['ko'], $q];
        }
    }
    usort($pires, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
    if ($pires !== []) {
        Terminal::ligne();
        Terminal::ligne(Terminal::style('Questions les plus ratées :', 'gras'));
        foreach (array_slice($pires, 0, 5) as [$ko, $q]) {
            Terminal::ligne(sprintf('  %dx  [ch. %s] %s', $ko, $q['ch'], mb_strimwidth(implode(' ', array_column($q['q'], 't')), 0, 64, '…')));
        }
    }
    Terminal::ligne();
    Terminal::ligne(Terminal::style('Dernières sessions :', 'gras'));
    foreach (array_slice($sessions, -5) as $s) {
        Terminal::ligne(sprintf('  n°%-3d %s  %d/%d', $s['n'], $s['date'], $s['score'], $s['total']));
    }
}

// ───────────────────────────── Vérification de la banque ─────────────────────────────

function verifierBanque(array $statiques, array $erreurs, array $generateurs): int
{
    Terminal::regle('Vérification de la banque');
    foreach ($erreurs as $e) {
        Terminal::ligne(Terminal::style('  ERREUR  ', 'rouge', 'gras') . $e);
    }
    $par = [];
    $cours = 0;
    $types = ['qcm' => 0, 'multi' => 0, 'saisie' => 0];
    foreach ($statiques as $q) {
        $par[$q['ch']] = ($par[$q['ch']] ?? 0) + 1;
        $cours += $q['cours'] ? 1 : 0;
        $types[$q['type']]++;
    }
    ksort($par);
    foreach (TITRES as $c => $titre) {
        $nb = $par[$c] ?? 0;
        $marque = $nb < 6 ? Terminal::style(' (peu de questions)', 'jaune') : '';
        Terminal::ligne(sprintf('  %s  %3d  %s%s', $c, $nb, $titre, $marque));
    }
    Terminal::ligne(sprintf('Total : %d questions statiques (%d QCM, %d multiples, %d saisies libres), dont %d tirées des cours PDF.', count($statiques), $types['qcm'], $types['multi'], $types['saisie'], $cours));

    // Indicateur de qualité : la bonne réponse ne doit pas se repérer à sa longueur.
    $qcm = array_filter($statiques, static fn (array $q): bool => $q['type'] === 'qcm' && count($q['choix']) >= 4);
    $nette = 0;
    foreach ($qcm as $q) {
        $bonne = 0;
        $maxMauvaise = 0;
        foreach ($q['choix'] as $c) {
            $l = mb_strlen($c['t']);
            if ($c['ok']) {
                $bonne = $l;
            } else {
                $maxMauvaise = max($maxMauvaise, $l);
            }
        }
        $nette += $bonne > $maxMauvaise * 1.12 + 2 ? 1 : 0;
    }
    if ($qcm !== []) {
        $part = (int) round(100 * $nette / count($qcm));
        Terminal::ligne(sprintf(
            'Équilibre des propositions : la bonne réponse est nettement la plus longue dans %d %% des QCM %s',
            $part,
            $part <= 25 ? Terminal::style('(bien équilibré)', 'vert') : Terminal::style('(à rééquilibrer : allonge les mauvaises réponses)', 'jaune')
        ));
    }

    $problemes = count($erreurs);
    Terminal::ligne(sprintf('Générateurs : %d (test de 300 tirages chacun)…', count($generateurs)));
    foreach ($generateurs as $nom => [$ch, $fn]) {
        for ($i = 0; $i < 300; $i++) {
            initialiserAlea(1000 + $i);
            try {
                $q = $fn();
            } catch (Throwable $e) {
                Terminal::ligne(Terminal::style("  ERREUR générateur $nom : " . $e->getMessage(), 'rouge'));
                $problemes++;
                break;
            }
            if (!isset(TITRES[$ch])) {
                Terminal::ligne(Terminal::style("  ERREUR générateur $nom : chapitre $ch inconnu", 'rouge'));
                $problemes++;
                break;
            }
            if ($q['type'] === 'saisie') {
                if ($q['acceptees'] === [] || !reponseLibreCorrecte($q['acceptees'][0], $q['acceptees'])) {
                    Terminal::ligne(Terminal::style("  ERREUR générateur $nom : réponse attendue non reconnue", 'rouge'));
                    $problemes++;
                    break;
                }
            } else {
                $textes = array_column($q['choix'], 't');
                $bonnes = count(array_filter($q['choix'], static fn (array $c): bool => $c['ok']));
                if (count($textes) !== count(array_unique($textes)) || $bonnes < 1 || count($textes) < 2) {
                    Terminal::ligne(Terminal::style("  ERREUR générateur $nom : propositions invalides (" . implode(' / ', $textes) . ')', 'rouge'));
                    $problemes++;
                    break;
                }
            }
        }
    }
    Terminal::ligne($problemes === 0 ? Terminal::style('Tout est cohérent.', 'vert', 'gras') : Terminal::style("$problemes problème(s) à corriger.", 'rouge', 'gras'));

    return $problemes === 0 ? 0 : 1;
}

// ───────────────────────────── Options ─────────────────────────────

function aide(): void
{
    $defaut = NOMBRE_QUESTIONS_DEFAUT;
    echo <<<TXT
    Quiz BTS SIO SLAM — PHP, bases de données, MVC, API REST, JWT et JavaScript

    Usage : php quiz.php [options]

      (sans option)        menu interactif
      --n={$defaut}               nombre de questions (défaut {$defaut})
      --chap=03,12-15      limiter à certains chapitres (listes et intervalles, 00 à 27)
      --bloc=2             1 bases · 2 objet · 3 web · 4 données · 5 architecture et API · 6 JavaScript
      --cours              uniquement les questions tirées des supports de référence (étiquette [cours])
      --examen             pas de correction pendant la session, bilan à la fin
      --serie              enchaîne les questions jusqu'à la première erreur (50 au plus)
      --chrono=30          secondes accordées par question (réponse hors délai = fausse)
      --revision           rejoue uniquement les questions ratées auparavant
      --stats              affiche tes statistiques
      --reset              efface l'historique (demande confirmation)
      --graine=123456      rejoue exactement une session précédente
      --verifier           contrôle la banque de questions et les générateurs
      --sans-couleur       désactive les couleurs
      --ascii              n'utilise que des caractères ASCII (✔ → OK, etc.)
      --aide               cette aide

    Pendant une question : A/B/C… pour répondre, s pour passer, q pour quitter.
    Pour une réponse libre : tape la réponse, /s pour passer, /q pour quitter.

    TXT;
}

/** @return list<string> */
function analyserChapitres(string $spec): array
{
    $liste = [];
    foreach (explode(',', $spec) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $part, $m) === 1) {
            for ($i = (int) $m[1]; $i <= (int) $m[2]; $i++) {
                $liste[] = sprintf('%02d', $i);
            }
        } elseif (ctype_digit($part)) {
            $liste[] = sprintf('%02d', (int) $part);
        }
    }

    return array_values(array_unique(array_filter($liste, static fn (string $c): bool => isset(TITRES[$c]))));
}

function analyserArguments(array $argv): array
{
    $o = ['n' => null, 'chap' => null, 'bloc' => null, 'cours' => false, 'examen' => false, 'revision' => false,
        'serie' => false, 'chrono' => null, 'stats' => false, 'reset' => false, 'graine' => null, 'verifier' => false,
        'sansCouleur' => false, 'ascii' => false, 'aide' => false, 'args' => count($argv) > 1];
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--(n|chap|bloc|graine|chrono)=(.+)$/', $a, $m) === 1) {
            $o[$m[1]] = $m[2];
        } else {
            $cle = match ($a) {
                '--cours' => 'cours', '--examen' => 'examen', '--revision' => 'revision', '--serie' => 'serie', '--stats' => 'stats',
                '--reset' => 'reset', '--verifier' => 'verifier', '--sans-couleur' => 'sansCouleur',
                '--ascii' => 'ascii', '--aide', '--help', '-h' => 'aide',
                default => null,
            };
            if ($cle === null) {
                fwrite(STDERR, "Option inconnue : $a (voir --aide)\n");
                exit(2);
            }
            $o[$cle] = true;
        }
    }

    return $o;
}

// ───────────────────────────── Programme principal ─────────────────────────────

function menu(array &$o, array $histo, array $statiques): bool
{
    $aRevoir = nombreARevoir($statiques, $histo);
    Terminal::ligne();
    Terminal::ligne(Terminal::style('  QUIZ BTS SIO SLAM', 'gras', 'cyan') . Terminal::style(sprintf('  ·  %d questions + générateurs  ·  %d session(s) déjà jouée(s)', count($statiques), count($histo['sessions'])), 'pale'));
    Terminal::ligne();
    $record = meilleureSerieHistorique($histo);
    Terminal::ligne('   1) Session aléatoire sur tout le programme');
    Terminal::ligne('   2) Choisir les chapitres');
    Terminal::ligne('   3) Mode examen (correction à la fin)');
    Terminal::ligne('   4) Mode série : jusqu\'à la première erreur' . ($record > 0 ? " (record : $record)" : ''));
    Terminal::ligne("   5) Réviser mes erreurs ($aRevoir à revoir)");
    Terminal::ligne('   6) Uniquement les questions tirées des supports de référence');
    Terminal::ligne('   7) Mes statistiques');
    Terminal::ligne('   q) Quitter');
    Terminal::ligne();
    $choix = Terminal::lire('Ton choix [1] :');
    if ($choix === null || mb_strtolower($choix) === 'q') {
        return false;
    }
    switch ($choix === '' ? '1' : $choix) {
        case '2':
            Terminal::ligne(Terminal::style('  Blocs : b1 bases (00-07) · b2 objet (08-11) · b3 web (12-15) · b4 données (16-19) · b5 architecture et API (20-23) · b6 JavaScript (24-27)', 'pale'));
            $spec = Terminal::lire('Chapitres (ex : 03,12-15) ou bloc (ex : b2) :');
            if ($spec !== null && preg_match('/^b([1-6])$/i', $spec, $m) === 1) {
                $o['bloc'] = $m[1];
            } elseif ($spec !== null && $spec !== '') {
                $o['chap'] = $spec;
            }
            break;
        case '3':
            $o['examen'] = true;
            break;
        case '4':
            $o['serie'] = true;
            break;
        case '5':
            $o['revision'] = true;
            break;
        case '6':
            $o['cours'] = true;
            break;
        case '7':
            $o['stats'] = true;

            return true;
    }
    if (!$o['serie']) {
        $n = Terminal::lire('Combien de questions ? [' . NOMBRE_QUESTIONS_DEFAUT . '] :');
        if ($n !== null && ctype_digit($n) && (int) $n > 0) {
            $o['n'] = $n;
        }
    }
    $chrono = Terminal::lire('Minuteur en secondes par question (Entrée = sans minuteur) :');
    if ($chrono !== null && ctype_digit($chrono) && (int) $chrono > 0) {
        $o['chrono'] = $chrono;
    }

    return true;
}

$o = analyserArguments($argv);
if ($o['aide']) {
    aide();
    exit(0);
}
Terminal::initialiser($o['sansCouleur'], $o['ascii']);

[$statiques, $erreursBanque] = chargerBanque();
$generateurs = chargerGenerateurs();

if ($o['verifier']) {
    exit(verifierBanque($statiques, $erreursBanque, $generateurs));
}
if ($erreursBanque !== []) {
    fwrite(STDERR, "Banque de questions invalide (php quiz.php --verifier) :\n  " . implode("\n  ", $erreursBanque) . "\n");
    exit(1);
}

$histo = chargerHistorique();

if ($o['reset']) {
    $rep = Terminal::lire('Effacer tout l\'historique (sessions et statistiques) ? [o/N] :');
    if ($rep !== null && in_array(mb_strtolower($rep), ['o', 'oui', 'y', 'yes'], true)) {
        @unlink(FICHIER_HISTORIQUE);
        Terminal::ligne('Historique effacé.');
    } else {
        Terminal::ligne('Annulé.');
    }
    exit(0);
}

if (!$o['args'] && Terminal::interactif()) {
    if (!menu($o, $histo, $statiques)) {
        exit(0);
    }
}
if ($o['stats']) {
    afficherStats($histo, $statiques);
    exit(0);
}

// Filtres (chapitres, bloc, cours)
$chapitres = null;
if ($o['chap'] !== null) {
    $chapitres = analyserChapitres((string) $o['chap']);
}
if ($o['bloc'] !== null && isset(BLOCS[(int) $o['bloc']])) {
    $chapitres = chapitresDuBloc((int) $o['bloc']);
}
if ($chapitres !== null && $chapitres === []) {
    fwrite(STDERR, "Aucun chapitre valide dans --chap (attendu : 00 à 27, ex : 03,12-15).\n");
    exit(2);
}
[$statiques, $generateurs] = filtrerBanque($statiques, $generateurs, $chapitres, (bool) $o['cours']);

$serie = (bool) $o['serie'] && !$o['revision'];
$chrono = $o['chrono'] !== null && ctype_digit((string) $o['chrono']) ? min(600, (int) $o['chrono']) : 0;
$n = $o['n'] !== null && ctype_digit((string) $o['n']) ? max(1, (int) $o['n']) : ($serie ? SERIE_MAX : NOMBRE_QUESTIONS_DEFAUT);
$graine = $o['graine'] !== null && ctype_digit((string) $o['graine']) ? (int) $o['graine'] : random_int(1, 2147483646);
initialiserAlea($graine);
$numSession = count($histo['sessions']) + 1;

$selection = preparerSession($statiques, $generateurs, $n, $histo, $numSession, $o);
if ($selection === []) {
    Terminal::ligne($o['revision']
        ? Terminal::style('Aucune erreur à réviser pour le moment : bravo ! Lance une session normale.', 'vert')
        : 'Aucune question ne correspond à ces filtres.');
    exit(0);
}

Terminal::ligne();
Terminal::ligne(Terminal::style(sprintf(
    'Session n°%d · %s · %s%s',
    $numSession,
    $serie ? 'série : jusqu\'à la première erreur' : count($selection) . ' question(s)',
    $o['examen'] ? 'mode examen' : ($o['revision'] ? 'révision des erreurs' : 'correction immédiate'),
    $chrono > 0 ? " · $chrono s par question" : ''
), 'gras', 'cyan'));

$resultats = [];
foreach ($selection as $i => $q) {
    $r = poser($q, $i + 1, $serie ? 0 : count($selection), (bool) $o['examen'], $chrono);
    $resultats[] = $r;
    if ($r['statut'] === 'quitter' || ($serie && $r['statut'] !== 'ok')) {
        break;
    }
}

bilan($resultats, (bool) $o['examen'], $graine, $numSession);

// Enregistrement de l'historique (sous verrou, via le moteur partagé)
$repondues = array_values(array_filter($resultats, static fn (array $r): bool => $r['statut'] !== 'quitter'));
$infos = [
    'mode' => $serie ? 'serie' : ($o['examen'] ? 'examen' : ($o['revision'] ? 'revision' : 'normal')),
    'chrono' => $chrono,
    'duree_moyenne' => $repondues === [] ? null : round(array_sum(array_column($repondues, 'duree')) / count($repondues), 1),
];
modifierHistorique(static fn (array $h): array => appliquerResultats($h, $resultats, $graine, $infos));
