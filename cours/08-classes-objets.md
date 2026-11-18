---
resume: Modéliser des données avec des classes, créer des objets, protéger leur état par l'encapsulation, et comprendre comment PHP copie et compare les objets.
---

> [!objectifs]
> - Déclarer une classe avec des propriétés typées, un constructeur et des méthodes.
> - Protéger l'état d'un objet avec `private` et `readonly`.
> - Comprendre qu'une variable contient une **poignée** vers l'objet : affectation, `clone`, comparaison.
> - Utiliser `instanceof`, l'opérateur `?->` et la méthode `__toString()`.

## Une classe, des objets

Une **classe** est un plan : elle décrit les données (les **propriétés**) et les comportements (les **méthodes**) d'un type d'objet. Un **objet** (ou **instance**) est un exemplaire construit à partir de ce plan avec `new`.

```php
<?php
declare(strict_types=1);

class Produit
{
    public string $nom;
    public float $prix;

    public function __construct(string $nom, float $prix)
    {
        $this->nom = $nom;
        $this->prix = $prix;
    }

    public function prixTtc(float $taux = 0.20): float
    {
        return round($this->prix * (1 + $taux), 2);
    }
}

$stylo = new Produit('Stylo', 2.5);   // crée un objet et appelle __construct
echo $stylo->nom;                     // Stylo
echo $stylo->prixTtc();               // 3
```

- `new Produit('Stylo', 2.5)` crée un **nouvel objet** et appelle automatiquement le **constructeur** `__construct()` avec ces arguments.
- L'opérateur `->` accède à une propriété ou appelle une méthode **sur un objet** : `$stylo->prixTtc()`.
- Dans une méthode, `$this` désigne **l'objet sur lequel la méthode a été appelée** : pour `$stylo->prixTtc()`, `$this` est `$stylo`.

Par convention, une classe porte un nom en PascalCase (`Produit`, `CommandeClient`) et se range dans un fichier du même nom (`Produit.php`).

## La promotion de constructeur

Déclarer une propriété, la recevoir en paramètre et l'affecter est si courant que PHP 8 propose un raccourci : la **promotion de constructeur**. Un paramètre précédé d'une visibilité devient automatiquement une propriété, initialisée avec l'argument.

```php
class Film
{
    public function __construct(
        private string $titre,
        private int $annee,
    ) {
    }
}
```

Ce code déclare les propriétés `$titre` et `$annee` **et** les remplit : il est équivalent à la version longue, en trois fois moins de lignes.

## Encapsulation : private et les méthodes

La **visibilité** indique qui peut accéder à une propriété ou à une méthode :

| Mot-clé | Accessible depuis… |
|---|---|
| `public` | partout |
| `protected` | la classe et ses classes enfants (chapitre 09) |
| `private` | uniquement l'intérieur de la classe |

Rendre les propriétés `private` et les exposer par des méthodes, c'est l'**encapsulation**. L'objet garde le contrôle de son état : il peut **refuser une valeur invalide**, et l'intérieur de la classe peut évoluer sans casser le code qui l'utilise.

```php
class CompteBancaire
{
    private float $solde = 0;

    public function deposer(float $montant): void
    {
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le dépôt doit être positif.');
        }
        $this->solde += $montant;
    }

    public function solde(): float
    {
        return $this->solde;
    }
}

$compte = new CompteBancaire();
$compte->deposer(50);
echo $compte->solde();     // 50
$compte->solde = 1000000;  // Error : la propriété est privée
```

Personne ne peut mettre le solde dans un état incohérent : toute modification passe par `deposer()`, qui vérifie la règle.

## readonly : une valeur fixée une fois pour toutes

Une propriété `readonly` (PHP 8.1) ne peut être initialisée **qu'une seule fois**, depuis l'intérieur de la classe (en général dans le constructeur). Toute modification ultérieure lance une `Error`.

```php
class Point
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
    ) {
    }
}

$p = new Point(2, 3);
echo $p->x;    // 2 : lecture autorisée, même de l'extérieur
$p->x = 5;     // Error: Cannot modify readonly property Point::$x
```

Placé devant la classe (`readonly class Point`, PHP 8.2), le mot-clé rend **toutes** ses propriétés `readonly`. On obtient un **objet immuable** : pour « modifier » un point, on en crée un nouveau. Les objets immuables évitent toute une famille de bugs, car personne ne peut les changer dans ton dos.

> [!attention]
> Une propriété typée sans valeur par défaut est **non initialisée** tant qu'on ne lui a rien affecté. La lire lance une `Error` : « Typed property Produit::$nom must not be accessed before initialization ». Initialise toutes les propriétés dans le constructeur, ou donne-leur une valeur par défaut.

Depuis PHP 8.2, créer une propriété qui n'a pas été déclarée dans la classe (`$p->couleur = 'bleu'`) est **déprécié** : déclare toujours tes propriétés.

## Les objets se manipulent par poignée

Une variable ne contient pas l'objet lui-même, mais une **poignée** (un identifiant) qui le désigne. Copier la variable copie la poignée : les deux variables désignent **le même objet**.

```php
class Compteur
{
    public int $n = 0;
}

$a = new Compteur();
$b = $a;          // $b désigne le MÊME objet que $a
$b->n = 5;
echo $a->n;       // 5
```

C'est la même chose quand on passe un objet à une fonction : la fonction peut modifier l'objet reçu (contrairement à un tableau, qui est copié).

Pour obtenir un **second objet**, il faut le cloner : `$copie = clone $a;`. Le clonage est **superficiel** : les propriétés qui contiennent elles-mêmes des objets sont copiées… par poignée. Une `$commande` clonée partage donc le même objet `$client` que l'originale. Pour une copie profonde, la classe définit la méthode `__clone()`, appelée sur la copie :

```php
public function __clone()
{
    $this->client = clone $this->client;
}
```

### Comparer deux objets

- `$a == $b` est vrai si les deux objets sont de la **même classe** et ont des **propriétés égales**.
- `$a === $b` est vrai seulement s'il s'agit du **même objet** (la même instance).

Deux `new Compteur()` distincts sont donc égaux avec `==` mais différents avec `===`.

## Tester et naviguer

`instanceof` teste si un objet est une instance d'une classe (ou d'une classe enfant, ou d'une interface qu'il implémente) :

```php
if ($article instanceof Produit) {
    echo $article->prixTtc();
}
```

L'opérateur **nullsafe** `?->` (PHP 8) arrête la chaîne dès qu'un maillon vaut `null`, au lieu de lancer une erreur :

```php
$ville = $client?->adresse?->ville;   // null si $client ou son adresse vaut null
```

## Quelques méthodes utiles

La méthode magique `__toString()` définit la **représentation texte** d'un objet : elle est appelée automatiquement quand l'objet est utilisé comme une chaîne (`echo`, concaténation).

```php
public function __toString(): string
{
    return "{$this->nom} ({$this->prix} €)";
}
// echo $stylo;  →  Stylo (2.5 €)
```

Une méthode qui modifie l'objet peut **retourner `$this`** : on peut alors enchaîner les appels (interface « fluide »).

```php
$requete = (new Requete())
    ->table('produits')
    ->ou('prix', '<', 10)
    ->trierPar('nom');
```

## Les pièges classiques

- Oublier `$this->` dans une méthode : `$nom` désigne alors une variable locale, pas la propriété.
- Croire que `$b = $a` copie l'objet : il faut `clone`.
- Oublier que `clone` est superficiel pour les objets imbriqués.
- Laisser des propriétés `public` qui peuvent être mises dans un état incohérent.
- Lire une propriété typée jamais initialisée.

> [!retenir]
> - Une classe décrit propriétés et méthodes ; `new` crée un objet et appelle `__construct()`.
> - `->` accède aux membres d'un objet ; `$this` est l'objet courant.
> - La promotion de constructeur déclare et initialise les propriétés en une fois.
> - `private` + méthodes = encapsulation ; `readonly` = valeur fixée une seule fois.
> - Une variable contient une poignée : `$b = $a` partage l'objet, `clone` le copie (superficiellement).
> - `==` compare les propriétés, `===` l'identité ; `?->` s'arrête sur `null`.
