<?php

declare(strict_types=1);

/**
 * Tests de la logique du site (partie.php, stats.php, vues.php + moteur partagé).
 * Aucun serveur, aucune session, aucun fichier écrit : ce sont des fonctions pures.
 *
 *   php site/tests/partie_test.php     (depuis la racine du dépôt)
 */

require dirname(__DIR__, 2) . '/moteur.php';
require dirname(__DIR__) . '/src/http.php'; // e(), pour les assistants d'affichage
require dirname(__DIR__) . '/src/vues.php';
require dirname(__DIR__) . '/src/partie.php';
require dirname(__DIR__) . '/src/stats.php';
require dirname(__DIR__) . '/src/fiches.php';

$total = 0;
$echecs = 0;

function verifier(string $nom, mixed $obtenu, mixed $attendu): void
{
    global $total, $echecs;
    $total++;
    if ($obtenu === $attendu) {
        echo "[OK]    $nom\n";

        return;
    }
    $echecs++;
    echo "[ECHEC] $nom\n        attendu : " . var_export($attendu, true) . "\n        obtenu  : " . var_export($obtenu, true) . "\n";
}

[$statiques, $erreursBanque] = chargerBanque();
verifier('la banque de questions est valide', $erreursBanque, []);
$generateurs = chargerGenerateurs();
$histoVide = ['version' => 1, 'sessions' => [], 'questions' => []];
$tous = array_map('strval', array_keys(TITRES));

$options = static fn (array $surcharge = []): array => $surcharge + [
    'mode' => 'normal', 'n' => 10, 'chrono' => 0, 'chapitres' => $tous, 'cours' => false, 'graine' => 4242, 'graine_texte' => '4242',
];

// ───── validerOptionsPartie ─────
$r = validerOptionsPartie(['mode' => 'examen', 'n' => '20', 'chrono' => '30', 'chapitres' => ['03', '10'], 'cours' => '1', 'graine' => ' 123 ']);
verifier('options valides : aucune erreur', $r['erreurs'], []);
verifier('options valides : valeurs normalisées', [$r['valeurs']['mode'], $r['valeurs']['n'], $r['valeurs']['chrono'], $r['valeurs']['chapitres'], $r['valeurs']['cours'], $r['valeurs']['graine']], ['examen', 20, 30, ['03', '10'], true, 123]);
verifier('minuteur absent : pas de minuteur', validerOptionsPartie(['n' => '15', 'chapitres' => ['03']])['valeurs']['chrono'], 0);
verifier('minuteur hors liste refusé', isset(validerOptionsPartie(['n' => '15', 'chrono' => '7', 'chapitres' => ['03']])['erreurs']['chrono']), true);
verifier('minuteur sous forme de tableau refusé sans TypeError', isset(validerOptionsPartie(['n' => '15', 'chrono' => ['30'], 'chapitres' => ['03']])['erreurs']['chrono']), true);
verifier('mode série accepté', validerOptionsPartie(['mode' => 'serie', 'n' => '15', 'chapitres' => ['03']])['erreurs'], []);
verifier('mode inconnu refusé', isset(validerOptionsPartie(['mode' => 'triche', 'n' => '15', 'chapitres' => ['03']])['erreurs']['mode']), true);
verifier('nombre de questions hors liste refusé', isset(validerOptionsPartie(['n' => '999', 'chapitres' => ['03']])['erreurs']['n']), true);
verifier('chapitre inconnu refusé', isset(validerOptionsPartie(['n' => '15', 'chapitres' => ['03', '99']])['erreurs']['chapitres']), true);
verifier('aucun chapitre refusé', isset(validerOptionsPartie(['n' => '15'])['erreurs']['chapitres']), true);
verifier('chapitres en chaîne (forme falsifiée) refusés', isset(validerOptionsPartie(['n' => '15', 'chapitres' => '03'])['erreurs']['chapitres']), true);
verifier('graine non numérique refusée', isset(validerOptionsPartie(['n' => '15', 'chapitres' => ['03'], 'graine' => '12abc'])['erreurs']['graine']), true);
verifier('graine trop grande refusée', isset(validerOptionsPartie(['n' => '15', 'chapitres' => ['03'], 'graine' => '99999999999'])['erreurs']['graine']), true);
verifier('tableau à la place de n : refusé sans TypeError', isset(validerOptionsPartie(['n' => ['15'], 'chapitres' => ['03']])['erreurs']['n']), true);

// ───── creerPartie ─────
$a = creerPartie($options(), $statiques, $generateurs, $histoVide);
$b = creerPartie($options(), $statiques, $generateurs, $histoVide);
verifier('une partie contient le nombre de questions demandé', count($a['questions']), 10);
verifier('même graine, même historique : mêmes questions (rejouable)', array_column($a['questions'], 'id'), array_column($b['questions'], 'id'));
$c = creerPartie($options(['graine' => 4243]), $statiques, $generateurs, $histoVide);
verifier('graine différente : questions différentes', array_column($a['questions'], 'id') !== array_column($c['questions'], 'id'), true);
verifier('partie neuve : aucune réponse, non terminée', [positionPartie($a), $a['terminee'], $a['enregistree']], [0, false, false]);

$filtre = creerPartie($options(['chapitres' => ['09', '10'], 'n' => 15]), $statiques, $generateurs, $histoVide);
verifier('filtre de chapitres respecté', array_values(array_unique(array_column($filtre['questions'], 'ch'))) === array_values(array_intersect(array_unique(array_column($filtre['questions'], 'ch')), ['09', '10'])), true);
verifier('révision sans erreur passée : aucune partie', creerPartie($options(['mode' => 'revision']), $statiques, $generateurs, $histoVide), null);
$cours = creerPartie($options(['cours' => true, 'n' => 20]), $statiques, $generateurs, $histoVide);
verifier('option « cours » : uniquement des questions des cours', count(array_filter($cours['questions'], static fn (array $q): bool => !$q['cours'])), 0);

// ───── validerReponse ─────
$qcm = array_values(array_filter($a['questions'], static fn (array $q): bool => $q['type'] === 'qcm'))[0];
$multi = array_values(array_filter($statiques, static fn (array $q): bool => $q['type'] === 'multi'))[0];
$saisie = array_values(array_filter($statiques, static fn (array $q): bool => $q['type'] === 'saisie'))[0];

verifier('qcm : un indice valide', validerReponse($qcm, ['choix' => '1'])['reponse'], ['choix' => [1]]);
verifier('qcm : indice hors limites refusé', validerReponse($qcm, ['choix' => '9'])['reponse'], null);
verifier('qcm : deux réponses refusées', validerReponse($qcm, ['choix' => ['0', '1']])['reponse'], null);
verifier('qcm : aucune réponse refusée', validerReponse($qcm, [])['reponse'], null);
verifier('qcm : valeur non numérique refusée', validerReponse($qcm, ['choix' => 'abc'])['reponse'], null);
verifier('qcm : valeur négative refusée', validerReponse($qcm, ['choix' => '-1'])['reponse'], null);
verifier('multi : plusieurs indices acceptés, triés', validerReponse($multi, ['choix' => ['2', '0']])['reponse'], ['choix' => [0, 2]]);
verifier('saisie : texte nettoyé', validerReponse($saisie, ['texte' => '  1024 '])['reponse'], ['texte' => '1024']);
verifier('saisie : tableau à la place du texte refusé', validerReponse($saisie, ['texte' => ['x']])['reponse'], null);
verifier('saisie : texte vide refusé', validerReponse($saisie, ['texte' => '   '])['reponse'], null);
verifier('saisie : texte trop long refusé', validerReponse($saisie, ['texte' => str_repeat('a', 201)])['reponse'], null);
verifier('« passer » toujours accepté', validerReponse($saisie, ['action' => 'passer'])['reponse'], ['passer' => true]);
verifier('action inconnue refusée', validerReponse($saisie, ['action' => 'supprimer', 'texte' => 'x'])['reponse'], null);

// ───── repondre ─────
$partie = creerPartie($options(['n' => 8, 'graine' => 77]), $statiques, $generateurs, $histoVide);
$verdicts = [];
foreach ($partie['questions'] as $i => $q) {
    $courante = questionCourante($partie);
    verifier("question $i : la question courante est la bonne", $courante['id'], $q['id']);
    if ($i % 3 === 0) {
        $reponse = ['passer' => true];
        $attendu = 'passee';
    } elseif ($q['type'] === 'saisie') {
        $reponse = ['texte' => $i % 2 === 0 ? $q['acceptees'][0] : 'réponse fausse zzz'];
        $attendu = $i % 2 === 0 ? 'ok' : 'ko';
    } else {
        $bonnes = array_keys(array_filter($q['choix'], static fn (array $c): bool => $c['ok']));
        $fausses = array_keys(array_filter($q['choix'], static fn (array $c): bool => !$c['ok']));
        $juste = $i % 2 === 0;
        $reponse = ['choix' => $juste ? $bonnes : [$fausses[0]]];
        $attendu = $juste ? 'ok' : 'ko';
    }
    $partie = repondre($partie, $reponse);
    $verdicts[] = $partie['resultats'][$i]['statut'];
    verifier("question $i : verdict attendu ($attendu)", $partie['resultats'][$i]['statut'], $attendu);
}
verifier('toutes les questions traitées : partie terminée', [$partie['terminee'], questionCourante($partie)], [true, null]);
verifier('répondre à une partie terminée ne change rien', count(repondre($partie, ['passer' => true])['resultats']), 8);

$bilan = bilanPartie($partie);
verifier('bilan : cohérence ok + ko + passées + hors délai = total', $bilan['ok'] + $bilan['ko'] + $bilan['passees'] + $bilan['temps'], $bilan['total']);
verifier('bilan : meilleure série cohérente avec les verdicts', $bilan['serie'], meilleureSerie($verdicts));
verifier('bilan : total = nombre de questions', $bilan['total'], 8);
verifier('bilan : pourcentage entre 0 et 100', $bilan['pourcentage'] >= 0 && $bilan['pourcentage'] <= 100, true);

// ───── abandon ─────
$abandon = creerPartie($options(['n' => 10, 'graine' => 5]), $statiques, $generateurs, $histoVide);
$abandon = repondre($abandon, ['passer' => true]);
$abandon = abandonnerPartie($abandon);
verifier('abandon : partie terminée avec une seule réponse', [$abandon['terminee'], positionPartie($abandon), bilanPartie($abandon)['non_traitees']], [true, 1, 9]);

// ───── historique partagé avec le terminal ─────
$histo = appliquerResultats($histoVide, resultatsPourHistorique($partie), (int) $partie['graine']);
verifier('historique : une session enregistrée', count($histo['sessions']), 1);
verifier('historique : score enregistré', $histo['sessions'][0]['score'], $bilan['ok']);
$ids = array_map(static fn (array $q): string => $q['id'], array_filter($partie['questions'], static fn (array $q): bool => !$q['gen']));
verifier('historique : les questions statiques sont mémorisées', count(array_intersect(array_keys($histo['questions']), $ids)), count($ids));

$suivante = creerPartie($options(['n' => 8, 'graine' => 78]), $statiques, $generateurs, $histo);
$communes = array_intersect(
    array_column(array_filter($partie['questions'], static fn (array $q): bool => !$q['gen']), 'id'),
    array_column(array_filter($suivante['questions'], static fn (array $q): bool => !$q['gen']), 'id')
);
verifier('la partie suivante évite (presque) toutes les questions de la précédente', count($communes) <= 1, true);

$aRevoir = nombreARevoir($statiques, $histo);
verifier('les questions ratées sont comptées pour la révision', $aRevoir, count(array_filter($partie['resultats'], static fn (array $r, int $k): bool => $r['statut'] !== 'ok' && !$partie['questions'][$k]['gen'], ARRAY_FILTER_USE_BOTH)));

// ───── chapitres, blocs, conseils de révision ─────
verifier('28 chapitres, de 00 à 27', $tous, array_map(static fn (int $i): string => sprintf('%02d', $i), range(0, 27)));
verifier('les blocs couvrent chaque chapitre une seule fois', array_merge(...array_map('chapitresDuBloc', array_keys(BLOCS))), $tous);
$parChapitre = array_count_values(array_column($statiques, 'ch'));
verifier('chaque chapitre a au moins 12 questions statiques', array_values(array_filter($tous, static fn (string $ch): bool => ($parChapitre[$ch] ?? 0) < 12)), []);
verifier('chaque générateur vise un chapitre existant', count(array_filter($generateurs, static fn (array $g): bool => !isset(TITRES[$g[0]]))), 0);
verifier('conseil de révision sans cours associé', conseilRevision('03'), 'chapitre 03 — Boucles');
verifier('conseil de révision avec le cours associé', conseilRevision('24'), 'chapitre 24 — JavaScript : les fondamentaux — cours « JavaScript Vanilla : les fondamentaux »');

// ───── la banque porte sur le langage, pas sur le contenu des exercices de TP ─────
$motsInterdits = ['Musée', 'CinéClub', 'Médiathèque', 'Medoo', 'appreciationFilm', 'suiteCollatz', 'calculerCleIsbn10', 'lireFiltres',
    'filtrerFilms', 'ajouterFavori', 'EmpruntService', 'pharmacie', 'matrice de formes', 'calculatrice', 'factorielleRecursive', 'sommeProfonde'];
$trouves = [];
foreach ($statiques as $q) {
    foreach ($motsInterdits as $mot) {
        if (mb_stripos(texteCompletQuestion($q), $mot) !== false) {
            $trouves[] = $q['source'] . ' (' . $mot . ')';
        }
    }
}
verifier('aucune question ne cite un projet ou une fonction des exercices de TP', $trouves, []);

// ───── accueil : thèmes, réglages par défaut et réglages mémorisés ─────
verifier('le bloc JavaScript = chapitres 24 à 27', chapitresDuBloc(6), ['24', '25', '26', '27']);
verifier('chaque bloc a son symbole sur l\'accueil', array_keys(ICONES_BLOCS), array_keys(BLOCS));
$defaut = reglagesParDefaut();
verifier('réglages par défaut valides (tous les chapitres, entraînement)', [validerOptionsPartie(['mode' => $defaut['mode'], 'n' => (string) $defaut['n'], 'chapitres' => $defaut['chapitres']])['erreurs'], $defaut['chapitres']], [[], $tous]);
verifier('partie rapide : réglages acceptés par la validation', validerOptionsPartie(['mode' => PARTIE_RAPIDE['mode'], 'n' => (string) PARTIE_RAPIDE['n'], 'chrono' => (string) PARTIE_RAPIDE['chrono'], 'chapitres' => $tous])['erreurs'], []);
$memorisee = derniersReglages(['options' => ['mode' => 'serie', 'n' => 20, 'chrono' => 30, 'chapitres' => ['08', '09'], 'cours' => true, 'graine' => 123, 'graine_texte' => '123']]);
verifier('réglages mémorisés : repris pour la partie suivante, sans la graine', [$memorisee['mode'], $memorisee['n'], $memorisee['chrono'], $memorisee['chapitres'], $memorisee['cours'], $memorisee['graine']], ['serie', 20, 30, ['08', '09'], true, null]);
verifier('réglages mémorisés : ignorés s\'ils sont devenus invalides (ancienne version)', derniersReglages(['options' => ['mode' => 'normal', 'n' => 15, 'chapitres' => ['29']]]), null);
verifier('réglages mémorisés : aucune partie, aucun réglage', [derniersReglages(null), derniersReglages(['options' => 'x'])], [null, null]);
verifier('nombre de questions par défaut : 20', [NOMBRE_QUESTIONS_DEFAUT, $defaut['n'], validerOptionsPartie(['n' => '999', 'chapitres' => ['03']])['valeurs']['n']], [20, 20, 20]);
$rapide = validerOptionsPartie(['mode' => 'normal', 'n' => '10', 'chapitres' => $tous, 'rapide' => '1'])['valeurs'];
verifier('partie rapide : un préréglage, pas mémorisé comme réglage de l\'utilisateur', [$rapide['rapide'], derniersReglages(['options' => $rapide])], [true, null]);
verifier('thème : un seul bouton fait le tour des thèmes', [themeSuivant('auto'), themeSuivant('clair'), themeSuivant('sombre'), themeSuivant('inconnu')], ['clair', 'sombre', 'auto', 'auto']);
verifier('correction : étiquettes en texte, pas seulement en couleur', [
    str_contains(etiquetteChoix(true, true), 'Ta réponse'), str_contains(etiquetteChoix(true, false), 'Bonne réponse'),
    str_contains(etiquetteChoix(false, true), 'class="etiquette-choix ko"'), etiquetteChoix(false, false),
], [true, true, true, '']);
$js = creerPartie($options(['chapitres' => chapitresDuBloc(6), 'n' => 20, 'graine' => 9]), $statiques, $generateurs, $histoVide);
verifier('partie JavaScript : 20 questions des chapitres 24 à 27 seulement', [count($js['questions']), array_values(array_diff(array_unique(array_column($js['questions'], 'ch')), ['24', '25', '26', '27']))], [20, []]);
verifier('partie JavaScript : des questions générées en JavaScript aussi', count(array_filter($js['questions'], static fn (array $q): bool => $q['gen'])) > 0, true);

// ───── minuteur (vérifié par le serveur) ─────
$chronoPartie = creerPartie($options(['n' => 5, 'chrono' => 30, 'graine' => 31]), $statiques, $generateurs, $histoVide);
verifier('minuteur : enregistré dans la partie', $chronoPartie['chrono'], 30);
verifier('minuteur : pas de départ avant l\'affichage', [questionAffichee($chronoPartie), tempsRestant($chronoPartie, 1000.0)], [false, 30.0]);
$chronoPartie = marquerAffichage($chronoPartie, 1000.0);
verifier('minuteur : départ noté à l\'affichage, puis décompte', [questionAffichee($chronoPartie), tempsRestant($chronoPartie, 1012.5)], [true, 17.5]);
verifier('minuteur : un rechargement ne remet pas le temps à zéro', tempsRestant(marquerAffichage($chronoPartie, 1020.0), 1020.0), 10.0);
verifier('minuteur : jamais négatif', tempsRestant($chronoPartie, 2000.0), 0.0);
$bonneReponse = static function (array $q): array {
    return $q['type'] === 'saisie'
        ? ['texte' => $q['acceptees'][0]]
        : ['choix' => array_keys(array_filter($q['choix'], static fn (array $c): bool => $c['ok']))];
};
$aTemps = repondre($chronoPartie, $bonneReponse(questionCourante($chronoPartie)), 1020.0);
verifier('minuteur : bonne réponse dans le délai = juste, durée mesurée', [$aTemps['resultats'][0]['statut'], $aTemps['resultats'][0]['duree']], ['ok', 20.0]);
$enRetard = repondre($chronoPartie, $bonneReponse(questionCourante($chronoPartie)), 1000.0 + 30 + TOLERANCE_CHRONO + 0.5);
verifier('minuteur : bonne réponse arrivée trop tard = temps écoulé', $enRetard['resultats'][0]['statut'], 'temps');
verifier('minuteur : la marge réseau est tolérée', repondre($chronoPartie, $bonneReponse(questionCourante($chronoPartie)), 1000.0 + 30 + TOLERANCE_CHRONO - 0.5)['resultats'][0]['statut'], 'ok');
verifier('minuteur : action « temps » envoyée à zéro', [validerReponse(questionCourante($chronoPartie), ['action' => 'temps'])['reponse'], repondre($chronoPartie, ['temps' => true], 1031.0)['resultats'][0]['statut']], [['temps' => true], 'temps']);
$sansChrono = marquerAffichage(creerPartie($options(['n' => 5, 'graine' => 32]), $statiques, $generateurs, $histoVide), 0.0);
verifier('minuteur : sans minuteur, une réponse lente reste juste', repondre($sansChrono, $bonneReponse(questionCourante($sansChrono)), 9999.0)['resultats'][0]['statut'], 'ok');
$bilanRetard = bilanPartie(abandonnerPartie($enRetard));
verifier('minuteur : le bilan compte les réponses hors délai', [$bilanRetard['temps'], $bilanRetard['ko'], $bilanRetard['ok']], [1, 0, 0]);
verifier('minuteur : temps moyen de réponse', dureeMoyenne([['duree' => 4.0], ['duree' => 6.5], ['duree' => null]]), 5.3);

// ───── mode série : jusqu'à la première erreur ─────
$serieOptions = $options(['mode' => 'serie', 'n' => 5, 'graine' => 64]);
$serie = creerPartie($serieOptions, $statiques, $generateurs, $histoVide);
verifier('série : le nombre de questions choisi est ignoré (jusqu\'à ' . SERIE_MAX . ')', count($serie['questions']), SERIE_MAX);
$serie = repondre($serie, $bonneReponse(questionCourante($serie)));
$serie = repondre($serie, $bonneReponse(questionCourante($serie)));
verifier('série : les bonnes réponses s\'enchaînent', [$serie['terminee'], serieEnCours($serie)], [false, 2]);
$serie = repondre($serie, ['passer' => true]);
verifier('série : la première réponse non juste termine la partie', [$serie['terminee'], positionPartie($serie), serieEnCours($serie)], [true, 3, 0]);
$bilanSerie = bilanPartie($serie);
verifier('série : bilan (série de 2, aucune question « non traitée »)', [$bilanSerie['serie'], $bilanSerie['non_traitees'], $bilanSerie['nouveau_record']], [2, 0, false]);
verifier('série : meilleure série d\'une suite de verdicts', [meilleureSerie(['ok', 'ok', 'ko', 'ok', 'ok', 'ok', 'temps']), meilleureSerie([]), meilleureSerie(['ko'])], [3, 0, 0]);
$histoSerie = appliquerResultats($histoVide, resultatsPourHistorique($serie), (int) $serie['graine'], infosHistorique($serie));
verifier('série : la session enregistre le mode, le minuteur et la série', [$histoSerie['sessions'][0]['mode'], $histoSerie['sessions'][0]['chrono'], $histoSerie['sessions'][0]['serie']], ['serie', 0, 2]);
verifier('série : record personnel lu dans l\'historique', [meilleureSerieHistorique($histoSerie), meilleureSerieHistorique($histoVide)], [2, 0]);
verifier('série : le record est mémorisé au lancement de la partie suivante', creerPartie($serieOptions, $statiques, $generateurs, $histoSerie)['record'], 2);

// ───── score en direct et prochaine étape conseillée ─────
$avecResultats = static fn (array $statuts, array $surcharge = []): array => $surcharge + [
    'mode' => 'normal', 'chrono' => 0, 'questions' => [],
    'resultats' => array_map(static fn (string $s): array => ['statut' => $s, 'choisies' => [], 'texte' => '', 'duree' => null], $statuts),
];
verifier('score en direct : justes, erreurs (fausses ou hors délai) et passées', scorePartie($avecResultats(['ok', 'ko', 'temps', 'passee', 'ok'])), ['ok' => 2, 'erreurs' => 2, 'passees' => 1]);
$bilanFictif = static fn (array $parChapitre, int $ko, int $total, int $ok): array => [
    'total' => $total, 'ok' => $ok, 'ko' => $ko, 'passees' => 0, 'temps' => 0,
    'pourcentage' => $total === 0 ? 0 : (int) round(100 * $ok / $total), 'par_chapitre' => $parChapitre,
];
verifier('chapitre le plus raté (à égalité de taux : le plus d\'erreurs)', chapitreLePlusFaible(['03' => ['ok' => 1, 'total' => 2], 12 => ['ok' => 2, 'total' => 4], '05' => ['ok' => 3, 'total' => 3]]), '12');
verifier('chapitre le plus raté : aucun si tout est juste', chapitreLePlusFaible(['03' => ['ok' => 2, 'total' => 2]]), null);
$etape = static fn (array $partie, array $bilan): string => prochaineEtape($partie, $bilan)['action'];
verifier('prochaine étape : beaucoup d\'erreurs → relire la fiche du chapitre le plus raté', [$etape($avecResultats([]), $bilanFictif(['07' => ['ok' => 0, 'total' => 3], '02' => ['ok' => 1, 'total' => 2]], 4, 5, 1)), prochaineEtape($avecResultats([]), $bilanFictif(['07' => ['ok' => 0, 'total' => 3], '02' => ['ok' => 1, 'total' => 2]], 4, 5, 1))['chapitre']], ['fiche', '07']);
verifier('prochaine étape : quelques erreurs → les refaire', $etape($avecResultats([]), $bilanFictif(['02' => ['ok' => 4, 'total' => 5]], 1, 5, 4)), 'erreurs');
verifier('prochaine étape : sans faute → ajouter le minuteur, puis tenter une série', [
    $etape($avecResultats([]), $bilanFictif(['02' => ['ok' => 5, 'total' => 5]], 0, 5, 5)),
    $etape($avecResultats([], ['chrono' => 30]), $bilanFictif(['02' => ['ok' => 5, 'total' => 5]], 0, 5, 5)),
    $etape($avecResultats([], ['mode' => 'serie']), $bilanFictif(['02' => ['ok' => 5, 'total' => 5]], 0, 5, 5)),
    $etape($avecResultats([]), $bilanFictif([], 0, 0, 0)),
], ['minuteur', 'serie', 'rejouer', 'rejouer']);

// ───── fiches de révision ─────
verifier('fiches : toutes les questions du chapitre demandé', count(filtrerFiches($statiques, '05', '')), $parChapitre['05']);
$motCle = filtrerFiches($statiques, null, 'password_hash');
verifier('fiches : la recherche trouve une notion dans tous les chapitres', count($motCle) > 0 && count(array_filter($motCle, static fn (array $q): bool => !str_contains(texteCompletQuestion($q), 'password_hash'))) === 0, true);
verifier('fiches : recherche sans accents ni majuscules', count(filtrerFiches($statiques, null, 'ECHAPPEMENT')) > 0, true);
verifier('fiches : tous les mots doivent être présents', count(filtrerFiches($statiques, null, 'closure zzzzz')), 0);
verifier('fiches : recherche vide = aucune fiche', filtrerFiches($statiques, null, '   '), []);
verifier('fiches : recherche validée (forme falsifiée, longueur maximale)', [validerRecherche(['x']), mb_strlen(validerRecherche(str_repeat('é', 200)))], ['', RECHERCHE_LONGUEUR_MAX]);
verifier('durée courte au format français', [formaterSecondes(8.44), formaterSecondes(65.0)], ['8,4 s', '1 min 05 s']);
verifier('temps moyen de l\'historique, pondéré par les réponses', tempsMoyenHistorique([['total' => 10, 'duree_moyenne' => 4.0], ['total' => 30, 'duree_moyenne' => 8.0], ['total' => 5]]), 7.0);

// ───── refaire ses erreurs ─────
$attendues = [];
foreach ($partie['resultats'] as $i => $r) {
    if ($r['statut'] !== 'ok') {
        $attendues[] = $partie['questions'][$i]['id'];
    }
}
$rattrapage = creerPartieErreurs($partie, $histo);
$idsRattrapage = array_column($rattrapage['questions'], 'id');
sort($idsRattrapage);
sort($attendues);
verifier('rattrapage : exactement les questions ratées ou passées', $idsRattrapage, $attendues);
verifier(
    'rattrapage : partie neuve, avec correction, liée à la partie d\'origine',
    [$rattrapage['mode'], $rattrapage['resultats'], $rattrapage['terminee'], $rattrapage['rattrapage'], $rattrapage['numSession']],
    ['normal', [], false, $partie['numSession'], count($histo['sessions']) + 1]
);
verifier('rattrapage : réglages d\'origine conservés (pour « Rejouer »)', $rattrapage['options'], $partie['options']);
$memesPropositions = true;
foreach ($rattrapage['questions'] as $q) {
    $origine = array_values(array_filter($partie['questions'], static fn (array $o): bool => $o['id'] === $q['id']))[0];
    $trier = static function (array $choix): array {
        usort($choix, static fn (array $a, array $b): int => strcmp($a['t'], $b['t']));

        return $choix;
    };
    $memesPropositions = $memesPropositions && $trier($q['choix']) === $trier($origine['choix']) && $q['acceptees'] === $origine['acceptees'];
}
verifier('rattrapage : mêmes propositions et mêmes bonnes réponses (seul l\'ordre change)', $memesPropositions, true);
$jouee = $rattrapage;
while (questionCourante($jouee) !== null) {
    $jouee = repondre($jouee, ['passer' => true]);
}
verifier('rattrapage : se joue jusqu\'au bout', [$jouee['terminee'], count($jouee['resultats'])], [true, count($attendues)]);
$parfaite = $partie;
foreach ($parfaite['resultats'] as $k => $r) {
    $parfaite['resultats'][$k]['statut'] = 'ok';
}
verifier('rattrapage : rien à refaire après une partie parfaite', creerPartieErreurs($parfaite, $histo), null);

// ───── progression et chapitres fragiles (statistiques) ─────
$sessions = [];
foreach ([[3, 10], [5, 10], [0, 0], [8, 10], [10, 10], [2, 4], [9, 10]] as $k => [$score, $nb]) {
    $sessions[] = ['n' => $k + 1, 'date' => '2026-11-0' . ($k + 1) . ' 10:00', 'score' => $score, 'total' => $nb];
}
$prog = progressionParties($sessions, 30, 5);
verifier('progression : les parties sans réponse sont ignorées', array_column($prog, 'n'), [1, 2, 4, 5, 6, 7]);
verifier('progression : score de chaque partie en %', array_column($prog, 'pct'), [30, 50, 80, 100, 50, 90]);
verifier('progression : moyenne glissante sur 5 parties, pondérée par les réponses', array_column($prog, 'moyenne'), [30, 40, 53, 65, 64, 77]);
verifier('progression : seules les dernières parties sont gardées', array_column(progressionParties($sessions, 3, 5), 'n'), [5, 6, 7]);
verifier('progression : historique vide', progressionParties([]), []);

$chapitresTest = [
    '03' => ['titre' => 'Boucles', 'taux' => 40, 'essais' => 10],
    '12' => ['titre' => 'Sessions', 'taux' => 65, 'essais' => 6],
    '17' => ['titre' => 'Transactions', 'taux' => 20, 'essais' => 3], // trop peu de réponses pour conclure
    '20' => ['titre' => 'Medoo', 'taux' => 90, 'essais' => 12],       // maîtrisé
    '26' => ['titre' => 'JWT', 'taux' => 40, 'essais' => 5],
    '27' => ['titre' => 'JavaScript', 'taux' => null, 'essais' => 0],
];
verifier('chapitres fragiles : du plus faible au moins faible', array_column(chapitresFragiles($chapitresTest), 'ch'), ['03', '26', '12']);
verifier('chapitres fragiles : nombre limité', array_column(chapitresFragiles($chapitresTest, 2), 'ch'), ['03', '26']);

// ───── graphique de progression (SVG produit par le serveur) ─────
$svg = graphiqueProgression($prog, 720, 260, 'graphe-large');
$xml = simplexml_load_string($svg);
verifier('graphique : SVG bien formé', $xml !== false, true);
verifier('graphique : un point par partie et une seule ligne', [substr_count($svg, 'class="point-partie"'), substr_count($svg, '<polyline')], [count($prog), 1]);
verifier('graphique : étiquette sur la dernière moyenne', str_contains($svg, '>77 %</text>'), true);
verifier('graphique : aucun style en ligne (CSP)', preg_match('/\sstyle=/', $svg), 0);
$donneesGraphe = json_decode((string) $xml['data-points'], true);
verifier('graphique : données de survol cohérentes', [count($donneesGraphe), $donneesGraphe[5]['moyenne'], $donneesGraphe[0]['dateAffichee']], [6, 77, '01/11/2026 10:00']);
verifier('graphique : rien en dessous de deux parties', graphiqueProgression(array_slice($prog, 0, 1), 720, 260, 'x'), '');
$piege = graphiqueProgression(progressionParties([
    ['n' => 1, 'date' => '"><script>alert(1)</script>', 'score' => 1, 'total' => 2],
    ['n' => 2, 'date' => '2026-11-06 10:00', 'score' => 2, 'total' => 2],
]), 720, 260, 'x');
verifier('graphique : données échappées (aucune injection possible)', str_contains($piege, '<script'), false);
verifier('date affichée au format français', formaterDate('2026-11-06 14:43'), '06/11/2026 14:43');

// ───── coloration syntaxique ─────
verifier(
    'coloration : variable, fonction et commentaire',
    colorerCode('$total = count($panier); // nombre'),
    '<span class="c-var">$total</span> = <span class="c-fn">count</span>(<span class="c-var">$panier</span>); <span class="c-com">// nombre</span>'
);
verifier('coloration : chaîne échappée, HTML neutralisé', colorerCode('echo "<b>";'), '<span class="c-kw">echo</span> <span class="c-str">&quot;&lt;b&gt;&quot;</span>;');
verifier('coloration : balise et attribut HTML', colorerCode('<input name="q">'), '<span class="c-tag">&lt;input</span> <span class="c-attr">name</span>=<span class="c-str">&quot;q&quot;</span>&gt;');
verifier('coloration : mots-clés SQL en majuscules', colorerCode('SELECT id FROM livres'), '<span class="c-kw">SELECT</span> id <span class="c-kw">FROM</span> livres');
verifier('coloration : une URL n\'est pas un commentaire', str_contains(colorerCode('curl http://localhost:8000/api'), 'c-com'), false);
verifier('coloration : tentative d\'injection neutralisée', str_contains(colorerCode('</code></pre><script>alert(1)</script>'), '<script'), false);

$blocs = [];
$extraireCode = static fn (array $q): string => implode("\n", array_column(array_filter($q['q'], static fn (array $s): bool => $s['code']), 't'));
foreach ($statiques as $q) {
    $blocs[] = $extraireCode($q);
}
foreach ($generateurs as [, $fabrique]) {
    for ($k = 0; $k < 5; $k++) {
        $blocs[] = $extraireCode($fabrique());
    }
}
$blocs = array_values(array_filter($blocs, static fn (string $b): bool => $b !== ''));
$fideles = 0;
$surs = 0;
foreach ($blocs as $bloc) {
    $html = colorerCode($bloc);
    $fideles += html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8') === $bloc ? 1 : 0;
    $surs += preg_match('/[<>]/', (string) preg_replace('~<span class="c-[a-z]+">|</span>~', '', $html)) === 0 ? 1 : 0;
}
verifier('coloration : le texte affiché est exactement le code (' . count($blocs) . ' blocs de la banque et des générateurs)', $fideles, count($blocs));
verifier('coloration : aucune balise ajoutée en dehors de nos <span>', $surs, count($blocs));

echo "\n";
echo $echecs === 0 ? "Tous les tests passent ($total).\n" : "$echecs échec(s) sur $total tests.\n";
exit($echecs === 0 ? 0 : 1);
