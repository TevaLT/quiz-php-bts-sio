---
resume: Ranger ses classes dans des namespaces, les importer avec use, charger automatiquement leurs fichiers, et utiliser à bon escient les constantes et membres statiques.
---

> [!objectifs]
> - Déclarer un namespace et importer des classes avec `use`.
> - Comprendre les noms complets, `::class` et le `\` initial.
> - Charger les classes automatiquement avec `spl_autoload_register()`.
> - Utiliser les constantes de classe, les propriétés et méthodes statiques, `self` et `static`.

## Pourquoi des namespaces ?

Dans un vrai projet, ton code côtoie celui de dizaines de bibliothèques. Deux d'entre elles peuvent très bien déclarer une classe `Produit` ou `Client`. Un **namespace** (espace de noms) range les classes dans des « dossiers » logiques et évite ces **collisions de noms** : `App\Model\Produit` et `Boutique\Produit` sont deux classes différentes.

```php
<?php
declare(strict_types=1);

namespace App\Model;

class Produit
{
    // …
}
```

La déclaration `namespace` se place **en tout début de fichier** : seule `declare(strict_types=1);` peut la précéder. Par convention, le namespace suit l'arborescence des dossiers : `App\Model\Produit` est rangée dans `src/Model/Produit.php`.

Le **nom complet** (pleinement qualifié) de la classe est `App\Model\Produit`. La constante magique `::class` le retourne : `Produit::class` vaut `'App\Model\Produit'`.

## Importer avec use

Pour utiliser une classe d'un autre namespace sans écrire son nom complet à chaque fois, on l'**importe** avec `use` en haut du fichier :

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Produit;
use App\Service\Panier as ServicePanier;   // alias, en cas de conflit de noms

$produit = new Produit('Stylo', 2.5);       // désigne App\Model\Produit
```

`use` ne charge **aucun fichier** et n'exécute rien : il crée seulement un **raccourci de nom** valable dans ce fichier.

### Le \ initial : la racine

Dans un fichier qui déclare `namespace App;`, un nom de classe sans `\` est cherché **dans le namespace courant** : `new DateTime()` chercherait `App\DateTime`, qui n'existe pas. Les classes de PHP sont dans l'espace **global** (la racine) : on écrit `new \DateTime()`, ou on les importe (`use DateTime;`).

Pour les **fonctions** et les constantes (`strlen`, `PHP_EOL`), PHP cherche d'abord dans le namespace courant, puis **se replie** sur l'espace global : `strlen()` fonctionne sans `\`. Ce repli n'existe pas pour les classes.

> [!attention]
> Deux fichiers qui déclarent la même classe dans le même namespace ne peuvent pas être chargés ensemble : PHP s'arrête sur une erreur fatale « Cannot redeclare class App\Produit ». Un nom complet doit être unique dans toute l'application.

## L'autoloading

Inclure à la main chaque fichier de classe avec `require` devient vite ingérable. L'**autoloading** demande à PHP de charger le fichier d'une classe **automatiquement**, la première fois qu'on l'utilise. `spl_autoload_register()` enregistre la fonction chargée de trouver ce fichier :

```php
spl_autoload_register(function (string $classe): void {
    // App\Model\Produit  →  src/Model/Produit.php
    $prefixe = 'App\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;   // pas une classe de notre application : un autre chargeur s'en occupera
    }
    $relatif = substr($classe, strlen($prefixe));
    $fichier = __DIR__ . '/src/' . str_replace('\\', '/', $relatif) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});

$p = new App\Model\Produit('Stylo', 2.5);   // déclenche le chargement de src/Model/Produit.php
```

Cette correspondance namespace → dossier est normalisée par la recommandation **PSR-4**. En pratique, on ne l'écrit pas soi-même : **Composer** génère l'autoloader (chapitre 20).

## Les constantes de classe

Une constante de classe appartient à la classe, pas à chaque objet. On la lit avec l'opérateur `::` (opérateur de résolution de portée).

```php
class Commande
{
    public const TVA = 0.20;
    public const STATUT_INITIAL = 'en_attente';

    public function totalTtc(float $ht): float
    {
        return $ht * (1 + self::TVA);   // depuis la classe : self::
    }
}

echo Commande::TVA;   // depuis l'extérieur : NomDeClasse::CONSTANTE
```

## Les membres statiques

Une propriété **statique** appartient à la **classe** : il n'en existe qu'un exemplaire, partagé par tous les objets. Une méthode statique s'appelle sur la classe, sans objet.

```php
class Produit
{
    private static int $nombreCrees = 0;

    public function __construct(public string $nom)
    {
        self::$nombreCrees++;
    }

    public static function nombreCrees(): int
    {
        return self::$nombreCrees;
    }
}

new Produit('Stylo');
new Produit('Cahier');
echo Produit::nombreCrees();   // 2
```

- On appelle une méthode statique avec `NomDeClasse::methode()` : `Produit::nombreCrees()`.
- Dans une méthode statique, il n'y a **pas d'objet courant** : `$this` n'existe pas (l'utiliser lance une erreur « Using $this when not in object context »).
- Depuis la classe, on accède aux membres statiques avec `self::` (ou `static::`).

### self ou static ?

`self` désigne **la classe où le code est écrit** ; `static` désigne **la classe réellement appelée** (on parle de *late static binding*). La différence apparaît avec l'héritage :

```php
class A
{
    public static function creer(): string
    {
        return static::class;   // la classe appelée
    }

    public static function creerSelf(): string
    {
        return self::class;     // toujours A
    }
}

class B extends A
{
}

echo B::creer();       // B
echo B::creerSelf();   // A
```

`static` est donc indispensable pour une méthode de fabrique héritée qui doit créer un objet **de la classe enfant** : `return new static(…);`.

## Statique : avec modération

Les méthodes statiques sont pratiques, mais elles créent des **dépendances cachées** : le code qui appelle `Base::connexion()` est soudé à cette classe précise, impossible à remplacer par une autre implémentation ou par un faux objet dans un test. Une propriété statique modifiable est en plus un **état global**, que n'importe quel code peut changer.

Bons usages du statique :

- les **constantes** de classe ;
- les **méthodes de fabrique** (constructeurs nommés) : `Produit::depuisTableau($ligne)`, `Couleur::depuisHex('#ff0000')` ;
- les fonctions utilitaires **pures**, sans état.

Pour le reste (accès à la base, envoi d'e-mails, configuration), préfère des **objets** passés au constructeur : c'est l'injection de dépendances (chapitre 21).

## Les pièges classiques

- Oublier le `\` devant une classe de PHP dans un fichier avec namespace (`new DateTime()`).
- Croire que `use` inclut le fichier : c'est l'autoloader qui le charge.
- Placer du code avant la déclaration `namespace`.
- Utiliser `$this` dans une méthode statique.
- Utiliser `self::` dans une méthode de fabrique destinée à être héritée (il faut `static`).

> [!retenir]
> - Un namespace évite les collisions de noms ; il se déclare en tête de fichier et suit les dossiers.
> - `use App\Model\Produit;` crée un raccourci de nom ; `Produit::class` donne le nom complet.
> - Dans un namespace, les classes de PHP s'écrivent avec `\` (`new \DateTime()`) ou s'importent.
> - `spl_autoload_register()` charge les classes à la demande ; Composer le fait pour toi (PSR-4).
> - `Classe::CONSTANTE`, `Classe::methode()` ; pas de `$this` en statique ; `static::` suit la classe appelée.
> - Le statique est à réserver aux constantes, fabriques et utilitaires purs.
