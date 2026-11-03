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

## Catalogue Logements (21 octobre 2026)

Maquette approuvée et intégration autorisée : `docs/contrats/logements-v2.md`. Sans dates, afficher uniquement les annonces publiables avec le tarif standard de référence ; après recherche datée valide, afficher la disponibilité et le total réels. Aucun inventaire de démonstration ajouté en production.

## Parcours de réservation (1er novembre 2026)

Maquette `docs/maquettes/reservation-v3.html` validée, intégration autorisée : `docs/contrats/reservation-v3.md`. Date d'arrivée + nombre de nuits (plus de plage de calendrier) ; séjour minimum 1 et maximum 30 nuits, heure de départ 11 h, accès du gérant à la pièce d'identité 7 jours après le départ : tous réglables dans Paramètres. Motif du séjour obligatoire ; pièce d'identité du voyageur déposée une fois dans son profil (après le paiement), chiffrée, visible seulement du gérant de la réservation confirmée, ouvertures journalisées. Les pages Mes réservations et Détail d'une réservation ne sont pas encore refondues.

## Mes réservations et détail (2 novembre 2026)

Maquette `docs/maquettes/mes-reservations-v3.html` validée, intégration autorisée : `docs/contrats/mes-reservations-v3.md`. L'espace client garde son menu latéral et prend la charte orange. Un bouton principal par carte ; le voyageur peut modifier l'heure d'arrivée prévue et son message jusqu'à la veille de l'arrivée ; « Réserver à nouveau » sur les séjours passés. Nouvelle adresse écrite de la résidence (champ du gérant), donnée au voyageur seulement après confirmation, jamais publique.

## Refonte de toutes les pages (2 novembre 2026)

Toutes les pages passent à la charte orange, par priorité : `docs/plan-refonte-orange.md`. Le lot 0 (socle commun : `docs/contrats/socle-charte-v1.md`) est fait : classe `charte` sur toutes les pages, jetons de couleur uniques, anciens verts retirés, pages à pleine largeur. Chaque lot suivant : maquette de ses écrans, validation, contrat, intégration, tests, rapport. Le lot 1 (entrée du visiteur : connexion, inscription, lien, invitation, pages d'erreur 403/404/419/500/503, hors connexion) est fait : `docs/contrats/entree-visiteur-v3.md`. Les pages d'erreur 500 et 503 restent statiques (aucune base de données, session ni réglage). Le lot 2 (catalogue Logements : titre court, arrivée + nombre de nuits, communes rapides, filtre par type, puces des filtres, cartes pleine largeur ; formulaire de l'accueil en arrivée + nuits ; en-tête et pied de page pleine largeur) est fait : `docs/contrats/logements-v3.md`, qui remplace les dispositions visuelles de `logements-v2.md`. Le lot 3 (fin du parcours voyageur : Mon espace avec « À faire en premier », Mon profil, Annuler, Avis, Signaler un problème, reçu imprimable, gabarit d'e-mail commun avec boutons d'action) est fait : `docs/contrats/espace-voyageur-v3.md`. Le lot 4 (pages d'information : Devenir partenaire, À propos, Aide, Contact, Conditions, Confidentialité, Mentions légales, profil public du gérant) est fait : `docs/contrats/pages-information-v3.md` ; aucun texte n'a changé, les textes juridiques restent des brouillons à faire valider par un juriste. Le lot 5 (gérant au quotidien : Aujourd'hui, Réservations, Calendrier, Réservation par téléphone) est fait : `docs/contrats/gerant-quotidien-v3.md`.

## Vérification du gérant et itinéraire (4 novembre 2026)

Décision du porteur, qui **remplace** celle du 21 octobre (certificat de résidence et badges séparés) : pour publier, le gérant fournit sa **pièce d'identité** (une fois) et le **contrat de bail ou justificatif de gestion de chaque résidence**, contrôlés par l'équipe Avelis ; un seul badge public **« Gérant vérifié »** ; le certificat de résidence n'est plus demandé ; les résidences déjà publiées restent en ligne. Le voyageur reçoit, **après confirmation seulement**, un itinéraire simple (bouton vers Google Maps, Waze ou Plans, repères écrits par le gérant, appel, message WhatsApp pour un chauffeur) : aucune carte intégrée ni bibliothèque. Référence : `docs/contrats/gerant-quotidien-v3.md`.

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
9. Interface : français simple, gros boutons, montants en FCFA, peu de champs, mobile d'abord, légère. Les pages remplissent toute la largeur de l'écran (décision du porteur du 2 novembre 2026) : pas de plafond de largeur qui laisse un grand vide à droite ; sur grand écran, on répartit le contenu sur plus de colonnes.
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

## Référence accueil orange (1er novembre 2026)

La maquette `docs/maquettes/accueil-v3/reference-validee.webp` est validée pour l'accueil.
Le contrat `docs/contrats/accueil-reference-v3.md` remplace les dispositions visuelles v2 de cette page :
orange vif, bleu nuit, ivoire, hero compact en deux colonnes et recherche visible sans défilement sur ordinateur.
Cette charte servira de référence aux autres pages lors de leurs prochains lots ; leur refonte n'est pas anticipée ici.
Les annonces et communes restent exclusivement réelles, avec les prix et règles existants.
