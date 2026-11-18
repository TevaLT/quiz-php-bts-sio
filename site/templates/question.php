<?php
/**
 * @var array $partie
 * @var array $q
 * @var int $numero
 * @var int $total
 * @var float|null $restant   secondes restantes (null : pas de minuteur)
 * @var int $serie            bonnes réponses consécutives juste avant cette question
 * @var array{ok:int, erreurs:int, passees:int} $score   score en direct
 */
$examen = $partie['mode'] === 'examen';
$enSerie = $partie['mode'] === 'serie';
$multi = $q['type'] === 'multi';
$chrono = (int) ($partie['chrono'] ?? 0);
$record = (int) ($partie['record'] ?? 0);
$type = match ($q['type']) {
    'multi' => 'Plusieurs réponses possibles',
    'saisie' => 'Réponse libre',
    default => 'Une seule réponse',
};
?>
<section class="partie" aria-labelledby="titre-question">
    <div class="progression">
        <p class="progression-texte">
            <?php if ($enSerie): ?>
                Question <strong><?= $numero ?></strong>
            <?php else: ?>
                Question <strong><?= $numero ?></strong> sur <?= $total ?>
            <?php endif; ?>
            <span class="badge"><?= e(isset($partie['rattrapage']) ? 'Rattrapage de la partie n°' . (int) $partie['rattrapage'] : (MODES[$partie['mode']] ?? '')) ?></span>
            <?php if ($enSerie): ?>
                <span class="badge serie">Bonnes réponses : <?= $serie ?></span>
                <?php if ($record > 0): ?><span class="badge neutre">Record : <?= $record ?></span><?php endif; ?>
            <?php elseif (!$examen && $serie >= 2): ?>
                <span class="badge serie"><?= $serie ?> d'affilée</span>
            <?php endif; ?>
            <?php if (!$examen && !$enSerie && $numero > 1): ?>
                <span class="score-direct" title="Bonnes réponses et erreurs depuis le début de la partie">
                    <span class="score-ok"><span aria-hidden="true">✔</span> <?= $score['ok'] ?><span class="sr-only"> juste(s)</span></span>
                    <span class="score-ko"><span aria-hidden="true">✘</span> <?= $score['erreurs'] ?><span class="sr-only"> erreur(s)</span></span>
                </span>
            <?php endif; ?>
        </p>
        <?php if (!$enSerie): ?>
            <progress max="<?= $total ?>" value="<?= $numero - 1 ?>" aria-label="Progression"><?= $numero - 1 ?> / <?= $total ?></progress>
            <ol class="points" aria-label="Résultats précédents">
                <?php for ($i = 0; $i < $total; $i++): ?>
                    <?php
                    $classe = $i === $numero - 1 ? 'courant' : (isset($partie['resultats'][$i]) ? ($examen ? 'fait' : $partie['resultats'][$i]['statut']) : '');
                    ?>
                    <li class="point <?= e($classe) ?>"><span class="sr-only"><?= $i + 1 ?></span></li>
                <?php endfor; ?>
            </ol>
        <?php endif; ?>
    </div>

    <?php if ($restant !== null): ?>
        <div class="chrono<?= $restant <= 5 ? ' urgent' : '' ?>" data-chrono data-limite="<?= $chrono ?>" data-restant="<?= e(number_format($restant, 1, '.', '')) ?>">
            <p class="chrono-texte">
                <span aria-hidden="true">⏱</span>
                <span data-chrono-texte><?= $restant > 0 ? 'Temps restant : <strong>' . (int) ceil($restant) . '</strong> s' : '<strong>Temps écoulé</strong> : cette réponse ne comptera pas' ?></span>
                <span class="discret">· <?= $chrono ?> s par question</span>
            </p>
            <progress max="<?= $chrono ?>" value="<?= e(number_format($restant, 1, '.', '')) ?>" aria-label="Temps restant" data-chrono-barre></progress>
            <p class="sr-only" aria-live="assertive" data-chrono-annonce></p>
        </div>
    <?php endif; ?>

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

            <div class="actions actions-jeu">
                <p class="aide-reponse" data-aide-reponse aria-live="assertive"></p>
                <button class="btn primaire" type="submit" name="action" value="valider" data-valider>Valider</button>
                <?php if (!$enSerie): ?>
                    <button class="btn discret-btn" type="submit" name="action" value="passer" formnovalidate>Passer</button>
                <?php endif; ?>
            </div>
            <p class="raccourcis" data-raccourcis hidden>
                <?php if ($q['type'] === 'saisie'): ?>
                    Raccourci : <kbd>Entrée</kbd> pour valider.
                <?php else: ?>
                    Raccourcis : <kbd>A</kbd>–<kbd><?= e(lettre(count($q['choix']) - 1)) ?></kbd> pour répondre, <kbd>Entrée</kbd> pour valider<?= $enSerie ? '' : ', <kbd>S</kbd> pour passer' ?>.
                <?php endif; ?>
            </p>
        </form>
    </article>

    <form method="post" action="/abandonner" class="abandon" data-confirmer="Terminer la partie maintenant ? Les questions restantes ne seront pas posées.">
        <?= champCsrf() ?>
        <button class="lien" type="submit"><?= $enSerie ? 'Arrêter la série ici' : 'Terminer la partie maintenant' ?></button>
        <?php if ($examen): ?><span class="discret">· mode examen : la correction s'affichera dans le bilan</span><?php endif; ?>
    </form>
</section>
