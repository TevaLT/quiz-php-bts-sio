---
resume: Définir des contrats avec les interfaces, partager du code avec les traits et représenter un ensemble fermé de valeurs avec les énumérations.
---

> [!objectifs]
> - Écrire une interface, l'implémenter et programmer « contre l'interface ».
> - Distinguer interface et classe abstraite.
> - Réutiliser du code avec un trait et résoudre un conflit entre traits.
> - Remplacer des chaînes « magiques » par une énumération.

## Les interfaces

Une **interface** est un **contrat** : elle liste des méthodes publiques (leurs signatures, sans leur code) qu'une classe s'engage à fournir. Elle ne contient ni propriété, ni code de méthode (seulement, éventuellement, des constantes).

```php
interface Payable
{
    public function montant(): float;
    public function payer(): void;
}

class Facture implements Payable
{
    public function __construct(private float $total)
    {
    }

    public function montant(): float
    {
        return $this->total;
    }

    public function payer(): void
    {
        // …
    }
}
```

- Une classe peut implémenter **plusieurs** interfaces : `class Abonnement implements Payable, Resiliable`.
- Une interface **ne s'instancie pas** : `new Payable()` lance une `Error`.
- Si la classe oublie une méthode du contrat, PHP lève une erreur fatale.

### Programmer contre l'interface

L'intérêt majeur : le code qui **utilise** des objets peut dépendre de l'interface, pas d'une classe précise.

```php
function encaisser(Payable $element): void
{
    echo 'Paiement de ' . $element->montant() . " €\n";
    $element->payer();
}
```

`encaisser()` accepte une facture, un abonnement, une amende… n'importe quel objet qui respecte le contrat, y compris des classes écrites plus tard. Dans les tests, on peut lui passer un faux objet. C'est la base des architectures souples (chapitre 21).

## Interface ou classe abstraite ?

| | Interface | Classe abstraite |
|---|---|---|
| Contient du code | Non (seulement des signatures et des constantes) | Oui (méthodes concrètes, propriétés) |
| Une classe peut en utiliser | Plusieurs (`implements A, B`) | Une seule (`extends`) |
| Exprime | une **capacité** : « sait être payé », « est comptable » | une **base commune** : « est une forme » |

Les deux peuvent se combiner : une classe abstraite peut implémenter une interface et fournir une partie du code.

### Des interfaces fournies par PHP

Implémenter certaines interfaces standard intègre tes objets au langage :

- `Countable` : la méthode `count()` permet d'écrire `count($panier)` sur ton objet ;
- `IteratorAggregate` : l'objet devient parcourable avec `foreach` ;
- `JsonSerializable` : `json_encode()` utilise la méthode `jsonSerialize()` ;
- `Stringable` : implémentée automatiquement par toute classe qui définit `__toString()`.

```php
class Panier implements Countable
{
    private array $articles = [];

    public function count(): int
    {
        return count($this->articles);
    }
}

echo count(new Panier());   // 0
```

## Les traits

Un **trait** est un bloc de méthodes (et de propriétés) qu'on **copie** dans une classe avec `use`. Il permet de partager du code entre des classes qui n'ont pas de lien d'héritage, puisque PHP n'autorise qu'un seul parent.

```php
trait Horodatable
{
    private ?DateTimeImmutable $creeLe = null;

    public function marquerCreation(): void
    {
        $this->creeLe = new DateTimeImmutable();
    }

    public function creeLe(): ?DateTimeImmutable
    {
        return $this->creeLe;
    }
}

class Article
{
    use Horodatable;
}

class Commentaire
{
    use Horodatable;
}
```

Un trait n'est **pas un type** : on ne peut pas l'instancier ni l'utiliser comme type de paramètre. Il ne remplace pas une interface ; on combine souvent les deux (l'interface déclare le contrat, le trait fournit une implémentation réutilisable).

### Conflit entre deux traits

Si deux traits utilisés par la même classe définissent une méthode du même nom, PHP lève une erreur fatale. On choisit alors explicitement avec `insteadof`, et on peut garder l'autre version sous un autre nom avec `as` :

```php
class Accueil
{
    use Francais, Anglais {
        Francais::bonjour insteadof Anglais;   // c'est la version française qui s'appelle bonjour()
        Anglais::bonjour as hello;             // la version anglaise reste disponible sous le nom hello()
    }
}
```

## Les énumérations

Une **énumération** (`enum`, PHP 8.1) définit un type qui n'accepte qu'un **ensemble fermé de valeurs** : les **cas**.

```php
enum Statut: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Ferme = 'ferme';

    public function libelle(): string
    {
        return match ($this) {
            Statut::Actif => 'Compte actif',
            Statut::Suspendu => 'Compte suspendu',
            Statut::Ferme => 'Compte fermé',
        };
    }
}

function changerStatut(Compte $compte, Statut $statut): void { /* … */ }

changerStatut($compte, Statut::Suspendu);   // impossible de passer 'suspendue' avec une faute
```

Pourquoi une énumération plutôt que des chaînes `'actif'`, `'suspendu'` ? Parce qu'une chaîne peut contenir **n'importe quoi** (une faute de frappe, une majuscule…) et que l'erreur ne se voit qu'au moment du bug. Avec un `enum`, le type `Statut` garantit que la valeur est l'un des cas prévus ; l'éditeur propose les cas ; et un `match` qui oublie un cas lance une `UnhandledMatchError`.

### Énumération « adossée » (backed enum)

`enum Statut: string` déclare une énumération **adossée** à des valeurs `string` (ou `int`) : chaque cas a une `value`, utile pour enregistrer le statut en base ou dans du JSON.

```php
echo Statut::Actif->value;        // 'actif'
echo Statut::Actif->name;         // 'Actif'

$s = Statut::from('actif');       // Statut::Actif
$s = Statut::from('inconnu');     // ValueError : aucun cas n'a cette valeur
$s = Statut::tryFrom('inconnu');  // null : pas d'exception
$tous = Statut::cases();          // [Statut::Actif, Statut::Suspendu, Statut::Ferme]
```

- `from()` sert quand la valeur **doit** être valide (une erreur est alors une vraie anomalie) ; `tryFrom()` quand elle peut être invalide (une saisie utilisateur), pour la traiter toi-même.
- Chaque cas est un objet **unique** : `Statut::Actif === Statut::from('actif')` est vrai. On compare donc les cas avec `===`.
- Une énumération peut avoir des **méthodes**, des **constantes** et implémenter des **interfaces**, mais pas de propriétés modifiables.
- Une énumération sans `: string` ni `: int` est dite « pure » : ses cas n'ont pas de `value`.

## Les pièges classiques

- Mettre du code ou des propriétés dans une interface.
- Utiliser un trait comme un type (`function f(Horodatable $x)` est impossible).
- Garder des chaînes « magiques » pour des valeurs qui forment un ensemble fermé.
- Utiliser `from()` sur une saisie utilisateur sans gérer la `ValueError` (préférer `tryFrom()`).

> [!retenir]
> - Une interface est un contrat de méthodes publiques ; une classe peut en implémenter plusieurs ; elle ne s'instancie pas.
> - Type tes paramètres avec une interface pour accepter tout objet qui respecte le contrat.
> - Classe abstraite = base commune avec du code ; interface = capacité, sans code.
> - Un trait copie des méthodes dans une classe ; `insteadof` et `as` règlent les conflits.
> - `enum` : un ensemble fermé de cas ; `from()` lance une `ValueError`, `tryFrom()` retourne `null`, `cases()` les liste.
