<?php

declare(strict_types=1);

/**
 * Point d'entrée unique du site (front controller).
 *
 *   php -S localhost:8000 -t site/public site/public/index.php     (depuis la racine du dépôt)
 */

// Serveur intégré de PHP : les fichiers du dossier assets/ sont servis directement (et seulement eux), avec des
// en-têtes de cache. Une URL versionnée (?v=…, voir urlRessource()) est gardée un an par le navigateur ; sinon,
// il revalide avec l'ETag et reçoit un 304 sans contenu si le fichier n'a pas changé.
if (PHP_SAPI === 'cli-server') {
    $demande = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($demande) && str_starts_with($demande, '/assets/')) {
        $fichier = realpath(__DIR__ . $demande);
        $assets = realpath(__DIR__ . '/assets');
        if ($fichier !== false && $assets !== false && str_starts_with($fichier, $assets . DIRECTORY_SEPARATOR) && is_file($fichier)) {
            $types = ['css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon'];
            $type = $types[strtolower(pathinfo($fichier, PATHINFO_EXTENSION))] ?? null;
            if ($type === null) {
                return false; // type inconnu : le serveur intégré s'en charge
            }
            $etag = '"' . dechex((int) filemtime($fichier)) . '-' . dechex((int) filesize($fichier)) . '"';
            header_remove('X-Powered-By');
            header('Content-Type: ' . $type);
            header('X-Content-Type-Options: nosniff');
            header('ETag: ' . $etag);
            header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
            if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
                http_response_code(304);

                return true;
            }
            header('Content-Length: ' . filesize($fichier));
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
                readfile($fichier);
            }

            return true;
        }
    }
}

define('RACINE_SITE', dirname(__DIR__));
require RACINE_SITE . '/src/bootstrap.php';

// Table de routage : chemin → méthode HTTP → fonction (définie dans src/pages.php).
$routes = [
    '/' => ['GET' => 'pageAccueil'],
    '/partie' => ['POST' => 'actionCreerPartie'],
    '/question' => ['GET' => 'pageQuestion'],
    '/repondre' => ['POST' => 'actionRepondre'],
    '/correction' => ['GET' => 'pageCorrection'],
    '/abandonner' => ['POST' => 'actionAbandonner'],
    '/bilan' => ['GET' => 'pageBilan'],
    '/erreurs' => ['POST' => 'actionRefaireErreurs'],
    '/stats' => ['GET' => 'pageStats'],
    '/cours' => ['GET' => 'pageCours'],
    '/fiches' => ['GET' => 'pageFiches'],
    '/sources' => ['GET' => 'pageSources'],
    '/reinitialiser' => ['GET' => 'pageReinitialiser', 'POST' => 'actionReinitialiser'],
    '/theme' => ['POST' => 'actionTheme'],
];

$chemin = cheminDemande();
if (!isset($routes[$chemin])) {
    erreurHttp(404, 'Page introuvable', "Cette page n'existe pas.");
}

$methode = methodeHttp() === 'HEAD' ? 'GET' : methodeHttp(); // HEAD = GET sans corps
$gestionnaire = $routes[$chemin][$methode] ?? null;
if ($gestionnaire === null) {
    header('Allow: ' . implode(', ', array_keys($routes[$chemin])));
    erreurHttp(405, 'Méthode non autorisée', 'Cette page ne répond pas à cette méthode HTTP.');
}

$gestionnaire();
