---
resume: Écrire, assembler, découper, chercher et mettre en forme du texte, en tenant compte des accents (UTF-8) et des pièges des fonctions de recherche.
---

> [!objectifs]
> - Choisir entre guillemets simples, guillemets doubles, heredoc et nowdoc.
> - Comprendre la différence entre octets et caractères, et utiliser les fonctions `mb_*`.
> - Chercher, extraire, remplacer et découper du texte.
> - Mettre en forme avec `sprintf()` et valider un format avec une expression régulière.

## Guillemets simples ou doubles

Entre **guillemets simples**, le texte est pris tel quel. Entre **guillemets doubles**, PHP remplace les variables par leur valeur (c'est l'**interpolation**) et interprète les séquences d'échappement comme `\n` (saut de ligne) ou `\t` (tabulation).

```php
$prenom = 'Ada';
echo 'Bonjour $prenom\n';   // Bonjour $prenom\n   (tel quel)
echo "Bonjour $prenom\n";   // Bonjour Ada  (suivi d'un saut de ligne)
```

Pour insérer un élément de tableau ou une propriété d'objet, entoure-le d'**accolades** : PHP sait alors exactement où l'expression commence et finit.

```php
echo "Film : {$film['titre']} ({$film['annee']})";
echo "Client : {$client->nom}";
```

Pour écrire un `$` ou un `"` littéral dans des guillemets doubles, échappe-le : `"Prix : 5 \$"`, `"Il a dit \"oui\""`.

### Heredoc et nowdoc

Pour un long texte sur plusieurs lignes (un modèle d'e-mail, un bloc HTML), le **heredoc** évite de jongler avec les guillemets. Il se comporte comme des guillemets doubles (les variables sont remplacées) ; le **nowdoc**, avec l'identifiant entre apostrophes, comme des guillemets simples (aucune interpolation).

```php
$message = <<<TXT
    Bonjour {$client['prenom']},
    votre commande n°{$numero} est expédiée.
    TXT;

$exemple = <<<'TXT'
    Ici, $prenom reste écrit tel quel.
    TXT;
```

L'identifiant de fermeture peut être indenté : cette indentation est retirée de toutes les lignes.

## Octets et caractères : UTF-8

En PHP, une chaîne est une suite d'**octets**. En UTF-8, les lettres sans accent occupent un octet, mais `é`, `ç` ou `€` en occupent plusieurs. Les fonctions classiques (`strlen`, `substr`, `strtoupper`…) comptent des **octets** :

```php
strlen('é');           // 2 : deux octets en UTF-8
mb_strlen('é');        // 1 : un caractère
strlen('Noël');        // 5
mb_strlen('Noël');     // 4
```

Dès qu'un texte peut contenir des accents (un nom, un commentaire, une saisie), utilise les fonctions **multioctets** `mb_*` : `mb_strlen`, `mb_substr`, `mb_strpos`, `mb_strtoupper`, `mb_strtolower`. Par exemple, la règle « un pseudo fait de 2 à 60 caractères » se vérifie avec `mb_strlen()`, sinon un pseudo accentué serait jugé trop long.

> [!attention]
> Depuis PHP 8.2, `strtoupper()`, `strtolower()` et `ucfirst()` ne modifient que les lettres **ASCII** : `strtoupper('élève')` donne `'éLèVE'` et `ucfirst('élodie')` laisse `'élodie'` inchangé. Utilise `mb_strtoupper('élève')` (`'ÉLÈVE'`) et, depuis PHP 8.4, `mb_ucfirst()`.

## Accéder aux caractères et extraire

```php
$s = 'PHP 8';
echo $s[0];      // P   (premier octet)
echo $s[-1];     // 8   (un indice négatif part de la fin)

echo substr('Bonjour', 0, 3);     // Bon   (à partir de 0, 3 octets)
echo substr('Bonjour', 3);        // jour  (jusqu'à la fin)
echo substr('Bonjour', -4);       // jour  (les 4 derniers)
echo mb_substr('Éléphant', 1, -1);   // léphan (du 2e caractère à l'avant-dernier)
```

Avec `substr()` et `mb_substr()`, une longueur **négative** signifie « s'arrêter à tant de caractères de la fin ».

## Chercher dans une chaîne

Depuis PHP 8, trois fonctions répondent directement par `true` ou `false` :

```php
str_contains('Bonjour', 'jour');       // true
str_starts_with('Bonjour', 'Bon');     // true
str_ends_with('photo.jpg', '.jpg');    // true
```

Elles sont **sensibles à la casse** (`str_contains('Bonjour', 'bon')` est faux). Et une chaîne contient toujours la chaîne vide : `str_contains('Bonjour', '')` est vrai.

Pour connaître la **position** d'un texte, `strpos()` (et `stripos()` sans tenir compte de la casse, `mb_strpos()` pour compter en caractères) retourne l'indice de la première occurrence, ou `false` s'il est absent.

> [!attention]
> La position peut valoir `0` (texte trouvé au tout début), et `0 == false` est vrai. Il faut donc toujours comparer strictement : `if (stripos($titre, $recherche) !== false)`. Avec `!= false`, un titre qui **commence** par le mot cherché serait considéré comme ne le contenant pas.

## Transformer

```php
str_replace('a', 'o', 'banana');                  // bonono (toutes les occurrences, sensible à la casse)
str_replace(['é', 'è'], ['e', 'e'], 'élève');     // eleve (avec des tableaux)
trim('  texte  ');                                // 'texte'
rtrim('1,2,3,', ',');                             // '1,2,3'
str_pad('7', 3, '0', STR_PAD_LEFT);               // '007'
str_repeat('=', 10);                              // '=========='
ucwords('bonjour le monde');                      // 'Bonjour Le Monde'
nl2br("ligne 1\nligne 2");                        // insère <br /> avant chaque saut de ligne
```

`trim()` sans second argument retire au début et à la fin les **espaces blancs** : espace, tabulation `\t`, sauts de ligne `\n` et `\r`, octet nul `\0` et tabulation verticale. C'est le premier nettoyage de toute saisie. Avec un second argument, il retire les caractères indiqués.

## Découper et assembler

`explode()` découpe une chaîne selon un séparateur ; `implode()` fait l'inverse.

```php
$genres = explode(', ', 'Science-fiction, Drame, Aventure');   // 3 éléments
echo implode(' / ', $genres);   // Science-fiction / Drame / Aventure
echo implode(', ', ['a', 'b', 'c']);   // a, b, c
```

Le séparateur doit correspondre **exactement** : `explode(',', 'a, b')` garde l'espace devant `' b'`. Combine avec `array_map('trim', …)` si les espaces sont irréguliers.

## Mettre en forme : sprintf

`sprintf()` construit une chaîne à partir d'un **modèle** et de valeurs ; `printf()` l'affiche directement.

| Format | Rôle | Exemple | Résultat |
|---|---|---|---|
| `%s` | chaîne | `sprintf('%s !', 'Salut')` | `Salut !` |
| `%d` | entier | `sprintf('%d articles', 3)` | `3 articles` |
| `%.2f` | décimal à 2 chiffres | `sprintf('%.2f', 3.14159)` | `3.14` |
| `%05.1f` | largeur 5, complété par des zéros | `sprintf('%05.1f', 3.14159)` | `003.1` |
| `%03d` | entier sur 3 chiffres | `sprintf('%03d', 7)` | `007` |

Dans `%05.1f`, `0` indique le caractère de remplissage, `5` la **largeur totale** (point compris) et `.1` le nombre de décimales. Pour un affichage « à la française » avec virgule et espace des milliers, `number_format()` reste plus simple (chapitre 01).

## Comparer deux chaînes

`===` dit si deux chaînes sont identiques. Pour **ordonner**, `strcmp($a, $b)` retourne un nombre négatif si `$a` vient avant `$b`, `0` si elles sont égales, positif sinon (depuis PHP 8.2, exactement `-1`, `0` ou `1`) : `strcmp('abc', 'abd')` vaut `-1`. `strcasecmp()` fait de même sans tenir compte de la casse. Ces fonctions s'utilisent directement avec `usort()`.

## Valider un format : les expressions régulières

Une expression régulière décrit un **motif** de texte. `preg_match()` retourne `1` si le motif est trouvé, `0` sinon (et `false` si le motif est invalide).

```php
preg_match('/^\d{5}$/', '75001');   // 1 : exactement 5 chiffres, du début (^) à la fin ($)
preg_match('/^\d{5}$/', '7500');    // 0

if (preg_match('/^(\d{2})(\d{3})$/', '75001', $morceaux)) {
    echo $morceaux[1];   // 75 : les parenthèses capturent des morceaux
}
```

Quelques éléments de base : `\d` un chiffre, `\w` une lettre, un chiffre ou `_`, `.` n'importe quel caractère, `+` « une fois ou plus », `*` « zéro fois ou plus », `?` « facultatif », `{2,5}` « de 2 à 5 fois », `[a-z]` une lettre minuscule. Sans `^` et `$`, le motif peut se trouver **n'importe où** dans la chaîne : c'est l'oubli le plus fréquent dans une validation. `preg_replace()` remplace selon un motif.

## Les pièges classiques

- Compter des caractères accentués avec `strlen()` au lieu de `mb_strlen()`.
- Tester `strpos(…) != false` au lieu de `!== false`.
- Attendre une interpolation entre guillemets simples.
- Oublier les accolades autour de `$t['cle']` dans une chaîne.
- Valider avec une expression régulière sans `^` ni `$`.

> [!retenir]
> - Guillemets doubles et heredoc remplacent les variables ; guillemets simples et nowdoc non.
> - `strlen()` compte des octets : pour du texte accentué, utilise les fonctions `mb_*`.
> - `str_contains`, `str_starts_with`, `str_ends_with` répondent par vrai/faux ; `strpos` peut retourner `0` : teste `!== false`.
> - `explode` découpe, `implode` assemble, `trim` nettoie, `str_replace` remplace.
> - `sprintf('%05.1f', 3.14159)` donne `003.1` ; `preg_match` retourne `1` ou `0`.
