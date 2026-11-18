---
resume: Distinguer avertissements, erreurs et exceptions, lancer et attraper des exceptions avec try, catch et finally, créer ses propres exceptions et configurer l'affichage des erreurs.
---

> [!objectifs]
> - Comprendre la différence entre un avertissement, une `Error` et une `Exception`.
> - Lancer une exception avec `throw` et la traiter avec `try`, `catch` et `finally`.
> - Choisir la bonne exception standard, ou créer la sienne.
> - Configurer les erreurs : tout voir en développement, tout journaliser en production.

## Quand quelque chose se passe mal

PHP signale les problèmes de deux façons :

- par des **messages d'erreur** (`Warning`, `Deprecated`…) : le script affiche ou journalise le message et **continue**. C'est le cas de `file_get_contents('absent.txt')`, qui émet un avertissement et retourne `false` ;
- par des **exceptions** : un objet est « lancé », et si personne ne l'attrape, le script **s'arrête** avec une erreur fatale « Uncaught … ».

Depuis PHP 7, la plupart des erreurs graves sont devenues des objets lancés comme des exceptions. Tous implémentent l'interface `Throwable`, qui a deux grandes branches :

| Branche | Signale | Exemples |
|---|---|---|
| `Error` | une erreur de **programmation**, levée par PHP | `TypeError`, `ValueError`, `ArgumentCountError`, `DivisionByZeroError` |
| `Exception` | une situation **exceptionnelle** que le programme peut gérer | `InvalidArgumentException`, `RuntimeException`, `PDOException`, `JsonException` |

```php
str_repeat('a', -1);   // ValueError : un argument a une valeur interdite
strlen();              // ArgumentCountError : il manque un argument
intdiv(1, 0);          // DivisionByZeroError
```

## try, catch, finally

On place le code qui peut échouer dans un bloc `try`, et le traitement de l'échec dans un ou plusieurs `catch` :

```php
try {
    $donnees = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    enregistrer($donnees);
} catch (JsonException $e) {
    echo 'Le fichier reçu n\'est pas du JSON valide.';
} catch (RuntimeException $e) {
    echo 'Enregistrement impossible : ' . $e->getMessage();
} finally {
    fermerConnexion();   // exécuté dans TOUS les cas
}
```

- Dès qu'une exception est lancée dans le `try`, la suite du bloc est abandonnée et PHP cherche le **premier** `catch` dont le type correspond (la classe elle-même ou une classe parente).
- Pour traiter plusieurs types de la même façon : `catch (JsonException | ValueError $e)`.
- Le bloc `finally` s'exécute **toujours** : que tout se passe bien, qu'une exception soit attrapée ou non, et même si le `try` contient un `return`. On y met le nettoyage (fermer un fichier, libérer une ressource).

```php
function f(): string
{
    try {
        return 'try';
    } finally {
        echo 'finally ';
    }
}
echo f();   // affiche « finally try » : finally passe avant que la valeur soit rendue
```

> [!attention]
> `catch (Exception $e)` n'attrape **pas** les `Error` : ce ne sont pas des sous-classes d'`Exception`. Un `strlen()` sans argument lance une `ArgumentCountError` qui traverse ce `catch`. Pour tout attraper, il faudrait `catch (Throwable $e)`, à réserver au gestionnaire global de l'application.

L'objet exception fournit `getMessage()`, `getCode()`, `getFile()`, `getLine()` et `getTraceAsString()` (la pile des appels) : de quoi écrire un journal précis.

## Lancer une exception

`throw` lance une exception, en général quand une fonction reçoit quelque chose qu'elle ne peut pas traiter :

```php
function definirAge(int $age): void
{
    if ($age < 0) {
        throw new InvalidArgumentException("L'âge ne peut pas être négatif : $age");
    }
    // …
}
```

Depuis PHP 8, `throw` est aussi une **expression** : on peut l'utiliser après `??` ou dans un `match`.

```php
$nom = $_GET['nom'] ?? throw new InvalidArgumentException('Paramètre « nom » manquant');
```

Si la clé `'nom'` existe, `$nom` reçoit sa valeur ; sinon, l'exception est lancée et l'affectation n'a jamais lieu.

### Quelle exception standard ?

| Situation | Exception |
|---|---|
| Un argument invalide (âge négatif, format incorrect) | `InvalidArgumentException` |
| Une valeur hors du domaine attendu | `DomainException`, `RangeException` |
| Un problème qui ne se voit qu'à l'exécution (fichier, réseau, service) | `RuntimeException` |
| Une erreur de logique du programme (méthode appelée au mauvais moment) | `LogicException` |

## Créer ses propres exceptions

Une exception **métier** décrit un problème du domaine de l'application. Il suffit d'hériter d'une exception existante :

```php
class StockInsuffisantException extends RuntimeException
{
}

function retirerDuStock(int $stock, int $quantite): int
{
    if ($quantite > $stock) {
        throw new StockInsuffisantException("Stock insuffisant : $stock disponible(s)");
    }
    return $stock - $quantite;
}

try {
    $stock = retirerDuStock(2, 5);
} catch (StockInsuffisantException $e) {
    echo $e->getMessage();   // traitement précis de CE problème
}
```

Le code appelant peut alors réagir précisément à ce cas, sans attraper par erreur d'autres problèmes.

### Relancer et chaîner

Dans un `catch`, `throw $e;` **relance** l'exception attrapée (par exemple après l'avoir journalisée) : elle remonte vers l'appelant. On peut aussi l'**envelopper** dans une exception plus parlante, en gardant l'originale comme cause :

```php
try {
    $pdo = new PDO($dsn, $utilisateur, $motDePasse);
} catch (PDOException $e) {
    throw new RuntimeException('Base de données indisponible', 0, $e);
}
```

Plus haut, `$e->getPrevious()` retrouve l'exception d'origine : le détail technique n'est pas perdu.

## Erreurs « silencieuses » : vérifier les retours

De nombreuses fonctions historiques ne lancent pas d'exception : elles retournent `false` et émettent un avertissement. Il faut **vérifier leur retour** :

```php
$contenu = file_get_contents($chemin);
if ($contenu === false) {
    throw new RuntimeException("Lecture impossible : $chemin");
}
```

> [!attention]
> L'opérateur `@` (`@file_get_contents($chemin)`) masque l'avertissement, mais **pas le problème** : le script continue avec `false`, et l'erreur réapparaît plus loin, sans message pour comprendre. Vérifie plutôt le retour, ou teste la condition avant (`is_file($chemin)`).

## Une exception que personne n'attrape

Si une exception remonte jusqu'en haut sans rencontrer de `catch` correspondant, PHP affiche (ou journalise) une **erreur fatale** « Uncaught … » avec la pile des appels, et **arrête le script**. Une application installe donc un gestionnaire global, avec `set_exception_handler()`, qui journalise le détail et affiche une page d'erreur neutre à l'utilisateur.

## Configurer l'affichage des erreurs

| Réglage | Développement | Production |
|---|---|---|
| `error_reporting` | `E_ALL` | `E_ALL` |
| `display_errors` | `On` : tout voir tout de suite | `Off` : rien dans la page |
| `log_errors` | `On` | `On` : tout dans le journal |

En **développement**, `E_ALL` affiché à l'écran fait apparaître le moindre avertissement (variable non définie, fonction dépréciée) : c'est le moment de corriger, avant que le problème ne devienne un bug.

En **production**, le détail d'une erreur (chemin des fichiers, requête SQL, version) ne doit **jamais** apparaître dans la page : il aiderait un attaquant. Le détail va dans le **journal du serveur**, et l'utilisateur reçoit un message générique (« Une erreur est survenue, réessayez plus tard »).

## Les pièges classiques

- Attraper `Exception` en croyant attraper aussi les `Error`.
- Écrire un `catch` vide qui « avale » l'exception : le problème disparaît sans trace.
- Ignorer le `false` retourné par une fonction de fichier ou de réseau.
- Masquer un avertissement avec `@` au lieu de le traiter.
- Afficher `$e->getMessage()` technique à l'utilisateur en production.

> [!retenir]
> - `Throwable` regroupe `Error` (erreurs de programmation) et `Exception` (situations à gérer).
> - `try` / `catch` attrape par type ; `finally` s'exécute toujours, même après un `return`.
> - `throw` lance une exception ; depuis PHP 8, c'est aussi une expression (`?? throw …`).
> - Crée tes exceptions métier en héritant de `RuntimeException` ou `InvalidArgumentException`.
> - `throw $e;` relance ; le troisième argument du constructeur chaîne la cause (`getPrevious()`).
> - En production : `display_errors` à `Off`, `log_errors` à `On`, message neutre pour l'utilisateur.
