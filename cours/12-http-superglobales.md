---
resume: Comprendre le dialogue requête-réponse du protocole HTTP, choisir entre GET et POST, lire les données reçues avec les superglobales sans jamais leur faire confiance.
---

> [!objectifs]
> - Décrire une requête et une réponse HTTP : méthode, chemin, en-têtes, corps, code de statut.
> - Choisir entre GET et POST selon ce que fait la requête.
> - Lire `$_GET`, `$_POST` et `$_SERVER` correctement, y compris quand une valeur manque ou a un type inattendu.
> - Savoir que tout ce qui vient du client peut être falsifié.

## Une requête, une réponse

Le web repose sur **HTTP** : le navigateur (le **client**) envoie une **requête**, le serveur renvoie une **réponse**. Puis la connexion logique est terminée : HTTP est **sans état**, le serveur ne se souvient de rien d'une requête à l'autre (les cookies et sessions, au chapitre 14, servent à compenser cela).

```http
GET /recherche?q=alien&page=2 HTTP/1.1
Host: cinema.exemple
User-Agent: Mozilla/5.0 …
Accept: text/html
```

Une requête contient une **méthode** (`GET`), un **chemin** avec éventuellement une **chaîne de requête** (`?q=alien&page=2`), des **en-têtes** (`Host`, `User-Agent`…) et, pour certaines méthodes, un **corps**.

```http
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
Set-Cookie: theme=sombre; Path=/; HttpOnly

<!DOCTYPE html>
<html lang="fr">…
```

La réponse commence par un **code de statut** (`200 OK`), puis des en-têtes, puis le corps (la page HTML, du JSON, une image…). En PHP, tout ce que le script affiche devient le corps ; `header()` ajoute un en-tête et `http_response_code(404)` fixe le code de statut.

## Les codes de statut

| Code | Signification | Exemple d'usage |
|---|---|---|
| `200` | OK | Page affichée normalement |
| `201` | Created | Ressource créée (API) |
| `204` | No Content | Succès sans contenu à renvoyer |
| `301` / `302` | Redirection permanente / temporaire | Page déplacée |
| `303` | See Other | Redirection après un POST (chapitre 13) |
| `400` | Bad Request | Requête mal formée, paramètres incohérents |
| `401` | Unauthorized | Non authentifié |
| `403` | Forbidden | Authentifié mais pas autorisé |
| `404` | Not Found | Ressource introuvable |
| `405` | Method Not Allowed | Méthode non acceptée par cette adresse |
| `422` | Unprocessable Content | Données reçues mais invalides |
| `500` | Internal Server Error | Erreur du serveur |

La famille se lit au premier chiffre : **2xx** succès, **3xx** redirection, **4xx** erreur du client, **5xx** erreur du serveur.

## GET ou POST ?

**GET** sert à **consulter**. Les paramètres voyagent dans l'URL (la chaîne de requête) : on peut **mettre la page en favori, la partager, revenir en arrière**. C'est la bonne méthode pour une recherche, un filtre, une pagination. GET doit être **sûr** (consulter ne modifie rien) et **idempotent** (répéter la requête produit le même état final).

**POST** sert à **agir** : créer, modifier, supprimer, se connecter. Les données voyagent dans le **corps** de la requête.

> [!attention]
> Une action qui **modifie** des données ne doit jamais passer par GET : un lien peut être préchargé par le navigateur, suivi par un robot d'indexation, rejoué depuis l'historique ou déclenché depuis un autre site par une simple image. Une suppression ou une création se fait toujours en POST.

POST ne **protège** pas pour autant les données : elles ne sont pas dans l'URL, mais restent lisibles dans les outils du navigateur et modifiables par n'importe qui. Seul **HTTPS** chiffre les échanges.

Les formulaires HTML ne connaissent que GET et POST. Les autres méthodes (`PUT`, `PATCH`, `DELETE`) servent surtout aux API (chapitre 22). Pour simuler un `DELETE` depuis un formulaire, on envoie un POST avec un champ caché `_method` valant `DELETE`, que le routeur interprète.

## Les superglobales

PHP range les données de la requête dans des tableaux disponibles **partout** : les superglobales.

| Superglobale | Contenu |
|---|---|
| `$_GET` | les paramètres de la chaîne de requête (l'URL) |
| `$_POST` | les champs d'un formulaire envoyé en POST |
| `$_SERVER` | des informations sur la requête et le serveur |
| `$_COOKIE` | les cookies envoyés par le navigateur |
| `$_SESSION` | les données de session (après `session_start()`) |
| `$_FILES` | les fichiers envoyés par un formulaire |

`$_REQUEST` mélange `$_GET`, `$_POST` (et parfois `$_COOKIE`) : on ne sait plus d'où vient une valeur, et une même clé dans deux sources crée une ambiguïté. Lis la source précise.

Dans `$_SERVER`, les entrées les plus utiles sont `REQUEST_METHOD` (la méthode : `'GET'`, `'POST'`…), `REQUEST_URI` (le chemin demandé) et `REMOTE_ADDR` (l'adresse IP du client). Les entrées qui commencent par `HTTP_` sont des **en-têtes envoyés par le client**, donc des déclarations qu'il peut écrire comme il veut : `HTTP_USER_AGENT` ou `HTTP_REFERER` ne prouvent rien.

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // traiter le formulaire
}
```

## Lire un paramètre correctement

Un paramètre peut être **absent**. Accéder directement à `$_GET['q']` provoquerait alors un avertissement « Undefined array key ». L'opérateur `??` donne une valeur par défaut :

```php
$q = $_GET['q'] ?? '';                  // '' si le paramètre est absent
$page = (int) ($_GET['page'] ?? 1);
```

Toutes les valeurs reçues sont des **chaînes**… ou des **tableaux**. Avec la notation `[]` dans le nom, PHP construit un tableau : l'URL `?genres[]=drame&genres[]=sf` donne `$_GET['genres'] === ['drame', 'sf']`. N'importe qui peut donc envoyer `?q[]=alien` au lieu de `?q=alien`, et `trim($_GET['q'])` lève alors une `TypeError`. Vérifie le type avant de t'en servir :

```php
$q = $_GET['q'] ?? '';
if (!is_string($q)) {
    http_response_code(400);   // requête mal formée
    exit('Paramètre invalide.');
}
$q = trim($q);
```

`filter_input()` lit et valide en une fois. Il retourne `null` si le champ est **absent**, `false` s'il est **invalide**, et la valeur convertie sinon :

```php
$annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);
```

## Ce que le navigateur envoie (ou pas)

- Un champ **sans attribut `name`** n'est pas envoyé : c'est le `name` qui devient la clé dans `$_GET` ou `$_POST`.
- Une **case à cocher non cochée** n'est pas envoyée du tout : on teste sa présence, `isset($_POST['newsletter'])`.
- Pour **envoyer un fichier**, le formulaire doit être en POST avec `enctype="multipart/form-data"` ; le fichier arrive dans `$_FILES`.

## Ne jamais faire confiance au client

Tout ce qui vient du client est **contrôlable par un utilisateur malveillant** : l'URL, tous les champs du formulaire (y compris les champs cachés et les valeurs d'une liste déroulante), les cookies, les en-têtes. Il peut modifier la page avec les outils du navigateur, ou forger la requête de toutes pièces avec un outil comme `curl`.

Les attributs HTML `required`, `min`, `maxlength` ou la liste fermée d'un `<select>` améliorent l'**expérience utilisateur** (un message immédiat), mais ne **protègent rien** : la validation qui compte se fait **côté serveur**, à chaque requête (chapitre 13).

## Séparer la lecture de la requête du traitement

Une fonction peut techniquement lire `$_GET` (c'est une superglobale), mais c'est une mauvaise idée. Une fonction de filtrage doit recevoir ses critères **en paramètres** :

```php
// le traitement : une fonction pure, testable sans requête HTTP
function filtrerFilms(array $films, string $recherche, ?int $annee): array
{
    return array_values(array_filter(
        $films,
        fn(array $f) => ($recherche === '' || stripos($f['titre'], $recherche) !== false)
            && ($annee === null || $f['annee'] === $annee),
    ));
}

// le contrôleur : lit et contrôle la requête, puis appelle le traitement
$q = $_GET['q'] ?? '';
$annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);
$resultats = filtrerFilms($films, is_string($q) ? trim($q) : '', is_int($annee) ? $annee : null);
```

La fonction peut alors être testée, réutilisée (dans une API, une commande) et comprise sans connaître la requête. Si rien ne correspond, elle retourne un **tableau vide** `[]` : pas `false`, pas `null`. Le code appelant n'a pas de cas particulier à traiter, et la vue affiche « Aucun résultat ».

> [!astuce]
> Un `var_dump($_GET)` est très utile pour comprendre ce qu'envoie un formulaire pendant l'apprentissage. Retire-le une fois le problème compris : il ne doit jamais rester dans le code livré.

## Les pièges classiques

- Accéder à `$_GET['x']` sans `??` ni `isset()`.
- Supposer qu'un paramètre est une chaîne alors qu'il peut être un tableau.
- Faire une action qui modifie des données en GET.
- Croire que POST, un champ caché ou un `<select>` empêchent l'utilisateur de choisir sa valeur.
- Utiliser `$_REQUEST`, ou faire confiance à un en-tête comme `User-Agent`.

> [!retenir]
> - HTTP : une requête (méthode, chemin, en-têtes, corps) et une réponse (statut, en-têtes, corps), sans mémoire entre les deux.
> - GET pour consulter (URL partageable, sûr, idempotent) ; POST pour agir. POST ne chiffre rien : c'est le rôle de HTTPS.
> - `$_GET`, `$_POST`, `$_SERVER['REQUEST_METHOD']` ; évite `$_REQUEST`.
> - Une valeur reçue peut être absente (`??`), d'un autre type (`is_string()`), ou falsifiée.
> - La validation HTML est du confort ; la seule protection est côté serveur.
> - Les fonctions de traitement reçoivent leurs critères en paramètres et retournent `[]` quand rien ne correspond.
