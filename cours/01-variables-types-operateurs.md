---
resume: Ranger des valeurs dans des variables, connaître les types de PHP, calculer avec les opérateurs et éviter les pièges des conversions automatiques.
---

> [!objectifs]
> - Déclarer des variables et des constantes, et choisir le bon type pour chaque donnée.
> - Utiliser les opérateurs arithmétiques, d'affectation et d'incrémentation.
> - Comprendre les conversions de type automatiques et savoir les contrôler.
> - Éviter les pièges classiques : nombres décimaux, divisions, chaînes numériques.

## Variables et affectation

Une variable est une **étiquette posée sur une valeur**. Elle commence par `$`, et reçoit sa valeur avec l'opérateur d'affectation `=`. Pas besoin de déclarer son type : PHP le déduit de la valeur.

```php
$titre = 'Dune';          // une chaîne de caractères
$annee = 1965;            // un entier
$prix = 9.90;             // un nombre décimal
$disponible = true;       // un booléen
$resume = null;           // « pas de valeur »
```

Les noms sont **sensibles à la casse** (`$titre` et `$Titre` sont deux variables) et s'écrivent par convention en camelCase : `$nombreFilms`, `$dureeMinutes`. Choisis des noms qui disent ce que contient la variable : `$d` ne dit rien, `$dureeMinutes` dit tout, y compris l'unité.

## Les types de PHP

| Type | Exemples | Remarque |
|---|---|---|
| `int` | `42`, `-7`, `0` | Entier, limité à `PHP_INT_MAX` |
| `float` | `3.14`, `8.5`, `1e3` | Nombre décimal (virgule flottante), écrit avec un **point** |
| `string` | `'Bonjour'`, `"Ada"` | Suite d'octets (de caractères) |
| `bool` | `true`, `false` | Vrai ou faux |
| `null` | `null` | Absence de valeur |
| `array` | `[1, 2, 3]` | Tableau (chapitre 04) |
| `object` | `new DateTime()` | Objet (chapitre 08) |

`var_dump()` affiche le type exact d'une valeur, et `get_debug_type()` le retourne sous forme de texte :

```php
var_dump(8.5);               // float(8.5)
var_dump("1979");            // string(4) "1979"
echo get_debug_type(3.0);    // float
echo get_debug_type(1979);   // int
```

### Choisir le bon type

Une donnée doit être stockée dans le type qui correspond à **ce qu'on va en faire**. Une année sert à calculer (une ancienneté, un tri) : c'est un `int` (`1979`), pas la chaîne `"1979"`. Un prix sert à calculer un total : c'est un nombre (`3.99`), jamais un texte mis en forme comme `"3,99 €"`. La mise en forme (virgule, symbole €) se fait **au moment de l'affichage** :

```php
$prix = 1234.5;
echo number_format($prix, 2, ',', ' ') . ' €';   // 1 234,50 €
```

`number_format()` prend le nombre de décimales, le séparateur décimal puis le séparateur des milliers.

## Les opérateurs arithmétiques

| Opérateur | Rôle | Exemple | Résultat |
|---|---|---|---|
| `+` `-` `*` | Addition, soustraction, multiplication | `7 * 3` | `21` |
| `/` | Division | `7 / 2` | `3.5` |
| `%` | Reste de la division entière (modulo) | `7 % 3` | `1` |
| `**` | Puissance | `2 ** 10` | `1024` |

Quelques précisions importantes :

- `/` retourne un `int` si la division tombe juste (`10 / 2` donne `int(5)`), sinon un `float` (`7 / 2` donne `3.5`).
- Pour une **division entière**, utilise `intdiv(7, 2)` qui donne `3`.
- Le résultat de `%` prend le **signe du premier nombre** : `-7 % 3` vaut `-1`.
- Diviser par zéro (`/`, `%` ou `intdiv()`) lance une erreur `DivisionByZeroError` qui arrête le script si elle n'est pas gérée (chapitre 07).

Le duo `intdiv()` / `%` est très utile pour découper une quantité :

```php
$dureeMinutes = 117;
$heures = intdiv($dureeMinutes, 60);   // 1
$minutes = $dureeMinutes % 60;         // 57
echo "{$heures} h {$minutes} min";     // 1 h 57 min
```

> [!astuce]
> `% 2` est le test de parité : `$n % 2 === 0` est vrai pour un nombre pair. Et `$i % 3 === 0` permet de faire quelque chose « une fois sur trois » dans une boucle.

## Affectation combinée et incrémentation

```php
$total = 10;
$total += 5;    // $total = $total + 5  → 15
$total *= 2;    // → 30
$texte = 'Bon';
$texte .= 'jour';   // concaténation : 'Bonjour'

$nombreFilms = 0;
$nombreFilms++;     // ajoute 1 → 1
$nombreFilms--;     // retire 1 → 0
```

La position du `++` compte quand on utilise la valeur dans la même instruction : `$i++` (post-incrémentation) **retourne l'ancienne valeur** puis ajoute 1 ; `++$i` (pré-incrémentation) ajoute 1 puis retourne la nouvelle valeur.

```php
$i = 5;
$a = $i++;   // $a reçoit 5, puis $i passe à 6
$b = ++$i;   // $i passe à 7, puis $b reçoit 7
```

L'opérateur `??=` affecte une valeur **seulement si la variable (ou la clé) est absente ou vaut `null`** : pratique pour donner une valeur par défaut.

```php
$options['langue'] ??= 'fr';   // ne remplace pas une langue déjà choisie
```

## Les constantes

Une constante contient une valeur **qui ne change pas** pendant l'exécution. Elle s'écrit sans `$`, en majuscules par convention.

```php
const TVA = 0.20;
const NOMBRE_MAX_ESSAIS = 3;

echo 100 * (1 + TVA);   // 120
```

Toute tentative de la modifier est une erreur. PHP fournit aussi des constantes prédéfinies : `PHP_VERSION`, `PHP_INT_MAX` (le plus grand entier), `PHP_EOL` (le saut de ligne du système)…

## Conversions de type

PHP convertit automatiquement les valeurs quand une opération l'exige : c'est le **transtypage implicite** (type juggling). Pratique, mais source de surprises.

```php
echo "5" + "3";      // 8 : deux chaînes numériques additionnées comme des nombres
echo "5" . "3";      // 53 : concaténation
echo "12abc" + 1;    // 13, avec un Warning « A non-numeric value encountered »
echo "abc" + 1;      // TypeError : une chaîne non numérique ne s'additionne pas
```

On peut aussi convertir **explicitement** avec un *cast* :

```php
(int) "12abc"    // 12 : PHP garde le nombre du début
(int) "abc"      // 0
(int) 3.99       // 3 : la partie décimale est coupée, pas arrondie
(float) "1e3"    // 1000.0
(bool) "0"       // false
(string) true    // "1"
```

Pour savoir si une chaîne représente un nombre avant de la convertir, utilise `is_numeric()` : il accepte `"12"`, `"-3.5"`, `"1e3"` ou `" 12"`, mais refuse `"12abc"`, `"0x1A"` et la chaîne vide.

### Vrai ou faux ?

Dans une condition, toute valeur est convertie en booléen. Sont considérés comme **faux** (*falsy*) : `false`, `0`, `0.0`, `""`, `"0"`, `[]` et `null`. **Tout le reste est vrai** (*truthy*), y compris `"0.0"`, `" "` (un espace), `"false"` et `-1`.

> [!attention]
> `"0"` est faux mais `"0.0"` est vrai. Ne compte pas sur ces conversions pour tester une saisie : compare explicitement (`$saisie === ''`, `$nombre === 0`).

### Le mode strict : declare(strict_types=1)

Placé **en première instruction** d'un fichier, `declare(strict_types=1);` interdit les conversions automatiques lors des appels de fonctions typées faits depuis ce fichier. Passer `"3.99"` à un paramètre `float` lance alors une `TypeError` au lieu de convertir silencieusement : l'erreur apparaît tout de suite, à l'endroit fautif. C'est une bonne pratique dans tout nouveau code (chapitre 06).

## Les nombres décimaux sont approximatifs

Les `float` sont stockés en binaire, et beaucoup de décimales (comme 0,1) ne tombent pas juste en binaire :

```php
var_dump(0.1 + 0.2);            // float(0.30000000000000004)
var_dump(0.1 + 0.2 === 0.3);    // bool(false)
```

Conséquences pratiques :

- Ne compare jamais deux `float` avec `===` : vérifie plutôt que leur écart est minuscule, `abs($a - $b) < 0.00001`.
- Pour de l'argent, calcule en **centimes** avec des entiers (`399` plutôt que `3.99`), et divise par 100 seulement pour l'affichage.
- Pour arrondir, `round(3.14159, 2)` donne `3.14`. `round()` arrondit la moitié **en s'éloignant de zéro** : `round(2.5)` vaut `3` et `round(-2.5)` vaut `-3`. `floor()` arrondit vers le bas, `ceil()` vers le haut.

Les entiers ont aussi une limite : au-delà de `PHP_INT_MAX` (environ 9,2 × 10¹⁸ sur un système 64 bits), le résultat devient un `float`, avec une perte de précision.

```php
var_dump(PHP_INT_MAX + 1);   // float(9.223372036854776E+18)
```

## Les pièges classiques

- Écrire un décimal avec une virgule : `3,99` n'est pas un nombre en PHP. C'est `3.99`.
- Stocker des nombres sous forme de texte mis en forme, puis essayer de calculer avec.
- Oublier que `(int)` tronque : `(int) 2.99` vaut `2`. Pour arrondir, c'est `round()`.
- Comparer des `float` avec `===`, ou calculer des montants en euros avec des `float`.
- Confondre `$i++` et `++$i` quand la valeur est utilisée dans la même instruction.

> [!retenir]
> - Une variable commence par `$` ; son type vient de sa valeur : `int`, `float`, `string`, `bool`, `null`, `array`, `object`.
> - Choisis le type selon l'usage : un prix ou une année sont des nombres ; la mise en forme se fait à l'affichage.
> - `/` peut donner un `float`, `intdiv()` donne une division entière, `%` le reste, `**` la puissance.
> - Les conversions automatiques existent mais piègent : `"5" + "3"` vaut `8`, `"abc" + 1` lance une `TypeError`.
> - Les `float` sont approximatifs : pas de `===` entre eux, et l'argent se compte en centimes.
> - `declare(strict_types=1);` en tête de fichier rend les erreurs de type visibles immédiatement.
