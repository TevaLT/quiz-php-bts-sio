<?php
/**
 * @var int $code
 * @var string $titre
 * @var string $message
 */
?>
<section class="carte etroite erreur-page" aria-labelledby="titre-erreur">
    <p class="code-erreur"><?= (int) $code ?></p>
    <h1 id="titre-erreur"><?= e($titre) ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="btn primaire" href="/">Retour à l'accueil</a></p>
</section>
