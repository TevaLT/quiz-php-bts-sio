<?php

declare(strict_types=1);

/**
 * Initialisation commune : sécurité (en-têtes, session), erreurs, chargement du moteur et du site.
 * Chargé en premier par public/index.php.
 */

define('RACINE_QUIZ', dirname(RACINE_SITE));

require RACINE_QUIZ . '/moteur.php';
require __DIR__ . '/http.php';
require __DIR__ . '/vues.php';
require __DIR__ . '/partie.php';
require __DIR__ . '/stats.php';
require __DIR__ . '/fiches.php';
require __DIR__ . '/cours.php';
require __DIR__ . '/pages.php';

date_default_timezone_set('Europe/Paris');

// Les erreurs vont dans les journaux du serveur, jamais dans la page (pas de fuite d'information).
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    error_log('[quiz] ' . $e::class . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    if (headers_sent()) {
        return;
    }
    $detail = getenv('QUIZ_DEBUG') === '1' ? $e::class . ' : ' . $e->getMessage() : 'Une erreur inattendue est survenue. Le détail est dans les journaux du serveur.';
    try {
        erreurHttp(500, 'Erreur du serveur', $detail);
    } catch (Throwable) {
        http_response_code(500);
        echo 'Erreur du serveur.';
    }
});

// En-têtes de sécurité : aucun script ni style en ligne, aucune ressource externe, pas d'affichage dans un cadre.
header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store'); // les pages dépendent de l'état de la partie : jamais de cache

// Session : cookie réservé au serveur (HttpOnly), non envoyé depuis un autre site (SameSite), HTTPS seulement si disponible.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_cache_limiter(''); // sinon PHP remplace notre en-tête Cache-Control
session_name('QUIZSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => estHttps(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
