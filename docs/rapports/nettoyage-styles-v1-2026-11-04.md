# Rapport de validation — nettoyage des anciens styles (lot 9)

## Ce qui a été fait
- Inventaire automatique des règles de style jamais utilisées, revue à la main, suppression de 151 règles (138 dans `site.css`, 6 dans `accueil.css`, 7 dans `app.css`).
- Les feuilles de style passent de 114 050 à 104 100 octets, sans aucun changement d'affichage.
- Vérification : 104 captures avant / après comparées par empreinte.
- Aucune migration, aucune vue ni règle de gestion modifiée.

## Critères
| Critère | Résultat | Preuve |
|---|---|---|
| Aucun changement d'affichage | Conforme | 99 captures sur 104 strictement identiques ; 4 différences limitées au compte à rebours « dates gardées encore… » ; 1 différence due au chargement d'une photo (voir ci-dessous) |
| Les classes fabriquées par le code sont conservées | Conforme | `espace-admin`, `espace-gerant`, `gq-b-*`, `xl:grid-cols-3` gardées |
| Plus d'ancien vert | Conforme | aucune occurrence dans les styles, vues et fichiers publics |
| Aucune régression | Conforme | 566 tests passent |

## Tests lancés
- `vendor/bin/pest` : 566 tests, tout passe.
- `tests/E2E/comparer-pages.mjs` : 104 captures avant, 104 après, empreintes dans `docs/rapports/nettoyage-styles-v1/`.
- Reproductibilité : un second relevé « avant » a montré que 4 pages (Mon espace et Mes réservations, sur ordinateur et téléphone) changent d'elles-mêmes à chaque capture à cause du compte à rebours ; elles ne sont pas comptées comme différences. Leur zone de différence avant / après est limitée à une ou deux lignes de texte (le compte à rebours).
- L'accueil sur téléphone diffère uniquement dans la zone d'une photo : elle n'était pas encore chargée sur la capture « avant » et l'est sur la capture « après » (`accueil-telephone-avant-apres.png`) ; ce n'est pas un effet des styles supprimés.

## Décisions prises seul
- Les règles dont une classe est fabriquée par le code ou écrite avec un échappement Tailwind ne sont jamais supprimées automatiquement.
- Aucune feuille entière n'est supprimée : chacune garde au moins une règle utilisée.

## Décisions en attente
Aucune.

## Risques et limites
- Une classe appelée seulement par un contenu saisi en base (par exemple dans un texte modifiable des Paramètres) ne serait pas détectée par l'inventaire ; aucune des 151 règles supprimées n'est dans ce cas d'après la relecture.
- Les pages à états rares (erreurs de saisie, états vides, messages de succès) ne sont pas toutes dans les 104 captures ; les 566 tests couvrent leurs textes mais pas leur apparence.

## Comment vérifier depuis un navigateur
Parcourir l'accueil, Logements, une réservation, l'espace gérant et l'administration : rien ne doit avoir changé d'apparence.
