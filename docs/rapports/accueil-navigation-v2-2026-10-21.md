# Accueil et navigation — validation du 21 octobre 2026

## Réalisation

Les maquettes ordinateur puis téléphone ont été validées en conversation. L’accueil reprend la direction chaleureuse vert/turquoise/sable : visuel principal, recherche, offres réelles, démonstration, confiance, parcours, partenaires et FAQ.
Le header et le footer communs ont été harmonisés avec cette direction.
Les quatre onglets mobiles sont visibles dans le navigateur et la PWA, avec un onglet actif et la zone de sécurité du téléphone.
Les espaces métier gérant et administrateur conservent leur navigation ; le site public adapte l’accès aux réservations au rôle.
Le visuel principal reste remplaçable dans les Réglages. Coordonnées, marque, réseaux et informations légales continuent à provenir de la base.
Les trois exemples fictifs demandés sont clairement séparés et non réservables ; ils ne créent aucun compte, logement, paiement ou disponibilité.
Les illustrations publiques sont aussi prises en charge par le cache PWA, avec un test de coupure réseau ; les pièces privées restent exclues.
Les annonces réelles et les données déjà présentes sont conservées. Aucun seeder n’a été lancé sur le serveur.

## Contrat

`docs/contrats/accueil-navigation-v2.md`, maquettes validées dans `docs/maquettes/accueil-v2/`.
Les exemples géographiques de la conception ont été remplacés par les communes réellement publiées. Les quartiers de Riviera et Zone 4 ne sont pas présentés comme des communes.
Les visuels générés servent uniquement à l’illustration de marque et aux exemples fictifs, jamais à inventer une photo d’annonce réelle.

## Vérifications

| Critère | Résultat / preuve |
| --- | --- |
| Compilation Blade | `php artisan view:cache` réussi |
| Construction | `npm run build` réussi ; CSS compressé ≈ 16 Ko, pas de police externe |
| JavaScript PWA | `node --test tests/Js/*.test.mjs` réussi, dont les 8 scénarios d’invitation et les essais de cache |
| Contrôle visuel local | Vraies vues Blade rendues avec données de présentation simulées, inspectées dans Chromium à 320, 390, 768, 1024 et 1440 px ; aucune image cassée, aucun débordement horizontal ni erreur JavaScript |
| Navigation mobile | Visible sous 1024 px, masquée sur ordinateur ; espace inférieur réservé, footer et boutons accessibles |
| Données fictives | Test HTML : trois cartes, aucune ancre, aucun formulaire ni bouton ; nombre de logements inchangé après lecture de l’accueil |
| Données réelles et confidentialité | Tests existants réutilisés : logements publiés, avis réels, marque et coordonnées dynamiques ; nouvelles communes limitées aux gérants éligibles |
| Parcours réel | Test Chromium ajouté : recherche GET depuis l’accueil, résultat réel, onglets, protection connexion, compte client et liens du footer |

La recette visuelle locale ne remplace pas le parcours PostgreSQL : elle utilise des réglages simulés, sans base métier locale.

**Vérification complète réussie sur le code applicatif `45a861295ea96307cbaa00993d0d0f452cc339d9`** : https://github.com/zumradeals/gesimo-core/actions/runs/39650553085 — 405 tests, 2 512 assertions ; formatage, migrations aller-retour, tests JavaScript, build Vite, parcours gérant existant et nouveau parcours accueil/navigation réussis.
Les captures réelles ordinateur/téléphone sont jointes à l’artefact `parcours-gerant` de cette exécution (rétention 7 jours). La vérification réelle couvre la transmission des dates/commune/voyageurs, un résultat publié, la connexion requise pour les réservations, le compte client, les quatre onglets, les photos chargées, les liens du footer et l’absence de débordement de 320 à 1440 px.
La dernière inspection de la capture mobile a conduit à réduire la hauteur du hero sur téléphone afin que le bouton de recherche initial soit entièrement visible au-dessus des onglets à 390 × 844 px. Un contrôle navigateur explicite de cette position a été ajouté ; la typographie ordinateur reste indépendante.

## Décisions et limites

Les exemples de démonstration sont une section éditoriale versionnée, sans nouvel éditeur administratif. Ils sont toujours accompagnés de mentions explicites, y compris leurs prix. Le lien de recherche placé hors des cartes mène exclusivement aux vraies offres.
Aucune modification de logique financière ni de processus de réservation, aucune nouvelle dépendance, aucune migration et aucune modification des données d’exploitation.
Les pages secondaires héritent de l’en-tête, du pied de page et du menu ; leur contenu attend ses propres maquettes avant refonte.
Le déploiement sur le VPS reste distinct de la fusion GitHub et n’a pas été réalisé ici.

## Recette après mise à jour

1. Sur ordinateur : vérifier le header complet, le visuel, la barre de recherche et le footer.
2. Sur téléphone : parcourir l’accueil ; ouvrir FAQ, mentions légales et les quatre onglets ; vérifier que le dernier lien du footer reste accessible au-dessus du menu.
3. Depuis l’accueil, choisir commune, arrivée, départ et voyageurs : les critères sont transmis à la recherche existante.
4. Sans connexion, ouvrir Réservations puis Compte : connexion requise ; une fois client connecté, retrouver les mêmes quatre onglets et le bon état actif.
5. Vérifier que les offres fictives sont identifiées et non cliquables, tandis que les annonces publiées ouvrent leur véritable fiche.
6. Modifier slogan ou image principale dans les Réglages : ces valeurs remplacent les valeurs par défaut.
