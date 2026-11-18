---
resume: Découvrir JavaScript et son écosystème, déclarer des variables avec let et const, maîtriser types, conversions, conditions, boucles et tableaux, et valider une saisie.
---

> [!objectifs]
> - Situer JavaScript : son rôle dans une page, ECMAScript, les moteurs et les environnements.
> - Charger un script correctement et déboguer avec la console.
> - Déclarer des variables avec `let` et `const`, connaître les types et les pièges de conversion.
> - Écrire des conditions, des boucles et manipuler des tableaux.
> - Valider une saisie et choisir des jeux d'essai pertinents.

## JavaScript dans une page web

Une page web repose sur trois langages complémentaires : **HTML** décrit le **contenu** et sa structure, **CSS** sa **présentation**, et **JavaScript** son **comportement** (réagir à un clic, vérifier un formulaire, charger des données sans recharger la page). JavaScript s'exécute **dans le navigateur**, sur la machine du visiteur, contrairement à PHP qui s'exécute sur le serveur.

### Un peu d'histoire

- JavaScript a été créé en **1995** par **Brendan Eich**, chez **Netscape**, pour rendre les pages interactives. Son nom est un choix marketing : **Java et JavaScript sont deux langages différents**.
- Le langage est normalisé sous le nom **ECMAScript** (par Ecma International) : JavaScript est l'implémentation la plus connue de cette norme. L'édition **ES2015** (aussi appelée ES6) a profondément modernisé le langage : `let`, `const`, les fonctions fléchées, les classes, les modules, les promesses.
- Un **moteur** JavaScript exécute le code : **V8** (Chrome, Edge, Node.js), **SpiderMonkey** (Firefox), JavaScriptCore (Safari).
- Le même langage s'exécute dans les **navigateurs**, mais aussi **côté serveur** et en ligne de commande avec **Node.js** (ou Deno, Bun).

Les fonctions comme `alert()`, `prompt()` ou `document.querySelector()` n'appartiennent **pas** au langage ECMAScript : elles sont fournies par le **navigateur** (les API Web). Elles n'existent donc pas dans Node.js.

## Charger un script

```html
<script src="script.js" defer></script>
```

On place le code dans un fichier séparé. L'attribut `defer` télécharge le script **en parallèle** de la lecture du HTML, puis l'exécute **une fois le HTML entièrement analysé** (et dans l'ordre des balises) : le script peut alors trouver tous les éléments de la page. Sans `defer`, un script placé dans le `<head>` s'exécute avant que le `<body>` n'existe.

`"use strict";` en tête d'un script active le **mode strict** : certaines erreurs silencieuses deviennent de vraies erreurs (par exemple, affecter une variable jamais déclarée). Les modules et les classes sont automatiquement en mode strict.

## La console, outil de débogage

La **console** des outils de développement (touche F12) affiche les messages du script et les **erreurs**, avec le fichier et la ligne.

```js
console.log("Total :", total);   // afficher une valeur pour comprendre ce qui se passe
console.error("Erreur simulée");  // message signalé comme une erreur (en rouge)
console.table(produits);          // un tableau d'objets présenté en tableau
```

Devant une erreur, on **lit le message** (son type, par exemple `TypeError` ou `ReferenceError`, et son texte), on va à la **ligne indiquée**, et on corrige la cause plutôt que le symptôme. Un script peut aussi s'exécuter **sans erreur** et donner un **mauvais résultat** : c'est une **erreur de logique**, la plus difficile à repérer, qu'on traque avec des `console.log()` et des jeux d'essai.

Les commentaires s'écrivent comme en PHP : `// sur une ligne` et `/* sur plusieurs lignes */`.

## Variables : let et const

```js
const prenom = "Lina";   // ne sera jamais réaffectée
let score = 0;           // sera modifiée
score = score + 10;
```

- `const` déclare une variable qui **ne peut pas être réaffectée** : `prenom = "Aya"` lève une `TypeError` (« Assignment to constant variable »).
- `let` déclare une variable **modifiable**.
- Règle pratique : `const` **par défaut**, `let` seulement quand la valeur doit changer (un compteur, un total, une saisie redemandée).

`const` empêche la **réaffectation**, pas la modification du **contenu** : avec `const produits = ["pain", "lait"]`, `produits.push("pomme")` fonctionne parfaitement. C'est la variable qui est constante, pas le tableau.

### La portée de bloc

Une variable `let` ou `const` n'existe **que dans le bloc** `{ … }` où elle est déclarée :

```js
let score = 10;
if (score >= 10) {
    let message = "Objectif atteint";
}
console.log(message);   // ReferenceError : message n'existe pas ici
```

L'ancien mot-clé `var` a une portée de **fonction**, pas de bloc : une variable `var` déclarée dans un `if` « fuit » dans toute la fonction, et redéclarer `var x` dans un bloc écrase la variable extérieure. C'est une source de bugs : on n'utilise plus `var` dans du nouveau code.

## Les types et leurs pièges

| Type | Exemples | `typeof` |
|---|---|---|
| nombre | `42`, `3.14`, `NaN`, `Infinity` | `"number"` |
| chaîne | `"Lina"`, `'a'`, et les gabarits entre accents graves | `"string"` |
| booléen | `true`, `false` | `"boolean"` |
| absence de valeur | `undefined`, `null` | `"undefined"`, `"object"` (!) |
| tableau | `[1, 2, 3]` | `"object"` |
| objet | `{ nom: "Ada" }` | `"object"` |

- Il n'existe qu'**un seul type de nombre** : entiers et décimaux sont tous des `number` (en virgule flottante). Donc `0.1 + 0.2 === 0.3` est **faux**, comme en PHP.
- `undefined` signifie « **pas encore de valeur** » (variable déclarée sans valeur, propriété absente, fonction sans `return`) ; `null` signifie « **aucune valeur, volontairement** ».
- `typeof null` vaut `"object"` (une erreur historique du langage, conservée pour la compatibilité) et `typeof []` aussi : pour tester un tableau, on utilise `Array.isArray()`.
- `5 / 0` vaut `Infinity` et `0 / 0` vaut `NaN` (*Not a Number*) : pas d'erreur, mais des valeurs spéciales qui se propagent dans les calculs. `17 % 5` vaut `2`, `2 ** 4` vaut `16`.

### Comparer : === et ==

- `=` **affecte** une valeur ; `===` **compare** valeur et type ; `==` compare après conversion.
- `"5" === 5` est **faux**, `"5" == 5` est **vrai**. Comme en PHP, on utilise `===` et `!==`.
- Deux **chaînes** se comparent caractère par caractère : `"10" < "9"` est **vrai**, car le caractère `"1"` vient avant `"9"`. Convertis d'abord en nombres.

### Les template literals

Délimitées par des **accents graves** (AltGr + 7 sur un clavier AZERTY), les chaînes « gabarits » insèrent des expressions avec `${…}` et peuvent tenir sur plusieurs lignes :

```js
const prenom = "Lina";
const age = 18;
console.log(`${prenom} a ${age} ans.`);   // Lina a 18 ans.
```

## Saisies et conversions

`prompt("Votre prénom ?")` affiche une boîte de saisie et retourne une **chaîne**, ou **`null`** si l'utilisateur clique sur Annuler. `confirm("Continuer ?")` retourne `true` ou `false`. `alert()` affiche un message.

Une saisie est **toujours du texte** : si `prompt()` a renvoyé `"2"` puis `"3"`, leur somme `brutA + brutB` vaut `"23"`, car `+` **concatène** dès qu'une chaîne est en jeu. Il faut **convertir** avec `Number()` avant de calculer.

```js
Number("42")       // 42
Number(" 42 ")     // 42 (espaces ignorés)
Number("12abc")    // NaN
Number("")         // 0  (!)
Number("   ")      // 0  (!)
Number(null)       // 0  (!)
```

Les trois derniers cas sont des pièges : une saisie **vide**, faite d'**espaces** ou **annulée** donnerait `0`, un nombre valide en apparence. On les rejette donc **avant** la conversion.

```js
const brut = prompt("Quantité ?");
if (brut === null || brut.trim() === "") {
    console.log("Saisie annulée ou vide");
} else {
    const quantite = Number(brut);
    if (!Number.isInteger(quantite) || quantite <= 0) {
        console.log("Il faut un entier strictement positif");
    }
}
```

Ce test rejette exactement l'annulation, la saisie vide et les espaces, **sans** rejeter `"0"` (qui est ensuite traité par la règle « strictement positif »).

- `Number.isFinite(n)` vérifie qu'on a un **vrai nombre fini**, utilisable dans un calcul (ni `NaN`, ni `Infinity`).
- `Number.isInteger("12")` vaut `false` : la fonction ne convertit pas, `"12"` est une chaîne. À l'inverse, l'ancienne fonction `isNaN("")` vaut `false`, car elle convertit `""` en `0` : préfère `Number.isNaN()`.
- `prixTTC.toFixed(2)` retourne une **chaîne** (`"12.50"`) destinée à l'**affichage** : on ne continue pas de calculer avec.

## Conditions

```js
const note = 14;
if (note >= 16) {
    console.log("Très bien");
} else if (note >= 10) {
    console.log("Admis");   // affiché : la première condition vraie l'emporte
} else {
    console.log("Ajourné");
}
```

Pour tester une saisie convertie, on compare **après** conversion et avec `===` : `Number(saisie) === 10`.

### Vrai, faux et court-circuit

Les valeurs **falsy** (considérées comme fausses) sont : `false`, `0`, `-0`, `""`, `null`, `undefined` et `NaN`. **Tout le reste est vrai**, y compris `"0"` (une chaîne non vide), `" "`, `[]` et `{}`. Attention, c'est une différence avec PHP, où `"0"` est faux.

`&&` et `||` évaluent de gauche à droite et **s'arrêtent dès que le résultat est connu** (court-circuit), en retournant la dernière valeur évaluée. Pour une valeur par défaut, `??` ne remplace que `null` et `undefined`, alors que `||` remplace **toute** valeur falsy :

```js
0 ?? 5   // 0 : la valeur existe
0 || 5   // 5 : 0 est falsy, || passe à la suite
```

### switch

`switch` compare avec l'égalité **stricte** (`===`) et, comme en PHP, **continue dans le `case` suivant** sans `break` :

```js
switch (operation) {
    case "+":
        console.log("Addition");
        break;   // sans ce break, « Soustraction » serait aussi affichée
    case "-":
        console.log("Soustraction");
        break;
    default:
        console.log("Opération inconnue");
}
```

## Boucles

Les boucles fonctionnent comme en PHP (chapitre 03) :

- `while` teste sa condition **avant** chaque tour ; `do … while` **après**, donc son corps s'exécute **au moins une fois** : c'est la boucle idéale pour **redemander une saisie** jusqu'à ce qu'elle soit valide.
- Dans `for (let i = 0; i < 5; i++)`, `i++` s'exécute **après** chaque tour, avant le test suivant.
- `i++` retourne l'ancienne valeur, `++i` la nouvelle : avec `let i = 5; let a = i++; let b = ++i;`, `a` vaut 5 et `b` vaut 7.
- Une condition qui ne devient jamais fausse (un compteur jamais incrémenté) donne une **boucle infinie** : la page se fige.
- `break` sort de la boucle (par exemple quand l'utilisateur tape « stop ») ; `continue` passe au tour suivant.

```js
let resultat = 1;   // élément neutre de la multiplication (0 annulerait tout)
for (let i = 0; i < exposant; i++) {
    resultat *= base;
}
```

## Tableaux

```js
const notes = [12, 15, 18];
console.log(notes[0]);        // 12
console.log(notes.length);    // 3
console.log(notes[3]);        // undefined : il n'y a pas de 4e case

let somme = 0;
for (let i = 0; i < notes.length; i++) {   // < et non <= : le dernier indice est length - 1
    somme += notes[i];
}
const moyenne = notes.length > 0 ? somme / notes.length : null;
```

- Les indices vont de `0` à `length - 1` : `i <= notes.length` lirait une case `undefined` de trop.
- On divise par `notes.length`, pas par `3` écrit en dur : le code reste juste si le nombre de notes change. Et un tableau **vide** donnerait `0 / 0`, soit `NaN` : on traite ce cas à part.
- `for (const produit of produits)` parcourt les **valeurs** ; `const` y est autorisé car une **nouvelle** variable est créée à chaque tour. La boucle `for` classique reste utile quand on a besoin de l'**indice**.

```js
const courses = ["citron"];
courses.push("pain");          // ajoute à la fin : ["citron", "pain"]
courses.unshift("lait");       // ajoute au début : ["lait", "citron", "pain"]
const position = courses.indexOf("pain");   // 2, ou -1 si absent
if (position !== -1) {
    courses.splice(position, 1);   // retire 1 élément à cette position
}
```

> [!attention]
> `indexOf()` retourne `-1` quand l'élément est absent, et `splice(-1, 1)` supprime… le **dernier** élément du tableau. Vérifie toujours `position !== -1` avant `splice()`.

## Tester son programme

Un programme se démontre avec des **jeux d'essai** choisis : des cas **normaux**, les **limites** (0, 1, la valeur maximale, la frontière d'une condition), et les cas **invalides** (saisie vide, annulée, texte, nombre négatif ou décimal). Un seul essai « qui marche » ne prouve rien.

## Les pièges classiques

- Additionner des saisies sans les convertir : `"2" + "3"` donne `"23"`.
- Convertir une saisie vide ou annulée : `Number("")` et `Number(null)` donnent `0`.
- Utiliser `var`, ou `==` au lieu de `===`.
- Comparer des nombres stockés sous forme de chaînes (`"10" < "9"`).
- Oublier un `break` dans un `switch`, ou `splice()` avec un indice `-1`.

> [!retenir]
> - HTML = contenu, CSS = présentation, JavaScript = comportement ; ECMAScript est la norme, V8 et SpiderMonkey des moteurs.
> - `<script src="…" defer>` ; la console (F12) pour lire les erreurs et afficher des valeurs.
> - `const` par défaut, `let` si la valeur change, jamais `var` ; portée de bloc.
> - `===` ; `typeof null` et `typeof []` valent `"object"` ; `"0"` est vrai en JavaScript.
> - Toute saisie est une chaîne : rejette `null` et le vide, puis `Number()`, puis `Number.isInteger()` ou `Number.isFinite()`.
> - `0 ?? 5` vaut `0`, `0 || 5` vaut `5` ; `indexOf()` retourne `-1` si absent.
