<?php
/**
 * @var array $partie
 * @var array $q          question traitée
 * @var array $resultat   statut, choisies, texte, duree
 * @var int $numero
 * @var int $total
 * @var bool $terminee
 * @var int $serie        bonnes réponses consécutives à la fin de la partie
 * @var array{ok:int, erreurs:int, passees:int} $score   score en direct
 */
$statut = $resultat['statut'];
$enSerie = $partie['mode'] === 'serie';
$verdict = match ($statut) {
    'ok' => ['Bonne réponse !', 'ok', '✔'],
    'passee' => ['Question passée', 'passee', '•'],
    'temps' => ['Temps écoulé', 'temps', '⏱'],
    default => ['Raté', 'ko', '✘'],
};
$duree = $resultat['duree'] ?? null;
$sousTitre = match (true) {
    $enSerie && $terminee && $statut === 'ok' => 'Série parfaite : ' . $numero . ' bonnes réponses, sans aucune erreur !',
    $enSerie && $terminee => 'Fin de la série : ' . ($numero - 1) . ' bonne(s) réponse(s) d\'affilée.',
    $statut === 'temps' => "La réponse n'a pas été donnée à temps : elle compte comme une erreur.",
    $statut === 'passee' => 'Voici la bonne réponse, pour la retenir la prochaine fois.',
    $statut === 'ok' => $duree !== null ? 'Trouvé en ' . formaterSecondes((float) $duree) . '.' : '',
    default => 'Lis l\'explication : la question reviendra plus souvent dans tes prochaines parties.',
};
?>
<section class="partie" aria-labelledby="titre-correction">
    <div class="progression">
        <p class="progression-texte">
            Question <strong><?= $numero ?></strong><?= $enSerie ? '' : ' sur ' . $total ?>
            <?php if (!$enSerie): ?>
                <span class="score-direct" title="Bonnes réponses et erreurs depuis le début de la partie">
                    <span class="score-ok"><span aria-hidden="true">✔</span> <?= $score['ok'] ?><span class="sr-only"> juste(s)</span></span>
                    <span class="score-ko"><span aria-hidden="true">✘</span> <?= $score['erreurs'] ?><span class="sr-only"> erreur(s)</span></span>
                </span>
            <?php endif; ?>
        </p>
        <?php if (!$enSerie): ?>
            <progress max="<?= $total ?>" value="<?= $numero ?>" aria-label="Progression"><?= $numero ?> / <?= $total ?></progress>
            <ol class="points" aria-label="Résultats">
                <?php for ($i = 0; $i < $total; $i++): ?>
                    <li class="point <?= e($partie['resultats'][$i]['statut'] ?? '') ?>"><span class="sr-only"><?= $i + 1 ?></span></li>
                <?php endfor; ?>
            </ol>
        <?php endif; ?>
    </div>

    <article class="carte question verdict-<?= e($verdict[1]) ?>">
        <div class="bandeau-verdict">
            <h1 id="titre-correction" class="verdict" role="status">
                <span class="verdict-icone" aria-hidden="true"><?= $verdict[2] ?></span> <?= e($verdict[0]) ?>
            </h1>
            <?php if ($sousTitre !== ''): ?><p class="verdict-texte"><?= e($sousTitre) ?></p><?php endif; ?>
        </div>

        <p class="meta"><span class="badge chap">Ch. <?= e($q['ch']) ?> · <?= e(TITRES[$q['ch']] ?? '') ?></span></p>
        <div class="enonce"><?= enonceHtml($q) ?></div>

        <?php if ($q['type'] === 'saisie'): ?>
            <dl class="comparaison">
                <?php if ($resultat['texte'] !== ''): ?>
                    <div><dt>Ta réponse<?= $statut === 'temps' ? ' (hors délai)' : '' ?></dt><dd class="<?= $statut === 'ok' ? 'ok' : 'ko' ?>"><?= e($resultat['texte']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Réponse attendue</dt><dd class="ok"><?= e(implode('  ou  ', $q['acceptees'])) ?></dd></div>
            </dl>
        <?php else: ?>
            <ol class="choix correction">
                <?php foreach ($q['choix'] as $i => $c): ?>
                    <?php $choisie = in_array($i, $resultat['choisies'], true); ?>
                    <li class="choix-ligne<?= $c['ok'] ? ' juste' : ($choisie ? ' faux' : '') ?><?= $choisie ? ' choisie' : '' ?>">
                        <span class="lettre" aria-hidden="true"><?= e(lettre($i)) ?></span>
                        <span class="texte"><?= texteHtml($c['t']) ?></span>
                        <?= etiquetteChoix($c['ok'], $choisie) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <aside class="explication">
            <h2>Explication</h2>
            <p><?= texteHtml($q['why']) ?></p>
            <?php if ($statut !== 'ok'): ?>
                <p class="revoir">À revoir : <?= e(typographie(conseilRevision($q['ch']))) ?> — <a href="/fiches?ch=<?= e($q['ch']) ?>">fiche du chapitre</a></p>
            <?php endif; ?>
        </aside>

        <div class="actions actions-jeu">
            <?php if ($terminee): ?>
                <a class="btn primaire" href="/bilan" data-suivant>Voir le bilan <span aria-hidden="true">→</span></a>
            <?php else: ?>
                <a class="btn primaire" href="/question" data-suivant>Question suivante <span aria-hidden="true">→</span></a>
            <?php endif; ?>
        </div>
        <p class="raccourcis" data-raccourcis hidden>Raccourci : <kbd>Entrée</kbd> pour continuer.</p>
    </article>
</section>
