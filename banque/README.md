# Format des fichiers de questions

Chaque fichier `*.txt` de ce dossier est lu automatiquement. Une question suit ce modèle :

```text
# 12                          ← chapitre (00 à 29) des questions qui suivent

Q: Que contient le cookie PHPSESSID ?
+ Seulement un identifiant de session aléatoire
- Toutes les données de $_SESSION, chiffrées
- Le nom et le mot de passe de l'utilisateur
- La liste des pages visitées
? Les données restent sur le serveur : seul l'identifiant circule.
```

| Marqueur | Rôle |
|---|---|
| `# NN` | chapitre des questions suivantes, de 00 à 27 (obligatoire avant la première question) |
| `Q: texte` | début d'une question ; ajouter ` [cours]` en fin de ligne pour la rattacher à un cours (filtre « tirées des supports de référence ») |
| `\|code` | ligne de code affichée sous l'énoncé (la barre verticale est retirée ; le site la colore) |
| `+ texte` | bonne réponse — **une seule** `+` : choix unique ; **plusieurs** `+` : cases multiples |
| `- texte` | mauvaise réponse (au moins une) |
| `= a \| b` | question à **réponse libre** : variantes acceptées séparées par `\|` (ni `+` ni `-` dans ce cas) |
| `i: texte` | indice affiché pour une réponse libre (facultatif) |
| `? texte` | explication affichée après la réponse (**obligatoire**) |
| `// …` | commentaire ignoré |

Les propositions sont mélangées automatiquement à chaque affichage : écris toujours la bonne réponse en premier.

## Écrire de bonnes questions

- **Longueurs comparables** : la bonne réponse ne doit pas se repérer à sa longueur (`php quiz.php --verifier` affiche un indicateur).
- Les mauvaises réponses doivent être **plausibles** (erreurs classiques), jamais absurdes.
- Pas de « Aucune de ces réponses » ni de « Toutes les réponses » : les propositions changent d'ordre.
- L'explication dit *pourquoi*, et pas seulement *quoi*.
- **Le langage, pas les exercices** : une question porte sur une notion de PHP ou de JavaScript, jamais sur le contenu
  d'un sujet de TP (nom d'un projet, d'une fonction à écrire, règle métier inventée pour l'exercice). Un test le vérifie.
- Vérifier chaque affirmation sur PHP en l'exécutant (`php -r '…'`) : les comportements changent selon les versions.
- Après une modification : `php quiz.php --verifier`.

## Fichiers

| Fichier | Chapitres |
|---|---|
| `01-bases.txt` | 00 à 07 : bases du langage |
| `02-objet.txt` | 08 à 11 : programmation orientée objet |
| `03-web.txt` | 12 à 15 : HTTP, formulaires, cookies, sessions, sécurité |
| `04-donnees.txt` | 16 à 19 : fichiers, dates, JSON, SQL, PDO |
| `05-architecture-api.txt` | 20 à 22 : Composer, MVC et frameworks, API REST |
| `06-securite-api-jwt.txt` | 23 : sécuriser une API avec JWT |
| `07` à `10-javascript-*.txt` | 24 à 27 : JavaScript |

## Licence

Les questions de ce dossier sont sous licence [CC BY-SA 4.0](LICENSE) ; leurs sources sont détaillées dans
[`../SOURCES.md`](../SOURCES.md).
