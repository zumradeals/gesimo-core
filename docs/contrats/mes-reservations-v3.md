# Avelis Séjours — Mes réservations et détail d'une réservation v3

## Vision

Le voyageur voit d'un coup d'œil l'état de ses séjours et ce qu'il doit faire en premier. Décisions du porteur, 2 novembre 2026 : maquette `docs/maquettes/mes-reservations-v3.html` validée ; recommandations retenues (espace client conservé et repeint, « Réserver à nouveau », modification de l'heure d'arrivée et du message jusqu'à la veille) ; **ajout d'une adresse écrite** pour chaque résidence. Charte : `docs/contrats/accueil-reference-v3.md`.

## Mes réservations

- Onglets « À venir » et « Passées » (compteurs). Rafraîchissement automatique toutes les 30 secondes.
- **Bandeau « À faire en premier »** (À venir) : paiement à finir (avec le temps restant), sinon pièce d'identité à ajouter pour le prochain séjour confirmé. Onglet Passées : « Votre avis compte » pour le dernier séjour terminé sans avis. Seules des choses déjà prévues par la plateforme y figurent.
- **Une carte par séjour** : photo, nom, résidence, dates, nuits, montant, état, repère (« Dates gardées encore… », « Arrivée dans N jours », « Arrivée demain », « Arrivée aujourd'hui », « Séjour en cours »), état de la pièce d'identité pour un séjour confirmé, montant remboursé pour une annulation, avis donné pour un séjour terminé.
- **Un seul bouton principal par carte** : Payer (en attente), Ajouter ma pièce d'identité (confirmé sans pièce), Voir le détail, Donner mon avis (terminé sans avis). Reçu et Annuler une réservation confirmée sont dans le détail. Une réservation non payée garde « Annuler cette réservation » sur sa carte. « Réserver à nouveau » renvoie vers la fiche du même logement.
- Liste vide : message simple et bouton « Chercher un séjour ».

## Détail d'une réservation

- En-tête : nom, état, résidence, repère d'arrivée.
- **En attente** : temps restant, bouton Payer, répartition du paiement (acompte et reste au gérant selon la résidence). Les coordonnées du gérant et l'adresse ne sont pas données.
- **Confirmée** : « Avant votre arrivée » (paiement, pièce d'identité avec dépôt directement dans la page, contact du gérant).
- **Votre séjour** : dates avec « à partir de » l'heure d'arrivée standard et « avant » l'heure de départ de la réservation, durée, voyageur, motif, heure d'arrivée prévue, message au gérant.
- **Modification** : l'heure d'arrivée prévue et le message au gérant se modifient **jusqu'à la veille de l'arrivée** (réservation en attente ou confirmée, par le voyageur seul). Rien d'autre ne change : ni dates, ni prix, ni paiement. Chaque modification est inscrite au journal d'audit (sans le texte du message).
- **Le gérant et le lieu** (après confirmation seulement) : nom, téléphone, bouton Appeler, **adresse précise**, bouton Voir sur la carte, règlement de la résidence.
- **Annulation** (réservation confirmée) : date limite d'annulation gratuite (condition flexible), et remboursement qui s'appliquerait si le voyageur annulait maintenant (calcul existant d'aperçu d'annulation, tenant compte des conditions figées à la réservation). Le bouton mène à la page d'annulation existante.
- Reçu, suivi, avis, signalement d'un problème : inchangés sur le fond.

## Adresse écrite de la résidence

- Nouveau champ facultatif « Adresse précise » (255 caractères) renseigné par le gérant dans l'édition de sa résidence.
- Donnée au voyageur **seulement après la confirmation** de sa réservation (détail et page de confirmation). Jamais sur la fiche du logement, la recherche, le profil public du gérant. Sans adresse précise, le détail le dit simplement et la carte reste proposée.
- Les résidences existantes n'ont pas d'adresse : le gérant peut l'ajouter quand il veut. Modifier l'adresse ne demande pas de nouvelle validation de la résidence (décision de mise en œuvre à confirmer).

## Habillage

L'espace client (menu latéral sur ordinateur, menu du bas sur téléphone) garde sa structure et prend la charte orange : menu bleu nuit, repère orange, boutons orange, cartes blanches sur fond clair. Les espaces gérant et administration ne changent pas. Classe `espace-client` sur la page ; styles dans `resources/css/mes-reservations.css`, jetons de `accueil-reference.css`.

## Implémentation

- `MesReservations` et `DetailReservation` (Livewire), vues `mes-reservations` et `detail-reservation`.
- `ReservationService::modifierInformations` et `informationsModifiables` ; droit `ReservationPolicy::modifierInformations`.
- Migration réversible `2026_11_02_000001_ajouter_adresse_residence` : colonne `residence.adresse`. Aucune règle financière modifiée, aucune dépendance ajoutée.

## Vérification

Tests Pest (liste, détail, modification, adresse, habillage) ; parcours navigateur `tests/E2E/reservation.mjs` étendu : liste, détail, annulation d'une réservation non payée, aucun débordement de 320 à 2 560 px.
