# Accueil : largeur, lisibilité et retrait des offres fictives

## Changements

Correction demandée par le porteur après observation de l’intégration. Le héros photographique et le menu mobile validés sont conservés. Les conteneurs de l’accueil et de la navigation commune deviennent fluides sur ordinateur, avec environ 4 % de marge de chaque côté. Titres, explications, liens et footer sont agrandis. Une offre unique occupe une carte horizontale complète ; deux offres occupent deux colonnes, trois offres trois colonnes. Les communes utilisent toute leur grille. Les photos sélectionnent une résolution adaptée à leur largeur.

La section « Pour vous projeter », ses trois offres fictives et toutes ses mentions de démonstration sont retirées. Les annonces et données existantes en base sont préservées. Aucune migration, dépendance ni nouvelle règle métier.

Les paramètres Général existants couvrent l’identité visuelle, le slogan, les coordonnées, les réseaux sociaux et les textes institutionnels/légaux. Les titres et blocs de l’accueil restent versionnés dans le code ; aucun éditeur de sections n’est ajouté.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Largeur adaptée à l’ordinateur | Contrôlée | Aperçus Chromium à 1 024, 1 440, 1 920 et 2 560 px |
| Téléphone préservé | Contrôlé | 320 et 390 px, recherche visible au-dessus des onglets à 390 × 844 |
| Absence de débordement | Contrôlée | Sept largeurs, 320 à 2 560 px |
| Offres fictives retirées | Implémenté | Vue et tests de non-régression mis à jour |
| Données métier préservées | Implémenté | Aucune écriture ni migration ajoutée |

## Validation

Compilation Vite et Pint réussis localement. Contrôles visuels depuis les vraies vues Blade avec une offre en mémoire, sans insertion en base : aucune image cassée et aucune erreur JavaScript. Tests PHP/PostgreSQL et parcours E2E : résultat CI à compléter après exécution.

## Vérification navigateur

Ouvrir l’accueil sur ordinateur : les sections suivent les mêmes marges que le héros, les explications sont lisibles, une seule annonce ne laisse plus deux colonnes vides. « Pour vous projeter » est absent. Sur téléphone, vérifier les quatre onglets, rechercher par dates puis ouvrir un logement. Les paramètres restent accessibles aux administrateurs autorisés dans Réglages > Général.

## Décisions et limites

Application directe des corrections demandées, sans nouvelle décision métier. Les autres pages héritent du header/footer communs ; leur contenu intérieur n’est pas refondu dans ce lot. Le déploiement sur le serveur reste nécessaire après fusion.
