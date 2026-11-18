---
resume: Lire et écrire des fichiers sans perdre de données, manipuler des dates fiables avec DateTimeImmutable et échanger des données au format JSON.
---

> [!objectifs]
> - Lire et écrire un fichier, entièrement ou ligne par ligne, et éviter les écritures concurrentes.
> - Manipuler des dates avec `DateTimeImmutable`, les fuseaux horaires et les intervalles.
> - Encoder et décoder du JSON en détectant les erreurs.
> - Écrire du code qui dépend de l'heure tout en restant testable.

## Lire et écrire un fichier d'un coup

Pour un fichier de taille raisonnable, deux fonctions suffisent :

```php
$contenu = file_get_contents(__DIR__ . '/notes.txt');    // tout le contenu, dans une chaîne
if ($contenu === false) {
    throw new RuntimeException('Lecture impossible');    // fichier absent ou illisible
}

file_put_contents(__DIR__ . '/notes.txt', "Première ligne\n");                 // remplace le contenu
file_put_contents(__DIR__ . '/journal.txt', $ligne . PHP_EOL, FILE_APPEND);    // ajoute à la fin
```

- `file_get_contents()` retourne le contenu sous forme de **chaîne**, ou `false` en cas d'échec (avec un avertissement) : on vérifie toujours le retour.
- `file_put_contents()` **crée** le fichier s'il n'existe pas et **écrase** son contenu ; avec l'option `FILE_APPEND`, il ajoute à la fin, ce qui convient à un journal.
- `file()` retourne un tableau des lignes ; avec `FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES`, sans sauts de ligne ni lignes vides.
- `is_file()`, `is_dir()`, `file_exists()` testent l'existence ; `unlink()` supprime un fichier.

## Lire ligne par ligne

Un gros fichier (un journal, un export CSV de plusieurs centaines de Mo) ne doit pas être chargé entièrement en mémoire. On l'ouvre avec `fopen()` et on le lit ligne par ligne :

```php
$f = fopen(__DIR__ . '/acces.log', 'r');
if ($f === false) {
    throw new RuntimeException('Ouverture impossible');
}
while (($ligne = fgets($f)) !== false) {
    // traiter $ligne
}
fclose($f);
```

La comparaison `!== false` est indispensable : une ligne qui contient seulement `0` (sans saut de ligne, en fin de fichier) est une chaîne *falsy*, et `while ($ligne = fgets($f))` arrêterait la lecture trop tôt.

Le deuxième argument de `fopen()` est le **mode** :

| Mode | Effet |
|---|---|
| `'r'` | lecture seule |
| `'w'` | écriture : crée le fichier ou **le vide** s'il existe |
| `'a'` | ajout à la fin (crée le fichier s'il n'existe pas) |
| `'x'` | création exclusive : échoue si le fichier existe déjà |
| `'c'` | écriture sans vider le fichier (utile avec un verrou) |

Pour les fichiers CSV, `fgetcsv()` et `fputcsv()` gèrent les séparateurs et les guillemets à ta place.

### Écritures simultanées : flock()

Sur un site, plusieurs requêtes peuvent écrire **en même temps** dans le même fichier : deux écritures peuvent se mélanger, ou l'une peut écraser l'autre. `flock()` pose un **verrou** sur un fichier ouvert : `LOCK_EX` (exclusif) pour écrire, `LOCK_SH` (partagé) pour lire. Les autres processus qui demandent le verrou **attendent** qu'il soit libéré.

```php
$f = fopen($chemin, 'c+');
flock($f, LOCK_EX);               // attend son tour
$donnees = json_decode(stream_get_contents($f) ?: '[]', true);
$donnees[] = $nouvelleEntree;
ftruncate($f, 0);
rewind($f);
fwrite($f, json_encode($donnees));
flock($f, LOCK_UN);
fclose($f);
```

Ce verrou est **consultatif** : il ne protège que contre le code qui utilise aussi `flock()`. Pour des écritures fréquentes et concurrentes, une base de données est plus adaptée (chapitre 17).

## Les dates : timestamps et fuseaux horaires

`time()` retourne le **timestamp Unix** : le nombre de secondes écoulées depuis le 1er janvier 1970 à minuit UTC. C'est pratique pour calculer des durées (`time() + 3600` = dans une heure). `date()` le met en forme :

```php
date_default_timezone_set('Europe/Paris');   // fuseau utilisé par les fonctions de date

echo date('d/m/Y H:i');        // 18/11/2026 14:05
echo date('d/m/Y', 0);         // 01/01/1970 (en UTC)
```

`date_default_timezone_set()` fixe le **fuseau horaire** par défaut du script (on le règle de préférence dans `php.ini`, avec `date.timezone`). Sans lui, les heures affichées peuvent être décalées de une ou deux heures. En base de données et dans les échanges entre systèmes, on stocke de préférence les dates en UTC ou dans un format qui indique le fuseau.

## DateTimeImmutable

Les objets date sont plus sûrs et plus lisibles que les timestamps :

```php
$aujourdhui = new DateTimeImmutable('2026-11-18');
$echeance = $aujourdhui->modify('+30 days');

echo $aujourdhui->format('d/m/Y');   // 18/11/2026 : inchangé
echo $echeance->format('d/m/Y');     // 18/12/2026

$intervalle = $aujourdhui->diff(new DateTimeImmutable('2026-12-25'));
echo $intervalle->days;              // 37 : nombre total de jours
```

Avec `DateTimeImmutable`, chaque modification (`modify()`, `add()`, `sub()`, `setTime()`…) **retourne un nouvel objet** et laisse l'original intact. Avec l'ancienne classe `DateTime`, `modify()` **change l'objet lui-même** : si cet objet était partagé (une date de début passée à une fonction), il est modifié partout, ce qui provoque des bugs difficiles à trouver.

Le calendrier est géré pour toi : `(new DateTimeImmutable('2024-02-28'))->modify('+1 day')` donne le **29/02/2024**, car 2024 est bissextile.

> [!attention]
> `modify('+1 month')` depuis le 31 janvier donne… le **3 mars** (le « 31 février » déborde). Pour « le mois suivant », pars du premier jour du mois ou utilise `modify('last day of next month')`.

Les principaux formats de `format()` : `d` jour, `m` mois, `Y` année sur 4 chiffres, `H` heure (0-23), `i` minutes, `s` secondes, `N` numéro du jour de la semaine (1 = lundi). Pour lire une date dans un format précis, `DateTimeImmutable::createFromFormat('d/m/Y', '18/11/2026')` retourne l'objet, ou `false` si la chaîne ne correspond pas.

### Rendre testable le code qui dépend de l'heure

Une fonction qui crée elle-même `new DateTimeImmutable()` (« maintenant ») donne un résultat différent à chaque exécution : impossible d'écrire un test qui vérifie une échéance précise. On lui passe donc la date courante **en paramètre** :

```php
function dateLimiteRetour(DateTimeImmutable $emprunt, DateTimeImmutable $maintenant): bool
{
    return $maintenant > $emprunt->modify('+21 days');   // en retard ?
}

// dans l'application
dateLimiteRetour($emprunt, new DateTimeImmutable());
// dans un test : une date fixe, un résultat certain
assert(dateLimiteRetour(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-23')) === true);
```

Une date limite qui a des conséquences (expiration d'un lien, fin d'une offre, retard) se calcule et se vérifie **sur le serveur** : l'horloge du navigateur est réglée par l'utilisateur, qui peut la changer.

## JSON

**JSON** (*JavaScript Object Notation*) est le format texte le plus utilisé pour échanger des données entre programmes : API, fichiers de configuration, stockage simple.

```php
$film = ['titre' => 'Le Voyage', 'annee' => 1902, 'genres' => ['aventure', 'fantastique']];

$json = json_encode($film, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$donnees = json_decode($json, true, flags: JSON_THROW_ON_ERROR);   // tableau associatif
```

- `json_encode()` transforme une valeur PHP en texte JSON. Un tableau associatif devient un **objet** JSON `{…}`, une liste un **tableau** `[…]`.
- `json_decode($json, true)` retourne des **tableaux associatifs** : `json_decode('{"age": 36}', true)` donne `['age' => 36]`. Sans le `true`, on obtient des objets `stdClass`.
- Par défaut, les caractères accentués sont encodés en `é` ; l'option `JSON_UNESCAPED_UNICODE` les garde lisibles (`é`). `JSON_PRETTY_PRINT` indente le résultat, `JSON_UNESCAPED_SLASHES` évite d'écrire `\/`.

### Détecter les erreurs

Sans option, `json_decode('{invalide')` retourne simplement `null`… comme `json_decode('null')`, qui est un JSON parfaitement valide. Impossible de distinguer une erreur d'une valeur nulle sans appeler `json_last_error()`. Avec `JSON_THROW_ON_ERROR`, une erreur lance une `JsonException` : elle ne peut pas passer inaperçue.

```php
try {
    $config = json_decode(file_get_contents($chemin), true, flags: JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    throw new RuntimeException("Configuration illisible : $chemin", 0, $e);
}
```

Un JSON décodé est une **donnée externe** : on vérifie ensuite que les clés attendues existent et ont le bon type, exactement comme un formulaire.

## Les pièges classiques

- Ne pas vérifier le `false` retourné par `file_get_contents()` ou `fopen()`.
- Écrire `while ($ligne = fgets($f))` au lieu de `!== false`.
- Ouvrir en mode `'w'` un fichier qu'on voulait compléter (il est vidé).
- Modifier un `DateTime` partagé au lieu d'utiliser `DateTimeImmutable`.
- Utiliser `json_decode()` sans `true` puis manipuler le résultat comme un tableau, ou sans `JSON_THROW_ON_ERROR`.

> [!retenir]
> - `file_get_contents` / `file_put_contents` (avec `FILE_APPEND` pour ajouter) ; vérifie toujours le `false`.
> - Gros fichiers : `fopen` + `fgets` dans `while (($l = fgets($f)) !== false)` ; `flock` pour les écritures concurrentes.
> - `time()` : secondes depuis le 01/01/1970 UTC ; règle le fuseau avec `date_default_timezone_set()`.
> - `DateTimeImmutable` : chaque modification crée un nouvel objet ; `diff()->days` donne un écart en jours.
> - Passe « maintenant » en paramètre pour tester ; les échéances se vérifient côté serveur.
> - `json_decode($json, true, flags: JSON_THROW_ON_ERROR)` ; `JSON_UNESCAPED_UNICODE` garde les accents.
