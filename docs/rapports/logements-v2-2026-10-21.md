# Intégration du catalogue Logements approuvé

La page `/recherche` reprend la maquette approuvée : introduction compacte, recherche horizontale, filtres latéraux, grille de cartes et engagements. Elle présente les annonces réelles dès l’ouverture. Sans dates, le tarif standard par nuit est explicitement indicatif. Après une recherche valide, le moteur existant fournit uniquement les logements libres et leur montant total. Sur téléphone, les filtres s’ouvrent dans un dialogue natif conservé pendant les mises à jour Livewire. Le menu, les onglets et le footer partagés sont conservés.

| Critère | Résultat / preuve |
| --- | --- |
| Annonces éligibles sans dates | Tests catalogue : identité validée, résidence publiée, unité active, capacité et tarif standard |
| Aucune fausse disponibilité | Séparation tarif de référence / montant daté ; test occupation et tarifs week-end |
| Dates incorrectes | Tests URL : format incorrect, date impossible, date manquante, départ antérieur, passé |
| Filtres | Tests commune, capacité, équipements cumulés, budget total et réinitialisation |
| Ordre sponsorisé | Test catalogue et tri décroissant ; moteur daté inchangé |
| Téléphone / ordinateur | Chromium, huit largeurs de 320 à 2 560 px, panneau natif, absence de débordement |
| Parcours fiche | Liens réels transmettant les dates et les voyageurs |

## Validation

Contrôles locaux réussis : Pint, compilation Blade, build Vite, tests JavaScript/PWA et rendu des vraies vues Blade dans Chromium aux huit largeurs. Dialogue mobile ouvert/fermé par Échap sans erreur JavaScript. Validation finale réussie sur le commit applicatif `8de9258` : **421 tests / 2 621 assertions**, migrations réversibles, tests JavaScript/PWA, build et quatre parcours Chromium (gérant, accueil, partenaire/navigation, catalogue). Le nouveau parcours contrôle le catalogue sans dates, les filtres réels pendant les mises à jour Livewire, le budget total, les huit largeurs et la transmission des dates à la fiche. Le focus des filtres ne provoque aucun déplacement à l’ouverture sur ordinateur.

Preuve : https://github.com/zumradeals/gesimo-core/actions/runs/39672319094. Le commit suivant finalise uniquement le rapport. Une exécution parallèle antérieure s’était arrêtée sur une attente intermittente du parcours gérant existant ; la validation finale ci-dessus réussit intégralement. Les captures finales ordinateur, téléphone et panneau de filtres ont été inspectées.

## Vérification navigateur

Ouvrir Logements sans dates : des cartes doivent être visibles et le budget désactivé. Filtrer une commune, puis réinitialiser. Sur téléphone, ouvrir Filtres, choisir Wi-Fi si des logements le proposent et fermer le panneau. Choisir deux dates futures : les cartes affichent le montant du séjour ; appliquer un petit budget puis le retirer. Ouvrir une fiche : dates et nombre de voyageurs sont conservés.

## Décisions et limites

Maquette explicitement approuvée. Aucun ajout de dépendance, migration ou inventaire fictif en production. La limite existante de 60 annonces par recherche est conservée ; le compteur décrit les logements présentés. Les exemples de la référence HTML et les données des tests restent hors des pages publiques. Le déploiement serveur reste nécessaire après fusion.
