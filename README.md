# Quiz BTS SIO SLAM — PHP, SQL, API et JavaScript

[![Tests](https://github.com/TevaLT/quiz-php-bts-sio/actions/workflows/tests.yml/badge.svg)](https://github.com/TevaLT/quiz-php-bts-sio/actions/workflows/tests.yml)

Un quiz pour réviser le programme de BTS SIO option SLAM (2ᵉ année) : bases de PHP, web, bases de données et PDO,
outils (Composer, Slim, Medoo), architecture MVC, API REST, sécurisation par JWT et JavaScript.

- **662 questions** réparties en **30 chapitres**, plus **45 générateurs** qui fabriquent des questions aux valeurs neuves
  (sortie d'un code, calcul, sélecteur CSS, expiration d'un JWT, statut HTTP…) ;
- **des questions différentes à chaque partie** : tirage pondéré par ton historique, ordre et propositions mélangés ;
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

Au programme : trois modes (entraînement, examen, révision des erreurs), sélections rapides de chapitres (PHP et SQL,
JavaScript, sécurité web…), correction expliquée avec le chapitre et le cours à relire, bilan avec « Refaire mes erreurs »,
statistiques et graphique de progression, thème clair ou sombre, raccourcis clavier, code coloré.

### Dans le terminal

```bash
php quiz.php            # menu interactif
php quiz.php --n=20     # 20 questions sur tout le programme
php quiz.php --aide     # toutes les options
```

| Option | Effet |
|---|---|
| `--n=15` | nombre de questions (15 par défaut) |
| `--chap=03,09-12` | limiter à certains chapitres (listes et intervalles, de 00 à 29) |
| `--bloc=5` | un seul bloc : 1 bases · 2 web · 3 bases de données · 4 outils et API · 5 JavaScript |
| `--cours` | uniquement les questions tirées des cours |
| `--examen` | aucune correction pendant la partie, bilan complet à la fin |
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
| Bases du langage | 00 Premiers pas · 01 Variables et types · 02 Conditions · 03 Boucles · 04 Tableaux et textes · 05 Algorithmes · 06 Fonctions et tests · 07 Récursivité · 08 Projet |
| Le web avec PHP | 09 HTTP et superglobales · 10 Formulaires robustes · 11 Cookies · 12 Sessions et sécurité · 13 Projet |
| Bases de données | 14 Modèle relationnel et SQL · 15 PDO : lire · 16 PDO : CRUD · 17 Transactions · 18 Authentification |
| Outils, architecture et API | 19 Composer · 20 Medoo · 21 Slim · 22 Slim + Medoo · 23 MVC · 24 API REST · 25 Projet · 26 JWT |
| JavaScript | 27 Fondamentaux · 28 Fonctions, stockage, JSON · 29 DOM et événements |

## Pourquoi les questions changent à chaque partie

1. **Graine neuve** à chaque partie : le tirage n'est jamais le même.
2. **Mémoire** (`historique.json`, créé au premier lancement et jamais versionné) : une question jamais vue est favorisée,
   une question de la partie précédente est presque exclue, une question ratée revient plus souvent, une question maîtrisée moins.
3. **Ordre mélangé** : questions et propositions changent de place ; deux questions du même chapitre se suivent rarement.
4. **Questions générées** (environ une sur quatre) : valeurs neuves à chaque fois, réponse calculée par le programme.
5. **Types variés** : choix unique, cases multiples, réponse libre (casse, accents et espaces tolérés).

## Ajouter des questions

Les questions sont de simples fichiers texte dans [`banque/`](banque) ; le format est décrit dans
[`banque/README.md`](banque/README.md). Après une modification :

```bash
php quiz.php --verifier
```

## Tests

```bash
php quiz.php --verifier            # banque de questions et générateurs (300 tirages par générateur)
php site/tests/partie_test.php     # logique du site : parties, statistiques, coloration du code, graphique
php site/tests/http_test.php       # site de bout en bout : vrai serveur, cookies, CSRF, statuts HTTP
```

Le test de bout en bout écrit dans un historique temporaire : le tien n'est jamais lu ni modifié.
Ces trois commandes tournent à chaque envoi sur GitHub, avec PHP 8.2, 8.3, 8.4 et 8.5.

## Structure

```text
quiz.php            interface terminal
moteur.php          moteur partagé : banque, tirage pondéré, correction, historique
generateurs.php     questions à valeurs aléatoires (réponses calculées)
banque/             questions, en fichiers texte
site/               interface web (voir site/README.md)
```

## Sources et licences

- Les chapitres 26 à 29 et la plupart des questions « tirées des cours » s'appuient sur les cours de **Térence FERUT**
  (AstroWare Conception), publiés sous licence [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.fr).
  Détail et autres sources : [`SOURCES.md`](SOURCES.md).
- **Code** (moteur, terminal, site) : licence [MIT](LICENSE).
- **Banque de questions** (`banque/`) : [CC BY-SA 4.0](banque/LICENSE), comme les cours dont elle s'inspire.
