# Cartes de communes — 21 octobre 2026

## Réalisation

À la demande du porteur, la section Cocody est remplacée par une carte compacte assortie à l’accueil. Elle comporte un aperçu réel, le nom de la commune, le nombre de logements publiés et un lien vers la recherche filtrée. Une carte unique reste limitée à 420 px sur ordinateur, en regard du titre ; sur téléphone elle utilise la largeur disponible. La même présentation s’applique aux autres communes.

L’aperçu est sélectionné parmi les photos des résidences publiées disposant d’une unité active, en excluant les photos d’unités inactives. La sélection est déterministe et ne charge qu’une photo par commune. En l’absence de photo, le composant existant affiche un emplacement neutre. Aucune image fictive ni donnée métier n’est créée ou remplacée. La photo actuelle de l’annonce reste donc celle utilisée à Cocody.

| Critère | Résultat / preuve |
| --- | --- |
| Carte compacte | Largeur maximale 420 px et contrôle E2E sur ordinateur |
| Aperçu réel | Test PHP avec photo publiée et exclusions privées/inactives |
| Sans photo | Test PHP du lien et de l’emplacement sans image inventée |
| Navigation | Clic sur la carte puis recherche filtrée en E2E |
| Responsive | Contrôle de 320 à 2 560 px |

## Validation

Build Vite, Pint, compilation Blade et syntaxe JavaScript contrôlés localement. Résultat de la CI à compléter après exécution.

## Vérification navigateur

Sur l’accueil, descendre à « Choisissez votre commune ». Vérifier la carte Cocody, son aperçu et son compteur. Cliquer sur la carte : la recherche doit être filtrée sur Cocody. Sur ordinateur, la carte reste compacte ; sur téléphone, elle reste lisible et accessible.

## Décisions et limites

Adaptation visuelle explicitement demandée ; pas de nouvelle règle de publication. Aucun schéma, dépendance ou réglage ajouté. Le déploiement serveur reste nécessaire après fusion.
