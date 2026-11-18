---
resume: Faire des choix dans un programme avec if, elseif, switch et match, comparer correctement deux valeurs et combiner des conditions.
---

> [!objectifs]
> - Écrire des conditions avec `if`, `elseif` et `else`, dans le bon ordre.
> - Comparer deux valeurs sans tomber dans les pièges de `==`.
> - Combiner des conditions avec `&&`, `||` et `!`, et profiter du court-circuit.
> - Choisir entre `switch`, `match`, l'opérateur ternaire et `??`.

## if, elseif, else

Une condition exécute un bloc de code seulement si une expression est vraie.

```php
$note = 14;

if ($note >= 16) {
    $mention = 'Très bien';
} elseif ($note >= 14) {
    $mention = 'Bien';
} elseif ($note >= 12) {
    $mention = 'Assez bien';
} elseif ($note >= 10) {
    $mention = 'Passable';
} else {
    $mention = 'Non admis';
}
```

PHP teste les conditions **dans l'ordre** et exécute **seulement le premier bloc** dont la condition est vraie. L'ordre est donc décisif : si l'on commençait par `if ($note >= 10)`, une note de 17 obtiendrait « Passable », car ce premier test serait déjà vrai. Règle pratique : du cas **le plus restrictif** au plus large.

À l'inverse, quand plusieurs règles peuvent s'appliquer **en même temps** (des remises qui se cumulent, plusieurs messages d'erreur à afficher), on écrit une suite de `if` **indépendants** : avec `elseif`, seule la première règle vraie serait appliquée.

```php
$remise = 0;
if ($client['fidele']) {
    $remise += 5;
}
if ($montant >= 100) {
    $remise += 10;   // s'ajoute à la remise fidélité
}
```

## Les opérateurs de comparaison

| Opérateur | Vrai si… |
|---|---|
| `===` | même valeur **et** même type |
| `!==` | valeur ou type différents |
| `==` | même valeur après conversion de type |
| `!=` | différents après conversion |
| `<`, `<=`, `>`, `>=` | inférieur, inférieur ou égal… |
| `<=>` | retourne `-1`, `0` ou `1` (opérateur « vaisseau spatial ») |

### == ou === ?

`==` compare **après conversion** : `"1" == "01"` est vrai, `"10" == "1e1"` aussi (deux chaînes numériques comparées comme des nombres), et `null == false` également. `===` compare la valeur **et** le type, sans aucune conversion : `"1" === 1` est faux.

> [!retenir]
> Utilise `===` et `!==` par défaut. Réserve `==` aux cas où tu veux vraiment une comparaison souple, en sachant pourquoi.

L'opérateur `<=>` retourne `-1` si la valeur de gauche est plus petite, `0` si elles sont égales et `1` si elle est plus grande : `2 <=> 5` vaut `-1`. On s'en sert surtout pour trier (chapitre 04).

## Combiner des conditions

| Opérateur | Signification |
|---|---|
| `&&` | ET : vrai si les deux sont vraies |
| `\|\|` | OU : vrai si au moins une est vraie |
| `!` | NON : inverse la valeur |

```php
// « $age est compris entre 18 et 65 inclus »
if ($age >= 18 && $age <= 65) { /* … */ }
```

Il n'existe pas de raccourci du type `18 <= $age <= 65` : il faut deux comparaisons reliées par `&&`.

### Le court-circuit

PHP évalue de gauche à droite et **s'arrête dès que le résultat est connu**. Avec `&&`, si la partie gauche est fausse, la droite n'est jamais évaluée. On en profite pour protéger un accès :

```php
if (isset($stock['pommes']) && $stock['pommes'] > 3) {
    echo 'Assez de pommes';
}
```

Si la clé `'pommes'` est absente, `isset()` donne `false` et la comparaison n'est pas évaluée : aucun avertissement « Undefined array key ».

> [!attention]
> Les mots-clés `and` et `or` existent aussi, mais avec une priorité **plus faible que `=`**. `$ok = true and false;` affecte `true` à `$ok` (puis évalue `and false` dans le vide). Utilise toujours `&&` et `||`.

## isset, empty et l'opérateur ??

- `isset($x)` est vrai si la variable existe **et** ne vaut pas `null`. Avec plusieurs arguments, `isset($a, $b)` est vrai seulement si **tous** sont définis et non nuls.
- `empty($x)` est vrai si la variable n'existe pas ou si sa valeur est *falsy* (`0`, `""`, `"0"`, `[]`, `null`, `false`). Attention : `empty("0")` est vrai, ce qui surprend quand un utilisateur saisit le chiffre zéro.

L'opérateur `??` (fusion nulle) retourne la valeur de gauche si elle existe et n'est pas `null`, sinon celle de droite. Il ne déclenche aucun avertissement si la variable ou la clé n'existe pas :

```php
$page = $_GET['page'] ?? 1;   // 1 si le paramètre est absent
echo 0 ?? 'x';                // 0 : la valeur existe et n'est pas null
```

Ne le confonds pas avec `?:` (l'« Elvis »), qui teste si la valeur est **vraie** : `0 ?: 'vide'` vaut `'vide'`, car `0` est *falsy*.

## L'opérateur ternaire

`condition ? valeurSiVrai : valeurSiFaux` est un `if/else` compact qui **produit une valeur** :

```php
$libelle = $quantite > 1 ? 'articles' : 'article';
echo "$quantite $libelle";
```

Il doit rester lisible. Enchaîner deux ternaires sans parenthèses (`$a ? 'x' : $b ? 'y' : 'z'`) est même une **erreur fatale** en PHP 8 : utilise des parenthèses, ou mieux, un `match`.

## switch

`switch` compare une valeur à plusieurs cas :

```php
switch ($jour) {
    case 'samedi':
    case 'dimanche':
        $type = 'week-end';
        break;
    default:
        $type = 'semaine';
}
```

Deux particularités : chaque `case` doit se terminer par `break`, sinon l'exécution **continue dans le cas suivant** (c'est voulu ci-dessus pour samedi, mais c'est souvent un oubli) ; et la comparaison utilise `==`, avec ses conversions.

## match (PHP 8)

`match` est une expression qui **retourne une valeur**, avec une comparaison **stricte** (`===`) et sans risque d'oubli de `break` :

```php
$type = match ($jour) {
    'samedi', 'dimanche' => 'week-end',
    'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi' => 'semaine',
};
```

Si aucun bras ne correspond et qu'il n'y a pas de `default`, PHP lance une `UnhandledMatchError` : un cas oublié se voit immédiatement au lieu de passer inaperçu.

Comme la comparaison est stricte, le type compte :

```php
$code = '2';
echo match ($code) {
    1 => 'un',
    2 => 'deux',
    default => 'autre',
};   // affiche « autre » : '2' (chaîne) !== 2 (entier)
```

`match (true)` permet même d'évaluer des conditions dans l'ordre :

```php
$categorie = match (true) {
    $age < 12 => 'enfant',
    $age < 18 => 'adolescent',
    default => 'adulte',
};
```

## La syntaxe alternative dans les gabarits

Dans un fichier qui mélange HTML et PHP, les accolades se perdent facilement. PHP propose une syntaxe plus lisible, avec `:` et un mot-clé de fin :

```php
<?php if ($panier === []): ?>
    <p>Ton panier est vide.</p>
<?php elseif (count($panier) === 1): ?>
    <p>Un article dans ton panier.</p>
<?php else: ?>
    <p><?= count($panier) ?> articles dans ton panier.</p>
<?php endif; ?>
```

Le même principe existe pour les boucles : `foreach (…):` … `endforeach;`, `for (…):` … `endfor;`, `while (…):` … `endwhile;`.

## Les pièges classiques

- Ordonner les `elseif` du plus large au plus restrictif : les cas suivants ne sont jamais atteints.
- Utiliser `==` et se faire surprendre par une conversion ; préférer `===`.
- Écrire `if ($x = 5)` (affectation, toujours vraie) au lieu de `if ($x === 5)`.
- Oublier un `break` dans un `switch`.
- Utiliser `empty()` sur une saisie qui peut légitimement valoir `"0"`.

> [!retenir]
> - `if / elseif / else` exécute le **premier** bloc vrai : va du cas le plus restrictif au plus large.
> - `===` compare valeur **et** type : c'est la comparaison à utiliser par défaut.
> - `&&` s'arrête dès qu'une partie est fausse : `isset($t['x']) && $t['x'] > 3` est sans danger.
> - `??` donne une valeur par défaut si la valeur est absente ou `null` ; `?:` teste si elle est vraie.
> - `match` retourne une valeur, compare avec `===` et lève une `UnhandledMatchError` si aucun cas ne correspond.
> - `switch` compare avec `==` et continue dans le cas suivant sans `break`.
