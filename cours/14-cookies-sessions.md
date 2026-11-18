---
resume: Retenir des informations d'une requête à l'autre avec les cookies (côté navigateur) et les sessions (côté serveur), et sécuriser la connexion et la déconnexion d'un utilisateur.
---

> [!objectifs]
> - Comprendre pourquoi HTTP a besoin des cookies et des sessions.
> - Créer, lire, valider et supprimer un cookie avec les bonnes options.
> - Utiliser une session : démarrage, lecture, écriture, messages flash.
> - Connecter et déconnecter un utilisateur sans faille de session.

## HTTP ne se souvient de rien

Chaque requête HTTP est indépendante : sans aide, le serveur ne sait pas que deux requêtes viennent du même visiteur. Pour retenir un thème choisi, un panier ou une connexion, on dispose de deux outils :

- le **cookie** : une petite donnée **stockée par le navigateur** et renvoyée automatiquement au serveur à chaque requête vers le même site ;
- la **session** : des données **stockées sur le serveur**, retrouvées grâce à un identifiant transmis… dans un cookie.

## Les cookies

Le serveur dépose un cookie avec l'en-tête `Set-Cookie` ; le navigateur le renvoie ensuite dans l'en-tête `Cookie` de chaque requête. En PHP, `setcookie()` envoie le cookie, et `$_COOKIE` contient ceux que le navigateur a envoyés.

```php
setcookie('theme', 'sombre', [
    'expires' => time() + 180 * 24 * 3600,   // 180 jours
    'path' => '/',
    'secure' => true,                         // envoyé seulement en HTTPS
    'httponly' => true,                       // illisible par JavaScript
    'samesite' => 'Lax',                      // pas envoyé par les requêtes « venant d'ailleurs »
]);
```

- Comme `header()`, `setcookie()` doit être appelé **avant tout affichage**.
- Sans `expires`, c'est un **cookie de session** : il disparaît à la fermeture du navigateur. Avec `expires`, il est **persistant** jusqu'à la date indiquée (un horodatage en secondes). Une préférence comme un thème peut durer quelques mois.
- `$_COOKIE` reflète la requête **reçue** : juste après `setcookie('theme', 'sombre', …)`, `$_COOKIE['theme']` contient encore l'**ancienne** valeur (ou rien). La nouvelle valeur n'arrivera qu'avec la requête suivante.

Pour **supprimer** un cookie, on le redéfinit avec une date d'expiration **dans le passé** (et le même `path`) :

```php
setcookie('theme', '', ['expires' => time() - 3600, 'path' => '/']);
```

### Les options de sécurité

| Option | Effet | Protège contre |
|---|---|---|
| `httponly` | le cookie n'est pas accessible en JavaScript (`document.cookie`) | le vol de cookie par un script injecté (XSS) |
| `secure` | le cookie n'est envoyé qu'en HTTPS | l'interception sur le réseau |
| `samesite=Lax` | le cookie n'accompagne pas les requêtes POST (et les sous-requêtes) déclenchées depuis un autre site | une partie des attaques CSRF (chapitre 15) |

En développement local en `http://`, un cookie `secure` peut ne pas être renvoyé par certains navigateurs : on rend donc cette option **configurable** (activée en production, où le site est en HTTPS), plutôt que de la supprimer.

### Un cookie n'est pas une donnée de confiance

Le cookie est stocké chez l'utilisateur, qui peut le **lire et le modifier** à volonté. Donc :

- on **valide** toujours la valeur d'un cookie en la lisant, comme n'importe quelle donnée reçue ;
- on n'y stocke **jamais** d'information sensible ou décisive : mot de passe, rôle (`admin=1`), prix, identifiant d'utilisateur « de confiance ».

```php
$theme = $_COOKIE['theme'] ?? 'clair';
if (!in_array($theme, ['clair', 'sombre'], true)) {
    $theme = 'clair';   // valeur inattendue, peut-être malveillante : on l'ignore
}
```

Avec cette **liste blanche**, un cookie trafiqué contenant `"><script>alert(1)</script>` est simplement remplacé par la valeur par défaut : il n'atteint jamais la page.

Un cookie est limité à environ **4 Ko**. Regrouper toutes les préférences dans un unique cookie JSON est possible, mais il faut alors réécrire tout le cookie à chaque changement et **valider chaque clé** après `json_decode()`.

> [!astuce]
> Les cookies **nécessaires** au fonctionnement (session, panier, préférence d'affichage) ne demandent pas de consentement. Les cookies **analytiques** ou publicitaires, si : l'utilisateur doit pouvoir accepter, refuser, puis retirer son consentement (RGPD).

## Les sessions

Une session garde les données **sur le serveur**. Le navigateur ne reçoit qu'un **identifiant de session**, une longue chaîne aléatoire, dans le cookie `PHPSESSID`. Pense à un **vestiaire** : le cookie est le numéro du ticket, visible ; le manteau (les données) reste dans le casier, côté serveur.

```php
session_start();   // en haut de chaque page qui utilise la session, avant tout affichage

$_SESSION['favoris'] ??= [];          // initialise si absent
$_SESSION['favoris'][] = 42;           // écrit
echo count($_SESSION['favoris']);      // lit
```

- `session_start()` crée une session ou reprend celle dont le navigateur a envoyé l'identifiant. Il envoie un cookie, donc il doit être appelé **avant tout affichage**.
- `$_SESSION` se lit et s'écrit comme un tableau ; PHP l'enregistre automatiquement à la fin du script.
- `$_SESSION['favoris'] ??= [];` crée la liste seulement si elle n'existe pas encore, sans écraser des favoris déjà enregistrés.

Une opération sur la session peut être rendue **idempotente** (la répéter ne change pas le résultat) : avant d'ajouter un favori, on vérifie qu'il n'y est pas déjà avec `in_array($id, $_SESSION['favoris'], true)`.

### Les messages flash

Un **message flash** (« Article ajouté ») est défini pendant le traitement d'un POST, puis affiché **une seule fois** sur la page suivante (après la redirection du motif PRG) :

```php
// pendant le POST
$_SESSION['flash'] = 'Article ajouté au panier.';
header('Location: /panier', true, 303);
exit;

// sur la page affichée ensuite (GET)
if (isset($_SESSION['flash'])) {
    echo '<p class="succes">' . htmlspecialchars($_SESSION['flash']) . '</p>';
    unset($_SESSION['flash']);   // lu puis supprimé : il ne réapparaîtra pas au rafraîchissement
}
```

## Connexion et déconnexion

À la connexion, une fois le mot de passe **vérifié** (chapitre 15) :

```php
session_regenerate_id(true);                // nouvel identifiant de session, l'ancien est détruit
$_SESSION['utilisateur_id'] = $utilisateur['id'];
```

- `session_regenerate_id(true)` change l'identifiant au moment où l'utilisateur obtient des droits. C'est la parade à la **fixation de session**.
- On stocke l'**identifiant** de l'utilisateur, pas son mot de passe ni son rôle : le rôle est relu en base quand il faut décider d'une autorisation.

> [!attention]
> Écrire `$_SESSION['utilisateur_id']` ne prouve rien en soi : c'est le code qui l'écrit **uniquement après** une vérification réussie du mot de passe qui fait de la session une preuve de connexion. La session n'est pas modifiable par l'utilisateur, mais ton propre code doit être rigoureux.

La **déconnexion** complète comporte trois étapes :

```php
$_SESSION = [];                                          // 1. vider les données
$p = session_get_cookie_params();
setcookie(session_name(), '', [                          // 2. supprimer le cookie de session
    'expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
    'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
]);
session_destroy();                                       // 3. détruire la session côté serveur
header('Location: /', true, 303);
exit;
```

### Fixation et vol de session

| Attaque | Principe | Parade |
|---|---|---|
| **Fixation** | l'attaquant fait utiliser à la victime un identifiant qu'il **connaît déjà**, puis attend qu'elle se connecte | `session_regenerate_id(true)` à la connexion ; `session.use_strict_mode` |
| **Vol** (détournement) | l'attaquant **récupère** l'identifiant d'une session active (script injecté, réseau non chiffré) | cookie `httponly` et `secure`, HTTPS partout, expiration |

### L'expiration

Une session doit expirer après une période d'**inactivité** (par exemple 20 minutes, soit 1 200 secondes). On mémorise l'heure de la dernière activité et on la compare à chaque requête :

```php
$limite = 20 * 60;
if (isset($_SESSION['derniere_activite']) && time() - $_SESSION['derniere_activite'] > $limite) {
    $_SESSION = [];
    session_destroy();
    // rediriger vers la page de connexion
}
$_SESSION['derniere_activite'] = time();
```

Le cookie ne porte que l'identifiant ; c'est le **serveur** qui décide si la session est encore vivante. Un cookie encore présent dans le navigateur sur une session expirée côté serveur ne donne plus accès à rien.

## Les pièges classiques

- Appeler `setcookie()` ou `session_start()` après un affichage.
- Lire `$_COOKIE` juste après `setcookie()` en espérant la nouvelle valeur.
- Stocker un rôle, un prix ou un mot de passe dans un cookie.
- Oublier `session_regenerate_id(true)` à la connexion.
- Une déconnexion qui se contente de `unset($_SESSION['utilisateur_id'])`.

> [!retenir]
> - Cookie : stocké par le navigateur, lisible et modifiable par l'utilisateur ; on le valide toujours (liste blanche).
> - Options : `httponly` (pas de JavaScript), `secure` (HTTPS), `samesite=Lax` (pas d'envoi depuis un autre site en POST).
> - Pour supprimer un cookie : expiration dans le passé. `$_COOKIE` ne change qu'à la requête suivante.
> - Session : données côté serveur, identifiant dans le cookie `PHPSESSID` ; `session_start()` avant tout affichage.
> - Connexion : `session_regenerate_id(true)` puis l'identifiant en session ; déconnexion : vider, supprimer le cookie, `session_destroy()`.
