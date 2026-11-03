# Rapport de validation — administration : argent et réglages (lot 8)

## Ce qui a été fait
- Paiements, Reversements, Contrôle, Mises en avant, Journal d'audit, Modèles d'e-mails, Réglages (toutes rubriques, Tests, Historique) et Installation passent à la charte orange, pleine largeur.
- Tableaux d'argent : tableau sur ordinateur, une carte par ligne sur téléphone.
- Installation : plus d'émoji, étapes en pastilles.
- Aucune règle d'argent, aucun montant, aucune écriture, aucun droit ni migration modifiés.

## Critères
| Critère | Résultat | Preuve |
|---|---|---|
| Dix écrans à la présentation commune, titres conservés | Conforme | test « les écrans d'argent et de réglages prennent la présentation commune… » |
| Réservés à l'administration | Conforme | test « …restent réservés à l'administration » |
| Tableaux en cartes sur téléphone | Conforme | test « …deviennent des cartes sur téléphone… » + navigateur (en-tête masqué à 390 px, conservé à 1440 px) |
| Avertissements d'argent conservés | Conforme | tests « Paiements garde l'avertissement… », « Reversements rappelle… », « Contrôle garde son calcul… » |
| Installation sans émoji, étapes | Conforme | test « l'installation accueille sans émoji… » |
| Secrets non affichés | Conforme | test « les réglages gardent leurs secrets masqués » |
| Pas de débordement, pas d'erreur JavaScript | Conforme | `tests/E2E/administration-argent-reglages.mjs` à 1440 et 390 px |
| Aucune régression | Conforme | 566 tests passent |

## Tests lancés
- `vendor/bin/pest` : 566 tests, tout passe (8 nouveaux).
- Parcours navigateur : « Parcours administration argent et réglages : OK » (neuf pages, deux largeurs).
- Captures avant / après : `docs/rapports/administration-argent-reglages-v3/`.

## Décisions prises seul
- L'installation garde ses cinq étapes actuelles (la maquette en montrait trois par simplification).
- Les « différences » visibles sur les captures de Contrôle viennent des données fictives du parcours navigateur (paiements insérés sans écriture), pas d'un défaut.

## Décisions en attente
Aucune.

## Risques et limites
- Les formulaires de rubriques de Réglages (Paiement, Notifications, Mise en avant, Sécurité) ont reçu la même enveloppe ; seule « Réservation » a été capturée.
- L'écran d'installation n'a pas de capture (il n'est accessible que lors du premier lancement) ; il est couvert par les tests existants et le nouveau test.

## Comment vérifier depuis un navigateur
Connecté en super-administrateur : Paiements (réduisez la fenêtre : les lignes deviennent des cartes), Reversements, Contrôle, Mises en avant, Journal d'audit, Modèles d'e-mails, Réglages.
