# GESIMO Séjours — Prompts d'exécution pour Claude Code

Version 0.2 — 20 octobre 2026 — projet de travail, à valider par le porteur du projet. Dépôt GitHub : `gesimo-core`. Pile technique imposée : PHP, Laravel, Livewire, PostgreSQL.

## Comment utiliser ce fichier

1. Mets les deux fichiers dans le dépôt `gesimo-core` : le PDF « Cahier des charges v0.3 et Modèle de données » sous le nom `docs/cahier-des-charges.pdf`, et ce fichier de prompts sous `docs/prompts-claude-code.md`. C'est la **référence unique** : Claude Code doit la lire avant chaque lot.
2. Lance les lots **dans l'ordre**, un seul à la fois. Copie le bloc « Prompt » du lot dans Claude Code.
3. À la fin de chaque lot, Claude Code doit te rendre un **rapport de validation** (format défini dans le Prompt 0). Ne passe au lot suivant que quand tu as validé le rapport.
4. Les **maquettes** sont obligatoires avant l'implémentation de chaque lot qui contient des écrans. Claude Code te les propose, tu les valides, puis il code.
5. Si Claude Code te pose une question technique, tu peux répondre : « Choisis l'option la plus simple et la plus sûre, et explique-moi ton choix en français simple. »

Ordre des lots : Prompt 0 (règles permanentes), puis Lot 1 à Lot 6, puis Lot 7 (recette finale).

---

## PROMPT 0 — Règles permanentes (à coller une fois au début, et à garder dans `CLAUDE.md`)

```
Tu es le développeur principal de GESIMO Séjours, une plateforme de réservation et de gestion de résidences meublées en Côte d'Ivoire. GESIMO est un projet indépendant : ne le rattache à aucun autre projet ou organisation.

PILE TECHNIQUE (imposée, ne pas la remettre en question)
- PHP (8.3 ou plus), Laravel (dernière version stable), Livewire pour les écrans dynamiques, Alpine.js et Tailwind CSS pour l'interface, PostgreSQL comme base de données.
- Utilise les outils natifs de Laravel : tâches planifiées (scheduler) pour l'expiration des réservations, files d'attente (queues) pour les e-mails et les webhooks, politiques d'autorisation (policies) pour les droits, migrations et tests (Pest ou PHPUnit).
- Cette pile est la pile préférée de GAMAD. N'ajoute une autre technologie que si c'est indispensable, et demande-moi d'abord.
- Le dépôt GitHub s'appelle gesimo-core.

CONTEXTE
- Le cahier des charges v0.3 et le modèle de données v0.1 sont dans docs/cahier-des-charges.pdf. Lis-les en entier avant de commencer et relis la partie concernée avant chaque lot.
- Le porteur du projet n'est pas technicien. Tu lui parles en français simple, sans jargon. Il valide les maquettes, les rapports et les décisions importantes. Tu ne remplaces jamais sa décision.

RÈGLES ABSOLUES
1. Ne jamais inventer une règle de gestion. Si le cahier ne dit rien, ou si deux passages se contredisent, tu t'arrêtes et tu poses la question avec ta recommandation.
2. Travailler uniquement dans le périmètre du lot demandé. Rien d'autre, même si c'est tentant.
3. Aucun réglage dans un fichier .env, sauf deux exceptions : l'adresse de connexion à la base de données et la clé de chiffrement maître. Tous les autres réglages (clés GeniusPay, e-mail, SMS, commission, durée de blocage, acompte, textes) vivent en base de données et se modifient depuis l'espace Paramètres web.
4. Les secrets (clés de paiement, mots de passe e-mail) sont chiffrés en base, jamais écrits en clair dans le code, les journaux, les messages d'erreur ou le dépôt Git. Fournis un fichier .env.example sans aucune vraie valeur.
5. Montants en FCFA, nombres entiers uniquement. Aucune suppression ni modification d'une donnée financière : une correction est une nouvelle écriture.
6. Les règles critiques sont imposées par la base de données (pas seulement par l'interface) : pas de chevauchement de dates, pas de paiement compté deux fois.
7. Sécurité par défaut : droits minimum par rôle, vérification des droits côté serveur à chaque action, mots de passe hachés, protection contre les injections, limitation des tentatives de connexion, journal d'audit des actions sensibles.
8. Aucun contournement d'authentification n'est autorisé en production (pas de code SMS fixe, pas de compte de test caché). Si un outil de développement existe, il doit être impossible à activer en production.
9. Interface : français simple, gros boutons, montants en FCFA, peu de champs, mobile d'abord, lisible sur un petit téléphone avec une connexion lente.
10. Chaque lot est livré avec des tests automatisés, une migration de base de données propre et réversible, et un rapport de validation.
11. Pas de dépendance inutile. Pour toute bibliothèque ajoutée, explique en deux phrases pourquoi, et choisis ce qui est courant, bien maintenu et bien documenté dans l'écosystème Laravel.
12. Commits Git petits et clairs, en français, un commit par sujet.

MAQUETTES (obligatoire)
Avant d'implémenter tout écran d'un lot, tu proposes une maquette de chaque écran clé de ce lot (HTML simple ouvrable dans un navigateur, ou images), version mobile d'abord. Tu les présentes au porteur du projet, tu attends sa validation écrite, puis tu implémentes. Pour chaque écran, indique : son but, ce que voit l'utilisateur, les actions possibles, et les messages d'erreur.

RAPPORT DE VALIDATION (à fournir à la fin de chaque lot)
- Ce qui a été fait, en 5 à 10 lignes de français simple.
- Critères de validation du lot : un tableau « critère / résultat (réussi, échoué, non testé) / preuve ».
- Les tests lancés et leur résultat réel (jamais « devrait fonctionner »).
- Les décisions que tu as prises seul, et celles qui attendent le porteur du projet.
- Les risques ou limites restants.
- Comment le porteur du projet peut vérifier lui-même, étape par étape, depuis un navigateur.

Confirme que tu as compris ces règles, puis attends le Lot 1.
```

---

## LOT 1 — Socle technique et base de données

**But** : poser les fondations (projet, base de données, comptes, rôles, journal d'audit), sans aucun écran métier complexe.

```
LOT 1 — Socle technique et base de données

Lis d'abord docs/cahier-des-charges.pdf (Partie II et sections 2, 7, 8).

PÉRIMÈTRE
1. Initialise le projet dans le dépôt gesimo-core avec la pile imposée (Laravel, Livewire, Alpine.js, Tailwind CSS, PostgreSQL). Présente-moi en une page l'arborescence prévue et les bibliothèques que tu comptes ajouter (authentification, tests, etc.) avec deux phrases de justification chacune, puis attends mon accord avant de continuer.
2. Structure claire en modules (noyau commun, séjours, paiement, paramètres), configuration des tests, formatage du code (Laravel Pint), intégration continue GitHub Actions si possible, README en français expliquant comment lancer le projet en local. Active l'extension PostgreSQL `btree_gist` par migration.
3. Base de données : crée les migrations pour toutes les tables du modèle de données v0.1 (personnes et accès, biens, réservation, argent, autres), avec clés, contraintes et index.
4. Règles gravées dans la base :
   - interdiction de chevauchement de dates pour une même unité (réservations actives et blocages), par une contrainte PostgreSQL d'exclusion ;
   - référence de paiement unique ;
   - écritures comptables impossibles à modifier ou supprimer (déclencheur ou droits) ;
   - montants entiers positifs là où c'est requis.
5. Comptes et rôles : visiteur, client, gérant, personnel, admin, super-administrateur. Contrôle des droits côté serveur.
6. Journal d'audit : qui, quoi, quand, sur quel objet, pour les actions sensibles.
7. Chiffrement des secrets en base à l'aide de la clé maître (la seule valeur sensible hors base, avec l'adresse de la base).
8. Données de démonstration (seed) clairement marquées « DÉMO », impossibles à charger en production.

HORS PÉRIMÈTRE
Authentification complète, écrans, paiement, réservation, notifications.

CRITÈRES DE VALIDATION
- Les migrations s'appliquent sur une base vide et se défont proprement.
- Test : deux réservations actives qui se chevauchent sur la même unité sont refusées par la base elle-même (même en insérant directement en SQL).
- Test : un paiement avec une référence déjà enregistrée est refusé.
- Test : modifier ou supprimer une écriture comptable échoue.
- Test : un secret enregistré est illisible en clair dans la base.
- Test : un utilisateur sans droit reçoit un refus côté serveur.
- Aucune valeur secrète dans le dépôt ; .env.example fourni.
- Rapport de validation fourni selon le format du Prompt 0.
```

---

## LOT 2 — Authentification, réservation et calendrier

**But** : le cœur du service, sans paiement réel : les comptes, les résidences, la disponibilité et la réservation provisoire.

```
LOT 2 — Authentification, réservation et calendrier

Lis docs/cahier-des-charges.pdf (sections 4, 5, 6 : RG-1 à RG-10) et le Lot 1 livré.

ÉTAPE A : MAQUETTES (avant tout code d'écran)
Propose les maquettes mobile puis ordinateur de : inscription et connexion, recherche, fiche résidence, récapitulatif de réservation, mes réservations (client), calendrier du gérant, saisie d'une réservation hors plateforme, création d'une résidence et d'une unité par le gérant, validation d'une résidence par l'admin. Attends ma validation écrite.

ÉTAPE B : IMPLÉMENTATION
1. Inscription et connexion :
   - téléphone + code SMS derrière une interface « fournisseur SMS » ;
   - le fournisseur SMS est DÉSACTIVÉ par défaut (réglage en base) ; dans ce cas, connexion par téléphone et mot de passe, ou par lien envoyé par e-mail ; un e-mail est demandé à l'inscription ;
   - aucun contournement ni code fixe, limitation des tentatives, sessions sécurisées.
2. Gérant : créer résidence et unités (photos, tarifs, équipements, règlement, mode de paiement total ou acompte, pourcentage d'acompte, politique d'annulation). La résidence reste « en attente de vérification » jusqu'à la validation d'un admin, qui peut accorder le badge « Vérifié GESIMO ».
3. Recherche avec filtres (commune, dates, budget, personnes, équipements) : n'affiche que les unités réellement disponibles et le prix total du séjour.
4. Réservation provisoire : à la validation du récapitulatif, la réservation passe « En attente » avec une expiration (2 heures, durée lue dans les réglages). Une tâche automatique libère les réservations expirées (statut « Expirée »).
5. Calendrier du gérant : vue par unité, blocage manuel de dates, saisie de réservations « Hors plateforme » (gratuites, sans commission, bloquent seulement le calendrier).
6. Statuts : En attente, Confirmée, Arrivée enregistrée, Terminée, Annulée, Expirée, Litige, Hors plateforme. Transitions autorisées seulement selon le cahier.
7. Personnel : le gérant peut inviter du personnel avec des droits limités à une résidence.

HORS PÉRIMÈTRE
Paiement réel, check-in, reversement, avis, mise en avant, PWA.

CRITÈRES DE VALIDATION
- Test de concurrence : deux clients réservant la même unité aux mêmes dates en même temps, un seul réussit.
- Test : une réservation « En attente » expire et libère les dates après la durée configurée (utiliser une durée très courte en test).
- Test : une réservation hors plateforme bloque le calendrier et ne génère ni paiement ni commission.
- Test : un client ne voit que ses réservations ; un gérant ne voit que ses résidences ; le personnel n'a que les droits donnés.
- Test : avec le SMS désactivé, la connexion de repli fonctionne ; aucun code fixe n'existe.
- Le prix total affiché est identique dans la recherche, la fiche et le récapitulatif.
- Maquettes validées par écrit avant le code. Rapport de validation fourni.
```

---

## LOT 3 — Paiement GeniusPay, commission et reversement

**But** : encaisser via GeniusPay, tenir une comptabilité irréprochable, gérer annulations, remboursements et reversements manuels.

```
LOT 3 — Paiement GeniusPay, commission et reversement

Lis docs/cahier-des-charges.pdf (sections 6 : RG-11 à RG-17, 8 et la partie « Argent » du modèle de données).

AVANT DE CODER
Consulte la documentation officielle de GeniusPay (pay.genius.ci et sa documentation développeur) pour connaître : la création d'un paiement, la redirection du client, les webhooks et leur signature, le mode sandbox, le remboursement. Résume-moi en français simple ce que tu as confirmé et ce que tu n'as PAS pu confirmer. Pour tout ce qui n'est pas confirmé, ne suppose rien : prévois le plus simple (reversement manuel) et signale-le. Attends mon accord sur ce résumé.

ÉTAPE A : MAQUETTES
Propose les maquettes de : page de paiement et retour de paiement (succès, échec, en attente), confirmation de réservation, reçu, annulation côté client avec montant remboursé affiché avant confirmation, tableau « Sommes dues » pour le gérant, écran de reversement pour l'admin, tableau des paiements et écritures pour l'admin. Attends ma validation écrite.

ÉTAPE B : IMPLÉMENTATION
1. Le fournisseur de paiement est derrière une interface (remplaçable). Les clés et le mode test/réel sont lus dans les Paramètres (jamais dans un fichier technique).
2. Paiement : le client paie l'acompte ou le total selon la résidence, dans la durée de la réservation provisoire. La réservation devient « Confirmée » uniquement après confirmation serveur du paiement (webhook signé et vérifié, ou vérification auprès de GeniusPay). Jamais sur la seule foi du retour du navigateur.
3. Webhooks idempotents : un même événement reçu deux fois ne produit qu'un seul effet. Les paiements tardifs sur une réservation déjà expirée sont gérés (remboursement ou réattribution si les dates sont encore libres) et signalés à l'admin.
4. Comptabilité : chaque mouvement est une écriture inaltérable (acompte reçu, commission, part du gérant, remboursement). La commission est retenue à la source (RG-13). Le plancher d'acompte (RG-14) est calculé et appliqué.
5. Reversement manuel (RG-15) : après le check-in, la somme devient « à reverser » ; l'admin la reverse par Mobile Money en dehors de la plateforme et l'enregistre (référence, date). Pas d'automatisation du versement sans confirmation écrite de GeniusPay.
6. Check-in et check-out minimaux pour déclencher le reversement.
7. Annulation et remboursements : politique flexible par défaut (RG-6 à RG-10), remboursement à la charge des fonds détenus, écritures correspondantes.
8. Rapprochement : un écran admin compare les paiements enregistrés et les écritures, et signale toute différence.

HORS PÉRIMÈTRE
Versement automatique aux gérants, mise en avant payante, avis.

CRITÈRES DE VALIDATION
- Tests en sandbox GeniusPay : paiement réussi, échoué, abandonné, webhook reçu deux fois, webhook avec fausse signature (refusé), paiement tardif après expiration.
- Pour 10 réservations de test, la somme (part gérant + commission + remboursements) égale la somme encaissée, au franc près.
- Aucune clé secrète dans le code, les journaux ou les réponses de l'interface.
- Une réservation ne passe jamais « Confirmée » sans paiement vérifié côté serveur.
- Les écritures comptables sont impossibles à modifier ou supprimer (test).
- Le gérant ne voit que ses propres montants dus ; l'admin voit tout.
- Rapport de validation fourni, avec la liste de ce qui n'a pas pu être confirmé auprès de GeniusPay.
```

---

## LOT 4 — Espaces client et gérant, pages publiques

**But** : une interface complète, simple, pour tous, et les pages publiques.

```
LOT 4 — Espaces client et gérant, pages publiques

Lis docs/cahier-des-charges.pdf (sections 3, 4, 5, 7) et les lots 1 à 3 livrés.

ÉTAPE A : MAQUETTES (obligatoire, y compris les pages publiques)
Propose les maquettes mobile d'abord, puis ordinateur, de :
- Pages publiques : accueil, résultats de recherche, fiche résidence, contact, à propos, FAQ, conditions générales, politique de confidentialité, page « Devenir gérant partenaire ».
- Espace client : accueil du compte, mes réservations, détail d'une réservation, avis après séjour, profil.
- Espace gérant : tableau de bord (arrivées du jour, départs, réservations à venir, sommes dues), liste des résidences et unités, calendrier, réservations, personnel, mises en avant, aide.
- Espace admin : tableau de bord, résidences à valider, réservations, paiements, reversements, litiges, journal d'audit.
Pour chaque écran, rédige aussi les textes (français simple, ton chaleureux et clair). Attends ma validation écrite, écran par écran si nécessaire.

ÉTAPE B : IMPLÉMENTATION
1. Implémente les écrans validés, en réutilisant des composants communs (boutons, champs, cartes, messages) pour garder une interface cohérente.
2. Un client doit pouvoir rechercher, réserver et payer en moins de 2 minutes sur un téléphone modeste.
3. Tableau de bord gérant : ce qu'il doit faire aujourd'hui, visible tout de suite, sans menu compliqué.
4. Check-in / check-out depuis le téléphone par le gérant ou le personnel.
5. Notifications par e-mail (confirmation, rappel d'arrivée, annulation, reversement effectué) via une couche « canaux » qui permettra d'ajouter SMS et WhatsApp plus tard. Les modèles de messages sont modifiables dans les Paramètres.
6. Avis : seulement après un séjour terminé.
7. Litiges : le client ou le gérant peut en ouvrir un ; l'admin le traite.
8. Accessibilité de base : contrastes, taille de texte, zones de clic larges, navigation au clavier.
9. Pages d'erreur et états vides compréhensibles (« Aucune chambre libre à ces dates. Essayez d'autres dates. »).

HORS PÉRIMÈTRE
Mise en avant payante (lot 5), PWA (lot 6), GESIMO Loyers.

CRITÈRES DE VALIDATION
- Parcours complets testés de bout en bout sur écran de 360 pixels de large : recherche, réservation, paiement sandbox, confirmation, check-in, check-out, avis.
- Parcours gérant complet testé : création d'une résidence, validation admin, réception d'une réservation, check-in.
- Chaque écran correspond à la maquette validée (liste des écarts, s'il y en a, justifiés).
- Aucun écran ne dépasse 3 secondes de chargement en réseau lent simulé (3G), ou l'écart est expliqué.
- Les e-mails sont envoyés en environnement de test et leurs textes viennent des Paramètres.
- Rapport de validation fourni.
```

---

## LOT 5 — Paramètres web et mise en avant payante

**But** : que le porteur du projet pilote tout depuis le navigateur, sans toucher au code ni à un fichier technique.

```
LOT 5 — Paramètres web et mise en avant payante

Lis docs/cahier-des-charges.pdf (RG-18 à RG-23, F-A7, section 7).

ÉTAPE A : MAQUETTES
Propose les maquettes de : assistant d'installation du premier lancement, espace Paramètres (toutes les rubriques), écran de test de connexion, historique des modifications, achat d'une mise en avant par le gérant, gestion des mises en avant par l'admin. Attends ma validation écrite.

ÉTAPE B : IMPLÉMENTATION
1. Assistant d'installation au premier lancement : création du compte super-administrateur, puis saisie guidée des réglages essentiels. Il ne peut plus être relancé une fois terminé.
2. Espace Paramètres, réservé au super-administrateur, par rubriques :
   - Général : nom, logo, coordonnées, textes légaux ;
   - Réservation : durée du blocage (120 minutes par défaut), acompte par défaut, politique d'annulation par défaut, délai d'annulation gratuite ;
   - Paiement : clés GeniusPay, mode test/réel, taux de commission, calcul du plancher d'acompte ;
   - Notifications : serveur e-mail, SMS activé ou désactivé (désactivé par défaut), modèles de messages ;
   - Mise en avant : durées et tarifs ;
   - Sécurité : durée de session, tentatives de connexion autorisées.
3. Secrets : chiffrés en base, affichés masqués, jamais renvoyés au navigateur en clair, avec un bouton « Tester la connexion » (e-mail, paiement) qui indique clairement réussite ou échec.
4. Chaque modification est tracée : qui, quand, ancienne et nouvelle valeur (sauf secrets, dont on trace seulement le fait qu'ils ont changé). Les réglages sensibles (paiement, commission, sécurité) exigent de ressaisir le mot de passe.
5. Validation des valeurs : une valeur impossible (commission négative, acompte inférieur au plancher, durée de blocage nulle) est refusée avec un message clair.
6. Un changement de réglage s'applique sans redémarrer le service et ne modifie jamais les réservations déjà créées (chaque réservation garde les conditions en vigueur à sa création).
7. Mise en avant payante : le gérant choisit une durée, paie à GESIMO via GeniusPay, l'annonce apparaît en tête de recherche avec la mention « Sponsorisé » pendant la période, puis revient normale automatiquement. L'admin peut suspendre une mise en avant.

HORS PÉRIMÈTRE
PWA, abonnements, GESIMO Loyers.

CRITÈRES DE VALIDATION
- Aucun réglage fonctionnel ne dépend d'un fichier .env en dehors de la base de données et de la clé maître (vérification par recherche dans le code, résultat montré).
- Test : changer la commission dans les Paramètres n'affecte pas les réservations existantes et s'applique aux nouvelles.
- Test : un secret n'est jamais renvoyé en clair par l'interface ni écrit dans les journaux.
- Test : seul le super-administrateur accède aux Paramètres ; tout autre rôle est refusé côté serveur.
- Test : l'historique des modifications est complet et non modifiable.
- Test : une mise en avant expire automatiquement et l'annonce redevient normale.
- Rapport de validation fourni.
```

---

## LOT 6 — PWA et performance

**But** : une application installable, rapide, utilisable même quand le réseau est faible.

```
LOT 6 — PWA et performance

Lis docs/cahier-des-charges.pdf (section 7) et les lots précédents.

ÉTAPE A : MAQUETTES
Propose : l'écran « Installer l'application » (invitation douce, jamais envahissante), l'écran de mode hors connexion, le message de reconnexion, l'icône et l'écran de démarrage. Attends ma validation écrite.

ÉTAPE B : IMPLÉMENTATION
1. PWA : manifeste, icônes, service worker, installation sur l'écran d'accueil du téléphone.
2. Mode dégradé en réseau faible ou coupé : les pages déjà consultées et « Mes réservations » restent lisibles ; les actions qui exigent le réseau (paiement, réservation) affichent un message clair et ne se perdent pas silencieusement.
3. Jamais de mise en cache de données sensibles (paiement, pièces d'identité) ; le cache est vidé à la déconnexion.
4. Performance : images compressées et redimensionnées, chargement différé, pages légères, code découpé. Budget : première page utile en moins de 3 secondes en 3G simulée sur téléphone d'entrée de gamme.
5. Mises à jour de l'application : un message simple « Une nouvelle version est disponible » avec un bouton, sans casser une réservation en cours.
6. Vérifie avec Lighthouse (ou équivalent) : PWA, performance, accessibilité, bonnes pratiques.

HORS PÉRIMÈTRE
Application native, notifications push.

CRITÈRES DE VALIDATION
- Rapport Lighthouse fourni (scores réels, mobile). Objectif : PWA installable validée, performance et accessibilité supérieures à 85 (sinon, écart expliqué).
- Test : couper le réseau, l'application affiche une page hors connexion claire, et reprend sans perte au retour du réseau.
- Test : un paiement en cours n'est jamais dupliqué par un rechargement ou un retour en arrière.
- Test : après déconnexion, aucune donnée personnelle n'est lisible depuis le cache.
- Installation vérifiée sur Android (Chrome) et sur iPhone (Safari), avec captures d'écran.
- Rapport de validation fourni.
```

---

## LOT 7 — Recette finale et mise en service

```
LOT 7 — Recette finale et mise en service

Lis docs/cahier-des-charges.pdf en entier.

PÉRIMÈTRE
1. Recette complète : pour chaque exigence F-C, F-G, F-A et chaque règle RG du cahier, un tableau « exigence / où elle est dans l'application / test / résultat ». Toute exigence non couverte est listée clairement.
2. Revue de sécurité : droits par rôle, injections, secrets, journaux, sessions, limitation des tentatives, sauvegardes, journal d'audit. Liste des risques restants classés par gravité.
3. Sauvegarde et restauration : sauvegarde quotidienne automatique de la base, et test de restauration réussi, preuve à l'appui.
4. Guide de déploiement pour une personne non technique ou son prestataire : étapes, hébergement (serveur privé virtuel avec PHP 8.3 ou plus, PostgreSQL, tâche planifiée Laravel et processus de files d'attente en arrière-plan ; un hébergement mutualisé qui n'offre que MySQL ne convient pas), nom de domaine, certificat, configuration initiale (seulement la base et la clé maître), puis assistant d'installation.
5. Guide d'utilisation en français simple pour : le client, le gérant, l'admin, le super-administrateur (une page chacun, avec captures d'écran).
6. Liste de contrôle avant lancement commercial : consultation juridique (encaissement pour compte de tiers, données personnelles, statut des meublés), recherche d'antériorité du nom GESIMO (OAPI), conditions générales et mandat de mise en relation, passage de GeniusPay du mode test au mode réel.

CRITÈRES DE VALIDATION
- Aucun critère « échoué » non expliqué ; les « non testé » sont listés avec leur raison.
- Restauration de sauvegarde démontrée.
- Une réservation réelle de bout en bout en mode test, du recherche au reversement enregistré, rejouée devant le porteur du projet.
- Rapport final de validation fourni.
```

---

## Conseils pour le porteur du projet

- **Tu valides, Claude Code exécute.** Si un rapport te semble flou, réponds : « Explique-moi cela plus simplement et montre-moi la preuve. »
- **Un lot à la fois.** Ne lance jamais deux lots en parallèle.
- **Garde chaque rapport de validation** dans un dossier `docs/rapports/` : c'est la trace de ce qui a été fait et validé.
- **Argent réel** : tant que le Lot 3 n'est pas validé et que GeniusPay est en mode test, aucun vrai paiement ne doit être accepté.
