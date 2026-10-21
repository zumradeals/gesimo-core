# Correction du parcours gérant — 21 octobre 2026

## Périmètre et constat

Relecture du dépôt `zumradeals/gesimo-core`, base `047961f`, et des trois captures fournies par le porteur.
La capture de la soumission montre une pièce d'identité déjà enregistrée et un justificatif du droit de gérer absent.
Elle ne prouve pas à elle seule une panne du stockage. Le parcours présentait néanmoins des défauts confirmés dans le code.

## Corrections

- Les boutons « Étape suivante » et les onglets appliquent les contrôles côté serveur. Les données valides de la résidence sont enregistrées avant d'avancer ; les champs obligatoires vides ou composés d'espaces bloquent le passage.
- Un logement en cours de saisie est contrôlé et enregistré avant l'étape suivante. Sans logement actif avec un tarif normal, le gérant reste à l'étape 2. La soumission applique aussi ces contrôles.
- « Envoyer pour vérification » enregistre les fichiers sélectionnés : le bouton séparé d'enregistrement reste disponible pour sauvegarder les pièces sans soumettre.
- L'écran explique les deux documents et leurs formats. Le transfert bloque temporairement les deux boutons. Une pièce enregistrée, non refusée et encore présente sur disque est reconnue après rechargement ; un document refusé ou indisponible doit être remplacé.
- Une sauvegarde sans fichier ne prétend plus avoir enregistré des pièces et ne fait plus repasser une résidence validée en vérification.
- Le stockage vérifie le résultat de l'écriture ; les mises à jour des documents sont transactionnelles et journalisées. En cas d'échec, les anciennes références restent intactes et les nouveaux fichiers du lot sont retirés. Les messages destinés au gérant n'exposent pas les erreurs internes.
- Une soumission répétée conserve les pièces, ne recrée pas la soumission en attente et ne dépublie pas une résidence déjà validée avec badge. Le remplacement réel d'une pièce conserve la règle existante de nouvelle vérification.
- Les prix négatifs, décimaux ou contenant des lettres sont refusés au lieu d'être transformés en montants positifs. Les espaces ordinaires et insécables restent acceptés. Les équipements sélectionnés sont contrôlés avant écriture.
- Le calendrier affiche un état explicatif sans erreur 404 lorsqu'aucun logement actif n'existe. Le sélecteur de résidence reste accessible. Changer de résidence efface les anciennes dates en cours de saisie. Les mois invalides ne font plus planter la lecture du calendrier.

## Validation

Exécution complète sur GitHub Actions, commit applicatif `86b8ad6530aec2bcc181c194d93a09e393f7cd46` :
https://github.com/zumradeals/gesimo-core/actions/runs/39598110244

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Suite métier PostgreSQL et concurrence | Réussi | 373 tests, 2 344 assertions, dont 27 nouveaux cas dans `SoumissionResidenceTest.php` |
| Envoi des deux documents, conservation après rechargement et second clic | Réussi | Nouveaux tests Livewire et vérification de la présence privée des fichiers |
| Échecs de stockage et d'écriture en base | Réussi | Pannes simulées : aucun faux succès, conservation des anciennes pièces et nettoyage des nouveaux fichiers en échec |
| Contrôles des étapes, logements et tarifs | Réussi | Tests des onglets, boutons, soumission directe, montants invalides et espaces insécables |
| Calendrier vide ou logements désactivés | Réussi | Tests de changement de résidence et refus d'une action de blocage sans logement |
| Droits existants | Réussi | Suite de sécurité et parcours gérant/admin existants toujours réussis |
| Formatage | Réussi | `vendor/bin/pint --test`, localement et dans GitHub Actions |
| Compilation des écrans | Réussi | `php artisan view:cache` localement |
| Migrations : création, retour et réapplication | Réussi | Vérification GitHub Actions existante |
| Construction du site | Réussi | `npm ci --ignore-scripts` puis `npm run build` localement ; build GitHub Actions réussi |
| Fonctionnement JavaScript hors connexion | Réussi | `node --test tests/Js/sw.test.mjs` localement |
| Essai interactif dans le navigateur de production | Non testé | Pas d'accès au serveur ni à la session du gérant utilisé pour ces corrections |

L'environnement local dispose de PHP pour le formatage et la compilation ; les tests PostgreSQL ont été exécutés sur GitHub Actions. L'essai local de la suite métier ne pouvait pas démarrer faute de serveur PostgreSQL local.

## Décisions et limites

Aucune nouvelle dépendance, aucune modification du schéma, aucune modification des règles financières et aucune refonte graphique.
Les modifications portent sur les écrans existants et leurs contrôles.
L'exigence du justificatif du droit de gérer reste appliquée à chaque résidence. Les pièces déjà enregistrées sont réutilisées lors des prochaines soumissions **de cette même résidence**.
La mutualisation de l'identité du gérant entre plusieurs résidences n'est pas réalisée dans cette correction : elle demande un contrat distinct sur le propriétaire du document, son remplacement et sa validation.
Les anciens fichiers privés remplacés restent conservés comme dans la version précédente ; une politique de conservation et de purge reste à définir séparément.
La capture « Aujourd'hui » et la liste des résidences ne montrent pas à elles seules de panne supplémentaire ; leur harmonisation graphique reste un travail distinct.

## Recette depuis un navigateur après mise à jour du serveur

1. Ouvrir une résidence, effacer le nom ou la commune, puis utiliser « Étape suivante » et les onglets : rester sur les champs en erreur.
2. À l'étape logements, ouvrir un nouveau logement et laisser son prix vide : le passage à l'envoi reste bloqué. Compléter le logement puis continuer : il est enregistré.
3. Sélectionner une pièce d'identité et un justificatif de gestion, attendre la fin du transfert puis cliquer directement sur « Envoyer pour vérification » : les pièces sont enregistrées et la résidence est soumise.
4. Recharger et vérifier les mentions « Déjà envoyé ». Une nouvelle soumission ne redemande pas ces mêmes documents.
5. Ne fournir que l'identité : le message explique que le justificatif de gestion manque ; l'identité déjà enregistrée reste conservée.
6. Essayer un fichier au mauvais format ou dépassant 5 Mo : voir l'erreur au bon champ, sans confirmation de succès.
7. Ouvrir le calendrier d'une résidence sans logement actif puis choisir une autre résidence : la page et le sélecteur restent utilisables.
8. Sur une résidence validée, enregistrer sans nouvelle pièce : elle reste en ligne. Remplacer réellement une pièce : elle repasse en vérification conformément à la règle existante.

## Mise en ligne

La fusion GitHub ne met pas à jour le VPS. Appliquer la procédure existante de `DEPLOIEMENT.md`, section « Mettre à jour le site », puis effectuer la recette ci-dessus.
