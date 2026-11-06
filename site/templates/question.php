<?php
/**
 * @var array $partie
 * @var array $q
 * @var int $numero
 * @var int $total
 */
$examen = $partie['mode'] === 'examen';
$multi = $q['type'] === 'multi';
$type = match ($q['type']) {
    'multi' => 'Plusieurs réponses possibles',
    'saisie' => 'Réponse libre',
    default => 'Une seule réponse',
};
?>
<section class="partie" aria-labelledby="titre-question">
    <div class="progression">
        <p class="progression-texte">
            Question <strong><?= $numero ?></strong> sur <?= $total ?>
            <span class="badge"><?= e(isset($partie['rattrapage']) ? 'Rattrapage de la partie n°' . (int) $partie['rattrapage'] : (MODES[$partie['mode']] ?? '')) ?></span>
        </p>
        <progress max="<?= $total ?>" value="<?= $numero - 1 ?>" aria-label="Progression"><?= $numero - 1 ?> / <?= $total ?></progress>
        <ol class="points" aria-label="Résultats précédents">
            <?php for ($i = 0; $i < $total; $i++): ?>
                <?php
                $classe = $i === $numero - 1 ? 'courant' : (isset($partie['resultats'][$i]) ? ($examen ? 'fait' : $partie['resultats'][$i]['statut']) : '');
                ?>
                <li class="point <?= e($classe) ?>"><span class="sr-only"><?= $i + 1 ?></span></li>
            <?php endfor; ?>
        </ol>
    </div>

    <article class="carte question">
        <p class="meta">
            <span class="badge chap">Ch. <?= e($q['ch']) ?> · <?= e(TITRES[$q['ch']] ?? '') ?></span>
            <span class="badge neutre"><?= e($type) ?></span>
        </p>

        <h1 id="titre-question" class="sr-only">Question <?= $numero ?></h1>
        <div class="enonce"><?= enonceHtml($q) ?></div>

        <form method="post" action="/repondre" class="reponse" data-formulaire-reponse data-type="<?= e($q['type']) ?>">
            <?= champCsrf() ?>
            <input type="hidden" name="numero" value="<?= $numero ?>">

            <?php if ($q['type'] === 'saisie'): ?>
                <div class="champ">
                    <label for="texte">Ta réponse</label>
                    <input id="texte" name="texte" type="text" maxlength="200" autocomplete="off" autocapitalize="off" spellcheck="false" autofocus required>
                    <?php if ($q['indice'] !== ''): ?><p class="aide">Indice : <?= e($q['indice']) ?></p><?php endif; ?>
                </div>
            <?php else: ?>
                <fieldset class="choix">
                    <legend class="sr-only"><?= $multi ? 'Coche toutes les bonnes réponses' : 'Choisis une réponse' ?></legend>
                    <?php foreach ($q['choix'] as $i => $c): ?>
                        <label class="choix-ligne">
                            <input type="<?= $multi ? 'checkbox' : 'radio' ?>" name="choix<?= $multi ? '[]' : '' ?>" value="<?= $i ?>" data-lettre="<?= e(lettre($i)) ?>">
                            <span class="lettre" aria-hidden="true"><?= e(lettre($i)) ?></span>
                            <span class="texte"><?= texteHtml($c['t']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php if ($multi): ?><p class="aide">Plusieurs propositions sont correctes : coche-les toutes.</p><?php endif; ?>
            <?php endif; ?>

            <div class="actions">
                <button class="btn primaire" type="submit" name="action" value="valider">Valider</button>
                <button class="btn" type="submit" name="action" value="passer" formnovalidate>Passer</button>
            </div>
            <p class="raccourcis" data-raccourcis hidden>
                <?php if ($q['type'] === 'saisie'): ?>
                    Raccourci : <kbd>Entrée</kbd> pour valider.
                <?php else: ?>
                    Raccourcis : <kbd>A</kbd>–<kbd><?= e(lettre(count($q['choix']) - 1)) ?></kbd> pour répondre, <kbd>Entrée</kbd> pour valider, <kbd>S</kbd> pour passer.
                <?php endif; ?>
            </p>
        </form>
    </article>

    <form method="post" action="/abandonner" class="abandon">
        <?= champCsrf() ?>
        <button class="lien" type="submit">Terminer la partie maintenant</button>
        <?php if ($examen): ?><span class="discret">· mode examen : la correction s'affichera dans le bilan</span><?php endif; ?>
    </form>
</section>
