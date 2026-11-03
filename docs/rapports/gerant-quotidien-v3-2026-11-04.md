# Rapport de validation — gérant au quotidien, vérification et itinéraire (lot 5)

## Ce qui a été fait
- **Vérification du gérant** : deux documents (pièce d'identité + contrat de bail de chaque résidence) obligatoires pour publier, un seul badge « Gérant vérifié », page « Ma vérification » avec quatre états, bandeau « À faire en premier » sur Aujourd'hui.
- **Itinéraire** : page « Accès à la résidence » pour le gérant (position du téléphone ou lien Google Maps, repères) ; page « Comment venir » pour le voyageur, après confirmation seulement (bouton Google Maps / Waze / Plans, repères, appel, WhatsApp pour un chauffeur).
- Aujourd'hui, Réservations, Calendrier et Réservation par téléphone à la charte orange, avec le menu bleu nuit du gérant.
- Migration réversible : champ `residence.reperes`.

## Critères
| Critère | Résultat | Preuve |
|---|---|---|
| Publier exige identité + bail contrôlé | Conforme | tests « publier exige l'identité… », « l'admin ne peut pas mettre en ligne… », « la soumission sans contrat de bail est refusée… » |
| Résidences déjà publiées restent en ligne | Conforme | test « Aujourd'hui demande d'abord… » |
| Badge unique « Gérant vérifié » | Conforme | test « …s'affiche sur la page de vérification et le profil public » |
| Quatre états des documents, motif du refus | Conforme | tests « Ma vérification montre… », « un bail refusé affiche le motif… » |
| Position par téléphone ou lien ; lien court expliqué | Conforme | tests et navigateur (position relevée, lien refusé avec message) |
| Rien de précis avant la confirmation | Conforme | test « Comment venir avant la confirmation… » + navigateur |
| Comment venir réservé au voyageur | Conforme | test « …réservé au voyageur de la réservation » |
| Déplacement > 500 m : résidence revue ; petit écart : non | Conforme | test « placer la résidence la première fois… » |
| Pas de débordement, pas d'erreur JavaScript | Conforme | `tests/E2E/gerant-quotidien.mjs` à 1440 et 390 px |
| Aucune régression | Conforme | 543 tests passent |

## Tests lancés
- `vendor/bin/pest` : 543 tests, tout passe (24 nouveaux ; 9 anciens adaptés à la nouvelle règle).
- Parcours navigateur : « Parcours gérant au quotidien : OK ».
- Captures avant / après : `docs/rapports/gerant-quotidien-v3/`.

## Décisions prises seul
- **Seuil de 500 m** : l'ancienne règle faisait revoir une résidence validée à toute modification de ses coordonnées ; avec « Utiliser ma position », un simple écart du GPS l'aurait dépubliée. Au-delà de 500 m, elle repasse en vérification. À confirmer ou ajuster.
- Le badge « Gérant vérifié » est affiché sur les annonces dont la résidence a son bail accepté ; sur le profil public, dès qu'une résidence a le sien.
- Le certificat de résidence n'est plus déposable ; les anciens documents restent visibles de l'administration.
- Un bail « refusé » bloque l'envoi jusqu'à son remplacement ; un bail « en contrôle » ne le bloque pas.

## Décisions en attente
- Dépublier ou non, à une date donnée, les résidences déjà publiées sans bail (aujourd'hui : elles restent en ligne).

## Risques et limites
- La position utilise la localisation du navigateur : elle demande l'autorisation du téléphone et fonctionne seulement sur une adresse sécurisée (https) en production.
- Le bouton « Me conduire » ouvre l'application de cartes du téléphone ; le comportement exact dépend de l'appareil.
- La page « Ma vérification » dépose les fichiers mais l'équipe Avelis les contrôle toujours depuis l'administration existante (écran refondu au lot 7).
- Les pages Mes résidences, Argent, Profil gérant, Mises en avant, Personnel et Aide du gérant gardent leur ancienne présentation (lot 6).

## Comment vérifier depuis un navigateur
Gérant : Aujourd'hui (bandeau de vérification), Ma vérification (déposez un document), Mes résidences → une résidence → « Accès à la résidence » (touchez « Utiliser ma position actuelle »). Voyageur : une réservation confirmée → « Comment venir » ; une réservation en attente de paiement ne montre rien de précis.
