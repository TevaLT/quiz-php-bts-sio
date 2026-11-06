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
 *   resultats    : un élément par question traitée [statut: ok|ko|passee, choisies: list<int>, texte: string]
 *   terminee     : toutes les questions traitées, ou partie abandonnée
 *   enregistree  : l'historique a déjà été mis à jour (évite un double enregistrement)
 *   rattrapage   : (facultatif) numéro de la partie dont on refait les erreurs
 */

const MODES = [
    'normal' => 'Entraînement',
    'examen' => 'Examen',
    'revision' => 'Révision',
];

const NOMBRES_QUESTIONS = [5, 10, 15, 20, 30, 50];

/**
 * Sélections rapides proposées sur l'accueil : un clic coche les chapitres correspondants (le choix reste modifiable).
 *
 * @return array<string, list<string>> libellé du bouton → chapitres cochés
 */
function preselectionsChapitres(): array
{
    return [
        'Tout' => array_map('strval', array_keys(TITRES)),
        'PHP et SQL' => array_merge(chapitresDuBloc(1), chapitresDuBloc(2), chapitresDuBloc(3), chapitresDuBloc(4)),
        'JavaScript' => chapitresDuBloc(5),
        'Sécurité web' => ['10', '11', '12', '18', '26'],
        'Aucun' => [],
    ];
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
        $n = 15;
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
            'chapitres' => $chapitres,
            'cours' => ($post['cours'] ?? '') === '1',
            'graine' => $graine,
            'graine_texte' => $texteGraine,
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
    $selection = preparerSession($st, $gen, (int) $options['n'], $histo, $numSession, [
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
 * Valide la réponse envoyée pour une question.
 *
 * @param array<string,mixed> $post
 * @return array{reponse: array<string,mixed>|null, erreur: string|null}
 */
function validerReponse(array $q, array $post): array
{
    $echec = static fn (string $message): array => ['reponse' => null, 'erreur' => $message];

    $action = $post['action'] ?? 'valider';
    if (!is_string($action) || !in_array($action, ['valider', 'passer'], true)) {
        return $echec('Action inconnue.');
    }
    if ($action === 'passer') {
        return ['reponse' => ['passer' => true], 'erreur' => null];
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
 * @param array<string,mixed> $reponse sortie validée de validerReponse()['reponse']
 */
function repondre(array $partie, array $reponse): array
{
    $q = questionCourante($partie);
    if ($q === null) {
        return $partie;
    }

    if (!empty($reponse['passer'])) {
        $resultat = ['statut' => 'passee', 'choisies' => [], 'texte' => ''];
    } elseif ($q['type'] === 'saisie') {
        $texte = (string) ($reponse['texte'] ?? '');
        $resultat = ['statut' => reponseLibreCorrecte($texte, $q['acceptees']) ? 'ok' : 'ko', 'choisies' => [], 'texte' => $texte];
    } else {
        $choisies = array_map('intval', $reponse['choix'] ?? []);
        $resultat = ['statut' => evaluerChoix($q, $choisies) ? 'ok' : 'ko', 'choisies' => $choisies, 'texte' => ''];
    }

    $partie['resultats'][] = $resultat;
    if (positionPartie($partie) >= count($partie['questions'])) {
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

/** Statistiques d'une partie pour la page de bilan. */
function bilanPartie(array $partie): array
{
    $resultats = $partie['resultats'];
    $total = count($resultats);
    $ok = count(array_filter($resultats, static fn (array $r): bool => $r['statut'] === 'ok'));
    $passees = count(array_filter($resultats, static fn (array $r): bool => $r['statut'] === 'passee'));

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
        'ko' => $total - $ok - $passees,
        'passees' => $passees,
        'non_traitees' => count($partie['questions']) - $total,
        'pourcentage' => $total === 0 ? 0 : (int) round(100 * $ok / $total),
        'note' => $total === 0 ? 0.0 : round(20 * $ok / $total, 1),
        'par_chapitre' => $parChapitre,
        'a_revoir' => array_keys(array_filter($parChapitre, static fn (array $v): bool => $v['ok'] < $v['total'])),
        'duree' => max(0, ($partie['fin'] ?? time()) - $partie['debut']),
    ];
}
