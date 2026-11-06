<?php
/** @var string $titre */
/** @var string $page */
/** @var string $contenu */
$theme = themeActuel();
$enCours = partieEnCours();
$retour = in_array(cheminDemande(), PAGES_RETOUR, true) ? cheminDemande() : '/';
$courant = static fn (string $nom): string => $page === $nom ? ' aria-current="page"' : '';
?><!DOCTYPE html>
<html lang="fr" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Quiz d'entraînement BTS SIO SLAM : PHP, SQL et PDO, MVC, API REST, JWT et JavaScript. Questions tirées au hasard à chaque partie, correction expliquée, suivi de la progression.">
    <title><?= e($titre) ?> · Quiz BTS SIO SLAM</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>

<header class="entete">
    <div class="conteneur entete-in">
        <a class="marque" href="/"><span class="logo" aria-hidden="true">&lt;/&gt;</span> Quiz SLAM</a>

        <nav class="nav" aria-label="Navigation principale">
            <a href="/"<?= $courant('accueil') ?>>Accueil</a>
            <?php if ($enCours !== null): ?>
                <a href="<?= $enCours['terminee'] ? '/bilan' : '/question' ?>"<?= $courant('jeu') ?>><?= $enCours['terminee'] ? 'Dernier bilan' : 'Partie en cours' ?></a>
            <?php endif; ?>
            <a href="/stats"<?= $courant('stats') ?>>Statistiques</a>
        </nav>

        <form class="theme" method="post" action="/theme" aria-label="Choix du thème">
            <?= champCsrf() ?>
            <input type="hidden" name="retour" value="<?= e($retour) ?>">
            <?php foreach (THEMES as $cle => $libelle): ?>
                <button type="submit" name="theme" value="<?= e($cle) ?>"<?= $theme === $cle ? ' aria-pressed="true"' : ' aria-pressed="false"' ?>><?= e($libelle) ?></button>
            <?php endforeach; ?>
        </form>
    </div>
</header>

<main id="contenu" class="conteneur">
    <?php foreach (consommerFlash() as $message): ?>
        <div class="alerte alerte-<?= e($message['type']) ?>" role="<?= $message['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>

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
