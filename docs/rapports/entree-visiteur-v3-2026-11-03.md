# Lot 1 : entrée du visiteur

Connexion et inscription passent en deux moitiés qui remplissent l'écran (panneau bleu nuit avec garanties et photo, formulaire à droite ; formulaire en premier sur téléphone). Le mot de passe peut être affiché, l'aide du numéro de téléphone est visible, le lien par e-mail devient « Mot de passe oublié ? Recevoir un lien par e-mail », l'inscription propose deux grandes cartes (voyageur ou gérant) avec le rappel du contrôle de la pièce d'identité du gérant. Le lien de connexion et l'invitation deviennent des cartes centrées. Les pages d'erreur 403, 404, 419, 500 et 503 sont dessinées aux couleurs du site (auparavant seule la 403 l'était ; la 404 était un « Not Found » blanc) ; les pages 500 et 503 s'affichent même si la base de données est en panne. Contrat : `docs/contrats/entree-visiteur-v3.md`.

| Critère | Résultat | Preuve |
| --- | --- | --- |
| Connexion et inscription à pleine largeur, formulaire avant le panneau sur téléphone | Conforme | Navigateur (positions) ; captures |
| Afficher / Masquer le mot de passe (connexion, inscription, invitation) | Conforme | Navigateur |
| Aide du téléphone, « Mot de passe oublié ? », lien « Créer un compte » | Conforme | Tests |
| Messages de saisie simples (téléphone et mot de passe manquants) | Conforme | Tests |
| Cartes « Réserver » / « Proposer ma résidence » ; rappel gérant ; `?profil=gerant` conservé | Conforme | Tests + navigateur |
| Message « dates conservées » conservé | Conforme | Test |
| Lien de connexion et invitation en cartes centrées, textes d'origine conservés | Conforme | Tests (35 tests de connexion et d'invitation existants inchangés) |
| Pages 403, 404, 419, 500, 503 au gabarit commun | Conforme | Tests (une requête HTTP par page) ; captures |
| Pages 500 et 503 sans base de données, session ni réglage | Conforme | Test : zéro requête SQL, aucune zone « Se connecter » |
| Aucun détail technique affiché sur la page 500 | Conforme | Test avec message secret |
| Hors connexion aux couleurs de la charte | Conforme | Test |
| Aucun débordement de 320 à 2 560 px | Conforme | Chromium : connexion, inscription, lien, invitation, 404 |
| Règles de connexion, de sécurité et de gestion inchangées | Conforme | 491 tests, dont tous les anciens |

## Tests lancés

- `vendor/bin/pest` : **491 tests, 3 216 assertions, tous réussis** (477 précédents + 14 nouveaux). Pint appliqué, build Vite réussi.
- Parcours Chromium `tests/E2E/entree.mjs` réussi, aucune erreur JavaScript ; captures ordinateur et téléphone avant et après inspectées (connexion, inscription, lien expiré, 404).
- Non vérifié visuellement : les pages 403, 419, 500 et 503 en navigateur (vérifiées par les tests de contenu et de requêtes), la connexion avec le code SMS (désactivée), l'invitation avec un compte existant.

## Décisions prises seul

- La photo du panneau est l'image de l'accueil déjà administrable dans les Paramètres, avec le visuel illustratif Avelis à défaut.
- Les textes des pages 404, 419, 500 et 503 sont ceux de la maquette validée.
- Pour la page 500, le contact passe par le lien `/contact` écrit en dur, car aucune configuration n'est lue.
- L'invitation expirée propose « Aller à l'accueil » (auparavant aucun bouton).

## Décisions en attente

Aucune.

## Risques et limites

- La page 503 ne s'affiche que si le serveur est mis en maintenance avec la commande Laravel prévue ; une panne complète du serveur web reste celle de l'hébergeur.
- Les anciennes classes d'entrée (`.auth`) sont inutilisées mais encore dans les feuilles de style : retrait prévu au lot 9.
- Déploiement serveur nécessaire après fusion (reconstruction des styles) ; aucune migration.

## Vérification depuis un navigateur

1. Ouvrir Se connecter : cliquer sur Afficher pour voir le mot de passe ; valider à vide puis avec un mauvais mot de passe.
2. Sur téléphone, vérifier que le formulaire apparaît avant le panneau bleu.
3. Ouvrir Créer un compte : choisir « Proposer ma résidence » et lire le rappel sur la pièce d'identité.
4. Ouvrir une adresse inexistante (par exemple `/test-inexistant`) : page « Cette page n'existe pas ».
5. Ouvrir un ancien lien de connexion : carte « Lien expiré » avec retour à la connexion.
