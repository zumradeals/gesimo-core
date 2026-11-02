# Avelis Séjours — fin du parcours voyageur v3 (lot 3)

## Vision

Mon espace, Mon profil, Annuler, Donner un avis, Signaler un problème, le reçu et les e-mails passent à la charte orange. Décisions du porteur, 3 novembre 2026 : maquette `docs/maquettes/espace-voyageur-v3.html` validée avec tous ses points à valider. Charte : `docs/contrats/accueil-reference-v3.md`, `docs/contrats/socle-charte-v1.md`. Pages à pleine largeur (règle 9). Aucune migration, aucun nouveau réglage. Les règles métier (remboursements, avis, litiges, droits d'accès, moment d'envoi des e-mails, durée des liens) ne changent pas.

## Mon espace (`/compte`)
- Titre « Bonjour {prénom} » (sans émoji), coordonnées, rôles.
- Bandeau **« À faire en premier »**, mêmes règles que Mes réservations : 1) payer une réservation dont les dates sont gardées, 2) ajouter la pièce d'identité (séjour confirmé réservé sur la plateforme, aucune pièce valable), 3) donner un avis (séjour terminé sans avis).
- Votre prochain séjour (état, repère « Arrivée dans N jours », gérant), « À faire » (avis à donner, trois au plus, ou « Rien à faire pour le moment »), compteurs, raccourcis (le gérant a aussi Mon personnel, Mises en avant, Aide du gérant). Un gérant sans rôle voyageur voit une carte « Ouvrir l'espace gérant ».
- Trois colonnes sur grand écran.

## Mon profil (`/compte/profil`)
Cartes côte à côte (jusqu'à trois colonnes). Aide « 10 chiffres, sans espace ni indicatif. ». Bouton « Afficher / Masquer » sur les mots de passe. Les messages et règles de saisie sont inchangés.

## Annuler (`/reservation/{id}/annuler`)
Le montant remboursé est le grand chiffre (« Remboursé : X »), avec la raison (délai gratuit, une nuit retenue). Avertissement « définitive », bouton rouge cerclé avec le montant, « Garder ma réservation ». Cas : intégral, nuit retenue, rien payé, impossible (bouton « Retour à ma réservation »). Calcul inchangé (`AnnulationService::apercu`).

## Donner un avis
Libellé sous la note : 1 Décevant, 2 Moyen, 3 Bien, 4 Très bien, 5 Excellent. Le texte « Votre avis sera publié sur la fiche de la résidence avec votre prénom. Une fois publié, il ne peut plus être modifié. » est affiché avant la publication. Note 5 étoiles par défaut (comportement inchangé).

## Signaler un problème (`/reservation/{id}/probleme`, `/reservations/{id}/probleme`)
Bloc « Et ensuite ? » (lecture par l'équipe, réponse sous 48 h, e-mail aux deux parties), avertissement « En litige » et « Aucun remboursement n'est automatique ». Même écran pour le gérant.

## Reçu (`/reservation/{id}/recu`)
En-tête orange, montant payé en évidence, reste à régler au gérant, mise en page prête pour l'impression (A4). **Ajout** : la ligne légale (raison sociale · RCCM · NIF, de Paramètres) en bas du reçu, seulement si elle est renseignée. Reçu toujours réservé au paiement réussi.

## E-mails
Gabarit commun `resources/views/emails/gabarit.blade.php` (styles écrits dans le message) : bandeau bleu nuit avec logo, titre, texte, grand bouton orange, note, pied de page avec la ligne légale si elle existe. Utilisé par les messages des modèles (`emails.message`), le lien de connexion et l'invitation du personnel. Les textes restent modifiables dans « Modèles d'e-mails ». **Ajout** : bouton « Voir ma réservation » (confirmation, rappel d'arrivée) et « Ajouter ma pièce d'identité » (rappel de pièce), seulement pour le voyageur de la réservation.

## Tests
`tests/Feature/Refonte/EspaceVoyageurV3Test.php` (13 tests) ; parcours navigateur `tests/E2E/espace-voyageur.mjs` avec `tests/Support/preparer-espace-voyageur.php`.
