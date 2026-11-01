# Avelis Séjours — référence accueil v3

## Vision

Permettre de rechercher un séjour dès l'ouverture sur ordinateur et donner à Avelis une identité chaleureuse et lisible. Le 1er novembre 2026, le porteur a validé l'intégration de la maquette orange et demandé qu'elle serve de référence pour les futures pages.

Référence visuelle : `docs/maquettes/accueil-v3/reference-validee.webp`. Ce contrat remplace les choix visuels v2 pour l'accueil uniquement ; les contrats métier restent applicables.

## Conception

| Jeton | Valeur | Rôle |
| --- | --- | --- |
| Orange | #FF6A00 | Actions principales, accent et numéros |
| Orange texte | #B94700 | Liens, petites icônes, surtitres sur fond clair |
| Bleu nuit | #17243B | Titres, texte des boutons orange et pied de page |
| Ivoire | #FFF8F0 | Entrée et communes |
| Pêche | #FFEADB | Accent discret, badges existants |
| Blanc | #FFFFFF | Formulaire et cartes |
| Gris | #526077 | Explications |
| Trait | #E4E7ED | Séparateurs |

Le texte des boutons orange est bleu nuit pour conserver un contraste lisible. Les petits textes sur fond clair utilisent l'orange foncé. Aucun changement de signification des badges ou statuts métier.

- En-tête compact, navigation publique existante et accès aux comptes conservés.
- Hero : titre à gauche, photo d'ambiance à droite, hauteur maîtrisée. Sur téléphone la photo décorative est retirée pour privilégier le formulaire.
- Recherche : commune, arrivée, départ, voyageurs et action sur une ligne dès 1 024 px. À 1 366 × 768, zoom 100 %, tous les champs et le bouton sont visibles sans défilement.
- Réassurance sous le formulaire ; annonces en trois colonnes sur ordinateur, deux sur tablette, une sur petit téléphone.
- Cartes des communes en photographie avec titre superposé, trois visibles sur ordinateur et défilement manuel accessible. Un petit inventaire reste compact.
- Confiance en deux colonnes, parcours en trois étapes, bandeau partenaire bleu nuit et footer compact avec liens légaux et coordonnées consultables.
- Les photos, annonces, prix, communes et éventuels avis du catalogue restent réels. Les trois exemples et photographies géographiques de la maquette sont des illustrations de conception, jamais injectées en base. Les photos d'ambiance existantes sont réutilisées ; le visuel principal reste administrable.
- Le bloc sponsorisé et les avis existants restent conditionnels ; aucun favori fictif, prix inventé ou bouton inactif n'est ajouté.

## Implémentation

`resources/css/accueil-reference.css` définit les jetons et composants de l'accueil. Leur portée est `body.site-accueil` et `.accueil-v3`. Les styles v2 devenus inutilisés sont retirés de `accueil.css`, qui conserve les composants communs et l'habillage des pages non refondues.

Les pages suivantes reprendront ces jetons après validation de leur conception, sans copier des couleurs en dur ni modifier les règles métier. La référence bitmap ne remplace pas ce contrat ni le code versionné.

Formulaire GET, routes, règles de validation, filtres de publication, réglages de marque, authentification et accès existants conservés. Aucune migration et aucune dépendance ajoutée au projet.

## Vérification

Parcours navigateur existant mis à jour : absence de débordement de 320 à 2 560 px, formulaire visible à 1 024 × 768 et 1 366 × 768, navigation, communes, recherche datée réelle et espaces protégés. Captures ordinateur et téléphone. Tests PHP existants sur les données réelles et les annonces non publiables conservés.
