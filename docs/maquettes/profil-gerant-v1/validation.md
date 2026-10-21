# Revue des maquettes v1

Date : 21 octobre 2026. Périmètre : conception et prototype statique uniquement.

## Vérifications effectuées

- Ouverture réelle du fichier autonome dans Chromium, sans serveur applicatif ni connexion à un compte.
- Inspection visuelle des captures du profil privé, des informations du gérant, de l'étape de soumission, de la confirmation et du profil public.
- Captures des quatre vues principales en 1440 px et en 390 px ; une capture supplémentaire de l'onglet informations.
- Absence de débordement horizontal sur les quatre vues à 320, 360, 390, 768 et 1024 px.
- Contrôle des états « identité manquante », « en cours de contrôle » et « validée » : la demande d'envoi reste indisponible dans les deux premiers cas.
- Simulation de l'envoi : retour à la liste avec confirmation ; le bouton d'envoi n'est plus visible.
- Simulation du gérant sans certification complémentaire : la mention du gérant disparaît tandis que le badge de la résidence reste indépendant.
- Aucune erreur JavaScript pendant ces parcours ; syntaxe du script et `git diff --check` vérifiés.

## Limites explicites

Les interactions ne sauvegardent pas des données métier et ne transmettent aucun fichier. Les décisions administratives, migrations, droits et règles de publication ne sont pas encore implémentés. Cette revue ne constitue ni une recette de production ni une nouvelle exécution des tests métier.

Les captures sont conservées dans `previews/`. Les sélecteurs et la barre de navigation de revue n'appartiennent pas aux futurs écrans de production.

La validation visuelle du porteur est attendue avant l'implémentation, conformément à `CLAUDE.md` et au déroulement convenu en conversation.
