# Rapport de validation — Lot 1 : socle technique et base de données

Date : 20 octobre 2026 · Branche : `claude/sleepy-brahmagupta-t7j8ta`

## 1. Ce qui a été fait

- Le projet est installé : Laravel 13, Livewire 4, Tailwind 4, PostgreSQL. Le site affiche pour l'instant « en construction ».
- Le code est rangé en quatre modules : `Noyau`, `Sejours`, `Paiement`, `Parametres`.
- Toutes les tables du modèle de données v0.1 sont créées par des migrations qui se défont et se rejouent proprement.
- La base de données elle-même interdit : les réservations qui se chevauchent, un paiement compté deux fois, la modification d'une écriture comptable, les montants à virgule ou négatifs.
- Les six rôles existent, avec des droits vérifiés côté serveur.
- Le journal d'audit enregistre qui a fait quoi, quand et sur quoi. Il est impossible à modifier.
- Les secrets sont chiffrés en base avec la clé maître. Les données DÉMO existent et refusent de se charger en production.
- L'intégration continue (GitHub Actions), le README en français et `CLAUDE.md` sont ajoutés.

## 2. Critères de validation

| Critère | Résultat | Preuve |
|---|---|---|
| Migrations sur base vide | Réussi | `migrate:fresh` OK ; 8 migrations appliquées |
| Migrations se défont proprement | Réussi | `migrate:rollback` : il ne reste que la table des migrations, 0 fonction, 0 extension ; `migrate` rejoué ensuite sans erreur |
| Deux réservations actives qui se chevauchent refusées par la base (SQL direct) | Réussi | `ChevauchementTest` : erreur PostgreSQL 23P01 (inclut un `INSERT` SQL brut) |
| Réservations/blocages : cas limites | Réussi | arrivée le jour du départ permise ; autre unité permise ; annulée/expirée/terminée/litige libèrent les dates ; blocage contre réservation refusé dans les deux sens |
| Référence de paiement déjà enregistrée refusée | Réussi | `ArgentTest` : erreur 23505 |
| Modifier ou supprimer une écriture comptable échoue | Réussi | `ArgentTest` : UPDATE, DELETE et TRUNCATE refusés (erreur 23000) |
| Secret illisible en clair dans la base | Réussi | `SecretsTest` : valeur chiffrée ; absente de `parametre`, `parametre_historique` et `journal_audit` |
| Utilisateur sans droit refusé côté serveur | Réussi | `DroitsTest` : réponses HTTP 403/401 et refus des policies |
| Aucune valeur secrète dans le dépôt, `.env.example` fourni | Réussi | recherche de motifs de clés/jetons : aucun résultat ; `.env.example` sans valeur ; `.env` ignoré par Git |
| Formatage Pint | Réussi | `pint --test` : passed |
| Construction des fichiers du site | Réussi | `npm run build` OK |
| Intégration continue sur GitHub | Réussi | passage n°3 vert : formatage, 64 tests, migrations défaites puis rejouées, construction du site — https://github.com/zumradeals/gesimo-core/actions/runs/39458488870 (les passages n°1 et n°2 avaient échoué : dossier de tests vide dans la configuration, corrigé) |

## 3. Tests lancés (résultat réel)

`vendor/bin/pest` sur PostgreSQL 16 : **64 tests réussis, 178 vérifications, 0 échec** (environ 4 secondes).

- Base : 38 tests (chevauchements, règles de réservation, argent).
- Sécurité : 26 tests (secrets, journal d'audit, droits, données DÉMO).

Contrôle de sensibilité : j'ai retiré volontairement la contrainte anti-chevauchement, l'unicité de la référence de paiement et la protection des écritures. 15 tests de la base sont alors devenus rouges. J'ai ensuite remis les protections, et les 64 tests repassent au vert.

## 4. Décisions

**Prises seul** (conformes à votre accord du jour, sinon à corriger) :
- Les quatre décisions validées : nuits [arrivée ; départ[, table interne d'occupation, litige qui libère les dates, paiement dont seul le statut avance.
- Tables et colonnes en français. Montants en `bigint`.
- `APP_ENV` et `APP_DEBUG` restent dans `.env`, comme indicateurs techniques, en plus des deux exceptions.
- Réservation hors plateforme : montants à 0 (RG-5 « gratuite ») ; nom du client obligatoire (pas de compte) ; statuts permis : hors plateforme, annulée, terminée.
- Les montants et l'origine d'une réservation sont figés après création (déduit de la règle « aucune modification financière »).
- Les droits du personnel sont deux cases : check-in/check-out et calendrier (cahier, section 2).
- Une écriture comptable a un type parmi : encaissement, commission, part du gérant, remboursement, reversement, mise en avant. Une correction renvoie vers l'écriture corrigée.
- Politique d'annulation : « flexible » ou « stricte » en attendant la définition de la politique stricte.
- Reversement et remboursement : même principe que le paiement (montants figés, statut qui avance seulement, aucune suppression).
- Avis : la base refuse un avis tant que le séjour n'est pas « terminé » (F-C7).
- Données DÉMO : chargées seulement dans les environnements `local`, `testing` et `demo`. Mots de passe tirés au hasard.

**En attente de vous :**
- Politique d'annulation « stricte » (RG-9) : quelles règles exactement ? (Lot 3)
- Délai d'annulation gratuite : 24 h à confirmer (point ouvert n°2 du cahier).
- Les données brutes d'un paiement sont figées à la création. Si le Lot 3 doit conserver aussi l'avis final du prestataire (webhook), je vous proposerai une table séparée « événements de paiement ».

## 5. Risques et limites

- **Table d'occupation interne** : elle est tenue à jour par la base, mais une personne ayant un accès SQL complet pourrait y écrire directement. Les droits du compte de l'application seront à restreindre en production (hébergement, Lot 7).
- **Droits d'écriture comptable** : l'immuabilité est assurée par des déclencheurs. Un super-utilisateur PostgreSQL pourrait les supprimer : à interdire à l'hébergeur par les droits du compte de l'application.
- **Un secret en base n'est protégé que si `APP_KEY` l'est** : la sauvegarder à part. Perdre cette clé rend les secrets illisibles.
- Les tests utilisent un mot de passe de base fictif (`gesimo_test`) qui n'existe que pour la base de tests locale/CI.
- L'authentification complète, les écrans, le paiement et les notifications ne sont pas faits (hors périmètre).

## 6. Comment vérifier depuis un navigateur

Il n'y a pas encore d'écran à tester. Voici ce que vous pouvez contrôler :
1. Ouvrez le dépôt sur GitHub : <https://github.com/zumradeals/gesimo-core>. Vous voyez les dossiers `app`, `database`, `tests`, `docs`, le `README.md` en français et `.env.example` sans valeur.
2. Ouvrez `database/migrations` : une migration par domaine (personnes, biens, réservations, argent, autres).
3. Onglet **Actions** : le dernier passage « Vérifications » doit être vert (tests, formatage, migrations qui se défont).
4. Ouvrez `tests/Feature/Base/ChevauchementTest.php` : chaque test porte une phrase qui décrit la règle vérifiée.
