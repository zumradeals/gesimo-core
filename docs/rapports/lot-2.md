# Rapport de validation — Lot 2 : authentification, réservation et calendrier

Date : 20 octobre 2026 · Branche : `claude/sleepy-brahmagupta-t7j8ta`

## 1. Ce qui a été fait

- **Maquettes** validées par le porteur du projet, avec ses demandes appliquées : pages pleines largeur sur ordinateur (plus de grands vides sur les côtés), barre d'onglets en bas sur téléphone, soin du design. Elles sont dans `docs/maquettes/lot-2/index.html`.
- **Connexion et inscription** : téléphone + mot de passe, ou lien par e-mail ; code SMS prévu derrière une interface « fournisseur SMS », **désactivé par défaut**. Aucun code fixe, essais limités, sessions sécurisées.
- **Gérant** : création de résidence et de logements (photos allégées, prix, week-end, fêtes, équipements, mode de paiement, annulation), envoi pour vérification avec ses pièces.
- **Admin** : valide (avec ou sans badge « Vérifié GESIMO») ou refuse avec un motif. Tant que ce n'est pas fait, la résidence est invisible.
- **Recherche** (commune, dates, personnes, budget, équipements) : seuls les logements réellement libres, avec le prix total du séjour, le même partout.
- **Réservation provisoire** : « En attente » 2 heures (réglage), libérée automatiquement chaque minute (statut « Expirée »).
- **Calendrier du gérant** : vue par logement, blocage de dates, réservations par téléphone (gratuites, sans commission). **Personnel** invitable avec des droits limités.
- Les statuts ne changent que dans le sens permis, **dans l'application et dans la base**.
- **Déploiement** pour `gesimo.dgafrique.com` : scripts et guide `DEPLOIEMENT.md`.

## 2. Critères de validation

| Critère | Résultat | Preuve |
|---|---|---|
| Deux clients réservent la même unité aux mêmes dates en même temps : un seul réussit | Réussi | `tests/Concurrence` : 8 processus PHP séparés, au même instant → 1 « OK », 7 refus, 1 seule réservation en base. Contrôle : sans la contrainte de la base, 8 réussissent et le test échoue |
| Une réservation « En attente » expire et libère les dates après la durée configurée | Réussi | `ReservationTest` : réglage à 1 minute ; rien ne bouge avant, « Expirée » après 2 minutes, dates de nouveau réservables ; la tâche est planifiée chaque minute (testé) |
| Une réservation hors plateforme bloque le calendrier, sans paiement ni commission | Réussi | `ReservationTest`, `ReservationReglesTest` : montants à 0, la base refuse tout paiement / écriture / commission sur elle |
| Un client ne voit que ses réservations ; un gérant que ses résidences ; le personnel que les droits donnés | Réussi | `DroitsEtCalendrierTest` : identifiants d'un autre = « introuvable » ; adresse trafiquée ignorée ; personnel avec/sans droit « calendrier », jamais de téléphone ni de résidences |
| Avec le SMS désactivé, la connexion de repli fonctionne ; aucun code fixe | Réussi | `ConnexionTest` : mot de passe et lien e-mail (à usage unique, 15 min) fonctionnent ; 6 codes courants refusés ; aucun code fixe dans `app/` (test de lecture du code) ; codes SMS aléatoires, à usage unique, 10 min, 5 essais |
| Le prix total est identique dans la recherche, la fiche et le récapitulatif | Réussi | `TarifEtRechercheTest` : même montant (55 000 FCFA) dans les trois écrans |
| Statuts : transitions seulement selon le cahier | Réussi | test de concordance : toutes les paires de statuts, application = base de données |
| Migrations propres et réversibles | Réussi | `migrate:fresh`, `rollback`, `migrate` : 0 table restante hors migrations, 0 fonction ; testé aussi par un propriétaire de base **sans droits de super-utilisateur** (comme en production) |
| Maquettes validées par écrit avant le code | Réussi | validation du porteur du projet, avec ses modifications, appliquées |
| Intégration continue GitHub | Réussi | passage n°7 vert sur le dernier commit : https://github.com/zumradeals/gesimo-core/actions/runs/39467889531 |
| Scripts de déploiement essayés sur un vrai VPS | **Non testé** | syntaxe vérifiée (`bash -n`) ; le premier lancement se fait sur votre VPS (faire une capture avant) |

## 3. Tests lancés (résultat réel)

`vendor/bin/pest` : **148 tests réussis, 0 échec** (environ 20 secondes) ; `vendor/bin/pint --test` : conforme.
Les 64 tests du Lot 1 passent toujours ; le Lot 2 en ajoute 84.

Les tests ont trouvé deux vrais défauts avant livraison, corrigés : (1) un numéro inconnu faisait planter la connexion (500) au lieu du message habituel ; (2) des valeurs par défaut manquantes sur certains modèles.
Vérifié à l'œil sur de vrais écrans (téléphone et ordinateur) : recherche, fiche, connexion, calendrier, résidence.

## 4. Décisions

**Prises seul** (toutes vos recommandations ont été suivies) :
- Les 11 points validés : prix week-end (ven. + sam.) et fêtes, budget = maximum du séjour, acompte seulement quand commission et frais sont réglés, tableau des statuts, badge séparé de la validation, e-mails en journal tant que non réglés, commande de création du super-admin, gérant choisi à l'inscription, photos (5 Mo, 8 par résidence), durée de garde en minutes (120).
- Téléphone : 10 chiffres, l'indicatif +225 est accepté et retiré.
- Un compte a un seul profil à l'inscription (client **ou** gérant).
- Le personnel peut voir et bloquer le calendrier, **pas** saisir de réservation par téléphone (réservé au gérant).
- Un gérant peut modifier une résidence déjà en ligne sans nouvelle vérification (le cahier ne dit rien).
- Un client ne peut annuler qu'une réservation « En attente » (remboursements au Lot 3).
- Ajout d'une commande `gesimo:parametre` pour régler l'e-mail depuis le serveur en attendant l'écran Paramètres (Lot 5).
- Ajout de colonnes : nombre de personnes d'une réservation, badge / motif de refus / date d'envoi d'une résidence ; un seul prix normal et un seul prix week-end par logement.
- Limite de connexion : 5 essais / 5 min par numéro et adresse, et 20 par numéro toutes adresses confondues.

**En attente de vous :**
- Politique d'annulation « stricte » (détails), délai d'annulation gratuite (24 h) — Lot 3.
- Un gérant doit-il être revérifié quand il modifie sa résidence en ligne ?
- Le personnel doit-il pouvoir saisir des réservations par téléphone ?

## 5. Risques et limites

- **Pas de paiement** (Lot 3) : aucune réservation ne peut devenir « Confirmée » en ligne ; elles expirent.
- **E-mails réels** : tant que les réglages `email.*` ne sont pas saisis, rien ne part (liens de connexion, invitations). Le mot de passe fonctionne.
- **SMS** : aucun fournisseur réel n'existe ; l'interface est prête et testée avec un faux fournisseur.
- Le modèle de données n'a pas de champ « adresse exacte » : le client voit le contact du gérant et un lien carte (si GPS) après confirmation. À trancher avec le Lot 3 (e-mail de confirmation).
- Les droits « arrivées/départs » du personnel sont enregistrés mais serviront au Lot 4 (check-in).
- Scripts de déploiement : non essayés sur un vrai serveur ; les sauvegardes quotidiennes restent sur le VPS (à copier ailleurs).
- Les champs « date » affichent le format du navigateur du visiteur.

## 6. Comment vérifier depuis un navigateur

1. Mettez le site en ligne avec `DEPLOIEMENT.md`, puis créez votre compte avec `gesimo:creer-super-admin`.
2. Sur **https://gesimo.dgafrique.com** : créez un compte « Proposer ma résidence », ajoutez une résidence, un logement avec prix et photos, joignez deux pièces, « Envoyer pour vérification ».
3. Connectez-vous avec le super-administrateur : menu « À vérifier » → ouvrez la résidence → « Valider et mettre en ligne ».
4. Déconnecté, ouvrez la page d'accueil : choisissez des dates → le logement apparaît avec son **prix total** ; ouvrez la fiche et réservez avec un compte client → « Garder ces dates 2 heures » → « Mes réservations ».
5. Côté gérant : « Calendrier » → bloquer des dates, « Ajouter une réservation par téléphone » ; invitez un membre du personnel.
6. Sur téléphone, le menu est en bas ; sur ordinateur, en haut et pleine largeur.
