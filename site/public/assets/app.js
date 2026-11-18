// Améliorations facultatives : le site fonctionne entièrement sans JavaScript.
// (Pas de script en ligne : la politique CSP du site n'autorise que ce fichier.)
(function () {
    'use strict';

    var chaque = function (liste, fonction) { Array.prototype.forEach.call(liste, fonction); };
    var tableau = function (liste) { return Array.prototype.slice.call(liste); };
    var entier = function (element, attribut) { return parseInt(element.getAttribute(attribut) || '0', 10) || 0; };

    // ── Messages : notifications discrètes ; les succès et informations disparaissent seuls, les erreurs restent ──
    var flashs = document.querySelector('[data-flashs]');
    if (flashs) {
        flashs.classList.add('toasts');
        chaque(flashs.querySelectorAll('[data-flash]'), function (alerte) {
            var minuterie = null;
            var retirer = function () {
                alerte.classList.add('sortie');
                window.setTimeout(function () { alerte.remove(); }, 300);
            };
            var fermer = document.createElement('button');
            fermer.type = 'button';
            fermer.className = 'fermer-alerte';
            fermer.setAttribute('aria-label', 'Fermer le message');
            fermer.textContent = '×';
            fermer.addEventListener('click', retirer);
            alerte.appendChild(fermer);
            if (alerte.getAttribute('data-flash') !== 'erreur') {
                minuterie = window.setTimeout(retirer, 4500);
                alerte.addEventListener('mouseenter', function () { window.clearTimeout(minuterie); }); // on prend le temps de lire
            }
        });
    }

    // ── Confirmation avant une action qui termine la partie (data-confirmer="message") ──
    chaque(document.querySelectorAll('form[data-confirmer]'), function (formulaire) {
        formulaire.addEventListener('submit', function (ev) {
            if (!window.confirm(formulaire.getAttribute('data-confirmer'))) { ev.preventDefault(); }
        });
    });

    // ── Accueil : tuiles par thème, résumé en direct de la partie, contrôle avant l'envoi ──
    var formulairePartie = document.querySelector('[data-formulaire-partie]');
    if (formulairePartie) {
        var cases = tableau(formulairePartie.querySelectorAll('input[type="checkbox"][name="chapitres[]"]'));
        var resume = formulairePartie.querySelector('[data-resume]');
        var dispo = formulairePartie.querySelector('[data-dispo]');
        var alerteDepart = formulairePartie.querySelector('[data-alerte]');
        var reglageNombre = formulairePartie.querySelector('[data-reglage-nombre]');
        var aideSerie = formulairePartie.querySelector('[data-aide-serie]');
        var caseCours = formulairePartie.querySelector('input[name="cours"]');
        var aRevoir = entier(formulairePartie, 'data-a-revoir');
        var nomsModes = { normal: 'Entraînement', examen: 'Examen', serie: 'Série', revision: 'Révision' };

        var valeur = function (nom, defaut) {
            var choisi = formulairePartie.querySelector('input[name="' + nom + '"]:checked');
            return choisi ? choisi.value : defaut;
        };
        var casesDuBloc = function (numero) {
            return tableau(formulairePartie.querySelectorAll('[data-bloc="' + numero + '"] input[type="checkbox"]'));
        };
        var pluriel = function (n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); };

        var majTuiles = function () {
            chaque(formulairePartie.querySelectorAll('[data-tuile]'), function (tuile) {
                var liste = casesDuBloc(tuile.getAttribute('data-tuile'));
                var cochees = liste.filter(function (c) { return c.checked; }).length;
                var detail = tuile.querySelector('[data-tuile-detail]');
                if (cochees === liste.length) {
                    var questions = liste.reduce(function (somme, c) { return somme + entier(c, 'data-nb'); }, 0);
                    tuile.setAttribute('aria-pressed', 'true');
                    detail.textContent = liste.length + ' chapitres · ' + questions + ' questions';
                } else if (cochees === 0) {
                    tuile.setAttribute('aria-pressed', 'false');
                    detail.textContent = 'Non inclus';
                } else {
                    tuile.setAttribute('aria-pressed', 'mixed');
                    detail.textContent = pluriel(cochees, 'chapitre') + ' sur ' + liste.length;
                }
            });
        };

        var majResume = function () {
            var mode = valeur('mode', 'normal');
            var n = parseInt(valeur('n', '15'), 10);
            var chrono = parseInt(valeur('chrono', '0'), 10);
            var cours = caseCours !== null && caseCours.checked;
            var cochees = cases.filter(function (c) { return c.checked; });
            var banque = cochees.reduce(function (somme, c) { return somme + entier(c, cours ? 'data-cours' : 'data-nb'); }, 0);
            var generees = cours ? 0 : cochees.reduce(function (somme, c) { return somme + entier(c, 'data-gen'); }, 0);
            var disponibles = banque + generees; // chaque générateur fournit au plus une question par partie

            var texte = mode === 'serie' ? 'Série jusqu\'à la première erreur'
                : mode === 'revision' ? 'Révision : jusqu\'à ' + n + ' de tes erreurs'
                    : n + ' questions · ' + nomsModes[mode];
            texte += ' · ' + pluriel(cochees.length, 'chapitre') + (chrono > 0 ? ' · ' + chrono + ' s par question' : '');
            resume.textContent = texte;

            var attention = false;
            if (cochees.length === 0) {
                dispo.textContent = 'Aucun chapitre choisi : sélectionne au moins un thème.';
                attention = true;
            } else if (mode === 'revision') {
                dispo.textContent = pluriel(aRevoir, 'erreur') + ' à revoir au total (tous chapitres confondus).';
            } else if (disponibles === 0) {
                dispo.textContent = 'Aucune question dans cette sélection' + (cours ? ' : décoche « Seulement les questions tirées des cours ».' : '.');
                attention = true;
            } else if (mode !== 'serie' && n > disponibles) {
                dispo.textContent = 'Seulement ' + disponibles + ' questions dans cette sélection : la partie en comptera ' + disponibles + '.';
                attention = true;
            } else {
                dispo.textContent = banque + ' questions disponibles' + (generees > 0 ? ' (+ ' + generees + ' générées)' : '');
            }
            dispo.classList.toggle('attention', attention);

            if (reglageNombre) { reglageNombre.classList.toggle('inactif', mode === 'serie'); }
            if (aideSerie) { aideSerie.hidden = mode !== 'serie'; }
            probleme = cochees.length === 0 ? 'Choisis au moins un thème (ou un chapitre) à réviser.'
                : mode !== 'revision' && disponibles === 0 ? 'Aucune question ne correspond à cette sélection : élargis-la avant de commencer.'
                    : '';
            if (probleme === '' && alerteDepart) { alerteDepart.hidden = true; }
        };
        var probleme = '';
        var maj = function () { majTuiles(); majResume(); };

        // Les tuiles remplacent la longue liste de cases : un clic ajoute ou retire tout un thème.
        var zoneTuiles = formulairePartie.querySelector('[data-tuiles]');
        var outilsSelection = formulairePartie.querySelector('[data-outils-selection]');
        var detailChapitres = formulairePartie.querySelector('[data-choix-chapitres]');
        if (zoneTuiles) { zoneTuiles.hidden = false; }
        if (outilsSelection) { outilsSelection.hidden = false; }
        if (detailChapitres) { detailChapitres.open = false; }

        chaque(formulairePartie.querySelectorAll('[data-tuile]'), function (tuile) {
            tuile.addEventListener('click', function () {
                var liste = casesDuBloc(tuile.getAttribute('data-tuile'));
                var toutes = liste.every(function (c) { return c.checked; });
                liste.forEach(function (c) { c.checked = !toutes; });
                maj();
            });
        });
        chaque(formulairePartie.querySelectorAll('[data-tout]'), function (bouton) {
            bouton.addEventListener('click', function () {
                var cocher = bouton.getAttribute('data-tout') === '1';
                cases.forEach(function (c) { c.checked = cocher; });
                maj();
            });
        });
        formulairePartie.addEventListener('change', maj);

        // Une sélection impossible est signalée ici, au lieu d'un aller-retour vers le serveur (qui refuserait en 422).
        formulairePartie.addEventListener('submit', function (ev) {
            if (probleme === '') { return; }
            ev.preventDefault();
            alerteDepart.textContent = probleme;
            alerteDepart.hidden = false;
            var premiereTuile = formulairePartie.querySelector('[data-tuile]');
            if (premiereTuile) { premiereTuile.focus(); }
        });
        maj();
    }

    // ── Question : aide à la réponse et raccourcis clavier ──
    var formulaireReponse = document.querySelector('[data-formulaire-reponse]');
    if (formulaireReponse) {
        var aide = document.querySelector('[data-raccourcis]');
        if (aide) { aide.hidden = false; }

        var type = formulaireReponse.getAttribute('data-type');
        var valider = formulaireReponse.querySelector('[data-valider]');
        var aideReponse = formulaireReponse.querySelector('[data-aide-reponse]');
        var listeChoix = formulaireReponse.querySelector('.choix');
        var champTexte = formulaireReponse.querySelector('input[name="texte"]');
        var boutonPasser = formulaireReponse.querySelector('button[value="passer"]');

        var aRepondu = function () {
            return type === 'saisie'
                ? champTexte !== null && champTexte.value.trim() !== ''
                : formulaireReponse.querySelector('input[name^="choix"]:checked') !== null;
        };
        var majPret = function () {
            var pret = aRepondu();
            if (valider) { valider.classList.toggle('attente', !pret); }
            if (pret) {
                aideReponse.textContent = '';
                if (listeChoix) { listeChoix.classList.remove('a-repondre'); }
            }
        };
        formulaireReponse.addEventListener('input', majPret);
        formulaireReponse.addEventListener('change', majPret);
        majPret();

        // Un message clair remplace la bulle du navigateur (ou un aller-retour inutile vers le serveur).
        formulaireReponse.noValidate = true;
        formulaireReponse.addEventListener('submit', function (ev) {
            var action = ev.submitter ? ev.submitter.value : 'valider';
            if (action !== 'valider' || aRepondu()) { return; }
            ev.preventDefault();
            var consigne = type === 'saisie' ? 'Écris ta réponse' : type === 'multi' ? 'Coche au moins une proposition' : 'Choisis une proposition';
            aideReponse.textContent = consigne + (boutonPasser ? ', ou passe la question.' : '.');
            if (listeChoix) { listeChoix.classList.add('a-repondre'); }
            var premier = formulaireReponse.querySelector('input[name^="choix"], input[name="texte"]');
            if (premier) { premier.focus({ preventScroll: true }); }
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
            var cible = ev.target;
            var saisie = cible && cible.tagName === 'INPUT' && cible.type === 'text';

            if (ev.key === 'Enter' && !saisie) {
                // Entrée valide si une proposition est cochée (sinon le navigateur ne fait rien de plus).
                if (formulaireReponse.querySelector('input[name^="choix"]:checked')) {
                    ev.preventDefault();
                    formulaireReponse.requestSubmit(valider);
                }
                return;
            }
            if (saisie) { return; }

            var touche = ev.key.length === 1 ? ev.key.toUpperCase() : '';
            if (touche === 'S' && boutonPasser) { // pas de bouton « Passer » en mode série
                ev.preventDefault();
                formulaireReponse.requestSubmit(boutonPasser);
                return;
            }
            if (type === 'qcm' || type === 'multi') {
                var entree = formulaireReponse.querySelector('input[data-lettre="' + touche + '"]');
                if (entree) {
                    ev.preventDefault();
                    if (type === 'multi') { entree.checked = !entree.checked; } else { entree.checked = true; }
                    entree.focus();
                    majPret();
                }
            }
        });
    }

    // ── Minuteur : compte à rebours affiché ; à zéro, la réponse part avec action=temps ──
    // Le serveur reste seul juge : il note l'heure d'affichage et refuse une réponse arrivée trop tard.
    var chrono = document.querySelector('[data-chrono]');
    if (chrono && formulaireReponse) {
        var restantInitial = parseFloat(chrono.getAttribute('data-restant') || '0');
        var depart = window.performance.now();
        var texteChrono = chrono.querySelector('[data-chrono-texte]');
        var barreChrono = chrono.querySelector('[data-chrono-barre]');
        var annonceChrono = chrono.querySelector('[data-chrono-annonce]');
        var envoye = false;
        var derniereSeconde = null;
        var minuterie = null;

        var envoyerTemps = function () {
            if (envoye) { return; }
            envoye = true;
            var champ = document.createElement('input');
            champ.type = 'hidden';
            champ.name = 'action';
            champ.value = 'temps';
            formulaireReponse.appendChild(champ);
            formulaireReponse.submit(); // submit() n'envoie aucun bouton : seule l'action « temps » part
        };

        var afficherTemps = function (secondes) {
            texteChrono.textContent = '';
            if (secondes <= 0) {
                var fin = document.createElement('strong');
                fin.textContent = 'Temps écoulé';
                texteChrono.appendChild(fin);
                return;
            }
            var valeurChrono = document.createElement('strong');
            valeurChrono.textContent = String(secondes);
            texteChrono.appendChild(document.createTextNode('Temps restant : '));
            texteChrono.appendChild(valeurChrono);
            texteChrono.appendChild(document.createTextNode(' s'));
        };

        var majChrono = function () {
            var restant = Math.max(0, restantInitial - (window.performance.now() - depart) / 1000);
            var secondes = Math.ceil(restant);
            if (barreChrono) { barreChrono.value = restant; }
            if (secondes !== derniereSeconde) {
                derniereSeconde = secondes;
                afficherTemps(secondes);
                chrono.classList.toggle('urgent', restant <= 5);
                if (annonceChrono && (secondes === 10 || secondes === 5)) {
                    annonceChrono.textContent = secondes + ' secondes restantes.';
                }
            }
            if (restant <= 0) {
                window.clearInterval(minuterie);
                if (annonceChrono) { annonceChrono.textContent = 'Temps écoulé.'; }
                envoyerTemps();
            }
        };

        formulaireReponse.addEventListener('submit', function (ev) {
            if (ev.defaultPrevented) { return; } // réponse incomplète : le temps continue
            envoye = true;
            window.clearInterval(minuterie);
        });
        minuterie = window.setInterval(majChrono, 200);
        majChrono();
    }

    // ── Correction : Entrée passe à la suite (sans faire défiler la page jusqu'au bouton) ──
    var suivant = document.querySelector('[data-suivant]');
    if (suivant) {
        var aideSuite = document.querySelector('[data-raccourcis]');
        if (aideSuite) { aideSuite.hidden = false; }
        suivant.focus({ preventScroll: true });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && document.activeElement !== suivant && !ev.ctrlKey && !ev.metaKey && !ev.altKey) {
                window.location.href = suivant.getAttribute('href');
            }
        });
    }

    // ── « Tout déplier / Tout replier » (fiches, détail du bilan), et fiche visée par l'adresse (#q…) ──
    var brancherDeplier = function (selecteurBouton, selecteurDetails) {
        chaque(document.querySelectorAll(selecteurBouton), function (bouton) {
            var details = document.querySelectorAll(selecteurDetails);
            var ouverts = false;
            bouton.hidden = false;
            bouton.addEventListener('click', function () {
                ouverts = !ouverts;
                chaque(details, function (d) { d.open = ouverts; });
                bouton.textContent = ouverts ? 'Tout replier' : 'Tout déplier';
            });
        });
    };
    brancherDeplier('[data-deplier]', 'details.fiche');
    brancherDeplier('[data-deplier-bilan]', 'details.detail');
    if (window.location.hash.length > 1) {
        var cible = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
        if (cible && cible.tagName === 'DETAILS') { cible.open = true; }
    }

    // ── Fiches : surlignage des mots recherchés (sans tenir compte des accents ni des majuscules) ──
    // Le texte reste du texte : seules des balises <mark> sont créées autour des passages trouvés.
    var zoneRecherche = document.querySelector('[data-surligner]');
    if (zoneRecherche && typeof ''.normalize === 'function') {
        var plier = function (texte) {
            var sortie = '';
            for (var i = 0; i < texte.length; i++) {
                var c = texte[i].toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                sortie += c.length === 1 ? c : texte[i]; // une lettre pour une lettre : les positions restent alignées
            }
            return sortie;
        };
        var mots = plier(zoneRecherche.getAttribute('data-surligner') || '').split(/\s+/).filter(function (m) { return m.length >= 2; });
        var surligner = function (racine) {
            var marcheur = document.createTreeWalker(racine, NodeFilter.SHOW_TEXT);
            var noeuds = [];
            while (marcheur.nextNode()) { noeuds.push(marcheur.currentNode); }
            noeuds.forEach(function (noeud) {
                var plie = plier(noeud.data);
                var zones = [];
                mots.forEach(function (mot) {
                    for (var pos = plie.indexOf(mot); pos !== -1; pos = plie.indexOf(mot, pos + mot.length)) {
                        zones.push([pos, pos + mot.length]);
                    }
                });
                if (!zones.length) { return; }
                zones.sort(function (a, b) { return a[0] - b[0]; });
                var fragment = document.createDocumentFragment();
                var curseur = 0;
                zones.forEach(function (zone) {
                    if (zone[0] < curseur) { return; } // chevauchement : déjà surligné
                    fragment.appendChild(document.createTextNode(noeud.data.slice(curseur, zone[0])));
                    var marque = document.createElement('mark');
                    marque.textContent = noeud.data.slice(zone[0], zone[1]);
                    fragment.appendChild(marque);
                    curseur = zone[1];
                });
                fragment.appendChild(document.createTextNode(noeud.data.slice(curseur)));
                noeud.parentNode.replaceChild(fragment, noeud);
            });
        };
        if (mots.length) {
            chaque(zoneRecherche.querySelectorAll('details.fiche'), surligner);
        }
    }

    // ── Statistiques : survol et clavier sur le graphique de progression ──
    // Les valeurs viennent de l'attribut data-points (JSON produit par PHP) ; elles sont insérées avec textContent.
    chaque(document.querySelectorAll('[data-graphe]'), function (figure) {
        var infobulle = figure.querySelector('[data-infobulle]');
        var annonce = figure.querySelector('[data-annonce]');

        chaque(figure.querySelectorAll('svg[data-points]'), function (svg) {
            var points;
            try { points = JSON.parse(svg.getAttribute('data-points') || '[]'); } catch (e) { return; }
            if (!points.length || !infobulle) { return; }
            var largeurVue = svg.viewBox.baseVal.width;
            var reticule = svg.querySelector('[data-reticule]');
            var survolPartie = svg.querySelector('[data-survol-partie]');
            var survolMoyenne = svg.querySelector('[data-survol-moyenne]');
            var courant = -1;

            var ligne = function (classe, texte, avecCle) {
                var div = document.createElement('div');
                div.className = classe;
                if (avecCle) {
                    var cle = document.createElement('span');
                    cle.className = 'cle cle-ligne';
                    div.appendChild(cle);
                }
                div.appendChild(document.createTextNode(texte));
                return div;
            };

            var afficher = function (i) {
                courant = i;
                var p = points[i];
                svg.classList.add('actif');
                reticule.setAttribute('x1', p.x);
                reticule.setAttribute('x2', p.x);
                survolPartie.setAttribute('cx', p.x);
                survolPartie.setAttribute('cy', p.yScore);
                survolMoyenne.setAttribute('cx', p.x);
                survolMoyenne.setAttribute('cy', p.yMoyenne);

                infobulle.textContent = '';
                infobulle.appendChild(ligne('infobulle-valeur', p.score + ' / ' + p.total + ' (' + p.pct + ' %)', false));
                infobulle.appendChild(ligne('infobulle-titre', 'Partie n°' + p.n + ' · ' + p.dateAffichee, false));
                infobulle.appendChild(ligne('infobulle-ligne', 'Moyenne glissante : ' + p.moyenne + ' %', true));
                infobulle.hidden = false;

                // Position : au-dessus du point le plus haut, sans sortir de la zone du graphique.
                var zone = svg.getBoundingClientRect();
                var parent = infobulle.parentNode.getBoundingClientRect();
                var echelle = zone.width / largeurVue;
                var demi = infobulle.offsetWidth / 2;
                var gauche = zone.left - parent.left + p.x * echelle;
                infobulle.style.left = Math.max(demi, Math.min(parent.width - demi, gauche)) + 'px';
                infobulle.style.top = (zone.top - parent.top + Math.min(p.yScore, p.yMoyenne) * echelle) + 'px';
            };

            var masquer = function () {
                svg.classList.remove('actif');
                infobulle.hidden = true;
            };

            var annoncer = function () {
                if (annonce && courant >= 0) {
                    var p = points[courant];
                    annonce.textContent = 'Partie n°' + p.n + ', ' + p.dateAffichee + ' : ' + p.score + ' sur ' + p.total
                        + ', soit ' + p.pct + ' %. Moyenne glissante : ' + p.moyenne + ' %.';
                }
            };

            var plusProche = function (clientX) {
                var zone = svg.getBoundingClientRect();
                var x = (clientX - zone.left) * largeurVue / zone.width;
                var meilleur = 0;
                points.forEach(function (p, i) {
                    if (Math.abs(p.x - x) < Math.abs(points[meilleur].x - x)) { meilleur = i; }
                });
                return meilleur;
            };

            svg.addEventListener('pointermove', function (ev) { afficher(plusProche(ev.clientX)); });
            svg.addEventListener('pointerdown', function (ev) { afficher(plusProche(ev.clientX)); });
            svg.addEventListener('pointerleave', function () { if (document.activeElement !== svg) { masquer(); } });
            svg.addEventListener('focus', function () { afficher(courant >= 0 ? courant : points.length - 1); annoncer(); });
            svg.addEventListener('blur', masquer);
            svg.addEventListener('keydown', function (ev) {
                var i = courant >= 0 ? courant : points.length - 1;
                if (ev.key === 'ArrowLeft') { i = Math.max(0, i - 1); }
                else if (ev.key === 'ArrowRight') { i = Math.min(points.length - 1, i + 1); }
                else if (ev.key === 'Home') { i = 0; }
                else if (ev.key === 'End') { i = points.length - 1; }
                else if (ev.key === 'Escape') { masquer(); return; }
                else { return; }
                ev.preventDefault();
                afficher(i);
                annoncer();
            });
        });
    });

    // ── Bilan : bouton « Imprimer » et impression avec tous les détails dépliés ──
    chaque(document.querySelectorAll('[data-imprimer]'), function (bouton) {
        bouton.hidden = false;
        bouton.addEventListener('click', function () { window.print(); });
    });
    window.addEventListener('beforeprint', function () {
        chaque(document.querySelectorAll('details:not([open])'), function (d) {
            d.setAttribute('data-replier', '');
            d.open = true;
        });
    });
    window.addEventListener('afterprint', function () {
        chaque(document.querySelectorAll('details[data-replier]'), function (d) {
            d.open = false;
            d.removeAttribute('data-replier');
        });
    });

    // ── Double envoi : un formulaire ne part qu'une fois (double clic, impatience…) ──
    // Enregistré en dernier : les contrôles précédents (confirmation, réponse incomplète) ont déjà pu annuler l'envoi.
    chaque(document.querySelectorAll('form[method="post"]'), function (formulaire) {
        formulaire.addEventListener('submit', function (ev) {
            if (ev.defaultPrevented) { return; }
            if (formulaire.hasAttribute('data-envoye')) { ev.preventDefault(); return; }
            formulaire.setAttribute('data-envoye', '');
            window.setTimeout(function () { // après la construction des données : le bouton cliqué est bien envoyé
                chaque(formulaire.querySelectorAll('button'), function (b) { b.disabled = true; b.classList.add('en-envoi'); });
            }, 0);
        });
    });
    window.addEventListener('pageshow', function (ev) { // retour arrière : les formulaires redeviennent utilisables
        if (!ev.persisted) { return; }
        chaque(document.querySelectorAll('form[data-envoye]'), function (formulaire) {
            formulaire.removeAttribute('data-envoye');
            chaque(formulaire.querySelectorAll('button'), function (b) { b.disabled = false; b.classList.remove('en-envoi'); });
        });
    });
}());
