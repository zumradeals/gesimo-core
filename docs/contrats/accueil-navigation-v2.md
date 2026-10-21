# Accueil et navigation — contrat visuel v2

## Vision

Donner envie de séjourner, expliquer les contrôles réels et rendre la recherche immédiatement accessible. Préserver la charte Avelis (vert profond, turquoise, sable et accents dorés), la pile existante et les règles métier.

## Conception validée

Le porteur a validé en conversation le 21 octobre 2026 la maquette ordinateur puis la déclinaison téléphone avec quatre onglets fixes. Références conservées dans `docs/maquettes/accueil-v2/ordinateur.webp` et `telephone.webp`. L’intégration de l’accueil et de sa navigation est explicitement autorisée, puis corrigée à sa demande : largeur fluide sur ordinateur, textes plus grands et suppression des offres fictives. Cette dernière décision remplace leur conservation initialement demandée.

- Header compact, aide visible sur téléphone, navigation complète sur ordinateur.
- Hero photographique, titre « Un lieu à vous. Le temps d’un séjour. », recherche commune/dates/voyageurs.
- Offres réellement publiées uniquement ; carte large pour une seule offre, deux ou trois colonnes selon le nombre d’annonces. Aucune section « Pour vous projeter » ni mention de démonstration.
- Contrôles d’identité, certification et gestion expliqués sans les confondre ; avis uniquement issus de séjours réels.
- Communes présentes en base, aucune géographie ou disponibilité inventée. À la demande du porteur, chaque commune est une carte compacte avec aperçu réel, nombre d’offres et lien de recherche, assortie à l’accueil ; une seule commune ne forme plus un bandeau étiré. Les aperçus excluent les résidences non publiées et les unités inactives. Sans photo, un emplacement neutre remplace l’image.
- Parcours en trois étapes, invitation aux gérants, FAQ puis footer professionnel avec coordonnées et mentions réglées dans la base.
- Sur téléphone : Accueil, Logements, Réservations, Compte, dans le navigateur comme dans l’application installée ; onglet actif visible et espace réservé au-dessus de la zone de sécurité.

## Données et réglages

Aucune offre fictive éditoriale n’est affichée. Aucun logement de substitution n’est créé lorsque l’inventaire est vide. Les données existantes en base et le DemoSeeder ne sont pas modifiés ni exécutés en production.

Les photographies d’ambiance ne remplacent jamais les photos d’une annonce réelle. Les réglages Général permettent déjà de modifier le nom, le slogan, le logo, le visuel principal, les coordonnées, les réseaux sociaux et les textes institutionnels/légaux. Les titres et blocs de l’accueil restent dans la vue versionnée : aucun éditeur de sections n’est ajouté dans ce lot.

Sur ordinateur, les conteneurs de l’accueil, du header et du footer ont des marges proportionnelles à l’écran ; les textes explicatifs sont agrandis. Le héros et la navigation téléphone validés sont conservés.

## Implémentation

Le formulaire GET réutilise la route et les contrôles de recherche existants. Les champs et l’éligibilité des annonces ne changent pas. Le sélecteur des communes utilise le même périmètre publié que les offres.

La navigation mobile publique et celle de l’espace client utilisent le même composant. Les visiteurs sont renvoyés vers la connexion pour leurs réservations et leur compte. Les gérants et administrateurs publics retrouvent leurs propres réservations ; le personnel sans ces droits reçoit un accès Aide. Les menus métier privés gérant/admin sont préservés.

Les autres pages héritent du header, du footer et de la navigation communs ; leur contenu n’est pas refondu sans maquette validée. Aucune migration, nouvelle dépendance, nouvelle règle financière ou modification silencieuse de données.
