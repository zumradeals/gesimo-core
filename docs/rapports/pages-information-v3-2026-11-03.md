# Rapport de validation — pages d'information v3 (lot 4)

## Ce qui a été fait
- Bandeau de page ivoire commun aux huit pages ; cartes, accordéons et boutons à la charte.
- Devenir partenaire : entête ivoire à deux colonnes, barre d'action sur téléphone, repli quand le taux de commission n'est pas réglé.
- Contact : formulaire en premier, encadré « Une réponse rapide ? » à la place de la liste des questions.
- Conditions, Confidentialité, Mentions légales : sommaire et lecture sur deux colonnes en grand écran.
- Profil public du gérant : entête, badges et cartes à la charte.
- Aucun texte, aucune règle, aucune route ni migration modifiés.

## Critères
| Critère | Résultat | Preuve |
|---|---|---|
| Contact : formulaire d'abord, renvoi vers l'Aide, pas de doublon | Conforme | test « Contact met le formulaire en premier… » + navigateur |
| Aide : six questions et coordonnées conservées | Conforme | test « l'Aide garde les six questions… » |
| Sommaire des trois textes de lecture | Conforme | test « …ont un sommaire… », clic sur le sommaire en navigateur |
| Texte libre des Paramètres conservé | Conforme | test « une page modifiée dans les Paramètres… » |
| Partenaire : repli du taux et barre mobile | Conforme | test « Devenir partenaire annonce le taux… », barre visible à 390 px |
| Pas de débordement, pas d'erreur JavaScript, huit pages à 1440 et 390 px | Conforme | `tests/E2E/pages-information.mjs` |
| Aucune régression | Conforme | 522 tests passent |

## Tests lancés
- `vendor/bin/pest` : 522 tests, tout passe (5 nouveaux).
- Parcours navigateur : « Parcours pages d'information : OK ».
- Captures avant / après : `docs/rapports/pages-information-v3/`.

## Décisions prises seul
- La photo de l'entête de Devenir partenaire est conservée (la page validée en octobre l'avait) ; elle passe à droite du texte au lieu d'être en fond.
- Le sommaire n'apparaît pas quand le texte est remplacé dans les Paramètres (texte libre sans titres identifiables).
- Les textes en deux colonnes commencent à 1200 px.

## Décisions en attente
Aucune. Rappel : les textes juridiques restent des brouillons à faire valider par un juriste.

## Risques et limites
- Les captures montrent des pages sans coordonnées ni raison sociale renseignées : à vérifier avec les vraies valeurs dans Paramètres.
- Le profil public n'a été vu qu'avec un gérant sans présentation ni badge certifié ; l'affichage avec badge est couvert par les tests existants, pas par une capture.

## Comment vérifier depuis un navigateur
Ouvrez, sur ordinateur puis sur téléphone : Devenir partenaire, À propos, Aide, Contact (envoyez un message vide pour voir les messages), Conditions (cliquez sur le sommaire), Confidentialité, Mentions légales, et la page publique d'un gérant.
