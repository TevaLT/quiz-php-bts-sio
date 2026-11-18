---
resume: Gérer les dépendances d'un projet avec Composer, comprendre composer.json et composer.lock, et charger ses classes automatiquement grâce à PSR-4.
---

> [!objectifs]
> - Installer et mettre à jour des bibliothèques avec Composer.
> - Lire un `composer.json` : dépendances, contraintes de version, autoload, scripts.
> - Comprendre le rôle de `composer.lock` et savoir quoi versionner.
> - Organiser ses classes selon PSR-4 et diagnostiquer un « Class not found ».

## Composer, le gestionnaire de dépendances

Un projet réel s'appuie sur des **bibliothèques** écrites par d'autres : un micro-framework, une bibliothèque JWT, un outil de tests. **Composer** les télécharge, installe aussi les bibliothèques dont elles dépendent elles-mêmes, et vérifie que toutes les versions sont compatibles. Les paquets viennent de **Packagist**, le dépôt public de la communauté PHP.

```bash
composer require slim/slim:"^4.0"      # ajoute une dépendance et l'inscrit dans composer.json
composer require --dev phpunit/phpunit # dépendance réservée au développement
composer install                       # installe les versions exactes du composer.lock
composer update                        # met à jour selon les contraintes et réécrit le composer.lock
composer dump-autoload                 # régénère l'autoloader
```

Les bibliothèques sont installées dans le dossier `vendor/`.

## composer.json

```json
{
    "name": "moi/cineclub",
    "require": {
        "php": ">=8.2",
        "slim/slim": "^4.12",
        "slim/psr7": "^1.6"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "scripts": {
        "start": "php -S localhost:8000 -t public",
        "test": "phpunit tests"
    }
}
```

- `require` liste le **code tiers** dont l'application a besoin (et la version de PHP exigée) ; `require-dev`, ce qui sert seulement à développer (tests, analyse).
- `autoload` décrit comment trouver **tes propres classes** : ici, tout ce qui commence par `App\` est dans `src/`.
- `scripts` définit des **raccourcis de commandes** : `composer start` lance le serveur, `composer test` les tests. Un script n'est pas une dépendance : c'est juste une commande qu'on n'a plus à retaper.

### Les contraintes de version

Les bibliothèques suivent le **versionnement sémantique** `MAJEUR.MINEUR.CORRECTIF` : un correctif répare, une version mineure ajoute sans casser, une version **majeure** peut **casser la compatibilité**.

| Contrainte | Accepte | Usage |
|---|---|---|
| `^4.0` | de 4.0 jusqu'à 5.0 exclu | le cas courant : toutes les versions compatibles 4.x |
| `^4.12` | de 4.12 jusqu'à 5.0 exclu | au moins une fonctionnalité de la 4.12 |
| `~4.12.1` | de 4.12.1 jusqu'à 4.13 exclu | seulement les correctifs |
| `4.12.3` | exactement cette version | rare : bloque toute mise à jour |

## composer.lock : la photo exacte

Une contrainte comme `^4.12` autorise plusieurs versions. Le fichier **`composer.lock`** enregistre la version **exacte** de chaque paquet installé (dépendances des dépendances comprises) :

- `composer install` lit le `composer.lock` et installe **exactement** ces versions : toute l'équipe et le serveur de production ont le **même code**.
- `composer update` cherche les versions les plus récentes **autorisées par les contraintes**, les installe et **réécrit** le `composer.lock`. On le lance volontairement, puis on teste.

> [!retenir]
> On **versionne** dans Git `composer.json` **et** `composer.lock`. On ne versionne **pas** `vendor/` (on l'ajoute au `.gitignore`) : il se reconstruit avec `composer install`.

Ne modifie **jamais** le contenu de `vendor/` : tes changements seraient écrasés au prochain `install` ou `update`, et personne d'autre ne les aurait. Pour adapter une bibliothèque, on l'étend ou on l'enveloppe dans son propre code.

## L'autoload PSR-4

Composer génère un **autoloader** (chapitre 11) qui charge les classes à la demande, celles des bibliothèques comme les tiennes. On l'inclut **une seule fois**, au début du point d'entrée (le contrôleur frontal `public/index.php`) :

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\Film;

$film = new Film('Le Voyage', 1902);   // src/Domain/Film.php est chargé automatiquement
```

La règle **PSR-4** fait correspondre namespace et chemin. Avec `"App\\": "src/"` :

| Classe | Fichier |
|---|---|
| `App\Domain\Film` | `src/Domain/Film.php` |
| `App\Controller\FilmController` | `src/Controller/FilmController.php` |
| `App\Repository\FilmRepository` | `src/Repository/FilmRepository.php` |

Le préfixe `App\` est remplacé par `src/`, chaque `\` devient un `/`, et on ajoute `.php`. (Dans le JSON, le `\` s'écrit `\\`.)

### « Class "App\Domain\Film" not found »

Le fichier existe, et pourtant PHP ne trouve pas la classe. Les causes les plus fréquentes :

- le **namespace** déclaré dans le fichier ne correspond pas à son dossier (`namespace App\Model;` dans `src/Domain/Film.php`) ;
- la **casse** ne correspond pas : `film.php` au lieu de `Film.php`. Windows et macOS ignorent souvent la casse des noms de fichiers, mais **Linux non** : le projet marche sur ton poste et casse sur le serveur ;
- `vendor/autoload.php` n'est pas inclus, ou la section `autoload` vient d'être modifiée sans régénérer l'autoloader.

`composer dump-autoload` est nécessaire quand tu **modifies la section `autoload`** de `composer.json` (nouveau préfixe, nouveau dossier). Ajouter une classe dans un dossier PSR-4 déjà déclaré ne le demande pas : l'autoloader calcule le chemin à chaque fois.

## Les PSR

**PSR** signifie *PHP Standards Recommendation* : des recommandations publiées par le groupe PHP-FIG pour que les bibliothèques fonctionnent ensemble. Les plus courantes :

- **PSR-4** : l'autoload (namespace ↔ dossier) ;
- **PSR-12** (étendue par **PER Coding Style**) : le style de code (indentation, accolades, espaces) ;
- **PSR-7** : les objets requête et réponse HTTP (chapitre 21) ;
- **PSR-15** : les middlewares HTTP ;
- **PSR-3** : l'interface des journaux (*loggers*).

## Les pièges classiques

- Versionner `vendor/`, ou oublier de versionner `composer.lock`.
- Lancer `composer update` sur le serveur de production au lieu de `composer install`.
- Modifier un fichier dans `vendor/`.
- Un namespace qui ne suit pas l'arborescence, ou une casse différente entre la classe et le fichier.
- Inclure les fichiers à la main avec `require` alors que l'autoloader est là.

> [!retenir]
> - `composer require` ajoute une dépendance ; `install` installe les versions du lock ; `update` les met à jour et réécrit le lock.
> - `require` = code tiers ; `autoload` = tes classes ; `scripts` = raccourcis de commandes (`composer start`).
> - `^4.0` : toute version 4.x, jamais la 5.0.
> - Versionne `composer.json` et `composer.lock`, pas `vendor/` ; ne modifie jamais `vendor/`.
> - PSR-4 : `App\Domain\Film` → `src/Domain/Film.php`, casse comprise ; `vendor/autoload.php` dans `public/index.php`.
