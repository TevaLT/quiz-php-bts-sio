<?php

declare(strict_types=1);

/** Nombre de parties prises en compte dans la moyenne glissante du graphique de progression. */
const PARTIES_MOYENNE = 5;

/** Nombre maximal de parties affichées dans le graphique de progression. */
const PARTIES_GRAPHIQUE = 30;

/**
 * Statistiques de l'historique (pour la page /stats). Fonction pure : elle reçoit l'historique et la banque.
 *
 * @param list<array<string,mixed>> $statiques
 * @return array{sessions: int, reponses: int, reussite: int, chapitres: array<string,array<string,mixed>>, pires: list<array{ko:int,question:array<string,mixed>}>, progression: list<array<string,mixed>>}
 */
function statistiquesHistorique(array $histo, array $statiques): array
{
    $sessions = $histo['sessions'];
    $ok = array_sum(array_column($sessions, 'score'));
    $total = array_sum(array_column($sessions, 'total'));

    $chapitres = [];
    foreach (TITRES as $ch => $titre) {
        $chapitres[$ch] = ['titre' => $titre, 'banque' => 0, 'vues' => 0, 'essais' => 0, 'reussies' => 0, 'taux' => null];
    }
    $pires = [];
    foreach ($statiques as $q) {
        $chapitres[$q['ch']]['banque']++;
        $rec = $histo['questions'][$q['id']] ?? null;
        if ($rec === null) {
            continue;
        }
        $chapitres[$q['ch']]['vues']++;
        $chapitres[$q['ch']]['essais'] += (int) $rec['ok'] + (int) $rec['ko'];
        $chapitres[$q['ch']]['reussies'] += (int) $rec['ok'];
        if ((int) $rec['ko'] > 0) {
            $pires[] = ['ko' => (int) $rec['ko'], 'question' => $q];
        }
    }
    foreach ($chapitres as $ch => $c) {
        $chapitres[$ch]['taux'] = $c['essais'] > 0 ? (int) round(100 * $c['reussies'] / $c['essais']) : null;
    }
    usort($pires, static fn (array $a, array $b): int => $b['ko'] <=> $a['ko']);

    return [
        'sessions' => count($sessions),
        'reponses' => (int) $total,
        'reussite' => $total > 0 ? (int) round(100 * $ok / $total) : 0,
        'meilleure_serie' => meilleureSerieHistorique($histo),
        'temps_moyen' => tempsMoyenHistorique($sessions),
        'chapitres' => $chapitres,
        'pires' => array_slice($pires, 0, 8),
        'progression' => progressionParties($sessions),
    ];
}

/**
 * Temps moyen par réponse sur les parties où il a été mesuré (pondéré par le nombre de réponses), ou null.
 *
 * @param list<array<string,mixed>> $sessions
 */
function tempsMoyenHistorique(array $sessions): ?float
{
    $somme = 0.0;
    $nombre = 0;
    foreach ($sessions as $s) {
        if (isset($s['duree_moyenne']) && is_numeric($s['duree_moyenne']) && (int) ($s['total'] ?? 0) > 0) {
            $somme += (float) $s['duree_moyenne'] * (int) $s['total'];
            $nombre += (int) $s['total'];
        }
    }

    return $nombre === 0 ? null : round($somme / $nombre, 1);
}

/**
 * Score de chaque partie et moyenne glissante sur les $fenetre dernières parties
 * (pondérée par le nombre de réponses : une partie de 30 questions pèse plus qu'une partie de 5).
 *
 * @param list<array<string,mixed>> $sessions historique['sessions'], de la plus ancienne à la plus récente
 * @return list<array{n:int, date:string, score:int, total:int, pct:int, moyenne:int}> les $max dernières parties
 */
function progressionParties(array $sessions, int $max = PARTIES_GRAPHIQUE, int $fenetre = PARTIES_MOYENNE): array
{
    $valides = array_values(array_filter($sessions, static fn (mixed $s): bool => is_array($s) && (int) ($s['total'] ?? 0) > 0));
    $points = [];
    foreach ($valides as $i => $s) {
        $groupe = array_slice($valides, max(0, $i - $fenetre + 1), min($fenetre, $i + 1));
        $ok = array_sum(array_map(static fn (array $x): int => (int) ($x['score'] ?? 0), $groupe));
        $total = array_sum(array_map(static fn (array $x): int => (int) $x['total'], $groupe));
        $points[] = [
            'n' => (int) ($s['n'] ?? $i + 1),
            'date' => (string) ($s['date'] ?? ''),
            'score' => (int) ($s['score'] ?? 0),
            'total' => (int) $s['total'],
            'pct' => (int) round(100 * (int) ($s['score'] ?? 0) / (int) $s['total']),
            'moyenne' => (int) round(100 * $ok / $total),
        ];
    }

    return array_slice($points, -$max);
}

/**
 * Chapitres à consolider : les plus faibles taux de réussite parmi ceux qui ont reçu assez de réponses.
 *
 * @param array<string,array<string,mixed>> $chapitres statistiquesHistorique()['chapitres']
 * @return list<array{ch:string, titre:string, taux:int, essais:int}>
 */
function chapitresFragiles(array $chapitres, int $max = 3, int $essaisMinimum = 4, int $seuil = 70): array
{
    $liste = [];
    foreach ($chapitres as $ch => $c) {
        if ($c['taux'] !== null && $c['essais'] >= $essaisMinimum && $c['taux'] < $seuil) {
            $liste[] = ['ch' => (string) $ch, 'titre' => (string) $c['titre'], 'taux' => (int) $c['taux'], 'essais' => (int) $c['essais']];
        }
    }
    usort($liste, static fn (array $a, array $b): int => [$a['taux'], $a['ch']] <=> [$b['taux'], $b['ch']]);

    return array_slice($liste, 0, $max);
}
