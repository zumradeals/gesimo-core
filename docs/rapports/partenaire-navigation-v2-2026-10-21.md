# Intégration de la page partenaire et du menu approuvés

La maquette approuvée est intégrée à la page Devenir partenaire dans la pile existante. Les sections présentent les bénéfices, les fonctions du gérant, les étapes d’inscription/publication, les justificatifs, les conditions financières et une FAQ. Les boutons conduisent à l’inscription gérant et au contact. La marque, le footer et les données financières restent dynamiques.

Le menu public présente Accueil, Logements, À propos, Devenir partenaire, Aide et Contact. La page active est indiquée. Sur téléphone, un menu complémentaire natif donne ces accès sans remplacer les quatre onglets. Échap et clic extérieur ferment le menu après initialisation ; la navigation native fonctionne sans JavaScript.

| Critère | Preuve |
| --- | --- |
| Fidélité à la maquette | Comparaison visuelle des vues Blade et de la référence approuvée |
| Menu cohérent et page active | Test PHP sur les six routes et parcours navigateur |
| Inscription gérant | Clic depuis le hero puis contrôle du profil préselectionné |
| Justificatifs conformes | Tests de contenu et règles existantes inchangées |
| Commission dynamique | Tests existants du taux et de l’exemple chiffré |
| Téléphone et ordinateur | Contrôle des largeurs 320 à 2 560 px |

## Validation

Compilation Vite, Pint, compilation Blade et contrôles Chromium locaux réussis à huit largeurs (320, 390, 768, 1 024, 1 280, 1 440, 1 920, 2 560 px) : aucune image cassée, aucun débordement global, menu mobile ouvert/fermé sans erreur JavaScript.

CI réussie sur le commit applicatif `f39a8ad` : **411 tests / 2 575 assertions**, migrations réversibles, tests PWA, build et trois parcours Chromium (gérant, accueil, partenaire/navigation). La FAQ, les six entrées du menu et l’inscription avec profil gérant présélectionné sont vérifiées. Captures ordinateur et téléphone inspectées.

Preuve : https://github.com/zumradeals/gesimo-core/actions/runs/39667849609. Le commit suivant finalise uniquement le rapport.

## Vérification navigateur

Ouvrir Devenir partenaire, consulter les sections et ouvrir une question fréquente. Sur téléphone, ouvrir Menu, accéder à À propos, revenir à Devenir partenaire et créer un compte : le profil gérant doit être préselectionné. Sur ordinateur, vérifier les six liens et leur état actif. Sur une largeur intermédiaire, le menu peut occuper deux lignes.

## Décisions et limites

Maquette et menu explicitement approuvés avant intégration. L’exemple financier existant est conservé pour garder la transparence des conditions et son lien aux Paramètres. Aucun ajout de dépendance, de migration ou de donnée fictive. Le déploiement serveur reste nécessaire après fusion.
