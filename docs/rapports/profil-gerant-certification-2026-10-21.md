# Profil gérant et certifications — validation du 21 octobre 2026

## Réalisation

Les quatre maquettes et les règles ont été approuvées par le porteur en conversation.
Le gérant dispose d’un dossier privé commun à ses résidences, avec identité obligatoire et certificat de résidence facultatif.
La dernière étape d’une résidence réutilise cette identité et ne demande plus de pièce personnelle.
Le justificatif de gestion reste facultatif et attribue uniquement le badge de la résidence contrôlée.
La soumission réussie ramène à la liste avec une confirmation et un lien de suivi.
Le profil public présente les seules offres publiées, sans adresse personnelle ni coordonnées privées.
Les décisions administratives et consultations de pièces sont autorisées côté serveur et auditées.
La migration conserve les fichiers et signale les anciennes identités divergentes pour contrôle.

## Critères et preuves

| Critère | Preuve |
| --- | --- |
| Identité réutilisée entre résidences | Tests Livewire de centralisation et de soumission, sans copie par annonce |
| Facultatifs indépendants | Matrice identité/certificat/gestion dans `ProfilGerantTest.php` |
| Absence d’identité acceptée | Refus de soumission directe et d’approbation administrative ; exclusion recherche, fiche et réservation |
| Renouvellement sans coupure | Ancienne preuve acceptée conservée pendant l’examen et le refus de sa remplaçante |
| Fichiers invalides ou indisponibles | Aucun faux succès ; anciennes références conservées ; fichier requis avant acceptation |
| Droits et confidentialité | Tests propriétaire/autre gérant/client/admin, liens privés, audit, HTML public sans données privées |
| Étapes obligatoires | Tests de non-régression `SoumissionResidenceTest.php` : champs vides, espaces, logement et tarif |
| Reprise prudente | Test de migration sur identités divergentes et ancien badge, sans suppression des sources |

## Vérifications exécutées

La suite PostgreSQL a réussi : **402 tests, 2 492 assertions**. Le parcours réel Chromium a également réussi : dépôt des deux fichiers, acceptation de l’identité, soumission sans justificatif facultatif, redirection mobile, publication sans badge ni certification, puis attribution séparée des deux reconnaissances.
Première recette complète réussie : https://github.com/zumradeals/gesimo-core/actions/runs/39611558608 (commit `d8af527`).

L’inspection des captures a relevé un chemin de photo utilisant `localhost` ; le disque public utilise désormais `/storage` sur le domaine courant. La recette finale vérifie aussi le chargement effectif des photos et le blocage du bouton suivant sur un nom vide. **Recette finale réussie**, commit applicatif `b38d4b875d4d30c000b9cba3847310068001b402` : https://github.com/zumradeals/gesimo-core/actions/runs/39611929016 — 402 tests / 2 492 assertions, formatage, migrations aller-retour, construction Vite et parcours Chromium réussis. Le navigateur confirme les champs obligatoires, les photos chargées, l’absence de débordement horizontal public à 390 px et l’absence d’erreurs JavaScript.
Les captures fictives du parcours sont téléchargeables depuis l’artefact `parcours-gerant` de cette exécution (conservation CI : 7 jours).
Le navigateur de test fonctionne en environnement local avec une base fictive préparée exclusivement en environnement `testing`. Cette distinction est nécessaire : Livewire réserve ce dernier environnement à son disque simulé de tests unitaires. Aucun contournement d’authentification n’est ajouté au site.

Compilation locale des vues (`php artisan view:cache`) et formatage (`vendor/bin/pint --dirty`) réussis.
Les 22 scénarios JavaScript du service worker réussissent (`node --test tests/Js/sw.test.mjs`), notamment l’exclusion des documents et espaces privés du cache.
Les tests PostgreSQL sont exécutés dans GitHub Actions ; aucun serveur PostgreSQL local exploitable dans cet environnement.

## Décisions et limites

Le contrat `docs/contrats/profil-gerant-certification-v1.md` est la référence des règles approuvées.
Une ancienne identité déjà acceptée reste valable pendant un renouvellement. Aucun délai d’expiration ou de purge n’a été inventé.
Le nom légal validé est verrouillé pour le gérant ; sa correction est réservée à l’administration après contrôle, avec audit.
Les profils publics sans offre publiée répondent 404 ; le propriétaire dispose d’un aperçu privé même sans offre publiée.
Playwright est installé temporairement dans la CI pour vérifier les vrais transferts de fichiers et les interfaces mobile/ordinateur. Il n’ajoute aucune dépendance d’exécution au site.

## Déploiement et données historiques

La fusion du code ne met pas à jour le VPS. Le serveur n’a pas été modifié durant ce travail.
Avant application de la migration : sauvegarder base, documents privés et clé de chiffrement ; examiner les dossiers dont l’identité manque ou présente plusieurs chemins différents. Ces dossiers ne sont pas réputés publiables selon la nouvelle règle.
Les anciens badges restent conservés en base mais leur nouvelle signification nécessite une confirmation administrative explicite du droit de gestion.
Le retour arrière sauvegarde les nouveaux profils et leurs documents dans une archive privée chiffrée avant suppression du schéma. Cette archive demande la même clé et une restauration manuelle ; elle ne remplace pas une sauvegarde complète avant déploiement.

## Recette après mise à jour

1. Compléter « Mon profil gérant », déposer l’identité et, facultativement, le certificat de résidence.
2. Dans l’administration, contrôler et accepter seulement l’identité : le gérant peut soumettre mais n’est pas certifié.
3. Soumettre une résidence complète sans justificatif de gestion : retour à « Mes résidences », confirmation nommée et suivi du dossier.
4. Approuver l’annonce sans badge : elle apparaît sur le profil public, sans exposer les informations privées.
5. Accepter le certificat : seule la mention « Gérant certifié » apparaît.
6. Ajouter puis accepter un justificatif de gestion : seule la résidence concernée reçoit son badge.
7. Refuser un certificat ou un justificatif facultatif : la résidence demeure publiable si son identité et son annonce sont acceptées.
8. Remplacer l’identité : l’ancienne acceptation reste valable pendant le contrôle ; le nouveau document ne remplace l’ancien qu’après accord.
9. Vérifier sur téléphone les champs obligatoires, erreurs, boutons et confirmation ; ouvrir le profil en visiteur anonyme.
