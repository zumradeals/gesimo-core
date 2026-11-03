# Avelis Séjours — nettoyage des anciens styles (lot 9)

## Vision
Les lots 0 à 8 ont refait toutes les pages à la charte orange. Ce lot retire les règles de style devenues inutiles, sans rien changer à l'affichage. Décision du porteur, 4 novembre 2026 : lot confirmé, sans maquette (aucun changement visible).

## Méthode
1. Inventaire automatique : pour chaque règle des feuilles de style, on cherche si une de ses classes apparaît dans les vues, le code PHP, le JavaScript ou les routes. Une règle n'est candidate que si **tous** ses sélecteurs contiennent une classe introuvable.
2. Revue à la main des candidates : les classes fabriquées par le code (`espace-admin`, `espace-gerant`, `gq-b-*`) et les classes Tailwind échappées (`xl:grid-cols-3`) sont **conservées**.
3. Suppression, puis comparaison de 104 captures avant / après (public, voyageur, gérant, administration ; ordinateur et téléphone) par empreinte d'image.

## Résultat
- **151 règles supprimées** : 138 dans `site.css` (anciennes pages d'accueil, de connexion `auth*`, galerie, menu mobile, héros, etc.), 6 dans `accueil.css`, 7 dans `app.css` (anciens onglets et situation du profil gérant). Les feuilles passent de 114 050 à 104 100 octets.
- Plus aucune couleur verte de l'ancienne charte dans les styles, les vues ni les fichiers publics.
- Aucune vue orpheline (les vues restantes sont toutes appelées, parfois par leur nom construit : `pages.*`, erreurs, mise en page).
- Outil conservé : `tests/E2E/comparer-pages.mjs` (relevé d'empreintes avant / après pour tout futur nettoyage).
