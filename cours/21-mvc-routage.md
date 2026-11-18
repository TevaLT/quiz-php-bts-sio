---
resume: Organiser une application web en couches (contrôleur frontal, routeur, contrôleurs, modèle, repository, vues), injecter les dépendances et utiliser un micro-framework comme Slim 4.
---

> [!objectifs]
> - Comprendre pourquoi on sépare les responsabilités, et ce que recouvre MVC.
> - Savoir quelle couche fait quoi : routage, contrôle HTTP, règles métier, SQL, affichage.
> - Injecter les dépendances plutôt que les créer partout.
> - Écrire des routes, des réponses et des middlewares avec Slim 4.

## Pourquoi organiser le code ?

Un fichier `film.php` qui ouvre la connexion, lit `$_GET`, exécute du SQL, vérifie les règles, gère les erreurs et affiche le HTML **fonctionne**… jusqu'au jour où il faut le modifier. Tout y est mélangé : impossible de tester une règle sans base de données, de réutiliser la requête ailleurs, ou de produire du JSON au lieu du HTML sans tout réécrire.

**Refactoriser**, c'est **modifier la structure** du code **sans changer son comportement** : on déplace, on découpe, on renomme, en vérifiant à chaque étape que tout fonctionne encore comme avant.

## Le motif MVC

Le motif **Modèle-Vue-Contrôleur** est né à la fin des années 1970 au Xerox PARC, avec le langage Smalltalk (Trygve Reenskaug, 1979), pour des interfaces graphiques. Le « MVC Web » en est une **adaptation** au cycle requête → réponse :

| Composant | Rôle | Ne fait pas |
|---|---|---|
| **Contrôleur frontal** (`public/index.php`) | point d'entrée **unique** de toutes les requêtes | de logique métier |
| **Routeur** | associe méthode + chemin (`GET /livres/{id}`) à un contrôleur | |
| **Contrôleur** | lit la requête, vérifie son format, appelle le modèle, **choisit la réponse** (200, 303, 404, 422) | de SQL, de HTML |
| **Modèle** (domaine) | les objets métier et leurs **règles** (un titre ne peut pas être vide) | d'HTTP, de SQL |
| **Repository** | le **SQL** : lire et écrire les objets du modèle en base | d'HTTP, d'affichage |
| **Vue** (gabarit) | produit le HTML, en **échappant** chaque donnée | de requêtes, de décisions |

Suivons `GET /livres/7` :

1. Le serveur envoie toute requête à `public/index.php`, qui charge l'autoloader et la configuration.
2. Le **routeur** trouve la route `GET /livres/{id}` et appelle `LivreController::afficher()` avec `id = "7"`.
3. Le **contrôleur** vérifie que l'identifiant est un entier ≥ 1 (sinon 400 ou 404), puis demande `$repository->trouver(7)`.
4. Le **repository** exécute la requête préparée et retourne un objet `Livre`… ou `null` si la ligne n'existe pas.
5. Le **contrôleur** décide : `null` → réponse 404 ; sinon il passe le livre à la **vue**.
6. La **vue** produit le HTML, avec `htmlspecialchars()` sur le titre.

Le repository **ne répond pas lui-même** 404 : il ne connaît pas HTTP. Il retourne `null` (ou lève une exception métier), et c'est le contrôleur qui traduit en statut HTTP. Le même repository peut ainsi servir une page HTML, une API JSON ou une commande en terminal.

> [!attention]
> Avoir des dossiers `Model`, `View` et `Controller` ne prouve pas une bonne architecture. Ce qui compte, ce sont les **dépendances** : une vue qui exécute du SQL ou un modèle qui lit `$_POST` trahissent le découpage, quels que soient les noms de dossiers.

### Les règles métier vivent dans le modèle

Une règle comme « un livre a toujours un titre » ou « le nombre de places ne peut pas être négatif » est un **invariant du domaine** : elle se vérifie dans le **modèle**, par exemple dans le constructeur, pour qu'aucun objet invalide ne puisse exister, quelle que soit l'entrée (formulaire, import, API).

```php
final class Livre
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $titre,
    ) {
        if (trim($titre) === '') {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }
    }
}
```

Les contraintes de la base (`NOT NULL`, `CHECK`) et les attributs HTML (`required`) **complètent** cette règle sans la remplacer. Une règle qui porte sur **plusieurs lignes** (« un client a au plus trois commandes en cours ») ne peut pas être une simple contrainte de colonne : un **service** la vérifie, dans une transaction.

Quand l'interface HTML et une API JSON offrent la même opération, elles appellent le **même** modèle (ou service) : les règles sont écrites une seule fois. Passer du HTML au JSON ne demande alors de changer que la **présentation** (la vue, la forme de la réponse).

### Erreurs : chaque cas à sa place

```php
try {
    $livre = new Livre(null, $titre);
    $this->repository->ajouter($livre);
} catch (InvalidArgumentException $e) {
    return $this->vue('livres/formulaire', ['erreur' => $e->getMessage(), 'titre' => $titre], 422);
}
```

Le contrôleur attrape **précisément** l'exception métier (`InvalidArgumentException` → 422, formulaire réaffiché). Il n'attrape pas `Throwable` : une panne SQL déguisée en « erreur de saisie » serait trompeuse. Les erreurs inattendues remontent jusqu'à un gestionnaire global qui les **journalise** et répond un **500 neutre**.

### Le nom d'un gabarit ne vient jamais de l'URL

`$this->vue($_GET['page'])` permettrait d'inclure n'importe quel fichier (chapitre 15). Le contrôleur choisit le gabarit **dans le code**.

## L'injection de dépendances

Un repository a besoin d'une connexion PDO. Plutôt que de la créer lui-même (`new PDO(…)` dans la classe), il la **reçoit par son constructeur** :

```php
final class LivreRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function trouver(int $id): ?Livre
    {
        $stmt = $this->pdo->prepare('SELECT id, titre FROM livres WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch();

        return $ligne === false ? null : new Livre((int) $ligne['id'], $ligne['titre']);
    }
}
```

C'est l'**injection de dépendances** : un objet reçoit ce dont il a besoin au lieu d'aller le chercher. Avantages : une seule connexion partagée, une configuration à un seul endroit, et des tests faciles (on injecte une base SQLite en mémoire). Les requêtes SQL sont **toutes** dans les repositories.

Pour apprendre, **SQLite** est pratique : aucun serveur à installer, un simple fichier, et la **même API PDO** que MySQL. La structure de la base se crée par un script lancé une fois (`php database/init.php`), jamais au moment d'afficher une page.

## Slim 4, un micro-framework

Un **framework** fournit la plomberie commune ; un **micro-framework** comme **Slim 4** se limite à l'essentiel : un **routeur**, des objets requête/réponse **PSR-7** et des **middlewares**. Il n'impose **pas** MVC : modèle, repositories et vues sont des choix de conception de l'équipe.

```php
<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();                      // décode les corps JSON
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);           // détails des erreurs : jamais en production

$app->get('/films/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $id = (int) $args['id'];                            // les paramètres de route sont des chaînes
    $recherche = $request->getQueryParams()['q'] ?? ''; // la chaîne de requête (?q=…)
    $response->getBody()->write("Film n°$id");
    return $response;
})->setName('film');

$app->run();
```

- Un segment `{id}` de la route arrive dans `$args['id']`, **sous forme de chaîne** ; `{id:[0-9]+}` limite le segment à des chiffres. Pour `/films/{id}/avis/{num}` et la requête `/films/7/avis/3`, `$args['num']` vaut `"3"`.
- `getQueryParams()` lit la chaîne de requête ; `getParsedBody()` le corps (pour du JSON, il faut `addBodyParsingMiddleware()`).
- Le callback **retourne** toujours un objet `Response`.
- Une route **nommée** (`->setName('film')`) permet de générer son URL sans l'écrire en dur : `$routeParser->urlFor('film', ['id' => 7])`.

### Les objets PSR-7 sont immuables

**PSR-7** définit les interfaces des messages HTTP. Ses objets sont **immuables** : `withHeader()`, `withStatus()` **retournent une nouvelle réponse** et ne modifient pas l'originale.

```php
$response->withHeader('X-Test', '1');   // inutile : la nouvelle réponse est perdue
return $response;

return $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus(201);                  // correct : on retourne la réponse modifiée
```

### Les middlewares

Un **middleware** enveloppe le traitement de la requête, comme les couches d'un **oignon** : il peut agir avant (refuser une requête non authentifiée), puis après (ajouter un en-tête). On leur confie les préoccupations **transversales** : gestion des erreurs, authentification, journalisation, CORS, décodage du corps.

Avec Slim, le **dernier middleware ajouté est exécuté en premier** (il est la couche extérieure). C'est pourquoi `addErrorMiddleware()` est ajouté en dernier : il enveloppe tout et attrape toutes les exceptions. Une requête réussie traverse donc : client → serveur PHP → middlewares → routeur → callback → réponse (qui repasse par les middlewares).

`displayErrorDetails` (le premier argument d'`addErrorMiddleware()`) peut valoir `true` **en développement seulement** : en production, les traces et chemins internes ne doivent jamais être affichés.

Pendant le développement, une route déjà déclarée mais pas encore codée peut répondre **501 Not Implemented**. Et le serveur intégré `php -S` reste un outil de **développement**, pas de production.

## Vérifier un refactoring

Une architecture se vérifie par des contraintes **mesurables** : seule la couche repository exécute du SQL ; les vues ne lancent aucune requête ; aucune classe hors des contrôleurs ne lit la requête ; le domaine ne dépend pas du framework ; `public/index.php` reste court.

Et un cas de test est **précis** : « vérifier que tout fonctionne » n'en est pas un. Un test indique une situation, une action et un résultat attendu observable, par exemple : « un titre `<b>x</b>` est affiché littéralement, pas en gras » (test de la vue et de son échappement).

> [!astuce]
> Pour montrer qu'on a compris MVC, le meilleur argument n'est pas « j'ai des dossiers Model et Controller », mais : « je peux **tracer** une requête de bout en bout, **justifier** chaque couche, et **tester** chacune séparément ».

## Les pièges classiques

- Un contrôleur qui contient du SQL, ou une vue qui interroge la base.
- Un repository qui répond 404 ou redirige.
- Attraper `Throwable` pour tout transformer en erreur de saisie.
- Oublier que `withHeader()` retourne une nouvelle réponse.
- Laisser `displayErrorDetails` à `true` en production.

> [!retenir]
> - Contrôleur frontal unique → routeur → contrôleur (HTTP) → modèle (règles) / repository (SQL) → vue (HTML échappé).
> - Le repository retourne `null` ; le contrôleur choisit 404, 303 ou 422.
> - Les invariants vivent dans le modèle ; les erreurs inattendues donnent un 500 neutre et journalisé.
> - Injection de dépendances : le repository reçoit `PDO` par son constructeur.
> - Slim 4 : routes avec `$args`, `getQueryParams()`, `getParsedBody()` ; objets PSR-7 immuables ; dernier middleware ajouté = premier exécuté.
