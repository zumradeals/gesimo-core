# Avelis Séjours — administration : argent et réglages (lot 8)

## Vision
Décisions du porteur, 4 novembre 2026 : maquette `docs/maquettes/administration-argent-reglages-v3.html` validée avec tous ses points et recommandations. Charte : `docs/contrats/accueil-reference-v3.md`, `docs/contrats/socle-charte-v1.md`. Pages à pleine largeur (règle 9), menu bleu nuit de l'administration (lot 7). **Aucune règle d'argent ne change** : montants en FCFA entiers, écritures jamais modifiées ni supprimées (une correction est une nouvelle écriture), mêmes décisions, même journal d'audit, mêmes droits ; aucune migration.

## Dispositions
- **Paiements, Reversements, Contrôle, Mises en avant, Journal d'audit, Modèles d'e-mails, Réglages (accueil, rubriques, Tests, Historique)** : en-tête commun, cartes et pastilles de la charte, même contenu et mêmes messages.
- **Tableaux** (paiements, écritures, journal, mises en avant, historique des reversements) : tableau sur ordinateur ; **sur téléphone (moins de 700 px), chaque ligne devient une carte** avec ses intitulés (attribut `data-l`, classe `tab-r`) : plus de défilement latéral.
- **Reversements** : liste des gérants à gauche, formulaire à droite sur ordinateur ; confirmation avant l'enregistrement (inchangée).
- **Contrôle** : message « Tout concorde » ou « n différences à examiner », quatre compteurs, calcul, différences et alertes en cartes (mêmes actions).
- **Réglages** : pastilles de rubriques, cartes d'état ; les secrets restent masqués, chiffrés en base, jamais affichés.
- **Installation** : « Bienvenue sur Avelis Séjours » sans émoji ; étapes en pastilles (les cinq étapes actuelles : Compte, Général, E-mail, Paiement, Fin) ; mêmes protections (code d'installation, 404 une fois terminée).

## Tests
`tests/Feature/Refonte/AdministrationArgentReglagesV3Test.php` (8 tests) ; parcours navigateur `tests/E2E/administration-argent-reglages.mjs`.
