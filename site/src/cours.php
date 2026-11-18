<?php

declare(strict_types=1);

/**
 * Le cours : un chapitre = un fichier texte au format Markdown simplifié, dans cours/ (à la racine du dépôt).
 *
 * Fonctions pures, comme partie.php : elles lisent les fichiers et renvoient des tableaux. L'affichage HTML est fait
 * par coursHtml() (vues.php), qui échappe TOUT le texte : une balise écrite dans un cours n'est jamais interprétée.
 *
 * Syntaxe reconnue (détaillée dans cours/README.md) :
 *   --- / resume: … / ---      en-tête du fichier (résumé affiché dans le sommaire)
 *   ## Titre, ### Sous-titre   sections ; les « ## » forment le sommaire de la page
 *   lignes consécutives        un paragraphe
 *   - élément / 1. élément     listes (une ligne indentée prolonge l'élément)
 *   ```php … ```               bloc de code coloré
 *   > [!retenir] …             encadré : retenir, attention, astuce, objectifs, exemple (sinon : note)
 *   | a | b |                  tableau (la ligne |---| sépare l'en-tête)
 *   `code`, **gras**, *italique*, [texte](/chemin ou https://…)   dans le texte
 */

/** Vitesse de lecture retenue pour estimer la durée d'un chapitre (mots par minute, code compris). */
const MOTS_PAR_MINUTE = 160;

/** Chemin du fichier de cours d'un chapitre (« 05 » → cours/05-chaines.md), ou null s'il n'existe pas. */
function fichierCours(string $chapitre): ?string
{
    if (!isset(TITRES[$chapitre])) {
        return null;
    }
    $fichiers = glob(DOSSIER_COURS . '/' . $chapitre . '-*.md') ?: [];
    sort($fichiers);

    return $fichiers[0] ?? null;
}

/**
 * Analyse le texte d'un cours.
 *
 * @return array{meta: array<string,string>, blocs: list<array<string,mixed>>, sommaire: list<array{id:string, texte:string}>, minutes: int}
 */
function analyserCours(string $texte): array
{
    $lignes = preg_split('/\R/u', str_replace("\t", '    ', $texte)) ?: [];
    $meta = [];
    $debut = 0;
    if (trim($lignes[0] ?? '') === '---') {
        for ($debut = 1; $debut < count($lignes) && trim($lignes[$debut]) !== '---'; $debut++) {
            if (preg_match('/^([a-z_]+)\s*:\s*(.*)$/u', $lignes[$debut], $m) === 1) {
                $meta[$m[1]] = trim($m[2]);
            }
        }
        $debut++;
    }
    $corps = array_slice($lignes, $debut);
    $ancres = [];
    $blocs = numeroterTitres(analyserBlocs($corps), $ancres);
    $sommaire = [];
    foreach ($blocs as $bloc) {
        if ($bloc['type'] === 'h2') {
            $sommaire[] = ['id' => $bloc['id'], 'texte' => $bloc['texte']];
        }
    }
    $mots = count(preg_split('/\s+/u', trim(implode("\n", $corps))) ?: []);

    return [
        'meta' => $meta,
        'blocs' => $blocs,
        'sommaire' => $sommaire,
        'minutes' => max(1, (int) round($mots / MOTS_PAR_MINUTE)),
    ];
}

/**
 * Découpe des lignes en blocs (paragraphes, titres, listes, code, encadrés, tableaux).
 *
 * @param list<string> $lignes
 * @return list<array<string,mixed>>
 */
function analyserBlocs(array $lignes): array
{
    $blocs = [];
    $paragraphe = [];
    $fermer = static function () use (&$paragraphe, &$blocs): void {
        if ($paragraphe !== []) {
            $blocs[] = ['type' => 'p', 'texte' => implode(' ', $paragraphe)];
            $paragraphe = [];
        }
    };
    $nombre = count($lignes);

    for ($i = 0; $i < $nombre; $i++) {
        $ligne = rtrim($lignes[$i]);
        if (trim($ligne) === '') {
            $fermer();
            continue;
        }

        // Bloc de code : tout est conservé tel quel jusqu'à la clôture.
        if (preg_match('/^```\s*([a-z0-9]*)\s*$/i', $ligne, $m) === 1) {
            $fermer();
            $code = [];
            for ($i++; $i < $nombre && preg_match('/^```\s*$/', rtrim($lignes[$i])) !== 1; $i++) {
                $code[] = rtrim($lignes[$i]);
            }
            $blocs[] = ['type' => 'code', 'langue' => strtolower($m[1]), 'texte' => implode("\n", $code)];
            continue;
        }

        if (preg_match('/^(#{2,3})\s+(.+)$/u', $ligne, $m) === 1) {
            $fermer();
            $blocs[] = ['type' => strlen($m[1]) === 2 ? 'h2' : 'h3', 'texte' => trim($m[2])];
            continue;
        }

        // Encadré : lignes qui commencent par « > » ; son contenu est lui-même analysé (listes, code…).
        if (str_starts_with($ligne, '>')) {
            $fermer();
            $interieur = [];
            for (; $i < $nombre && str_starts_with(rtrim($lignes[$i]), '>'); $i++) {
                $interieur[] = (string) preg_replace('/^> ?/', '', rtrim($lignes[$i]));
            }
            $i--;
            $genre = 'note';
            $titre = '';
            if ($interieur !== [] && preg_match('/^\[!([a-z]+)\]\s*(.*)$/u', $interieur[0], $m) === 1) {
                $genre = $m[1];
                $titre = trim($m[2]);
                array_shift($interieur);
            }
            $blocs[] = ['type' => 'encadre', 'genre' => $genre, 'titre' => $titre, 'blocs' => analyserBlocs($interieur)];
            continue;
        }

        if (str_starts_with($ligne, '|')) {
            $fermer();
            $rangees = [];
            for (; $i < $nombre && str_starts_with(trim($lignes[$i]), '|'); $i++) {
                $rangees[] = celluleTableau(trim($lignes[$i]));
            }
            $i--;
            $entete = [];
            $separateur = isset($rangees[1]) && array_filter($rangees[1], static fn (string $c): bool => preg_match('/^:?-{3,}:?$/', $c) !== 1) === [];
            if ($separateur) {
                $entete = $rangees[0];
                $rangees = array_slice($rangees, 2);
            }
            $blocs[] = ['type' => 'tableau', 'entete' => $entete, 'lignes' => $rangees];
            continue;
        }

        if (preg_match('/^([-*]|\d+\.)\s+(.*)$/u', $ligne, $m) === 1) {
            $fermer();
            $ordonnee = !in_array($m[1], ['-', '*'], true);
            $elements = [$m[2]];
            for ($i++; $i < $nombre; $i++) {
                $suite = rtrim($lignes[$i]);
                if (preg_match('/^([-*]|\d+\.)\s+(.*)$/u', $suite, $mm) === 1 && in_array($mm[1], ['-', '*'], true) !== $ordonnee) {
                    $elements[] = $mm[2];
                } elseif ($suite !== '' && preg_match('/^\s{2,}\S/', $suite) === 1) {
                    $elements[count($elements) - 1] .= ' ' . trim($suite);
                } else {
                    break;
                }
            }
            $i--;
            $blocs[] = ['type' => $ordonnee ? 'ol' : 'ul', 'elements' => $elements];
            continue;
        }

        $paragraphe[] = trim($ligne);
    }
    $fermer();

    return $blocs;
}

/**
 * Cellules d'une ligne de tableau. « \| » permet d'écrire une barre verticale dans une cellule (opérateur ||…).
 *
 * @return list<string>
 */
function celluleTableau(string $ligne): array
{
    $ligne = str_replace('\|', "\u{E000}", trim($ligne, " \t"));
    $ligne = (string) preg_replace('/^\||\|$/', '', $ligne);

    return array_map(static fn (string $c): string => str_replace("\u{E000}", '|', trim($c)), explode('|', $ligne));
}

/**
 * Donne un identifiant unique (ancre) à chaque titre, pour le sommaire et les liens directs (#ancre).
 *
 * @param list<array<string,mixed>> $blocs
 * @param array<string,int> $utilisees
 * @return list<array<string,mixed>>
 */
function numeroterTitres(array $blocs, array &$utilisees): array
{
    foreach ($blocs as $k => $bloc) {
        if ($bloc['type'] === 'h2' || $bloc['type'] === 'h3') {
            $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', texteRecherche(strip_tags((string) preg_replace('/`([^`]*)`/', '$1', $bloc['texte'])))), '-');
            $base = $base === '' ? 'section' : $base;
            $utilisees[$base] = ($utilisees[$base] ?? 0) + 1;
            $blocs[$k]['id'] = $utilisees[$base] === 1 ? $base : $base . '-' . $utilisees[$base];
        } elseif ($bloc['type'] === 'encadre') {
            $blocs[$k]['blocs'] = numeroterTitres($bloc['blocs'], $utilisees);
        }
    }

    return $blocs;
}

/** Cours complet d'un chapitre, ou null s'il n'est pas (encore) écrit. */
function lireCours(string $chapitre): ?array
{
    $fichier = fichierCours($chapitre);
    if ($fichier === null) {
        return null;
    }
    $texte = file_get_contents($fichier);

    return $texte === false ? null : analyserCours($texte);
}

/**
 * Sommaire du cours : chaque chapitre, son résumé et sa durée de lecture estimée.
 *
 * @return array<string, array{titre: string, resume: string, minutes: int, ecrit: bool}>
 */
function listeCours(): array
{
    $liste = [];
    foreach (TITRES as $ch => $titre) {
        $ch = (string) sprintf('%02d', (int) $ch);
        $cours = lireCours($ch);
        $liste[$ch] = [
            'titre' => $titre,
            'resume' => $cours['meta']['resume'] ?? '',
            'minutes' => $cours['minutes'] ?? 0,
            'ecrit' => $cours !== null,
        ];
    }

    return $liste;
}

/** Numéro du bloc (BLOCS) qui contient un chapitre (« 09 » → 2, Programmation orientée objet). */
function blocDuChapitre(string $chapitre): int
{
    foreach (BLOCS as $numero => [, $debut, $fin]) {
        if ((int) $chapitre >= $debut && (int) $chapitre <= $fin) {
            return $numero;
        }
    }

    return 1;
}

/**
 * Le prochain chapitre à travailler : le premier, dans l'ordre du cours, jamais testé par le quiz ou réussi à moins
 * de 50 % (à retravailler). Au-delà, on avance : un chapitre « à consolider » se revoit plus tard, sans bloquer la
 * suite du parcours. Null si tous les chapitres ont été testés avec succès.
 *
 * @param array<int|string, array{taux: int|null}> $chapitres statistiquesHistorique()['chapitres']
 */
function chapitreConseille(array $chapitres, int $seuil = 50): ?string
{
    foreach (array_keys(TITRES) as $ch) {
        $taux = $chapitres[$ch]['taux'] ?? null;
        if ($taux === null || $taux < $seuil) {
            return sprintf('%02d', (int) $ch);
        }
    }

    return null;
}
