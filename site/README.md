# Le site du quiz

La version web du quiz : mêmes questions, mêmes générateurs, même historique que `php quiz.php`,
avec une interface dans le navigateur.

PHP 8.2 ou plus suffit : aucune dépendance, aucune base de données, pas de Composer.

## Lancer

Depuis la racine du dépôt :

```bash
php -S localhost:8000 -t site/public site/public/index.php
```

puis ouvre <http://localhost:8000>. Raccourcis : double-clic sur `site/demarrer.cmd` (Windows) ou
`sh site/demarrer.sh` (macOS / Linux). Pour changer de port : `PORT=8080 sh site/demarrer.sh`
(sous Windows, modifie la ligne `set PORT=` du `.cmd`).

> Le serveur intégré de PHP est fait pour le développement local : n'expose pas ce site sur Internet.

## Utiliser

1. **Accueil** : mode (entraînement, examen, révision de tes erreurs), nombre de questions, chapitres
   (sélections rapides : tout, PHP et SQL, JavaScript, sécurité web), et suggestion des chapitres à consolider.
2. **Question** : réponds au clavier (`A`–`D`, `Entrée`, `S` pour passer) ou à la souris ; le code est coloré.
3. **Correction** : bonne réponse, explication, chapitre et cours à relire.
4. **Bilan** : score, détail par chapitre, correction de chaque question, « Refaire mes erreurs », graine pour rejouer, impression.
5. **Statistiques** : graphique de progression (score de chaque partie et moyenne glissante), réussite par chapitre,
   questions les plus ratées.

L'historique est écrit dans `historique.json` à la racine du dépôt, le même fichier que celui du terminal.
La variable d'environnement `QUIZ_HISTORIQUE` permet d'utiliser un autre fichier (c'est ce que font les tests).

## Structure

```text
site/
├── public/index.php         point d'entrée unique + table de routage
├── public/assets/           style.css, app.js (le seul JavaScript du site, facultatif), favicon.svg
├── src/
│   ├── bootstrap.php        en-têtes de sécurité, session, chargement
│   ├── http.php             échappement, redirections, CSRF, messages flash, thème, rendu
│   ├── partie.php           logique d'une partie : fonctions pures sur des tableaux
│   ├── pages.php            pages (GET) et actions (POST)
│   ├── stats.php            statistiques, progression, chapitres à consolider
│   └── vues.php             assistants d'affichage : coloration du code, graphiques SVG (HTML toujours échappé)
├── templates/               gabarits HTML
└── tests/
    ├── partie_test.php      tests de la logique (fonctions pures)
    └── http_test.php        tests de bout en bout (vrai serveur, historique temporaire)
```

Deux interfaces (terminal et web) partagent les mêmes règles : tout ce qui ne dépend ni du terminal ni du web est
dans `moteur.php`.

## Routes

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/` | accueil et formulaire de création |
| POST | `/partie` | crée une partie (422 si les options sont invalides) |
| GET | `/question`, `/correction`, `/bilan` | déroulement d'une partie |
| POST | `/repondre`, `/abandonner` | répondre, terminer maintenant |
| POST | `/erreurs` | refaire les questions ratées de la partie terminée |
| GET | `/stats`, `/sources` | statistiques ; sources et licences |
| GET, POST | `/reinitialiser` | effacer l'historique (avec confirmation) |
| POST | `/theme` | thème clair, sombre ou automatique |

## Ce que le site met en pratique

| Notion | Où la voir |
|---|---|
| Front controller et routage par méthode | `public/index.php` (chapitres 21 et 23) |
| Séparation données / logique / affichage | `partie.php` et `stats.php` ne touchent à aucune superglobale ni au HTML (chapitre 23) |
| Validation par listes blanches | `validerOptionsPartie()`, `validerReponse()` dans `partie.php` (chapitres 09 et 10) |
| Formulaire réaffiché avec le statut **422** | création d'une partie invalide (chapitre 22) |
| Post/Redirect/Get et messages flash | toutes les actions POST redirigent en **303** (chapitres 10 et 12) |
| Jeton **CSRF** et `hash_equals`, refus **403** | `verifierCsrf()` dans `http.php` (chapitre 12) |
| Session : cookie `HttpOnly`, `SameSite`, mode strict | `bootstrap.php` (chapitres 11 et 12) |
| Cookie de préférence validé (thème) | `actionTheme()` : liste blanche + redirection limitée à des pages connues |
| Échappement de toute sortie (`e()`), pas de style ni de script en ligne | `http.php`, `vues.php`, CSP dans `bootstrap.php` (chapitres 04 et 10) |
| Données JavaScript insérées avec `textContent` | survol du graphique dans `app.js` (chapitre 29) |
| Pas de fuite d'information | erreurs dans les journaux, page 500 neutre (`QUIZ_DEBUG=1` pour déboguer en local) |
| Double envoi / bouton « retour » | le numéro de question est contrôlé côté serveur (`actionRepondre()`) |
| Les bonnes réponses ne quittent jamais le serveur | elles restent dans la session, la correction est faite par PHP |
| Écriture de l'historique sous verrou (`flock`) | `modifierHistorique()` dans `moteur.php` (chapitre 17 : accès concurrents) |

## Accessibilité

- Tout fonctionne sans JavaScript ; `app.js` ajoute seulement les raccourcis, les sélections rapides et le survol du graphique.
- Le graphique de progression a son équivalent en tableau, se parcourt au clavier (flèches, Début, Fin) et annonce
  chaque partie aux lecteurs d'écran ; ses couleurs sont validées en thème clair et sombre (contraste, daltonisme).
- Le bilan et les statistiques s'impriment proprement (encre noire, détails dépliés).

## Tests

```bash
php site/tests/partie_test.php     # logique : parties, rattrapage, progression, coloration, graphique
php site/tests/http_test.php       # bout en bout : statuts HTTP, CSRF, cookies, en-têtes, pages
php quiz.php --verifier            # banque de questions et générateurs
```

## Pistes pour aller plus loin

- Ajouter un minuteur ou un mode « série » (arrêt à la première erreur).
- Remplacer le routage maison par Slim 4, ou l'historique JSON par une base SQLite avec PDO.
