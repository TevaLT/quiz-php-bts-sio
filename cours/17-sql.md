---
resume: Concevoir des tables reliées par des clés, protéger les données par des contraintes et écrire des requêtes SELECT avec jointures, regroupements et tris.
---

> [!objectifs]
> - Concevoir des tables avec des types adaptés, une clé primaire et des clés étrangères.
> - Représenter les relations un-à-plusieurs et plusieurs-à-plusieurs.
> - Utiliser les contraintes (`NOT NULL`, `UNIQUE`, `CHECK`) pour garantir la qualité des données.
> - Écrire des requêtes `SELECT` avec filtres, jointures, regroupements et tris, en maîtrisant `NULL`.

## Le modèle relationnel

Une base de données relationnelle range les données dans des **tables**. Chaque **ligne** est un enregistrement, chaque **colonne** une information de type défini. Chaque table a une **clé primaire** (`PRIMARY KEY`) qui identifie chaque ligne de façon unique, souvent un entier auto-incrémenté.

```sql
CREATE TABLE genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE films (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    sortie DATE NULL,
    note DECIMAL(3, 1) NULL CHECK (note BETWEEN 0 AND 10),
    genre_id INT NOT NULL,
    supprime_le DATETIME NULL,
    FOREIGN KEY (genre_id) REFERENCES genres (id)
);
```

### Choisir les types

| Donnée | Type | Remarque |
|---|---|---|
| Identifiant, quantité | `INT` | entier |
| Texte court | `VARCHAR(n)` | longueur maximale `n` |
| Texte long | `TEXT` | description, commentaire |
| Montant, note exacte (8,5) | `DECIMAL(p, s)` | valeur décimale **exacte** ; `FLOAT` est approximatif |
| Date seule | `DATE` | `2026-11-18` |
| Date et heure | `DATETIME` | `2026-11-18 14:05:00` |
| Vrai / faux | `BOOLEAN` (`TINYINT(1)` en MySQL) | |

`DECIMAL(3, 1)` accepte 3 chiffres dont 1 après la virgule : de `-99.9` à `99.9`, assez pour une note comme `8.5`.

## Relations et clés étrangères

Une **clé étrangère** (`FOREIGN KEY`) relie une colonne à la clé primaire d'une autre table. Elle garantit l'**intégrité référentielle** : impossible d'insérer un film avec un `genre_id` qui n'existe pas, ni (par défaut) de supprimer un genre encore utilisé par des films.

- Relation **un-à-plusieurs** (un genre, plusieurs films) : la clé étrangère se place du côté « plusieurs » (`films.genre_id`).
- Relation **plusieurs-à-plusieurs** (un film a plusieurs réalisateurs, un réalisateur plusieurs films) : on crée une **table d'association** qui contient les deux clés étrangères.

```sql
CREATE TABLE film_realisateur (
    film_id INT NOT NULL,
    realisateur_id INT NOT NULL,
    PRIMARY KEY (film_id, realisateur_id),          -- clé primaire composée
    FOREIGN KEY (film_id) REFERENCES films (id),
    FOREIGN KEY (realisateur_id) REFERENCES realisateurs (id)
);
```

La clé primaire **composée** `(film_id, realisateur_id)` interdit d'associer deux fois le même réalisateur au même film. C'est la bonne clé pour une table comme `favoris (utilisateur_id, film_id)`.

## Les contraintes protègent les données

- `NOT NULL` : la valeur est obligatoire.
- `UNIQUE` : pas deux fois la même valeur. Une adresse e-mail n'apparaît qu'une fois dans `utilisateurs` grâce à `email VARCHAR(255) NOT NULL UNIQUE`. Sur plusieurs colonnes, `UNIQUE (etudiant_id, cours_id)` garantit qu'un étudiant n'est pas inscrit deux fois au même cours (mais il peut suivre plusieurs cours).
- `CHECK` : une condition sur la valeur. Un `INSERT` qui viole `CHECK (note BETWEEN 0 AND 10)` est **refusé** avec une erreur.
- `FOREIGN KEY` : la valeur doit exister dans la table référencée.

Ces contraintes restent indispensables même si PHP valide déjà les données : elles protègent aussi contre les autres programmes, les imports, les erreurs de code et les accès concurrents. La base a toujours le dernier mot.

### Normaliser sans dogmatisme

La **normalisation** vise une **source unique de vérité** : chaque information est stockée **une seule fois**. Le nom d'un genre est dans `genres`, et les films y font référence par `genre_id` ; si on renomme le genre, tous les films suivent. Dupliquer le nom dans chaque film obligerait à tout mettre à jour, avec un risque d'incohérence. Pousser la normalisation à l'extrême (une table pour chaque petite information) nuit en revanche à la lisibilité et aux performances : on normalise **avec bon sens**.

### Supprimer… sans effacer

Une **suppression logique** (*soft delete*) consiste à renseigner une colonne `supprime_le` au lieu d'effacer la ligne. Les requêtes courantes ajoutent `WHERE supprime_le IS NULL` ; la ligne reste disponible pour l'historique, l'audit ou une restauration.

## NULL : l'absence de valeur

`NULL` signifie « **valeur inconnue ou absente** ». Ce n'est ni `0`, ni une chaîne vide. Toute comparaison avec `NULL` donne un résultat inconnu : `note = NULL` n'est **jamais** vrai, même pour une note absente. On teste avec `IS NULL` et `IS NOT NULL`.

```sql
SELECT titre FROM films WHERE note IS NULL;   -- les films sans note
```

Les fonctions d'agrégat **ignorent** les `NULL` : `AVG(note)` calcule la moyenne des films **notés** seulement, sans compter les autres comme des zéros.

## Interroger avec SELECT

Les clauses d'un `SELECT` s'écrivent **toujours dans cet ordre** :

```sql
SELECT   colonnes, calculs
FROM     table
JOIN     autre_table ON condition
WHERE    filtre sur les lignes
GROUP BY regroupement
HAVING   filtre sur les groupes
ORDER BY tri
LIMIT    nombre de lignes;
```

Quelques exemples :

```sql
-- les trois films les mieux notés
SELECT titre, note FROM films WHERE note IS NOT NULL ORDER BY note DESC LIMIT 3;

-- les films dont le titre contient « nuit »
SELECT titre FROM films WHERE titre LIKE '%nuit%';
```

Sans `ORDER BY`, le SGBD retourne les lignes dans un ordre **non garanti** : il peut changer d'une exécution à l'autre. Dès que l'ordre compte (une liste affichée, une pagination), on le précise.

### Les jointures

Une **jointure** combine les lignes de plusieurs tables selon une condition, en général l'égalité d'une clé étrangère et d'une clé primaire.

```sql
-- INNER JOIN : seulement les films qui ont un genre correspondant
SELECT f.titre, g.nom AS genre
FROM films f
INNER JOIN genres g ON g.id = f.genre_id;

-- LEFT JOIN : TOUS les genres, même ceux qui n'ont aucun film
SELECT g.nom, COUNT(f.id) AS nb_films
FROM genres g
LEFT JOIN films f ON f.genre_id = g.id
GROUP BY g.id, g.nom;
```

- `INNER JOIN` ne garde que les lignes qui ont une correspondance des deux côtés.
- `LEFT JOIN` garde **toutes** les lignes de la table de gauche ; quand il n'y a pas de correspondance, les colonnes de droite valent `NULL`.

Dans le second exemple, `COUNT(f.id)` compte les films **réels** : pour un genre sans film, `f.id` vaut `NULL` et n'est pas compté, ce qui donne `0`. `COUNT(*)` compterait la ligne produite par le `LEFT JOIN` et donnerait `1` : une erreur classique.

### Regrouper : GROUP BY et HAVING

`GROUP BY` regroupe les lignes qui ont la même valeur, pour calculer un agrégat par groupe : `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`.

- `WHERE` filtre les **lignes avant** le regroupement.
- `HAVING` filtre les **groupes après** le regroupement (il peut utiliser les agrégats).

```sql
-- les genres qui ont au moins 5 films notés au-dessus de 7
SELECT g.nom, COUNT(*) AS nb
FROM films f
JOIN genres g ON g.id = f.genre_id
WHERE f.note > 7
GROUP BY g.id, g.nom
HAVING COUNT(*) >= 5
ORDER BY nb DESC;
```

## Modifier les données

```sql
INSERT INTO films (titre, sortie, note, genre_id) VALUES ('Le Voyage', '1902-09-01', 8.5, 3);
UPDATE films SET note = 9 WHERE id = 12;
DELETE FROM films WHERE id = 12;
```

> [!attention]
> Un `UPDATE` ou un `DELETE` **sans `WHERE`** s'applique à **toutes** les lignes de la table. Écris la clause `WHERE` en premier, et teste-la d'abord avec un `SELECT`.

## Les pièges classiques

- Écrire `= NULL` au lieu de `IS NULL`.
- Utiliser `FLOAT` pour des montants ou des notes exactes.
- `COUNT(*)` avec un `LEFT JOIN` qui doit compter zéro.
- Filtrer un agrégat avec `WHERE` au lieu de `HAVING`.
- Compter sur un ordre sans `ORDER BY`, ou oublier le `WHERE` d'un `UPDATE`.

> [!retenir]
> - Clé primaire pour identifier, clé étrangère pour relier ; plusieurs-à-plusieurs = table d'association.
> - `NOT NULL`, `UNIQUE`, `CHECK`, `FOREIGN KEY` : la base garantit les règles, même si PHP valide déjà.
> - `DECIMAL` pour les valeurs exactes, `DATE` pour une date seule.
> - `NULL` se teste avec `IS NULL` ; `AVG` et `COUNT(colonne)` ignorent les `NULL`.
> - Ordre des clauses : `SELECT … FROM … JOIN … WHERE … GROUP BY … HAVING … ORDER BY … LIMIT`.
> - `LEFT JOIN` + `COUNT(colonne)` pour garder les groupes vides ; sans `ORDER BY`, l'ordre n'est pas garanti.
