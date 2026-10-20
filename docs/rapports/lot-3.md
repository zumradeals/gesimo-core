# Rapport de validation — Lot 3 : paiement GeniusPay, commission et reversement

Date : 20 octobre 2026 · Branche : `claude/sleepy-brahmagupta-t7j8ta`

## 1. Ce qui a été fait

- **Paiement derrière une interface** (`FournisseurPaiement`) : GeniusPay est un branchement remplaçable. Mode (test/réel), clés et secret webhook sont lus dans **Paramètres** (secrets chiffrés), jamais dans le code ni dans `.env`.
- **Une réservation n'est confirmée que par un paiement vérifié** : message signé (HMAC-SHA256) **puis** contrôle direct auprès de GeniusPay. Sans secret configuré, tout message est refusé. La base elle-même refuse de passer une réservation en « Confirmée » sans paiement réussi couvrant l'acompte.
- **Messages en double, en retard, ou après annulation** : traités une seule fois (unicité en base + verrous) ; un paiement tardif sur une réservation expirée confirme si les dates sont libres, sinon remboursement automatique + alerte admin.
- **Livre de comptes inaltérable** : encaissement, commission (prise à la source, taux figé à la confirmation), part du gérant, remboursement, reversement. Une correction est une nouvelle écriture.
- **Plancher d'acompte (RG-14)**, **annulations et remboursements** (politique flexible, montant montré avant confirmation), **arrivée / départ** minimaux, **reversement manuel** par l'admin, **écran de rapprochement** (Contrôle).
- Écrans : page de paiement, retour, confirmation, reçu, annulation ; gérant : arrivées, « Argent » ; admin : Paiements, Reversements, Contrôle.
- Décisions du Lot 2 appliquées (politique d'annulation unique « flexible », nouvelle vérification si l'identité d'une résidence change).

## 2. Critères de validation

| Critère | Résultat | Preuve |
|---|---|---|
| Paiement réussi, échoué, abandonné, message en double, fausse signature, paiement tardif | Réussi (avec un faux GeniusPay) | `PaiementFluxTest`, `FournisseurGeniusPayTest` |
| 10 réservations : part gérant + commission + remboursements = encaissé, au franc près | Réussi | `ComptabiliteTest` « 10 réservations » |
| Aucun secret dans le code, les journaux, les réponses | Réussi | tests de fuite (`PaiementFluxTest`) ; la clé sandbox vue sur capture n'est écrite nulle part |
| Aucune confirmation sans paiement vérifié | Réussi | test applicatif + garde en base (SQLSTATE) |
| Écritures inaltérables | Réussi | `ComptabiliteTest` (modification/suppression refusées par la base) |
| Gérant : ses montants ; admin : tout | Réussi | `DroitsPaiementTest` |
| Migration propre et réversible | Réussi | `migrate:fresh --seed`, `rollback`, `migrate` |
| **Essais réels avec GeniusPay (bac à sable)** | **Non testé** | l'environnement de développement ne peut pas joindre geniuspay.ci ; à faire après déploiement (liste dans `DEPLOIEMENT.md`) |

## 3. Tests lancés

`vendor/bin/pest` : **246 réussis, 0 échec** · `vendor/bin/pint --test` : conforme · `npm run build` : OK.

## 4. Décisions

Prises avec votre accord (« j'approuve tes recommandations ») : reversement manuel ; GESIMO supporte les frais GeniusPay ; commission = % du prix du séjour (ou de la nuit retenue), arrondie à l'entier supérieur à .5, plafonnée au montant acquis ; taux figé à la confirmation.
**En attente de vous** : le **taux de commission réel** (aucune valeur par défaut : tant que `commission.pourcentage` n'est pas réglé, le paiement en ligne reste fermé).

## 5. Ce qui n'a PAS pu être confirmé avec GeniusPay

1. Qui supporte les frais exacts, par moyen de paiement.
2. Comment simuler succès / échec / abandon dans le bac à sable.
3. Délais de remboursement et remboursement des frais ; remboursement partiel.
4. Nouvelles tentatives d'envoi des webhooks et comportement de l'horodatage lors d'un renvoi.
5. Conditions de vérification du compte (KYC) pour passer en réel.
6. Le versement automatique (cashout) n'est volontairement pas utilisé.
7. Tout appel réel : jamais exécuté depuis ici.

## 6. Risques et limites

- Intégration validée contre la documentation et un faux serveur, pas contre le vrai : prévoir des corrections après les premiers essais.
- Le reversement est manuel : l'admin envoie l'argent (Wave/virement) puis le marque « fait ».
- ⚠️ **La clé secrète sandbox apparaissait sur une capture d'écran : régénérez-la** avant de la saisir.

## 7. Comment vérifier depuis un navigateur

Suivre `DEPLOIEMENT.md` (étape 4 bis puis « Liste de tests »), puis : réserver et payer en test, annuler, ouvrir Admin ▸ Contrôle (aucun écart attendu) et Admin ▸ Reversements.
