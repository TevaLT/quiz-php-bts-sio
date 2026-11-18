---
resume: Créer des listes et des tableaux associatifs, les parcourir, chercher, trier, filtrer et transformer leurs éléments avec les fonctions de PHP.
---

> [!objectifs]
> - Créer et manipuler des tableaux indexés, associatifs et à plusieurs dimensions.
> - Chercher une valeur ou une clé sans tomber dans les pièges de `false` et `null`.
> - Trier un tableau (par valeur, par clé, avec ta propre règle).
> - Transformer un tableau avec `array_map`, `array_filter` et leurs cousines.

## Deux sortes de tableaux

Un tableau PHP associe des **clés** à des **valeurs**. Selon la nature des clés, on parle de :

- **tableau indexé** (une liste) : les clés sont des entiers 0, 1, 2… attribués automatiquement ;
- **tableau associatif** : les clés sont des noms choisis, qui donnent du sens à chaque valeur.

```php
$couleurs = ['rouge', 'vert', 'bleu'];   // clés 0, 1, 2
echo $couleurs[0];                       // rouge

$livre = [
    'titre' => 'Fondation',
    'auteur' => 'Isaac Asimov',
    'annee' => 1951,
];
echo $livre['auteur'];                   // Isaac Asimov
```

Les deux se combinent : une **liste d'enregistrements** (une liste de tableaux associatifs) est la forme la plus courante des données en PHP, par exemple les lignes lues dans une base de données.

```php
$livres = [
    ['titre' => 'Fondation', 'annee' => 1951],
    ['titre' => 'Dune', 'annee' => 1965],
];
echo $livres[1]['titre'];   // Dune
```

## Ajouter, modifier, supprimer

```php
$panier = ['pain'];
$panier[] = 'lait';           // ajoute à la fin, avec la clé suivante (1)
$panier[0] = 'baguette';      // modifie
unset($panier[0]);            // supprime la clé 0 : les autres clés ne bougent pas
echo count($panier);          // 1
```

`$t[] = …` utilise comme clé **le plus grand entier déjà utilisé + 1** : avec `$t = [3 => 'a']`, la valeur ajoutée reçoit la clé `4`. Et comme `unset()` ne renumérote pas, une liste peut se retrouver « trouée » (clés 0, 2, 3) : `array_values()` la renumérote de 0 à n-1.

D'autres fonctions ajoutent ou retirent aux extrémités : `array_push($t, $v)` et `array_pop($t)` à la fin, `array_unshift($t, $v)` et `array_shift($t)` au début.

### Les clés sont converties

Une clé ne peut être qu'un `int` ou une `string`, et PHP convertit les autres types : `'1'` (chaîne contenant un entier) devient l'entier `1`, `true` devient `1`, `1.7` devient `1`. Donc `[1 => 'a', '1' => 'b', true => 'c']` ne contient qu'**un seul** élément, `'c'` : les trois clés sont la même. En revanche `'08'` reste une chaîne (ce n'est pas l'écriture canonique d'un entier).

### Compter

`count($t)` donne le nombre d'éléments **du premier niveau** : `count([1, [2, 3]])` vaut `2`, car le second élément est un tableau, compté comme un seul élément.

## Déstructurer

On peut ranger les éléments d'un tableau dans des variables en une instruction :

```php
[$latitude, $longitude] = [48.85, 2.35];
['titre' => $titre, 'annee' => $annee] = $livre;

[$a, $b] = [$b, $a];   // échange les valeurs de $a et $b, sans variable temporaire
```

L'opérateur de décomposition `...` déplie un tableau dans un autre : avec `$a = [1, 2]` et `$b = [3]`, `[...$a, ...$b]` donne `[1, 2, 3]`.

## Chercher dans un tableau

| Besoin | Fonction | Retour |
|---|---|---|
| La valeur est-elle présente ? | `in_array($v, $t, true)` | `true` / `false` |
| À quelle clé se trouve la valeur ? | `array_search($v, $t, true)` | la clé, ou `false` |
| La clé existe-t-elle ? | `array_key_exists($k, $t)` | `true` / `false` |
| La clé existe-t-elle avec une valeur non nulle ? | `isset($t[$k])` | `true` / `false` |

Le troisième argument `true` de `in_array()` et `array_search()` active la **comparaison stricte** (`===`). Sans lui, la comparaison est souple : `in_array('1e1', ['10'])` est vrai, parce que les deux chaînes numériques valent 10. Mets `true` par défaut.

> [!attention]
> `array_search()` retourne la **clé** trouvée, qui peut être `0`. Or `0` est *falsy* : `if (array_search('a', ['a', 'b']))` est faux alors que la valeur est bien là, en position 0. Teste toujours `!== false`.

`isset()` et `array_key_exists()` diffèrent sur un point : avec `$film = ['resume' => null]`, `array_key_exists('resume', $film)` est **vrai** (la clé existe) mais `isset($film['resume'])` est **faux** (la valeur est `null`).

## Trier

Les fonctions de tri **modifient le tableau sur place** et retournent seulement `true` : écrire `$t = sort($t)` remplace ton tableau par `true` !

| Fonction | Trie selon | Garde les clés ? |
|---|---|---|
| `sort` / `rsort` | les valeurs (croissant / décroissant) | Non, renumérote |
| `asort` / `arsort` | les valeurs | Oui |
| `ksort` / `krsort` | les clés | Oui |
| `usort` | ta fonction de comparaison | Non |
| `uasort` / `uksort` | ta fonction (valeurs / clés) | Oui |

Pour un tableau associatif comme des notes par élève, `arsort()` trie par valeurs décroissantes **en gardant le lien** entre chaque prénom et sa note ; `rsort()` perdrait les prénoms.

Pour trier selon ta propre règle, `usort()` reçoit une fonction qui compare deux éléments et retourne un nombre négatif, nul ou positif. L'opérateur `<=>` fait exactement cela :

```php
usort($livres, fn($a, $b) => $a['annee'] <=> $b['annee']);     // du plus ancien au plus récent
usort($livres, fn($a, $b) => $b['annee'] <=> $a['annee']);     // ordre inverse
```

Pour obtenir une version triée **sans modifier l'original**, trie une copie : `$tries = $livres; usort($tries, …);` (un tableau est copié lors d'une affectation).

## Transformer : map, filter, reduce

Ces fonctions **ne modifient pas** le tableau d'origine : elles en retournent un nouveau.

```php
$prix = [10, 25, 40];

$ttc = array_map(fn($p) => $p * 1.2, $prix);          // [12, 30, 48]
$chers = array_filter($prix, fn($p) => $p > 20);      // [1 => 25, 2 => 40]
$total = array_reduce($prix, fn($somme, $p) => $somme + $p, 0);   // 75
```

- `array_map($fonction, $t)` applique une fonction à **chaque** élément.
- `array_filter($t, $fonction)` garde les éléments pour lesquels la fonction retourne vrai. Appelé **sans fonction**, il retire toutes les valeurs *falsy* (`0`, `''`, `null`, `false`, `[]`, `'0'`).
- `array_reduce($t, $fonction, $initial)` combine tous les éléments en une seule valeur.

> [!attention]
> `array_filter()` **conserve les clés** : le résultat ci-dessus a les clés 1 et 2, pas 0 et 1. Si tu as besoin d'une vraie liste (pour la convertir en JSON, ou accéder à `[0]`), ajoute `array_values()`. Même chose pour `array_unique()`, qui garde la clé de la première occurrence.

D'autres fonctions indispensables :

```php
array_sum([3, 4]);                          // 7
array_column($livres, 'titre');             // ['Fondation', 'Dune'] : une colonne d'une liste d'enregistrements
array_slice($livres, 0, 3);                 // les 3 premiers éléments (le tableau d'origine est intact)
array_unique(['a', 'b', 'a']);              // [0 => 'a', 1 => 'b']
array_reverse([1, 2, 3]);                   // [3, 2, 1]
array_keys($livre);                         // ['titre', 'auteur', 'annee']
array_count_values(['a', 'b', 'a']);        // ['a' => 2, 'b' => 1]
```

Par exemple, le total d'un panier s'écrit en une ligne : `array_sum(array_column($lignes, 'prix'))`.

### Sur place ou nouveau tableau ?

Retiens la règle : les fonctions qui **réorganisent** le tableau lui-même (les tris, `shuffle`, `array_push`, `array_pop`, `array_shift`, `array_unshift`, `array_splice`) le reçoivent par référence et le **modifient**. Les autres (`array_map`, `array_filter`, `array_slice`, `array_merge`, `array_reverse`…) **retournent un nouveau tableau** qu'il faut récupérer.

## Fusionner deux tableaux

`array_merge($a, $b)` et `$a + $b` ne font pas la même chose :

```php
$defaut = ['langue' => 'fr', 'theme' => 'clair'];
$choix = ['theme' => 'sombre'];

array_merge($defaut, $choix);   // ['langue' => 'fr', 'theme' => 'sombre'] : la droite gagne
$choix + $defaut;               // ['theme' => 'sombre', 'langue' => 'fr'] : la gauche gagne
```

- Avec des **clés texte** communes, `array_merge()` garde la valeur du **second** tableau, alors que `+` garde celle du **premier** et n'ajoute que les clés manquantes.
- Avec des **clés numériques**, `array_merge()` renumérote et met tout bout à bout ; `+` ignore les éléments dont la clé existe déjà.

## Compter des occurrences

Un tableau associatif sert souvent de compteur, la clé étant l'élément compté :

```php
$frequences = [];
foreach ($mots as $mot) {
    $frequences[$mot] = ($frequences[$mot] ?? 0) + 1;
}
arsort($frequences);   // les mots les plus fréquents d'abord
```

Le `?? 0` évite l'avertissement « Undefined array key » au premier passage de chaque mot. (Pour ce cas simple, `array_count_values($mots)` fait le même travail.)

## Les pièges classiques

- Écrire `$t = sort($t);` : le tableau est remplacé par `true`.
- Tester `if (array_search(…))` au lieu de `!== false`.
- Oublier `true` dans `in_array()` et subir une comparaison souple.
- Oublier `array_values()` après `array_filter()` ou `array_unique()`.
- Utiliser `isset()` pour tester une clé dont la valeur peut être `null`.

> [!retenir]
> - Liste : clés 0, 1, 2… ; tableau associatif : clés nommées. `$t[] = $v` ajoute à la fin.
> - `in_array($v, $t, true)` et `array_search(…) !== false` : comparaison stricte et test explicite.
> - Les tris modifient le tableau et retournent `true` ; `asort`/`arsort` gardent les clés, `usort` + `<=>` trie selon ta règle.
> - `array_map`, `array_filter`, `array_slice`… retournent un **nouveau** tableau ; `array_filter` garde les clés.
> - `array_merge` : la droite gagne ; `+` : la gauche gagne.
