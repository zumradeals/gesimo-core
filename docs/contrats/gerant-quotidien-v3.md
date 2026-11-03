# Avelis Séjours — gérant au quotidien, vérification du gérant et itinéraire du voyageur (lot 5)

## Vision
Décisions du porteur, 4 novembre 2026 : maquette `docs/maquettes/gerant-quotidien-v3.html` validée avec tous ses points et recommandations. Charte : `docs/contrats/accueil-reference-v3.md`, `docs/contrats/socle-charte-v1.md`. Pages à pleine largeur (règle 9). **Ce contrat remplace la décision du 21 octobre sur le profil gérant** (`docs/contrats/profil-gerant-certification-v1.md`, sections 2, 4 et 5) pour ce qui concerne le certificat de résidence, le badge de résidence séparé et le caractère facultatif du justificatif de gestion.

## 1. Vérification du gérant : deux documents
- **Pour publier** : la pièce d'identité du gérant (une fois pour toutes ses résidences) **et** le contrat de bail, ou tout justificatif de gestion, **de chaque résidence**. Les deux sont contrôlés par l'équipe Avelis (décision humaine, journalisée).
- **Un seul badge « Gérant vérifié »** : pièce d'identité acceptée ET au moins une résidence au justificatif de gestion accepté (`ProfilGerant::estVerifie()`). Il remplace « Gérant certifié » et « Gestion vérifiée » / « Droit de gestion vérifié » partout (profil public, fiche, recherche, cartes, statut de résidence). Sur une annonce, le badge s'affiche si la résidence a son bail accepté.
- **Certificat de résidence** : n'est plus demandé (plus de dépôt dans « Mon profil gérant »). Les documents déjà déposés restent en base et visibles de l'administration ; `estCertifie()` subsiste pour la compatibilité mais n'est plus affiché.
- **Envoi d'une résidence** (étape « Vérification et envoi ») : impossible tant qu'aucun bail non refusé n'est déposé (« Ajoutez le contrat de bail de la résidence… »), sauf résidence déjà validée. Un bail refusé doit être remplacé.
- **Mise en ligne par l'administrateur** : refusée tant que le bail n'est pas accepté (la case « J'ai contrôlé le contrat de bail » le valide dans la foulée).
- **Résidences déjà publiées** : elles restent publiées. Le gérant voit un bandeau « Complétez votre vérification » lui demandant le bail pour obtenir le badge.
- **Page « Ma vérification »** (`/gerant/verification`, menu du gérant) : deux cartes (identité ; bail par résidence), quatre états (À fournir, En contrôle, Validé, Refusé avec le motif), dépôt par photo ou fichier (PDF, JPEG, PNG, 5 Mo), « Et ensuite ? », confidentialité.
- **Aujourd'hui** : bandeau « À faire en premier » (identité d'abord, puis bail d'une résidence ; rien pendant un contrôle en cours).
- Textes mis à jour en cohérence : Devenir partenaire, Aide du gérant, étape 3 de l'éditeur de résidence, décision administrative.

## 2. Accès à la résidence (côté gérant)
- Page `/gerant/residences/{id}/acces` (lien dans l'éditeur de résidence, à la place des champs latitude et longitude) : bouton « Utiliser ma position actuelle » (le téléphone donne sa position), ou collage d'un lien Google Maps **complet** (position lue dans le lien ; les liens courts maps.app.goo.gl sont refusés avec une explication, aucun appel à un service extérieur), adresse écrite, **repères** (300 caractères au maximum, nouveau champ `residence.reperes`, migration réversible), aperçu de ce que verra le voyageur.
- **Déplacer une résidence déjà validée de plus de 500 m la fait revoir** par Avelis (elle repasse en attente) ; placer la position la première fois ou la corriger de quelques mètres ne change rien. Ce seuil remplace l'ancienne règle « toute modification des coordonnées » (imprécision du GPS).
- Droits : seul le gérant de la résidence (ou ses responsables) y accède.

## 3. Comment venir (côté voyageur)
- Page `/reservation/{id}/venir`, lien « Comment venir » dans « Le gérant et le lieu » du détail de réservation. Réservée au voyageur de la réservation.
- **Après confirmation seulement** : carte, gros bouton « Me conduire à la résidence » (Google Maps ; Waze et Plans proposés juste en dessous), adresse écrite, repères, « Appeler le gérant », « Envoyer l'adresse à mon chauffeur (WhatsApp) » (message prêt à envoyer : adresse, repères, lien de la carte). Sans position : message qui renvoie aux repères et à l'appel, sans bouton de carte.
- Avant la confirmation (réservation en attente de paiement) : message de patience et bouton de paiement ; **aucune adresse, aucun repère, aucune position**. Le public ne voit toujours que la commune.
- Aucune carte intégrée, aucune bibliothèque, aucune clé : de simples liens (`Itineraire`).

## 4. Aujourd'hui, Réservations, Calendrier, Réservation par téléphone
Même contenu et mêmes règles, charte orange, cartes en plusieurs colonnes (jusqu'à quatre sur très grand écran), menu latéral bleu nuit du gérant (classe `espace-gerant`), bandeau « À faire en premier » sur Aujourd'hui.

## Tests
`tests/Feature/Refonte/GerantQuotidienV3Test.php` (24 tests) ; anciens tests adaptés (bail obligatoire) ; parcours navigateur `tests/E2E/gerant-quotidien.mjs` avec `tests/Support/preparer-espace-voyageur.php`.
