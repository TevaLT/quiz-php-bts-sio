# Quiz BTS SIO SLAM — apprendre PHP (et JavaScript)

[![Tests](https://github.com/TevaLT/quiz-php-bts-sio/actions/workflows/tests.yml/badge.svg)](https://github.com/TevaLT/quiz-php-bts-sio/actions/workflows/tests.yml)

Un quiz pour apprendre et réviser PHP : bases du langage, programmation orientée objet, web et sécurité, bases de données
avec PDO, Composer, MVC, API REST et JWT — et JavaScript (fondamentaux, objets et JSON, DOM, asynchrone avec `fetch`).

- **781 questions** réparties en **28 chapitres**, plus **50 générateurs** qui fabriquent des questions aux valeurs neuves
  (sortie d'un code, logique booléenne, `match`, exceptions, objets, `$_GET`, `filter_var`, dates, JSON, boucle d'événements JS…) ;
- **des questions sur le langage**, pas sur le contenu des exercices de TP ;
- **des questions différentes à chaque partie** : tirage pondéré par ton historique, ordre et propositions mélangés ;
- **minuteur, mode série et fiches de révision** pour apprendre autant que pour s'évaluer ;
- **deux interfaces, un seul moteur** : dans le navigateur ou dans le terminal, avec le même historique ;
- **aucune dépendance** : PHP 8.2 ou plus suffit (ni Composer, ni base de données, ni connexion Internet).

## Démarrer

```bash
git clone https://github.com/TevaLT/quiz-php-bts-sio.git
cd quiz-php-bts-sio
```

### Dans le navigateur

```bash
php -S localhost:8000 -t site/public site/public/index.php
```

puis ouvre <http://localhost:8000>. Raccourcis : double-clic sur `site/demarrer.cmd` (Windows) ou `sh site/demarrer.sh` (macOS, Linux).

Au programme :

- une **partie rapide** en un clic, ou une configuration en trois étapes (thèmes, mode, réglages) avec un résumé en direct,
  tes derniers réglages repris et les sélections impossibles signalées avant l'envoi ;
- quatre modes : **entraînement** (correction après chaque question), **examen** (bilan à la fin), **série** (jusqu'à la
  première erreur, avec ton record) et **révision** de tes erreurs ;
- un **minuteur** par question (15 à 90 s) vérifié par le serveur : recharger la page ne le remet pas à zéro, une réponse
  hors délai ne compte pas ;
- des **fiches de révision** par chapitre (toutes les réponses expliquées) et une recherche par mot-clé ;
- une correction expliquée (« Ta réponse », « Bonne réponse ») avec le chapitre, le cours et la fiche à relire, et un bilan
  qui conseille la prochaine étape (relire une fiche, refaire ses erreurs, ajouter le minuteur, tenter une série) ;
- des statistiques avec graphique de progression et records, un thème clair ou sombre, des raccourcis clavier et du code coloré.

### Dans le terminal

```bash
php quiz.php                 # menu interactif
php quiz.php --n=20          # 20 questions sur tout le programme
php quiz.php --serie         # jusqu'à la première erreur
php quiz.php --chrono=30     # 30 secondes par question
php quiz.php --aide          # toutes les options
```

| Option | Effet |
|---|---|
| `--n=30` | nombre de questions (20 par défaut) |
| `--chap=03,12-15` | limiter à certains chapitres (listes et intervalles, de 00 à 27) |
| `--bloc=2` | un seul bloc : 1 bases · 2 objet · 3 web · 4 données · 5 architecture et API · 6 JavaScript |
| `--cours` | uniquement les questions tirées des cours |
| `--examen` | aucune correction pendant la partie, bilan complet à la fin |
| `--serie` | enchaîne les questions jusqu'à la première erreur (50 au plus) |
| `--chrono=30` | secondes accordées par question : une réponse plus lente compte comme une erreur |
| `--revision` | rejoue uniquement les questions ratées auparavant |
| `--stats` | taux de réussite par chapitre, questions les plus ratées |
| `--reset` | efface l'historique (avec confirmation) |
| `--graine=123456` | rejoue exactement une ancienne partie (la graine est affichée à la fin) |
| `--verifier` | contrôle la banque de questions et les générateurs |
| `--sans-couleur`, `--ascii` | si ton terminal affiche mal les couleurs ou les symboles |

Réponses : une lettre (`A`, `B`…) pour un choix unique, plusieurs lettres (`A,C`) quand l'énoncé le demande, du texte pour
une réponse libre. `s` passe la question, `q` quitte (`/s` et `/q` pendant une réponse libre).

## Les chapitres

| Bloc | Chapitres |
|---|---|
| Bases du langage | 00 Premiers pas et débogage · 01 Variables, types et opérateurs · 02 Conditions et comparaisons · 03 Boucles · 04 Tableaux · 05 Chaînes de caractères · 06 Fonctions et récursivité · 07 Erreurs et exceptions |
| Programmation orientée objet | 08 Classes et objets · 09 Héritage et polymorphisme · 10 Interfaces, traits et énumérations · 11 Namespaces et membres statiques |
| PHP et le web | 12 HTTP et superglobales · 13 Formulaires et validation · 14 Cookies et sessions · 15 Sécurité des applications web |
| Données et persistance | 16 Fichiers, dates et JSON · 17 SQL et modèle relationnel · 18 PDO : connexion et lecture · 19 PDO : écriture et transactions |
| Architecture et API | 20 Composer et autoload · 21 MVC, routage et frameworks · 22 API REST · 23 Sécuriser une API : JWT |
| JavaScript | 24 Fondamentaux · 25 Fonctions, objets et stockage · 26 DOM et événements · 27 Asynchrone et fetch |

## Pourquoi les questions changent à chaque partie

1. **Graine neuve** à chaque partie : le tirage n'est jamais le même.
2. **Mémoire** (`historique.json`, créé au premier lancement et jamais versionné) : une question jamais vue est favorisée,
   une question de la partie précédente est presque exclue, une question ratée revient plus souvent, une question maîtrisée moins.
3. **Ordre mélangé** : questions et propositions changent de place ; deux questions du même chapitre se suivent rarement.
4. **Questions générées** (environ une sur quatre) : valeurs neuves à chaque fois, réponse calculée par PHP en exécutant
   la même opération.
5. **Types variés** : choix unique, cases multiples, réponse libre (casse, accents et espaces tolérés).

## Ajouter des questions

Les questions sont de simples fichiers texte dans [`banque/`](banque) ; le format est décrit dans
[`banque/README.md`](banque/README.md). Après une modification :

```bash
php quiz.php --verifier
```

Le site garde une version analysée de la banque dans `cache/` (non versionné) : elle est régénérée automatiquement
dès qu'un fichier de `banque/` change.

## Tests

```bash
php quiz.php --verifier            # banque de questions et générateurs (300 tirages par générateur)
php site/tests/partie_test.php     # logique : parties, minuteur, série, fiches, statistiques, coloration du code, graphique
php site/tests/http_test.php       # site de bout en bout : vrai serveur, cookies, CSRF, statuts HTTP, cache des ressources
```

Le test de bout en bout écrit dans un historique temporaire : le tien n'est jamais lu ni modifié.
Ces trois commandes tournent à chaque envoi sur GitHub, avec PHP 8.2, 8.3, 8.4 et 8.5.

## Structure

```text
quiz.php            interface terminal
moteur.php          moteur partagé : banque (et son cache), tirage pondéré, correction, historique, séries
generateurs.php     questions à valeurs aléatoires (réponses calculées)
banque/             questions, en fichiers texte
site/               interface web (voir site/README.md)
cache/              banque analysée pour le site (généré, non versionné)
```

## Sources et licences

- Les chapitres 23 à 26 et les questions « tirées des cours » s'appuient sur les cours de **Térence FERUT**
  (AstroWare Conception), publiés sous licence [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.fr).
  Détail et autres sources : [`SOURCES.md`](SOURCES.md).
- **Code** (moteur, terminal, site) : licence [MIT](LICENSE).
- **Banque de questions** (`banque/`) : [CC BY-SA 4.0](banque/LICENSE), comme les cours dont elle s'inspire.
