# Rapport de validation — gérant : gestion (lot 6)

## Ce qui a été fait
- Mes résidences, éditeur en trois étapes, Mon profil gérant, Argent, Mises en avant (liste et achat), Mon personnel et Aide du gérant passent à la charte orange, pleine largeur.
- Mon profil gérant : l'onglet « Mes documents » est retiré ; renvoi vers « Ma vérification » ; encadré de situation.
- Mes résidences : boutons « Modifier » et « Accès à la résidence » sur chaque carte.
- Aucune règle, aucun champ, aucun montant, aucune migration modifiés.

## Critères
| Critère | Résultat | Preuve |
|---|---|---|
| Sept écrans de gestion à la présentation commune, titres conservés | Conforme | test « les écrans de gestion du gérant… » |
| Mes résidences : Modifier, Accès, première résidence | Conforme | tests « …propose Modifier et Accès… », « …sans résidence… » |
| Profil sans onglet documents, lien vers Ma vérification | Conforme | tests « …n'a plus d'onglet documents… », « …situation… », navigateur |
| Éditeur : trois étapes et messages | Conforme | test « l'éditeur de résidence garde ses trois étapes… » |
| Droits : écrans réservés au gérant | Conforme | test « Argent, Mises en avant et Personnel restent réservés… » |
| Aide à jour (deux documents, accès) | Conforme | test « l'aide du gérant donne les textes à jour… » |
| Pas de débordement, pas d'erreur JavaScript | Conforme | `tests/E2E/gerant-gestion.mjs` à 1440 et 390 px |
| Aucune régression | Conforme | 551 tests passent |

## Tests lancés
- `vendor/bin/pest` : 551 tests, tout passe (8 nouveaux).
- Parcours navigateur : « Parcours gérant gestion : OK » (huit pages, deux largeurs).
- Captures avant / après : `docs/rapports/gerant-gestion-v3/`.

## Décisions prises seul
- Le composant du profil garde ses méthodes de dépôt de documents (testées) même si l'écran ne les montre plus.
- Sur le tableau « Argent », la présentation en tableau est conservée (défilement horizontal si l'écran est étroit) plutôt que le passage en cartes de la maquette sur téléphone : à améliorer si vous le souhaitez.

## Décisions en attente
Aucune.

## Risques et limites
- Les états « Après envoi », « Refusée » et « Invitation envoyée » sont couverts par les tests existants, pas par une capture.
- L'administration (lots 7 et 8) et les anciens styles (lot 9) restent à traiter.

## Comment vérifier depuis un navigateur
Connecté en gérant : Mes résidences (Modifier, Accès), Mon profil gérant (plus d'onglet documents), Argent, Mises en avant (Nouvelle mise en avant), Mon personnel, Aide du gérant.
