---
resume: Découper un programme en fonctions typées et testables, maîtriser la portée des variables, les fonctions anonymes, l'inclusion de fichiers et la récursivité.
---

> [!objectifs]
> - Déclarer des fonctions avec des paramètres et un retour typés.
> - Utiliser les valeurs par défaut, les arguments nommés et les paramètres variadiques.
> - Comprendre la portée des variables, `static`, les références et les closures.
> - Organiser le code en plusieurs fichiers avec `require`.
> - Écrire une fonction récursive correcte et savoir quand l'éviter.

## Déclarer et appeler une fonction

Une fonction regroupe des instructions sous un nom, reçoit des **paramètres** et **retourne** un résultat avec `return`.

```php
<?php
declare(strict_types=1);

function prixTtc(float $prixHt, float $taux = 0.20): float
{
    return round($prixHt * (1 + $taux), 2);
}

echo prixTtc(10.0);          // 12
echo prixTtc(10.0, 0.055);   // 10.55
```

`return` termine immédiatement la fonction. Une fonction peut être appelée **avant** sa déclaration dans le même fichier : PHP enregistre toutes les fonctions déclarées au premier niveau avant d'exécuter le script.

## Typer les paramètres et le retour

Les types documentent le contrat de la fonction et sont **vérifiés** par PHP.

| Écriture | Signification |
|---|---|
| `int`, `float`, `string`, `bool`, `array` | le type indiqué |
| `?int` | un `int` **ou** `null` (type nullable) |
| `int\|string` | l'un ou l'autre (type union, PHP 8) |
| `mixed` | n'importe quelle valeur |
| `: void` | la fonction ne retourne **rien** (un appel donne `null`) |
| `: never` | la fonction ne se termine jamais normalement : elle lance une exception ou appelle `exit` (PHP 8.1) |

Par exemple, une fonction de recherche qui retourne une position ou `null` si rien n'est trouvé se déclare `: ?int`.

Avec `declare(strict_types=1);` en tête du fichier, un mauvais type lance une `TypeError` : appeler `prixTtc("3.99")` échoue, au lieu de convertir silencieusement la chaîne. La seule conversion acceptée est l'élargissement d'un `int` en `float`. Sans le mode strict, PHP aurait converti `"3.99"` en `3.99` sans rien dire, et une donnée mal formée aurait pu passer inaperçue.

## Valeurs par défaut, arguments nommés, variadiques

Un paramètre avec une **valeur par défaut** devient facultatif. Avec `function resumeCourt(string $texte, int $limiteMots = 18)`, l'appel `resumeCourt($texte)` utilise `$limiteMots = 18`.

Les **arguments nommés** (PHP 8) permettent de passer les arguments dans n'importe quel ordre, et de sauter ceux qui ont une valeur par défaut :

```php
function formaterPrix(float $prix, string $devise = '€', int $decimales = 2): string
{
    return number_format($prix, $decimales, ',', ' ') . ' ' . $devise;
}

echo formaterPrix(devise: '$', prix: 3.99);   // 3,99 $
echo formaterPrix(1500, decimales: 0);        // 1 500 €
```

Un paramètre **variadique** `...$nombres` reçoit tous les arguments restants sous forme de **tableau** :

```php
function somme(int ...$nombres): int
{
    return array_sum($nombres);   // somme(1, 2, 3) : $nombres vaut [1, 2, 3]
}
```

## La portée des variables

Une fonction a sa **propre portée** : elle ne voit pas les variables du script qui l'appelle, et ses variables disparaissent à la fin de l'appel.

```php
$taux = 0.2;

function calculer(float $prix): float
{
    return $prix * (1 + $taux);   // Warning: Undefined variable $taux
}
```

La solution propre est de **passer la valeur en paramètre**. (Le mot-clé `global` existe, mais il crée des dépendances cachées : à éviter.)

Une variable déclarée `static` dans une fonction **garde sa valeur entre deux appels** :

```php
function compteur(): int
{
    static $n = 0;
    return ++$n;
}
compteur(); compteur();
echo compteur();   // 3
```

### Passage par valeur ou par référence

Par défaut, une fonction reçoit une **copie** de l'argument : modifier un tableau reçu ne change pas celui de l'appelant. Le `&` devant un paramètre le passe **par référence** : la fonction travaille alors directement sur la variable de l'appelant.

```php
function ajouter(array &$liste, string $element): void
{
    $liste[] = $element;   // modifie le tableau de l'appelant
}
```

C'est ainsi que fonctionnent `sort()` ou `array_push()`. Pour ton propre code, préfère en général **retourner** le nouveau tableau : c'est plus lisible et plus facile à tester.

## Écrire de bonnes fonctions

Une **fonction pure** retourne toujours le même résultat pour les mêmes arguments, et n'a **aucun effet de bord** : elle n'affiche rien, ne modifie pas de variable extérieure, n'écrit ni fichier ni base. Elle est facile à comprendre, à réutiliser et à tester.

- Une fonction de **calcul** ou de **traitement** retourne son résultat avec `return` ; elle ne fait pas d'`echo`. C'est le code appelant qui décide quoi afficher, et où (page HTML, terminal, JSON…).
- Une fonction fait **une seule chose**, et son nom le dit : `calculerMoyenne()`, `estMajeur()`, `formaterDate()`.
- Pour vérifier une fonction, un seul exemple « qui marche » ne prouve rien : teste plusieurs cas, dont les **cas limites** (tableau vide, zéro, valeur négative, chaîne avec accents, valeur à la frontière d'une condition).

```php
// tests rapides avec assert()
assert(estMajeur(18) === true);
assert(estMajeur(17) === false);   // la frontière
assert(moyenne([]) === null);      // le cas vide
```

Pour mesurer la **durée** d'un traitement, on lit l'horloge avant et après : `$debut = microtime(true); … $duree = microtime(true) - $debut;` (en secondes, avec décimales). `hrtime(true)` donne une mesure plus précise, en nanosecondes.

## Fonctions anonymes et fonctions fléchées

Une fonction peut être une **valeur** : rangée dans une variable, passée en argument (un *callback*), retournée par une autre fonction.

```php
$doubler = function (int $x): int {
    return $x * 2;
};
echo $doubler(21);   // 42

$prixTtc = array_map(fn($p) => $p * 1.2, [10, 20]);   // fonction fléchée
```

Une fonction anonyme ne voit pas non plus les variables extérieures : on les importe avec `use`, **par copie au moment de la création**.

```php
$taux = 0.2;
$ttc = function (float $prix) use ($taux): float {
    return $prix * (1 + $taux);
};
```

Une **fonction fléchée** `fn() =>` importe automatiquement les variables dont elle a besoin, elle aussi **par valeur, au moment de sa création** :

```php
$x = 1;
$f = fn() => $x + 1;
$x = 10;
echo $f();   // 2 : $f a capturé $x quand il valait 1
```

Pour capturer la variable elle-même (et voir ses changements), il faut `use (&$x)` dans une fonction anonyme classique.

La syntaxe `strlen(...)` (PHP 8.1) crée une fonction à partir d'une fonction existante : `$f = strlen(...); echo $f('abcd');` affiche `4`.

## Organiser le code en fichiers

```php
require __DIR__ . '/fonctions.php';        // inclut et exécute le fichier
require_once __DIR__ . '/config.php';      // ne l'inclut qu'une seule fois
```

- `require` arrête le script avec une **erreur fatale** si le fichier est introuvable ; `include` émet seulement un avertissement et continue (rarement souhaitable).
- `require_once` ignore un fichier déjà inclus : on évite ainsi de déclarer deux fois la même fonction, ce qui serait une erreur.
- `__DIR__` contient le **dossier du fichier en cours**. L'utiliser rend les chemins indépendants du dossier depuis lequel le script est lancé.

## La récursivité

Une fonction **récursive** s'appelle elle-même pour résoudre une version plus petite du même problème. Elle a besoin de deux ingrédients :

1. un **cas de base**, qui se résout sans appel récursif ;
2. un **appel récursif** sur un problème **plus petit**, qui se rapproche du cas de base.

```php
function factorielle(int $n): int
{
    if ($n <= 1) {
        return 1;                       // cas de base : 0! = 1! = 1
    }
    return $n * factorielle($n - 1);    // appel sur un problème plus petit
}
echo factorielle(5);   // 120
```

Sans cas de base atteignable, la fonction s'appelle à l'infini : chaque appel occupe de la mémoire, et PHP finit par s'arrêter sur une **erreur fatale** (mémoire épuisée, ou « Maximum call stack size reached » selon la configuration). PHP n'optimise pas la récursion terminale : une récursion très profonde coûte toujours une place sur la pile par appel.

La récursivité est naturelle pour les structures **imbriquées** :

```php
function somme(array $t): int
{
    $total = 0;
    foreach ($t as $element) {
        $total += is_array($element) ? somme($element) : $element;
    }
    return $total;
}
echo somme([1, [2, 3], [4, [5]]]);   // 15
```

Pour tester si un mot est un palindrome récursivement, le cas de base est une chaîne de **0 ou 1 caractère** (toujours un palindrome) ; sinon, on compare le premier et le dernier caractère, puis on recommence sur l'intérieur.

### Attention au coût

La version naïve de Fibonacci (`fib($n) = fib($n - 1) + fib($n - 2)`) recalcule sans cesse les mêmes valeurs : le nombre d'appels **explose** (près de 2,7 millions pour `fib(30)`), alors qu'une boucle fait 30 tours. La **mémoïsation** corrige cela : on mémorise chaque résultat calculé, par exemple dans un tableau `static`, pour ne jamais le recalculer.

```php
function fib(int $n): int
{
    static $memo = [];
    if ($n < 2) {
        return $n;
    }
    return $memo[$n] ??= fib($n - 1) + fib($n - 2);
}
```

## Les pièges classiques

- Utiliser dans une fonction une variable du script sans la passer en paramètre.
- Faire un `echo` dans une fonction de calcul au lieu de retourner le résultat.
- Croire qu'une fonction fléchée voit la valeur **actuelle** d'une variable modifiée après sa création.
- Écrire une récursion sans cas de base, ou dont l'appel ne se rapproche pas du cas de base.
- Inclure un fichier avec un chemin relatif sans `__DIR__`.

> [!retenir]
> - Type les paramètres et le retour (`?int`, `int|string`, `void`, `never`) et active `declare(strict_types=1);`.
> - Une fonction ne voit que ses paramètres ; `static` garde une valeur entre deux appels ; `&` passe par référence.
> - `use` et `fn` capturent les variables **par valeur, à la création**.
> - `require` + `__DIR__` pour inclure ; `require_once` pour n'inclure qu'une fois.
> - Récursivité = cas de base + appel sur un problème plus petit ; pense à la mémoïsation.
