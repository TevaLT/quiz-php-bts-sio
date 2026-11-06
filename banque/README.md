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
| `# NN` | chapitre des questions suivantes (obligatoire avant la première question) |
| `Q: texte` | début d'une question ; ajouter ` [cours]` en fin de ligne pour la rattacher à un cours (filtre « tirées des cours ») |
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
- Après une modification : `php quiz.php --verifier`.

## Licence

Les questions de ce dossier sont sous licence [CC BY-SA 4.0](LICENSE) ; leurs sources sont détaillées dans
[`../SOURCES.md`](../SOURCES.md).
