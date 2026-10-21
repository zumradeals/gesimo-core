# Accueil et navigation — contrat visuel v2

## Vision

Donner envie de séjourner, expliquer les contrôles réels et rendre la recherche immédiatement accessible. Préserver la charte Avelis (vert profond, turquoise, sable et accents dorés), la pile existante et les règles métier.

## Conception validée

Le porteur a validé en conversation le 21 octobre 2026 la maquette ordinateur puis la déclinaison téléphone avec quatre onglets fixes. Références conservées dans `docs/maquettes/accueil-v2/ordinateur.webp` et `telephone.webp`. L’intégration de l’accueil et de sa navigation est explicitement autorisée, ainsi que la conservation des offres fictives de présentation.

- Header compact, aide visible sur téléphone, navigation complète sur ordinateur.
- Hero photographique, titre « Un lieu à vous. Le temps d’un séjour. », recherche commune/dates/voyageurs.
- Offres réellement publiées en premier, puis exemples illustratifs dans une section séparée.
- Contrôles d’identité, certification et gestion expliqués sans les confondre ; avis uniquement issus de séjours réels.
- Communes présentes en base, aucune géographie ou disponibilité inventée.
- Parcours en trois étapes, invitation aux gérants, FAQ puis footer professionnel avec coordonnées et mentions réglées dans la base.
- Sur téléphone : Accueil, Logements, Réservations, Compte, dans le navigateur comme dans l’application installée ; onglet actif visible et espace réservé au-dessus de la zone de sécurité.

## Données et limites de la démonstration

Les trois exemples sont de simples contenus éditoriaux de présentation : chaque carte indique son caractère fictif, ses tarifs illustratifs et son absence de réservation. Aucun identifiant de logement, aucun bouton ni formulaire de réservation, aucune insertion en base. Ils ne figurent pas dans la recherche, les disponibilités ou le paiement. Les données de démonstration déjà présentes et le DemoSeeder restent conservés ; le seeder n’est pas exécuté sur le serveur de production.

Les photographies d’illustration générées ne remplacent jamais les photos d’une annonce réelle. Le visuel principal est remplaçable par le réglage existant « Image de la page d’accueil ». Les exemples éditoriaux se modifient dans la vue versionnée, sans nouveau système d’administration dans ce lot.

## Implémentation

Le formulaire GET réutilise la route et les contrôles de recherche existants. Les champs et l’éligibilité des annonces ne changent pas. Le sélecteur des communes utilise le même périmètre publié que les offres.

La navigation mobile publique et celle de l’espace client utilisent le même composant. Les visiteurs sont renvoyés vers la connexion pour leurs réservations et leur compte. Les gérants et administrateurs publics retrouvent leurs propres réservations ; le personnel sans ces droits reçoit un accès Aide. Les menus métier privés gérant/admin sont préservés.

Les autres pages héritent du header, du footer et de la navigation communs ; leur contenu n’est pas refondu sans maquette validée. Aucune migration, nouvelle dépendance, nouvelle règle financière ou modification silencieuse de données.
