# Rapport de validation — Lot 4 : espaces client et gérant, pages publiques

Date : 20 octobre 2026 · Branche : `claude/sleepy-brahmagupta-t7j8ta`

## 1. Ce qui a été fait

- **Maquettes** (23 écrans, `docs/maquettes/lot-4/index.html`) validées par le porteur du projet, avec mes recommandations.
- **Pages publiques** : à propos, FAQ, contact (message gardé en base + e-mail à l'équipe, 5 envois/heure maximum par personne), conditions et confidentialité (**brouillons à faire valider par un juriste**), « Devenir gérant partenaire » (mène à l'inscription gérant), pied de page, onglet « Aide ».
- **Client** : accueil du compte (prochain séjour, avis à donner), profil (informations, mot de passe), « Mes réservations » (à venir / passées), détail d'une réservation, avis après séjour, « Signaler un problème ».
- **Gérant** : tableau de bord « Aujourd'hui » (arrivées, présents, à venir, sommes dues, litiges), check-in avec **pièce d'identité obligatoire** (numéro chiffré en base) et check-out depuis le téléphone, liste des réservations, aide.
- **Admin** : tableau de bord (à traiter + indicateurs du cahier), toutes les réservations, litiges, journal d'audit (lecture seule, sans secret), modèles d'e-mails.
- **E-mails** par une couche « canaux » (e-mail aujourd'hui ; SMS/WhatsApp plus tard) : confirmation, rappel d'arrivée la veille (9 h), annulation (client + gérant), reversement effectué (gérant), litige ouvert / clos. Chaque message est écrit en base, jamais envoyé deux fois, et un échec d'envoi ne bloque jamais un paiement. Les textes sont modifiables (super-administrateur).
- **Avis** : seulement après un séjour terminé, une seule fois (application **et** base de données) ; moyenne et derniers avis sur la fiche du logement.
- **Litiges** : le client ou le gérant en ouvre un ; la réservation passe « En litige » (plus de check-in) puis retrouve son statut à la clôture par l'admin.

## 2. Critères de validation

| Critère | Résultat | Preuve |
|---|---|---|
| Parcours client complet : recherche, réservation, paiement, confirmation, check-in, check-out, avis | Réussi (paiement : faux GeniusPay) | `ParcoursTest` |
| Parcours gérant complet : création de résidence, validation admin, réservation reçue, check-in | Réussi | `ParcoursTest` |
| Écrans lisibles sur 360 px de large, sans débordement, menu en bas | Réussi : 21 pages parcourues avec un navigateur à 360 px (public, client, gérant, admin) : 0 débordement, menu du bas présent | script Playwright, captures regardées |
| Aucun écran > 3 s en 3G | **Écart expliqué** | voir §4 |
| E-mails envoyés en test, textes venant des Paramètres | Réussi | `MessagerieTest` |
| Avis seulement après séjour terminé | Réussi | `AvisEtLitigesTest` (+ refus par la base, code 23514) |
| Litiges : ouverture, traitement, retour de statut, droits | Réussi | `AvisEtLitigesTest` |
| Accessibilité de base (contrastes, zones de clic ≥ 44 px, libellés, clavier) | Réussi pour ce qui est livré ; audit complet au Lot 7 | revue du code et captures |
| États vides et erreurs compréhensibles | Réussi | textes des maquettes repris |
| Migration réversible | Réussi | `migrate:fresh`, `rollback`, `migrate` |
| Écrans conformes aux maquettes | Réussi, avec les écarts du §5 | — |

## 3. Tests lancés

`vendor/bin/pest` : **276 réussis, 0 échec** (dont 30 nouveaux) · `vendor/bin/pint --test` : conforme · `npm run build` : OK.

## 4. Chargement en 3G (écart)

Mesuré à 360 px avec une « 3G lente » (500 kbit/s, 400 ms) : environ 11 s à la première visite, **car le serveur de développement n'envoie rien de compressé** (le script Livewire pèse 609 Ko). En production, la compression d'Nginx (déjà activée dans `deploy/nginx-gesimo.conf`) ramène l'ensemble à environ 140 Ko, soit environ **3 à 4 s à la première visite en 3G lente** (estimation, non mesurée), moins de 2 s en « 3G rapide », et presque instantané ensuite (fichiers en cache). **À remesurer sur le vrai serveur** après déploiement.

## 5. Écarts avec les maquettes (justifiés)

- L'onglet « Mon compte » s'appelle « Compte » (il tenait mal sur 360 px) ; l'accueil admin est le tableau de bord.
- Litige : **pas de bouton « Décider un remboursement »**. Un litige ne déclenche aucun mouvement d'argent (je n'invente pas de règle).
- Deux modèles d'e-mail en plus : « litige ouvert » et « litige clos ».
- Modèles d'e-mails réservés au **super-administrateur** (cohérent avec RG-20/21).
- « Mises en avant » : simple carte « bientôt » (Lot 5). Texte « À propos » provisoire.

## 6. Décisions

Prises seul (simples et sûres) : retour au statut d'avant à la clôture d'un litige ; un seul litige ouvert par personne et par réservation ; pièce d'identité obligatoire au check-in.
**En attente de vous** : (1) que faire de l'argent après un litige (remboursement partiel, geste commercial…) ; (2) texte « À propos » ; (3) validation juridique des conditions et de la confidentialité ; (4) adresse e-mail de l'équipe (`contact.email`).

## 7. Risques et limites

Aucun e-mail n'a été envoyé par un vrai serveur d'e-mail (à tester après déploiement, réglages `email.*`). Le paiement reste testé seulement avec le faux GeniusPay (voir Lot 3). Pas de SMS/WhatsApp (plus tard).

## 8. Comment vérifier depuis un navigateur

Créer un compte client, réserver et payer en test ; avec le compte gérant, enregistrer l'arrivée (pièce d'identité) puis le départ ; côté client : « Donner mon avis ». Signaler un problème, puis (admin) « Litiges » ▸ clore. Regarder « Journal d'audit » et « Modèles d'e-mails » (super-admin).
