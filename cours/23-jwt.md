---
resume: Authentifier les appels à une API avec un jeton Bearer au format JWT : structure, signature, claims, vérification dans un middleware, et limites à connaître.
---

> [!objectifs]
> - Distinguer authentification et autorisation dans une API, session et jeton Bearer.
> - Comprendre la structure d'un JWT, ses claims et le rôle de sa signature.
> - Émettre un jeton à la connexion et le vérifier dans un middleware Slim.
> - Connaître les limites des JWT : vol, expiration, stockage, révocation.

## Qui appelle, et a-t-il le droit ?

Sans contrôle, `DELETE /api/livres/7` supprime le livre. Le routeur connaît la **méthode** et l'**URL**, mais ni **qui** appelle, ni s'il en a le **droit**. Deux vérifications s'imposent, dans cet ordre :

1. **Authentification** : **qui es-tu ?** L'appelant présente une preuve d'identité valide. Sinon : **401**.
2. **Autorisation** : **as-tu le droit** de faire **cette** action sur **cette** ressource ? Sinon : **403**.

Un jeton valide prouve l'identité, **pas tous les droits** : si Nina, authentifiée, demande la suppression d'un livre qui appartient à quelqu'un d'autre, l'API répond **403** (ou 404 si elle masque volontairement l'existence des ressources des autres). Après ce refus, le livre doit toujours exister.

## Session ou jeton ?

Un site PHP classique, dont les pages et le formulaire de connexion sont sur la **même origine**, utilise le plus simplement une **session** avec son cookie (chapitre 14) : l'état est gardé **côté serveur**.

Une API utilisée par plusieurs clients (site en JavaScript, application mobile, scripts) et qui veut rester **sans état** utilise plutôt un **jeton** que le client présente à **chaque** requête, dans l'en-tête `Authorization` :

```http
GET /api/livres HTTP/1.1
Host: api.exemple
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiJ9.c2lnbmF0dXJl…
```

« **Bearer** » (porteur) décrit **comment** le jeton est présenté : quiconque le **porte** est considéré comme authentifié. Le jeton peut être une chaîne aléatoire **opaque** (que le serveur cherche dans sa base) ou un **JWT** : Bearer ne dit rien du format.

Une **clé d'API** est une autre valeur opaque, qui identifie un **appelant** (souvent un logiciel, un service partenaire) avec des permissions limitées. Elle ne chiffre rien et ne représente pas un utilisateur connecté.

## Le JWT

Un **JWT** (*JSON Web Token*, norme **RFC 7519**) est un jeton qui **contient** lui-même des informations, appelées **claims**, et qui est **signé** pour qu'on puisse vérifier qu'il n'a pas été modifié. Il se compose de trois parties encodées en **Base64URL**, séparées par des points :

```text
en-tête.charge-utile.signature
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiIsImV4cCI6MTc5NTAwMDAwMH0.Xb3…
```

- L'**en-tête** (*header*) indique l'algorithme de signature : `{"alg": "HS256", "typ": "JWT"}`.
- La **charge utile** (*payload*) contient les claims : `{"sub": "42", "role": "lecteur", "exp": 1795000000}`.
- La **signature** garantit l'intégrité des deux premières parties.

### Les claims standard

| Claim | Signification |
|---|---|
| `iss` | *issuer* : qui a émis le jeton |
| `sub` | *subject* : l'utilisateur concerné (son identifiant) |
| `aud` | *audience* : l'API à laquelle le jeton est **destiné** |
| `exp` | *expiration* : timestamp Unix après lequel le jeton est refusé |
| `iat` | *issued at* : date d'émission |
| `nbf` | *not before* : date avant laquelle le jeton n'est pas encore valable |
| `jti` | identifiant unique du jeton (utile pour une liste de révocation) |

> [!attention]
> La charge utile d'un JWT signé est seulement **encodée** en Base64URL, **pas chiffrée** : n'importe qui peut la lire en la décodant. On n'y met **jamais** de mot de passe ni de donnée confidentielle : seulement un identifiant, un rôle, des dates.

### La signature HS256

Avec **HS256** (HMAC-SHA256), la signature est calculée sur `base64url(en-tête) + "." + base64url(charge utile)` avec une **clé secrète** connue du seul serveur :

```php
function base64url(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

$entete = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
$charge = base64url(json_encode(['sub' => '42', 'exp' => time() + 900]));
$signature = base64url(hash_hmac('sha256', "$entete.$charge", $cleSecrete, true));
$jeton = "$entete.$charge.$signature";
```

Si un attaquant remplace `"role": "lecteur"` par `"role": "admin"` sans connaître la clé, la signature recalculée par le serveur ne correspond plus : le jeton est **rejeté** (401). La signature empêche de **falsifier** un jeton ; elle n'empêche pas de le **voler** et de l'utiliser tel quel.

Ce code sert à comprendre. En pratique, on utilise une **bibliothèque éprouvée** comme `firebase/php-jwt` : les détails (comparaison en temps constant, vérification de l'algorithme, des dates, refus de `"alg": "none"`) sont faciles à rater.

## Émettre et vérifier un jeton en PHP

La clé secrète est **longue et aléatoire**, et rangée **hors du code versionné** (variable d'environnement, fichier de configuration non versionné), comme le mot de passe de la base.

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// POST /api/login : après password_verify() réussi
$maintenant = time();
$charge = [
    'iss' => 'lectures-app',
    'aud' => 'lectures-api',
    'sub' => (string) $utilisateur['id'],
    'iat' => $maintenant,
    'exp' => $maintenant + 900,        // valable 15 minutes
];
$jeton = JWT::encode($charge, $cleSecrete, 'HS256');

// à chaque requête protégée
$claims = JWT::decode($jeton, new Key($cleSecrete, 'HS256'));
if ($claims->aud !== 'lectures-api') {
    // jeton émis pour une autre API : refusé (401)
}
```

- `new Key($cle, 'HS256')` impose l'**algorithme attendu** : le serveur ne laisse pas le jeton choisir lui-même comment il doit être vérifié (un attaquant pourrait sinon annoncer un algorithme plus faible).
- `JWT::decode()` vérifie la **signature** et les **dates** (`exp`, `nbf`, `iat`) et lève une exception sinon (par exemple `ExpiredException` pour un jeton expiré → **401**). Les claims propres à ton application (`aud`, `iss`) se vérifient ensuite : un jeton valide mais destiné à une **autre** API est refusé.
- La séquence complète : le client envoie ses identifiants à `POST /api/login` → l'API vérifie le mot de passe et renvoie un jeton → le client l'envoie dans `Authorization: Bearer …` à chaque appel → l'API vérifie le jeton, puis les droits.

### La route de connexion

- Elle répond **401** avec **le même message** pour un e-mail inconnu et un mauvais mot de passe (pas d'énumération des comptes).
- Sa réponse porte `Cache-Control: no-store` : elle contient un secret, aucun cache (navigateur, proxy) ne doit en garder une copie.
- Elle **limite les tentatives** et répond **429 Too Many Requests** au-delà : protéger les routes par JWT n'empêche pas d'essayer des milliers de mots de passe sur la route de connexion.

## Le middleware d'authentification (Slim 4)

Plutôt que de vérifier le jeton dans chaque route, un **middleware** s'en charge pour tout un groupe de routes : il lit l'en-tête `Authorization`, vérifie le jeton, et **refuse avec 401** s'il est absent, expiré ou invalide. Sinon, il attache l'identité à la requête et laisse passer.

```php
$app->get('/api/livres', [$api, 'liste']);              // publique
$app->post('/api/login', [$api, 'connexion']);          // publique
$app->group('/api', function (RouteCollectorProxy $groupe) use ($api) {
    $groupe->post('/livres', [$api, 'ajouter']);        // jeton exigé
    $groupe->delete('/livres/{id}', [$api, 'supprimer']);
})->add($authentification);
```

Ici, la liste et la connexion sont publiques ; la création et la suppression exigent un jeton valide. Les contrôleurs vérifient ensuite l'**autorisation** (rôle, propriétaire de la ressource), car le middleware ne connaît pas la ressource visée.

Un échec d'authentification se signale par le **statut 401**, pas par un `200` avec `{"error": "non autorisé"}` : les clients, les outils et les journaux se fient au statut.

Attendus typiques : `GET /api/livres` public → **200** ; `POST /api/login` avec un mauvais mot de passe → **401** ; `DELETE` autorisé → **204** ; `DELETE` avec un jeton valide sur le livre d'un autre → **403**, et le livre existe toujours.

## Les limites des JWT

- Un JWT **volé** reste utilisable **jusqu'à son expiration** : la signature est valide, le voleur est un « porteur » comme un autre. **Falsifier** (modifier) un jeton est empêché par la signature ; **voler** un jeton ne l'est pas.
- Se **déconnecter** côté client (effacer le jeton) ne rend pas inutilisable une **copie** volée : un JWT ne se révoque pas tout seul. Il faut une durée de vie **courte**, et éventuellement une liste de révocation (par `jti`).
- Une durée de vie courte réduit la fenêtre d'attaque, mais oblige à **renouveler** l'accès plus souvent : c'est le rôle du **refresh token**, un jeton distinct, de longue durée, utilisé **uniquement** pour obtenir un nouvel access token, stocké et révocable côté serveur.
- Stocké dans `localStorage`, un jeton est **lisible par tout script** de la page : une faille XSS suffit à le voler. Un cookie `HttpOnly` + `Secure` le protège de JavaScript, mais il est alors envoyé automatiquement par le navigateur, ce qui réintroduit le risque **CSRF** à traiter (`SameSite`, jeton anti-CSRF). Aucun stockage ne règle tout.
- **CORS** n'empêche pas un voleur de jeton d'appeler l'API depuis son propre programme : CORS ne concerne que les navigateurs.
- Toute exception aux vérifications (« si le rôle vaut admin, on saute la vérification de la signature ») est une faille : le rôle est **dans** le jeton, donc n'importe qui pourrait l'écrire. La signature se vérifie **toujours**, avant de croire quoi que ce soit.

## JWT, OAuth 2.0, OpenID Connect

- **JWT** est un **format** de jeton, utilisable sans rien d'autre.
- **OAuth 2.0** est un **cadre de délégation d'accès** : une application obtient un **access token** pour accéder à une API au nom d'un utilisateur, sans connaître son mot de passe (« Autoriser cette application à lire vos photos »). Cet access token n'est pas forcément un JWT.
- **OpenID Connect** est une couche d'**identité** bâtie sur OAuth 2.0 (« Se connecter avec… ») : il ajoute un **ID Token**, généralement au format JWT, qui **informe le client** de l'identité de l'utilisateur, alors que l'access token sert à **appeler une API**.

## Les pièges classiques

- Mettre des données confidentielles dans la charge utile.
- Écrire sa propre vérification de signature, ou laisser le jeton choisir l'algorithme.
- Oublier de vérifier `exp` ou `aud`, ou confondre authentification et autorisation.
- Répondre 200 avec un message d'erreur au lieu de 401 / 403.
- Des jetons valables des semaines, sans moyen de révocation.

> [!retenir]
> - Authentification (401 si absente ou invalide) puis autorisation (403) ; un jeton valide ne donne pas tous les droits.
> - `Authorization: Bearer <jeton>` ; Bearer = mode de présentation, JWT = format.
> - JWT = `en-tête.charge.signature` en Base64URL ; la charge est lisible par tous, la signature empêche la falsification.
> - Claims : `sub`, `exp`, `iat`, `aud`, `iss` ; clé secrète hors du code ; `JWT::decode($jeton, new Key($cle, 'HS256'))`.
> - Un middleware vérifie le jeton ; les contrôleurs vérifient les droits sur la ressource.
> - Un JWT volé reste valable jusqu'à `exp` : durée courte, refresh token, prudence sur le stockage.
