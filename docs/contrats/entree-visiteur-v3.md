# Avelis Séjours — entrée du visiteur v3 (lot 1)

## Vision

Entrer dans son compte, créer un compte, ou comprendre une erreur doit être simple pour tout le monde. Décisions du porteur, 3 novembre 2026 : maquette `docs/maquettes/entree-visiteur-v3.html` validée, recommandations retenues. Charte : `docs/contrats/accueil-reference-v3.md` et socle `docs/contrats/socle-charte-v1.md`. Aucune règle de gestion, aucune migration, aucune donnée modifiée.

## Connexion et inscription

- **Deux moitiés qui remplissent l'écran** (ordinateur) : à gauche un panneau bleu nuit avec trois garanties et la photo d'ambiance (réglage « Image de la page d'accueil » des Paramètres, visuel illustratif Avelis à défaut) ; à droite le formulaire. **Sur téléphone, le formulaire passe en premier** et la photo disparaît.
- **Connexion** : téléphone avec l'aide « 10 chiffres, sans espace ni indicatif », mot de passe avec bouton **Afficher / Masquer**, « Me connecter », puis « **Mot de passe oublié ? Recevoir un lien par e-mail** » (lien valable 15 minutes, utilisable une fois), lien « Créer un compte ». Le message « Vos dates sont conservées » reste affiché pour un visiteur venu d'une réservation. Le bloc de connexion par code SMS reste masqué tant que le réglage SMS n'est pas actif.
- **Inscription** : « Je veux… » devient deux grandes cartes (« Réserver un séjour », « Proposer ma résidence ») avec une phrase d'explication. Pour un gérant, un rappel dit que sa pièce d'identité est demandée et contrôlée avant la publication de sa première annonce (règle existante). Nom, téléphone, e-mail, mot de passe (8 caractères minimum, bouton Afficher), acceptation des conditions par le bouton. Le paramètre d'adresse `?profil=gerant` est conservé.
- **Messages** : « Indiquez votre numéro de téléphone. », « Indiquez votre mot de passe. », « Téléphone ou mot de passe incorrect. », « Trop de tentatives… » (durée réglée dans Paramètres), « Ce numéro de téléphone a déjà un compte. Connectez-vous. », « Cette adresse e-mail a déjà un compte. Connectez-vous. ». Le message de lien envoyé reste identique que le compte existe ou non, pour ne pas révéler les comptes.

## Lien de connexion et invitation

- Carte centrée : « Bienvenue » avec un bouton « Me connecter » (le lien n'ouvre pas la session tout seul) ; « Lien expiré » avec retour à la connexion.
- Invitation du personnel : nom de la résidence, personne qui invite et droits accordés ; choix du mot de passe (avec Afficher), connexion à un compte existant ou acceptation directe ; « Invitation expirée » avec retour à l'accueil.

## Pages d'erreur

Gabarit commun (`x-erreur.page`) : en-tête et pied du site, carte centrée avec icône, code, titre clair, phrase et au plus deux boutons.

| Page | Titre | Actions |
| --- | --- | --- |
| 403 | Cette page n'est pas accessible avec ce compte (message précis du refus, nom et rôle du compte) | Retour à mon espace ou Se connecter, Voir les logements ; « Me déconnecter » pour réserver avec un compte client |
| 404 | Cette page n'existe pas | Aller à l'accueil, Voir les logements |
| 419 | Votre session a expiré | Recharger la page, Me reconnecter |
| 500 | Un problème de notre côté | Réessayer, Nous contacter |
| 503 | Nous revenons très vite (maintenance) | Réessayer |

**Les pages 500 et 503 sont statiques** : aucune base de données, aucune session, aucun réglage (test des requêtes à zéro), pour s'afficher même quand la plateforme est en panne. Elles n'affichent jamais de détail technique.

## Hors connexion

Page inchangée sur le fond (message rassurant, Réessayer, lien vers les réservations enregistrées), couleurs de la charte (fond ivoire, cercle pêche, en-tête bleu nuit).

## Ce qui n'est pas ajouté

« Se souvenir de moi », connexion par réseau social, double authentification : ces fonctions n'existent pas aujourd'hui.

## Implémentation

Composants `x-auth.entree`, `x-auth.carte`, `x-erreur.page` ; vues Livewire de connexion, inscription, lien et invitation ; vues `errors/403, 404, 419, 500, 503` ; `resources/css/entree.css` ; messages de validation de `Connexion`. Anciennes classes `.auth*` laissées sans usage (retrait au lot 9). Aucune dépendance ajoutée.

## Vérification

Tests Pest (`tests/Feature/Refonte/EntreeVisiteurTest.php`) ; parcours navigateur `tests/E2E/entree.mjs` (afficher le mot de passe, erreurs, connexion, cartes du profil, lien et invitation expirés, 404, aucun débordement de 320 à 2 560 px, formulaire avant panneau sur téléphone) ; captures avant et après : `docs/rapports/entree-visiteur-v3/`.
