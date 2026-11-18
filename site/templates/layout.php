<?php
/** @var string $titre */
/** @var string $page */
/** @var string $contenu */
$theme = themeActuel();
$themeSuivant = themeSuivant($theme);
$iconesThemes = ['auto' => '◐', 'clair' => '☀', 'sombre' => '☾'];
$enCours = partieEnCours();
$retour = in_array(cheminDemande(), PAGES_RETOUR, true) ? cheminDemande() : '/';
$courant = static fn (string $nom): string => $page === $nom ? ' aria-current="page"' : '';
$flashs = consommerFlash();
?><!DOCTYPE html>
<html lang="fr" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Quiz pour apprendre et réviser PHP (bases, objet, web, PDO, MVC, API REST, JWT) et JavaScript : questions neuves à chaque partie, minuteur, mode série, correction expliquée, fiches de révision et suivi de la progression.">
    <title><?= e($titre) ?> · Quiz BTS SIO SLAM</title>
    <link rel="icon" href="<?= e(urlRessource('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(urlRessource('/assets/style.css')) ?>">
    <script src="<?= e(urlRessource('/assets/app.js')) ?>" defer></script>
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>

<header class="entete">
    <div class="conteneur entete-in">
        <a class="marque" href="/"><span class="logo" aria-hidden="true">&lt;/&gt;</span> Quiz SLAM</a>

        <nav class="nav" aria-label="Navigation principale">
            <a href="/"<?= $courant('accueil') ?>>Accueil</a>
            <?php if ($enCours !== null): ?>
                <?php if ($enCours['terminee']): ?>
                    <a href="/bilan"<?= $courant('jeu') ?>>Dernier bilan</a>
                <?php else: ?>
                    <a href="/question" class="lien-en-cours"<?= $courant('jeu') ?>><span class="pastille" aria-hidden="true"></span>Partie en cours</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="/fiches"<?= $courant('fiches') ?>>Fiches</a>
            <a href="/stats"<?= $courant('stats') ?>>Statistiques</a>
        </nav>

        <form class="theme" method="post" action="/theme">
            <?= champCsrf() ?>
            <input type="hidden" name="retour" value="<?= e($retour) ?>">
            <button type="submit" name="theme" value="<?= e($themeSuivant) ?>" title="Changer de thème (suivant : <?= e(THEMES[$themeSuivant]) ?>)"
                aria-label="Thème : <?= e(THEMES[$theme]) ?>. Passer au thème <?= e(THEMES[$themeSuivant]) ?>.">
                <span class="theme-icone" aria-hidden="true"><?= $iconesThemes[$theme] ?? '◐' ?></span><span class="theme-nom"><?= e(THEMES[$theme]) ?></span>
            </button>
        </form>
    </div>
</header>

<main id="contenu" class="conteneur">
    <?php if ($flashs !== []): ?>
        <div class="flashs" data-flashs>
            <?php foreach ($flashs as $message): ?>
                <div class="alerte alerte-<?= e($message['type']) ?>" role="<?= $message['type'] === 'erreur' ? 'alert' : 'status' ?>" data-flash="<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $contenu ?>
</main>

<footer class="pied">
    <div class="conteneur pied-in">
        <p>Quiz BTS SIO SLAM · même moteur et même historique que le quiz en terminal (<code>php quiz.php</code>).</p>
        <p><a href="/sources"<?= $courant('sources') ?>>Sources et licences</a></p>
    </div>
</footer>
</body>
</html>
