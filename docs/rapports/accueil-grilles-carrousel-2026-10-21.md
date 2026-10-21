# Accueil : cartes compactes, offres ordinaires et carrousel

## Changements

Affinements demandés après la capture de 19 h 16. Le héros et son formulaire ne sont pas modifiés. Les sections suivantes ont des marges légèrement renforcées et une largeur plafonnée à 1 920 px. Les annonces gardent une carte compacte même lorsque le catalogue ne contient qu’une offre.

« Logements à la une » présente jusqu’à six annonces éligibles dont la résidence bénéficie d’une mise en avant active, selon le service existant. « À chaque séjour, son cadre » est rétablie et présente jusqu’à six offres ordinaires éligibles. Les cartes sont communes aux deux sections ; aucune duplication entre elles ni offre fictive publique. Si l’inventaire est inférieur à six, seules les offres existantes sont affichées ; une section sans offre est masquée.

Les communes sont disposées sur une seule ligne : quatre visibles de 1 024 à 1 439 px, cinq au-delà, selon le nombre de communes existantes. Le reste défile par flèches, clavier ou glissement tactile. Les flèches sont désactivées aux extrémités. Sans JavaScript, le défilement natif reste disponible. Pas de rotation automatique, de duplication ou de nouvelle dépendance ; préférence de réduction des mouvements respectée. Toutes les communes éligibles sont présentes, sans troncature aux six premières.

## Vérification

| Critère | Preuve prévue |
| --- | --- |
| Six annonces dans chaque catégorie, sans doublon | Test PHP et inventaire E2E local uniquement |
| Catalogue réduit, sans faux remplissage | Test PHP d’une offre unique |
| Mises en avant expirées traitées comme ordinaires | Test PHP |
| Carrousel à quatre/cinq cartes | Géométrie Chromium aux largeurs ordinateur |
| Flèches et clavier | Défilement réel contrôlé dans Chromium |
| Téléphone | 320 et 390 px sans débordement global |

Compilation Vite, Pint et compilation Blade contrôlés localement. Résultat CI à compléter après exécution. Les données supplémentaires du navigateur sont isolées dans un script qui refuse de fonctionner hors de l’environnement testing.

## Vérification manuelle

Ouvrir l’accueil sur ordinateur et téléphone. Le héros garde sa présentation. Les annonces sont compactes, les offres ordinaires réapparaissent et les communes restent sur une seule ligne. Cliquer sur les flèches, puis sur une commune pour vérifier la recherche filtrée. Avec moins de six mises en avant réelles, le site ne crée pas de cartes supplémentaires.

## Décisions et limites

Organisation explicitée au porteur : mises en avant actives d’un côté, offres ordinaires de l’autre. Aucun changement aux paiements, conditions d’éligibilité, badges ou données de production. Le déploiement serveur reste nécessaire après fusion.
