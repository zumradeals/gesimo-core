# Avelis Séjours — catalogue Logements v3 (lot 2)

## Vision

Trouver un logement vite, sur tous les écrans. Décisions du porteur, 3 novembre 2026 : maquette `docs/maquettes/logements-v3.html` validée, recommandations retenues (arrivée + nuits, filtre par type, communes rapides, puces des filtres). Ce contrat remplace les dispositions visuelles de `docs/contrats/logements-v2.md` ; ses règles métier restent applicables : sans dates, seules les annonces publiables avec le tarif standard de référence ; après une recherche datée valide, disponibilité et total réels ; aucun inventaire de démonstration en production. Charte : `docs/contrats/accueil-reference-v3.md` et `docs/contrats/socle-charte-v1.md`. Aucune migration.

## Page `/recherche`

- **Titre court** à la place du grand bandeau : « Trouvez votre logement ». La recherche et les premiers logements sont visibles sans défilement sur ordinateur.
- **Recherche** : commune, **date d'arrivée**, **nombre de nuits** (boutons − et +, mêmes limites que la réservation : minimum et maximum réglés dans Paramètres, 1 et 30 par défaut), voyageurs, « Rechercher ». Le départ est calculé et affiché (« Départ : lun. 23 déc. 2026, avant 11 h »). Changer la date d'arrivée garde le même nombre de nuits. Le navigateur ne bloque plus la saisie : les messages viennent du serveur, en phrases simples.
- **Liens existants** : `arrivee` + `depart` (anciens liens, fiches) et `arrivee` + `nuits` (formulaire de l'accueil) sont tous deux acceptés ; le départ ou le nombre de nuits en découle. Une arrivée sans départ ni nuits ne lance pas de recherche (comportement inchangé).
- **Communes rapides** : une ligne de boutons « Toutes » puis chaque commune avec son nombre de logements (mêmes conditions que le catalogue sans dates). Affichée dès qu'il y a au moins deux communes.
- **Filtres** (colonne à gauche sur ordinateur, dialogue natif sur téléphone) : budget (actif seulement avec une recherche datée, sur le montant total), équipements (tous exigés), **type de logement** (studio, appartement, chambre ; plusieurs possibles, un type inconnu est ignoré), réinitialiser. Le bouton Filtres du téléphone indique le nombre de filtres actifs, et « Afficher N logements » ferme le dialogue.
- **Puces des filtres actifs** au-dessus des résultats (équipements, types, budget), chacune avec sa croix de retrait, et « Tout effacer ». « Réinitialiser » conserve les dates et le nombre de nuits.
- **Tri** : « Recommandés » (annonces sponsorisées d'abord, puis du moins cher au plus cher : ordre inchangé) et « Prix décroissant ». Les annonces sponsorisées restent toujours en premier. Il n'y a pas d'option « Prix croissant » distincte : elle donnerait le même résultat que « Recommandés » (écart avec la maquette, voir le rapport).
- **Cartes** qui remplissent toute la largeur : autant de colonnes que l'écran le permet (une sur petit téléphone, cinq ou plus sur grand écran). Sans dates : « Tarif de référence » et prix par nuit. Avec dates : « Montant du séjour », « pour N nuits » et « soit X / nuit en moyenne ». Badge « Sponsorisé » et « Gestion vérifiée » selon les règles existantes. Limite de 60 annonces inchangée.
- **Chargement** : des cartes grises remplacent les annonces pendant la mise à jour.
- **Aucun résultat** : « Essayons d'autres critères. » et des boutons précis : « Toutes les communes », « Retirer « … » » (deux au plus), « Changer les dates » ; « Élargir ma recherche » quand aucun filtre n'est actif.
- **Messages** : « La date d'arrivée ne peut pas être dans le passé. », « Le nombre de nuits doit être compris entre X et Y. », erreurs de dates existantes.
- **Corrections** : « 1 logement présenté » au singulier ; tri par défaut nommé « Recommandés ».
- Les liens vers la fiche transmettent l'arrivée, le départ calculé et les voyageurs.

## Formulaire de recherche de l'accueil

Le champ « Départ » est remplacé par « Nombre de nuits » (− et +, minimum et maximum réglés dans Paramètres) ; le formulaire envoie `arrivee` et `nuits`. Le reste de l'accueil ne change pas.

## Largeur

L'en-tête, le pied de page et le contenu de toutes les pages du site occupent maintenant toute la largeur (suite de la règle 9 : plus de plafond de 1600 px). Les pages de lecture (Conditions, etc.) gardent une colonne de texte lisible.

## Implémentation

`Recherche` (Livewire) : `nuits`, `types`, `choisirCommune`, `basculerType`, `retirerBudget`, `plusNuit`, `moinsNuit`, `fixerNuits` ; `Disponibilites` : filtre par type et `logementsParCommune` ; vue `recherche.blade.php` ; `resources/css/logements.css` réécrit ; formulaire de l'accueil. Aucune dépendance ajoutée.

## Vérification

Tests Pest (`tests/Feature/Refonte/LogementsV3Test.php`, plus les tests existants du catalogue) ; parcours navigateur `tests/E2E/logements.mjs` (données `preparer-reservation.php` puis `preparer-catalogue.php`) : colonnes de cartes de 320 à 2 560 px, communes, filtres et puces, type, nuits, budget, états vides, dates incorrectes, dialogue sur téléphone, recherche depuis l'accueil ; captures avant et après : `docs/rapports/logements-v3/`.
