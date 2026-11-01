# Accueil orange v3 — validation

La recherche est remontée dans le premier écran et le hero est désormais composé d'un texte à gauche et d'une photo à droite. La page adopte l'orange vif, le bleu nuit et l'ivoire de la maquette approuvée. Les cartes et sections sont plus compactes. Les communes gardent leurs photographies réelles et leur navigation manuelle. Les paramètres de marque et les données du catalogue sont conservés. La référence visuelle, les jetons et le contrat de conception sont versionnés pour les futurs lots. Les autres pages conservent leur habillage.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Vues Blade | Réussi | `php artisan view:cache` |
| Compilation | Réussi | `npm run build`, sans avertissement CSS |
| Formatage PHP | Réussi | `vendor/bin/pint --test` |
| JavaScript existant | Réussi | `node --test tests/Js/*.test.mjs` : 9 tests |
| Données et accès | Réussi | CI 41328248426 : 424 tests PHP, 2 637 assertions |
| Premier écran et parcours navigateur | Réussi localement | `tests/E2E/accueil.mjs`, captures 1 366 × 768 et téléphone |
| Menus et catalogue | Réussi localement | Parcours `profil-gerant.mjs`, `partenaire-navigation.mjs` et `logements.mjs` |

Aucune migration, aucune nouvelle dépendance du projet, aucun compte ni logement créé hors de la base locale de test. La logique de réservation et de paiement n'est pas modifiée.

Les photos et annonces illustratives de la maquette ne sont pas des offres à publier. Les visuels d'ambiance existants sont utilisés et les cartes affichent uniquement les données du catalogue ; leur contenu varie donc selon l'inventaire. Le texte sur orange est bleu nuit et les petits liens utilisent un orange foncé pour préserver leur lisibilité.

Vérification manuelle : ouvrir l'accueil à 1 366 × 768 et zoom 100 %, vérifier les quatre champs et le bouton sans défilement, choisir commune et dates puis lancer la recherche ; tester le menu et les liens sur téléphone ; vérifier les liens Aide, Contact, Conditions et Mentions légales.

## Preuves et environnement

Captures : `accueil-premier-ecran-1366.webp` et `accueil-telephone-ecran.webp`. Les aplats des cartes viennent des images synthétiques de la base de test ; ils ne remplacent pas les photographies du catalogue réel.

La première CI a validé les 424 tests PHP, les migrations, le JavaScript et la compilation, puis a été arrêtée par l’incompatibilité entre Playwright 1.51.1 et le nouvel Ubuntu de `ubuntu-latest`. Le runner est fixé à `ubuntu-24.04`, compatible avec l’outil existant, sans changer de dépendance. La CI finale doit être verte avant fusion.
