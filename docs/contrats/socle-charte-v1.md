# Avelis Séjours — socle commun de la charte orange (lot 0)

## Vision

Le porteur a décidé le 2 novembre 2026 que toutes les pages passent à la charte orange de l'accueil (`docs/contrats/accueil-reference-v3.md`), par ordre de priorité (`docs/plan-refonte-orange.md`). Le lot 0 pose la base commune : dès maintenant, aucune page ne garde l'ancien vert pour son en-tête, son pied de page, ses boutons ou ses accents, même si sa mise en page sera retravaillée plus tard dans son propre lot.

Le lot 0 ne change aucune mise en page, aucune règle de gestion, aucune donnée.

## Ce qui change

- **Une classe `charte` sur le corps de toute page** : site public, espace voyageur, espace gérant, administration, pages d'erreur, assistant d'installation. Les règles de la charte (en-tête, pied de page, boutons, titres, cartes, champs, menus) s'appliquent à toutes.
- **Jetons de couleur** : bleu nuit `#17243b`, orange `#ff6a00`, orange texte `#b94700`, ivoire `#fff8f0`, pêche `#ffeadb`, gris `#526077`, trait `#e4e7ed` (définis une seule fois, `accueil-reference.css`). Les jetons Tailwind « marque » deviennent l'orange (50 ivoire, 100 pêche, 500 orange, 600 orange texte, 700 orange foncé).
- **Boutons** : orange vif avec texte bleu nuit (contraste conservé) ; boutons secondaires blancs à bordure bleu nuit.
- **Menus latéraux** (voyageur, gérant, administration) : bleu nuit avec repère orange.
- **Feuilles de style historiques** (`site.css`, `app.css`, `accueil.css`, `logements.css`, `partenaire.css`) : verts remplacés par les jetons (anciens verts foncés en bleu nuit, vert principal en orange texte, teintes claires en ivoire et pêche, traits et gris en jetons). Les couleurs de statut (succès en vert, alertes, avertissements) sont conservées car elles ont un sens.
- **Application installable** : couleur de thème orange, fond ivoire ; page hors connexion aux couleurs de la charte.
- **Largeur** : plus aucun plafond de largeur dans l'espace voyageur, gérant et administration (décision du porteur : les pages remplissent l'écran). Le détail d'une réservation se répartit sur trois colonnes dès 1 500 px.

## Ce qui n'est pas encore refait (lots suivants)

Les mises en page, textes et composants propres à chaque page (catalogue Logements, Devenir partenaire, formulaires, tableaux gérant et administration, e-mails, reçu) gardent leur structure actuelle avec les nouvelles couleurs. Les e-mails gardent leur ancien vert jusqu'au lot 3.

## Vérification

Tests Pest (classe de charte sur chaque espace, thème de l'application installable, absence des anciens verts dans les feuilles de style, absence de plafond de largeur) ; captures avant et après de 35 pages représentatives : `docs/rapports/socle-charte-v1/`.
