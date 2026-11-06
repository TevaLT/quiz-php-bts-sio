<?php
/**
 * @var array $valeurs   options sélectionnées (valeurs par défaut ou saisies à réafficher)
 * @var array $erreurs   messages par champ
 * @var array $comptes   nombre de questions par chapitre
 * @var int $total
 * @var int $nbGenerateurs
 * @var int $nbCours
 * @var int $aRevoir
 * @var int $nbSessions
 * @var array|null $partie
 * @var list<array{ch:string,titre:string,taux:int,essais:int}> $fragiles   chapitres à consolider (historique)
 * @var array<string,list<string>> $preselections                          sélections rapides de chapitres
 */
$coche = static fn (bool $condition): string => $condition ? ' checked' : '';
$choisis = array_map('strval', $valeurs['chapitres']);
?>
<section class="hero">
    <h1>Révise PHP, SQL, les API et JavaScript</h1>
    <p class="chapo">
        <?= (int) $total ?> questions et <?= (int) $nbGenerateurs ?> générateurs, répartis en <?= count(TITRES) ?> chapitres :
        bases de PHP, web, bases de données, architecture MVC, API REST et JWT, JavaScript.
        <strong>À chaque partie, les questions changent.</strong>
    </p>
</section>

<?php if ($partie !== null && !$partie['terminee']): ?>
    <section class="carte reprise" aria-label="Partie en cours">
        <p>
            Une partie est en cours : <strong><?= positionPartie($partie) ?> / <?= count($partie['questions']) ?></strong> questions traitées
            (<?= e(isset($partie['rattrapage']) ? 'rattrapage des erreurs' : (MODES[$partie['mode']] ?? '')) ?>).
        </p>
        <div class="actions">
            <a class="btn primaire" href="/question">Reprendre</a>
            <form method="post" action="/abandonner">
                <?= champCsrf() ?>
                <button class="btn" type="submit">La terminer maintenant</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if ($fragiles !== []): ?>
    <section class="carte conseil" aria-labelledby="titre-conseil">
        <div class="conseil-texte">
            <h2 id="titre-conseil">À consolider en priorité</h2>
            <ul class="liste-fragiles">
                <?php foreach ($fragiles as $f): ?>
                    <li>
                        <span class="num"><?= e($f['ch']) ?></span> <?= e($f['titre']) ?>
                        · <span class="taux <?= e(classeTaux($f['taux'])) ?>"><?= (int) $f['taux'] ?> %</span>
                        <span class="discret">de réussite sur <?= (int) $f['essais'] ?> réponses</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form method="post" action="/partie">
            <?= champCsrf() ?>
            <input type="hidden" name="mode" value="normal">
            <input type="hidden" name="n" value="15">
            <?php foreach ($fragiles as $f): ?>
                <input type="hidden" name="chapitres[]" value="<?= e($f['ch']) ?>">
            <?php endforeach; ?>
            <button class="btn primaire" type="submit">M'entraîner sur ces chapitres</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($erreurs !== []): ?>
    <div class="alerte alerte-erreur" role="alert">
        <strong>La partie n'a pas pu être créée :</strong>
        <ul>
            <?php foreach ($erreurs as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="carte formulaire" method="post" action="/partie" data-formulaire-partie>
    <?= champCsrf() ?>

    <fieldset class="groupe">
        <legend>Mode de jeu</legend>
        <div class="modes">
            <label class="mode">
                <input type="radio" name="mode" value="normal"<?= $coche($valeurs['mode'] === 'normal') ?>>
                <span class="mode-titre">Entraînement</span>
                <span class="mode-desc">Correction et explication après chaque question.</span>
            </label>
            <label class="mode">
                <input type="radio" name="mode" value="examen"<?= $coche($valeurs['mode'] === 'examen') ?>>
                <span class="mode-titre">Examen</span>
                <span class="mode-desc">Aucune correction avant la fin : bilan complet à la dernière question.</span>
            </label>
            <label class="mode">
                <input type="radio" name="mode" value="revision"<?= $coche($valeurs['mode'] === 'revision') ?>>
                <span class="mode-titre">Révision</span>
                <span class="mode-desc">Rejoue uniquement tes erreurs (<?= (int) $aRevoir ?> à revoir).</span>
            </label>
        </div>
    </fieldset>

    <div class="ligne-options">
        <div class="champ">
            <label for="n">Nombre de questions</label>
            <select id="n" name="n">
                <?php foreach (NOMBRES_QUESTIONS as $nombre): ?>
                    <option value="<?= $nombre ?>"<?= $valeurs['n'] === $nombre ? ' selected' : '' ?>><?= $nombre ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erreurs['n'])): ?><p class="erreur-champ"><?= e($erreurs['n']) ?></p><?php endif; ?>
        </div>

        <div class="champ">
            <span class="etiquette">Source des questions</span>
            <label class="case">
                <input type="checkbox" name="cours" value="1"<?= $coche($valeurs['cours']) ?>>
                Uniquement les questions tirées des cours <span class="discret">(<?= (int) $nbCours ?>)</span>
            </label>
        </div>
    </div>

    <fieldset class="groupe">
        <legend>Chapitres</legend>
        <?php if (isset($erreurs['chapitres'])): ?><p class="erreur-champ"><?= e($erreurs['chapitres']) ?></p><?php endif; ?>

        <div class="preselections" data-preselections hidden>
            <span class="etiquette-ligne">Sélection rapide :</span>
            <?php foreach ($preselections as $libelle => $liste): ?>
                <button type="button" class="puce" data-chapitres="<?= e(implode(' ', $liste)) ?>"><?= e($libelle) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="blocs">
            <?php foreach (BLOCS as $numeroBloc => [$nomBloc, $debut, $fin]): ?>
                <div class="bloc" data-bloc>
                    <div class="bloc-entete">
                        <h3><?= e($nomBloc) ?> <span class="discret"><?= sprintf('%02d', $debut) ?>–<?= sprintf('%02d', $fin) ?></span></h3>
                    </div>
                    <div class="chapitres">
                        <?php foreach (chapitresDuBloc($numeroBloc) as $ch): ?>
                            <label class="chapitre">
                                <input type="checkbox" name="chapitres[]" value="<?= e($ch) ?>" data-nb="<?= (int) ($comptes[$ch] ?? 0) ?>"<?= $coche(in_array($ch, $choisis, true)) ?>>
                                <span class="num"><?= e($ch) ?></span>
                                <span class="nom"><?= e(TITRES[$ch]) ?></span>
                                <span class="nb"><?= (int) ($comptes[$ch] ?? 0) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="resume-selection" data-resume aria-live="polite"></p>
    </fieldset>

    <details class="avance"<?= isset($erreurs['graine']) || $valeurs['graine_texte'] !== '' ? ' open' : '' ?>>
        <summary>Options avancées</summary>
        <div class="champ">
            <label for="graine">Graine (facultatif)</label>
            <input id="graine" name="graine" type="text" inputmode="numeric" autocomplete="off" maxlength="10" value="<?= e($valeurs['graine_texte']) ?>" placeholder="ex : 123456">
            <p class="aide">Rejoue exactement une ancienne partie (la graine est affichée dans le bilan). Laisse vide pour un tirage neuf.</p>
            <?php if (isset($erreurs['graine'])): ?><p class="erreur-champ"><?= e($erreurs['graine']) ?></p><?php endif; ?>
        </div>
    </details>

    <div class="actions">
        <button class="btn primaire grand" type="submit">Commencer</button>
    </div>
</form>

<section class="carte pourquoi">
    <h2>Pourquoi les questions changent à chaque partie</h2>
    <ul>
        <li><strong>Tirage neuf</strong> : une graine aléatoire est tirée à chaque partie.</li>
        <li><strong>Mémoire</strong> : les questions de la partie précédente sont presque exclues, celles jamais vues favorisées<?= $nbSessions > 0 ? ' (' . (int) $nbSessions . ' partie(s) déjà enregistrée(s))' : '' ?>.</li>
        <li><strong>Ordre mélangé</strong> : questions et propositions changent de place.</li>
        <li><strong>Questions générées</strong> : environ une sur quatre a des valeurs neuves, et sa réponse est calculée par le programme.</li>
    </ul>
</section>
