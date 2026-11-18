<?php
/**
 * @var string|null $chapitre              chapitre affiché (validé par liste blanche), ou null
 * @var string $recherche                  texte recherché (validé), ou ''
 * @var list<array<string,mixed>> $fiches  questions à afficher
 * @var array<string,int> $comptes         nombre de questions par chapitre
 * @var array<string,int> $generes         nombre de générateurs par chapitre
 */
$cles = array_map('strval', array_keys(TITRES));
$position = $chapitre === null ? null : array_search($chapitre, $cles, true);
$precedent = is_int($position) && $position > 0 ? $cles[$position - 1] : null;
$suivant = is_int($position) && $position < count($cles) - 1 ? $cles[$position + 1] : null;
$resume = static fn (array $q): string => trim(implode(' ', array_column(array_filter($q['q'], static fn (array $s): bool => !$s['code']), 't')));
?>
<section class="page-fiches" aria-labelledby="titre-fiches"<?= $recherche !== '' ? ' data-surligner="' . e($recherche) . '"' : '' ?>>
    <h1 id="titre-fiches">
        <?php if ($chapitre !== null): ?>
            <span class="num"><?= e($chapitre) ?></span> <?= e(TITRES[$chapitre]) ?>
        <?php else: ?>
            Fiches de révision
        <?php endif; ?>
    </h1>
    <p class="chapo">
        <?php if ($chapitre !== null): ?>
            <?= (int) ($comptes[$chapitre] ?? 0) ?> questions avec leur réponse et leur explication<?= ($generes[$chapitre] ?? 0) > 0 ? ', plus ' . (int) $generes[$chapitre] . ' générateur(s) qui fabriquent des questions neuves pendant les parties' : '' ?>.
            <?php if (isset(COURS_DU_CHAPITRE[$chapitre])): ?><br><span class="discret">Cours de référence : <?= e(typographie('« ' . COURS_DU_CHAPITRE[$chapitre] . ' »')) ?></span><?php endif; ?>
        <?php else: ?>
            Toutes les questions de la banque, rangées par chapitre, avec la bonne réponse et l'explication. Idéal pour apprendre avant de jouer, ou pour relire un chapitre raté.
        <?php endif; ?>
    </p>

    <form class="carte recherche" method="get" action="/fiches" role="search">
        <label for="q">Rechercher une notion dans toute la banque</label>
        <div class="recherche-ligne">
            <input id="q" name="q" type="search" maxlength="<?= RECHERCHE_LONGUEUR_MAX ?>" value="<?= e($recherche) ?>" placeholder="ex : password_hash, closure, fetch">
            <button class="btn" type="submit">Rechercher</button>
        </div>
    </form>

    <?php if ($chapitre === null && $recherche === ''): ?>
        <div class="blocs blocs-fiches">
            <?php foreach (BLOCS as $numeroBloc => [$nomBloc, $debut, $fin]): ?>
                <section class="bloc carte-bloc" aria-labelledby="bloc-<?= (int) $numeroBloc ?>">
                    <h2 id="bloc-<?= (int) $numeroBloc ?>"><?= e($nomBloc) ?> <span class="discret"><?= sprintf('%02d', $debut) ?>–<?= sprintf('%02d', $fin) ?></span></h2>
                    <ul class="liste-fiches">
                        <?php foreach (chapitresDuBloc($numeroBloc) as $ch): ?>
                            <li>
                                <a href="/fiches?ch=<?= e($ch) ?>"><span class="num"><?= e($ch) ?></span> <?= e(TITRES[$ch]) ?></a>
                                <span class="nb"><?= (int) ($comptes[$ch] ?? 0) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <nav class="nav-fiches" aria-label="Navigation entre les fiches">
            <a href="/fiches">← Tous les chapitres</a>
            <?php if ($precedent !== null): ?><a href="/fiches?ch=<?= e($precedent) ?>">‹ <?= e($precedent) ?> · <?= e(TITRES[$precedent]) ?></a><?php endif; ?>
            <?php if ($suivant !== null): ?><a href="/fiches?ch=<?= e($suivant) ?>"><?= e($suivant) ?> · <?= e(TITRES[$suivant]) ?> ›</a><?php endif; ?>
        </nav>

        <div class="outils-fiches">
            <p class="discret" role="status">
                <?php if ($recherche !== ''): ?>
                    <?= count($fiches) ?> fiche(s) pour « <?= e($recherche) ?> »<?= count($fiches) >= RECHERCHE_RESULTATS_MAX ? ' (les ' . RECHERCHE_RESULTATS_MAX . ' premières)' : '' ?>.
                <?php else: ?>
                    <?= count($fiches) ?> fiche(s). Clique sur une question pour voir la réponse.
                <?php endif; ?>
            </p>
            <?php if ($fiches !== []): ?>
                <button class="lien" type="button" data-deplier hidden>Tout déplier</button>
            <?php endif; ?>
        </div>

        <?php if ($fiches === []): ?>
            <div class="carte vide"><p>Aucune question ne contient ces mots. Essaie un terme plus court ou une autre orthographe.</p></div>
        <?php endif; ?>

        <?php foreach ($fiches as $k => $q): ?>
            <details class="detail fiche" id="<?= e($q['id']) ?>">
                <summary>
                    <span class="num"><?= $k + 1 ?>.</span>
                    <span class="resume-court"><?= texteHtml(mb_strimwidth($resume($q), 0, 140, '…')) ?></span>
                    <?php if ($recherche !== ''): ?><span class="badge chap">Ch. <?= e($q['ch']) ?></span><?php endif; ?>
                    <?php if ($q['cours']): ?><span class="badge neutre">cours</span><?php endif; ?>
                </summary>
                <div class="detail-corps">
                    <div class="enonce"><?= enonceHtml($q) ?></div>
                    <?php if ($q['type'] === 'saisie'): ?>
                        <dl class="comparaison">
                            <div><dt>Réponse attendue</dt><dd class="ok"><?= e(implode('  ou  ', $q['acceptees'])) ?></dd></div>
                        </dl>
                    <?php else: ?>
                        <ol class="choix correction">
                            <?php foreach ($q['choix'] as $i => $c): ?>
                                <li class="choix-ligne<?= $c['ok'] ? ' juste' : '' ?>">
                                    <span class="lettre" aria-hidden="true"><?= e(lettre($i)) ?></span>
                                    <span class="texte"><?= texteHtml($c['t']) ?></span>
                                    <?= etiquetteChoix($c['ok'], false) ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <p class="explication-ligne"><?= texteHtml($q['why']) ?></p>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="fin-fiches">
            <?php if ($chapitre !== null): ?>
                <form method="post" action="/partie">
                    <?= champCsrf() ?>
                    <input type="hidden" name="mode" value="normal">
                    <input type="hidden" name="n" value="10">
                    <input type="hidden" name="chapitres[]" value="<?= e($chapitre) ?>">
                    <button class="btn primaire" type="submit">M'entraîner sur ce chapitre (10 questions)</button>
                </form>
                <?php if ($suivant !== null): ?><a class="btn" href="/fiches?ch=<?= e($suivant) ?>">Fiche suivante : <?= e(TITRES[$suivant]) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
            <?php endif; ?>
            <a class="lien-haut" href="#contenu">↑ Haut de page</a>
        </div>
    <?php endif; ?>
</section>
