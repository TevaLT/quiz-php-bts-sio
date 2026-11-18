---
resume: Créer, modifier et supprimer des données en toute sécurité avec PDO, gérer les accès concurrents et regrouper des écritures dans une transaction.
---

> [!objectifs]
> - Écrire les opérations CRUD avec des requêtes préparées et contrôler leur résultat.
> - Placer chaque contrôle au bon endroit : validation, autorisation, contraintes de la base.
> - Détecter les conflits d'écriture (accès concurrents).
> - Utiliser une transaction pour que plusieurs écritures réussissent ou échouent ensemble.

## CRUD

Les quatre opérations de base sur des données forment le **CRUD** :

| Opération | SQL | Exemple |
|---|---|---|
| **C**reate (créer) | `INSERT` | ajouter un film |
| **R**ead (lire) | `SELECT` | afficher la fiche |
| **U**pdate (modifier) | `UPDATE` | corriger le titre |
| **D**elete (supprimer) | `DELETE` | retirer le film |

```php
// créer
$stmt = $pdo->prepare('INSERT INTO films (titre, annee, genre_id) VALUES (:titre, :annee, :genre)');
$stmt->execute(['titre' => $titre, 'annee' => $annee, 'genre' => $genreId]);
$id = (int) $pdo->lastInsertId();   // l'identifiant auto-incrémenté de la ligne créée

// modifier
$stmt = $pdo->prepare('UPDATE films SET titre = :titre WHERE id = :id');
$stmt->execute(['titre' => $titre, 'id' => $id]);

// supprimer
$stmt = $pdo->prepare('DELETE FROM films WHERE id = :id');
$stmt->execute(['id' => $id]);
```

- `lastInsertId()` retourne l'identifiant généré par le dernier `INSERT` sur **cette connexion** (sous forme de chaîne) : on s'en sert pour rediriger vers la fiche créée.
- Un titre avec apostrophe (`L'Armée des ombres`) ne demande **rien de particulier** : la requête préparée s'en charge.

> [!attention]
> `UPDATE films SET titre = :titre` **sans `WHERE`** renomme **tous** les films de la table. Relis toujours la clause `WHERE` d'un `UPDATE` ou d'un `DELETE`.

### Ne jamais écrire execute($_POST)

Passer directement `$_POST` à `execute()` envoie **ce que le client a décidé** : des clés en trop font échouer la requête, et surtout, la liste des champs modifiables n'est plus contrôlée par ton code. On construit **explicitement** le tableau, à partir des valeurs **validées** :

```php
$stmt->execute([
    'titre' => $donnees['titre'],    // valeurs issues de la validation
    'annee' => $donnees['annee'],
    'genre' => $donnees['genre_id'],
]);
```

### rowCount()

`rowCount()` donne le nombre de lignes **touchées** par un `INSERT`, `UPDATE` ou `DELETE`. Attention avec MySQL : un `UPDATE` qui ne **change** aucune valeur (le titre était déjà le bon) retourne `0`. Un `0` ne prouve donc pas que la ligne n'existe pas : pour le savoir, on la lit d'abord avec un `SELECT`.

## Chaque contrôle à sa place

Le flux complet d'une création :

1. **GET** : afficher le formulaire (avec le jeton CSRF).
2. **POST** : lire et **normaliser** les champs.
3. **Valider** les règles (formats, longueurs, existence du genre choisi).
4. **Autoriser** : jeton CSRF valide, utilisateur connecté et en droit de créer.
5. **Insérer** avec une requête préparée.
6. **Rediriger** (303) vers la fiche créée, avec un message flash.

Avant un `DELETE`, on vérifie de même : la méthode (POST), le jeton CSRF, l'identifiant (entier valide), l'existence de la ligne, et le **droit** de l'utilisateur sur **cette** ligne. Masquer le bouton aux non-administrateurs ne suffit pas : c'est le contrôle d'autorisation côté serveur qui arrête la suppression.

Un `genre_id` falsifié dans le formulaire est arrêté par la **validation** (le genre est cherché dans la liste issue de la base), et la **clé étrangère** garde le dernier mot si un cas a échappé à la validation.

### Les contraintes de la base restent utiles

Même quand PHP a tout validé, les contraintes (`UNIQUE`, `FOREIGN KEY`, `CHECK`, `NOT NULL`) protègent contre ce que PHP ne voit pas : deux requêtes **simultanées** qui passent la validation en même temps, un autre programme, un import, un bug. Quand une contrainte est violée, PDO lève une `PDOException` avec le code **SQLSTATE `23000`** (violation d'intégrité) : on peut l'attraper pour afficher un message clair, par exemple « Cette adresse e-mail est déjà utilisée ».

```php
try {
    $stmt->execute(['email' => $email, 'hash' => $hash]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $erreurs['email'] = 'Cette adresse e-mail est déjà utilisée.';
    } else {
        throw $e;   // autre problème : on ne le cache pas
    }
}
```

### Séparer les responsabilités

La classe qui exécute le SQL (un **repository**, ou dépôt) ne fait **que** cela : elle reçoit des valeurs, exécute les requêtes, retourne des données. Elle n'appelle ni `header('Location: …')`, ni `echo`, ni `$_POST` : la redirection et l'affichage sont l'affaire du **contrôleur** (chapitre 21). Elle peut alors être réutilisée par une API ou une commande, et testée seule.

### Archiver plutôt que détruire

Supprimer définitivement une ligne efface aussi son **historique** (qui a commandé quoi, quand), peut casser des références, et rend toute erreur irréversible. On préfère souvent **archiver** : une suppression logique (`UPDATE … SET supprime_le = NOW()`, chapitre 17).

## Les accès concurrents

Deux utilisateurs modifient la même fiche en même temps : le second enregistrement **écrase** le premier sans que personne ne s'en rende compte. Une colonne **`version`** permet de détecter ce conflit (verrouillage optimiste) :

```php
$stmt = $pdo->prepare(
    'UPDATE films SET titre = :titre, version = version + 1
     WHERE id = :id AND version = :version'
);
$stmt->execute(['titre' => $titre, 'id' => $id, 'version' => $versionLue]);
if ($stmt->rowCount() === 0) {
    // quelqu'un a modifié la fiche entre la lecture et l'écriture : on prévient l'utilisateur
}
```

### Lire, décider, écrire : la course

Réserver une place en trois temps (lire le nombre de places restantes, vérifier en PHP qu'il en reste, puis écrire) est **dangereux** : entre la lecture et l'écriture, une autre requête peut avoir pris la dernière place. Les deux requêtes ont lu « 1 place », les deux réservent : il y a une réservation de trop.

La solution : faire **décider la base** en une seule instruction **atomique**, avec une condition dans le `UPDATE` :

```sql
UPDATE seances SET places_restantes = places_restantes - 1
WHERE id = :id AND places_restantes > 0
```

Le SGBD garantit qu'une seule requête à la fois modifie la ligne. Si `rowCount()` vaut `0`, c'est qu'il n'y avait plus de place : on **refuse** la réservation (aucune place n'a été décrémentée). La contrainte `CHECK (places_restantes >= 0)` sert de **filet de sécurité** : même un code erroné ne pourra jamais rendre le compteur négatif.

Autre solution : `SELECT … FOR UPDATE`, dans une transaction, **verrouille** les lignes lues jusqu'à la fin de la transaction ; les autres transactions qui veulent les modifier attendent.

## Les transactions

Une **transaction** regroupe plusieurs requêtes qui doivent réussir **toutes ensemble**, ou **aucune**. Réserver, c'est décrémenter les places **et** insérer la réservation : une place décrémentée sans réservation (ou l'inverse) serait un état incohérent.

```php
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE seances SET places_restantes = places_restantes - 1 WHERE id = :id AND places_restantes > 0');
    $stmt->execute(['id' => $seanceId]);
    if ($stmt->rowCount() === 0) {
        throw new PlusDePlaceException("Séance $seanceId complète");
    }

    $stmt = $pdo->prepare('INSERT INTO reservations (seance_id, utilisateur_id) VALUES (:seance, :utilisateur)');
    $stmt->execute(['seance' => $seanceId, 'utilisateur' => $utilisateurId]);

    $pdo->commit();      // tout est validé d'un coup
} catch (Throwable $e) {
    $pdo->rollBack();    // tout est annulé : la base revient à l'état d'avant beginTransaction()
    throw $e;            // on relance : l'appelant doit savoir que l'opération a échoué
}
```

- `commit()` valide définitivement toutes les modifications de la transaction.
- `rollBack()` les **annule toutes**. Si l'`INSERT` échoue (une contrainte `UNIQUE` refuse un doublon de réservation), le décrément des places, déjà exécuté, est **annulé aussi** : le compteur retrouve sa valeur.
- On **relance** l'exception après le `rollBack()` : sinon l'erreur serait avalée et le code appelant croirait que la réservation a réussi.

Une transaction seule ne corrige pas l'algorithme « lire, décider, écrire » : il faut aussi la mise à jour conditionnelle (ou `FOR UPDATE`) pour empêcher deux requêtes de lire la même valeur.

### ACID

Les transactions d'un SGBD garantissent quatre propriétés :

- **Atomicité** : tout ou rien ; aucune transaction n'est appliquée à moitié.
- **Cohérence** : la base passe d'un état valide à un autre état valide (les contraintes sont respectées).
- **Isolation** : les transactions simultanées ne voient pas les états intermédiaires les unes des autres.
- **Durabilité** : une fois le `commit()` fait, les données survivent à une panne.

On parle aussi d'**invariant** : une règle qui doit **toujours** rester vraie (« les places restantes égalent la capacité moins le nombre de réservations »). Les états intermédiaires qui la violent ne doivent jamais être visibles ni persister : c'est le rôle de la transaction.

On utilise une transaction dès que **plusieurs écritures dépendent les unes des autres** : une commande et ses lignes, un virement (débit et crédit), une réservation et son compteur.

### Journaliser et tester

Le journal d'une transaction indique l'**étape** (début, commit, rollback) et les **identifiants** utiles (séance, utilisateur) : `error_log('[reservation] ROLLBACK seance=3 utilisateur=7')`. Jamais de mot de passe ni de donnée sensible.

Pour tester la gestion d'erreur, on provoque l'échec de la **seconde** écriture et on vérifie que la **première a été annulée** (le compteur de places est inchangé). Pour une séance complète, le résultat observable est un **refus explicite**, **aucune** réservation insérée et un compteur **toujours à 0**.

## Les pièges classiques

- Un `UPDATE` ou un `DELETE` sans `WHERE`.
- `execute($_POST)` au lieu d'un tableau construit à partir des valeurs validées.
- Interpréter `rowCount() === 0` comme « la ligne n'existe pas ».
- « Lire, décider en PHP, écrire » sur une ressource partagée.
- Un `catch` qui fait `rollBack()` sans relancer l'exception.

> [!retenir]
> - CRUD = `INSERT`, `SELECT`, `UPDATE`, `DELETE`, toujours en requêtes préparées ; `lastInsertId()` après un `INSERT`.
> - Les valeurs passées à `execute()` viennent de la validation, jamais directement de `$_POST`.
> - Validation, autorisation (CSRF + droits) puis écriture ; les contraintes de la base gardent le dernier mot (SQLSTATE 23000).
> - Contre les conflits : colonne `version`, ou `UPDATE … WHERE places_restantes > 0` puis `rowCount()`.
> - Transaction : `beginTransaction()`, `commit()`, et en cas d'erreur `rollBack()` puis `throw`.
> - ACID : atomicité, cohérence, isolation, durabilité ; une transaction protège les invariants.
