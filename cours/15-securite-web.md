---
resume: Protéger une application web contre les attaques courantes (XSS, CSRF, accès non autorisés, inclusion de fichiers) et stocker correctement les mots de passe.
---

> [!objectifs]
> - Reconnaître les attaques XSS et CSRF et appliquer leurs parades.
> - Distinguer authentification, autorisation, validation et échappement.
> - Hacher et vérifier un mot de passe avec `password_hash()` et `password_verify()`.
> - Limiter la surface d'attaque : dossier public, journaux, listes blanches.

## Le principe de base

**Toute donnée qui vient de l'extérieur est suspecte** : formulaire, URL, cookies, en-têtes, fichiers envoyés, et même le contenu de ta base de données, qui a été saisi un jour par quelqu'un. La sécurité ne repose jamais sur une seule barrière : on empile plusieurs protections (la **défense en profondeur**).

| Menace | Défense principale |
|---|---|
| XSS (injection de script) | échapper à l'affichage (`htmlspecialchars`), politique CSP |
| Injection SQL | requêtes préparées (chapitre 18) |
| CSRF (requête forgée) | jeton CSRF, actions en POST, cookies `SameSite` |
| Accès à la ressource d'un autre | contrôle d'autorisation côté serveur |
| Vol de mots de passe | `password_hash()`, HTTPS |
| Force brute | limiter les tentatives de connexion |
| Inclusion de fichier arbitraire | liste blanche des fichiers |

## XSS : l'injection de script

Une faille **XSS** (*Cross-Site Scripting*) permet d'injecter du HTML ou du JavaScript dans une page vue par d'autres. Si quelqu'un saisit `<img src=x onerror=alert(1)>` comme nom et que la page l'affiche tel quel, le navigateur des visiteurs **exécute** le script : il peut lire la page, agir au nom de l'utilisateur, envoyer ses données ailleurs.

La parade : **échapper à l'affichage**, dans le contexte HTML, **toutes** les données dynamiques.

```php
<p>Bonjour <?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?></p>
```

Le nom s'affiche alors littéralement, `<img src=x onerror=alert(1)>`, sans être interprété. Il faut aussi échapper les données qui viennent **de ta propre base** : elles ont pu y être enregistrées par un attaquant (XSS « stockée »).

En complément, l'en-tête **Content-Security-Policy** (CSP) indique au navigateur quels scripts la page a le droit d'exécuter. Avec `Content-Security-Policy: script-src 'self'`, les scripts écrits directement dans la page sont bloqués : même si un échappement est oublié, le script injecté ne s'exécute pas.

## CSRF : la requête forgée

Une attaque **CSRF** (*Cross-Site Request Forgery*) fait exécuter une action à un utilisateur **connecté**, à son insu, depuis un autre site. Une page piégée contient un formulaire invisible qui envoie un POST vers ton site ; le navigateur y joint automatiquement les cookies de session de la victime, et ton site croit que la demande vient d'elle.

La parade principale est le **jeton CSRF** : une valeur aléatoire, gardée en session et ajoutée à chaque formulaire. L'autre site ne peut pas la connaître.

```php
// création (une fois par session)
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
```

```php
<!-- dans chaque formulaire -->
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
```

```php
// vérification avant toute action
$jeton = $_POST['csrf'] ?? null;
if (!is_string($jeton) || !hash_equals($_SESSION['csrf'], $jeton)) {
    http_response_code(403);
    exit('Requête refusée.');
}
```

- `random_bytes()` utilise un générateur **cryptographiquement sûr** ; `rand()` et `mt_rand()` sont **prévisibles** et ne conviennent pas pour un secret.
- `hash_equals()` compare en **temps constant** : avec `===`, la comparaison s'arrête au premier caractère différent, et la durée de la réponse peut renseigner un attaquant.
- On vérifie d'abord que la valeur reçue est une **chaîne** (elle pourrait être un tableau, et `hash_equals()` lèverait une `TypeError`).
- Un jeton absent ou faux donne un **403 Forbidden** : la requête est comprise mais refusée.

Les actions qui modifient des données passent par **POST** : un simple lien GET « Supprimer » serait déclenchable par une image `<img src="/supprimer?id=3">` sur n'importe quel site. Le cookie `SameSite=Lax` (chapitre 14) ajoute une barrière supplémentaire.

## Authentification et autorisation

Deux questions différentes, à ne pas confondre :

- l'**authentification** répond à « **qui es-tu ?** » : le serveur vérifie une preuve (le mot de passe comparé à son condensat) ;
- l'**autorisation** répond à « **as-tu le droit de faire ça ?** » : le serveur vérifie, pour **chaque action**, que l'utilisateur connecté a ce droit sur **cette ressource**.

S'ajoutent la **validation** (la donnée respecte-t-elle les règles ?) et l'**échappement** (la donnée est-elle inoffensive à l'affichage ?).

Le statut `401 Unauthorized` signifie « non authentifié » (il faut se connecter) ; `403 Forbidden` signifie « authentifié, mais pas autorisé ».

### L'autorisation se vérifie côté serveur, toujours

- **Masquer un bouton** « Supprimer » aux non-administrateurs ne protège rien : n'importe qui peut envoyer la requête directement. L'action elle-même vérifie le droit.
- Le **rôle** ne vient jamais du client : un champ `role=admin` dans un formulaire, un cookie ou l'URL ne doit accorder aucun droit. La session ne contient que l'identifiant de l'utilisateur, et son rôle est **relu en base** lors de chaque contrôle sensible (il a pu changer depuis la connexion).
- Un jeton CSRF valide prouve que la requête vient de ton site, pas que l'utilisateur a le droit de faire l'action : les deux contrôles sont nécessaires.

> [!attention]
> Si un membre peut voir la réservation d'un autre en changeant `?id=12` en `?id=13` dans l'URL, c'est une faille de **référence directe non contrôlée** (IDOR). La parade : vérifier que la ressource appartient à l'utilisateur connecté, par exemple avec `WHERE id = :id AND utilisateur_id = :utilisateur` dans la requête SQL.

## Stocker les mots de passe

Un mot de passe ne se stocke **jamais en clair**, ni **chiffré** : on stocke un **hachage** (un condensat).

- Le **chiffrement** est réversible : avec la clé, on retrouve le texte. Si la clé fuite, tous les mots de passe aussi.
- Le **hachage** est à **sens unique** : on ne peut pas retrouver le mot de passe ; on peut seulement vérifier qu'un mot de passe saisi donne le même condensat.

```php
// à l'inscription
$hash = password_hash($motDePasse, PASSWORD_DEFAULT);   // à stocker en base

// à la connexion
if (password_verify($saisie, $hash)) {
    // mot de passe correct
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        // l'algorithme ou son coût ont évolué : on recalcule et on met à jour la base
    }
}
```

- `password_hash()` ajoute un **sel** aléatoire : deux appels avec le même mot de passe donnent **deux condensats différents**. Deux utilisateurs avec le même mot de passe n'ont donc pas le même condensat.
- `password_verify()` retrouve le sel et l'algorithme dans le condensat, et compare correctement.
- `md5()` et `sha1()` sont des fonctions **rapides**, conçues pour vérifier l'intégrité de fichiers : un attaquant peut en tester des milliards par seconde. `password_hash()` (bcrypt par défaut) est volontairement **lent** et réglable.
- La colonne qui reçoit le condensat est un `VARCHAR(255)` : un condensat bcrypt fait 60 caractères aujourd'hui, mais `PASSWORD_DEFAULT` peut évoluer vers un algorithme qui produit des condensats plus longs.

### Le formulaire de connexion

- Affiche **le même message** pour un e-mail inconnu et un mot de passe faux (« Identifiants incorrects ») : sinon, un attaquant peut découvrir quelles adresses ont un compte.
- **Limite les tentatives** (par compte et par adresse IP, avec un délai croissant) pour freiner les attaques par **force brute**.
- Pour la politique de mots de passe, la **longueur** est l'argument solide (au moins 12 caractères, par exemple) ; empiler des règles arbitraires (une majuscule, un chiffre, un symbole) pousse à des mots de passe prévisibles comme `Motdepasse1!`.

## Réduire la surface d'attaque

### Seul le dossier public est exposé

Le serveur web ne doit servir **que** le dossier `public/` (le contrôleur frontal, les CSS, les images). Le code (`src/`), la configuration, les mots de passe de la base et les données restent **en dehors**, impossibles à télécharger. C'est pourquoi on lance le serveur de développement avec `php -S localhost:8000 -t public`.

### Pas de fichier choisi par l'utilisateur

```php
include 'pages/' . $_GET['page'] . '.php';   // DANGEREUX
```

Avec `?page=../../config/secrets`, l'utilisateur fait inclure un fichier de son choix (**traversée de répertoire**). Le nom de la page doit venir d'une **liste blanche** :

```php
$page = match ($_GET['page'] ?? 'accueil') {
    'accueil' => 'accueil',
    'contact' => 'contact',
    default => 'introuvable',
};
include __DIR__ . '/pages/' . $page . '.php';
```

### Des journaux utiles et sans secret

Un journal d'actions contient la **date**, l'**action** et l'**identifiant** de l'utilisateur concerné. Il ne contient **jamais** de mot de passe (même dans un message d'erreur), de jeton ou de donnée sensible : les journaux sont lus par beaucoup de monde et conservés longtemps.

## Les pièges classiques

- Échapper « la plupart » des affichages : un seul oubli suffit pour une faille XSS.
- Utiliser `rand()` pour un jeton, ou `===` pour le comparer.
- Contrôler les droits dans l'interface (boutons masqués) mais pas dans l'action.
- Stocker les mots de passe avec `md5()` ou les chiffrer.
- Dire « mot de passe incorrect » pour un compte qui existe et « compte inconnu » sinon.

> [!retenir]
> - XSS : `htmlspecialchars()` sur toute donnée affichée, même venant de la base ; CSP en renfort.
> - CSRF : jeton `bin2hex(random_bytes(32))` en session, vérifié avec `hash_equals()` ; 403 sinon ; actions en POST.
> - Authentification = qui es-tu ; autorisation = as-tu le droit, vérifiée côté serveur à chaque action (401 vs 403).
> - Mots de passe : `password_hash()` / `password_verify()` / `password_needs_rehash()`, colonne `VARCHAR(255)`.
> - Message de connexion identique, tentatives limitées, longueur plutôt que complexité.
> - Seul `public/` est exposé ; les fichiers inclus viennent d'une liste blanche ; aucun secret dans les journaux.
