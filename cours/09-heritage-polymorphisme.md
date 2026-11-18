---
resume: Réutiliser et spécialiser des classes avec l'héritage, définir des classes abstraites, profiter du polymorphisme et savoir quand préférer la composition.
---

> [!objectifs]
> - Créer une classe enfant avec `extends` et appeler le constructeur parent.
> - Redéfinir une méthode en respectant les règles de compatibilité.
> - Utiliser `protected`, `final` et les classes abstraites.
> - Comprendre le polymorphisme, le principe de Liskov, et choisir entre héritage et composition.

## Hériter d'une classe

`class Chien extends Animal` signifie que `Chien` **hérite** de toutes les propriétés et méthodes publiques et protégées d'`Animal`, et peut en ajouter ou en redéfinir. On dit que `Chien` est une classe **enfant** (ou sous-classe) et `Animal` sa classe **parent**. L'héritage exprime une relation « **est un** » : un chien **est un** animal.

```php
class Animal
{
    public function __construct(protected string $nom)
    {
    }

    public function presenter(): string
    {
        return "Je m'appelle {$this->nom} et je fais « {$this->cri()} ».";
    }

    public function cri(): string
    {
        return '…';
    }
}

class Chien extends Animal
{
    public function __construct(string $nom, private string $race)
    {
        parent::__construct($nom);   // le parent initialise SA partie de l'objet
    }

    public function cri(): string    // redéfinition
    {
        return 'Wouf';
    }
}

echo (new Chien('Rex', 'berger'))->presenter();   // Je m'appelle Rex et je fais « Wouf ».
```

- Si l'enfant définit son propre constructeur, celui du parent n'est **pas** appelé automatiquement : `parent::__construct($nom)` le fait explicitement.
- Une méthode redéfinie peut réutiliser la version du parent avec `parent::methode()`.
- Une classe PHP n'étend **qu'une seule** classe (héritage simple). Pour partager du code entre classes sans lien de parenté, on utilise les traits (chapitre 10).

### protected

`protected` rend un membre accessible dans la classe **et dans ses enfants**, mais pas de l'extérieur. Ci-dessus, `Chien` peut lire `$this->nom` ; le code qui utilise l'objet, non.

## Redéfinir une méthode : les règles

Une méthode redéfinie doit rester **compatible** avec celle du parent, pour que l'enfant puisse être utilisé partout où le parent est attendu :

- la visibilité ne peut pas être **réduite** : redéfinir en `private` une méthode `public` du parent est une erreur fatale (« Access level to Chien::cri() must be public ») ;
- les paramètres et le type de retour doivent être compatibles (même nombre d'arguments obligatoires, types au moins aussi larges en entrée, au moins aussi précis en sortie).

L'attribut `#[\Override]` (PHP 8.3), placé sur une méthode, déclare l'intention de **redéfinir** une méthode du parent. Si aucune méthode du parent ne porte ce nom (faute de frappe, ou méthode renommée dans le parent), PHP lève une erreur au lieu de créer silencieusement une nouvelle méthode.

```php
class Chat extends Animal
{
    #[\Override]
    public function cri(): string
    {
        return 'Miaou';
    }
}
```

## final : interdire l'héritage

`final` devant une **classe** interdit de l'étendre ; devant une **méthode**, interdit de la redéfinir. On l'utilise pour protéger un comportement qui ne doit pas être modifié (une règle de sécurité, un calcul critique), ou simplement pour signaler qu'une classe n'a pas été conçue pour être étendue.

## Les classes abstraites

Une **classe abstraite** est un modèle **incomplet** : on ne peut pas l'instancier (`new Forme()` lance une `Error`), elle sert uniquement de parent. Elle peut contenir des méthodes **abstraites** : une signature sans corps, que chaque enfant concret **doit** implémenter.

```php
abstract class Forme
{
    abstract public function aire(): float;   // chaque forme sait calculer son aire

    public function decrire(): string         // code commun à toutes les formes
    {
        return static::class . ' d\'aire ' . round($this->aire(), 2);
    }
}

class Rectangle extends Forme
{
    public function __construct(private float $largeur, private float $hauteur)
    {
    }

    public function aire(): float
    {
        return $this->largeur * $this->hauteur;
    }
}

class Cercle extends Forme
{
    public function __construct(private float $rayon)
    {
    }

    public function aire(): float
    {
        return M_PI * $this->rayon ** 2;
    }
}
```

Une classe concrète qui oublie d'implémenter une méthode abstraite provoque une **erreur fatale** dès sa déclaration : PHP refuse une classe qui ne respecte pas le contrat.

## Le polymorphisme

Le **polymorphisme**, c'est la possibilité d'appeler **la même méthode** sur des objets de classes différentes, chacun réagissant **à sa façon**. Le code appelant n'a pas besoin de connaître la classe exacte :

```php
$formes = [new Rectangle(2, 3), new Cercle(1)];

foreach ($formes as $forme) {
    echo $forme->decrire(), "\n";   // Rectangle d'aire 6, puis Cercle d'aire 3.14
}
```

C'est la méthode de l'**objet réel** qui est exécutée, même quand l'appel se fait depuis le code du parent : dans `presenter()`, l'appel `$this->cri()` exécute `Chien::cri()` si l'objet est un chien. On peut ainsi ajouter une nouvelle forme (un triangle) **sans modifier** la boucle : c'est l'un des grands intérêts de l'objet. Une suite de `if ($forme instanceof …)` est souvent le signe qu'il manque une méthode polymorphe.

## Le principe de substitution de Liskov

Le **principe de Liskov** (le « L » de SOLID) dit qu'on doit pouvoir **remplacer un objet parent par n'importe lequel de ses enfants sans casser le programme**. Un enfant peut ajouter des comportements, mais ne doit pas trahir le contrat du parent : lever des exceptions inattendues, exiger plus en entrée, garantir moins en sortie.

Exemple classique : faire hériter `Carre` de `Rectangle` paraît logique en géométrie, mais si `Rectangle` a une méthode `changerLargeur()`, un carré ne peut pas la respecter sans modifier aussi sa hauteur. Le code qui s'attend à un rectangle est alors surpris : l'héritage était une mauvaise idée.

## Héritage ou composition ?

L'héritage crée un lien **fort** et définitif entre deux classes. La **composition** consiste à donner à un objet un autre objet en propriété, auquel il délègue une partie du travail : c'est une relation « **a un** » (ou « utilise un »).

```php
class Commande
{
    public function __construct(
        private Client $client,               // une commande A un client
        private CalculateurRemise $remise,    // et UTILISE un calculateur
    ) {
    }

    public function total(float $montant): float
    {
        return $montant - $this->remise->calculer($this->client, $montant);
    }
}
```

Préfère la composition quand :

- la relation n'est pas vraiment « est un » (une commande n'**est** pas un client) ;
- tu veux réutiliser un comportement dans des classes sans rapport ;
- le comportement doit pouvoir **changer** (remplacer le calculateur de remise par un autre, ou par un faux objet dans les tests).

La règle souvent citée : « préférer la composition à l'héritage ». L'héritage reste idéal pour une vraie hiérarchie « est un », peu profonde.

## Les pièges classiques

- Oublier `parent::__construct()` : la partie parent de l'objet n'est pas initialisée.
- Réduire la visibilité ou changer la signature d'une méthode redéfinie.
- Tenter d'instancier une classe abstraite.
- Construire de longues chaînes d'héritage (`A → B → C → D`) difficiles à comprendre.
- Hériter seulement pour récupérer du code, sans relation « est un ».

> [!retenir]
> - `extends` : l'enfant hérite des membres publics et protégés ; une seule classe parent.
> - `parent::__construct()` et `parent::methode()` appellent la version du parent.
> - Une redéfinition garde une visibilité au moins aussi large ; `#[\Override]` vérifie qu'on redéfinit bien.
> - `final` interdit l'héritage ou la redéfinition ; une classe abstraite ne s'instancie pas.
> - Polymorphisme : même appel, comportement propre à l'objet réel.
> - Liskov : un enfant doit pouvoir remplacer son parent ; sinon, préfère la composition.
