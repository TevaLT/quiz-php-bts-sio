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
            Les questions servent à apprendre et réviser le langage PHP (bases, programmation objet, web, bases de données,
            architecture MVC, API REST, JWT) et JavaScript, au niveau du BTS SIO option SLAM.
        </p>
        <p>
            Les chapitres 23 à 26 et les questions « tirées des supports de référence » s'appuient sur les cours de
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
            Les autres questions sont originales et portent sur le langage lui-même : elles ne reprennent ni le contenu ni
            les sujets des exercices de TP. Quelques notions générales viennent aussi d'un MOOC PHP et d'un sujet de synthèse
            corrigé (PHP, PDO, MVC, REST) ; elles ont été reformulées sur d'autres exemples, sans reprendre leur texte.
        </p>
    </section>

    <section class="carte" aria-labelledby="titre-cours-site">
        <h2 id="titre-cours-site">D'où vient le cours</h2>
        <p>
            Les <?= count(TITRES) ?> leçons de la rubrique <a href="/cours">Cours</a> sont des textes originaux, écrits pour ce quiz :
            elles expliquent le langage et ses outils avec des exemples génériques, sans reproduire le texte des supports
            ci-dessus ni les exercices de TP. Les résultats annoncés (sorties de code, messages d'erreur) ont été vérifiés
            avec PHP et Node.js. Quand un chapitre s'appuie sur un support de référence, la correction et les fiches le citent.
        </p>
    </section>

    <section class="carte" aria-labelledby="titre-licences">
        <h2 id="titre-licences">Licences</h2>
        <ul>
            <li><strong>Code</strong> (moteur, quiz en terminal, site web) : licence MIT.</li>
            <li>
                <strong>Cours</strong> (dossier <code>cours/</code>) :
                <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" rel="license">CC BY-SA 4.0</a>, comme la banque de questions.
            </li>
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
