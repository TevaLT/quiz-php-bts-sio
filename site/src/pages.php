<?php

declare(strict_types=1);

/**
 * Pages et actions du site (les « contrôleurs »).
 *
 *   - une page (GET) lit l'état, prépare les données et appelle rendre() ;
 *   - une action (POST) vérifie le jeton CSRF, valide l'entrée, modifie l'état puis REDIRIGE (Post/Redirect/Get).
 *
 * La logique du quiz est dans partie.php et dans le moteur partagé (quiz/moteur.php).
 */

/** Banque de questions et générateurs, chargés une fois par requête. @return array{0: list<array<string,mixed>>, 1: array<string,array{0:string,1:callable}>} */
function banque(): array
{
    static $cache = null;
    if ($cache === null) {
        [$statiques, $erreurs] = chargerBanque();
        if ($erreurs !== []) {
            throw new RuntimeException('Banque de questions invalide : ' . implode(' | ', $erreurs));
        }
        $cache = [$statiques, chargerGenerateurs()];
    }

    return $cache;
}

function partieEnCours(): ?array
{
    $partie = $_SESSION['partie'] ?? null;

    return is_array($partie) ? $partie : null;
}

function memoriserPartie(array $partie): void
{
    $_SESSION['partie'] = $partie;
}

/** Enregistre les résultats d'une partie dans l'historique (une seule fois, même si la page est rechargée). */
function cloturerPartie(array $partie): array
{
    if (!$partie['enregistree'] && $partie['resultats'] !== []) {
        try {
            modifierHistorique(static fn (array $histo): array => appliquerResultats($histo, resultatsPourHistorique($partie), (int) $partie['graine']));
        } catch (Throwable $e) {
            error_log('[quiz] historique non enregistré : ' . $e->getMessage());
            flash('erreur', "Ton historique n'a pas pu être enregistré (détails dans les journaux du serveur).");
        }
    }
    $partie['enregistree'] = true;

    return $partie;
}

// ───────────────────────────── Accueil et création d'une partie ─────────────────────────────

function pageAccueil(?array $valeurs = null, array $erreurs = [], int $statut = 200): never
{
    [$statiques, $generateurs] = banque();
    $histo = chargerHistorique();
    $comptes = array_fill_keys(array_keys(TITRES), 0);
    foreach ($statiques as $q) {
        $comptes[$q['ch']]++;
    }
    $valeurs ??= [
        'mode' => 'normal',
        'n' => 15,
        'chapitres' => array_map('strval', array_keys(TITRES)),
        'cours' => false,
        'graine' => null,
        'graine_texte' => '',
    ];

    rendre('accueil', [
        'titre' => 'Accueil',
        'page' => 'accueil',
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'comptes' => $comptes,
        'total' => count($statiques),
        'nbGenerateurs' => count($generateurs),
        'nbCours' => count(array_filter($statiques, static fn (array $q): bool => $q['cours'])),
        'aRevoir' => nombreARevoir($statiques, $histo),
        'nbSessions' => count($histo['sessions']),
        'partie' => partieEnCours(),
        'fragiles' => chapitresFragiles(statistiquesHistorique($histo, $statiques)['chapitres']),
        'preselections' => preselectionsChapitres(),
    ], $statut);
}

function actionCreerPartie(): never
{
    verifierCsrf();
    ['valeurs' => $valeurs, 'erreurs' => $erreurs] = validerOptionsPartie($_POST);
    if ($erreurs !== []) {
        pageAccueil($valeurs, $erreurs, 422); // formulaire réaffiché avec les saisies et les messages
    }

    [$statiques, $generateurs] = banque();
    $partie = creerPartie($valeurs, $statiques, $generateurs, chargerHistorique());
    if ($partie === null) {
        $erreurs['general'] = $valeurs['mode'] === 'revision'
            ? 'Aucune erreur à réviser pour ces chapitres : bravo ! Lance plutôt un entraînement.'
            : 'Aucune question ne correspond à ces options.';
        pageAccueil($valeurs, $erreurs, 422);
    }

    // Une partie inachevée est close proprement : ses réponses déjà données comptent dans l'historique.
    $ancienne = partieEnCours();
    if ($ancienne !== null && !$ancienne['enregistree']) {
        cloturerPartie(abandonnerPartie($ancienne));
    }
    memoriserPartie($partie);
    rediriger('/question');
}

// ───────────────────────────── Déroulement d'une partie ─────────────────────────────

function pageQuestion(): never
{
    $partie = partieEnCours();
    if ($partie === null) {
        flash('info', "Aucune partie en cours : lance-en une.");
        rediriger('/');
    }
    $q = questionCourante($partie);
    if ($q === null) {
        rediriger('/bilan');
    }

    rendre('question', [
        'titre' => 'Question ' . (positionPartie($partie) + 1) . ' sur ' . count($partie['questions']),
        'page' => 'jeu',
        'partie' => $partie,
        'q' => $q,
        'numero' => positionPartie($partie) + 1,
        'total' => count($partie['questions']),
    ]);
}

function actionRepondre(): never
{
    verifierCsrf();
    $partie = partieEnCours();
    if ($partie === null) {
        rediriger('/');
    }
    $q = questionCourante($partie);
    $numero = filter_var($_POST['numero'] ?? null, FILTER_VALIDATE_INT);
    if ($q === null || $numero !== positionPartie($partie) + 1) {
        // double envoi, bouton « précédent », onglet obsolète : on ne compte pas deux fois la même question
        flash('info', 'Cette question a déjà reçu une réponse.');
        rediriger($q === null ? '/bilan' : '/question');
    }

    ['reponse' => $reponse, 'erreur' => $erreur] = validerReponse($q, $_POST);
    if ($reponse === null) {
        flash('erreur', (string) $erreur);
        rediriger('/question');
    }

    $partie = repondre($partie, $reponse);
    if ($partie['terminee']) {
        $partie = cloturerPartie($partie);
    }
    memoriserPartie($partie);

    if ($partie['mode'] === 'examen') {
        rediriger($partie['terminee'] ? '/bilan' : '/question');
    }
    rediriger('/correction');
}

function pageCorrection(): never
{
    $partie = partieEnCours();
    if ($partie === null) {
        rediriger('/');
    }
    $traitees = positionPartie($partie);
    if ($partie['mode'] === 'examen' || $traitees === 0) {
        rediriger($partie['terminee'] ? '/bilan' : '/question');
    }

    rendre('correction', [
        'titre' => 'Correction',
        'page' => 'jeu',
        'q' => $partie['questions'][$traitees - 1],
        'resultat' => $partie['resultats'][$traitees - 1],
        'numero' => $traitees,
        'total' => count($partie['questions']),
        'terminee' => $partie['terminee'],
    ]);
}

function actionAbandonner(): never
{
    verifierCsrf();
    $partie = partieEnCours();
    if ($partie === null) {
        rediriger('/');
    }
    memoriserPartie(cloturerPartie(abandonnerPartie($partie)));
    rediriger('/bilan');
}

function pageBilan(): never
{
    $partie = partieEnCours();
    if ($partie === null) {
        flash('info', 'Aucune partie à afficher : lance-en une.');
        rediriger('/');
    }
    if (!$partie['terminee']) {
        rediriger('/question');
    }

    rendre('bilan', [
        'titre' => 'Bilan',
        'page' => 'jeu',
        'partie' => $partie,
        'bilan' => bilanPartie($partie),
    ]);
}

/** Relance immédiatement les questions ratées ou passées de la partie qui vient de se terminer. */
function actionRefaireErreurs(): never
{
    verifierCsrf();
    $partie = partieEnCours();
    if ($partie === null) {
        flash('info', 'Aucune partie terminée : lance-en une.');
        rediriger('/');
    }
    if (!$partie['terminee']) {
        rediriger('/question');
    }
    $nouvelle = creerPartieErreurs($partie, chargerHistorique());
    if ($nouvelle === null) {
        flash('info', 'Aucune erreur dans cette partie : rien à refaire.');
        rediriger('/bilan');
    }
    memoriserPartie($nouvelle);
    rediriger('/question');
}

// ───────────────────────────── Statistiques et remise à zéro ─────────────────────────────

function pageStats(): never
{
    [$statiques] = banque();
    rendre('stats', [
        'titre' => 'Statistiques',
        'page' => 'stats',
        'stats' => statistiquesHistorique(chargerHistorique(), $statiques),
    ]);
}

function pageSources(): never
{
    rendre('sources', ['titre' => 'Sources et licences', 'page' => 'sources']);
}

function pageReinitialiser(): never
{
    rendre('reinitialiser', ['titre' => 'Effacer l\'historique', 'page' => 'stats']);
}

function actionReinitialiser(): never
{
    verifierCsrf();
    if (($_POST['confirmer'] ?? '') !== 'oui') {
        flash('erreur', 'Coche la case de confirmation pour effacer ton historique.');
        rediriger('/reinitialiser');
    }
    if (is_file(FICHIER_HISTORIQUE)) {
        unlink(FICHIER_HISTORIQUE);
    }
    flash('succes', 'Historique effacé : toutes les questions redeviennent « jamais vues ».');
    rediriger('/stats');
}

// ───────────────────────────── Thème ─────────────────────────────

function actionTheme(): never
{
    verifierCsrf();
    $theme = $_POST['theme'] ?? null;
    if (!is_string($theme) || !isset(THEMES[$theme])) {
        erreurHttp(400, 'Requête invalide', 'Ce thème n\'existe pas.');
    }
    enregistrerTheme($theme);

    $retour = $_POST['retour'] ?? '/';
    rediriger(is_string($retour) && in_array($retour, PAGES_RETOUR, true) ? $retour : '/');
}
