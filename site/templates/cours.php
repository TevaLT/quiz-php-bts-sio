<?php
/**
 * Cours : sommaire (chapitre === null) ou leçon d'un chapitre.
 *
 * Sommaire :
 * @var array<string, array{titre:string, resume:string, minutes:int, ecrit:bool}> $liste
 * @var array<string, array<string,mixed>> $chapitres   statistiques par chapitre (taux de réussite au quiz)
 * @var string|null $conseille                          prochain chapitre conseillé, ou null si tout est maîtrisé
 *
 * Leçon :
 * @var string|null $chapitre
 * @var array{meta:array<string,string>, blocs:list<array<string,mixed>>, sommaire:list<array{id:string,texte:string}>, minutes:int} $cours
 * @var array<string,mixed> $stats    statistiques du chapitre
 * @var int $nbQuestions
 * @var int $nbGenerateurs
 * @var string|null $precedent
 * @var string|null $suivant
 * @var bool $partieEnCours
 */

/** Niveau de maîtrise d'un chapitre d'après le quiz : [classe CSS, libellé]. */
$maitrise = static fn (?int $taux): array => match (true) {
    $taux === null => ['neutre', 'Pas encore testé'],
    $taux >= 80 => ['bon', 'Maîtrisé · ' . $taux . ' %'],
    $taux >= 50 => ['moyen', 'À consolider · ' . $taux . ' %'],
    default => ['faible', 'À retravailler · ' . $taux . ' %'],
};
?>
<?php if ($chapitre === null): ?>
    <?php
    $maitrises = count(array_filter($chapitres, static fn (array $c): bool => $c['taux'] !== null && $c['taux'] >= 80));
    $testes = count(array_filter($chapitres, static fn (array $c): bool => $c['taux'] !== null));
    // Durée totale de lecture, arrondie au quart d'heure : « 45 min », « 4 h », « 4 h 30 ».
    $quarts = (int) round(array_sum(array_column($liste, 'minutes')) / 15);
    $dureeTotale = $quarts < 4 ? max(15, 15 * $quarts) . ' min' : intdiv($quarts, 4) . ' h' . ($quarts % 4 > 0 ? ' ' . sprintf('%02d', 15 * ($quarts % 4)) : '');
    ?>
    <section class="page-cours" aria-labelledby="titre-cours">
        <header class="tete-cours">
            <h1 id="titre-cours">Le cours</h1>
            <p class="chapo">
                <?= count($liste) ?> chapitres pour apprendre PHP et JavaScript pas à pas, du premier <code>echo</code> jusqu'à une API
                sécurisée. <strong>Lis un chapitre, puis teste-toi</strong> : le quiz te dit ce qui est acquis et ce qu'il faut relire.
            </p>
        </header>

        <section class="carte reprise-cours" aria-labelledby="titre-reprise">
            <div>
                <?php if ($conseille !== null): ?>
                    <p class="surtitre"><?= $testes === 0 ? 'Par où commencer' : 'Prochain chapitre conseillé' ?></p>
                    <h2 id="titre-reprise"><span class="num"><?= e($conseille) ?></span> <?= e(TITRES[$conseille]) ?></h2>
                    <p class="discret">
                        <?php if (($chapitres[$conseille]['taux'] ?? null) === null): ?>
                            <?= $testes === 0 ? 'Le cours se suit dans l\'ordre : chaque chapitre s\'appuie sur les précédents.' : 'Le premier chapitre que tu n\'as pas encore testé.' ?>
                        <?php else: ?>
                            Moins de 50 % de réussite au quiz sur ce chapitre : une relecture s'impose.
                        <?php endif; ?>
                        <?php if (($liste[$conseille]['minutes'] ?? 0) > 0): ?>Environ <?= (int) $liste[$conseille]['minutes'] ?> min de lecture.<?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="surtitre">Bravo</p>
                    <h2 id="titre-reprise">Tous les chapitres sont validés au quiz</h2>
                    <p class="discret">Relis ceux que tu veux, ou lance une série pour vérifier que tout est devenu un réflexe.</p>
                <?php endif; ?>
            </div>
            <div class="reprise-actions">
                <?php if ($conseille !== null): ?>
                    <a class="btn primaire grand" href="/cours?ch=<?= e($conseille) ?>"><?= $testes === 0 ? 'Commencer le cours' : 'Lire ce chapitre' ?> <span aria-hidden="true">→</span></a>
                <?php else: ?>
                    <a class="btn primaire grand" href="/">Lancer une partie</a>
                <?php endif; ?>
            </div>
            <div class="avancement">
                <p><strong><?= $maitrises ?></strong> chapitre<?= $maitrises > 1 ? 's' : '' ?> maîtrisé<?= $maitrises > 1 ? 's' : '' ?> sur <?= count($liste) ?>
                    <span class="discret">· <?= $testes ?> testé<?= $testes > 1 ? 's' : '' ?> au quiz · environ <?= e($dureeTotale) ?> de lecture en tout</span></p>
                <?= barreSvg(count($liste) > 0 ? (int) round(100 * $maitrises / count($liste)) : 0, 'bon') ?>
            </div>
        </section>

        <div class="blocs-cours">
            <?php foreach (BLOCS as $numeroBloc => [$nomBloc, $debut, $fin]): ?>
                <section class="carte bloc-cours" aria-labelledby="bloc-cours-<?= (int) $numeroBloc ?>">
                    <h2 id="bloc-cours-<?= (int) $numeroBloc ?>">
                        <span class="tuile-icone" aria-hidden="true"><?= e(ICONES_BLOCS[$numeroBloc] ?? '') ?></span>
                        <?= e($nomBloc) ?> <span class="discret">chapitres <?= sprintf('%02d', $debut) ?> à <?= sprintf('%02d', $fin) ?></span>
                    </h2>
                    <ol class="liste-cours">
                        <?php foreach (chapitresDuBloc($numeroBloc) as $ch): ?>
                            <?php $infos = $liste[$ch] ?? null; [$classe, $libelle] = $maitrise($chapitres[$ch]['taux'] ?? null); ?>
                            <li<?= $ch === $conseille ? ' class="conseille"' : '' ?>>
                                <?php if ($infos !== null && $infos['ecrit']): ?>
                                    <a class="lien-cours" href="/cours?ch=<?= e($ch) ?>"><span class="num"><?= e($ch) ?></span> <span class="titre-cours"><?= e(TITRES[$ch]) ?></span></a>
                                    <?php if ($infos['resume'] !== ''): ?><p class="resume-cours"><?= texteCours($infos['resume']) ?></p><?php endif; ?>
                                    <p class="meta-cours">
                                        <span><?= (int) $infos['minutes'] ?> min</span>
                                        <span class="badge maitrise <?= e($classe) ?>"><?= e($libelle) ?></span>
                                        <?php if ($ch === $conseille): ?><span class="badge">Conseillé</span><?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <span class="lien-cours inactif"><span class="num"><?= e($ch) ?></span> <span class="titre-cours"><?= e(TITRES[$ch]) ?></span></span>
                                    <p class="meta-cours"><span class="discret">Chapitre en cours de rédaction</span></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <?php
    $numeroBloc = blocDuChapitre($chapitre);
    [$classe, $libelle] = $maitrise($stats['taux'] ?? null);
    $resume = $cours['meta']['resume'] ?? '';
    ?>
    <article class="page-cours lecon" aria-labelledby="titre-lecon">
        <nav class="fil" aria-label="Fil d'Ariane">
            <a href="/cours">Cours</a> <span aria-hidden="true">›</span>
            <span><?= e(BLOCS[$numeroBloc][0]) ?></span> <span aria-hidden="true">›</span>
            <span aria-current="page">Chapitre <?= e($chapitre) ?></span>
        </nav>

        <header class="tete-lecon">
            <h1 id="titre-lecon"><span class="num"><?= e($chapitre) ?></span> <?= e(TITRES[$chapitre]) ?></h1>
            <?php if ($resume !== ''): ?><p class="chapo"><?= texteCours($resume) ?></p><?php endif; ?>
            <ul class="meta-lecon">
                <li><span aria-hidden="true">⏱</span> <?= (int) $cours['minutes'] ?> min de lecture</li>
                <li><?= (int) $nbQuestions ?> questions au quiz<?= $nbGenerateurs > 0 ? ' (+ ' . (int) $nbGenerateurs . ' générée' . ($nbGenerateurs > 1 ? 's' : '') . ')' : '' ?></li>
                <li><span class="badge maitrise <?= e($classe) ?>"><?= e($libelle) ?></span></li>
                <li><a href="#teste-toi">Aller au test <span aria-hidden="true">↓</span></a></li>
            </ul>
        </header>

        <div class="lecon-grille">
            <?php if (count($cours['sommaire']) > 1): ?>
                <nav class="sommaire-lecon" aria-labelledby="titre-sommaire-lecon">
                    <p class="sommaire-titre" id="titre-sommaire-lecon">Dans ce chapitre</p>
                    <ol>
                        <?php foreach ($cours['sommaire'] as $entree): ?>
                            <li><a href="#<?= e($entree['id']) ?>"><?= texteCours($entree['texte']) ?></a></li>
                        <?php endforeach; ?>
                        <li><a href="#teste-toi">Teste-toi</a></li>
                    </ol>
                </nav>
            <?php endif; ?>

            <div class="lecon-texte">
                <?= coursHtml($cours['blocs']) ?>

                <section class="carte teste-toi" id="teste-toi" aria-labelledby="titre-teste-toi">
                    <div>
                        <p class="surtitre">Et maintenant</p>
                        <h2 id="titre-teste-toi">Teste-toi sur ce chapitre</h2>
                        <p>
                            10 questions sur « <?= e(TITRES[$chapitre]) ?> », avec la correction expliquée après chaque réponse.
                            <?php if (($stats['taux'] ?? null) !== null): ?>Ta réussite actuelle : <strong class="taux <?= e(classeTaux($stats['taux'])) ?>"><?= (int) $stats['taux'] ?> %</strong>.<?php endif; ?>
                        </p>
                    </div>
                    <div class="actions">
                        <form method="post" action="/partie"<?= $partieEnCours ? ' data-confirmer="Une partie est en cours : la terminer pour lancer ce test ? Les réponses déjà données restent enregistrées."' : '' ?>>
                            <?= champCsrf() ?>
                            <input type="hidden" name="mode" value="normal">
                            <input type="hidden" name="n" value="10">
                            <input type="hidden" name="chrono" value="0">
                            <input type="hidden" name="rapide" value="1">
                            <input type="hidden" name="chapitres[]" value="<?= e($chapitre) ?>">
                            <button class="btn primaire" type="submit">Lancer le test</button>
                        </form>
                        <a class="btn" href="/fiches?ch=<?= e($chapitre) ?>">Relire les fiches</a>
                    </div>
                </section>

                <nav class="nav-lecons" aria-label="Chapitres voisins">
                    <?php if ($precedent !== null): ?>
                        <a class="voisin precedent" href="/cours?ch=<?= e($precedent) ?>" rel="prev">
                            <span class="discret"><span aria-hidden="true">←</span> Chapitre précédent</span>
                            <span><span class="num"><?= e($precedent) ?></span> <?= e(TITRES[$precedent]) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($suivant !== null): ?>
                        <a class="voisin suivant" href="/cours?ch=<?= e($suivant) ?>" rel="next">
                            <span class="discret">Chapitre suivant <span aria-hidden="true">→</span></span>
                            <span><span class="num"><?= e($suivant) ?></span> <?= e(TITRES[$suivant]) ?></span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </article>
<?php endif; ?>
