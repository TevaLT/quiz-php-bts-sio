<?php
/**
 * @var array $q          question traitée
 * @var array $resultat   statut, choisies, texte
 * @var int $numero
 * @var int $total
 * @var bool $terminee
 */
$statut = $resultat['statut'];
$verdict = match ($statut) {
    'ok' => ['Bonne réponse !', 'ok', '✔'],
    'passee' => ['Question passée', 'passee', '•'],
    default => ['Raté', 'ko', '✘'],
};
?>
<section class="partie" aria-labelledby="titre-correction">
    <div class="progression">
        <p class="progression-texte">Question <strong><?= $numero ?></strong> sur <?= $total ?></p>
        <progress max="<?= $total ?>" value="<?= $numero ?>" aria-label="Progression"><?= $numero ?> / <?= $total ?></progress>
    </div>

    <article class="carte question verdict-<?= e($verdict[1]) ?>">
        <h1 id="titre-correction" class="verdict" role="status">
            <span class="verdict-icone" aria-hidden="true"><?= $verdict[2] ?></span> <?= e($verdict[0]) ?>
        </h1>

        <p class="meta"><span class="badge chap">Ch. <?= e($q['ch']) ?> · <?= e(TITRES[$q['ch']] ?? '') ?></span></p>
        <div class="enonce"><?= enonceHtml($q) ?></div>

        <?php if ($q['type'] === 'saisie'): ?>
            <dl class="comparaison">
                <?php if ($statut !== 'passee'): ?>
                    <div><dt>Ta réponse</dt><dd class="<?= $statut === 'ok' ? 'ok' : 'ko' ?>"><?= e($resultat['texte']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Réponse attendue</dt><dd class="ok"><?= e(implode('  ou  ', $q['acceptees'])) ?></dd></div>
            </dl>
        <?php else: ?>
            <ol class="choix correction">
                <?php foreach ($q['choix'] as $i => $c): ?>
                    <?php
                    $choisie = in_array($i, $resultat['choisies'], true);
                    $classe = $c['ok'] ? 'juste' : ($choisie ? 'faux' : '');
                    ?>
                    <li class="choix-ligne <?= $classe ?><?= $choisie ? ' choisie' : '' ?>">
                        <span class="lettre" aria-hidden="true"><?= e(lettre($i)) ?></span>
                        <span class="texte"><?= texteHtml($c['t']) ?></span>
                        <span class="marque">
                            <?php if ($c['ok']): ?><span class="sr-only">Bonne réponse</span><span aria-hidden="true">✔</span>
                            <?php elseif ($choisie): ?><span class="sr-only">Ta réponse, fausse</span><span aria-hidden="true">✘</span><?php endif; ?>
                            <?php if ($choisie && $c['ok']): ?><span class="sr-only"> (cochée)</span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <aside class="explication">
            <h2>Explication</h2>
            <p><?= texteHtml($q['why']) ?></p>
            <?php if ($statut !== 'ok'): ?>
                <p class="revoir">À revoir : <?= e(typographie(conseilRevision($q['ch']))) ?></p>
            <?php endif; ?>
        </aside>

        <div class="actions">
            <?php if ($terminee): ?>
                <a class="btn primaire" href="/bilan" data-suivant>Voir le bilan</a>
            <?php else: ?>
                <a class="btn primaire" href="/question" data-suivant>Question suivante</a>
            <?php endif; ?>
        </div>
        <p class="raccourcis" data-raccourcis hidden>Raccourci : <kbd>Entrée</kbd> pour continuer.</p>
    </article>
</section>
