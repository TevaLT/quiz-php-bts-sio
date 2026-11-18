---
resume: Se connecter à une base de données avec PDO, exécuter des requêtes préparées pour éviter l'injection SQL, lire les résultats et paginer une liste.
---

> [!objectifs]
> - Créer une connexion PDO avec les bonnes options, et garder les identifiants hors du code.
> - Comprendre l'injection SQL et l'empêcher avec les requêtes préparées.
> - Lire des résultats avec `fetch()`, `fetchAll()` et `fetchColumn()`, et traiter le cas « aucune ligne ».
> - Paginer, rechercher avec `LIKE` et éviter le problème N+1.

## PDO, une interface commune

**PDO** (*PHP Data Objects*) est l'extension standard de PHP pour dialoguer avec une base de données. Elle offre la **même interface** pour MySQL/MariaDB, PostgreSQL, SQLite… : seul le **DSN** change.

```php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=cineclub;charset=utf8mb4';

$pdo = new PDO($dsn, $utilisateur, $motDePasse, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // une erreur SQL lance une PDOException
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // lignes en tableaux associatifs
    PDO::ATTR_EMULATE_PREPARES => false,                  // vraies requêtes préparées côté serveur
]);
```

- Le **DSN** (*Data Source Name*) décrit **où** se trouve la base : le pilote (`mysql`), l'hôte, le port, le nom de la base (`dbname`) et l'encodage (`charset=utf8mb4`, le vrai UTF-8 complet de MySQL, émojis compris).
- Le DSN ne suffit pas pour s'authentifier : l'**utilisateur** et le **mot de passe** sont passés à part.
- `ERRMODE_EXCEPTION` fait lever une `PDOException` à chaque erreur SQL, au lieu de retourner discrètement `false`. C'est le comportement par défaut depuis PHP 8, mais on l'écrit pour être explicite.
- `FETCH_ASSOC` : chaque ligne est un tableau associatif `['id' => 3, 'titre' => '…']`, sans les doublons numériques.

### Les identifiants hors du code

Le mot de passe de la base ne s'écrit pas dans le code versionné : on le met dans un fichier de configuration **hors du dossier public** et **exclu de Git** (`.gitignore`), ou dans des **variables d'environnement**.

```php
$config = require __DIR__ . '/../config/database.php';   // retourne un tableau, non versionné
$pdo = new PDO($config['dsn'], $config['utilisateur'], $config['mot_de_passe'], $options);
```

### Si la connexion échoue

Le constructeur lance une `PDOException` (base arrêtée, mot de passe faux…). Son message peut contenir des détails techniques (hôte, utilisateur) : on le **journalise**, et l'utilisateur voit une page d'erreur neutre (code 500 ou 503).

```php
try {
    $pdo = new PDO($dsn, $utilisateur, $motDePasse, $options);
} catch (PDOException $e) {
    error_log('Connexion BDD impossible : ' . $e->getMessage());
    http_response_code(503);
    exit('Service momentanément indisponible.');
}
```

Une **connexion** SQL est technique et courte : en général, chaque requête HTTP ouvre la sienne et la ferme à la fin du script. Ce n'est pas une **session utilisateur**, qui dure entre les requêtes (chapitre 14).

## L'injection SQL

Construire une requête en **concaténant** une donnée reçue est la faille la plus célèbre du web :

```php
// DANGEREUX
$sql = "SELECT * FROM films WHERE titre = '" . $_GET['titre'] . "'";
```

Si `titre` vaut `' OR '1'='1`, la requête devient `SELECT * FROM films WHERE titre = '' OR '1'='1'` : la condition est toujours vraie, et **tous** les films sont retournés. Avec d'autres valeurs, un attaquant peut lire des tables entières (mots de passe compris) ou détruire des données.

## Les requêtes préparées

Une **requête préparée** envoie au SGBD le **texte SQL** et les **valeurs** **séparément**. Le SQL est analysé une fois, avec des emplacements (*placeholders*) ; les valeurs y sont ensuite insérées **comme des données**, jamais interprétées comme du code. L'injection devient impossible.

```php
$stmt = $pdo->prepare('SELECT id, titre, note FROM films WHERE genre_id = :genre AND note >= :note');
$stmt->execute(['genre' => $genreId, 'note' => 7]);
$films = $stmt->fetchAll();
```

On peut utiliser des emplacements **nommés** (`:genre`) ou **positionnels** (`?`, avec un tableau ordonné). Les apostrophes (`L'Armée des ombres`) ne posent plus aucun problème : il n'y a rien à échapper.

`$pdo->query($sql)` exécute directement une requête **sans aucune partie variable** (`SELECT COUNT(*) FROM films`). Dès qu'une valeur vient de l'extérieur, c'est `prepare()` + `execute()`.

> [!attention]
> Un emplacement remplace une **valeur**, jamais un **nom** de colonne, de table, ni un mot-clé comme `ASC` / `DESC`. Pour un tri choisi par l'utilisateur (`ORDER BY`), on choisit la colonne dans une **liste blanche** (chapitre 13).

Les requêtes préparées protègent la **base de données**, pas la page : un titre contenant du HTML malveillant, enregistré proprement, doit toujours être **échappé à l'affichage** (`htmlspecialchars`) pour éviter le XSS.

## Lire les résultats

| Méthode | Retourne | Si aucune ligne |
|---|---|---|
| `fetch()` | la ligne suivante | `false` |
| `fetchAll()` | toutes les lignes, dans un tableau | `[]` (tableau vide) |
| `fetchColumn()` | la première colonne de la ligne suivante | `false` |

```php
// une seule ligne : la fiche d'un film
$stmt = $pdo->prepare('SELECT * FROM films WHERE id = :id');
$stmt->execute(['id' => $id]);
$film = $stmt->fetch();
if ($film === false) {
    http_response_code(404);   // le film n'existe pas
    // …
}

// une valeur unique : un comptage
$total = (int) $pdo->query('SELECT COUNT(*) FROM films')->fetchColumn();
```

Selon le pilote et sa configuration, une valeur numérique peut arriver sous forme de **chaîne** (`"42"`) : la conversion `(int)` garantit le bon type pour les comparaisons strictes.

### 400 ou 404 ?

Pour une page `/film?id=…` : si l'identifiant est **mal formé** (`abc`), la requête elle-même est incorrecte → **400** ; s'il est valide mais **n'existe pas** (`9999`), la ressource est introuvable → **404**.

```php
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    http_response_code(400);
    exit('Identifiant invalide.');
}
```

### Les gros résultats

`fetchAll()` charge **tout** le résultat en mémoire. Pour un export de 500 000 lignes, on parcourt plutôt les lignes une à une ; un **générateur** (`yield`) le fait proprement, avec une seule ligne en mémoire à la fois :

```php
function tousLesFilms(PDO $pdo): Generator
{
    $stmt = $pdo->query('SELECT id, titre FROM films ORDER BY id');
    while (($ligne = $stmt->fetch()) !== false) {
        yield $ligne;
    }
}

foreach (tousLesFilms($pdo) as $film) {
    fputcsv($sortie, $film);
}
```

## Rechercher et paginer

### LIKE

Dans un motif `LIKE`, `%` signifie « n'importe quelle suite de caractères » et `_` « exactement un caractère ». Les jokers font partie de la **valeur**, qui reste paramétrée :

```php
$stmt = $pdo->prepare('SELECT titre FROM films WHERE titre LIKE :q ORDER BY titre');
$stmt->execute(['q' => '%' . $recherche . '%']);
```

### LIMIT et OFFSET

Pour afficher la page `$page` avec `$limite` lignes par page, on saute `($page - 1) * $limite` lignes :

```php
$limite = 20;
$offset = ($page - 1) * $limite;   // page 1 → 0, page 2 → 20, page 3 → 40

$stmt = $pdo->prepare('SELECT id, titre FROM films ORDER BY titre LIMIT :limite OFFSET :offset');
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
```

`LIMIT` et `OFFSET` attendent des **entiers** : `bindValue(…, PDO::PARAM_INT)` les transmet avec le bon type (passés dans `execute([…])`, ils seraient envoyés comme des chaînes). Le nombre de pages se calcule avec `(int) ceil($total / $limite)`, et un `ORDER BY` est indispensable pour que les pages soient stables.

## Le problème N+1

Afficher 50 films avec le nom de leur genre en faisant **une requête par film** pour aller chercher le genre, c'est exécuter 1 requête pour la liste + 50 requêtes : **N+1 requêtes**. Le site ralentit à mesure que la liste grandit.

La correction : récupérer tout en **une seule requête** avec une jointure (ou, pour une relation multiple, une seconde requête avec `WHERE id IN (…)`).

```php
$films = $pdo->query(
    'SELECT f.id, f.titre, g.nom AS genre
     FROM films f JOIN genres g ON g.id = f.genre_id
     ORDER BY f.titre'
)->fetchAll();
```

> [!astuce]
> Avant d'écrire une requête dans le code PHP, teste-la dans un **client SQL** (DBeaver, phpMyAdmin, la console MySQL) avec des valeurs réelles. Tu valides le SQL seul, avec ses messages d'erreur précis, avant d'y ajouter la couche PHP.

## Les pièges classiques

- Concaténer une valeur reçue dans une requête SQL.
- Vouloir paramétrer un nom de colonne ou `ASC`/`DESC`.
- Oublier de traiter le `false` de `fetch()` (fiche inexistante).
- Mettre les identifiants de la base dans le code versionné, ou afficher le message de la `PDOException`.
- Faire une requête par ligne affichée (N+1).

> [!retenir]
> - `new PDO($dsn, $utilisateur, $motDePasse, $options)` avec `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`.
> - Les identifiants vont dans une configuration non versionnée, hors du dossier public.
> - Toute valeur variable passe par `prepare()` + `execute()` : SQL et données sont envoyés séparément.
> - `fetch()` → `false` si aucune ligne ; `fetchAll()` → `[]` ; `(int)` sur `fetchColumn()`.
> - `LIKE :q` avec `'%' . $q . '%'` ; `OFFSET = ($page - 1) * $limite` avec `PDO::PARAM_INT`.
> - Une jointure plutôt que N+1 requêtes ; l'échappement HTML reste nécessaire à l'affichage.
