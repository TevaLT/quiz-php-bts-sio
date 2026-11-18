<?php
/**
 * @var array $partie
 * @var array $bilan
 * @var array{action: string, titre: string, texte: string, chapitre?: string} $etape   prochaine étape conseillée
 */
$classe = $bilan['pourcentage'] >= 80 ? 'bon' : ($bilan['pourcentage'] >= 50 ? 'moyen' : 'faible');
$enSerie = $partie['mode'] === 'serie';
$message = match (true) {
    $bilan['total'] === 0 => "Aucune réponse n'a été donnée.",
    $enSerie && $bilan['ok'] === $bilan['total'] => 'Série parfaite : aucune erreur sur ' . $bilan['total'] . ' questions !',
    $enSerie => 'Série de ' . $bilan['serie'] . ' bonne(s) réponse(s) avant la première erreur.',
    $bilan['pourcentage'] >= 90 => 'Excellent, tu maîtrises !',
    $bilan['pourcentage'] >= 70 => 'Très bien, continue !',
    $bilan['pourcentage'] >= 50 => 'Correct, mais il reste des zones à consolider.',
    default => 'Pas de panique : chaque erreur est une notion de plus à retenir.',
};
$examen = $partie['mode'] === 'examen';
$nbErreurs = $bilan['ko'] + $bilan['passees'] + $bilan['temps'];
$chrono = (int) ($partie['chrono'] ?? 0);
$options = $partie['options'];
// Cours à relire (chapitres adossés à un cours précis), sans doublon.
$coursARelire = array_values(array_unique(array_filter(array_map(
    static fn ($ch): ?string => COURS_DU_CHAPITRE[(string) $ch] ?? null,
    $bilan['a_revoir']
))));

/** Formulaire qui relance une partie avec les réglages d'origine, éventuellement modifiés ($surcharge). */
$boutonPartie = static function (array $surcharge, string $libelle, bool $primaire) use ($options, $partie): void {
    $mode = (string) ($surcharge['mode'] ?? $options['mode'] ?? $partie['mode']);
    echo '<form method="post" action="/partie">', champCsrf(),
        '<input type="hidden" name="mode" value="', e($mode), '">',
        '<input type="hidden" name="n" value="', (int) ($options['n'] ?? NOMBRE_QUESTIONS_DEFAUT), '">',
        '<input type="hidden" name="chrono" value="', (int) ($surcharge['chrono'] ?? $options['chrono'] ?? 0), '">',
        '<input type="hidden" name="cours" value="', !empty($options['cours']) ? '1' : '0', '">';
    if ($surcharge === [] && !empty($options['rapide'])) {
        echo '<input type="hidden" name="rapide" value="1">'; // rejouer une partie rapide à l'identique reste une partie rapide
    }
    foreach ($options['chapitres'] ?? [] as $ch) {
        echo '<input type="hidden" name="chapitres[]" value="', e((string) $ch), '">';
    }
    echo '<button class="btn', $primaire ? ' primaire' : '', '" type="submit">', e($libelle), '</button></form>';
};
$boutonErreurs = static function (bool $primaire) use ($nbErreurs): void {
    echo '<form method="post" action="/erreurs">', champCsrf(), '<button class="btn', $primaire ? ' primaire' : '', '" type="submit">',
        $nbErreurs > 1 ? 'Refaire mes ' . $nbErreurs . ' erreurs' : 'Refaire mon erreur', '</button></form>';
};
$libelleRejouer = ($options['mode'] ?? '') === 'serie' ? 'Nouvelle série' : 'Rejouer (questions neuves)';
?>
<section aria-labelledby="titre-bilan">
    <h1 id="titre-bilan"><?= $enSerie ? 'Bilan de la série' : 'Bilan de la partie' ?></h1>

    <div class="carte resultat">
        <?= anneauSvg($bilan['pourcentage'], 'Score : ' . $bilan['ok'] . ' sur ' . $bilan['total']) ?>
        <div class="resultat-texte">
            <p class="score <?= e($classe) ?>"><strong><?= $bilan['ok'] ?></strong> / <?= $bilan['total'] ?> <span class="note"><?= e(number_format($bilan['note'], 1, ',', '')) ?> / 20</span></p>
            <p><?= e($message) ?></p>
            <?php if ($bilan['nouveau_record']): ?>
                <p class="record" role="status">Nouveau record : <strong><?= $bilan['serie'] ?></strong> bonnes réponses d'affilée<?= $bilan['record'] > 0 ? ' (ancien record : ' . $bilan['record'] . ')' : '' ?> !</p>
            <?php endif; ?>
            <ul class="chiffres">
                <li><strong><?= $bilan['ok'] ?></strong> juste(s)</li>
                <li><strong><?= $bilan['ko'] ?></strong> fausse(s)</li>
                <?php if ($bilan['passees'] > 0): ?><li><strong><?= $bilan['passees'] ?></strong> passée(s)</li><?php endif; ?>
                <?php if ($chrono > 0 || $bilan['temps'] > 0): ?><li><strong><?= $bilan['temps'] ?></strong> hors délai</li><?php endif; ?>
                <?php if ($bilan['non_traitees'] > 0): ?><li><strong><?= $bilan['non_traitees'] ?></strong> non traitée(s)</li><?php endif; ?>
                <li>Meilleure série : <strong><?= $bilan['serie'] ?></strong></li>
                <li>Durée : <strong><?= e(formaterDuree($bilan['duree'])) ?></strong></li>
                <?php if ($bilan['duree_moyenne'] !== null): ?><li>Temps moyen : <strong><?= e(formaterSecondes($bilan['duree_moyenne'])) ?></strong> par question</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <section class="carte etape-suivante" aria-labelledby="titre-suite">
        <div class="etape-suivante-texte">
            <p class="surtitre">Et maintenant ?</p>
            <h2 id="titre-suite"><?= e(typographie($etape['titre'])) ?></h2>
            <p><?= e($etape['texte']) ?></p>
        </div>
        <div class="actions">
            <?php
            match ($etape['action']) {
                'erreurs' => $boutonErreurs(true),
                'minuteur' => $boutonPartie(['chrono' => 30], 'Rejouer avec 30 s par question', true),
                'serie' => $boutonPartie(['mode' => 'serie'], 'Lancer une série', true),
                'rejouer' => $boutonPartie([], $libelleRejouer, true),
                default => null,
            };
            ?>
            <?php if ($etape['action'] === 'fiche'): ?>
                <a class="btn primaire" href="/fiches?ch=<?= e($etape['chapitre'] ?? '') ?>">Lire la fiche</a>
            <?php endif; ?>
            <?php if ($nbErreurs > 0 && $etape['action'] !== 'erreurs'): ?>
                <?php $boutonErreurs(false); ?>
            <?php endif; ?>
            <?php if ($etape['action'] !== 'rejouer'): ?>
                <?php $boutonPartie([], $libelleRejouer, false); ?>
            <?php endif; ?>
            <a class="btn" href="/#configurer">Changer les réglages</a>
        </div>
    </section>

    <?php if ($bilan['par_chapitre'] !== []): ?>
        <section class="carte" aria-labelledby="titre-chapitres">
            <h2 id="titre-chapitres">Par chapitre</h2>
            <ul class="liste-chapitres">
                <?php foreach ($bilan['par_chapitre'] as $ch => $v): ?>
                    <?php $taux = (int) round(100 * $v['ok'] / $v['total']); ?>
                    <li>
                        <span class="chap-nom"><span class="num"><?= e((string) $ch) ?></span> <a href="/fiches?ch=<?= e((string) $ch) ?>"><?= e(TITRES[$ch] ?? '') ?></a></span>
                        <?= barreSvg($taux, classeTaux($taux)) ?>
                        <span class="chap-score"><?= $v['ok'] ?>/<?= $v['total'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($bilan['a_revoir'] !== []): ?>
                <p class="revoir">
                    À relire : <?= implode(', ', array_map(static fn ($c): string => '<a href="/fiches?ch=' . e((string) $c) . '">fiche ' . e((string) $c) . '</a>', $bilan['a_revoir'])) ?>
                    <?php if ($coursARelire !== []): ?>
                        <br>Cours concernés : <?= e(typographie(implode(' ; ', array_map(static fn (string $c): string => '« ' . $c . ' »', $coursARelire)))) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($bilan['total'] > 0): ?>
        <section class="carte" aria-labelledby="titre-detail">
            <div class="titre-avec-outil">
                <h2 id="titre-detail">Détail des questions</h2>
                <button class="lien" type="button" data-deplier-bilan hidden>Tout déplier</button>
            </div>
            <p class="discret"><?= $examen ? 'Voici le corrigé de l\'examen.' : 'Les questions ratées sont dépliées ; clique sur une question pour la revoir.' ?></p>
            <?php foreach ($partie['resultats'] as $i => $resultat): ?>
                <?php
                $q = $partie['questions'][$i];
                $juste = $resultat['statut'] === 'ok';
                [$symbole, $libelle] = match ($resultat['statut']) {
                    'ok' => ['✔', 'Juste'],
                    'passee' => ['•', 'Passée'],
                    'temps' => ['⏱', 'Hors délai'],
                    default => ['✘', 'Fausse'],
                };
                $resume = trim(implode(' ', array_column(array_filter($q['q'], static fn (array $s): bool => !$s['code']), 't')));
                $duree = $resultat['duree'] ?? null;
                ?>
                <details class="detail <?= e($resultat['statut']) ?>"<?= $juste ? '' : ' open' ?>>
                    <summary>
                        <span class="symbole" aria-hidden="true"><?= $symbole ?></span>
                        <span class="sr-only"><?= $libelle ?> :</span>
                        <span class="num"><?= $i + 1 ?>.</span> <span class="resume-court"><?= texteHtml(mb_strimwidth($resume, 0, 110, '…')) ?></span>
                        <span class="badge chap">Ch. <?= e($q['ch']) ?></span>
                        <?php if ($duree !== null): ?><span class="discret duree"><?= e(formaterSecondes((float) $duree)) ?></span><?php endif; ?>
                    </summary>
                    <div class="detail-corps">
                        <div class="enonce"><?= enonceHtml($q) ?></div>
                        <?php if ($q['type'] === 'saisie'): ?>
                            <dl class="comparaison">
                                <?php if ($resultat['texte'] !== ''): ?>
                                    <div><dt>Ta réponse<?= $resultat['statut'] === 'temps' ? ' (hors délai)' : '' ?></dt><dd class="<?= $juste ? 'ok' : 'ko' ?>"><?= e($resultat['texte']) ?></dd></div>
                                <?php endif; ?>
                                <div><dt>Réponse attendue</dt><dd class="ok"><?= e(implode('  ou  ', $q['acceptees'])) ?></dd></div>
                            </dl>
                        <?php else: ?>
                            <ol class="choix correction">
                                <?php foreach ($q['choix'] as $k => $c): ?>
                                    <?php $choisie = in_array($k, $resultat['choisies'], true); ?>
                                    <li class="choix-ligne<?= $c['ok'] ? ' juste' : ($choisie ? ' faux' : '') ?><?= $choisie ? ' choisie' : '' ?>">
                                        <span class="lettre" aria-hidden="true"><?= e(lettre($k)) ?></span>
                                        <span class="texte"><?= texteHtml($c['t']) ?></span>
                                        <?= etiquetteChoix($c['ok'], $choisie) ?>
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

    <p class="discret infos-partie">
        <?php if (isset($partie['rattrapage'])): ?>
            Partie n°<?= (int) $partie['numSession'] ?> · rattrapage des erreurs de la partie n°<?= (int) $partie['rattrapage'] ?>
        <?php else: ?>
            Partie n°<?= (int) $partie['numSession'] ?><?= $chrono > 0 ? ' · ' . $chrono . ' s par question' : '' ?> · graine <code><?= (int) $partie['graine'] ?></code>
            (pour rejouer exactement ces questions : accueil, « Personnaliser », puis « Rejouer une partie précise »)
        <?php endif; ?>
    </p>

    <div class="actions actions-bilan">
        <?php if ($nbErreurs > 0): ?>
            <?php $boutonErreurs(true); ?>
        <?php endif; ?>
        <?php $boutonPartie([], $libelleRejouer, $nbErreurs === 0); ?>
        <a class="btn" href="/">Accueil</a>
        <a class="btn" href="/stats">Mes statistiques</a>
        <button class="btn" type="button" data-imprimer hidden>Imprimer le bilan</button>
    </div>
</section>
