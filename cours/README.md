# Le cours

Un chapitre de cours par chapitre du quiz : `NN-sujet.md`, où `NN` est le numéro du chapitre (de `00` à `27`, voir
`TITRES` dans `moteur.php`). Le site les affiche sur `/cours` ; chaque leçon se termine par un test de 10 questions sur
son chapitre.

Les cours expliquent **le langage et ses outils**, avec des exemples génériques : ils ne reprennent ni le contenu ni les
sujets des exercices de TP, ni le texte des supports cités dans `SOURCES.md`.

## Format

Du Markdown simplifié, lu par `site/src/cours.php`. Tout le texte est échappé à l'affichage : une balise HTML écrite dans
un cours apparaît telle quelle, elle n'est jamais interprétée.

````text
---
resume: Une phrase affichée dans le sommaire du cours et en tête de la leçon.
---

> [!objectifs]
> - ce que le lecteur saura faire à la fin du chapitre

## Une section (elle apparaît dans le sommaire de la page)

Un paragraphe : des lignes consécutives. Dans le texte : `du code`, **du gras**, *de l'italique*,
[un lien interne](/fiches?ch=05) ou [un lien externe](https://www.php.net/).

### Une sous-section

- une liste à puces
  (une ligne indentée de deux espaces prolonge l'élément)
1. une liste numérotée

```php
echo 'un bloc de code, coloré comme dans le quiz';
```

> [!attention]
> Un encadré. Genres reconnus : objectifs, retenir, attention, astuce, exemple
> (tout autre genre devient une simple note). Un titre peut suivre : > [!astuce] Mon titre

| En-tête | Autre colonne |
|---|---|
| cellule | `a \|\| b` : « \| » écrit une barre verticale dans une cellule |
````

Langages des blocs de code : `php`, `js`, `sql`, `html`, `json`, `bash`, `http`, `text`.

Les liens ne sont acceptés que vers une page du site (`/…`) ou une adresse `https://` ; les autres restent du texte.

## Règles de rédaction

- Un chapitre commence par l'encadré `objectifs` et se termine par une section « Les pièges classiques » puis l'encadré
  `retenir`.
- Le cours couvre **toutes** les notions que le quiz interroge dans ce chapitre (la banque est dans `banque/`).
- Chaque résultat annoncé (sortie d'un code, message d'erreur) est vérifié avec PHP 8.2 ou plus (et Node.js pour JavaScript).
- Le tutoiement, comme dans le reste du quiz.

## Vérifier

```bash
php site/tests/partie_test.php
```

Les tests vérifient que les 28 chapitres existent, ont un résumé, au moins quatre sections, du code, les encadrés
`objectifs` et `retenir`, des tableaux réguliers, et que tout le code PHP des cours se colore sans perte.

## Licence

Le contenu de ce dossier est mis à disposition selon les termes de la licence
[CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.fr), comme la banque de questions (voir `LICENSE`).
