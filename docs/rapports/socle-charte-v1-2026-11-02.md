# Lot 0 : socle commun de la charte orange

Toutes les pages portent désormais la charte orange : en-tête, pied de page, boutons, liens, menus latéraux, onglets, champs et cartes, dans le site public, l'espace voyageur, l'espace gérant et l'administration. Les mises en page ne changent pas : chaque page sera retravaillée dans son lot. Les pages de l'espace client, gérant et administration remplissent maintenant toute la largeur de l'écran, et le détail d'une réservation se répartit sur trois colonnes sur grand écran, à la demande du porteur. Contrat : `docs/contrats/socle-charte-v1.md`.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Classe de charte sur toutes les pages (public, voyageur, gérant, admin, erreurs) | Conforme | Test « toutes les pages portent la classe de la charte… » |
| Plus d'ancien vert dans les feuilles de style (hors couleurs de statut) | Conforme | Test d'absence des huit anciens verts |
| Jetons Tailwind « marque » en orange ; boutons orange à texte bleu nuit | Conforme | Test + captures |
| Application installable aux couleurs de la charte | Conforme | Test du manifeste et de la couleur de thème |
| Aucun plafond de largeur dans les espaces connectés | Conforme | Test + capture à 1 920 px |
| Aucune mise en page, règle ou donnée modifiée | Conforme | 473 tests existants inchangés et réussis |
| Rendu avant / après de 35 pages | Contrôlé | `docs/rapports/socle-charte-v1/` (public, gérant, administration) |

## Tests lancés

- `vendor/bin/pest` : **477 tests, tous réussis** (473 existants + 4 nouveaux) ; tests JavaScript : 9 réussis ; Pint appliqué ; build Vite réussi.
- Parcours navigateur de la réservation (fiche, connexion, informations, Mes réservations, détail, annulation) réussi après le changement.
- 35 pages représentatives capturées avant et après (public, voyageur, gérant, administration) ; planches de comparaison inspectées pour la connexion, le catalogue, Devenir partenaire, Aujourd'hui, l'éditeur de résidence, le calendrier, le tableau de bord administrateur, les Réglages et les Paiements.

## Décisions prises seul

- Le vert principal devient l'**orange texte** (`#b94700`) partout où il servait de texte, de bordure ou de fond à texte blanc : c'est la seule nuance orange lisible avec du blanc. Les boutons principaux restent orange vif à texte bleu nuit, comme sur l'accueil.
- Les alertes d'information restent bleues, les succès verts et les avertissements jaunes : ce sont des couleurs de statut, pas des couleurs de marque.
- Couleur de thème de l'application installable : orange pour toutes les pages (auparavant orange seulement sur l'accueil).
- Les e-mails sont laissés en l'état (lot 3).

## Limites

- Des pages gardent des traces de leur ancienne composition tant que leur lot n'est pas passé : par exemple les dégradés d'en-tête du catalogue et les pastilles de Devenir partenaire sont recolorés mais pas redessinés.
- Les captures ont été prises sur un jeu de données d'essai presque vide (pas de réservations, pas de litiges) : les tableaux remplis ne sont pas vérifiés visuellement.
- Déploiement serveur nécessaire après fusion (reconstruction des styles).

## Vérification depuis un navigateur

1. Ouvrir successivement Logements, Connexion, Devenir partenaire, Contact : en-tête et pied de page identiques à l'accueil, boutons orange.
2. Se connecter en voyageur, gérant puis administrateur : menu latéral bleu nuit avec repère orange, boutons orange.
3. Sur un grand écran, Mes réservations et le détail d'une réservation occupent toute la largeur.
4. Installer l'application sur téléphone : barre de statut orange.
