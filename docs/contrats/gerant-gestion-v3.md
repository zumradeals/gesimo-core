# Avelis Séjours — gérant : gestion (lot 6)

## Vision
Décisions du porteur, 4 novembre 2026 : maquette `docs/maquettes/gerant-gestion-v3.html` validée avec tous ses points et recommandations. Charte : `docs/contrats/accueil-reference-v3.md`, `docs/contrats/socle-charte-v1.md`. Pages à pleine largeur (règle 9), menu latéral bleu nuit du gérant (`espace-gerant`, lot 5). **Aucune règle, aucun champ, aucun montant ni message ne change** ; aucune migration.

## Écrans
- **Mes résidences** (`/gerant/residences`) : une carte par résidence (statut, badge « Gérant vérifié » selon le lot 5, nombre de logements), boutons « Modifier » et « 📍 Accès », « Suivre mon dossier » pour une résidence en attente, motif d'un refus, confirmation après envoi, message pour la première résidence.
- **Éditeur de résidence en trois étapes** (`/gerant/residences/nouvelle`, `/gerant/residences/{id}`) : étapes en grandes pastilles (en cours, faite), contenu pleine largeur, mêmes champs et mêmes messages.
- **Mon profil gérant** (`/gerant/profil`) : **l'onglet « Mes documents » est retiré** (la pièce d'identité et les baux se déposent dans « Ma vérification », un seul endroit) ; deux blocs, informations privées et présentation publique ; encadré « Votre situation » (identité à valider, identité validée, gérant vérifié) ; rappel de ce que voit le public. Le composant garde ses méthodes de dépôt (compatibilité).
- **Argent**, **Mises en avant** (liste, achat, retour de paiement), **Mon personnel**, **Aide du gérant** : cartes et grilles de la charte, mêmes informations.
- **Signaler un problème** (gérant) : inchangé, déjà refait au lot 3.

## Tests
`tests/Feature/Refonte/GerantGestionV3Test.php` (8 tests) ; parcours navigateur `tests/E2E/gerant-gestion.mjs`.
