<?php
/** Page « Sources et licences » : contenu statique (même information que SOURCES.md à la racine du dépôt). */
$coursFerut = [
    'PHP interactif : formulaires, superglobales, cookies et sessions',
    'PHP et les données persistantes (PDO)',
    'MVC : comprendre avant d\'utiliser (Slim 4)',
    'Architecture REST et API avec Slim 4',
    'Sécuriser une API REST : JWT et authentification',
    'JavaScript Vanilla : les fondamentaux',
    'JavaScript Vanilla : approfondissement',
    'JavaScript Vanilla : le DOM et les événements',
];
?>
<section class="page-texte" aria-labelledby="titre-sources">
    <h1 id="titre-sources">Sources et licences</h1>

    <section class="carte" aria-labelledby="titre-origine">
        <h2 id="titre-origine">D'où viennent les questions</h2>
        <p>
            Les questions servent à réviser le programme de BTS SIO option SLAM (2<sup>e</sup> année) :
            bases de PHP, web, bases de données, outils, architecture MVC, API REST et JavaScript.
        </p>
        <p>
            Les chapitres 26 à 29 et la plupart des questions « tirées des cours » s'appuient sur les cours de
            <strong>Térence FERUT</strong> (AstroWare Conception), publiés sous licence
            <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" rel="license">CC BY-SA 4.0</a> :
        </p>
        <ul class="liste-sources">
            <?php foreach ($coursFerut as $titreCours): ?>
                <li><?= e($titreCours) ?></li>
            <?php endforeach; ?>
        </ul>
        <p>
            Ces questions sont des adaptations (reformulations, nouveaux exemples, propositions de réponse et explications) :
            elles ne remplacent pas les cours, qui restent la référence pour apprendre.
        </p>
        <p>
            Les autres questions sont originales : elles portent sur les notions travaillées en TP, sans en reproduire les sujets.
            Quelques notions générales viennent aussi d'un MOOC PHP et d'un sujet de synthèse corrigé (PHP, PDO, MVC, REST) ;
            elles ont été reformulées sur d'autres exemples, sans reprendre leur texte.
        </p>
    </section>

    <section class="carte" aria-labelledby="titre-licences">
        <h2 id="titre-licences">Licences</h2>
        <ul>
            <li><strong>Code</strong> (moteur, quiz en terminal, site web) : licence MIT.</li>
            <li>
                <strong>Banque de questions</strong> (dossier <code>banque/</code>) :
                <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" rel="license">CC BY-SA 4.0</a>, comme les cours dont elle s'inspire.
                Tu peux la réutiliser et la modifier, en citant les sources et en gardant la même licence.
            </li>
        </ul>
    </section>

    <section class="carte" aria-labelledby="titre-donnees">
        <h2 id="titre-donnees">Tes données</h2>
        <p>
            Le site tourne sur ta machine. Ton historique est écrit dans <code>historique.json</code>, à côté du quiz :
            rien n'est envoyé ailleurs, aucune ressource externe n'est chargée, aucun traceur n'est utilisé.
        </p>
        <p>Deux cookies seulement : la session (<code>QUIZSESSID</code>, pour la partie en cours) et le thème choisi (<code>quiz_theme</code>).</p>
    </section>
</section>
