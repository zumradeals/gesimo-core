# Intégration du parcours de réservation v3

La fiche du logement et l'étape de réservation reprennent la maquette validée `docs/maquettes/reservation-v3.html` : le voyageur choisit une date d'arrivée et un nombre de nuits (boutons − et +, raccourcis), voit le départ calculé, le prix groupé et la vraie date limite d'annulation gratuite. L'étape « Vos informations » demande le nom comme sur la pièce, le téléphone, le motif du séjour (obligatoire), l'heure d'arrivée et un message (facultatifs) et une seule case d'accord. La pièce d'identité n'est demandée qu'après le paiement, déposée une fois dans le profil, chiffrée, et visible seulement du gérant de la réservation confirmée pendant 7 jours après le départ, chaque ouverture étant journalisée. Un rappel part 2 jours avant l'arrivée. Les pages du parcours adoptent la charte orange de l'accueil. Contrat : `docs/contrats/reservation-v3.md`.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Arrivée + nombre de nuits, départ calculé, minimum et maximum réglables | Conforme | Tests « la fiche calcule le départ… », « …respecte le séjour minimum et maximum » ; parcours navigateur |
| Prix groupé, détail nuit par nuit, total sans frais cachés | Conforme | Test de regroupement ; captures |
| Annulation avec la vraie date, politique stricte gérée | Conforme | Test « …vraie date limite… » |
| Dates prises : propositions de dates libres | Conforme | Tests fiche et étape informations (trois propositions, sans chevauchement) |
| Motif obligatoire, « autre » avec précision, accord enregistré | Conforme | Tests service (messages, base de données refusant un motif inconnu) |
| Pièce chiffrée sur disque privé, formats, taille, validité, remplacement | Conforme | Tests de dépôt ; contenu stocké différent de l'original |
| Accès à la pièce limité (gérant, personnel autorisé, réservation confirmée, durée réglée, journal) | Conforme | Tests de droits et de durée (avec voyage dans le temps) |
| Pièce manquante : avertissement, jamais de blocage | Conforme | Test vue du gérant |
| Rappel 2 jours avant, sans doublon, seulement si la pièce manque | Conforme | Test commande planifiée |
| Visiteur non connecté : dates conservées, retour après connexion ou inscription | Conforme | Tests + parcours navigateur |
| Téléphone / ordinateur, aucun débordement de 320 à 2 560 px | Conforme | Chromium, sept largeurs, fiche et étape informations |
| Règles financières, paiement, acompte | Inchangés | Les 423 tests existants passent après adaptation des seuls tests qui vérifiaient l'ancien texte |

## Tests lancés

- `vendor/bin/pest` : **456 tests, 2 880 assertions, tous réussis** (423 existants + 33 nouveaux).
- Migration appliquée, annulée puis réappliquée sans erreur (base de test dédiée).
- Pint appliqué ; build Vite réussi ; tests JavaScript/PWA : 9 réussis.
- Parcours Chromium `tests/E2E/reservation.mjs` réussi (fiche, connexion avec dates conservées, informations, validations, bouton visible sur téléphone, aucune erreur JavaScript). Captures ordinateur et téléphone inspectées.
- Non confirmé : le parcours `tests/E2E/accueil.mjs` s'arrête sur mon jeu de données (aucune annonce publiée) car il suppose que le parcours gérant ait d'abord validé l'identité du gérant ; il n'a pas été relancé dans son enchaînement habituel. Aucun fichier de l'accueil n'a été modifié, mais les jetons de couleur ont été déplacés dans `accueil-reference.css` : à revérifier visuellement après déploiement. Pas de test GitHub Actions lancé depuis cette session.

## Décisions prises seul

- Valeurs de réglage validées : minimum 1, maximum 30, départ 11 h, accès à la pièce 7 jours.
- Inscription au milieu d'une réservation : renvoi vers les pages existantes avec dates conservées, plutôt qu'un second formulaire d'inscription dans la page.
- Au-delà du maximum, lien vers Contact (les coordonnées du gérant ne sont pas données avant confirmation).
- « Vérifiée à l'arrivée » : l'état est atteint quand le gérant enregistre l'arrivée avec le type et le numéro de la pièce (formulaire existant).
- Les pages du parcours (dont paiement et confirmation) passent de l'espace client latéral au site public autour, comme dans la maquette.

## Décisions en attente

- Conformité de la durée de conservation et du chiffrement de la pièce d'identité avec les exigences de l'ARTCI : validation juridique recommandée avant le lancement.
- Refonte de Mes réservations et du détail d'une réservation (lot suivant).

## Risques et limites

- La pièce chiffrée dépend de la clé maître `APP_KEY` : la perdre rend les pièces illisibles (sauvegarde de la clé nécessaire).
- Le rappel par e-mail dépend de la configuration d'e-mail dans Paramètres ; sans elle, aucun rappel ne part.
- Le déploiement serveur (migration, build, planificateur) reste nécessaire après fusion.

## Vérification depuis un navigateur

1. Ouvrir une fiche de logement, choisir une date d'arrivée, ajouter des nuits avec « + » : le départ, le total et la date d'annulation changent. Essayer « 1 semaine ».
2. Cliquer sur Réserver sans être connecté : la connexion indique que les dates sont conservées ; après connexion, retour sur la réservation.
3. Remplir « Vos informations » : le bouton reste grisé tant que la case n'est pas cochée ; essayer sans motif, puis « Autre » sans précision.
4. Après un paiement de test, la confirmation propose d'ajouter la pièce d'identité ; la retrouver dans Mon profil.
5. Côté gérant, ouvrir la réservation : motif, message, état de la pièce et lien « Voir la pièce ».
6. Dans Paramètres > Réservation : modifier séjour minimum, maximum, heure de départ et durée d'accès à la pièce.
