# Catalogue Logements — version approuvée

Maquette ordinateur et téléphone approuvée en conversation avant intégration, référence `docs/maquettes/logements-v2.html`. La route `/recherche` et les fiches existantes sont conservées.

- Catalogue dès l’ouverture, sans dates : unités actives, résidence publiée et gérant éligible, capacité suffisante et tarif standard renseigné. Prix clairement nommé « Tarif de référence », par nuit. Une réservation déjà présente ne masque pas l’annonce dans ce mode ; aucune disponibilité n’est promise.
- Recherche avec dates : deux dates valides, arrivée non passée, départ après arrivée. Disponibilités et prix totaux fournis par le moteur existant, comprenant les tarifs week-end/fête et les occupations. Dates incorrectes : erreur visible et retour au catalogue de référence.
- Commune, voyageurs et tous les équipements sélectionnés filtrent les offres. Budget maximum sur le total uniquement, désactivé avant une recherche datée valide. Réinitialiser conserve les dates.
- Sponsorisation explicite, prioritaire ; ordre aléatoire entre sponsorisés, tri croissant/décroissant pour les autres offres. Limite existante de 60 résultats conservée, compteur des logements présentés.
- Cartes compactes sur trois colonnes sur grand écran, deux aux tailles intermédiaires et une sur téléphone. Photos réelles ou composant sans photo existant. Badges uniquement selon les justificatifs réellement validés.
- Filtres latéraux sur ordinateur ; dialogue natif sur téléphone avec fermeture, Échap et retour au bouton d’ouverture. Mêmes champs, sans duplications. Navigation et footer communs conservés.
- Les fiches restent les vraies fiches publiques, avec transmission des dates/voyageurs. Aucun ajout d’annonces fictives ou de photos de maquette aux logements.

Aucun changement des règles de paiement, réservation, certification ou annulation. Aucune dépendance ni migration ajoutée.
