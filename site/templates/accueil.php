<?php
/**
 * @var array $valeurs                    réglages affichés (dernière partie, valeurs par défaut ou saisie à réafficher)
 * @var array $erreurs                    messages par champ
 * @var array<string,int> $comptes        questions de la banque par chapitre
 * @var array<string,int> $comptesCours   questions tirées des cours par chapitre
 * @var array<string,int> $generes        générateurs par chapitre
 * @var int $total
 * @var int $nbGenerateurs
 * @var int $nbCours
 * @var int $aRevoir
 * @var int $nbSessions
 * @var int $record                       meilleure série de l'historique
 * @var array|null $partie
 * @var list<array{ch:string,titre:string,taux:int,essais:int}> $fragiles   chapitres à consolider (historique)
 * @var string|null $conseille            prochain chapitre conseillé du cours (null : tout est validé)
 * @var array|null $coursConseille        leçon de ce chapitre (durée de lecture), ou null
 */
$coche = static fn (bool $condition): string => $condition ? ' checked' : '';
$choisis = array_map('strval', $valeurs['chapitres']);
$chronoChoisi = (int) ($valeurs['chrono'] ?? 0);
$tousLesChapitres = array_map('strval', array_keys(TITRES));
$enCours = $partie !== null && !$partie['terminee'];
// « Révision » n'est pas proposable sans erreur à revoir : on repasse alors sur l'entraînement.
$mode = $valeurs['mode'] === 'revision' && $aRevoir === 0 ? 'normal' : $valeurs['mode'];

// Résumé initial de la barre « Commencer » (app.js le met à jour en direct).
$dispo = 0;
$dispoGeneres = 0;
foreach ($choisis as $ch) {
    $dispo += $valeurs['cours'] ? ($comptesCours[$ch] ?? 0) : ($comptes[$ch] ?? 0);
    $dispoGeneres += $valeurs['cours'] ? 0 : ($generes[$ch] ?? 0);
}
$resume = match ($mode) {
    'serie' => "Série jusqu'à la première erreur",
    'revision' => 'Révision de tes erreurs',
    default => (int) $valeurs['n'] . ' questions · ' . (MODES[$mode] ?? ''),
};
$resume .= ' · ' . count($choisis) . ' chapitre' . (count($choisis) > 1 ? 's' : '') . ($chronoChoisi > 0 ? ' · ' . $chronoChoisi . ' s par question' : '');
$detailTuile = static function (array $chapitresBloc) use ($choisis, $comptes): array {
    $cochesBloc = count(array_intersect($chapitresBloc, $choisis));
    $questions = array_sum(array_map(static fn (string $c): int => $comptes[$c] ?? 0, $chapitresBloc));

    return match (true) {
        $cochesBloc === count($chapitresBloc) => ['true', count($chapitresBloc) . ' chapitres · ' . $questions . ' questions'],
        $cochesBloc === 0 => ['false', 'Non inclus'],
        default => ['mixed', $cochesBloc . ' chapitre' . ($cochesBloc > 1 ? 's' : '') . ' sur ' . count($chapitresBloc)],
    };
};
?>
<section class="hero">
    <div class="hero-texte">
        <h1>Apprends et révise PHP, de la première ligne à l'API</h1>
        <p class="chapo">
            <?= (int) $total ?> questions en <?= count(TITRES) ?> chapitres — bases, objet, web, bases de données, API — et JavaScript.
            <strong>Chaque réponse est expliquée, et les questions changent à chaque partie.</strong>
        </p>
    </div>

    <div class="hero-actions">
        <?php if ($enCours): ?>
            <a class="btn primaire grand" href="/question">Reprendre ma partie</a>
            <p class="hero-note">
                <?php if ($partie['mode'] === 'serie'): ?>
                    Série en cours : <?= positionPartie($partie) ?> question(s) jouée(s)
                <?php else: ?>
                    <?= positionPartie($partie) ?> question(s) sur <?= count($partie['questions']) ?> déjà jouée(s)
                <?php endif; ?>
                · <?= e(isset($partie['rattrapage']) ? 'rattrapage des erreurs' : (MODES[$partie['mode']] ?? '')) ?>
            </p>
        <?php else: ?>
            <form method="post" action="/partie">
                <?= champCsrf() ?>
                <input type="hidden" name="mode" value="<?= e(PARTIE_RAPIDE['mode']) ?>">
                <input type="hidden" name="n" value="<?= (int) PARTIE_RAPIDE['n'] ?>">
                <input type="hidden" name="chrono" value="<?= (int) PARTIE_RAPIDE['chrono'] ?>">
                <input type="hidden" name="rapide" value="1">
                <?php foreach ($tousLesChapitres as $ch): ?>
                    <input type="hidden" name="chapitres[]" value="<?= e($ch) ?>">
                <?php endforeach; ?>
                <button class="btn primaire grand" type="submit"><span aria-hidden="true">▶</span> Partie rapide</button>
            </form>
            <p class="hero-note"><?= (int) PARTIE_RAPIDE['n'] ?> questions sur tout le programme, corrigées une à une.<?= $nbSessions === 0 ? ' Idéal pour découvrir le quiz.' : '' ?></p>
        <?php endif; ?>
        <div class="hero-liens">
            <a class="btn" href="#configurer">Personnaliser</a>
            <a class="btn" href="/fiches">Fiches de révision</a>
        </div>
        <?php if ($enCours): ?>
            <form class="hero-abandon" method="post" action="/abandonner" data-confirmer="Terminer la partie en cours maintenant ? Les questions restantes ne seront pas posées.">
                <?= champCsrf() ?>
                <button class="lien" type="submit">Terminer la partie en cours</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="carte apprendre" aria-labelledby="titre-apprendre">
    <span class="tuile-icone" aria-hidden="true"><?= e($conseille !== null ? ICONES_BLOCS[blocDuChapitre($conseille)] ?? '' : '{ }') ?></span>
    <div>
        <?php if ($conseille === null): ?>
            <h2 id="titre-apprendre">Le cours : tous les chapitres sont validés</h2>
            <p class="discret"><?= count(TITRES) ?> leçons à relire quand tu veux, du premier <code>echo</code> à l'API sécurisée.</p>
        <?php else: ?>
            <h2 id="titre-apprendre"><?= $nbSessions === 0 ? 'Tu débutes ? Commence par le cours' : 'Continue le cours' ?></h2>
            <p class="discret">
                Chapitre conseillé : <span class="num"><?= e($conseille) ?></span> <?= e(TITRES[$conseille]) ?>
                <?= $coursConseille !== null ? '· ' . (int) $coursConseille['minutes'] . ' min de lecture, ' : '· ' ?>puis un test de 10 questions.
            </p>
        <?php endif; ?>
    </div>
    <div class="actions">
        <?php if ($conseille !== null && $coursConseille !== null): ?>
            <a class="btn<?= $nbSessions === 0 ? ' primaire' : '' ?>" href="/cours?ch=<?= e($conseille) ?>">Lire le chapitre <?= e($conseille) ?></a>
        <?php endif; ?>
        <a class="btn" href="/cours">Sommaire du cours</a>
    </div>
</section>

<?php if ($fragiles !== []): ?>
    <section class="carte conseil" aria-labelledby="titre-conseil">
        <div class="conseil-texte">
            <h2 id="titre-conseil">À consolider en priorité</h2>
            <ul class="liste-fragiles">
                <?php foreach ($fragiles as $f): ?>
                    <li>
                        <span class="num"><?= e($f['ch']) ?></span> <a href="/fiches?ch=<?= e($f['ch']) ?>"><?= e($f['titre']) ?></a>
                        · <span class="taux <?= e(classeTaux($f['taux'])) ?>"><?= (int) $f['taux'] ?> %</span>
                        <span class="discret">de réussite sur <?= (int) $f['essais'] ?> réponses</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form method="post" action="/partie">
            <?= champCsrf() ?>
            <input type="hidden" name="mode" value="normal">
            <input type="hidden" name="n" value="<?= NOMBRE_QUESTIONS_DEFAUT ?>">
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

<form id="configurer" class="carte formulaire configurateur" method="post" action="/partie" data-formulaire-partie
      data-a-revoir="<?= (int) $aRevoir ?>" aria-labelledby="titre-configurer">
    <?= champCsrf() ?>
    <h2 id="titre-configurer">Personnaliser ma partie</h2>

    <fieldset class="etape">
        <legend><span class="etape-num" aria-hidden="true">1</span> Que veux-tu réviser ?</legend>
        <?php if (isset($erreurs['chapitres'])): ?><p class="erreur-champ"><?= e($erreurs['chapitres']) ?></p><?php endif; ?>

        <div class="tuiles" data-tuiles hidden>
            <?php foreach (BLOCS as $numeroBloc => [$nomBloc]): ?>
                <?php [$etat, $detail] = $detailTuile(chapitresDuBloc($numeroBloc)); ?>
                <button type="button" class="tuile" data-tuile="<?= (int) $numeroBloc ?>" aria-pressed="<?= $etat ?>">
                    <span class="tuile-icone" aria-hidden="true"><?= e(ICONES_BLOCS[$numeroBloc] ?? '') ?></span>
                    <span class="tuile-texte">
                        <span class="tuile-nom"><?= e($nomBloc) ?></span>
                        <span class="tuile-detail" data-tuile-detail><?= e($detail) ?></span>
                    </span>
                    <span class="tuile-coche" aria-hidden="true"></span>
                </button>
            <?php endforeach; ?>
        </div>
        <p class="outils-selection" data-outils-selection hidden>
            <button type="button" class="lien" data-tout="1">Tout sélectionner</button>
            <button type="button" class="lien" data-tout="0">Tout désélectionner</button>
        </p>

        <details class="choix-chapitres" data-choix-chapitres open>
            <summary>Choisir chapitre par chapitre</summary>
            <div class="blocs">
                <?php foreach (BLOCS as $numeroBloc => [$nomBloc, $debut, $fin]): ?>
                    <div class="bloc" data-bloc="<?= (int) $numeroBloc ?>">
                        <div class="bloc-entete">
                            <h3><?= e($nomBloc) ?> <span class="discret"><?= sprintf('%02d', $debut) ?>–<?= sprintf('%02d', $fin) ?></span></h3>
                        </div>
                        <div class="chapitres">
                            <?php foreach (chapitresDuBloc($numeroBloc) as $ch): ?>
                                <label class="chapitre">
                                    <input type="checkbox" name="chapitres[]" value="<?= e($ch) ?>"
                                           data-nb="<?= (int) ($comptes[$ch] ?? 0) ?>" data-cours="<?= (int) ($comptesCours[$ch] ?? 0) ?>" data-gen="<?= (int) ($generes[$ch] ?? 0) ?>"<?= $coche(in_array($ch, $choisis, true)) ?>>
                                    <span class="num"><?= e($ch) ?></span>
                                    <span class="nom"><?= e(TITRES[$ch]) ?></span>
                                    <span class="nb"><?= (int) ($comptes[$ch] ?? 0) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </fieldset>

    <fieldset class="etape">
        <legend><span class="etape-num" aria-hidden="true">2</span> Comment veux-tu jouer ?</legend>
        <div class="modes">
            <label class="mode">
                <input type="radio" name="mode" value="normal"<?= $coche($mode === 'normal') ?>>
                <span class="mode-titre">Entraînement</span>
                <span class="mode-desc">La correction et l'explication après chaque réponse. Le meilleur mode pour apprendre.</span>
            </label>
            <label class="mode">
                <input type="radio" name="mode" value="examen"<?= $coche($mode === 'examen') ?>>
                <span class="mode-titre">Examen</span>
                <span class="mode-desc">Aucune correction pendant la partie : note et corrigé complet à la fin.</span>
            </label>
            <label class="mode">
                <input type="radio" name="mode" value="serie"<?= $coche($mode === 'serie') ?>>
                <span class="mode-titre">Série</span>
                <span class="mode-desc">Jusqu'à la première erreur (<?= SERIE_MAX ?> questions au plus).<?= $record > 0 ? ' Ton record : ' . (int) $record . '.' : '' ?></span>
            </label>
            <label class="mode<?= $aRevoir === 0 ? ' indisponible' : '' ?>">
                <input type="radio" name="mode" value="revision"<?= $coche($mode === 'revision') ?><?= $aRevoir === 0 ? ' disabled' : '' ?>>
                <span class="mode-titre">Révision</span>
                <span class="mode-desc"><?= $aRevoir > 0 ? 'Rejoue seulement tes erreurs passées (' . (int) $aRevoir . ' à revoir).' : 'Aucune erreur à revoir pour l\'instant : joue d\'abord une partie.' ?></span>
            </label>
        </div>
    </fieldset>

    <fieldset class="etape">
        <legend><span class="etape-num" aria-hidden="true">3</span> Réglages</legend>
        <div class="reglages">
            <fieldset class="reglage" data-reglage-nombre>
                <legend>Nombre de questions</legend>
                <div class="segments">
                    <?php foreach (NOMBRES_QUESTIONS as $nombre): ?>
                        <label class="segment"><input type="radio" name="n" value="<?= $nombre ?>"<?= $coche((int) $valeurs['n'] === $nombre) ?>><span><?= $nombre ?></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="aide" data-aide-serie hidden>En série, la partie dure jusqu'à la première erreur.</p>
                <?php if (isset($erreurs['n'])): ?><p class="erreur-champ"><?= e($erreurs['n']) ?></p><?php endif; ?>
            </fieldset>

            <fieldset class="reglage">
                <legend>Minuteur par question</legend>
                <div class="segments">
                    <?php foreach (CHRONOS as $secondes): ?>
                        <label class="segment"><input type="radio" name="chrono" value="<?= $secondes ?>"<?= $coche($chronoChoisi === $secondes) ?>><span><?= $secondes === 0 ? 'Sans' : $secondes . ' s' ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($erreurs['chrono'])): ?><p class="erreur-champ"><?= e($erreurs['chrono']) ?></p><?php endif; ?>
            </fieldset>

            <div class="reglage">
                <label class="interrupteur">
                    <input type="checkbox" name="cours" value="1"<?= $coche($valeurs['cours']) ?>>
                    <span>Seulement les questions tirées des supports de référence <span class="discret">(<?= (int) $nbCours ?>)</span></span>
                </label>
            </div>

            <details class="avance"<?= isset($erreurs['graine']) || $valeurs['graine_texte'] !== '' ? ' open' : '' ?>>
                <summary>Rejouer une partie précise (graine)</summary>
                <div class="champ">
                    <label for="graine">Graine</label>
                    <input id="graine" name="graine" type="text" inputmode="numeric" autocomplete="off" maxlength="10" value="<?= e($valeurs['graine_texte']) ?>" placeholder="ex : 123456">
                    <p class="aide">Le numéro affiché dans le bilan d'une partie : il redonne exactement les mêmes questions. Laisse vide pour un tirage neuf.</p>
                    <?php if (isset($erreurs['graine'])): ?><p class="erreur-champ"><?= e($erreurs['graine']) ?></p><?php endif; ?>
                </div>
            </details>
        </div>
    </fieldset>

    <div class="barre-depart">
        <div class="barre-texte">
            <p class="barre-resume" data-resume aria-live="polite"><?= e($resume) ?></p>
            <p class="barre-dispo" data-dispo><?= (int) $dispo ?> questions disponibles<?= $dispoGeneres > 0 ? ' (+ ' . (int) $dispoGeneres . ' générées)' : '' ?></p>
            <p class="barre-alerte" data-alerte role="alert" hidden></p>
        </div>
        <button class="btn primaire grand" type="submit">Commencer</button>
    </div>
</form>

<details class="carte pourquoi">
    <summary><h2>Comment fonctionne le quiz ?</h2></summary>
    <ul>
        <li><strong>Des questions neuves</strong> : un nouveau tirage à chaque partie, dans un ordre mélangé ; les questions de la partie précédente sont presque exclues, tes erreurs reviennent plus souvent<?= $nbSessions > 0 ? ' (' . (int) $nbSessions . ' partie(s) déjà enregistrée(s))' : '' ?>.</li>
        <li><strong>Des questions générées</strong> : environ une sur quatre a des valeurs neuves (code à lire, calcul, sortie d'un programme), et sa réponse est calculée par PHP.</li>
        <li><strong>Le minuteur</strong> est vérifié par le serveur : recharger la page ne le remet pas à zéro, et une réponse arrivée trop tard ne compte pas.</li>
        <li><strong>Le cours</strong> explique chaque notion du quiz, du premier <code>echo</code> à l'API sécurisée, en <?= count(TITRES) ?> chapitres ; chacun se termine par un test de 10 questions.</li>
        <li><strong>Les fiches</strong> rassemblent toutes les réponses expliquées, chapitre par chapitre, avec une recherche par mot-clé.</li>
    </ul>
</details>
