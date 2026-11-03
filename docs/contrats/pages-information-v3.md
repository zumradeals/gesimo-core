# Avelis Séjours — pages d'information v3 (lot 4)

## Vision
Devenir partenaire, À propos, Aide, Contact, Conditions, Confidentialité, Mentions légales et le profil public du gérant passent à la charte orange. Décisions du porteur, 3 novembre 2026 : maquette `docs/maquettes/pages-information-v3.html` validée avec tous ses points et recommandations. Charte : `docs/contrats/accueil-reference-v3.md`, `docs/contrats/socle-charte-v1.md`. Pages à pleine largeur (règle 9). Aucune migration, aucun réglage nouveau. **Aucun texte ne change** (les textes juridiques restent des brouillons à faire valider par un juriste).

## Dispositions
- **Bandeau de page** commun : ivoire, titre bleu nuit, surtitre orange.
- **Devenir partenaire** : entête ivoire en deux colonnes (texte et photo) sur ordinateur ; même ordre de sections ; bouton orange ; **barre d'action fixe « Devenir gérant partenaire »** sur téléphone ; si le taux de commission n'est pas réglé, l'encadré d'exemple dit « Le taux de commission sera communiqué à l'inscription. » (avant : aucun encadré).
- **À propos** et **Aide** : cartes et accordéon tactile (52 px, signe + / –). Pas de moteur de recherche.
- **Contact** : le formulaire passe en premier (y compris sur téléphone) ; la liste des questions fréquentes est retirée au profit d'un encadré « Une réponse rapide ? » avec le bouton « Voir les questions fréquentes ». Champs, messages et envoi inchangés.
- **Conditions, Confidentialité, Mentions légales** : nouveau composant `x-site.lecture` ; sommaire « Sur cette page » à gauche sur ordinateur (liens vers les sections), texte sur deux colonnes dès 1200 px (sauf les mentions légales, en tableau). Le bandeau « Brouillon de travail » est conservé. Un texte remplacé dans les Paramètres (Général) reste affiché tel quel, sans sommaire.
- **Profil public du gérant** : entête ivoire, initiale ou logo orange, badges verts arrondis, encadré du badge « Gérant certifié », cartes de logements en quatre colonnes sur très grand écran. Mêmes informations publiques qu'avant.
- Styles : `resources/css/pages-info.css`.

## Tests
`tests/Feature/Refonte/PagesInformationV3Test.php` (5 tests) ; parcours navigateur `tests/E2E/pages-information.mjs`.
