---
resume: Répéter des instructions avec while, do while, for et foreach, contrôler une boucle avec break et continue, et éviter les boucles infinies.
---

> [!objectifs]
> - Choisir la bonne boucle : `while`, `do … while`, `for` ou `foreach`.
> - Parcourir un tableau avec ses clés et ses valeurs.
> - Contrôler le déroulement avec `break` et `continue`.
> - Repérer et éviter les erreurs classiques : boucle infinie, décalage d'une unité, référence oubliée.

## while : tant que…

`while` répète un bloc **tant que** sa condition est vraie. La condition est testée **avant** chaque tour : si elle est fausse dès le départ, le corps ne s'exécute **jamais**.

```php
$capital = 1000;
$annees = 0;
while ($capital < 2000) {
    $capital *= 1.05;   // 5 % d'intérêts par an
    $annees++;
}
echo "Capital doublé en $annees ans";   // 15 ans
```

On utilise `while` quand on **ne connaît pas à l'avance** le nombre de tours : attendre qu'un calcul atteigne un seuil, lire un fichier jusqu'à la fin, interroger un utilisateur jusqu'à obtenir une réponse valide.

> [!attention]
> La cause la plus fréquente d'une **boucle infinie** : la condition ne devient jamais fausse, parce que la variable testée n'est pas modifiée dans le corps (un `$i++` oublié, par exemple). Le script tourne alors jusqu'à la limite `max_execution_time`.

## do … while : au moins une fois

`do … while` teste la condition **après** le corps : le bloc s'exécute **au moins une fois**, même si la condition est fausse.

```php
do {
    $de = random_int(1, 6);
    echo "Lancer : $de\n";
} while ($de !== 6);   // on relance tant qu'on n'a pas fait 6
```

C'est la boucle idéale quand il faut agir avant de pouvoir tester : lancer un dé, demander une saisie puis la vérifier.

## for : un nombre de tours connu

`for` regroupe sur une ligne les trois éléments d'une boucle à compteur :

```php
for ($i = 1; $i <= 5; $i++) {
    echo $i;   // 12345
}
```

L'ordre d'exécution est le suivant :

1. l'**initialisation** (`$i = 1`) une seule fois, au départ ;
2. la **condition** (`$i <= 5`), avant chaque tour : si elle est fausse, la boucle s'arrête ;
3. le **corps** de la boucle ;
4. l'**évolution** (`$i++`), après chaque tour, puis retour à l'étape 2.

On peut compter à rebours : `for ($compteur = 5; $compteur >= 1; $compteur--)` affiche `54321`.

Après la boucle, la variable du compteur existe toujours et vaut la **première valeur qui a rendu la condition fausse** :

```php
for ($i = 0; $i < 3; $i++) {
    echo $i;
}
echo $i;   // affiche au total « 0123 » : $i vaut 3 en sortie de boucle
```

### L'erreur de décalage d'une unité

Le *off-by-one* consiste à faire **un tour de trop ou de moins**, en se trompant entre `<` et `<=`, ou en partant de 1 au lieu de 0. Un tableau de 5 éléments a des indices de 0 à 4 : `for ($i = 0; $i <= count($t); $i++)` lit une case `$t[5]` qui n'existe pas.

```php
$t = ['a', 'b', 'c', 'd', 'e'];
for ($i = 0; $i < count($t); $i++) {   // < et non <=
    echo $t[$i];
}
```

> [!astuce]
> Pour vérifier une boucle, teste mentalement le **premier** et le **dernier** tour : quelle valeur a le compteur, la condition est-elle vraie, que se passe-t-il au tour suivant ?

`range()` crée la liste des valeurs d'une suite, pratique avec `foreach` : `range(1, 5)` donne `[1, 2, 3, 4, 5]` et `range(0, 10, 5)` donne `[0, 5, 10]` (le troisième argument est le pas).

## foreach : parcourir un tableau

`foreach` est la boucle la plus utilisée en PHP : elle parcourt chaque élément d'un tableau, sans compteur à gérer et sans risque de décalage.

```php
$notes = ['Ada' => 15, 'Linus' => 12, 'Grace' => 18];

foreach ($notes as $note) {
    echo $note, ' ';             // 15 12 18
}

foreach ($notes as $prenom => $note) {
    echo "$prenom : $note\n";    // la clé ET la valeur
}
```

Dans `foreach ($films as $cle => $film)`, `$cle` contient la **clé** de l'élément courant : l'indice (0, 1, 2…) pour une liste, le nom de la clé pour un tableau associatif. Sur un tableau vide, le corps ne s'exécute **aucune fois**, sans erreur.

### Modifier les éléments pendant le parcours

`foreach` travaille sur une **copie** de chaque valeur : modifier `$prix` dans la boucle ne change pas le tableau.

```php
$prix = [10, 20, 30];
foreach ($prix as $p) {
    $p *= 2;              // ne modifie que la copie
}
// $prix vaut toujours [10, 20, 30]

foreach ($prix as $cle => $p) {
    $prix[$cle] = $p * 2; // on passe par la clé : [20, 40, 60]
}
```

On peut aussi parcourir **par référence** avec `&$p`, mais c'est risqué : après la boucle, `$p` reste lié au dernier élément.

```php
$t = [1, 2, 3];
foreach ($t as &$v) {}
foreach ($t as $v) {}      // réécrit le dernier élément à chaque tour !
echo implode(',', $t);     // 1,2,2
```

> [!attention]
> Après un `foreach` par référence, fais toujours `unset($v);` pour casser le lien. Ou évite la référence et passe par la clé, comme ci-dessus.

### Afficher une liste dans un gabarit

Avec la syntaxe alternative, une boucle reste lisible au milieu du HTML :

```php
<ul>
    <?php foreach ($articles as $article): ?>
        <li><?= htmlspecialchars($article['titre']) ?></li>
    <?php endforeach; ?>
</ul>
```

`htmlspecialchars()` protège l'affichage contre l'injection de HTML : on y revient au chapitre 15.

## break et continue

- `break` **sort** immédiatement de la boucle.
- `continue` **passe au tour suivant**, sans exécuter la fin du corps.

```php
foreach ($commandes as $commande) {
    if ($commande['annulee']) {
        continue;            // on ignore cette commande, on passe à la suivante
    }
    if ($commande['montant'] > 1000) {
        echo 'Commande à vérifier trouvée';
        break;               // inutile de chercher plus loin
    }
}
```

Dans des boucles imbriquées, `break 2;` sort des **deux** boucles d'un coup (et `continue 2;` passe au tour suivant de la boucle extérieure).

## Mémoriser pendant une boucle

Beaucoup d'algorithmes accumulent un résultat au fil des tours : une somme, un maximum, un compteur. La variable d'accumulation s'initialise **avant** la boucle.

```php
$total = 0;
$maximum = null;
foreach ($ventes as $vente) {
    $total += $vente;
    if ($maximum === null || $vente > $maximum) {
        $maximum = $vente;
    }
}
```

Pour savoir si une valeur a **déjà été rencontrée**, on garde les valeurs vues comme **clés** d'un tableau : le test `isset()` est alors immédiat, même sur une longue liste.

```php
$vus = [];
foreach ($emails as $email) {
    if (isset($vus[$email])) {
        echo "Doublon : $email\n";
    }
    $vus[$email] = true;
}
```

## Les pièges classiques

- Oublier de faire évoluer la variable de la condition : boucle infinie.
- Se tromper entre `<` et `<=` : un tour de trop ou de moins.
- Initialiser l'accumulateur **dans** la boucle : il repart de zéro à chaque tour.
- Modifier `$valeur` dans un `foreach` en croyant changer le tableau.
- Laisser traîner une référence après un `foreach (… as &$v)`.

> [!retenir]
> - `while` teste avant le tour (0 tour possible), `do … while` après (au moins 1 tour).
> - `for (init; condition; évolution)` : init une fois, condition avant chaque tour, évolution après chaque tour.
> - `foreach ($t as $cle => $valeur)` parcourt un tableau ; la valeur est une copie.
> - `break` sort de la boucle, `continue` passe au tour suivant ; `break 2` sort de deux boucles.
> - Une boucle infinie vient d'une condition qui ne devient jamais fausse.
> - Teste toujours le premier et le dernier tour pour éviter le décalage d'une unité.
