<?php

declare(strict_types=1);

/**
 * Fiches de révision : toutes les questions d'un chapitre (ou d'une recherche) avec leur réponse et leur explication.
 * Fonctions pures, comme partie.php : elles reçoivent la banque et retournent des tableaux.
 */

/** Longueur maximale d'une recherche, et nombre maximal de fiches affichées pour une recherche. */
const RECHERCHE_LONGUEUR_MAX = 80;
const RECHERCHE_RESULTATS_MAX = 120;

/** Texte comparable pour une recherche : minuscules, sans accents, espaces simplifiés. */
function texteRecherche(string $texte): string
{
    $texte = mb_strtolower($texte);
    $texte = strtr($texte, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'",
    ]);

    return trim((string) preg_replace('/\s+/u', ' ', $texte));
}

/** Tout le texte d'une question (énoncé, code, propositions, réponses, explication), pour la recherche. */
function texteCompletQuestion(array $q): string
{
    return implode(' ', array_merge(array_column($q['q'], 't'), array_column($q['choix'], 't'), $q['acceptees'], [$q['why']]));
}

/**
 * Valide la recherche reçue dans l'URL (?q=…) : une chaîne de longueur raisonnable, sinon rien.
 */
function validerRecherche(mixed $brut): string
{
    if (!is_string($brut)) {
        return '';
    }
    $texte = trim($brut);

    return mb_strlen($texte) > RECHERCHE_LONGUEUR_MAX ? mb_substr($texte, 0, RECHERCHE_LONGUEUR_MAX) : $texte;
}

/**
 * Questions à afficher : celles d'un chapitre, ou celles qui contiennent tous les mots recherchés.
 *
 * @param list<array<string,mixed>> $statiques
 * @return list<array<string,mixed>>
 */
function filtrerFiches(array $statiques, ?string $chapitre, string $recherche): array
{
    if ($chapitre !== null) {
        return array_values(array_filter($statiques, static fn (array $q): bool => $q['ch'] === $chapitre));
    }
    $mots = array_values(array_filter(explode(' ', texteRecherche($recherche)), static fn (string $m): bool => $m !== ''));
    if ($mots === []) {
        return [];
    }
    $trouvees = [];
    foreach ($statiques as $q) {
        $texte = texteRecherche(texteCompletQuestion($q));
        $toutes = true;
        foreach ($mots as $mot) {
            if (!str_contains($texte, $mot)) {
                $toutes = false;
                break;
            }
        }
        if ($toutes) {
            $trouvees[] = $q;
            if (count($trouvees) >= RECHERCHE_RESULTATS_MAX) {
                break;
            }
        }
    }

    return $trouvees;
}
