# Intégration de Mes réservations et du détail d'une réservation v3

La liste des réservations et le détail reprennent la maquette validée. Un bandeau « À faire en premier » montre l'urgence (payer, ajouter sa pièce d'identité, donner son avis) ; chaque carte n'a qu'un bouton principal et affiche des repères simples (temps restant, « Arrivée dans 3 jours », état de la pièce). Le détail regroupe « Avant votre arrivée », le séjour avec ses horaires, le gérant, l'adresse écrite (après confirmation), l'annulation avec la date limite et le remboursement qui s'appliquerait. Le voyageur peut corriger l'heure d'arrivée et son message jusqu'à la veille. L'espace client prend la charte orange. Une adresse précise, renseignée par le gérant, a été ajoutée à la demande du porteur. Contrat : `docs/contrats/mes-reservations-v3.md`.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Bandeau « À faire en premier » (paiement, pièce, avis) | Conforme | Tests liste ; navigateur |
| Un seul bouton principal ; Reçu et Annuler dans le détail | Conforme | Test « …compte à rebours… » (absence de Annuler/Reçu) |
| Repères : demain, aujourd'hui, dans N jours, séjour en cours | Conforme | Tests liste |
| Passées : avis, Réserver à nouveau, montant remboursé | Conforme | Test séjours passés |
| Détail confirmé : avant l'arrivée, horaires, motif, gérant, règlement | Conforme | Test détail |
| Annulation : date limite et remboursement affichés avant de cliquer | Conforme | Test annulation du détail |
| Modification de l'heure d'arrivée et du message jusqu'à la veille, journalisée | Conforme | Tests de modification (droits, valeurs invalides, jour J) |
| Montants et statut inchangés par la modification | Conforme | Test de modification |
| Adresse écrite : champ du gérant, donnée après confirmation seulement, jamais publique | Conforme | Tests adresse (fiche, recherche, profil public, autre voyageur) |
| Habillage orange limité à l'espace client | Conforme | Test habillage (gérant et admin inchangés) |
| Téléphone / ordinateur, aucun débordement de 320 à 2 560 px | Conforme | Chromium, liste et détail |

## Tests lancés

- `vendor/bin/pest` : **473 tests, 3 009 assertions, tous réussis** (456 précédents + 17 nouveaux).
- Parcours Chromium `tests/E2E/reservation.mjs` étendu et réussi : liste, détail, annulation d'une réservation non payée, aucune erreur JavaScript. Captures ordinateur et téléphone inspectées.
- Migration appliquée sur base neuve par les tests et le parcours navigateur ; Pint appliqué ; build Vite réussi.
- Non vérifié : le paiement réel (non configuré dans cet environnement), les parcours navigateur des autres lots, GitHub Actions.

## Décisions prises seul

- Le menu latéral du client reste, repeint (bleu nuit, repère orange) : cela touche aussi les autres pages de l'espace client (Mon espace, Mon profil, annulation, avis, signalement), qui prennent les mêmes couleurs sans autre refonte.
- Modifier l'adresse précise ne déclenche pas de nouvelle validation de la résidence (contrairement à la position sur la carte et au quartier) : à confirmer.
- Le bandeau de la liste ne propose que ce qui existe déjà : un séjour confirmé sans pièce d'identité fournie, un paiement en attente, un séjour terminé sans avis.
- « Appeler » utilise le numéro du gérant tel qu'il est enregistré (10 chiffres).

## Décisions en attente

- Faut-il une nouvelle validation de la résidence quand le gérant change l'adresse précise ?
- Adresse à renseigner pour les résidences existantes : le gérant l'ajoute depuis l'édition de sa résidence ; aucune reprise automatique.

## Risques et limites

- La liste lit l'état de la pièce d'identité une seule fois pour le voyageur ; elle ne distingue pas une pièce qui expirerait avant un séjour précis.
- Déploiement serveur nécessaire après fusion (migration et reconstruction des styles).

## Vérification depuis un navigateur

1. Se connecter en client avec une réservation non payée : « Mes réservations » affiche « Payez pour garder vos dates » et un bouton Payer.
2. Avec une réservation confirmée sans pièce d'identité : le bandeau propose de l'ajouter ; le détail contient le dépôt de la pièce.
3. Dans le détail d'une réservation confirmée : modifier l'heure d'arrivée et le message ; vérifier la date limite d'annulation et le remboursement annoncé.
4. Côté gérant, renseigner « Adresse précise » dans l'édition de la résidence ; vérifier qu'elle n'apparaît ni sur la fiche publique ni avant la confirmation, puis dans le détail du voyageur après confirmation.
5. Sur téléphone : le menu du bas et les cartes restent lisibles, sans défilement horizontal.
