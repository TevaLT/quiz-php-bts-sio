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
    'mode' => 'normal', 'n' => 10, 'chapitres' => $tous, 'cours' => false, 'graine' => 4242, 'graine_texte' => '4242',
];

// ───── validerOptionsPartie ─────
$r = validerOptionsPartie(['mode' => 'examen', 'n' => '20', 'chapitres' => ['03', '10'], 'cours' => '1', 'graine' => ' 123 ']);
verifier('options valides : aucune erreur', $r['erreurs'], []);
verifier('options valides : valeurs normalisées', [$r['valeurs']['mode'], $r['valeurs']['n'], $r['valeurs']['chapitres'], $r['valeurs']['cours'], $r['valeurs']['graine']], ['examen', 20, ['03', '10'], true, 123]);
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
verifier('bilan : cohérence ok + ko + passées = total', $bilan['ok'] + $bilan['ko'] + $bilan['passees'], $bilan['total']);
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
verifier('30 chapitres, de 00 à 29', $tous, array_map(static fn (int $i): string => sprintf('%02d', $i), range(0, 29)));
verifier('les blocs couvrent chaque chapitre une seule fois', array_merge(...array_map('chapitresDuBloc', array_keys(BLOCS))), $tous);
$parChapitre = array_count_values(array_column($statiques, 'ch'));
verifier('chaque chapitre a au moins 8 questions statiques', array_values(array_filter($tous, static fn (string $ch): bool => ($parChapitre[$ch] ?? 0) < 8)), []);
verifier('chaque générateur vise un chapitre existant', count(array_filter($generateurs, static fn (array $g): bool => !isset(TITRES[$g[0]]))), 0);
verifier('conseil de révision sans cours associé', conseilRevision('03'), 'chapitre 03 — Boucles');
verifier('conseil de révision avec le cours associé', conseilRevision('27'), 'chapitre 27 — JavaScript : les fondamentaux — cours « JavaScript Vanilla : les fondamentaux »');

// ───── sélections rapides de l'accueil ─────
$pre = preselectionsChapitres();
verifier('sélections rapides : uniquement des chapitres existants', count(array_filter(array_merge(...array_values($pre)), static fn (string $c): bool => !isset(TITRES[$c]))), 0);
verifier('sélection « Tout » = tous les chapitres', $pre['Tout'], $tous);
verifier('sélection « JavaScript » = chapitres 27 à 29', $pre['JavaScript'], ['27', '28', '29']);
verifier('« PHP et SQL » + « JavaScript » = tous les chapitres, sans doublon', array_merge($pre['PHP et SQL'], $pre['JavaScript']), $tous);
$js = creerPartie($options(['chapitres' => $pre['JavaScript'], 'n' => 20, 'graine' => 9]), $statiques, $generateurs, $histoVide);
verifier('partie JavaScript : 20 questions des chapitres 27 à 29 seulement', [count($js['questions']), array_values(array_diff(array_unique(array_column($js['questions'], 'ch')), ['27', '28', '29']))], [20, []]);
verifier('partie JavaScript : des questions générées en JavaScript aussi', count(array_filter($js['questions'], static fn (array $q): bool => $q['gen'])) > 0, true);

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
