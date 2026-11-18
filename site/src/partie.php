<?php

declare(strict_types=1);

/**
 * Logique d'une partie de quiz sur le web.
 *
 * Ce sont des fonctions « pures » : elles reçoivent et retournent des tableaux, sans toucher aux
 * superglobales ni produire de HTML (séparation logique / affichage, comme en MVC). La session PHP ne sert
 * qu'à garder le tableau « partie » entre deux requêtes.
 *
 * Forme d'une partie :
 *   id, mode, options, graine, numSession, debut, fin,
 *   questions    : questions tirées, propositions déjà mélangées (l'ordre reste stable au rechargement)
 *   resultats    : un élément par question traitée
 *                  [statut: ok|ko|passee|temps, choisies: list<int>, texte: string, duree: ?float (secondes)]
 *   chrono       : secondes accordées par question (0 = sans minuteur)
 *   debuts       : instant (microtime) du premier affichage de chaque question : le minuteur est vérifié par le
 *                  serveur, recharger la page ne le remet pas à zéro
 *   record       : meilleure série de l'historique au lancement (pour annoncer un nouveau record)
 *   terminee     : toutes les questions traitées, partie abandonnée, ou première erreur en mode « série »
 *   enregistree  : l'historique a déjà été mis à jour (évite un double enregistrement)
 *   rattrapage   : (facultatif) numéro de la partie dont on refait les erreurs
 */

const MODES = [
    'normal' => 'Entraînement',
    'examen' => 'Examen',
    'serie' => 'Série',
    'revision' => 'Révision',
];

const NOMBRES_QUESTIONS = [5, 10, 15, 20, 30, 50];

/** Minuteur par question, en secondes (0 = sans minuteur). */
const CHRONOS = [0, 15, 30, 45, 60, 90];

/** Réglages d'une « partie rapide » (un clic depuis l'accueil) : 10 questions sur tout le programme, avec correction. */
const PARTIE_RAPIDE = ['mode' => 'normal', 'n' => 10, 'chrono' => 0];

/** Réglages proposés par défaut sur l'accueil (première visite). */
function reglagesParDefaut(): array
{
    return [
        'mode' => 'normal',
        'n' => NOMBRE_QUESTIONS_DEFAUT,
        'chrono' => 0,
        'chapitres' => array_map('strval', array_keys(TITRES)),
        'cours' => false,
        'graine' => null,
        'graine_texte' => '',
    ];
}

/**
 * Réglages de la dernière partie, pour pré-remplir l'accueil : on retrouve ses habitudes sans tout recocher.
 * La graine n'est pas reprise (nouveau tirage), et des réglages devenus invalides (ancienne version) sont ignorés,
 * comme ceux d'une « Partie rapide » (un préréglage : l'accueil repropose alors les réglages par défaut).
 */
function derniersReglages(?array $partie): ?array
{
    $options = $partie['options'] ?? null;
    if (!is_array($options) || !isset($options['mode'], $options['n'], $options['chapitres']) || !is_array($options['chapitres'])) {
        return null;
    }
    if (!empty($options['rapide'])) {
        return null;
    }
    $resultat = validerOptionsPartie([
        'mode' => (string) $options['mode'],
        'n' => (string) $options['n'],
        'chrono' => (string) ($options['chrono'] ?? 0),
        'chapitres' => array_map('strval', $options['chapitres']),
        'cours' => !empty($options['cours']) ? '1' : '',
    ]);

    return $resultat['erreurs'] === [] ? $resultat['valeurs'] : null;
}

/**
 * Valide le formulaire de création d'une partie : rien de ce qui vient du navigateur n'est supposé fiable.
 *
 * @param array<string,mixed> $post
 * @return array{valeurs: array<string,mixed>, erreurs: array<string,string>}
 */
function validerOptionsPartie(array $post): array
{
    $erreurs = [];

    $mode = $post['mode'] ?? 'normal';
    if (!is_string($mode) || !isset(MODES[$mode])) {
        $erreurs['mode'] = 'Mode inconnu.';
        $mode = 'normal';
    }

    $n = filter_var($post['n'] ?? null, FILTER_VALIDATE_INT);
    if ($n === false || !in_array($n, NOMBRES_QUESTIONS, true)) {
        $erreurs['n'] = 'Choisis un nombre de questions parmi ceux proposés.';
        $n = NOMBRE_QUESTIONS_DEFAUT;
    }

    $chrono = filter_var($post['chrono'] ?? '0', FILTER_VALIDATE_INT);
    if ($chrono === false || !in_array($chrono, CHRONOS, true)) {
        $erreurs['chrono'] = 'Choisis une durée de minuteur parmi celles proposées.';
        $chrono = 0;
    }

    $chapitres = [];
    $brut = $post['chapitres'] ?? [];
    if (!is_array($brut)) {
        $erreurs['chapitres'] = 'Liste de chapitres invalide.';
    } else {
        foreach ($brut as $c) {
            if (!is_string($c) || !isset(TITRES[$c])) {
                $erreurs['chapitres'] = 'Chapitre inconnu dans la sélection.';
                continue;
            }
            $chapitres[$c] = $c;
        }
        if ($chapitres === [] && !isset($erreurs['chapitres'])) {
            $erreurs['chapitres'] = 'Coche au moins un chapitre.';
        }
    }
    $chapitres = array_values($chapitres);
    sort($chapitres);

    $graine = null;
    $texteGraine = $post['graine'] ?? '';
    if (!is_string($texteGraine)) {
        $erreurs['graine'] = 'Graine invalide.';
        $texteGraine = '';
    } elseif (trim($texteGraine) !== '') {
        $texteGraine = trim($texteGraine);
        if (!ctype_digit($texteGraine) || strlen($texteGraine) > 10 || (int) $texteGraine < 1 || (int) $texteGraine > 2147483646) {
            $erreurs['graine'] = 'La graine est un entier entre 1 et 2147483646.';
        } else {
            $graine = (int) $texteGraine;
        }
    }

    return [
        'valeurs' => [
            'mode' => $mode,
            'n' => $n,
            'chrono' => $chrono,
            'chapitres' => $chapitres,
            'cours' => ($post['cours'] ?? '') === '1',
            'graine' => $graine,
            'graine_texte' => $texteGraine,
            'rapide' => ($post['rapide'] ?? '') === '1', // lancée par « Partie rapide » : un préréglage, pas un choix à mémoriser
        ],
        'erreurs' => $erreurs,
    ];
}

/**
 * Crée une partie, ou retourne null si aucune question ne correspond aux options.
 *
 * @param array<string,mixed> $options sortie validée de validerOptionsPartie()['valeurs']
 * @param list<array<string,mixed>> $statiques
 * @param array<string,array{0:string,1:callable}> $generateurs
 */
function creerPartie(array $options, array $statiques, array $generateurs, array $histo): ?array
{
    $graine = $options['graine'] ?? random_int(1, 2147483646);
    initialiserAlea($graine);
    $numSession = count($histo['sessions']) + 1;

    $tousLesChapitres = count($options['chapitres']) === count(TITRES);
    [$st, $gen] = filtrerBanque($statiques, $generateurs, $tousLesChapitres ? null : $options['chapitres'], (bool) $options['cours']);
    // Une série continue jusqu'à la première erreur : on prépare le nombre maximal de questions.
    $n = $options['mode'] === 'serie' ? SERIE_MAX : (int) $options['n'];
    $selection = preparerSession($st, $gen, $n, $histo, $numSession, [
        'revision' => $options['mode'] === 'revision',
        'cours' => (bool) $options['cours'],
    ]);
    if ($selection === []) {
        return null;
    }
    foreach ($selection as $i => $q) {
        if ($q['type'] !== 'saisie') {
            $selection[$i]['choix'] = melanger($q['choix']); // mélangé une fois pour toutes
        }
    }

    return [
        'id' => bin2hex(random_bytes(6)),
        'mode' => $options['mode'],
        'options' => $options,
        'graine' => $graine,
        'numSession' => $numSession,
        'debut' => time(),
        'fin' => null,
        'questions' => $selection,
        'resultats' => [],
        'chrono' => (int) ($options['chrono'] ?? 0),
        'debuts' => [],
        'record' => meilleureSerieHistorique($histo),
        'terminee' => false,
        'enregistree' => false,
    ];
}

/**
 * Nouvelle partie avec les questions ratées ou passées d'une partie terminée : mêmes énoncés (y compris les
 * questions générées, avec leurs chiffres), dans un ordre et avec des propositions remélangés.
 * Retourne null s'il n'y a rien à refaire.
 */
function creerPartieErreurs(array $partie, array $histo): ?array
{
    $aRefaire = [];
    foreach ($partie['resultats'] as $i => $resultat) {
        if ($resultat['statut'] !== 'ok' && isset($partie['questions'][$i])) {
            $aRefaire[] = $partie['questions'][$i];
        }
    }
    if ($aRefaire === []) {
        return null;
    }

    $graine = random_int(1, 2147483646);
    initialiserAlea($graine);
    $questions = espacerChapitres($aRefaire); // mélange aussi l'ordre des questions
    foreach ($questions as $k => $q) {
        if ($q['type'] !== 'saisie') {
            $questions[$k]['choix'] = melanger($q['choix']);
        }
    }

    return [
        'id' => bin2hex(random_bytes(6)),
        'mode' => 'normal', // correction après chaque question : on refait ses erreurs pour comprendre
        'options' => $partie['options'], // « Rejouer » repartira des réglages d'origine
        'graine' => $graine,
        'numSession' => count($histo['sessions']) + 1,
        'debut' => time(),
        'fin' => null,
        'questions' => $questions,
        'resultats' => [],
        'chrono' => (int) ($partie['chrono'] ?? 0), // même minuteur que la partie d'origine
        'debuts' => [],
        'record' => meilleureSerieHistorique($histo),
        'terminee' => false,
        'enregistree' => false,
        'rattrapage' => (int) $partie['numSession'],
    ];
}

/** Nombre de questions déjà traitées. */
function positionPartie(array $partie): int
{
    return count($partie['resultats']);
}

/** Question à poser maintenant, ou null si la partie est finie. */
function questionCourante(array $partie): ?array
{
    if ($partie['terminee']) {
        return null;
    }

    return $partie['questions'][positionPartie($partie)] ?? null;
}

/**
 * Note l'instant où la question courante est affichée pour la première fois (départ du minuteur et mesure
 * du temps de réponse). Un rechargement de la page ne change rien : le temps continue de s'écouler.
 */
function marquerAffichage(array $partie, float $maintenant): array
{
    if (questionCourante($partie) !== null) {
        $partie['debuts'][positionPartie($partie)] ??= $maintenant;
    }

    return $partie;
}

/** La question courante a-t-elle déjà été affichée ? (une réponse ne peut arriver qu'après l'affichage) */
function questionAffichee(array $partie): bool
{
    return isset($partie['debuts'][positionPartie($partie)]);
}

/** Secondes restantes pour répondre à la question courante, ou null sans minuteur. */
function tempsRestant(array $partie, float $maintenant): ?float
{
    $chrono = (int) ($partie['chrono'] ?? 0);
    if ($chrono <= 0) {
        return null;
    }
    $debut = $partie['debuts'][positionPartie($partie)] ?? $maintenant;

    return max(0.0, $chrono - ($maintenant - $debut));
}

/**
 * Score en direct, affiché pendant la partie : bonnes réponses, erreurs (fausses ou hors délai) et questions passées.
 *
 * @return array{ok: int, erreurs: int, passees: int}
 */
function scorePartie(array $partie): array
{
    $score = ['ok' => 0, 'erreurs' => 0, 'passees' => 0];
    foreach ($partie['resultats'] as $resultat) {
        if ($resultat['statut'] === 'ok') {
            $score['ok']++;
        } elseif ($resultat['statut'] === 'passee') {
            $score['passees']++;
        } else {
            $score['erreurs']++;
        }
    }

    return $score;
}

/** Nombre de bonnes réponses consécutives à la fin de la partie (la série en cours). */
function serieEnCours(array $partie): int
{
    $serie = 0;
    foreach (array_reverse($partie['resultats']) as $resultat) {
        if ($resultat['statut'] !== 'ok') {
            break;
        }
        $serie++;
    }

    return $serie;
}

/**
 * Valide la réponse envoyée pour une question.
 *
 * @param array<string,mixed> $post
 * @return array{reponse: array<string,mixed>|null, erreur: string|null}
 */
function validerReponse(array $q, array $post): array
{
    $echec = static fn (string $message): array => ['reponse' => null, 'erreur' => $message];

    $action = $post['action'] ?? 'valider';
    if (!is_string($action) || !in_array($action, ['valider', 'passer', 'temps'], true)) {
        return $echec('Action inconnue.');
    }
    if ($action === 'passer') {
        return ['reponse' => ['passer' => true], 'erreur' => null];
    }
    if ($action === 'temps') {
        return ['reponse' => ['temps' => true], 'erreur' => null]; // envoyé par le minuteur à zéro
    }

    if ($q['type'] === 'saisie') {
        $texte = $post['texte'] ?? null;
        if (!is_string($texte) || trim($texte) === '') {
            return $echec('Écris ta réponse, ou passe la question.');
        }
        $texte = trim($texte);
        if (mb_strlen($texte) > 200) {
            return $echec('Réponse trop longue (200 caractères maximum).');
        }

        return ['reponse' => ['texte' => $texte], 'erreur' => null];
    }

    $choix = $post['choix'] ?? [];
    $liste = is_array($choix) ? $choix : [$choix];
    $indices = [];
    foreach ($liste as $valeur) {
        if (!is_string($valeur) || !ctype_digit($valeur) || (int) $valeur >= count($q['choix'])) {
            return $echec('Proposition inconnue.');
        }
        $indices[(int) $valeur] = (int) $valeur;
    }
    $indices = array_values($indices);
    sort($indices);
    if ($indices === []) {
        return $echec('Choisis une proposition, ou passe la question.');
    }
    if ($q['type'] === 'qcm' && count($indices) !== 1) {
        return $echec('Une seule proposition est attendue.');
    }

    return ['reponse' => ['choix' => $indices], 'erreur' => null];
}

/**
 * Enregistre la réponse à la question courante et retourne la partie mise à jour.
 *
 * Avec un minuteur, c'est le serveur qui tranche : une réponse arrivée après le délai (plus une petite marge pour
 * le réseau) est comptée « temps écoulé », quelle que soit sa valeur. En mode série, la première réponse qui
 * n'est pas juste termine la partie.
 *
 * @param array<string,mixed> $reponse sortie validée de validerReponse()['reponse']
 * @param float|null $maintenant instant de la réponse (microtime) ; injectable pour les tests
 */
function repondre(array $partie, array $reponse, ?float $maintenant = null): array
{
    $q = questionCourante($partie);
    if ($q === null) {
        return $partie;
    }
    $maintenant ??= microtime(true);
    $debut = $partie['debuts'][positionPartie($partie)] ?? null;
    $duree = $debut === null ? null : round(max(0.0, $maintenant - $debut), 1);
    $chrono = (int) ($partie['chrono'] ?? 0);
    $horsDelai = $chrono > 0 && $duree !== null && $duree > $chrono + TOLERANCE_CHRONO;

    if (!empty($reponse['passer'])) {
        $resultat = ['statut' => 'passee', 'choisies' => [], 'texte' => ''];
    } elseif (!empty($reponse['temps'])) {
        $resultat = ['statut' => 'temps', 'choisies' => [], 'texte' => ''];
    } elseif ($q['type'] === 'saisie') {
        $texte = (string) ($reponse['texte'] ?? '');
        $resultat = ['statut' => reponseLibreCorrecte($texte, $q['acceptees']) ? 'ok' : 'ko', 'choisies' => [], 'texte' => $texte];
    } else {
        $choisies = array_map('intval', $reponse['choix'] ?? []);
        $resultat = ['statut' => evaluerChoix($q, $choisies) ? 'ok' : 'ko', 'choisies' => $choisies, 'texte' => ''];
    }
    if ($horsDelai && $resultat['statut'] !== 'passee') {
        $resultat['statut'] = 'temps'; // la réponse reste affichée dans la correction, mais elle ne compte pas
    }
    $resultat['duree'] = $duree;

    $partie['resultats'][] = $resultat;
    $finDeSerie = $partie['mode'] === 'serie' && $resultat['statut'] !== 'ok';
    if ($finDeSerie || positionPartie($partie) >= count($partie['questions'])) {
        $partie['terminee'] = true;
        $partie['fin'] = time();
    }

    return $partie;
}

/** Termine la partie maintenant : les questions déjà traitées comptent, les autres sont ignorées. */
function abandonnerPartie(array $partie): array
{
    $partie['terminee'] = true;
    $partie['fin'] ??= time();

    return $partie;
}

/**
 * Résultats au format attendu par appliquerResultats() (moteur partagé avec le terminal).
 *
 * @return list<array{question: array<string,mixed>, statut: string}>
 */
function resultatsPourHistorique(array $partie): array
{
    $liste = [];
    foreach ($partie['resultats'] as $i => $resultat) {
        $liste[] = ['question' => $partie['questions'][$i], 'statut' => $resultat['statut']];
    }

    return $liste;
}

/**
 * Informations ajoutées à la session dans l'historique (partagé avec le terminal).
 *
 * @return array{mode: string, chrono: int, duree_moyenne: float|null}
 */
function infosHistorique(array $partie): array
{
    return [
        'mode' => isset($partie['rattrapage']) ? 'rattrapage' : (string) $partie['mode'],
        'chrono' => (int) ($partie['chrono'] ?? 0),
        'duree_moyenne' => dureeMoyenne($partie['resultats']),
    ];
}

/** Temps moyen de réponse (en secondes, une décimale), ou null si aucune durée n'a été mesurée. */
function dureeMoyenne(array $resultats): ?float
{
    $durees = array_values(array_filter(array_column($resultats, 'duree'), static fn (mixed $d): bool => is_float($d) || is_int($d)));

    return $durees === [] ? null : round(array_sum($durees) / count($durees), 1);
}

/** Statistiques d'une partie pour la page de bilan. */
function bilanPartie(array $partie): array
{
    $resultats = $partie['resultats'];
    $total = count($resultats);
    $ok = count(array_filter($resultats, static fn (array $r): bool => $r['statut'] === 'ok'));
    $passees = count(array_filter($resultats, static fn (array $r): bool => $r['statut'] === 'passee'));
    $horsDelai = count(array_filter($resultats, static fn (array $r): bool => $r['statut'] === 'temps'));
    $serie = meilleureSerie(array_column($resultats, 'statut'));
    $record = (int) ($partie['record'] ?? 0);

    $parChapitre = [];
    foreach ($resultats as $i => $r) {
        $ch = $partie['questions'][$i]['ch'];
        $parChapitre[$ch] ??= ['ok' => 0, 'total' => 0];
        $parChapitre[$ch]['total']++;
        $parChapitre[$ch]['ok'] += $r['statut'] === 'ok' ? 1 : 0;
    }
    ksort($parChapitre);

    return [
        'total' => $total,
        'ok' => $ok,
        'ko' => $total - $ok - $passees - $horsDelai,
        'passees' => $passees,
        'temps' => $horsDelai,
        'non_traitees' => $partie['mode'] === 'serie' ? 0 : count($partie['questions']) - $total,
        'pourcentage' => $total === 0 ? 0 : (int) round(100 * $ok / $total),
        'note' => $total === 0 ? 0.0 : round(20 * $ok / $total, 1),
        'par_chapitre' => $parChapitre,
        'a_revoir' => array_keys(array_filter($parChapitre, static fn (array $v): bool => $v['ok'] < $v['total'])),
        'duree' => max(0, ($partie['fin'] ?? time()) - $partie['debut']),
        'duree_moyenne' => dureeMoyenne($resultats),
        'serie' => $serie,
        'record' => $record,
        'nouveau_record' => $serie > $record && $serie >= 3,
    ];
}

/**
 * La prochaine étape conseillée après une partie, affichée en tête du bilan : une seule action mise en avant,
 * choisie selon le résultat (beaucoup d'erreurs → relire la fiche du chapitre le plus raté ; quelques erreurs →
 * les refaire ; sans faute → augmenter la difficulté avec le minuteur, puis la série).
 *
 * @return array{action: string, titre: string, texte: string, chapitre?: string}
 *         action : rejouer | fiche | erreurs | minuteur | serie
 */
function prochaineEtape(array $partie, array $bilan): array
{
    $erreurs = $bilan['ko'] + $bilan['passees'] + $bilan['temps'];
    if ($bilan['total'] === 0) {
        return ['action' => 'rejouer', 'titre' => 'Lance une nouvelle partie', 'texte' => "Aucune réponse n'a été donnée pendant cette partie."];
    }
    if ($erreurs > 0 && $bilan['pourcentage'] < 50) {
        $faible = chapitreLePlusFaible($bilan['par_chapitre']);
        if ($faible !== null) {
            return [
                'action' => 'fiche',
                'chapitre' => $faible,
                'titre' => 'Relis la fiche « ' . (TITRES[$faible] ?? $faible) . ' »',
                'texte' => "C'est le chapitre le plus raté de cette partie : relis ses réponses expliquées, puis refais tes erreurs.",
            ];
        }
    }
    if ($erreurs > 0) {
        return [
            'action' => 'erreurs',
            'titre' => $erreurs > 1 ? "Refais tes $erreurs erreurs" : 'Refais ton erreur',
            'texte' => 'Le plus efficace : les retravailler tout de suite, pendant que les explications sont fraîches.',
        ];
    }
    if ($partie['mode'] !== 'serie' && (int) ($partie['chrono'] ?? 0) === 0) {
        return [
            'action' => 'minuteur',
            'titre' => 'Sans faute ! Ajoute un minuteur',
            'texte' => 'Rejoue avec 30 secondes par question pour vérifier que les bonnes réponses sont devenues des réflexes.',
        ];
    }
    if ($partie['mode'] !== 'serie') {
        return ['action' => 'serie', 'titre' => 'Sans faute ! Tente une série', 'texte' => "Enchaîne les questions jusqu'à la première erreur : jusqu'où iras-tu ?"];
    }

    return ['action' => 'rejouer', 'titre' => 'Série parfaite !', 'texte' => 'Relance une série pour aller encore plus loin.'];
}

/**
 * Chapitre au plus faible taux de réussite dans une partie (à égalité, celui qui compte le plus d'erreurs),
 * parmi ceux qui ont au moins une erreur ; null si tout est juste.
 *
 * @param array<int|string, array{ok: int, total: int}> $parChapitre
 */
function chapitreLePlusFaible(array $parChapitre): ?string
{
    $pire = null;
    foreach ($parChapitre as $ch => $v) {
        if ($v['ok'] >= $v['total']) {
            continue;
        }
        $candidat = [$v['ok'] / $v['total'], -($v['total'] - $v['ok']), sprintf('%02d', (int) $ch)];
        if ($pire === null || $candidat < $pire) {
            $pire = $candidat;
        }
    }

    return $pire === null ? null : $pire[2];
}
