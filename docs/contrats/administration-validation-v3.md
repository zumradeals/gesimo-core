# Avelis Séjours — administration : validation et litiges (lot 7)

## Vision
Décisions du porteur, 4 novembre 2026 : maquette `docs/maquettes/administration-validation-v3.html` validée avec tous ses points et recommandations. Charte : `docs/contrats/accueil-reference-v3.md`, `docs/contrats/socle-charte-v1.md`. Pages à pleine largeur (règle 9). **Droits, décisions, journal d'audit et règles inchangés** ; aucune migration.

## Dispositions
- **Habillage** : menu latéral bleu nuit (classe `espace-admin`). Menu du bas sur téléphone : Tableau, Résidences, Réservations, Litiges (avant : Accueil, Réservations, Argent, Contrôle).
- **Tableau de bord** : « Bonjour {prénom} » sans émoji ; liens « Traiter » (litige) et « Contrôler » (résidence) dans « À traiter maintenant ».
- **Résidences à valider** : titre « Résidences à valider (n) » ; blocs dossiers de gérants, **contrats de bail à contrôler** (obligatoires), résidences en attente, décisions récentes.
- **Valider une résidence** : section « Contrat de bail — Obligatoire » ; **récapitulatif identité et bail** (Validée / Validé / En contrôle / Refusé / Absent(e)) au-dessus de « Valider et mettre en ligne » ; ligne « Gérant vérifié : oui / pas encore » à la place de la certification. Les règles du lot 5 s'appliquent (pas de mise en ligne sans bail contrôlé).
- **Dossier d'un gérant** : badge « Gérant vérifié », encadré « Ce que ce contrôle change » (deux documents ; le certificat de résidence n'est plus demandé, anciens dépôts conservés en historique).
- **Réservations** et **Litiges** : cartes de la charte ; même filtre, même décision écrite obligatoire ; le remboursement passe toujours par « Contrôle ».

## Tests
`tests/Feature/Refonte/AdministrationValidationV3Test.php` (7 tests) ; parcours navigateur `tests/E2E/administration-validation.mjs`.
