---
resume: Concevoir une API REST qui échange du JSON : ressources et URL, méthodes HTTP, codes de statut précis, erreurs uniformes, et les principes qui rendent une API vraiment RESTful.
---

> [!objectifs]
> - Concevoir les URL d'une API autour des ressources, et choisir la bonne méthode HTTP.
> - Répondre avec le bon code de statut et le bon format, y compris en cas d'erreur.
> - Lire un corps JSON en PHP et renvoyer du JSON correctement.
> - Connaître les contraintes de REST, le modèle de Richardson, CORS, les ETag et OpenAPI.

## Une API, des ressources

Une **API** web permet à des programmes (une application mobile, un site en JavaScript, un autre serveur) d'utiliser les données et les règles d'une application, en échangeant le plus souvent du **JSON** plutôt que des pages HTML.

Le style **REST** (*Representational State Transfer*) a été défini par **Roy Fielding** dans sa thèse, en **2000**. Une API REST s'organise autour des **ressources** (les « choses » du domaine : des films, des avis, des réservations), désignées par des **URL** :

| URL | Désigne |
|---|---|
| `/api/films` | la **collection** des films |
| `/api/films/7` | le film n°7 |
| `/api/films/7/avis` | les avis du film n°7 |

Une **ressource** est la chose elle-même ; ce qui circule est une **représentation** de cette ressource (du JSON, du HTML, du CSV…). Les URL contiennent des **noms**, jamais des verbes : l'action est portée par la **méthode HTTP**.

## Les méthodes HTTP

| Méthode | Action | Exemple | Réponse en cas de succès |
|---|---|---|---|
| `GET` | lire | `GET /api/films/7` | `200` + la ressource |
| `POST` | créer dans une collection | `POST /api/films` | `201` + en-tête `Location` + la ressource créée |
| `PUT` | **remplacer** entièrement | `PUT /api/films/7` | `200` (ou `204`) |
| `PATCH` | modifier **partiellement** | `PATCH /api/reservations/5` | `200` + la ressource modifiée |
| `DELETE` | supprimer | `DELETE /api/films/7` | `204`, **sans corps** |

Un échange de création complet ressemble à ceci :

```http
POST /api/films HTTP/1.1
Content-Type: application/json
Accept: application/json

{"titre": "Le Voyage", "annee": 1902}
```

```http
HTTP/1.1 201 Created
Location: /api/films/42
Content-Type: application/json

{"id": 42, "titre": "Le Voyage", "annee": 1902}
```

- `PUT` envoie la représentation **complète** (un champ absent est effacé) ; `PATCH` seulement les champs à changer. Annuler une réservation, c'est `PATCH /api/reservations/5` avec `{"statut": "annulee"}`, qui répond 200 avec l'objet modifié.
- Après une création, une **page HTML** redirige (303, motif PRG) ; une **API** répond **201 Created** avec l'en-tête `Location: /api/films/42` et la ressource créée dans le corps.

`GET /api/livres/7/supprimer` est une mauvaise idée : un GET doit être **sûr** (sans effet), et un verbe dans l'URL contourne le sens des méthodes. On écrit `DELETE /api/livres/7`.

### Idempotence

Une méthode est **idempotente** si la répéter laisse le serveur dans le **même état final** : c'est le cas de `GET`, `PUT` et `DELETE`. Rejouer `DELETE /api/films/7` laisse le film… supprimé : le second appel répond 404, mais l'état du serveur est le même. `POST` n'est pas idempotent (chaque appel peut créer une ressource) ; `PATCH` dépend de l'opération (« fixer le titre » oui, « ajouter 1 au compteur » non).

## Les codes de statut d'une API

Une API **doit** utiliser des statuts précis : c'est grâce à eux que le client sait ce qui s'est passé. Une route qui répond **toujours 200**, même pour un identifiant inconnu ou une erreur, oblige le client à deviner en lisant le corps.

| Situation | Statut |
|---|---|
| Lecture réussie, y compris une liste **vide** (`[]`) | `200` |
| Ressource créée | `201` |
| Suppression réussie (pas de corps) | `204` |
| JSON **mal formé**, paramètres incohérents (`?page=-3`) | `400` |
| Authentification absente ou invalide | `401` |
| Authentifié, mais pas le droit | `403` |
| Ressource introuvable | `404` |
| Méthode non prise en charge par cette URL | `405` |
| Conflit avec l'état actuel (doublon, version périmée) | `409` |
| `Content-Type` non pris en charge (`text/plain` au lieu de JSON) | `415` |
| JSON lisible mais **règle métier** violée (titre vide) | `422` |
| Trop de requêtes | `429` |
| Erreur interne | `500` |

La nuance 400 / 422 : `{ titre: }` n'est pas du JSON valide → **400** ; `{"titre": " "}` est du JSON valide, mais le titre est vide après `trim` → **422**.

Pour la ressource d'un **autre** utilisateur, la réponse normale est **403** (identité connue, accès interdit). Une API peut choisir **404** pour ne pas révéler que la ressource existe, à condition de l'appliquer partout et de le documenter.

## Accept et Content-Type

- `Content-Type` décrit le format du **corps envoyé** (par le client dans sa requête, ou par le serveur dans sa réponse) : `application/json`.
- `Accept` indique le format que le client **souhaite recevoir**.

Une API qui attend du JSON répond **415** si la requête annonce un autre format.

## Une API JSON en PHP, sans framework

```php
function repondreJson(mixed $donnees, int $statut = 200): never
{
    http_response_code($statut);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

// lire le corps JSON d'un POST
if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    repondreJson(['erreur' => ['code' => 'format', 'message' => 'Corps JSON attendu.']], 415);
}
try {
    $donnees = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
} catch (JsonException) {
    repondreJson(['erreur' => ['code' => 'json_invalide', 'message' => 'JSON mal formé.']], 400);
}
$titre = is_array($donnees) && is_string($donnees['titre'] ?? null) ? trim($donnees['titre']) : '';
if ($titre === '') {
    repondreJson(['erreur' => ['code' => 'titre_vide', 'message' => 'Le titre est obligatoire.']], 422);
}
```

- Un corps JSON ne remplit **pas** `$_POST` : on le lit avec `file_get_contents('php://input')`.
- On fixe le **statut** et l'en-tête **`Content-Type`** avant d'écrire le JSON.

### Des erreurs uniformes et discrètes

Toutes les erreurs de l'API ont **la même forme** (par exemple `{"erreur": {"code": "…", "message": "…"}}`), pour que les clients les traitent de façon générique. Elles ne contiennent **jamais** de trace d'exception, de requête SQL, de chemin de fichier, de version de logiciel ni de secret : ces détails vont dans le journal du serveur.

## REST, au-delà du JSON

Renvoyer du JSON ne suffit pas à rendre une API « RESTful ». REST définit **six contraintes** :

1. **Client-serveur** : l'interface est séparée du stockage des données.
2. **Sans état** (*stateless*) : chaque requête contient **tout** ce qu'il faut pour la traiter (y compris la preuve d'identité) ; le serveur ne garde pas d'état de conversation entre deux requêtes.
3. **Mise en cache** : les réponses indiquent si elles peuvent être mises en cache.
4. **Interface uniforme** : ressources identifiées par des URL, manipulées par des représentations, méthodes et statuts standard.
5. **Système en couches** : le client ignore s'il parle au serveur final ou à un intermédiaire (proxy, cache).
6. **Code à la demande** : le serveur peut envoyer du code exécutable au client. C'est la **seule contrainte facultative**.

Une **session PHP** côté serveur n'est **pas** compatible avec la contrainte sans état stricte : une API identifie l'appelant à chaque requête, par un **jeton** (chapitre 23). Les routes `/api/` ne démarrent donc pas de session.

### Le modèle de maturité de Richardson

| Niveau | Description | Exemple |
|---|---|---|
| 0 | une seule URL, HTTP sert de simple transport (style RPC) | `POST /execute` avec `{"action": "supprimerLivre", "id": 7}` |
| 1 | des **ressources** distinctes | `POST /livres/7/supprimer` |
| 2 | ressources + **méthodes et statuts HTTP** utilisés correctement | `DELETE /livres/7` → `204` |
| 3 | + **HATEOAS** : les réponses contiennent des **liens** vers les actions possibles | `"liens": {"avis": "/livres/7/avis"}` |

**HATEOAS** (*Hypermedia As The Engine Of Application State*) : le client découvre les actions disponibles en suivant les liens fournis, comme un humain sur un site web. La plupart des API se situent au niveau 2.

## Les outils autour d'une API

- **ETag** : un identifiant de version de la ressource, envoyé dans la réponse. Le client le renvoie dans `If-None-Match` ; si la ressource n'a pas changé, le serveur répond **304 Not Modified** sans corps (économie de bande passante). Avec `If-Match`, il sert aussi à éviter d'écraser une modification concurrente.
- **CORS** (*Cross-Origin Resource Sharing*) : par sécurité, un navigateur interdit à une page d'un site A de lire les réponses d'une API sur un site B, sauf si l'API l'autorise avec des en-têtes comme `Access-Control-Allow-Origin`. CORS ne concerne **que les navigateurs** : un script ou une application mobile n'y est pas soumis, ce n'est donc pas une protection de l'API.
- **OpenAPI** : un fichier (`openapi.yaml`) qui décrit le **contrat** de l'API (URL, méthodes, paramètres, schémas JSON, statuts). Il sert à générer une documentation, des clients et des tests.

Une API publiée est un **contrat** : renommer le champ `titre` en `nom` casse tous les clients existants. C'est un **changement non rétrocompatible**, qui demande une nouvelle version (`/api/v2/…`) ou une période de transition où les deux champs coexistent.

> [!attention]
> Une application mobile ne doit jamais interroger **directement** la base de données du serveur : elle contournerait toutes les règles (validation, autorisations) et embarquerait les identifiants de la base. Elle passe par l'API.

## Les pièges classiques

- Des verbes dans les URL (`/api/getFilms`, `/api/films/7/supprimer`).
- Répondre 200 à tout, y compris aux erreurs.
- Confondre 400 (requête illisible) et 422 (règle métier), ou 401 et 403.
- Renvoyer un corps avec un 204, ou un message d'erreur technique au client.
- Démarrer une session PHP pour une API.

> [!retenir]
> - Des ressources désignées par des noms (`/api/films/7`) ; l'action est portée par la méthode.
> - `POST` → 201 + `Location` ; `DELETE` → 204 sans corps ; liste vide → 200 avec `[]`.
> - 400 JSON illisible, 415 mauvais format, 422 règle métier, 401 non authentifié, 403 interdit, 404 introuvable.
> - `GET`, `PUT`, `DELETE` sont idempotents ; `POST` non.
> - En PHP : `php://input` + `json_decode`, puis `http_response_code()`, `Content-Type: application/json` et `json_encode`.
> - REST : six contraintes (le code à la demande est facultative), sans état ; niveau 2 de Richardson = méthodes et statuts ; niveau 3 = HATEOAS.
