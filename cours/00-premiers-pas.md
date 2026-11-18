---
resume: Où s'exécute PHP, comment écrire et lancer un premier script, afficher des valeurs pour déboguer et lire un message d'erreur sans paniquer.
---

> [!objectifs]
> - Comprendre ce qui se passe entre le navigateur et le serveur quand on demande une page PHP.
> - Écrire un premier script et le lancer, en ligne de commande puis avec le serveur intégré.
> - Afficher du texte et des valeurs, commenter son code.
> - Déboguer : inspecter une variable, lire un message d'erreur et retrouver la ligne fautive.

## PHP s'exécute sur le serveur

Quand tu tapes l'adresse d'une page PHP, ton navigateur envoie une **requête** au serveur. Le serveur repère que le fichier se termine par `.php` et le confie à l'interpréteur PHP. PHP exécute le script **sur le serveur**, et ce que le script affiche (en général du HTML) est renvoyé au navigateur dans la **réponse**.

Le navigateur ne reçoit donc jamais le code PHP : seulement le résultat. Si tu fais « Afficher le code source de la page », tu vois le HTML produit, pas les instructions PHP qui l'ont fabriqué. C'est aussi pour cela qu'on peut écrire dans un script des informations que le visiteur ne doit pas voir, comme le mot de passe de la base de données.

```php
<!-- fichier bonjour.php, sur le serveur -->
<p>Nous sommes le <?php echo date('d/m/Y'); ?>.</p>
```

```html
<!-- ce que reçoit le navigateur -->
<p>Nous sommes le 18/11/2026.</p>
```

> [!retenir]
> PHP tourne côté **serveur** ; HTML, CSS et JavaScript sont interprétés côté **navigateur** (client). Le navigateur ne voit que la sortie du script.

## Les balises PHP

Un fichier PHP peut mélanger du HTML et du PHP. Le code PHP se place entre `<?php` et `?>` ; tout ce qui est en dehors est envoyé tel quel.

```php
<h1>Accueil</h1>
<?php
$prenom = 'Lina';
echo '<p>Bonjour ' . $prenom . ' !</p>';
?>
<footer>Fin de la page</footer>
```

Pour afficher une seule valeur au milieu du HTML, la **balise courte d'affichage** `<?= … ?>` est plus lisible : `<?= $titre ?>` est exactement équivalent à `<?php echo $titre; ?>`.

```php
<title><?= $titre ?></title>
```

Dans un fichier qui ne contient **que** du PHP (une classe, une fonction, un fichier de configuration), on **omet la balise fermante** `?>`. Sinon, un simple saut de ligne après elle serait envoyé au navigateur, ce qui peut casser l'envoi d'en-têtes HTTP (erreur « headers already sent »).

## Afficher du texte : echo et print

`echo` affiche une ou plusieurs chaînes. Ce n'est pas une fonction mais une construction du langage : les parenthèses sont inutiles.

```php
echo 'Bonjour';
echo 'Prix : ', 12, ' €';        // plusieurs arguments séparés par des virgules
echo 'Total : ' . 3 * 4 . ' €';  // le point « . » concatène (colle) des chaînes
```

`print` fait presque la même chose, mais n'accepte qu'un argument et **retourne 1** : on peut donc l'utiliser dans une expression. En pratique, on utilise `echo`.

> [!attention]
> En PHP, la concaténation se fait avec le **point** `.`, pas avec `+`. Le `+` sert uniquement aux additions : `'3' + '4'` vaut `7`, alors que `'3' . '4'` vaut `'34'`.

Chaque instruction se termine par un **point-virgule**. En oublier un provoque une erreur de syntaxe (« Parse error »), souvent signalée à la ligne **suivante** : c'est là que PHP s'est rendu compte que quelque chose n'allait pas.

## Commenter son code

Les commentaires sont ignorés par PHP. Ils expliquent le **pourquoi** du code, pas ce qui est déjà évident.

```php
// commentaire sur une ligne
# autre forme de commentaire sur une ligne (rare)

/*
   commentaire
   sur plusieurs lignes
*/

/**
 * Bloc de documentation (PHPDoc), placé au-dessus d'une fonction ou d'une classe.
 * Les éditeurs s'en servent pour l'autocomplétion.
 */
```

> [!astuce]
> Attention à `#[` : depuis PHP 8, ces deux caractères ouvrent un **attribut** (une annotation lue par le langage), pas un commentaire. Préfère `//` pour tes commentaires.

## Lancer un script

### En ligne de commande

PHP s'installe avec un exécutable `php` utilisable dans un terminal :

```bash
php -v                 # version installée de PHP
php bonjour.php        # exécute le script et affiche sa sortie dans le terminal
php -l bonjour.php     # vérifie seulement la syntaxe (lint), sans exécuter
php -r 'echo 2 ** 10;' # exécute une ligne de code
```

`php -l` est très pratique : il signale une erreur de syntaxe sans lancer le script, donc sans risque d'effet de bord (envoi d'e-mail, écriture en base…).

### Avec le serveur web intégré

Pour développer un site, PHP fournit un petit serveur web. Place-toi dans le dossier du projet et lance :

```bash
php -S localhost:8000             # sert le dossier courant
php -S localhost:8000 -t public   # sert le dossier public/
```

Ouvre ensuite `http://localhost:8000` dans ton navigateur. Ce serveur est réservé au **développement** : en production, on utilise Apache ou Nginx avec PHP-FPM.

## Le nom des choses : casse et variables

Une **variable** commence par `$`, suivie d'un nom (lettres, chiffres, `_`, sans commencer par un chiffre). Les noms de variables sont **sensibles à la casse** : `$titre` et `$Titre` sont deux variables différentes.

En revanche, les **mots-clés** (`echo`, `if`, `function`…), les noms de **fonctions** et les noms de **classes** ne sont pas sensibles à la casse : `ECHO` ou `StrLen()` fonctionnent, mais c'est une très mauvaise habitude. Écris toujours les noms comme ils sont déclarés.

| Élément | Sensible à la casse ? |
|---|---|
| Variables (`$prenom`) | Oui |
| Constantes (`MAX_ESSAIS`) | Oui |
| Clés de tableau (`'Nom'` ≠ `'nom'`) | Oui |
| Mots-clés, fonctions, classes, méthodes | Non |

## Déboguer : inspecter une variable

Quand un résultat te surprend, la première chose à faire est de **regarder ce que contient vraiment la variable**.

```php
$panier = ['pomme' => 3, 'poire' => 0];
$total = '12';

var_dump($total);   // string(2) "12"  → le type et la valeur
print_r($panier);   // Array ( [pomme] => 3 [poire] => 0 )
```

- `var_dump()` affiche le **type** et la valeur (et la longueur des chaînes) : c'est l'outil le plus précis. Il montre par exemple qu'un `"12"` est une chaîne et non un nombre, ou qu'une valeur vaut `false` et non `0`.
- `print_r()` affiche un tableau ou un objet de façon lisible, mais sans les types : `false` et `null` n'y apparaissent même pas. Avec `print_r($x, true)`, il **retourne** le texte au lieu de l'afficher.
- `var_export()` affiche la valeur sous forme de code PHP valide.

Dans une page web, entoure l'affichage d'une balise `<pre>` pour garder l'indentation : `echo '<pre>'; var_dump($panier); echo '</pre>';`.

> [!astuce]
> `PHP_VERSION` contient la version de PHP qui exécute le script (par exemple `"8.3.6"`). Pratique pour vérifier qu'on utilise bien la version attendue : `echo PHP_VERSION;`.

## Lire un message d'erreur

Un message d'erreur PHP dit **quoi**, **où** et **à quelle ligne**. Il faut le lire en entier, calmement, de gauche à droite.

```text
Parse error: syntax error, unexpected token "echo" in /var/www/index.php on line 12
```

Ici, PHP a trouvé à la ligne 12 un `echo` qu'il n'attendait pas. La vraie cause est souvent juste **avant** : un point-virgule, une parenthèse ou une accolade oubliés à la ligne 11. Commence donc par la ligne indiquée, puis remonte. Le message précise parfois ce que PHP attendait : `unexpected variable "$total", expecting "," or ";"` signifie qu'il manque un point-virgule juste avant `$total`.

Les niveaux à connaître :

| Message | Signification | Le script continue ? |
|---|---|---|
| `Parse error` | Le code n'est pas du PHP valide (syntaxe) | Non, rien ne s'exécute |
| `Fatal error` | Erreur grave à l'exécution | Non, il s'arrête |
| `Warning` | Problème sérieux mais non bloquant | Oui |
| `Deprecated` | Fonctionnalité appelée à disparaître | Oui |

Deux messages très fréquents :

- `Warning: Undefined variable $age` : tu utilises une variable qui n'a jamais reçu de valeur (souvent une faute de frappe : `$age` au lieu de `$ages`). PHP la remplace par `null` et continue, ce qui donne des résultats faux plus loin.
- `Fatal error: Uncaught Error: Call to undefined function afficher()` : la fonction n'existe pas. Le nom est mal écrit, ou le fichier qui la déclare n'a pas été inclus.

### Afficher les erreurs… seulement en développement

Sur ta machine, tu veux voir toutes les erreurs. En production, elles ne doivent **jamais** apparaître dans la page : elles révèlent des chemins de fichiers et des détails internes utiles à un attaquant. On les écrit alors dans un journal (log).

```php
// en développement uniquement (en général réglé dans php.ini)
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

> [!attention]
> Pour la même raison, ne laisse jamais en ligne une page qui appelle `phpinfo()`. Elle affiche toute la configuration du serveur : version exacte de PHP, extensions, chemins, variables d'environnement… Une mine d'informations pour préparer une attaque.

## Les pièges classiques

- Oublier le `$` devant une variable : PHP croit lire une constante et lève une erreur.
- Utiliser `+` au lieu de `.` pour assembler du texte.
- Laisser un espace ou une ligne vide avant `<?php` ou après `?>` dans un fichier inclus : du texte part trop tôt vers le navigateur.
- Corriger « au hasard » sans avoir lu le message d'erreur jusqu'au bout.

> [!retenir]
> - PHP s'exécute sur le serveur ; le navigateur reçoit seulement ce que le script affiche.
> - `<?= $x ?>` est le raccourci de `<?php echo $x; ?>` ; on omet `?>` dans un fichier 100 % PHP.
> - On concatène avec `.` et chaque instruction finit par `;`.
> - `php -l` vérifie la syntaxe, `php -S localhost:8000` lance un serveur de développement.
> - `var_dump()` montre le type et la valeur : c'est le premier réflexe pour déboguer.
> - Un message d'erreur donne le fichier et la ligne : lis-le en entier et regarde aussi la ligne précédente.
