// Améliorations facultatives : le site fonctionne entièrement sans JavaScript.
// (Pas de script en ligne : la politique CSP du site n'autorise que ce fichier.)
(function () {
    'use strict';

    var chaque = function (liste, fonction) { Array.prototype.forEach.call(liste, fonction); };

    // ── Accueil : sélections rapides, boutons « Tout / Rien » par bloc, compteur de questions disponibles ──
    var formulairePartie = document.querySelector('[data-formulaire-partie]');
    if (formulairePartie) {
        var cases = Array.prototype.slice.call(formulairePartie.querySelectorAll('input[name="chapitres[]"]'));
        var resume = formulairePartie.querySelector('[data-resume]');

        var majResume = function () {
            var choisies = cases.filter(function (c) { return c.checked; });
            var total = choisies.reduce(function (somme, c) { return somme + parseInt(c.getAttribute('data-nb') || '0', 10); }, 0);
            if (resume) {
                resume.textContent = choisies.length + ' chapitre(s) sélectionné(s) · ' + total + ' questions disponibles';
            }
        };

        var preselections = formulairePartie.querySelector('[data-preselections]');
        if (preselections) {
            preselections.hidden = false;
            chaque(preselections.querySelectorAll('[data-chapitres]'), function (bouton) {
                bouton.addEventListener('click', function () {
                    var liste = (bouton.getAttribute('data-chapitres') || '').split(' ').filter(Boolean);
                    cases.forEach(function (c) { c.checked = liste.indexOf(c.value) !== -1; });
                    majResume();
                });
            });
        }

        chaque(formulairePartie.querySelectorAll('[data-bloc]'), function (bloc) {
            var entete = bloc.querySelector('.bloc-entete');
            var outils = document.createElement('div');
            outils.className = 'bloc-outils';
            [['Tout', true], ['Rien', false]].forEach(function (def) {
                var bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'lien';
                bouton.textContent = def[0];
                bouton.addEventListener('click', function () {
                    chaque(bloc.querySelectorAll('input[name="chapitres[]"]'), function (c) { c.checked = def[1]; });
                    majResume();
                });
                outils.appendChild(bouton);
            });
            entete.appendChild(outils);
        });

        cases.forEach(function (c) { c.addEventListener('change', majResume); });
        majResume();
    }

    // ── Question : raccourcis clavier ──
    var formulaireReponse = document.querySelector('[data-formulaire-reponse]');
    if (formulaireReponse) {
        var aide = document.querySelector('[data-raccourcis]');
        if (aide) { aide.hidden = false; }

        var type = formulaireReponse.getAttribute('data-type');
        document.addEventListener('keydown', function (ev) {
            if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
            var cible = ev.target;
            var saisie = cible && cible.tagName === 'INPUT' && cible.type === 'text';

            if (ev.key === 'Enter' && !saisie) {
                // Entrée valide si une proposition est cochée (sinon le navigateur ne fait rien de plus).
                var coche = formulaireReponse.querySelector('input[name^="choix"]:checked');
                if (coche) {
                    ev.preventDefault();
                    formulaireReponse.requestSubmit(formulaireReponse.querySelector('button[value="valider"]'));
                }
                return;
            }
            if (saisie) { return; }

            var touche = ev.key.length === 1 ? ev.key.toUpperCase() : '';
            if (touche === 'S') {
                ev.preventDefault();
                formulaireReponse.requestSubmit(formulaireReponse.querySelector('button[value="passer"]'));
                return;
            }
            if (type === 'qcm' || type === 'multi') {
                var entree = formulaireReponse.querySelector('input[data-lettre="' + touche + '"]');
                if (entree) {
                    ev.preventDefault();
                    if (type === 'multi') { entree.checked = !entree.checked; } else { entree.checked = true; }
                    entree.focus();
                }
            }
        });
    }

    // ── Correction : Entrée passe à la suite ──
    var suivant = document.querySelector('[data-suivant]');
    if (suivant) {
        var aideSuite = document.querySelector('[data-raccourcis]');
        if (aideSuite) { aideSuite.hidden = false; }
        suivant.focus();
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && document.activeElement !== suivant && !ev.ctrlKey && !ev.metaKey && !ev.altKey) {
                window.location.href = suivant.getAttribute('href');
            }
        });
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
}());
