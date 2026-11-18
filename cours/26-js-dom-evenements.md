---
resume: Sélectionner et modifier les éléments d'une page avec le DOM, réagir aux actions de l'utilisateur avec les événements, et lire un formulaire sans faille ni piège de conversion.
---

> [!objectifs]
> - Comprendre ce qu'est le DOM et quand il est disponible.
> - Sélectionner des éléments et traiter le cas où ils sont absents.
> - Modifier le texte, les classes et les attributs sans risque d'injection.
> - Réagir aux événements : clic, saisie, envoi de formulaire, propagation et délégation.

## Le DOM

Quand le navigateur lit le HTML, il construit en mémoire un **arbre d'objets** : le **DOM** (*Document Object Model*). Chaque balise devient un **nœud** qu'un script peut lire et modifier ; la page affichée suit ces modifications immédiatement.

- Le **code source** HTML est le texte reçu du serveur ; le **DOM** est la structure **vivante** en mémoire. Après une modification par JavaScript, les deux diffèrent (l'inspecteur montre le DOM, « Afficher la source » le HTML d'origine).
- Le DOM ne fait **pas** partie du langage JavaScript : c'est une **API du navigateur**, accessible par l'objet global `document` (`window.document === document`). Il n'existe pas dans Node.js.
- Les modifications du DOM ne sont **pas enregistrées** : après `element.remove()`, recharger la page fait réapparaître l'élément.

### Attendre que le DOM soit prêt

Un script placé dans le `<head>` sans `defer` s'exécute **avant** que le `<body>` soit analysé : `document.querySelector("#titre")` renvoie alors `null`. Deux solutions : l'attribut **`defer`** (le script s'exécute après l'analyse du HTML ; c'est aussi le cas des scripts `type="module"`), ou attendre l'événement **`DOMContentLoaded`**, qui signale que le HTML est entièrement analysé et le DOM construit.

## Sélectionner des éléments

```js
const titre = document.getElementById("titre");          // par id, ou null
const bouton = document.querySelector(".ligne .forme");   // le PREMIER qui correspond, ou null
const infos = document.querySelectorAll(".info");         // TOUS, dans une NodeList (éventuellement vide)
const ligne2 = document.querySelector('[data-row="2"]');  // par attribut
```

- `querySelector()` et `querySelectorAll()` acceptent n'importe quel **sélecteur CSS** : `.ligne .forme` désigne les éléments de classe `forme` **à l'intérieur** d'un élément de classe `ligne`. Un sélecteur invalide lève une erreur (`SyntaxError`).
- Un `id` est **unique** dans la page ; une `class` peut être portée par plusieurs éléments (et un élément peut avoir plusieurs classes).
- Quand rien ne correspond, `getElementById()` et `querySelector()` renvoient **`null`**, et `querySelectorAll()` une liste **vide** (de longueur 0).

> [!attention]
> L'erreur « **Cannot set properties of null** » sur `element.textContent = "OK"` signifie que la **sélection** a échoué : `element` vaut `null` (mauvais sélecteur, faute de frappe dans l'id, ou script exécuté avant que l'élément existe). Le problème n'est pas l'affectation, mais la ligne qui a sélectionné.

### Collections vivantes ou figées

`getElementsByClassName()` renvoie une **HTMLCollection vivante** : elle se met à jour toute seule quand les classes changent. Si on retire la classe `active` à chaque élément de `getElementsByClassName("active")`, la collection **rétrécit pendant le parcours** et un élément sur deux est sauté. `querySelectorAll()` renvoie au contraire une **NodeList figée**, qui garde la liste d'origine : c'est le choix le plus prévisible. Pour parcourir une collection vivante sans surprise, on la copie d'abord avec `Array.from(actifs)`.

Une HTMLCollection n'est pas un tableau : `getElementsByClassName("x").map(f)` échoue, car elle n'a pas de méthode `map`. `Array.from()` la convertit ; une NodeList a `forEach()`, mais pas `map()` non plus.

## Modifier la page

### Le texte : textContent, pas innerHTML

```js
titre.textContent = "<b>Important</b>";   // affiche littéralement <b>Important</b>
zone.innerHTML = saisieUtilisateur;       // DANGEREUX : le HTML est interprété
```

`textContent` insère du **texte** : les balises éventuelles sont affichées telles quelles, jamais interprétées. `innerHTML` interprète le HTML : avec une saisie de l'utilisateur ou une donnée externe, c'est une porte ouverte à l'**injection de script** (XSS, chapitre 15). Pour du contenu dynamique, on utilise `textContent`, ou on construit les éléments un par un.

```js
const li = document.createElement("li");   // créé en mémoire : PAS encore visible
li.textContent = saisie;                   // "<b>Tomates</b>" s'affichera tel quel
liste.append(li);                          // inséré dans le DOM : maintenant visible
```

### Les classes : classList

L'état visuel d'un élément se pilote par ses **classes** : le JavaScript ajoute ou retire une classe, et le **CSS** décide de l'apparence. On ne modifie pas les styles un par un depuis le script.

```js
zone.classList.add("active");
zone.classList.remove("active");
const estActif = zone.classList.toggle("active");   // inverse, et renvoie le nouvel état
forme.classList.toggle("active", false);            // force l'état : ici, retire la classe
zone.classList.contains("active");                  // true ou false
```

`classList` touche **une seule** classe sans effacer les autres, alors qu'affecter `className` remplace **toute** la liste des classes de l'élément.

### Les attributs et les données

```js
lien.getAttribute("title");     // la valeur, ou null si l'attribut n'existe pas
lien.setAttribute("title", "Ouvrir la fiche");
forme.closest(".ligne");        // l'ancêtre le plus proche (ou l'élément lui-même) qui correspond, ou null
bouton.dataset.row;             // pour <button data-row="2"> : "2", une CHAÎNE
```

Les attributs `data-*` attachent une donnée à un élément (un identifiant, une ligne), lue avec `element.dataset` : c'est plus fiable que des variables globales qui doivent rester synchronisées avec la page. Leurs valeurs sont toujours des **chaînes**.

Pour un bouton à bascule, on aligne l'attribut **`aria-pressed`** sur l'état visuel : les technologies d'assistance (lecteurs d'écran) annoncent ainsi « bouton enfoncé », ce que la seule couleur ne dit pas.

```js
const actif = bouton.classList.toggle("active");
bouton.setAttribute("aria-pressed", String(actif));
```

## Les événements

Un **événement** signale une action : un clic, une touche, une saisie, l'envoi d'un formulaire. On l'écoute avec `addEventListener()` :

```js
function afficherBonjour(event) {
    console.log("Bonjour !", event.target);
}

bouton.addEventListener("click", afficherBonjour);      // on passe LA FONCTION
bouton.addEventListener("click", afficherBonjour());    // ERREUR : appelle la fonction tout de suite
```

- On passe la **référence** de la fonction, sans parenthèses. Avec des parenthèses, la fonction est **exécutée immédiatement**, et c'est son résultat (souvent `undefined`) qui est donné comme écouteur.
- `addEventListener()` permet **plusieurs** écouteurs pour un même événement, et se retire avec `removeEventListener()` ; la propriété `onclick` n'en garde qu'**un seul** (une seconde affectation écrase la première).
- Une **fonction nommée** est préférable quand on veut la retirer plus tard ou la réutiliser ; une fonction fléchée convient aux petits gestionnaires.
- C'est le **navigateur** qui appelle le gestionnaire et lui transmet l'objet **`event`**.

### target et currentTarget

`event.target` est l'élément **réellement cliqué** (par exemple une icône à l'intérieur du bouton) ; `event.currentTarget` est l'élément **qui porte l'écouteur**.

### La propagation

Un événement **remonte** (*bubbling*) de l'élément cliqué vers ses ancêtres. Si un bouton placé dans une `div` porte un écouteur `click` et que la `div` en porte un aussi, un clic sur le bouton déclenche **d'abord** l'écouteur du bouton, **puis** celui de la `div`.

- `event.stopPropagation()` arrête cette remontée.
- `event.preventDefault()` annule l'**action par défaut** du navigateur (suivre un lien, envoyer un formulaire), sans arrêter la propagation. Ce sont deux choses différentes.

### La délégation d'événements

Grâce à la remontée, on peut poser **un seul écouteur** sur un conteneur pour gérer tous ses éléments, **y compris ceux ajoutés plus tard** :

```js
liste.addEventListener("click", (event) => {
    const bouton = event.target.closest("button[data-id]");
    if (bouton === null) {
        return;   // clic ailleurs dans la liste
    }
    supprimerProduit(bouton.dataset.id);
});
```

## Les formulaires

```html
<form id="calcul">
    <label for="a">Premier nombre</label>
    <input id="a" name="a" type="number" step="any" required>
    <label for="b">Second nombre</label>
    <input id="b" name="b" type="number" step="any" required>
    <button type="submit">Calculer</button>
    <output id="resultat" aria-live="polite"></output>
</form>
```

- L'attribut `for` d'un `<label>` le relie au champ de même `id` : cliquer sur le libellé active le champ, et les lecteurs d'écran l'annoncent. L'attribut `name` sert à identifier la valeur lors de l'envoi du formulaire.
- `step="any"` autorise les décimaux dans un champ `number` (par défaut, seuls les entiers sont valides).
- `aria-live="polite"` fait **annoncer** par les lecteurs d'écran le nouveau contenu du résultat, sans interrompre l'utilisateur.
- Un `<button>` **sans attribut `type`** dans un formulaire est un bouton **`submit`** : il envoie le formulaire (et recharge la page) ! Pour un bouton qui ne doit rien envoyer, écris `type="button"`.
- Un vrai `<button>` est utilisable au **clavier** (Tab, Entrée, Espace) et annoncé comme bouton ; une `div` avec un écouteur `click` ne l'est pas.

### Écouter submit, et lire les valeurs

On écoute l'événement **`submit`** du **formulaire**, pas le `click` du bouton : `submit` se déclenche aussi quand l'utilisateur appuie sur **Entrée** dans un champ.

```js
const formulaire = document.querySelector("#calcul");
formulaire.addEventListener("submit", (event) => {
    event.preventDefault();   // on reste sur la page : le calcul se fait en JavaScript
    const a = lireNombre(formulaire.elements.a);
    const b = lireNombre(formulaire.elements.b);
    const sortie = document.querySelector("#resultat");
    sortie.textContent = a === null || b === null ? "Saisie invalide" : String(a + b);
});

function lireNombre(champ) {
    const texte = champ.value.trim();
    if (texte === "") {
        return null;            // vide : Number("") donnerait 0
    }
    const nombre = Number(texte);
    return Number.isFinite(nombre) ? nombre : null;
}
```

- `champ.value` est **toujours une chaîne**, même pour un `<input type="number">`. Sans conversion, `a + b` **concatène** : si un calcul donne `"23"` au lieu de `5` alors que les éléments sont bien sélectionnés, le problème est au niveau de la **conversion**.
- On rejette la saisie vide **avant** `Number()`, qui la convertirait en `0`.
- La fonction renvoie `null` pour « invalide », et on teste `valeur === null` plutôt que `!valeur` : `0` est une saisie valide mais falsy.
- Une fonction de lecture d'**entier** utiliserait `Number.isInteger()` et renverrait `null` pour `"2.5"`.

L'événement **`input`** se déclenche **à chaque modification** de la valeur pendant la saisie (pour un aperçu en direct) ; `change` seulement quand le champ perd le focus après modification.

### Diviser par zéro

Une calculatrice doit traiter `calculer(8, 0, "/")` : en JavaScript, `8 / 0` vaut `Infinity`, pas une erreur. C'est donc au code de **détecter** le cas et d'afficher un message clair.

## Les pièges classiques

- Sélectionner avant que le DOM existe (script sans `defer` dans le `<head>`).
- Utiliser `innerHTML` avec une donnée externe.
- Écrire `addEventListener("click", maFonction())` avec des parenthèses.
- Oublier `type="button"` sur un bouton dans un formulaire, ou écouter `click` au lieu de `submit`.
- Additionner des `value` sans conversion.

> [!retenir]
> - Le DOM est l'arbre vivant construit à partir du HTML ; c'est une API du navigateur (`document`).
> - `querySelector` → élément ou `null` ; `querySelectorAll` → NodeList figée ; `getElementsByClassName` → collection vivante.
> - `textContent` pour le texte (jamais `innerHTML` avec une saisie) ; `classList` pour l'état ; `dataset` pour les données.
> - `addEventListener("click", fonction)` sans parenthèses ; `target` = élément cliqué, `currentTarget` = porteur de l'écouteur.
> - La propagation remonte de l'enfant vers les parents ; `preventDefault()` annule l'action par défaut ; la délégation utilise un seul écouteur.
> - Formulaire : écouter `submit`, `preventDefault()`, `value` est une chaîne à valider puis convertir.
