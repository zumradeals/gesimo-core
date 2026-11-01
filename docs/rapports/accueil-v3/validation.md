# Accueil orange v3 — validation

La recherche est remontée dans le premier écran et le hero est désormais composé d'un texte à gauche et d'une photo à droite. La page adopte l'orange vif, le bleu nuit et l'ivoire de la maquette approuvée. Les cartes et sections sont plus compactes. Les communes gardent leurs photographies réelles et leur navigation manuelle. Les paramètres de marque et les données du catalogue sont conservés. La référence visuelle, les jetons et le contrat de conception sont versionnés pour les futurs lots. Les autres pages conservent leur habillage.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Vues Blade | Réussi | `php artisan view:cache` |
| Compilation | Réussi | `npm run build`, sans avertissement CSS |
| Formatage PHP | Réussi | `vendor/bin/pint --test` |
| JavaScript existant | Réussi | `node --test tests/Js/*.test.mjs` : 9 tests |
| Données et accès | Réussi localement | Tests Refonte/SiteTest et LogementsTest |
| Premier écran et parcours navigateur | En cours | `tests/E2E/accueil.mjs`, captures et CI |

Aucune migration, aucune nouvelle dépendance du projet, aucun compte ni logement créé hors de la base locale de test. La logique de réservation et de paiement n'est pas modifiée.

Les photos et annonces illustratives de la maquette ne sont pas des offres à publier. Les visuels d'ambiance existants sont utilisés et les cartes affichent uniquement les données du catalogue ; leur contenu varie donc selon l'inventaire. Le texte sur orange est bleu nuit et les petits liens utilisent un orange foncé pour préserver leur lisibilité.

Vérification manuelle : ouvrir l'accueil à 1 366 × 768 et zoom 100 %, vérifier les quatre champs et le bouton sans défilement, choisir commune et dates puis lancer la recherche ; tester le menu et les liens sur téléphone ; vérifier les liens Aide, Contact, Conditions et Mentions légales.
