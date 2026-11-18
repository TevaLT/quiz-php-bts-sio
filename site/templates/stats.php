<?php
/**
 * @var array $stats
 * @var list<array{ch:string,titre:string,taux:int,essais:int}> $fragiles   chapitres les moins réussis
 */
$progression = $stats['progression'];
?>
<section aria-labelledby="titre-stats">
    <h1 id="titre-stats">Mes statistiques</h1>

    <?php if ($stats['sessions'] === 0): ?>
        <div class="carte vide">
            <p>Aucune partie enregistrée pour le moment.</p>
            <p><a class="btn primaire" href="/">Lancer ma première partie</a></p>
        </div>
    <?php else: ?>
        <div class="cartes-chiffres">
            <div class="carte chiffre"><span class="valeur"><?= (int) $stats['sessions'] ?></span><span class="legende">parties jouées</span></div>
            <div class="carte chiffre"><span class="valeur"><?= (int) $stats['reponses'] ?></span><span class="legende">réponses données</span></div>
            <div class="carte chiffre <?= e(classeTaux($stats['reussite'])) ?>"><span class="valeur"><?= (int) $stats['reussite'] ?> %</span><span class="legende">de réussite globale</span></div>
            <div class="carte chiffre"><span class="valeur"><?= (int) $stats['meilleure_serie'] ?></span><span class="legende">bonnes réponses d'affilée (record)</span></div>
        </div>
        <?php if ($stats['temps_moyen'] !== null): ?>
            <p class="discret">Temps moyen par réponse (parties où il a été mesuré) : <strong><?= e(formaterSecondes($stats['temps_moyen'])) ?></strong>.</p>
        <?php endif; ?>

        <?php if ($fragiles !== []): ?>
            <section class="carte conseil" aria-labelledby="titre-fragiles">
                <div class="conseil-texte">
                    <h2 id="titre-fragiles">Tes points faibles</h2>
                    <p class="discret">Les chapitres où tu réussis le moins, sur au moins quelques réponses :
                        <?= e(implode(', ', array_map(static fn (array $f): string => $f['titre'] . ' (' . $f['taux'] . ' %)', $fragiles))) ?>.</p>
                </div>
                <form method="post" action="/partie">
                    <?= champCsrf() ?>
                    <input type="hidden" name="mode" value="normal">
                    <input type="hidden" name="n" value="<?= NOMBRE_QUESTIONS_DEFAUT ?>">
                    <?php foreach ($fragiles as $f): ?>
                        <input type="hidden" name="chapitres[]" value="<?= e($f['ch']) ?>">
                    <?php endforeach; ?>
                    <button class="btn primaire" type="submit">M'entraîner dessus</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="carte" aria-labelledby="titre-progression">
            <h2 id="titre-progression">Progression</h2>
            <?php if (count($progression) >= 2): ?>
                <figure class="graphe" data-graphe>
                    <ul class="legende">
                        <li><span class="cle cle-point" aria-hidden="true"></span>Score de chaque partie</li>
                        <li><span class="cle cle-ligne" aria-hidden="true"></span>Moyenne des <?= PARTIES_MOYENNE ?> dernières parties</li>
                    </ul>
                    <div class="graphe-zone">
                        <?= graphiqueProgression($progression, 720, 260, 'graphe-large') ?>
                        <?= graphiqueProgression($progression, 340, 220, 'graphe-etroit') ?>
                        <div class="infobulle" data-infobulle hidden></div>
                    </div>
                    <figcaption class="discret">
                        En bas, le numéro de chaque partie (de la plus ancienne à la plus récente<?= $stats['sessions'] > PARTIES_GRAPHIQUE ? ', ' . PARTIES_GRAPHIQUE . ' dernières' : '' ?>).
                        Survole le graphique, ou sélectionne-le puis utilise les flèches, pour lire le détail d'une partie.
                    </figcaption>
                    <p class="sr-only" aria-live="polite" data-annonce></p>
                </figure>
            <?php else: ?>
                <p class="discret">Le graphique de progression apparaîtra après ta deuxième partie.</p>
            <?php endif; ?>

            <details class="donnees"<?= count($progression) < 2 ? ' open' : '' ?>>
                <summary>Détail des parties (tableau)</summary>
                <div class="defilement">
                <table class="tableau">
                    <thead>
                        <tr><th scope="col">N°</th><th scope="col">Date</th><th scope="col">Score</th><th scope="col">Réussite</th><th scope="col">Moyenne (<?= PARTIES_MOYENNE ?> parties)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($progression) as $p): ?>
                            <tr>
                                <th scope="row"><?= (int) $p['n'] ?></th>
                                <td><?= e(formaterDate($p['date'])) ?></td>
                                <td><?= (int) $p['score'] ?> / <?= (int) $p['total'] ?></td>
                                <td><?= (int) $p['pct'] ?> %</td>
                                <td><?= (int) $p['moyenne'] ?> %</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </details>
        </section>

        <section class="carte" aria-labelledby="titre-par-chapitre">
            <h2 id="titre-par-chapitre">Par chapitre</h2>
            <p class="discret">Questions déjà vues / questions de la banque, et taux de réussite sur ces questions.</p>
            <div class="defilement">
            <table class="tableau">
                <thead>
                    <tr><th scope="col">Chapitre</th><th scope="col">Vues</th><th scope="col" class="col-barre">Réussite</th><th scope="col">Taux</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['chapitres'] as $ch => $c): ?>
                        <tr>
                            <th scope="row"><span class="num"><?= e((string) $ch) ?></span> <a href="/fiches?ch=<?= e((string) $ch) ?>"><?= e($c['titre']) ?></a></th>
                            <td><?= (int) $c['vues'] ?> / <?= (int) $c['banque'] ?></td>
                            <td class="col-barre"><?= barreSvg($c['taux'] ?? 0, classeTaux($c['taux'])) ?></td>
                            <td class="taux <?= e(classeTaux($c['taux'])) ?>"><?= $c['taux'] === null ? '—' : (int) $c['taux'] . ' %' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>

        <?php if ($stats['pires'] !== []): ?>
            <section class="carte" aria-labelledby="titre-pires">
                <h2 id="titre-pires">Questions les plus ratées</h2>
                <ol class="liste-pires">
                    <?php foreach ($stats['pires'] as $p): ?>
                        <li>
                            <span class="badge neutre"><?= (int) $p['ko'] ?>×</span>
                            <span class="badge chap">Ch. <?= e($p['question']['ch']) ?></span>
                            <?= texteHtml(mb_strimwidth(trim(implode(' ', array_column(array_filter($p['question']['q'], static fn (array $s): bool => !$s['code']), 't'))), 0, 120, '…')) ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <p class="discret"><a href="/reinitialiser">Effacer mon historique…</a></p>
</section>
