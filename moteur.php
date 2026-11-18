<?php

declare(strict_types=1);

/**
 * Moteur du quiz : tout ce qui ne dépend ni du terminal ni du web.
 *
 *   - lecture de la banque de questions (banque/*.txt, mise en cache pour le site) et des générateurs ;
 *   - tirage pondéré selon l'historique (des questions neuves à chaque session) ;
 *   - correction des réponses (choix unique, cases multiples, réponse libre) ;
 *   - historique des sessions (historique.json) : scores, séries, temps de réponse.
 *
 * Il est utilisé par le quiz en ligne de commande (quiz.php) ET par le site web (site/) :
 * deux interfaces, les mêmes règles.
 */

mb_internal_encoding('UTF-8');

/** Historique des parties : historique.json à côté de ce fichier, ou le chemin donné par la variable d'environnement QUIZ_HISTORIQUE. */
if (!defined('FICHIER_HISTORIQUE')) {
    $perso = getenv('QUIZ_HISTORIQUE');
    define('FICHIER_HISTORIQUE', is_string($perso) && $perso !== '' ? $perso : __DIR__ . '/historique.json');
    unset($perso);
}
const DOSSIER_BANQUE = __DIR__ . '/banque';

/** Cache de la banque analysée (fichier PHP généré, servi par OPcache) : utilisé par le site, jamais versionné. */
const DOSSIER_CACHE = __DIR__ . '/cache';

/** Nombre de questions d'une partie quand on ne précise rien (site et terminal). */
const NOMBRE_QUESTIONS_DEFAUT = 20;

/** Nombre maximal de questions d'une partie en mode « série » (elle s'arrête à la première erreur). */
const SERIE_MAX = 50;

/** Marge (en secondes) accordée au-delà du minuteur : temps de transfert de la page et de la réponse. */
const TOLERANCE_CHRONO = 2;

const TITRES = [
    '00' => 'Premiers pas et débogage',
    '01' => 'Variables, types et opérateurs',
    '02' => 'Conditions et comparaisons',
    '03' => 'Boucles',
    '04' => 'Tableaux',
    '05' => 'Chaînes de caractères',
    '06' => 'Fonctions et récursivité',
    '07' => 'Erreurs et exceptions',
    '08' => 'Classes et objets',
    '09' => 'Héritage et polymorphisme',
    '10' => 'Interfaces, traits et énumérations',
    '11' => 'Namespaces et membres statiques',
    '12' => 'HTTP et superglobales',
    '13' => 'Formulaires et validation',
    '14' => 'Cookies et sessions',
    '15' => 'Sécurité des applications web',
    '16' => 'Fichiers, dates et JSON',
    '17' => 'SQL et modèle relationnel',
    '18' => 'PDO : connexion et lecture',
    '19' => 'PDO : écriture et transactions',
    '20' => 'Composer et autoload',
    '21' => 'MVC, routage et frameworks',
    '22' => 'API REST',
    '23' => 'Sécuriser une API : JWT',
    '24' => 'JavaScript : les fondamentaux',
    '25' => 'JavaScript : fonctions, objets et stockage',
    '26' => 'JavaScript : DOM et événements',
    '27' => 'JavaScript : asynchrone et fetch',
];

const BLOCS = [
    1 => ['Bases du langage', 0, 7],
    2 => ['Programmation orientée objet', 8, 11],
    3 => ['PHP et le web', 12, 15],
    4 => ['Données et persistance', 16, 19],
    5 => ['Architecture et API', 20, 23],
    6 => ['JavaScript', 24, 27],
];

/**
 * Chapitres adossés à un cours précis (indiqué dans la correction pour savoir quoi relire).
 * Les autres chapitres s'appuient sur plusieurs supports : voir SOURCES.md.
 */
const COURS_DU_CHAPITRE = [
    '12' => 'PHP interactif : formulaires, superglobales, cookies et sessions',
    '13' => 'PHP interactif : formulaires, superglobales, cookies et sessions',
    '14' => 'PHP interactif : formulaires, superglobales, cookies et sessions',
    '18' => 'PHP et les données persistantes (PDO)',
    '19' => 'PHP et les données persistantes (PDO)',
    '21' => 'MVC : comprendre avant d\'utiliser (Slim 4)',
    '22' => 'Architecture REST et API avec Slim 4',
    '23' => 'Sécuriser une API REST : JWT et authentification',
    '24' => 'JavaScript Vanilla : les fondamentaux',
    '25' => 'JavaScript Vanilla : approfondissement',
    '26' => 'JavaScript Vanilla : le DOM et les événements',
];

/** « 12 — Sessions et sécurité » (pour les messages « à revoir »). */
function libelleChapitre(string $ch): string
{
    return $ch . ' — ' . (TITRES[$ch] ?? '?');
}

/** Phrase « À revoir : … » d'une correction (chapitre, et cours associé s'il y en a un). */
function conseilRevision(string $ch): string
{
    $texte = 'chapitre ' . libelleChapitre($ch);
    if (isset(COURS_DU_CHAPITRE[$ch])) {
        $texte .= ' — cours « ' . COURS_DU_CHAPITRE[$ch] . ' »';
    }

    return $texte;
}

// ───────────────────────────── Aléa (graine rejouable) ─────────────────────────────

function initialiserAlea(int $graine): void
{
    mt_srand($graine);
}

function alea(int $min, int $max): int
{
    return mt_rand($min, $max);
}

/** @param list<mixed> $liste @return list<mixed> */
function melanger(array $liste): array
{
    $liste = array_values($liste);
    for ($i = count($liste) - 1; $i > 0; $i--) {
        $j = alea(0, $i);
        [$liste[$i], $liste[$j]] = [$liste[$j], $liste[$i]];
    }

    return $liste;
}

/** @param list<mixed> $liste */
function tirer(array $liste): mixed
{
    return $liste[alea(0, count($liste) - 1)];
}

// ───────────────────────────── Fabrique de questions ─────────────────────────────

/** Convertit un texte ou une liste de lignes (« |code » = ligne de code) en segments affichables. */
function segments(array|string $q): array
{
    $lignes = is_array($q) ? $q : [$q];
    $segments = [];
    foreach ($lignes as $l) {
        if (str_starts_with($l, '|')) {
            $segments[] = ['code' => true, 't' => (string) preg_replace('/^\| ?/', '', $l)];
        } else {
            $segments[] = ['code' => false, 't' => $l];
        }
    }

    return $segments;
}

function identifiantQuestion(array $segments): string
{
    return 'q' . substr(md5(implode("\n", array_column($segments, 't'))), 0, 10);
}

/** @param list<string> $mauvaises */
function creerQcm(string $ch, array|string $q, string $bonne, array $mauvaises, string $why, bool $cours = false): array
{
    $seg = segments($q);
    $choix = [['t' => $bonne, 'ok' => true]];
    foreach ($mauvaises as $m) {
        $choix[] = ['t' => $m, 'ok' => false];
    }

    return ['id' => identifiantQuestion($seg), 'ch' => $ch, 'type' => 'qcm', 'q' => $seg, 'choix' => $choix,
        'acceptees' => [], 'why' => $why, 'cours' => $cours, 'gen' => false, 'indice' => ''];
}

/** @param list<string> $bonnes @param list<string> $mauvaises */
function creerMulti(string $ch, array|string $q, array $bonnes, array $mauvaises, string $why, bool $cours = false): array
{
    $seg = segments($q);
    $choix = [];
    foreach ($bonnes as $b) {
        $choix[] = ['t' => $b, 'ok' => true];
    }
    foreach ($mauvaises as $m) {
        $choix[] = ['t' => $m, 'ok' => false];
    }

    return ['id' => identifiantQuestion($seg), 'ch' => $ch, 'type' => 'multi', 'q' => $seg, 'choix' => $choix,
        'acceptees' => [], 'why' => $why, 'cours' => $cours, 'gen' => false, 'indice' => ''];
}

/** @param list<string>|string $acceptees */
function creerSaisie(string $ch, array|string $q, array|string $acceptees, string $why, bool $cours = false, string $indice = ''): array
{
    $seg = segments($q);

    return ['id' => identifiantQuestion($seg), 'ch' => $ch, 'type' => 'saisie', 'q' => $seg, 'choix' => [],
        'acceptees' => is_array($acceptees) ? $acceptees : [$acceptees], 'why' => $why, 'cours' => $cours, 'gen' => false, 'indice' => $indice];
}

// ───────────────────────────── Lecture de la banque (fichiers .txt) ─────────────────────────────

/**
 * Format d'une question (voir banque/README.md) :
 *
 *   # 03                      chapitre des questions suivantes
 *   Q: Texte de la question [cours]
 *   |    ligne de code        (affichée comme du code)
 *   + bonne réponse           (une seule « + » = choix unique ; plusieurs = cases multiples)
 *   - mauvaise réponse
 *   = réponse libre | variante (question à saisie libre)
 *   ? explication
 *
 * La banque analysée est mémorisée pour la durée du processus. Pour le site web, elle est aussi écrite dans un
 * fichier PHP de cache (dossier cache/) que l'OPcache garde en mémoire : les fichiers texte ne sont relus qu'après
 * une modification. Le terminal et les tests lisent toujours les fichiers texte (aucun fichier écrit).
 *
 * @return array{0: list<array<string,mixed>>, 1: list<string>}
 */
function chargerBanque(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $fichiers = glob(DOSSIER_BANQUE . '/*.txt') ?: [];
    sort($fichiers);
    if (PHP_SAPI === 'cli' || getenv('QUIZ_CACHE') === '0') {
        return $memo = analyserBanque($fichiers);
    }

    // Empreinte : chemin, date et taille de chaque fichier, plus la version du moteur (le format peut évoluer).
    $empreinte = [__FILE__ . '@' . (int) filemtime(__FILE__)];
    foreach ($fichiers as $f) {
        $empreinte[] = $f . '@' . (int) filemtime($f) . '@' . (int) filesize($f);
    }
    $cache = DOSSIER_CACHE . '/banque-' . md5(implode('|', $empreinte)) . '.php';
    if (is_file($cache)) {
        try {
            $donnees = include $cache;
            if (is_array($donnees) && isset($donnees[0], $donnees[1])) {
                return $memo = $donnees;
            }
        } catch (Throwable) {
            // cache illisible : on relit les fichiers texte ci-dessous
        }
    }

    $memo = analyserBanque($fichiers);
    ecrireCacheBanque($cache, $memo);

    return $memo;
}

/** Écrit le cache de manière atomique (fichier temporaire puis renommage) et supprime les anciennes versions. */
function ecrireCacheBanque(string $cache, array $donnees): void
{
    if (!is_dir(DOSSIER_CACHE) && !@mkdir(DOSSIER_CACHE, 0775, true) && !is_dir(DOSSIER_CACHE)) {
        return; // dossier non accessible en écriture : le site fonctionne, simplement sans cache
    }
    $temporaire = $cache . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($temporaire, "<?php\n// Cache généré automatiquement depuis banque/*.txt : ne pas modifier.\nreturn " . var_export($donnees, true) . ";\n") === false) {
        return;
    }
    if (!@rename($temporaire, $cache)) {
        @unlink($temporaire);

        return;
    }
    foreach (glob(DOSSIER_CACHE . '/banque-*.php') ?: [] as $ancien) {
        if ($ancien !== $cache) {
            @unlink($ancien);
        }
    }
}

/**
 * Analyse les fichiers texte de la banque.
 *
 * @param list<string> $fichiers
 * @return array{0: list<array<string,mixed>>, 1: list<string>}
 */
function analyserBanque(array $fichiers): array
{
    $questions = [];
    $erreurs = [];

    foreach ($fichiers as $fichier) {
        $nom = basename($fichier);
        $lignes = file($fichier, FILE_IGNORE_NEW_LINES) ?: [];
        $chapitre = null;
        $c = null; // question en cours
        $derniere = '';

        $finir = static function () use (&$c, &$questions, &$erreurs, $nom): void {
            if ($c === null) {
                return;
            }
            $where = "$nom:{$c['ligne']}";
            $plus = count(array_filter($c['choix'], static fn (array $x): bool => $x['ok']));
            $moins = count($c['choix']) - $plus;
            if ($c['chapitre'] === null || !isset(TITRES[$c['chapitre']])) {
                $erreurs[] = "$where : chapitre manquant ou inconnu";
            } elseif ($c['acceptees'] !== []) {
                if ($c['choix'] !== []) {
                    $erreurs[] = "$where : mélange de « = » et de choix (+/-)";
                }
                $type = 'saisie';
            } elseif ($plus === 1 && $moins >= 1) {
                $type = 'qcm';
            } elseif ($plus >= 2 && $moins >= 1) {
                $type = 'multi';
            } else {
                $erreurs[] = "$where : il faut 1 « + » et au moins 1 « - » (ou plusieurs « + », ou une ligne « = »)";
            }
            if (trim($c['why']) === '') {
                $erreurs[] = "$where : explication (« ? ») manquante";
            }
            if (isset($type) && $c['chapitre'] !== null && isset(TITRES[$c['chapitre']])) {
                $seg = segments($c['texte']);
                $textes = array_column($c['choix'], 't');
                if (count($textes) !== count(array_unique($textes))) {
                    $erreurs[] = "$where : propositions en double";
                }
                $questions[] = [
                    'id' => identifiantQuestion($seg), 'ch' => $c['chapitre'], 'type' => $type, 'q' => $seg,
                    'choix' => $c['choix'], 'acceptees' => $c['acceptees'], 'why' => trim($c['why']),
                    'cours' => $c['cours'], 'gen' => false, 'indice' => $c['indice'], 'source' => $where,
                ];
            }
            $c = null;
        };

        foreach ($lignes as $i => $brut) {
            $n = $i + 1;
            $l = rtrim($brut, "\r\n ");
            if ($l === '' || str_starts_with($l, '//')) {
                continue;
            }
            if (preg_match('/^#\s*(\d{2})\b/', $l, $m) === 1) {
                $finir();
                $chapitre = $m[1];
                continue;
            }
            if (str_starts_with($l, 'Q:')) {
                $finir();
                $texte = trim(substr($l, 2));
                $cours = false;
                if (preg_match('/\s*\[cours\]$/', $texte) === 1) {
                    $cours = true;
                    $texte = (string) preg_replace('/\s*\[cours\]$/', '', $texte);
                }
                $c = ['ligne' => $n, 'chapitre' => $chapitre, 'texte' => [$texte], 'choix' => [], 'acceptees' => [],
                    'why' => '', 'cours' => $cours, 'indice' => ''];
                $derniere = 'q';
                continue;
            }
            if ($c === null) {
                $erreurs[] = "$nom:$n : ligne hors question";
                continue;
            }
            if (str_starts_with($l, '|')) {
                $c['texte'][] = $l;
                $derniere = 'q';
            } elseif (str_starts_with($l, '+ ') || str_starts_with($l, '- ')) {
                $c['choix'][] = ['t' => trim(substr($l, 2)), 'ok' => $l[0] === '+'];
                $derniere = 'c';
            } elseif (str_starts_with($l, '= ')) {
                foreach (explode('|', substr($l, 2)) as $variante) {
                    if (trim($variante) !== '') {
                        $c['acceptees'][] = trim($variante);
                    }
                }
                $derniere = 'a';
            } elseif (str_starts_with($l, 'i: ')) {
                $c['indice'] = trim(substr($l, 3));
            } elseif (str_starts_with($l, '? ')) {
                $c['why'] .= ($c['why'] === '' ? '' : ' ') . trim(substr($l, 2));
                $derniere = 'e';
            } elseif ($derniere === 'e') {
                $c['why'] .= ' ' . trim($l);
            } elseif ($derniere === 'c') {
                $k = count($c['choix']) - 1;
                $c['choix'][$k]['t'] .= ' ' . trim($l);
            } else {
                $c['texte'][] = trim($l);
            }
        }
        $finir();
    }

    $vus = [];
    foreach ($questions as $q) {
        if (isset($vus[$q['id']])) {
            $erreurs[] = "{$q['source']} : question en double de {$vus[$q['id']]}";
        }
        $vus[$q['id']] = $q['source'];
    }

    return [$questions, $erreurs];
}

// ───────────────────────────── Historique ─────────────────────────────

function chargerHistorique(): array
{
    $vide = ['version' => 1, 'sessions' => [], 'questions' => []];
    if (!is_file(FICHIER_HISTORIQUE)) {
        return $vide;
    }
    $data = json_decode((string) file_get_contents(FICHIER_HISTORIQUE), true);

    return is_array($data) && isset($data['sessions'], $data['questions']) ? $data : $vide;
}

// (l'écriture de l'historique se fait par modifierHistorique(), plus bas, sous verrou exclusif)

// ───────────────────────────── Sélection pondérée ─────────────────────────────

/**
 * Poids d'une question statique pour la session numéro $numSession.
 * Objectif : des questions neuves à chaque session, avec un rappel espacé des erreurs.
 */
function poidsQuestion(array $q, array $histo, int $numSession): float
{
    $rec = $histo['questions'][$q['id']] ?? null;
    if ($rec === null) {
        return 3.0; // jamais vue : priorité
    }
    $poids = match (true) {
        ($rec['dernier'] ?? '') === 'ko' => 2.5,       // à retravailler
        ($rec['serie_ok'] ?? 0) >= 3 => 0.4,           // bien maîtrisée
        default => 1.0,
    };
    $ecart = $numSession - (int) ($rec['session'] ?? 0);
    if ($ecart <= 1) {
        $poids *= 0.05; // vue à la session précédente : quasi exclue
    } elseif ($ecart === 2) {
        $poids *= 0.3;
    }

    return max($poids, 0.001);
}

/**
 * Tirage pondéré sans remise.
 *
 * @param list<array<string,mixed>> $pool
 * @param list<float> $poids
 * @return list<array<string,mixed>>
 */
function tirageSansRemise(array $pool, array $poids, int $combien): array
{
    $pool = array_values($pool);
    $poids = array_values($poids);
    $choisies = [];
    while ($combien > 0 && $pool !== []) {
        $total = (int) round(array_sum($poids) * 1000);
        $r = alea(1, max(1, $total));
        $acc = 0;
        $index = count($pool) - 1;
        foreach ($poids as $i => $p) {
            $acc += (int) round($p * 1000);
            if ($r <= $acc) {
                $index = $i;
                break;
            }
        }
        $choisies[] = $pool[$index];
        array_splice($pool, $index, 1);
        array_splice($poids, $index, 1);
        $combien--;
    }

    return $choisies;
}

/** Évite deux questions consécutives du même chapitre quand c'est possible. */
function espacerChapitres(array $questions): array
{
    $meilleur = $questions;
    $meilleurScore = PHP_INT_MAX;
    for ($essai = 0; $essai < 60; $essai++) {
        $candidat = melanger($questions);
        $conflits = 0;
        for ($i = 1; $i < count($candidat); $i++) {
            if ($candidat[$i]['ch'] === $candidat[$i - 1]['ch']) {
                $conflits++;
            }
        }
        if ($conflits < $meilleurScore) {
            $meilleur = $candidat;
            $meilleurScore = $conflits;
            if ($conflits === 0) {
                break;
            }
        }
    }

    return $meilleur;
}

/**
 * @param array<string,array{0:string,1:callable}> $generateurs
 * @return list<array<string,mixed>>
 */
function preparerSession(array $statiques, array $generateurs, int $n, array $histo, int $numSession, array $options): array
{
    $revision = $options['revision'];
    if ($revision) {
        $pool = array_values(array_filter(
            $statiques,
            static fn (array $q): bool => (($histo['questions'][$q['id']]['dernier'] ?? '') === 'ko')
        ));
        $poids = array_fill(0, count($pool), 1.0);
        $choisies = tirageSansRemise($pool, $poids, $n);

        return espacerChapitres($choisies);
    }

    // 1) combien de questions générées (≈ 25 %), au plus une par générateur
    $nbGen = 0;
    $gens = [];
    if (!$options['cours'] && $generateurs !== []) {
        $gens = array_keys($generateurs);
        $souhait = max(1, (int) round($n * 0.25));
        $nbGen = min($souhait, count($gens));
        if (count($statiques) < $n - $nbGen) {
            $nbGen = min(count($gens), $n - count($statiques));
        }
    }
    $nbStat = min(count($statiques), $n - $nbGen);

    // 2) questions statiques : tirage pondéré selon l'historique
    $poids = array_map(static fn (array $q): float => poidsQuestion($q, $histo, $numSession), $statiques);
    $choisies = tirageSansRemise($statiques, $poids, $nbStat);

    // 3) questions générées : chiffres neufs à chaque appel
    if ($nbGen > 0) {
        foreach (array_slice(melanger($gens), 0, $nbGen) as $nom) {
            $q = ($generateurs[$nom][1])();
            $q['ch'] = $generateurs[$nom][0];
            $q['gen'] = true;
            $q['id'] = 'gen:' . $nom;
            $choisies[] = $q;
        }
    }

    return espacerChapitres($choisies);
}

// ───────────────────────────── Réponses libres ─────────────────────────────

function normaliserReponse(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'",
    ]);
    $s = (string) preg_replace('/\s+/u', '', $s);
    $s = trim($s, "\"'`");
    $s = (string) preg_replace('/\(\)$/', '', $s);
    $s = str_replace(',', '.', $s);
    $s = (string) preg_replace('/(?<=\d)\.0+$/', '', $s);

    return $s;
}

function reponseLibreCorrecte(string $saisie, array $acceptees): bool
{
    $a = normaliserReponse($saisie);
    if ($a === '') {
        return false;
    }
    foreach ($acceptees as $attendue) {
        $b = normaliserReponse($attendue);
        if ($a === $b) {
            return true;
        }
        if (is_numeric($a) && is_numeric($b) && abs((float) $a - (float) $b) < 1e-9) {
            return true;
        }
    }

    return false;
}

// ───────────────────────────── Correction ─────────────────────────────

/** Lettre affichée devant une proposition : 0 → A, 1 → B… */
function lettre(int $i): string
{
    return chr(ord('A') + $i);
}

/** Texte « bonne réponse » d'une question. */
function bonneReponse(array $q): string
{
    if ($q['type'] === 'saisie') {
        return implode('  ou  ', $q['acceptees']);
    }
    $parts = [];
    foreach ($q['choix'] as $i => $c) {
        if ($c['ok']) {
            $parts[] = lettre($i) . ') ' . $c['t'];
        }
    }

    return implode('  +  ', $parts);
}

/**
 * Vrai si les propositions cochées (indices dans l'ordre d'affichage) sont exactement les bonnes.
 *
 * @param list<int|string> $indices
 */
function evaluerChoix(array $q, array $indices): bool
{
    $attendues = [];
    foreach ($q['choix'] as $i => $c) {
        if ($c['ok']) {
            $attendues[] = $i;
        }
    }
    $donnees = array_values(array_unique(array_map('intval', $indices)));
    sort($donnees);

    return $donnees === $attendues;
}

// ───────────────────────────── Banque, générateurs et filtres ─────────────────────────────

/** Générateurs de questions (chargés une seule fois par processus). */
function chargerGenerateurs(): array
{
    static $generateurs = null;

    return $generateurs ??= require __DIR__ . '/generateurs.php';
}

/** @return list<string> chapitres (« 00 »…« 27 ») d'un bloc de 1 à 6 */
function chapitresDuBloc(int $bloc): array
{
    if (!isset(BLOCS[$bloc])) {
        return [];
    }
    [, $debut, $fin] = BLOCS[$bloc];

    return array_map(static fn (int $i): string => sprintf('%02d', $i), range($debut, $fin));
}

/**
 * Restreint la banque à certains chapitres et/ou aux questions tirées des cours PDF.
 *
 * @param list<array<string,mixed>> $statiques
 * @param array<string,array{0:string,1:callable}> $generateurs
 * @param list<string>|null $chapitres
 * @return array{0: list<array<string,mixed>>, 1: array<string,array{0:string,1:callable}>}
 */
function filtrerBanque(array $statiques, array $generateurs, ?array $chapitres, bool $cours): array
{
    if ($chapitres !== null) {
        $statiques = array_values(array_filter($statiques, static fn (array $q): bool => in_array($q['ch'], $chapitres, true)));
        $generateurs = array_filter($generateurs, static fn (array $g): bool => in_array($g[0], $chapitres, true));
    }
    if ($cours) {
        $statiques = array_values(array_filter($statiques, static fn (array $q): bool => $q['cours']));
    }

    return [$statiques, $generateurs];
}

/** Nombre de questions dont la dernière réponse était fausse. */
function nombreARevoir(array $statiques, array $histo): int
{
    return count(array_filter(
        $statiques,
        static fn (array $q): bool => (($histo['questions'][$q['id']]['dernier'] ?? '') === 'ko')
    ));
}

// ───────────────────────────── Historique : écriture protégée et mise à jour ─────────────────────────────

/**
 * Lit, modifie puis réécrit l'historique sous verrou exclusif (deux interfaces ouvertes en même temps
 * ne peuvent pas s'écraser l'une l'autre).
 *
 * @param callable(array): array $modifier
 */
function modifierHistorique(callable $modifier): array
{
    $poignee = fopen(FICHIER_HISTORIQUE, 'c+');
    if ($poignee === false) {
        throw new RuntimeException("Impossible d'ouvrir l'historique du quiz.");
    }
    try {
        flock($poignee, LOCK_EX);
        $contenu = stream_get_contents($poignee);
        $data = json_decode(is_string($contenu) ? $contenu : '', true);
        $histo = is_array($data) && isset($data['sessions'], $data['questions'])
            ? $data
            : ['version' => 1, 'sessions' => [], 'questions' => []];
        $histo = $modifier($histo);
        ftruncate($poignee, 0);
        rewind($poignee);
        fwrite($poignee, (string) json_encode($histo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($poignee);
        flock($poignee, LOCK_UN);
    } finally {
        fclose($poignee);
    }

    return $histo;
}

/**
 * Longueur de la plus longue suite de bonnes réponses consécutives.
 *
 * @param list<string> $statuts ok | ko | passee | temps
 */
function meilleureSerie(array $statuts): int
{
    $meilleure = 0;
    $courante = 0;
    foreach ($statuts as $statut) {
        $courante = $statut === 'ok' ? $courante + 1 : 0;
        $meilleure = max($meilleure, $courante);
    }

    return $meilleure;
}

/** Record personnel : la plus longue série de bonnes réponses enregistrée dans l'historique (0 si aucune). */
function meilleureSerieHistorique(array $histo): int
{
    $record = 0;
    foreach ($histo['sessions'] as $session) {
        $record = max($record, (int) ($session['serie'] ?? 0));
    }

    return $record;
}

/**
 * Ajoute à l'historique les résultats d'une session.
 *
 * @param list<array{question: array<string,mixed>, statut: string}> $resultats statut : ok | ko | passee | temps | quitter
 * @param array<string,mixed> $infos informations ajoutées à la session (mode, minuteur, temps moyen…)
 */
function appliquerResultats(array $histo, array $resultats, int $graine, array $infos = []): array
{
    $repondues = array_values(array_filter($resultats, static fn (array $r): bool => $r['statut'] !== 'quitter'));
    if ($repondues === []) {
        return $histo;
    }
    $numSession = count($histo['sessions']) + 1;
    $parChap = [];
    foreach ($repondues as $r) {
        $q = $r['question'];
        $ok = $r['statut'] === 'ok';
        $parChap[$q['ch']] ??= [0, 0];
        $parChap[$q['ch']][1]++;
        $parChap[$q['ch']][0] += $ok ? 1 : 0;
        if ($q['gen']) {
            continue; // les questions générées changent à chaque fois : inutile de les mémoriser
        }
        $rec = $histo['questions'][$q['id']] ?? ['vu' => 0, 'ok' => 0, 'ko' => 0, 'dernier' => '', 'serie_ok' => 0, 'session' => 0];
        $rec['vu']++;
        $rec['ok'] += $ok ? 1 : 0;
        $rec['ko'] += $ok ? 0 : 1;
        $rec['dernier'] = $ok ? 'ok' : 'ko';
        $rec['serie_ok'] = $ok ? $rec['serie_ok'] + 1 : 0;
        $rec['session'] = $numSession;
        $histo['questions'][$q['id']] = $rec;
    }
    $histo['sessions'][] = [
        'n' => $numSession,
        'date' => date('Y-m-d H:i'),
        'score' => count(array_filter($repondues, static fn (array $r): bool => $r['statut'] === 'ok')),
        'total' => count($repondues),
        'graine' => $graine,
        'serie' => meilleureSerie(array_column($repondues, 'statut')),
        'par_chapitre' => $parChap,
    ] + $infos;

    return $histo;
}
