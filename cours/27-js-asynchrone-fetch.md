---
resume: Comprendre le code asynchrone (boucle d'événements, promesses, async/await) et dialoguer avec une API PHP grâce à fetch, en gérant les erreurs, le JSON, CORS et l'expérience utilisateur.
---

> [!objectifs]
> - Comprendre pourquoi JavaScript est asynchrone, et l'ordre d'exécution du code.
> - Utiliser les promesses avec `then` puis avec `async` / `await`.
> - Appeler une API avec `fetch()`, envoyer et recevoir du JSON, et traiter toutes les erreurs.
> - Faire dialoguer fetch avec une API PHP (corps JSON, CORS) et soigner l'interface pendant l'attente.

## Un seul fil, beaucoup d'attentes

JavaScript exécute le code d'une page sur **un seul fil** : pendant qu'une instruction s'exécute, rien d'autre ne se passe (pas même l'affichage). Or une requête réseau peut prendre plusieurs secondes. Si le script attendait la réponse en bloquant, la page serait **figée**.

Les opérations longues sont donc **asynchrones** : on les **lance**, le script **continue**, et une fonction est rappelée **plus tard**, quand le résultat est prêt. Pendant qu'un `fetch` attend la réponse du serveur, l'utilisateur peut continuer à faire défiler, cliquer, saisir.

```js
console.log("A");
setTimeout(() => console.log("B"), 0);
console.log("C");
// affiche A, C, puis B
```

`setTimeout(rappel, 1000)` programme l'exécution de `rappel` **dans au moins** 1 000 ms, sans bloquer : le script continue immédiatement. Même avec un délai de 0, le rappel attend que le code en cours soit **terminé**.

### La boucle d'événements

Le moteur exécute d'abord tout le code **synchrone** en cours. Ensuite, la **boucle d'événements** traite les rappels en attente, dans un ordre précis :

1. d'abord **toutes les microtâches** : les suites de promesses (`then`, la reprise après un `await`) ;
2. puis **une tâche** de la file : un rappel de `setTimeout`, un événement (clic…) ; puis de nouveau les microtâches, et ainsi de suite.

```js
console.log(1);
setTimeout(() => console.log(4), 0);
Promise.resolve().then(() => console.log(3));
console.log(2);
// affiche 1, 2, 3, 4 : le code synchrone, puis la microtâche, puis le setTimeout
```

## Les promesses

Une **promesse** (`Promise`) représente un résultat **qui n'est pas encore disponible**. Elle est d'abord **en attente**, puis devient **tenue** (avec une valeur) ou **rompue** (avec une erreur). On réagit avec `then()` et `catch()` :

```js
fetch("/api/produits")
    .then((reponse) => reponse.json())
    .then((donnees) => console.log(donnees))
    .catch((erreur) => console.error("Échec :", erreur));
```

`Promise.all([p1, p2, p3])` attend **plusieurs** promesses lancées en parallèle : elle est tenue avec le tableau de **tous** les résultats (dans l'ordre), ou rompue dès que **l'une** échoue.

## async / await

`async` et `await` permettent d'écrire du code asynchrone qui **se lit comme du code synchrone** :

```js
async function chargerProduits() {
    const reponse = await fetch("/api/produits");   // attend sans bloquer la page
    const donnees = await reponse.json();
    return donnees;
}
```

- `await` **suspend la fonction** jusqu'à ce que la promesse soit réglée, puis donne sa valeur. Pendant ce temps, le reste de la page continue de fonctionner.
- `await` s'utilise **dans une fonction `async`** (ou au niveau supérieur d'un module).
- Une fonction `async` renvoie **toujours une promesse** : le `return donnees` ci-dessus tient la promesse avec `donnees`. Le code appelant doit donc lui aussi utiliser `await` ou `then()`.
- `const donnees = await reponse.json();` équivaut, sans `await`, à `reponse.json().then((donnees) => …)`.

Sans `await`, `const reponse = fetch("/api/produits");` ne contient **pas** la réponse, mais une **promesse** en attente : `reponse.ok` vaut alors `undefined`.

## fetch : appeler une API

`fetch(url)` envoie une requête HTTP et renvoie **immédiatement** une promesse, tenue avec un objet `Response` dès que les **en-têtes** de la réponse sont arrivés.

```js
async function chargerProduits() {
    try {
        const reponse = await fetch("/api/produits", {
            headers: { Accept: "application/json" },
        });
        if (!reponse.ok) {
            throw new Error(`Erreur HTTP ${reponse.status}`);
        }
        return await reponse.json();
    } catch (erreur) {
        console.error(erreur);
        afficherMessage("Impossible de charger les produits. Réessaie plus tard.");
        return [];
    }
}
```

> [!attention]
> La promesse de `fetch()` n'est **rompue** qu'en cas d'échec **réseau** (serveur injoignable, connexion coupée, requête bloquée par CORS). Une réponse **404** ou **500** est une réponse HTTP valide : la promesse est **tenue**. Il faut donc tester **`reponse.ok`**, qui vaut `true` pour les statuts **200 à 299** (y compris 201 Created) et `false` sinon.

- `reponse.status` donne le code (404, 422…), `reponse.ok` le résumé.
- `reponse.json()` lit le corps et le décode : elle renvoie **une promesse** (le corps peut ne pas être encore entièrement arrivé), d'où le second `await`.
- Un `try … catch` autour des `await` attrape les échecs réseau, les erreurs lancées pour un statut non `ok`, et le JSON invalide.

### Envoyer du JSON

```js
const reponse = await fetch("/api/produits", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ nom: "Stylo", quantite: 12 }),
});
if (reponse.status === 422) {
    const { erreur } = await reponse.json();   // message de validation renvoyé par l'API
}
```

On précise la **méthode**, l'en-tête **`Content-Type: application/json`**, et le **corps** sérialisé avec `JSON.stringify()`.

## Côté PHP

Un corps JSON n'arrive **pas** dans `$_POST` (qui ne décode que les formulaires classiques). PHP le lit sur le flux `php://input` (chapitre 22) :

```php
$donnees = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
```

### CORS

Si la page est servie par `http://localhost:5173` (un serveur de développement front) et l'API par `http://localhost:8000`, ce sont deux **origines** différentes (le port compte). Par sécurité, le navigateur **bloque la lecture** de la réponse, sauf si l'API l'autorise explicitement :

```php
header('Access-Control-Allow-Origin: http://localhost:5173');
```

Pour une requête `POST` en JSON, le navigateur envoie d'abord une requête de vérification `OPTIONS` (*preflight*) : l'API doit aussi y répondre, avec `Access-Control-Allow-Methods` et `Access-Control-Allow-Headers`. Rappel : CORS protège les **utilisateurs du navigateur**, pas l'API elle-même (chapitre 22).

## L'interface pendant l'attente

Une requête prend du temps, et l'utilisateur doit le **savoir** :

```js
formulaire.addEventListener("submit", async (event) => {
    event.preventDefault();
    bouton.disabled = true;              // pas de double envoi pendant l'attente
    statut.textContent = "Enregistrement…";
    try {
        const reponse = await fetch("/api/produits", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(lireFormulaire()) });
        statut.textContent = reponse.ok ? "Produit enregistré." : "Le serveur a refusé l'enregistrement.";
    } catch {
        statut.textContent = "Connexion impossible : réessaie.";
    } finally {
        bouton.disabled = false;         // dans tous les cas, on rend la main
    }
});
```

- **Désactiver le bouton** pendant la requête évite qu'un utilisateur impatient ne clique deux fois et **crée deux enregistrements** (côté serveur, une contrainte d'unicité reste la vraie garantie).
- Afficher un **état** (« Enregistrement… »), puis le **résultat**, ou une **erreur compréhensible**. Avec `aria-live`, ce message est aussi annoncé aux lecteurs d'écran.
- `finally` réactive le bouton **quel que soit** le résultat : sans lui, une erreur laisserait l'interface bloquée.

## Les pièges classiques

- Oublier `await` et manipuler une promesse comme si c'était la réponse.
- Croire qu'une réponse 404 ou 500 fait échouer `fetch()` : il faut tester `reponse.ok`.
- Oublier que `reponse.json()` renvoie une promesse.
- Envoyer du JSON sans `Content-Type`, ou le chercher dans `$_POST` côté PHP.
- Laisser le bouton actif pendant la requête, ou bloqué après une erreur.

> [!retenir]
> - JavaScript ne bloque pas : les opérations longues sont asynchrones ; code synchrone, puis microtâches (promesses), puis tâches (`setTimeout`, événements).
> - Une promesse se traite avec `then` / `catch`, ou avec `await` dans une fonction `async` (qui renvoie toujours une promesse).
> - `fetch()` renvoie une promesse ; 404 et 500 ne la rompent pas : teste `reponse.ok` (200 à 299) ; `reponse.json()` est aussi une promesse.
> - Envoyer du JSON : `method`, `headers: { "Content-Type": "application/json" }`, `body: JSON.stringify(…)` ; côté PHP, `php://input`.
> - Deux origines différentes : l'API doit envoyer `Access-Control-Allow-Origin`.
> - Pendant l'attente : bouton désactivé, état affiché, erreurs claires, `finally` pour rendre la main.
