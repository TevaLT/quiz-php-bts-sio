---
resume: Traiter un formulaire de bout en bout : normaliser, valider côté serveur, réafficher les erreurs, échapper à l'affichage et rediriger après l'envoi (Post/Redirect/Get).
---

> [!objectifs]
> - Distinguer normaliser, valider et échapper, et savoir à quel moment faire chaque opération.
> - Valider une saisie avec `filter_var()`, des bornes et des listes blanches.
> - Réafficher le formulaire avec les erreurs et les valeurs déjà saisies.
> - Appliquer le motif Post/Redirect/Get et comprendre l'erreur « headers already sent ».

## Un formulaire bien construit

```html
<form action="/inscription" method="post" novalidate>
    <label for="nom">Nom</label>
    <input id="nom" name="nom" type="text" required maxlength="60">

    <label for="age">Âge</label>
    <input id="age" name="age" type="number" min="15" max="120">

    <label for="formule">Formule</label>
    <select id="formule" name="formule">
        <option value="decouverte">Découverte</option>
        <option value="annuelle">Annuelle</option>
    </select>

    <button type="submit">S'inscrire</button>
</form>
```

- `method="post"`, car l'envoi **crée** quelque chose (chapitre 12).
- Chaque champ a un `name` (la clé reçue côté serveur) et un `<label>` relié par `for`/`id` : cliquer sur le libellé active le champ, et un lecteur d'écran l'annonce.
- `required`, `min`, `maxlength` aident l'utilisateur, mais le serveur **revérifie tout**.

Côté serveur, **toutes** les valeurs arrivent sous forme de **chaînes** : même un `<input type="number">` donne `"42"`, pas `42`. Et un champ peut arriver sous forme de tableau si quelqu'un envoie `nom[]=x` : sans contrôle de type, `trim()` ou `mb_strlen()` lèvent une `TypeError`.

## Normaliser, valider, échapper

Trois opérations différentes, à trois moments différents :

| Opération | Rôle | Quand | Exemple |
|---|---|---|---|
| **Normaliser** | mettre la saisie sous une forme standard | à la réception | `trim()`, `mb_strtolower()` sur un e-mail |
| **Valider** | vérifier que la valeur respecte les règles, sinon la refuser | à la réception | longueur, plage, format, liste autorisée |
| **Échapper** | rendre la valeur inoffensive pour **un contexte** de sortie | à l'affichage | `htmlspecialchars()` dans du HTML |

Valider ne transforme pas : on accepte ou on refuse, avec un message. Échapper ne refuse rien : on transforme les caractères spéciaux pour qu'ils soient affichés au lieu d'être interprétés.

## Valider côté serveur

```php
$erreurs = [];

$nom = $_POST['nom'] ?? '';
$nom = is_string($nom) ? trim($nom) : '';
if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60) {
    $erreurs['nom'] = 'Le nom doit contenir de 2 à 60 caractères.';
}

$age = filter_var($_POST['age'] ?? '', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 15, 'max_range' => 120],
]);
if ($age === false) {
    $erreurs['age'] = "L'âge doit être un nombre entier entre 15 et 120.";
}

$formule = $_POST['formule'] ?? '';
if (!in_array($formule, ['decouverte', 'annuelle'], true)) {
    $erreurs['formule'] = 'Formule inconnue.';
}
```

- On stocke les erreurs dans un **tableau associatif** `champ => message` : on peut afficher chaque message à côté de son champ, et `$erreurs === []` signifie « tout est valide ».
- `filter_var($valeur, FILTER_VALIDATE_INT)` retourne l'entier, ou `false` si la valeur n'en est pas un (`filter_var('abc', FILTER_VALIDATE_INT)` donne `false`). Avec les options `min_range` et `max_range` (comme dans le code ci-dessus), une valeur hors bornes donne aussi `false` : `42` est refusé si la plage va de 1 à 10.
- On teste `=== false` et non `!$age` : un âge validé peut légitimement valoir `0` dans d'autres règles, et `0` est *falsy*.
- Une valeur qui doit appartenir à un ensemble fermé (une formule, une taille, un ordre de tri) se vérifie avec une **liste blanche** : `in_array($valeur, $autorisees, true)`. C'est la validation la plus sûre qui soit.

`FILTER_VALIDATE_EMAIL` vérifie seulement le **format** d'une adresse, pas qu'elle existe ni qu'elle appartient à l'utilisateur. Pour cela, il faut envoyer un e-mail de confirmation contenant un lien.

### Tester les bornes

Une règle « de 2 à 60 caractères » se vérifie aux **frontières** : 1 (refusé), 2 (accepté), 60 (accepté), 61 (refusé). De même, « âge entre 15 et 120 » se teste avec 14, 15, 120 et 121. Les erreurs se cachent presque toujours aux frontières (un `<` à la place d'un `<=`).

## Réafficher le formulaire en cas d'erreur

Quand une validation échoue, on **réaffiche le formulaire** : chaque champ avec la valeur saisie (pour ne pas faire tout retaper), et le message d'erreur à côté du champ concerné. Les valeurs **valides** des autres champs sont conservées.

```php
<label for="nom">Nom</label>
<input id="nom" name="nom" value="<?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>"
       <?= isset($erreurs['nom']) ? 'aria-invalid="true" aria-describedby="erreur-nom"' : '' ?>>
<?php if (isset($erreurs['nom'])): ?>
    <p id="erreur-nom" class="erreur"><?= htmlspecialchars($erreurs['nom']) ?></p>
<?php endif; ?>
```

La valeur réaffichée vient de l'utilisateur : elle est **échappée**, comme toute donnée insérée dans du HTML. On peut aussi renvoyer le code `422` pour signaler des données invalides.

## Échapper à l'affichage

`htmlspecialchars()` transforme les caractères qui ont un sens en HTML (`<`, `>`, `&`, `"`, `'`) en entités (`&lt;`, `&gt;`…). Une saisie comme `<script>…</script>` s'affiche alors comme du texte au lieu de s'exécuter.

```php
echo htmlspecialchars($commentaire, ENT_QUOTES, 'UTF-8');
```

`ENT_QUOTES` échappe **aussi les apostrophes** (les guillemets doubles le sont déjà), indispensable quand la valeur est placée dans un attribut délimité par `'`. C'est le comportement par défaut depuis PHP 8.1, mais l'écrire rend l'intention explicite. Dans un gabarit, on crée souvent un raccourci : `function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }`.

Pourquoi échapper **à la sortie** et pas avant d'enregistrer ? Parce que l'échappement dépend du **contexte** : une même donnée peut finir dans une page HTML, un JSON, un CSV, un e-mail texte. Une donnée échappée pour le HTML et stockée ainsi serait fausse partout ailleurs (et échappée deux fois à l'affichage). On stocke la donnée **brute**, et on l'échappe **au moment de l'écrire**, selon l'endroit.

Pour une requête SQL, l'échappement n'est pas le bon outil : on utilise des **requêtes préparées** (chapitre 18).

## Post/Redirect/Get

Si la page qui traite un POST affiche directement un résultat, un rafraîchissement (F5) **renvoie le formulaire** : double inscription, double commande. Le motif **Post/Redirect/Get** (PRG) l'évite : on traite le POST, on **redirige**, et c'est la page suivante, en GET, qui s'affiche.

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // … validation …
    if ($erreurs === []) {
        enregistrerInscription($nom, $age, $formule);
        header('Location: /inscription/merci', true, 303);
        exit;
    }
    // sinon : on continue et on réaffiche le formulaire avec les erreurs
}
```

- Le code **303 See Other** indique explicitement au navigateur de suivre la redirection avec un **GET**.
- `exit;` juste après `header('Location: …')` est indispensable : `header()` ne fait qu'ajouter un en-tête, **le script continue** sinon, et le code qui suit (un affichage, un autre traitement) s'exécuterait.
- Pour annoncer « Inscription enregistrée » sur la page suivante, on passe par un **message flash** en session (chapitre 14).

Le PRG évite le renvoi au rafraîchissement, mais pas deux clics très rapides : une opération qui doit être unique se protège aussi côté serveur (contrainte d'unicité en base, jeton à usage unique).

### « Cannot modify header information - headers already sent »

Les en-têtes HTTP partent **avant** le corps de la réponse. Dès que le script a envoyé du contenu (un `echo`, du HTML, un espace ou une ligne vide avant `<?php`, un `?>` suivi d'un saut de ligne dans un fichier inclus), il est trop tard pour `header()`, `setcookie()` ou `session_start()`. Le message indique le fichier et la ligne où la sortie a commencé : c'est là qu'il faut regarder.

## Listes, filtres et pagination

Une page de liste avec recherche, tri et pagination applique les mêmes principes :

- l'**ordre de tri** (`?tri=prix`) est choisi dans une **liste blanche** : la valeur reçue n'est jamais insérée telle quelle dans une requête SQL (un `ORDER BY` ne peut pas être paramétré) ;
- les **liens de pagination** conservent les critères de recherche : `http_build_query()` reconstruit la chaîne de requête proprement encodée ;
- quand aucun élément ne correspond, la page affiche un **message explicite** (« Aucun film ne correspond à ta recherche »), pas une liste vide sans explication.

```php
$tris = ['titre' => 'titre ASC', 'prix' => 'prix ASC', 'recent' => 'annee DESC'];
$tri = $_GET['tri'] ?? 'titre';
$ordreSql = $tris[$tri] ?? $tris['titre'];   // jamais la valeur brute

$lienPageSuivante = '?' . http_build_query(['q' => $q, 'tri' => $tri, 'page' => $page + 1]);
```

> [!attention]
> Un champ caché comme `<input type="hidden" name="utilisateur_id" value="12">` est modifiable par l'utilisateur, qui peut réserver au nom de quelqu'un d'autre. L'identité de l'utilisateur vient toujours de la **session**, côté serveur, jamais du formulaire.

## Les pièges classiques

- Ne valider qu'en HTML ou en JavaScript.
- Tester `if (!$age)` au lieu de `=== false`.
- Effacer toutes les saisies quand un seul champ est faux.
- Échapper avant d'enregistrer en base, ou oublier d'échapper à l'affichage.
- Oublier `exit` après une redirection, ou afficher quelque chose avant `header()`.

> [!retenir]
> - Normaliser (`trim`) et valider à la réception ; échapper (`htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`) à l'affichage.
> - `filter_var()` retourne `false` en cas d'échec : teste `=== false`. Les listes blanches valident les ensembles fermés.
> - Les erreurs vont dans un tableau `champ => message` ; on réaffiche le formulaire avec les valeurs échappées.
> - Post/Redirect/Get : `header('Location: …', true, 303); exit;` après un POST réussi.
> - « Headers already sent » : quelque chose a été affiché avant `header()`.
