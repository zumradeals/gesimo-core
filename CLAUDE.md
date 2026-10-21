# Avelis Séjours — règles permanentes

Avelis Séjours est une plateforme de réservation et de gestion de résidences meublées en Côte d'Ivoire.
C'est un projet indépendant : ne le rattache à aucun autre projet ou organisation.
Nom : le projet s'appelait « GESIMO Séjours » ; il s'appelle désormais **Avelis Séjours** (décision du porteur du 21 octobre 2026).
Les identifiants techniques internes gardent l'ancien nom (dépôt `gesimo-core`, dossier serveur `/var/www/gesimo`, base `gesimo`, service `gesimo-queue`, commandes `gesimo:*`, domaine actuel) pour ne pas casser le serveur ; ils ne sont jamais visibles du public.
Le porteur du projet n'est pas technicien : on lui parle en français simple, sans jargon. Il valide les maquettes,
les rapports et les décisions importantes. On ne remplace jamais sa décision.

## Pile technique (imposée)
PHP 8.3+, Laravel, Livewire, Alpine.js, Tailwind CSS, PostgreSQL. Outils natifs de Laravel : scheduler (expiration des
réservations), files d'attente (e-mails, webhooks), policies (droits), migrations, tests (Pest).
Toute autre technologie : seulement si indispensable, et après accord du porteur du projet.

## Documents de référence
`docs/GESIMO_Cahier_des_charges_v0.3_et_Modele_de_donnees.pdf` (à relire avant chaque lot) et
`docs/GESIMO_Prompts_Claude_Code.md` (les lots).

## Évolution du profil gérant (21 octobre 2026)

Référence cible : `docs/contrats/profil-gerant-certification-v1.md`. Le porteur a validé la distinction suivante :
pièce d’identité obligatoire pour publier après contrôle ; certificat de résidence facultatif pour devenir « Gérant certifié » ;
justificatif de gestion facultatif pour le badge de la résidence concernée. Les pièces personnelles appartiennent au profil du gérant.
Les règles fondatrices et les quatre maquettes ont été validées en conversation le 21 octobre 2026.
Le code met en œuvre cette organisation ; le contrat décrit aussi la reprise prudente des anciens dossiers et le renouvellement des documents.

## Refonte accueil et navigation (21 octobre 2026)

Les maquettes ordinateur et téléphone ont été validées. Référence : `docs/contrats/accueil-navigation-v2.md`.
Dernière décision du porteur : retirer les offres fictives et leurs mentions de l’accueil. Seules les annonces réellement publiées sont présentées. Le contenu sur ordinateur occupe une largeur fluide avec une typographie plus lisible ; le héros validé est conservé.

## Page partenaire et menu public (21 octobre 2026)

Maquette et menu approuvés en conversation, intégration autorisée. Référence : `docs/contrats/devenir-partenaire-v2.md`. Le catalogue Logements est conservé ; le menu public comprend Accueil, Logements, À propos, Devenir partenaire, Aide et Contact, avec un menu complémentaire sur téléphone.

## Règles absolues
1. Ne jamais inventer une règle de gestion. Cahier muet ou contradictoire : s'arrêter et poser la question avec une recommandation.
2. Travailler uniquement dans le périmètre du lot demandé.
3. Aucun réglage dans `.env`, sauf l'adresse de la base (`DB_URL`) et la clé de chiffrement maître (`APP_KEY`),
   plus `APP_ENV`/`APP_DEBUG` (indicateurs techniques). Tout le reste vit en base et se règle depuis l'espace Paramètres.
4. Secrets chiffrés en base, jamais en clair dans le code, les journaux, les erreurs ou Git. `.env.example` sans vraie valeur.
5. Montants en FCFA, entiers. Aucune suppression ni modification d'une donnée financière : une correction est une nouvelle écriture.
6. Règles critiques imposées par la base de données (pas de chevauchement de dates, pas de paiement compté deux fois).
7. Sécurité par défaut : droits minimum par rôle, vérifiés côté serveur à chaque action, mots de passe hachés,
   protection contre les injections, limitation des tentatives de connexion, journal d'audit.
8. Aucun contournement d'authentification en production. Tout outil de développement doit être impossible à activer en production.
9. Interface : français simple, gros boutons, montants en FCFA, peu de champs, mobile d'abord, légère.
10. Chaque lot : tests automatisés, migration propre et réversible, rapport de validation.
11. Pas de dépendance inutile ; toute bibliothèque ajoutée est justifiée en deux phrases.
12. Commits petits et clairs, en français, un commit par sujet.

## Maquettes
Avant tout écran d'un lot : maquette de chaque écran clé (HTML simple, mobile d'abord), présentée au porteur du projet,
validation écrite attendue avant d'implémenter. Pour chaque écran : but, ce que voit l'utilisateur, actions, messages d'erreur.

## Rapport de validation (fin de lot)
Ce qui a été fait (5 à 10 lignes) · tableau critère / résultat / preuve · tests lancés et résultat réel ·
décisions prises seul et décisions en attente · risques et limites · comment vérifier depuis un navigateur.

## Organisation du dépôt
- `app/Noyau` : comptes, rôles, droits, journal d'audit. `app/Sejours` : résidences, réservations.
  `app/Paiement` : argent. `app/Parametres` : réglages et secrets chiffrés.
- Tables et colonnes en français, comme dans le cahier. Migrations dans `database/migrations`.
- Lancer les tests : `vendor/bin/pest`. Formater : `vendor/bin/pint`.

## Fusion
Une fois le lot terminé, testé et son rapport écrit, on fusionne sur `main` sans attendre de validation supplémentaire
(décision du porteur du projet). Une maquette ou un écran non validé n'est jamais fusionné.
