<?php
/**
 * @var array $partie
 * @var array $bilan
 */
$classe = $bilan['pourcentage'] >= 80 ? 'bon' : ($bilan['pourcentage'] >= 50 ? 'moyen' : 'faible');
$message = match (true) {
    $bilan['total'] === 0 => "Aucune réponse n'a été donnée : lance une nouvelle partie.",
    $bilan['pourcentage'] >= 90 => 'Excellent, tu maîtrises !',
    $bilan['pourcentage'] >= 70 => 'Très bien, continue !',
    $bilan['pourcentage'] >= 50 => 'Correct, mais il reste des zones à consolider.',
    default => 'Pas de panique : relis les chapitres signalés puis relance une partie.',
};
$examen = $partie['mode'] === 'examen';
$nbErreurs = $bilan['ko'] + $bilan['passees'];
// Cours à relire (chapitres adossés à un cours précis), sans doublon.
$coursARelire = array_values(array_unique(array_filter(array_map(
    static fn ($ch): ?string => COURS_DU_CHAPITRE[(string) $ch] ?? null,
    $bilan['a_revoir']
))));
?>
<section aria-labelledby="titre-bilan">
    <h1 id="titre-bilan">Bilan de la partie</h1>

    <div class="carte resultat">
        <?= anneauSvg($bilan['pourcentage'], 'Score : ' . $bilan['ok'] . ' sur ' . $bilan['total']) ?>
        <div class="resultat-texte">
            <p class="score <?= e($classe) ?>"><strong><?= $bilan['ok'] ?></strong> / <?= $bilan['total'] ?> <span class="note"><?= e(number_format($bilan['note'], 1, ',', '')) ?> / 20</span></p>
            <p><?= e($message) ?></p>
            <ul class="chiffres">
                <li><strong><?= $bilan['ok'] ?></strong> juste(s)</li>
                <li><strong><?= $bilan['ko'] ?></strong> fausse(s)</li>
                <li><strong><?= $bilan['passees'] ?></strong> passée(s)</li>
                <?php if ($bilan['non_traitees'] > 0): ?><li><strong><?= $bilan['non_traitees'] ?></strong> non traitée(s)</li><?php endif; ?>
                <li>Durée : <strong><?= e(formaterDuree($bilan['duree'])) ?></strong></li>
            </ul>
            <?php if (isset($partie['rattrapage'])): ?>
                <p class="discret">Partie n°<?= (int) $partie['numSession'] ?> · rattrapage des erreurs de la partie n°<?= (int) $partie['rattrapage'] ?></p>
            <?php else: ?>
                <p class="discret">Partie n°<?= (int) $partie['numSession'] ?> · graine <code><?= (int) $partie['graine'] ?></code> (à saisir dans « Options avancées » pour rejouer exactement ces questions)</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($bilan['par_chapitre'] !== []): ?>
        <section class="carte" aria-labelledby="titre-chapitres">
            <h2 id="titre-chapitres">Par chapitre</h2>
            <ul class="liste-chapitres">
                <?php foreach ($bilan['par_chapitre'] as $ch => $v): ?>
                    <?php $taux = (int) round(100 * $v['ok'] / $v['total']); ?>
                    <li>
                        <span class="chap-nom"><span class="num"><?= e((string) $ch) ?></span> <?= e(TITRES[$ch] ?? '') ?></span>
                        <?= barreSvg($taux, classeTaux($taux)) ?>
                        <span class="chap-score"><?= $v['ok'] ?>/<?= $v['total'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($bilan['a_revoir'] !== []): ?>
                <p class="revoir">
                    À relire : <?= e(implode(', ', array_map(static fn ($c): string => 'ch. ' . $c, $bilan['a_revoir']))) ?>
                    <?php if ($coursARelire !== []): ?>
                        <br>Cours concernés : <?= e(typographie(implode(' ; ', array_map(static fn (string $c): string => '« ' . $c . ' »', $coursARelire)))) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($bilan['total'] > 0): ?>
        <section class="carte" aria-labelledby="titre-detail">
            <h2 id="titre-detail">Détail des questions</h2>
            <p class="discret"><?= $examen ? 'Voici la correction de l\'examen.' : 'Les questions ratées sont dépliées.' ?></p>
            <?php foreach ($partie['resultats'] as $i => $resultat): ?>
                <?php
                $q = $partie['questions'][$i];
                $juste = $resultat['statut'] === 'ok';
                $symbole = $juste ? '✔' : ($resultat['statut'] === 'passee' ? '•' : '✘');
                $resume = trim(implode(' ', array_column(array_filter($q['q'], static fn (array $s): bool => !$s['code']), 't')));
                ?>
                <details class="detail <?= e($resultat['statut']) ?>"<?= $juste ? '' : ' open' ?>>
                    <summary>
                        <span class="symbole" aria-hidden="true"><?= $symbole ?></span>
                        <span class="sr-only"><?= $juste ? 'Juste' : ($resultat['statut'] === 'passee' ? 'Passée' : 'Fausse') ?> :</span>
                        <span class="num"><?= $i + 1 ?>.</span> <span class="resume-court"><?= texteHtml(mb_strimwidth($resume, 0, 110, '…')) ?></span>
                        <span class="badge chap">Ch. <?= e($q['ch']) ?></span>
                    </summary>
                    <div class="detail-corps">
                        <div class="enonce"><?= enonceHtml($q) ?></div>
                        <?php if ($q['type'] === 'saisie'): ?>
                            <dl class="comparaison">
                                <?php if ($resultat['statut'] !== 'passee'): ?>
                                    <div><dt>Ta réponse</dt><dd class="<?= $juste ? 'ok' : 'ko' ?>"><?= e($resultat['texte']) ?></dd></div>
                                <?php endif; ?>
                                <div><dt>Réponse attendue</dt><dd class="ok"><?= e(implode('  ou  ', $q['acceptees'])) ?></dd></div>
                            </dl>
                        <?php else: ?>
                            <ol class="choix correction">
                                <?php foreach ($q['choix'] as $k => $c): ?>
                                    <?php
                                    $choisie = in_array($k, $resultat['choisies'], true);
                                    $cl = $c['ok'] ? 'juste' : ($choisie ? 'faux' : '');
                                    ?>
                                    <li class="choix-ligne <?= $cl ?><?= $choisie ? ' choisie' : '' ?>">
                                        <span class="lettre" aria-hidden="true"><?= e(lettre($k)) ?></span>
                                        <span class="texte"><?= texteHtml($c['t']) ?></span>
                                        <span class="marque"><?= $c['ok'] ? '<span class="sr-only">Bonne réponse</span><span aria-hidden="true">✔</span>' : ($choisie ? '<span class="sr-only">Ta réponse, fausse</span><span aria-hidden="true">✘</span>' : '') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                        <p class="explication-ligne"><?= texteHtml($q['why']) ?></p>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="actions actions-bilan">
        <?php if ($nbErreurs > 0): ?>
            <form method="post" action="/erreurs">
                <?= champCsrf() ?>
                <button class="btn primaire" type="submit">Refaire mes <?= $nbErreurs ?> erreur<?= $nbErreurs > 1 ? 's' : '' ?></button>
            </form>
        <?php endif; ?>
        <form method="post" action="/partie">
            <?= champCsrf() ?>
            <input type="hidden" name="mode" value="<?= e($partie['options']['mode'] ?? $partie['mode']) ?>">
            <input type="hidden" name="n" value="<?= (int) $partie['options']['n'] ?>">
            <input type="hidden" name="cours" value="<?= $partie['options']['cours'] ? '1' : '0' ?>">
            <?php foreach ($partie['options']['chapitres'] as $ch): ?>
                <input type="hidden" name="chapitres[]" value="<?= e($ch) ?>">
            <?php endforeach; ?>
            <button class="btn<?= $nbErreurs > 0 ? '' : ' primaire' ?>" type="submit">Rejouer (questions neuves)</button>
        </form>
        <a class="btn" href="/">Changer les réglages</a>
        <a class="btn" href="/stats">Mes statistiques</a>
        <button class="btn" type="button" data-imprimer hidden>Imprimer le bilan</button>
    </div>
</section>
