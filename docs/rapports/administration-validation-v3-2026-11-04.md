# Rapport de validation — administration : validation et litiges (lot 7)

## Ce qui a été fait
- Tableau de bord, Résidences à valider, Valider une résidence, Dossier d'un gérant, Réservations et Litiges passent à la charte orange (menu bleu nuit, cartes, pleine largeur).
- Textes mis en cohérence avec la règle des deux documents : « Contrat de bail : obligatoire », plus de « certification facultative ».
- Valider une résidence : récapitulatif de l'état de la pièce d'identité et du contrat de bail avant la décision.
- Menu du bas sur téléphone : Tableau, Résidences, Réservations, Litiges.
- Aucun droit, aucune décision, aucune règle ni migration modifiés.

## Critères
| Critère | Résultat | Preuve |
|---|---|---|
| Six écrans à la présentation commune, titres conservés | Conforme | test « les écrans de validation et de litiges… » |
| Réservés à l'administration | Conforme | test « …restent réservés à l'administration » |
| Tableau sans émoji, Traiter / Contrôler | Conforme | test « le tableau de bord n'a plus d'émoji… » |
| Textes cohérents (bail obligatoire) | Conforme | tests « Résidences à valider parle de contrat de bail… », « Dossier gérant explique… » |
| Récapitulatif des deux documents ; blocage sans bail | Conforme | test « Valider une résidence montre l'état des deux documents… » |
| Menu du bas de l'administration | Conforme | test « le menu du bas… » |
| Pas de débordement, pas d'erreur JavaScript | Conforme | `tests/E2E/administration-validation.mjs` à 1440 et 390 px |
| Aucune régression | Conforme | 558 tests passent |

## Tests lancés
- `vendor/bin/pest` : 558 tests, tout passe (7 nouveaux).
- Parcours navigateur : « Parcours administration : OK ».
- Captures avant / après : `docs/rapports/administration-validation-v3/`.

## Décisions prises seul
- Le menu du bas de l'administration change (Tableau, Résidences, Réservations, Litiges) comme validé ; Paiements, Reversements et Contrôle restent dans le menu latéral et seront refaits au lot 8.
- Sur le tableau de bord, la liste « Dernières réservations » garde son tableau (lecture sur ordinateur).

## Décisions en attente
Aucune.

## Risques et limites
- Les états « Bail refusé », « Reprise à revoir » et « Litige clos » sont couverts par les tests existants, pas par une capture.
- Les écrans d'argent et de réglages gardent l'ancienne présentation (lot 8).

## Comment vérifier depuis un navigateur
Connecté en administrateur : Tableau de bord, Résidences à valider → ouvrir une résidence (récapitulatif des deux documents) → dossier d'identité du gérant, Réservations, Litiges.
