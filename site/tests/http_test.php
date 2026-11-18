<?php

declare(strict_types=1);

/**
 * Test de bout en bout : lance le vrai site avec le serveur intégré de PHP, puis joue des parties comme un navigateur
 * (cookies, jeton CSRF, redirections), et vérifie les statuts HTTP, les en-têtes de sécurité et le contenu des pages.
 *
 * L'historique est écrit dans un fichier TEMPORAIRE (variable d'environnement QUIZ_HISTORIQUE) :
 * ton historique.json n'est jamais lu ni modifié, et le test le vérifie.
 *
 *   php site/tests/http_test.php     (depuis la racine du dépôt)
 */

$racine = dirname(__DIR__, 2);
$public = $racine . '/site/public';
$temp = sys_get_temp_dir() . '/quiz-e2e-' . bin2hex(random_bytes(4));
mkdir($temp . '/sessions', 0700, true);
$fichierHisto = $temp . '/historique.json';
$journal = $temp . '/erreurs.log';
$vraiHisto = $racine . '/historique.json';
$empreinteAvant = is_file($vraiHisto) ? hash_file('sha256', $vraiHisto) : null;

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

// ───── Démarrage du serveur sur un port libre ─────
$sonde = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr((string) stream_socket_get_name($sonde, false), ':'), 1);
fclose($sonde);

$env = getenv() + [];
$env['QUIZ_HISTORIQUE'] = $fichierHisto;
$nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$serveur = proc_open(
    [PHP_BINARY, '-d', 'session.save_path=' . $temp . '/sessions', '-d', 'error_log=' . $journal, '-d', 'log_errors=1',
        '-S', '127.0.0.1:' . $port, '-t', $public, $public . '/index.php'],
    [0 => ['file', $nul, 'r'], 1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']],
    $tubes,
    $racine,
    $env
);
if (!is_resource($serveur)) {
    fwrite(STDERR, "Impossible de lancer le serveur intégré de PHP.\n");
    exit(1);
}
register_shutdown_function(static function () use ($serveur, $temp): void {
    proc_terminate($serveur);
    proc_close($serveur);
    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($fichiers as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($temp);
});
for ($essai = 0; $essai < 100; $essai++) {
    $connexion = @fsockopen('127.0.0.1', $port, $code, $message, 0.2);
    if ($connexion !== false) {
        fclose($connexion);
        break;
    }
    usleep(50_000);
}

// ───── Petit client HTTP (une connexion par requête, cookies conservés comme dans un navigateur) ─────
$cookies = [];

/**
 * @param array<string,string> $entetesEnPlus en-têtes ajoutés à la requête (If-None-Match…)
 * @return array{statut: int, entetes: array<string, list<string>>, corps: string}
 */
function requete(string $methode, string $chemin, array $champs = [], array $entetesEnPlus = []): array
{
    global $port, $cookies;
    $corps = $champs === [] ? '' : http_build_query($champs);
    $tete = "$methode $chemin HTTP/1.0\r\nHost: 127.0.0.1:$port\r\n";
    foreach ($entetesEnPlus as $nom => $valeur) {
        $tete .= "$nom: $valeur\r\n";
    }
    if ($cookies !== []) {
        $tete .= 'Cookie: ' . implode('; ', array_map(static fn (string $k, string $v): string => "$k=$v", array_keys($cookies), $cookies)) . "\r\n";
    }
    if ($methode === 'POST') {
        $tete .= "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($corps) . "\r\n";
    }
    $flux = fsockopen('127.0.0.1', $port, $code, $message, 5);
    if ($flux === false) {
        return ['statut' => 0, 'entetes' => [], 'corps' => ''];
    }
    fwrite($flux, $tete . "\r\n" . $corps);
    $reponse = (string) stream_get_contents($flux);
    fclose($flux);

    [$enTete, $contenu] = explode("\r\n\r\n", $reponse, 2) + [1 => ''];
    $lignes = explode("\r\n", $enTete);
    preg_match('~^HTTP/\S+ (\d{3})~', (string) array_shift($lignes), $m);
    $entetes = [];
    foreach ($lignes as $ligne) {
        [$nom, $valeur] = explode(':', $ligne, 2) + [1 => ''];
        $entetes[strtolower(trim($nom))][] = trim($valeur);
    }
    foreach ($entetes['set-cookie'] ?? [] as $c) {
        [$paire] = explode(';', $c, 2);
        [$k, $v] = explode('=', $paire, 2) + [1 => ''];
        if ($v === '' || $v === 'deleted') {
            unset($cookies[$k]);
        } else {
            $cookies[$k] = $v;
        }
    }

    return ['statut' => (int) ($m[1] ?? 0), 'entetes' => $entetes, 'corps' => $contenu];
}

function entete(array $r, string $nom): string
{
    return $r['entetes'][strtolower($nom)][0] ?? '';
}

function jeton(string $html): string
{
    return preg_match('/name="csrf" value="([0-9a-f]{64})"/', $html, $m) === 1 ? $m[1] : '';
}

/** Aucune page ne doit contenir de style ni de script en ligne (la politique CSP les bloquerait). */
$pagesSansEnLigne = 0;
$pagesVues = 0;
function controlerPage(array $r): void
{
    global $pagesSansEnLigne, $pagesVues;
    if (!str_contains(entete($r, 'Content-Type'), 'text/html')) {
        return;
    }
    $pagesVues++;
    $pagesSansEnLigne += preg_match('/\sstyle=|<style|<script(?![^>]*\ssrc=)|\son[a-z]+=/i', $r['corps']) === 0 ? 1 : 0;
}

// ───── Accueil, en-têtes de sécurité, fichiers statiques ─────
$accueil = requete('GET', '/');
controlerPage($accueil);
verifier('GET / : 200', $accueil['statut'], 200);
verifier('accueil : titre', str_contains($accueil['corps'], 'Apprends et révise PHP'), true);
verifier('accueil : 20 questions par défaut', (bool) preg_match('~name="n" value="20" checked~', $accueil['corps']), true);
verifier('accueil : les 28 chapitres sont proposés', substr_count($accueil['corps'], 'type="checkbox" name="chapitres[]"'), 28);
verifier('accueil : partie rapide en un clic, au-dessus de la configuration', strpos($accueil['corps'], 'Partie rapide') < strpos($accueil['corps'], 'id="configurer"'), true);
verifier('accueil : configuration en trois étapes et bouton « Commencer »', [substr_count($accueil['corps'], 'class="etape-num"'), str_contains($accueil['corps'], 'class="barre-depart"')], [3, true]);
verifier('accueil : révision indisponible sans erreur à revoir', (bool) preg_match('~value="revision"[^>]*disabled~', $accueil['corps']), true);
verifier('en-tête : un seul bouton de thème (passe au thème suivant)', [substr_count($accueil['corps'], 'name="theme"'), str_contains($accueil['corps'], 'name="theme" value="clair"')], [1, true]);
verifier('accueil : mode série et minuteur proposés', [str_contains($accueil['corps'], 'value="serie"'), str_contains($accueil['corps'], 'name="chrono"')], [true, true]);
verifier('accueil : ressources versionnées pour le cache du navigateur', (bool) preg_match('~href="/assets/style\.css\?v=[0-9a-f]+"~', $accueil['corps']), true);
verifier('accueil : tuiles par thème (affichées par JavaScript), liste des chapitres ouverte sans JavaScript', [substr_count($accueil['corps'], 'data-tuile="'), str_contains($accueil['corps'], 'data-tuiles hidden'), str_contains($accueil['corps'], 'data-choix-chapitres open')], [6, true, true]);
verifier('CSP : aucun script ni style en ligne autorisé', str_contains(entete($accueil, 'Content-Security-Policy'), "script-src 'self'; style-src 'self'"), true);
verifier('en-têtes : nosniff et refus des cadres', [entete($accueil, 'X-Content-Type-Options'), entete($accueil, 'X-Frame-Options')], ['nosniff', 'DENY']);
verifier('en-têtes : X-Powered-By retiré', entete($accueil, 'X-Powered-By'), '');
verifier('cookie de session HttpOnly et SameSite', (bool) preg_match('/QUIZSESSID=[^;]+;.*HttpOnly.*SameSite=Lax/i', implode("\n", $accueil['entetes']['set-cookie'] ?? [])), true);
$csrf = jeton($accueil['corps']);
verifier('accueil : jeton CSRF présent', strlen($csrf), 64);

$css = requete('GET', '/assets/style.css');
verifier('GET /assets/style.css : 200 en text/css', [$css['statut'], str_starts_with(entete($css, 'Content-Type'), 'text/css')], [200, true]);
verifier('ressource non versionnée : revalidée à chaque fois, avec un ETag', [entete($css, 'Cache-Control'), entete($css, 'ETag') !== ''], ['no-cache', true]);
$cssVersionne = requete('GET', '/assets/style.css?v=1');
verifier('ressource versionnée : gardée un an par le navigateur', entete($cssVersionne, 'Cache-Control'), 'public, max-age=31536000, immutable');
$revalidation = requete('GET', '/assets/app.js', [], ['If-None-Match' => entete(requete('GET', '/assets/app.js'), 'ETag')]);
verifier('ressource inchangée : 304 sans contenu', [$revalidation['statut'], $revalidation['corps']], [304, '']);
$icone = requete('GET', '/assets/favicon.svg');
verifier('GET /assets/favicon.svg : 200', $icone['statut'], 200);
$traversee = requete('GET', '/assets/../src/bootstrap.php');
verifier('remontée de dossier refusée : 404, aucun code source servi', [$traversee['statut'], str_contains($traversee['corps'], '<?php')], [404, false]);

// ───── Erreurs : 404, 405, 403, 422 ─────
verifier('page inconnue : 404', requete('GET', '/nexiste-pas')['statut'], 404);
$put = requete('PUT', '/');
verifier('méthode non prévue : 405 avec en-tête Allow', [$put['statut'], entete($put, 'Allow')], [405, 'GET']);
verifier('POST sans jeton CSRF : 403', requete('POST', '/partie', ['mode' => 'normal', 'n' => '5', 'chapitres' => ['24']])['statut'], 403);
$invalide = requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'normal', 'n' => '7', 'chapitres' => ['24']]);
controlerPage($invalide);
verifier('options invalides : 422 et formulaire réaffiché avec le message', [$invalide['statut'], str_contains($invalide['corps'], 'Choisis un nombre de questions')], [422, true]);
$chronoInvalide = requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'normal', 'n' => '5', 'chrono' => '7', 'chapitres' => ['24']]);
verifier('minuteur inconnu : 422', [$chronoInvalide['statut'], str_contains($chronoInvalide['corps'], 'minuteur')], [422, true]);

// ───── Fiches de révision ─────
$sommaire = requete('GET', '/fiches');
controlerPage($sommaire);
verifier('fiches : sommaire des 28 chapitres', [$sommaire['statut'], substr_count($sommaire['corps'], 'href="/fiches?ch=')], [200, 28]);
$fiche = requete('GET', '/fiches?ch=08');
controlerPage($fiche);
verifier('fiches : un chapitre avec ses questions, réponses et explications', [$fiche['statut'], substr_count($fiche['corps'], '<details class="detail fiche"') >= 12, str_contains($fiche['corps'], 'Classes et objets')], [200, true, true]);
$recherche = requete('GET', '/fiches?q=' . rawurlencode('password_hash'));
controlerPage($recherche);
verifier('fiches : recherche par mot-clé', [$recherche['statut'], str_contains($recherche['corps'], 'fiche(s) pour « password_hash »'), str_contains($recherche['corps'], '<details class="detail fiche"')], [200, true, true]);
$piegeRecherche = requete('GET', '/fiches?q=' . rawurlencode('<script>alert(1)</script>'));
verifier('fiches : la recherche est échappée', [$piegeRecherche['statut'], str_contains($piegeRecherche['corps'], '<script>alert(1)</script>')], [200, false]);
verifier('fiches : chapitre inconnu ou falsifié → 404', [requete('GET', '/fiches?ch=99')['statut'], requete('GET', '/fiches?ch[]=05')['statut']], [404, 404]);
verifier('fiches : lien vers la leçon du chapitre', str_contains($fiche['corps'], 'href="/cours?ch=08"'), true);

// ───── Cours ─────
verifier('en-tête : lien « Cours »', str_contains($accueil['corps'], '<a href="/cours">Cours</a>'), true);
verifier('accueil : carte « Apprendre » qui conseille le premier chapitre', [str_contains($accueil['corps'], 'class="carte apprendre"'), str_contains($accueil['corps'], 'href="/cours?ch=00"')], [true, true]);
$sommaireCours = requete('GET', '/cours');
controlerPage($sommaireCours);
verifier('cours : sommaire des 28 leçons, lien actif dans le menu', [$sommaireCours['statut'], substr_count($sommaireCours['corps'], 'class="lien-cours" href="/cours?ch='), str_contains($sommaireCours['corps'], '<a href="/cours" aria-current="page">Cours</a>')], [200, 28, true]);
verifier('cours : sans historique, on commence par le chapitre 00', [str_contains($sommaireCours['corps'], 'Commencer le cours'), str_contains($sommaireCours['corps'], '<li class="conseille">')], [true, true]);
$lecon = requete('GET', '/cours?ch=08');
controlerPage($lecon);
verifier('cours : une leçon (titre, plan de la page, code coloré, encadrés)', [
    $lecon['statut'], str_contains($lecon['corps'], 'Classes et objets'), str_contains($lecon['corps'], 'class="sommaire-lecon"'),
    str_contains($lecon['corps'], '<div class="bloc-code" data-langue="PHP"><pre class="code"><code><span class="c-'), str_contains($lecon['corps'], 'class="encadre encadre-retenir"'),
], [200, true, true, true, true]);
verifier('cours : chapitres voisins et test du chapitre en fin de leçon', [str_contains($lecon['corps'], 'href="/cours?ch=07" rel="prev"'), str_contains($lecon['corps'], 'href="/cours?ch=09" rel="next"'), str_contains($lecon['corps'], 'name="chapitres[]" value="08"')], [true, true, true]);
verifier('cours : le thème garde la leçon affichée', str_contains($lecon['corps'], 'name="retour" value="/cours?ch=08"'), true);
$leconJs = requete('GET', '/cours?ch=26');
controlerPage($leconJs);
// seuls vrais formulaires : le thème (en-tête) et « Teste-toi » ; le <form> des exemples est du texte coloré
verifier('cours : le HTML montré dans les exemples est affiché, jamais interprété', [$leconJs['statut'], substr_count($leconJs['corps'], '<script'), substr_count($leconJs['corps'], '<form'), str_contains($leconJs['corps'], '<span class="c-tag">&lt;form</span>')], [200, 1, 2, true]);
$premiere = requete('GET', '/cours?ch=00');
$derniere = requete('GET', '/cours?ch=27');
verifier('cours : pas de chapitre avant le premier ni après le dernier', [str_contains($premiere['corps'], 'rel="prev"'), str_contains($premiere['corps'], 'rel="next"'), str_contains($derniere['corps'], 'rel="prev"'), str_contains($derniere['corps'], 'rel="next"')], [false, true, true, false]);
verifier('cours : chapitre inconnu ou falsifié → 404', [
    requete('GET', '/cours?ch=99')['statut'], requete('GET', '/cours?ch[]=08')['statut'], requete('GET', '/cours?ch=8')['statut'], requete('GET', '/cours?ch=' . rawurlencode('../moteur'))['statut'],
], [404, 404, 404, 404]);
verifier('cours : POST refusé (405)', requete('POST', '/cours', ['csrf' => $csrf])['statut'], 405);

// ───── Une partie complète (JavaScript), avec erreurs volontaires ─────
$creation = requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'normal', 'n' => '5', 'chapitres' => ['24', '25', '26', '27'], 'graine' => '2024']);
verifier('création de partie : 303 vers /question (Post/Redirect/Get)', [$creation['statut'], entete($creation, 'Location')], [303, '/question']);
$sansAffichage = requete('POST', '/repondre', ['csrf' => $csrf, 'numero' => '1', 'action' => 'passer']);
verifier('réponse envoyée sans avoir affiché la question : refusée (303 vers /question)', [$sansAffichage['statut'], entete($sansAffichage, 'Location')], [303, '/question']);

$repondues = 0;
$numeroPrecedent = null;
for ($tour = 0; $tour < 10; $tour++) {
    $page = requete('GET', '/question');
    controlerPage($page);
    if ($page['statut'] !== 200) {
        break;
    }
    preg_match('/name="numero" value="(\d+)"/', $page['corps'], $m);
    preg_match('/data-type="(qcm|multi|saisie)"/', $page['corps'], $t);
    $numero = $m[1] ?? '';
    $champs = ['csrf' => $csrf, 'numero' => $numero, 'action' => 'valider'];
    if ($tour === 0) {
        $champs['action'] = 'passer';
    } elseif (($t[1] ?? '') === 'saisie') {
        $champs['texte'] = 'réponse fausse zzz';
    } elseif (($t[1] ?? '') === 'multi') {
        $champs['choix'] = ['0'];
    } else {
        $champs['choix'] = '0';
    }
    $envoi = requete('POST', '/repondre', $champs);
    $repondues++;
    if ($tour === 1) {
        verifier('code coloré côté serveur dans les questions JavaScript', str_contains($page['corps'], 'class="c-') || !str_contains($page['corps'], '<pre class="code">'), true);
        $double = requete('POST', '/repondre', $champs);
        verifier('double envoi de la même réponse : ignoré (303, pas comptée deux fois)', $double['statut'], 303);
    }
    if (entete($envoi, 'Location') === '/correction') {
        $correction = requete('GET', '/correction');
        controlerPage($correction);
        if ($tour === 0) {
            verifier('correction : 200 avec explication et conseil de révision', [$correction['statut'], str_contains($correction['corps'], 'Explication'), str_contains($correction['corps'], 'À revoir : <a href="/cours?ch=2')], [200, true, true]);
        }
    }
    $numeroPrecedent = $numero;
}
verifier('les 5 questions ont été traitées', $repondues, 5);

$bilan = requete('GET', '/bilan');
controlerPage($bilan);
verifier('bilan : 200', $bilan['statut'], 200);
verifier('bilan : bouton « Refaire mes erreurs »', (bool) preg_match('/Refaire (mes \d+ erreurs|mon erreur)/', $bilan['corps']), true);
verifier('bilan : leçons à relire (et supports de référence) cités', [str_contains($bilan['corps'], 'À relire : <a href="/cours?ch=2'), str_contains($bilan['corps'], 'Supports de référence')], [true, true]);
verifier('bilan : prochaine étape conseillée en tête', [str_contains($bilan['corps'], 'Et maintenant ?'), strpos($bilan['corps'], 'Et maintenant ?') < strpos($bilan['corps'], 'Détail des questions')], [true, true]);
verifier('historique temporaire : une partie enregistrée', count(json_decode((string) @file_get_contents($fichierHisto), true)['sessions'] ?? []), 1);
$accueilApres = requete('GET', '/');
verifier('accueil : les réglages de la dernière partie sont repris', [
    (bool) preg_match('~value="24"[^>]*checked~', $accueilApres['corps']),
    (bool) preg_match('~value="00"[^>]*checked~', $accueilApres['corps']),
    (bool) preg_match('~name="n" value="5" checked~', $accueilApres['corps']),
], [true, false, true]);

// ───── Refaire ses erreurs ─────
$csrf = jeton($bilan['corps']) ?: $csrf;
$erreurs = requete('POST', '/erreurs', ['csrf' => $csrf]);
verifier('refaire ses erreurs : 303 vers /question', [$erreurs['statut'], entete($erreurs, 'Location')], [303, '/question']);
$rattrapage = requete('GET', '/question');
controlerPage($rattrapage);
verifier('rattrapage : badge affiché', str_contains($rattrapage['corps'], 'Rattrapage de la partie n°1'), true);
preg_match('/name="numero" value="(\d+)"/', $rattrapage['corps'], $m);
requete('POST', '/repondre', ['csrf' => $csrf, 'numero' => $m[1] ?? '1', 'action' => 'passer']);
$fin = requete('POST', '/abandonner', ['csrf' => $csrf]);
verifier('abandon : 303 vers /bilan', [$fin['statut'], entete($fin, 'Location')], [303, '/bilan']);
$bilanRattrapage = requete('GET', '/bilan');
controlerPage($bilanRattrapage);
verifier('bilan du rattrapage : origine indiquée', str_contains($bilanRattrapage['corps'], 'rattrapage des erreurs de la partie n°1'), true);
verifier('historique temporaire : deux parties enregistrées', count(json_decode((string) @file_get_contents($fichierHisto), true)['sessions'] ?? []), 2);

// ───── Minuteur : à zéro, la réponse part avec action=temps et compte comme une erreur ─────
requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'normal', 'n' => '5', 'chrono' => '15', 'chapitres' => ['01', '02'], 'graine' => '77']);
$questionChrono = requete('GET', '/question');
controlerPage($questionChrono);
verifier('minuteur : affiché avec sa durée', [str_contains($questionChrono['corps'], 'data-chrono'), str_contains($questionChrono['corps'], 'data-limite="15"')], [true, true]);
preg_match('/name="numero" value="(\d+)"/', $questionChrono['corps'], $m);
$tempsEcoule = requete('POST', '/repondre', ['csrf' => $csrf, 'numero' => $m[1] ?? '1', 'action' => 'temps']);
verifier('minuteur à zéro : réponse enregistrée, puis correction', [$tempsEcoule['statut'], entete($tempsEcoule, 'Location')], [303, '/correction']);
$correctionTemps = requete('GET', '/correction');
controlerPage($correctionTemps);
verifier('correction : verdict « Temps écoulé »', [$correctionTemps['statut'], str_contains($correctionTemps['corps'], 'Temps écoulé')], [200, true]);
requete('POST', '/abandonner', ['csrf' => $csrf]);
$bilanTemps = requete('GET', '/bilan');
controlerPage($bilanTemps);
verifier('bilan : la réponse hors délai est comptée', (bool) preg_match('~<strong>1</strong> hors délai~', $bilanTemps['corps']), true);

// ───── Mode série : la partie s'arrête à la première réponse qui n'est pas juste ─────
requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'serie', 'n' => '15', 'chapitres' => ['08', '09'], 'graine' => '88']);
$questionSerie = requete('GET', '/question');
controlerPage($questionSerie);
verifier('série : compteur affiché, pas de bouton « Passer »', [str_contains($questionSerie['corps'], 'Bonnes réponses : 0'), str_contains($questionSerie['corps'], 'value="passer"')], [true, false]);
preg_match('/name="numero" value="(\d+)"/', $questionSerie['corps'], $m);
$finSerie = requete('POST', '/repondre', ['csrf' => $csrf, 'numero' => $m[1] ?? '1', 'action' => 'passer']);
verifier('série : terminée dès la première réponse non juste', entete($finSerie, 'Location'), '/correction');
$bilanSerie = requete('GET', '/bilan');
controlerPage($bilanSerie);
verifier('série : bilan de la série et bouton « Nouvelle série »', [$bilanSerie['statut'], str_contains($bilanSerie['corps'], 'Bilan de la série'), str_contains($bilanSerie['corps'], 'Nouvelle série')], [200, true, true]);
$sessionsEnregistrees = json_decode((string) @file_get_contents($fichierHisto), true)['sessions'] ?? [];
verifier('historique : mode et minuteur enregistrés avec chaque partie', [count($sessionsEnregistrees), $sessionsEnregistrees[2]['chrono'] ?? null, $sessionsEnregistrees[3]['mode'] ?? null], [4, 15, 'serie']);

// ───── Statistiques, sources, thème ─────
$stats = requete('GET', '/stats');
controlerPage($stats);
verifier('statistiques : 200 avec le graphique de progression', [$stats['statut'], substr_count($stats['corps'], 'class="graphe-svg')], [200, 2]);
verifier('statistiques : tableau des parties (équivalent accessible du graphique)', str_contains($stats['corps'], 'Détail des parties (tableau)'), true);
verifier('statistiques : record de série et temps moyen', [str_contains($stats['corps'], 'bonnes réponses d\'affilée (record)'), str_contains($stats['corps'], 'Temps moyen par réponse')], [true, true]);
$sources = requete('GET', '/sources');
controlerPage($sources);
verifier('sources : 200, auteur et licence cités', [$sources['statut'], str_contains($sources['corps'], 'Térence FERUT'), str_contains($sources['corps'], 'CC BY-SA 4.0')], [200, true, true]);

$theme = requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'sombre', 'retour' => '/stats']);
verifier('thème : 303 vers la page d\'origine, cookie posé', [$theme['statut'], entete($theme, 'Location'), $cookies['quiz_theme'] ?? null], [303, '/stats', 'sombre']);
$ouverte = requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'clair', 'retour' => 'https://exemple.invalid/']);
verifier('thème : pas de redirection ouverte vers un autre site', entete($ouverte, 'Location'), '/');
verifier('thème inconnu : 400', requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'rose'])['statut'], 400);
verifier('thème : retour sur la leçon en cours, chapitre revalidé', [
    entete(requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'clair', 'retour' => '/cours?ch=08']), 'Location'),
    entete(requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'clair', 'retour' => '/cours?ch=99']), 'Location'),
    entete(requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'clair', 'retour' => '/stats?ch=08']), 'Location'),
], ['/cours?ch=08', '/cours', '/stats']);
verifier('le thème choisi est appliqué', str_contains(requete('GET', '/sources')['corps'], 'data-theme="clair"'), true);

// ───── « Teste-toi » à la fin d'une leçon : 10 questions sur ce seul chapitre ─────
preg_match('~<section class="carte teste-toi".*?</form>~s', requete('GET', '/cours?ch=09')['corps'], $formulaireTest);
preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)">~', $formulaireTest[0] ?? '', $champsTest, PREG_SET_ORDER);
$envoiTest = [];
foreach ($champsTest as [, $nom, $valeur]) {
    $nom === 'chapitres[]' ? $envoiTest['chapitres'][] = $valeur : $envoiTest[$nom] = $valeur;
}
verifier('cours : le formulaire « Teste-toi » envoie le chapitre, 10 questions et le jeton', [$envoiTest['chapitres'] ?? null, $envoiTest['n'] ?? null, strlen($envoiTest['csrf'] ?? '')], [['09'], '10', 64]);
$test = requete('POST', '/partie', $envoiTest);
verifier('cours : « Teste-toi » lance la partie (303 vers /question)', [$test['statut'], entete($test, 'Location')], [303, '/question']);
$questionTest = requete('GET', '/question');
controlerPage($questionTest);
verifier('cours : la partie porte sur le chapitre de la leçon', [$questionTest['statut'], str_contains($questionTest['corps'], 'Héritage et polymorphisme'), str_contains($questionTest['corps'], 'sur 10')], [200, true, true]);

// ───── Partie rapide : un préréglage qui ne remplace pas les réglages par défaut de l'accueil ─────
$champsRapides = ['csrf' => $csrf, 'mode' => 'normal', 'n' => '10', 'chrono' => '0', 'rapide' => '1', 'chapitres' => array_map(static fn (int $i): string => sprintf('%02d', $i), range(0, 27))];
$rapide = requete('POST', '/partie', $champsRapides);
verifier('partie rapide : 303 vers /question', [$rapide['statut'], entete($rapide, 'Location')], [303, '/question']);
$accueilRapide = requete('GET', '/');
verifier('partie rapide : l\'accueil propose « Reprendre » et garde 20 questions par défaut', [str_contains($accueilRapide['corps'], 'Reprendre ma partie'), (bool) preg_match('~name="n" value="20" checked~', $accueilRapide['corps'])], [true, true]);

// ───── Bilan global ─────
verifier("aucun style ni script en ligne dans les $pagesVues pages HTML visitées", $pagesSansEnLigne, $pagesVues);
$lignesJournal = is_file($journal) ? array_values(array_filter(file($journal, FILE_IGNORE_NEW_LINES) ?: [], static fn (string $l): bool => trim($l) !== '')) : [];
verifier('aucune erreur, aucun avertissement PHP côté serveur', $lignesJournal, []);
verifier('ton historique.json réel n\'a pas été modifié', is_file($vraiHisto) ? hash_file('sha256', $vraiHisto) : null, $empreinteAvant);

echo "\n";
echo $echecs === 0 ? "Tous les tests de bout en bout passent ($total).\n" : "$echecs échec(s) sur $total tests.\n";
exit($echecs === 0 ? 0 : 1);
