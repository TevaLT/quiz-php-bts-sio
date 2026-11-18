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

1. **Accueil** : « Partie rapide » en un clic (10 questions sur tout le programme), ou « Reprendre » si une partie est en cours.
   Pour personnaliser, trois étapes : les thèmes à réviser (une tuile par bloc, ou chapitre par chapitre), le mode (entraînement,
   examen, série, révision de tes erreurs), puis les réglages (nombre de questions, minuteur, questions des cours). Une barre
   « Commencer » reste visible avec le résumé de la partie et le nombre de questions disponibles. Les réglages de ta dernière
   partie sont repris, et une sélection impossible est signalée avant l'envoi.
2. **Question** : réponds au clavier (`A`–`D`, `Entrée`, `S` pour passer) ou à la souris ; le code est coloré. Le score s'affiche
   en direct. Valider sans répondre affiche une consigne, sans recharger la page. Avec le minuteur, un compte à rebours s'affiche ;
   à zéro, la question est comptée « hors délai ». En série, le compteur et ton record sont affichés.
3. **Correction** : un bandeau de verdict, les étiquettes « Ta réponse » et « Bonne réponse » (en texte, pas seulement en couleur),
   l'explication, le temps de réponse, le chapitre, le cours et la fiche à relire.
4. **Bilan** : score, puis « Et maintenant ? » : l'action conseillée selon le résultat (relire la fiche du chapitre le plus raté,
   refaire ses erreurs, ajouter le minuteur, tenter une série). Ensuite le détail par chapitre, la meilleure série (et nouveau
   record), le temps moyen, la correction de chaque question, la graine pour rejouer et l'impression.
5. **Fiches** : toutes les questions d'un chapitre avec leur réponse expliquée, et une recherche par mot-clé dans toute la banque
   (mots surlignés, accents et majuscules ignorés).
6. **Statistiques** : graphique de progression (score de chaque partie et moyenne glissante), record de série, temps moyen,
   réussite par chapitre, questions les plus ratées, et un raccourci pour s'entraîner sur ses points faibles.

Partout : un seul bouton de thème (Auto → Clair → Sombre), des messages en notifications discrètes (les erreurs restent
affichées), une pastille sur « Partie en cours », et une protection contre le double envoi des formulaires. Sur mobile,
l'en-tête est compact et les boutons d'action restent collés en bas de l'écran.

L'historique est écrit dans `historique.json` à la racine du dépôt, le même fichier que celui du terminal.
La variable d'environnement `QUIZ_HISTORIQUE` permet d'utiliser un autre fichier (c'est ce que font les tests).

## Structure

```text
site/
├── public/index.php         point d'entrée unique + table de routage + ressources statiques avec cache
├── public/assets/           style.css, app.js (le seul JavaScript du site, facultatif), favicon.svg
├── src/
│   ├── bootstrap.php        en-têtes de sécurité, session, chargement
│   ├── http.php             échappement, redirections, CSRF, messages flash, thème, URL versionnées, rendu
│   ├── partie.php           logique d'une partie (modes, minuteur, série) : fonctions pures sur des tableaux
│   ├── fiches.php           fiches de révision et recherche : fonctions pures
│   ├── pages.php            pages (GET) et actions (POST)
│   ├── stats.php            statistiques, progression, records, chapitres à consolider
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
| POST | `/repondre`, `/abandonner` | répondre (ou « temps écoulé »), terminer maintenant |
| POST | `/erreurs` | refaire les questions ratées de la partie terminée |
| GET | `/fiches`, `/fiches?ch=05`, `/fiches?q=pdo` | fiches de révision : sommaire, chapitre, recherche (404 si chapitre inconnu) |
| GET | `/stats`, `/sources` | statistiques ; sources et licences |
| GET, POST | `/reinitialiser` | effacer l'historique (avec confirmation) |
| POST | `/theme` | thème clair, sombre ou automatique |

## Ce que le site met en pratique

| Notion | Où la voir |
|---|---|
| Front controller et routage par méthode | `public/index.php` (chapitre 21) |
| Séparation données / logique / affichage | `partie.php`, `fiches.php` et `stats.php` ne touchent à aucune superglobale ni au HTML (chapitre 21) |
| Validation par listes blanches | `validerOptionsPartie()`, `validerReponse()`, chapitre des fiches (chapitres 12 et 13) |
| Formulaire réaffiché avec le statut **422** | création d'une partie invalide (chapitre 21) |
| Post/Redirect/Get et messages flash | toutes les actions POST redirigent en **303** (chapitres 13 et 14) |
| Jeton **CSRF** et `hash_equals`, refus **403** | `verifierCsrf()` dans `http.php` (chapitre 15) |
| Session : cookie `HttpOnly`, `SameSite`, mode strict | `bootstrap.php` (chapitre 14) |
| Cookie de préférence validé (thème) | `actionTheme()` : liste blanche + redirection limitée à des pages connues |
| Échappement de toute sortie (`e()`), pas de style ni de script en ligne | `http.php`, `vues.php`, CSP dans `bootstrap.php` (chapitres 13 et 15) |
| Données JavaScript insérées avec `textContent` | survol du graphique et minuteur dans `app.js` (chapitre 26) |
| Pas de fuite d'information | erreurs dans les journaux, page 500 neutre (`QUIZ_DEBUG=1` pour déboguer en local) |
| Ne jamais faire confiance au client | le minuteur est vérifié par le serveur (`repondre()`) : l'heure d'affichage est notée dans la session |
| Double envoi / bouton « retour » | le numéro de question est contrôlé côté serveur (`actionRepondre()`) |
| Les bonnes réponses ne quittent jamais le serveur | elles restent dans la session, la correction est faite par PHP |
| Écriture de l'historique sous verrou (`flock`) | `modifierHistorique()` dans `moteur.php` (chapitre 16) |
| Cache HTTP : `ETag`, `304`, URL versionnées | ressources statiques servies par `public/index.php` (chapitre 22) |

## Performances

- **Banque mise en cache** : les fichiers texte de `banque/` sont analysés une seule fois, puis gardés dans `cache/` sous
  forme de fichier PHP que l'OPcache garde en mémoire (environ 0,8 ms au lieu de 5 ms par page). Le cache est régénéré
  dès qu'un fichier change ; `QUIZ_CACHE=0` le désactive.
- **Ressources statiques en cache** : `style.css` et `app.js` sont appelés avec une URL versionnée (`?v=…`) et gardés un an
  par le navigateur ; une nouvelle version change l'URL. Chaque page charge donc un seul document.
- **Session écrite seulement si elle change** : afficher une question déjà affichée ne réécrit pas la session.

## Accessibilité

- Tout fonctionne sans JavaScript ; `app.js` ajoute les raccourcis, le compte à rebours, les tuiles de thèmes et le résumé en
  direct, les consignes avant l'envoi, les notifications et le survol du graphique. Sans JavaScript, la liste des chapitres
  reste dépliée et le minuteur reste appliqué par le serveur.
- Les tuiles sont des boutons à bascule (`aria-pressed` : oui, non ou « mixte » pour un thème en partie coché) ; les réglages
  sont de vrais groupes de boutons radio, utilisables aux flèches du clavier.
- La correction ne repose pas sur la couleur seule : chaque proposition corrigée porte une étiquette en texte.
- Les animations (verdict, pastille, notifications) sont coupées si le système demande de réduire les animations.
- Le compte à rebours annonce « 10 secondes » et « 5 secondes » aux lecteurs d'écran, sans les submerger à chaque seconde.
- Le graphique de progression a son équivalent en tableau, se parcourt au clavier (flèches, Début, Fin) et annonce
  chaque partie aux lecteurs d'écran ; ses couleurs sont validées en thème clair et sombre (contraste, daltonisme).
- Le bilan, les fiches et les statistiques s'impriment proprement (encre noire, détails dépliés).

## Tests

```bash
php site/tests/partie_test.php     # logique : parties, minuteur, série, fiches, rattrapage, progression, coloration, graphique
php site/tests/http_test.php       # bout en bout : statuts HTTP, CSRF, cookies, en-têtes, cache, minuteur, série, fiches
php quiz.php --verifier            # banque de questions et générateurs
```

## Pistes pour aller plus loin

- Remplacer le routage maison par Slim 4, ou l'historique JSON par une base SQLite avec PDO.
- Ajouter des comptes utilisateurs (sessions + `password_hash`) pour partager le quiz entre plusieurs élèves.
