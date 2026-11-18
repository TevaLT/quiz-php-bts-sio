<?php

declare(strict_types=1);

/**
 * Outils HTTP du site : échappement, redirections, CSRF, messages flash, thème, rendu des gabarits.
 * Tout ce qui touche aux superglobales est ici ou dans pages.php — jamais dans partie.php.
 */

const THEMES = ['auto' => 'Auto', 'clair' => 'Clair', 'sombre' => 'Sombre'];

/** Pages vers lesquelles on peut revenir après un changement de thème (liste blanche : pas de redirection ouverte). */
const PAGES_RETOUR = ['/', '/stats', '/question', '/correction', '/bilan', '/sources', '/fiches'];

/**
 * URL d'une ressource statique, versionnée par sa date de modification (/assets/app.js?v=…) : le navigateur peut
 * la garder en cache longtemps, et une nouvelle version change l'URL.
 */
function urlRessource(string $chemin): string
{
    $fichier = RACINE_SITE . '/public' . $chemin;

    return $chemin . (is_file($fichier) ? '?v=' . dechex((int) filemtime($fichier)) : '');
}

/** Échappe une valeur pour l'insérer dans du HTML (à utiliser à CHAQUE sortie). */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function estHttps(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function methodeHttp(): string
{
    $methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    return is_string($methode) ? strtoupper($methode) : 'GET';
}

function cheminDemande(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $chemin = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);
    $chemin = is_string($chemin) ? rawurldecode($chemin) : '/';

    return $chemin === '/' ? '/' : rtrim($chemin, '/');
}

/** Redirection (303 par défaut : le navigateur poursuit en GET — motif Post/Redirect/Get). */
function rediriger(string $chemin, int $statut = 303): never
{
    header('Location: ' . $chemin, true, $statut);
    exit;
}

// ───────────────────────────── CSRF ─────────────────────────────

function jetonCsrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function champCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jetonCsrf()) . '">';
}

/** À appeler au début de chaque action POST : refuse (403) si le jeton est absent ou différent. */
function verifierCsrf(): void
{
    $recu = $_POST['csrf'] ?? null;
    $attendu = $_SESSION['csrf'] ?? '';
    if (!is_string($recu) || !is_string($attendu) || $attendu === '' || !hash_equals($attendu, $recu)) {
        erreurHttp(403, 'Requête refusée', 'Le jeton de sécurité est absent ou invalide (la session a peut-être expiré). Recharge la page puis réessaie.');
    }
}

// ───────────────────────────── Messages flash ─────────────────────────────

/** @param 'info'|'succes'|'erreur' $type */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> messages en attente, lus une seule fois */
function consommerFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($messages) ? $messages : [];
}

// ───────────────────────────── Thème (cookie validé par liste blanche) ─────────────────────────────

function themeActuel(): string
{
    $theme = $_COOKIE['quiz_theme'] ?? 'auto';

    return is_string($theme) && isset(THEMES[$theme]) ? $theme : 'auto';
}

/** Thème suivant dans le cycle Auto → Clair → Sombre → Auto (un seul bouton dans l'en-tête). */
function themeSuivant(string $theme): string
{
    $cles = array_keys(THEMES);
    $position = array_search($theme, $cles, true);

    return $cles[(is_int($position) ? $position + 1 : 0) % count($cles)];
}

function enregistrerTheme(string $theme): void
{
    setcookie('quiz_theme', $theme, [
        'expires' => time() + 180 * 86400,
        'path' => '/',
        'secure' => estHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ───────────────────────────── Rendu des gabarits ─────────────────────────────

/** Exécute un gabarit et retourne son HTML. Le nom du gabarit vient du code, jamais de la requête. */
function capturer(string $gabarit, array $donnees): string
{
    extract($donnees, EXTR_SKIP);
    ob_start();
    require RACINE_SITE . '/templates/' . $gabarit . '.php';

    return (string) ob_get_clean();
}

/** Rend une page complète (gabarit + layout) puis arrête le script. */
function rendre(string $gabarit, array $donnees = [], int $statut = 200): never
{
    $donnees += ['titre' => 'Quiz BTS SIO SLAM', 'page' => ''];
    $contenu = capturer($gabarit, $donnees);
    http_response_code($statut);
    header('Content-Type: text/html; charset=utf-8');
    echo capturer('layout', $donnees + ['contenu' => $contenu]);
    exit;
}

function erreurHttp(int $statut, string $titre, string $message): never
{
    rendre('erreur', ['titre' => $titre, 'code' => $statut, 'message' => $message], $statut);
}
