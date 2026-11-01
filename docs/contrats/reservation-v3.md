# Avelis Séjours — parcours de réservation v3

## Vision

Réserver doit être très facile, y compris pour une personne peu à l'aise avec le numérique : on choisit une date d'arrivée et un nombre de nuits, on dit qui voyage et pourquoi, on paie. Le gérant reçoit ce dont il a besoin pour accueillir, sans exposer plus de données que nécessaire.

Décisions du porteur, 1er novembre 2026 : maquette `docs/maquettes/reservation-v3.html` validée ; arrivée + nombre de nuits à la place d'une plage de calendrier ; identité du voyageur selon l'option « mixte » ; motif du séjour obligatoire ; réglages recommandés validés. La charte visuelle est celle de `docs/contrats/accueil-reference-v3.md`.

## Parcours

1. **Fiche du logement** : date d'arrivée, nombre de nuits (boutons − et +, raccourcis 1 nuit, week-end, 1 semaine, 2 semaines, 1 mois), voyageurs (− et +, jamais au-dessus de la capacité). Le départ est calculé et affiché avec l'heure de départ. Le prix tient en lignes groupées (« 5 nuits × 10 000 FCFA ») avec le détail nuit par nuit repliable ; le total est « sans frais cachés ». La règle d'annulation affiche la vraie date et l'heure limites.
2. **Vos informations** (étape 1) : nom comme sur la pièce, téléphone, motif du séjour (obligatoire), heure d'arrivée et message au gérant (facultatifs), une seule case d'accord (règlement de la résidence et conditions générales). « Continuer vers le paiement » garde les dates pour la durée réglée dans Paramètres.
3. **Paiement** (étape 2) : inchangé sur le fond (total ou acompte selon la résidence), avec rappel du voyageur et du motif.
4. **Confirmation** (étape 3) : bloc « Ajoutez votre pièce d'identité » (photo ou fichier), possibilité de le faire plus tard, prochaines étapes, contact du gérant.
5. **Mon profil** (client) : la pièce est déposée une seule fois, remplaçable, supprimable, réutilisée à chaque réservation.

Cas particuliers : visiteur non connecté (renvoyé vers la connexion ou l'inscription avec message « Vos dates sont conservées », puis retour sur la réservation) ; dates prises (message et trois propositions de dates libres pour le même nombre de nuits) ; séjour hors limites (message simple, invitation à contacter Avelis au-delà du maximum) ; délai de garde terminé et paiement refusé : écrans existants.

## Règles

| Sujet | Règle |
| --- | --- |
| Séjour minimum | Réglage « Séjour minimum (nuits) », défaut **1**, de 1 à 30. |
| Séjour maximum | Réglage « Séjour maximum (nuits) », défaut **30**, de 1 à 365. Au-delà, pas de réservation en ligne : message et lien vers Contact. Le minimum ne peut pas dépasser le maximum. Les réservations prises par téléphone par le gérant ne sont pas limitées. |
| Heure de départ | Réglage, défaut **11 h**. Affichée sur la fiche et à l'étape informations ; l'heure en vigueur est figée dans la réservation à sa création. |
| Heure d'arrivée affichée | Le réglage existant « Heure d'arrivée supposée » (14 h) est repris : « Arrivée à partir de 14 h ». |
| Garde des dates | Réglage existant « Durée du blocage des dates » (défaut 120 min) ; la maquette montrait 30 min à titre d'exemple. |
| Paiement | Total ou acompte selon la résidence, règles inchangées. |
| Motif | Sept choix : vacances, affaires, famille, événement, études, soins médicaux, autre (avec précision obligatoire). Visible du gérant de la réservation seulement, sans effet sur le prix ni sur l'acceptation. Un motif « Événement » rappelle le règlement de la résidence. |
| Accord | Le moment où le voyageur accepte le règlement et les conditions est enregistré dans la réservation. |

## Pièce d'identité du voyageur (option « mixte »)

- **Types acceptés** : carte nationale d'identité, passeport, titre de séjour, autre pièce officielle. Formats JPG, PNG ou PDF, 5 Mo au maximum, date de fin de validité facultative (une pièce expirée compte comme « à fournir »).
- **Rien n'est exigé avant le paiement.** La pièce est demandée juste après la confirmation et doit être fournie avant l'arrivée. Un rappel part 2 jours avant l'arrivée aux voyageurs qui ne l'ont pas encore ajoutée.
- **Une pièce par compte**, conservée dans le profil tant que le voyageur ne la supprime pas. Suppression refusée tant qu'une réservation confirmée ou en cours existe.
- **Protection** : fichier chiffré par l'application sur le disque privé, jamais public, jamais dans les journaux ; aucun numéro de pièce n'est saisi par le voyageur.
- **Accès** : le gérant de la réservation et son personnel ayant le droit « arrivées et départs », seulement si la réservation est confirmée, et jusqu'à **7 jours après le départ** (réglage « Accès du gérant à la pièce d'identité », 0 à 90 jours). Pas d'accès pour l'administration, un autre gérant, un autre client. Chaque ouverture est inscrite au journal d'audit.
- **États vus du gérant** : « À fournir », « Fournie », « Vérifiée à l'arrivée » (dès que l'arrivée est enregistrée avec la pièce originale contrôlée). Une pièce manquante affiche un avertissement mais **ne bloque pas** l'arrivée : le gérant décide à l'accueil.

## Implémentation

- Réglages ajoutés dans la rubrique Réservation (`Reglages::definitions`), lus par `ReglesSejour`.
- Migration `2026_11_01_000001_preparer_reservation_v3` (réversible) : colonnes `motif_sejour`, `motif_precision`, `heure_arrivee_prevue`, `message_gerant`, `conditions_acceptees_le`, `heure_depart` sur `reservation` ; table `piece_voyageur`. Le nom et le téléphone du voyageur réutilisent `nom_client` et `telephone_client`. Aucune règle financière modifiée.
- `FicheLogement` et `Recapitulatif` (Livewire) ; `ReservationService::garderDates` reçoit les informations du voyageur (paramètre facultatif) et applique le séjour minimum et maximum ; `PiecesVoyageur` (dépôt, chiffrement, états, accès) ; composant `PieceIdentite` ; route `gerant.piece-voyageur`, droit `ReservationPolicy::voirPieceVoyageur` ; commande planifiée `gesimo:rappels-piece` (9 h 05) et modèle d'e-mail « Rappel de la pièce d'identité » modifiable dans l'administration.
- Pages du parcours (fiche, informations, paiement, retour de paiement, confirmation) : site public autour, classe `site-reservation`. Les jetons `--avelis-*` sont désormais définis une seule fois pour tout le site (`accueil-reference.css`) ; `resources/css/reservation.css` porte les composants du parcours. Aucune dépendance ajoutée.

## Limites connues et décisions de mise en œuvre

- Le formulaire d'inscription n'est pas dupliqué dans la page de réservation : le visiteur est renvoyé vers la connexion ou l'inscription existantes, avec ses dates conservées et un retour automatique sur la réservation.
- Au-delà du séjour maximum, le lien mène à la page Contact d'Avelis : les coordonnées du gérant ne sont données qu'après confirmation (règle existante).
- Les créneaux d'arrivée (avant 14 h, 14 h–18 h, 18 h–22 h, après 22 h) sont fixes.
- Le gérant qui saisit une arrivée renseigne toujours le type et le numéro de la pièce vue (formulaire existant) : c'est ce qui passe l'état en « Vérifiée à l'arrivée ».
- Les pages Mes réservations et Détail d'une réservation gardent leur habillage actuel ; leur refonte viendra dans un lot ultérieur.

## Vérification

Tests Pest (fiche, informations, service, pièce chiffrée, droits, rappel, pages) ; parcours navigateur `tests/E2E/reservation.mjs` (données `tests/Support/preparer-reservation.php`) : fiche, connexion avec dates conservées, informations, absence de débordement de 320 à 2 560 px, bouton visible sur téléphone.
