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

/** @return array{statut: int, entetes: array<string, list<string>>, corps: string} */
function requete(string $methode, string $chemin, array $champs = []): array
{
    global $port, $cookies;
    $corps = $champs === [] ? '' : http_build_query($champs);
    $tete = "$methode $chemin HTTP/1.0\r\nHost: 127.0.0.1:$port\r\n";
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
verifier('accueil : nouveau titre', str_contains($accueil['corps'], 'Révise PHP, SQL, les API et JavaScript'), true);
verifier('accueil : les 30 chapitres sont proposés', substr_count($accueil['corps'], 'name="chapitres[]"'), 30);
verifier('accueil : sélections rapides présentes (masquées sans JavaScript)', str_contains($accueil['corps'], 'data-preselections hidden'), true);
verifier('CSP : aucun script ni style en ligne autorisé', str_contains(entete($accueil, 'Content-Security-Policy'), "script-src 'self'; style-src 'self'"), true);
verifier('en-têtes : nosniff et refus des cadres', [entete($accueil, 'X-Content-Type-Options'), entete($accueil, 'X-Frame-Options')], ['nosniff', 'DENY']);
verifier('en-têtes : X-Powered-By retiré', entete($accueil, 'X-Powered-By'), '');
verifier('cookie de session HttpOnly et SameSite', (bool) preg_match('/QUIZSESSID=[^;]+;.*HttpOnly.*SameSite=Lax/i', implode("\n", $accueil['entetes']['set-cookie'] ?? [])), true);
$csrf = jeton($accueil['corps']);
verifier('accueil : jeton CSRF présent', strlen($csrf), 64);

$css = requete('GET', '/assets/style.css');
verifier('GET /assets/style.css : 200 en text/css', [$css['statut'], str_starts_with(entete($css, 'Content-Type'), 'text/css')], [200, true]);
$icone = requete('GET', '/assets/favicon.svg');
verifier('GET /assets/favicon.svg : 200', $icone['statut'], 200);
$traversee = requete('GET', '/assets/../src/bootstrap.php');
verifier('remontée de dossier refusée : 404, aucun code source servi', [$traversee['statut'], str_contains($traversee['corps'], '<?php')], [404, false]);

// ───── Erreurs : 404, 405, 403, 422 ─────
verifier('page inconnue : 404', requete('GET', '/nexiste-pas')['statut'], 404);
$put = requete('PUT', '/');
verifier('méthode non prévue : 405 avec en-tête Allow', [$put['statut'], entete($put, 'Allow')], [405, 'GET']);
verifier('POST sans jeton CSRF : 403', requete('POST', '/partie', ['mode' => 'normal', 'n' => '5', 'chapitres' => ['27']])['statut'], 403);
$invalide = requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'normal', 'n' => '7', 'chapitres' => ['27']]);
controlerPage($invalide);
verifier('options invalides : 422 et formulaire réaffiché avec le message', [$invalide['statut'], str_contains($invalide['corps'], 'Choisis un nombre de questions')], [422, true]);

// ───── Une partie complète (JavaScript), avec erreurs volontaires ─────
$creation = requete('POST', '/partie', ['csrf' => $csrf, 'mode' => 'normal', 'n' => '5', 'chapitres' => ['27', '28', '29'], 'graine' => '2024']);
verifier('création de partie : 303 vers /question (Post/Redirect/Get)', [$creation['statut'], entete($creation, 'Location')], [303, '/question']);

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
            verifier('correction : 200 avec explication et conseil de révision', [$correction['statut'], str_contains($correction['corps'], 'Explication'), str_contains($correction['corps'], 'À revoir : chapitre 2')], [200, true, true]);
        }
    }
    $numeroPrecedent = $numero;
}
verifier('les 5 questions ont été traitées', $repondues, 5);

$bilan = requete('GET', '/bilan');
controlerPage($bilan);
verifier('bilan : 200', $bilan['statut'], 200);
verifier('bilan : bouton « Refaire mes erreurs »', (bool) preg_match('/Refaire mes \d+ erreurs?/', $bilan['corps']), true);
verifier('bilan : cours à relire cités', str_contains($bilan['corps'], 'Cours concernés'), true);
verifier('historique temporaire : une partie enregistrée', count(json_decode((string) @file_get_contents($fichierHisto), true)['sessions'] ?? []), 1);

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

// ───── Statistiques, sources, thème ─────
$stats = requete('GET', '/stats');
controlerPage($stats);
verifier('statistiques : 200 avec le graphique de progression', [$stats['statut'], substr_count($stats['corps'], 'class="graphe-svg')], [200, 2]);
verifier('statistiques : tableau des parties (équivalent accessible du graphique)', str_contains($stats['corps'], 'Détail des parties (tableau)'), true);
$sources = requete('GET', '/sources');
controlerPage($sources);
verifier('sources : 200, auteur et licence cités', [$sources['statut'], str_contains($sources['corps'], 'Térence FERUT'), str_contains($sources['corps'], 'CC BY-SA 4.0')], [200, true, true]);

$theme = requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'sombre', 'retour' => '/stats']);
verifier('thème : 303 vers la page d\'origine, cookie posé', [$theme['statut'], entete($theme, 'Location'), $cookies['quiz_theme'] ?? null], [303, '/stats', 'sombre']);
$ouverte = requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'clair', 'retour' => 'https://exemple.invalid/']);
verifier('thème : pas de redirection ouverte vers un autre site', entete($ouverte, 'Location'), '/');
verifier('thème inconnu : 400', requete('POST', '/theme', ['csrf' => $csrf, 'theme' => 'rose'])['statut'], 400);
verifier('le thème choisi est appliqué', str_contains(requete('GET', '/sources')['corps'], 'data-theme="clair"'), true);

// ───── Bilan global ─────
verifier("aucun style ni script en ligne dans les $pagesVues pages HTML visitées", $pagesSansEnLigne, $pagesVues);
$lignesJournal = is_file($journal) ? array_values(array_filter(file($journal, FILE_IGNORE_NEW_LINES) ?: [], static fn (string $l): bool => trim($l) !== '')) : [];
verifier('aucune erreur, aucun avertissement PHP côté serveur', $lignesJournal, []);
verifier('ton historique.json réel n\'a pas été modifié', is_file($vraiHisto) ? hash_file('sha256', $vraiHisto) : null, $empreinteAvant);

echo "\n";
echo $echecs === 0 ? "Tous les tests de bout en bout passent ($total).\n" : "$echecs échec(s) sur $total tests.\n";
exit($echecs === 0 ? 0 : 1);
