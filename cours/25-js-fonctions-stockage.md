---
resume: Écrire des fonctions JavaScript fiables, manipuler objets et tableaux avec les méthodes modernes, déboguer avec la console, et conserver des données dans le navigateur avec localStorage et JSON.
---

> [!objectifs]
> - Déclarer des fonctions avec paramètres, valeurs par défaut et `return`, et séparer calcul et interface.
> - Manipuler des objets et des tableaux d'objets : `map`, `filter`, `reduce`, `find`, déstructuration, `?.`.
> - Utiliser les outils de la console pour déboguer et mesurer.
> - Conserver des données avec `localStorage` et `sessionStorage`, en JSON, de façon robuste.

## Les fonctions

```js
function prixTTC(prixHT, taux = 0.2) {
    return prixHT * (1 + taux);
}

const total = prixTTC(10);   // 12
```

- Les instructions d'une fonction s'exécutent **seulement quand elle est appelée**, pas quand elle est déclarée.
- Dans la déclaration, `prixHT` et `taux` sont des **paramètres** ; dans l'appel, `10` est un **argument**.
- JavaScript **ne vérifie pas** le type des paramètres, ni même leur nombre : un argument manquant vaut `undefined`. C'est à la fonction de contrôler ce qu'elle reçoit.
- `return` renvoie une valeur **et termine** la fonction. Une fonction sans `return` renvoie `undefined` : avec `function double(x) { console.log(x * 2); }`, `const r = double(4)` affiche 8 mais `r` vaut `undefined`.

Une valeur par défaut ne s'applique que si l'argument est **`undefined`** (absent) : `afficherLigne("Stylo", null)` reçoit bien `null`, pas la valeur par défaut.

### Retour anticipé et valeurs « absentes »

Un `return` en début de fonction (une **clause de garde**) sort immédiatement quand les données sont inutilisables, sans imbriquer tout le reste dans un `else` :

```js
function afficherLigne(nom, quantite = 0) {
    if (nom.trim() === "") {
        return "";               // rien à afficher : on s'arrête là
    }
    return `${nom} : ${quantite}`;
}
```

Pour distinguer les cas, choisis des valeurs **qui ont un sens** : une fonction de recherche peut renvoyer `0` pour un article **épuisé** et `null` pour un article **inconnu**. On teste alors `quantite === null` : un test `if (quantite)` confondrait les deux, car `0` est falsy.

### Calcul d'un côté, interface de l'autre

Une fonction de **calcul** reçoit ses données en paramètres et **retourne** un résultat ; elle ne contient ni `prompt()`, ni `alert()`, ni accès à la page. On peut alors la tester et la réutiliser (dans une autre page, côté serveur avec Node.js). C'est le reste du programme qui demande les saisies et affiche les résultats.

```js
function estEntierPositif(valeur) {
    return Number.isInteger(valeur) && valeur > 0;
}
estEntierPositif("3");   // false : "3" est une chaîne, il faut convertir avant
```

Une variable déclarée **dans** une fonction n'existe que dans la fonction ; une fonction peut lire une constante déclarée **en dehors** (comme `const seuilAlerte = 5`), mais il vaut mieux lui passer ce dont elle a besoin en paramètre.

### Fonctions fléchées

```js
const double = (x) => x * 2;                // équivaut à function (x) { return x * 2; }
const saluer = () => console.log("Bonjour");
const somme = (a, b) => {
    const total = a + b;                    // avec un bloc { }, le return est obligatoire
    return total;
};
```

## Les objets

Un objet regroupe des **propriétés** nommées :

```js
const produit = { nom: "Stylo", quantite: 12, prix: 1.5 };
produit.quantite += 3;            // 15
produit["prix"] = 1.8;            // notation entre crochets (utile avec une clé variable)
console.log(Object.keys(produit)); // ["nom", "quantite", "prix"]

const { nom, quantite } = produit; // déstructuration : nom vaut "Stylo"
const ville = client?.adresse?.ville;   // undefined si client ou adresse vaut null/undefined
```

Comme pour un tableau, `const` empêche de réaffecter `produit`, pas de modifier ses propriétés.

### Les méthodes des tableaux

Ces méthodes reçoivent une **fonction** appelée pour chaque élément :

```js
const stock = [
    { nom: "Stylo", quantite: 12 },
    { nom: "Gomme", quantite: 0 },
    { nom: "Cahier", quantite: 4 },
];

stock.map(p => p.nom);                          // ["Stylo", "Gomme", "Cahier"]
stock.filter(p => p.quantite > 0);              // les produits disponibles
stock.reduce((total, p) => total + p.quantite, 0);   // 16
stock.find(p => p.nom === "Gomme");             // l'objet, ou undefined
stock.findIndex(p => p.nom === "Règle");        // -1 : aucun ne correspond
[1, 2, 3, 4].filter(n => n % 2 === 0);          // [2, 4]
```

`map`, `filter` et `reduce` **retournent un nouveau tableau** (ou une valeur) sans modifier l'original. Pour **copier** un tableau d'objets, `stock.map(p => ({ ...p }))` crée une nouvelle copie de chaque objet (copie **superficielle** : un objet imbriqué resterait partagé).

## La console, au-delà de console.log

| Méthode | Rôle |
|---|---|
| `console.error()` | affiche un message signalé comme une erreur |
| `console.table(stock)` | présente un tableau d'objets sous forme de tableau lisible |
| `console.time("inventaire")` / `console.timeEnd("inventaire")` | mesure la durée entre les deux appels (avec **le même libellé**) |
| `console.timeLog("inventaire")` | affiche le temps écoulé **sans arrêter** le chronomètre |
| `console.clear()` | efface la console |

Autour d'une boucle de `prompt()`, `console.timeEnd()` mesure surtout… le temps de réaction de l'utilisateur. Et un `console.log()` montre une valeur à un instant, ce n'est **pas** un test complet : un test fixe des entrées, une action et le résultat attendu.

## Conserver des données dans le navigateur

Une variable JavaScript disparaît quand la page est rechargée ou fermée. Le **Web Storage** permet de garder des données dans le navigateur :

- `localStorage` : conservé **après la fermeture** de l'onglet et du navigateur (en principe, tant que l'utilisateur ne nettoie pas ses données) ;
- `sessionStorage` : limité à **l'onglet**, effacé à sa fermeture.

Les données sont rangées **par origine** (protocole + domaine + port) : toutes les pages de `http://localhost:8080` partagent le même `localStorage`, mais `http://localhost:3000` en a un autre. Ouvre tes pages via un serveur (`http://localhost`) plutôt qu'en `file://`, où le stockage et de nombreuses API se comportent différemment.

```js
localStorage.setItem("quantite", 12);
localStorage.getItem("quantite");      // "12" : tout est stocké en CHAÎNE
localStorage.getItem("cle-inconnue");  // null
localStorage.removeItem("quantite");   // supprime une clé
localStorage.clear();                  // supprime TOUTES les clés de l'origine
```

Avant de convertir une valeur lue, on teste `brut !== null` : sinon `Number(null)` donnerait `0`, une quantité valide en apparence.

> [!attention]
> Tout script de la page peut lire le `localStorage`. Une faille XSS suffit pour le vider ou le copier ailleurs : on n'y stocke **jamais** de mot de passe ni de jeton sensible. Il n'est pas non plus partagé avec le serveur : ce n'est ni une base de données, ni un moyen de sécurité.

### Stocker des objets avec JSON

`localStorage` ne stocke que des chaînes : `setItem("produit", { nom: "Gants" })` enregistre le texte `"[object Object]"`, inutilisable. On **sérialise** en JSON avec `JSON.stringify()`, et on relit avec `JSON.parse()`.

```js
const CLE = "monapp.stock.v1";   // préfixe + version : pas de collision, migration possible

function chargerStock() {
    const brut = localStorage.getItem(CLE);
    try {
        const stock = JSON.parse(brut ?? "[]");          // clé absente → tableau vide
        return Array.isArray(stock) ? stock : null;      // contenu inattendu → null
    } catch {
        return null;                                     // JSON invalide (SyntaxError)
    }
}

function enregistrerStock(stock) {
    try {
        localStorage.setItem(CLE, JSON.stringify(stock));
        return true;
    } catch {
        return false;   // quota dépassé, stockage désactivé…
    }
}
```

- `JSON.parse("invalide")` lance une `SyntaxError` : on l'entoure d'un `try … catch`.
- `Array.isArray()` vérifie que le contenu décodé a bien la forme attendue : la valeur a pu être modifiée par une autre version de l'application, ou à la main.
- La fonction de lecture distingue `[]` (« rien d'enregistré », un état normal) et `null` (« données illisibles », à signaler).
- La fonction d'écriture renvoie `true` ou `false` : l'écriture peut **échouer** (quota d'environ 5 Mo dépassé, navigation privée), et l'interface doit pouvoir le dire.
- Une clé nommée `"monapp.stock.v1"` plutôt que `"stock"` évite les collisions avec d'autres scripts de la même origine, et la version permet de faire évoluer le format.

**JSON** est un format **texte**, indépendant du langage : il accepte objets, tableaux, chaînes (entre guillemets **doubles**), nombres, booléens et `null`, mais ni fonctions, ni `undefined`, ni commentaires.

## Un menu en console

Dans un programme qui lit des choix avec `prompt()`, on compare le choix à `"1"` (une **chaîne**), puisque `prompt()` retourne du texte. Dans une boucle de saisie, `continue` permet d'ignorer une saisie invalide et de reposer la question directement.

```js
while (true) {
    const saisie = prompt("Produit (stop pour finir)");
    if (saisie === null || saisie.trim().toLowerCase() === "stop") {
        break;
    }
    if (saisie.trim() === "") {
        continue;   // saisie vide : on redemande
    }
    // traiter la saisie
}
```

## Les pièges classiques

- Oublier le `return` et utiliser le résultat `undefined`.
- Attendre une valeur par défaut quand l'argument vaut `null`.
- Tester `if (quantite)` alors que `0` est une valeur légitime.
- Stocker un objet sans `JSON.stringify()`, ou relire sans `try … catch`.
- Mettre des données sensibles dans `localStorage`.

> [!retenir]
> - Une fonction s'exécute à l'appel ; paramètres ≠ arguments ; pas de vérification de type ; sans `return`, elle renvoie `undefined`.
> - Les fonctions de calcul retournent un résultat, sans `prompt()` ni `alert()`.
> - `map`, `filter`, `reduce`, `find`, `findIndex` (`-1` si absent) ; `{ ...p }` copie un objet ; `?.` s'arrête sur `null`.
> - `localStorage` (persistant) et `sessionStorage` (onglet) stockent des chaînes, par origine ; `getItem` renvoie `null` si absent.
> - `JSON.stringify()` pour écrire, `JSON.parse()` dans un `try … catch` pour lire, puis vérifier la forme (`Array.isArray`).
> - Jamais de secret dans `localStorage`.
