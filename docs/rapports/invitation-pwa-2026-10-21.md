# Invitation PWA — correction du 21 octobre 2026

## Comportement validé

Aucune invitation automatique à installer sur ordinateur. Sur téléphone, deux choix initiaux : « Installer » et « Plus tard ». Une invitation douce ne revient pas à chaque écran. Cette correction conserve le bandeau existant ; elle ne crée pas de nouvel écran.

## Corrections

- Le filtre exige un appareil mobile en plus du tactile et de la largeur ; un ordinateur hybride dans une petite fenêtre n’est plus assimilé à un téléphone.
- L’invitation est proposée au plus une fois dans la session de l’onglet, même si la personne continue sans cliquer.
- « Plus tard » conserve le report de 30 jours déjà présent ; un refus dans le dialogue natif produit le même effet.
- Si le stockage de session est bloqué, aucune invitation automatique n’est affichée, pour éviter les relances à chaque page.
- Sur iPhone, « Installer » ouvre les instructions Safari nécessaires : le site ne prétend pas installer automatiquement ni avoir terminé l’installation.
- Une erreur du dialogue natif est prise en charge sans rejet JavaScript non traité. Un stockage invalide ne fait plus planter les bandeaux.
- « Plus tard » dans le message distinct de mise à jour est mémorisé durant la session de l’onglet : ni la minuterie de 30 secondes ni une navigation ne réaffiche ce message. La mise à jour reste manuelle.

## Validation

| Critère | Preuve |
| --- | --- |
| Ordinateur, y compris tactile | Test des événements `beforeinstallprompt` avec filtre desktop et fenêtre étroite |
| Mobile Installer / Plus tard | Test du HTML réel produit et des actions du bandeau |
| Navigation sans clic | Nouvelle page avec stockage partagé : aucun nouveau bandeau |
| Report et refus | Tests nouvelle session, refus natif et erreur du dialogue |
| iPhone | Instructions Safari après « Installer », sans état installé fictif |
| Installation existante, première visite, paiement | Aucune invitation |
| Stockage bloqué ou malformé | Pas de panne, invitation non réaffichée |
| Mise à jour reportée | Minuterie et navigation testées ; aucun message d’activation automatique |

`node --test tests/Js/pwa-invitation.test.mjs` : 8 tests réussis. `npm run build` : réussi.
Le nouveau test exécute le vrai script de page dans un navigateur simulé Node, avec événements et stockage partagés ; aucune dépendance ajoutée. Il est inclus dans GitHub Actions avec les tests existants du service worker.
Un essai local dans Chromium, avec événements d’installation simulés, confirme aussi : aucun bandeau sur ordinateur tactile en fenêtre de 390 px ; deux choix sur Android simulé ; fermeture puis navigation sans nouvelle proposition ; aucune erreur JavaScript. Cet essai ne prétend pas installer une application dans le système du téléphone.

La vérification générale GitHub a été déclenchée et est encore en file d’attente lors de la rédaction : https://github.com/zumradeals/gesimo-core/actions/runs/39617842816. Aucun nouveau résultat de la suite PHP complète n’est revendiqué pour cette correction JavaScript.

La capture transmise n’était pas disponible au chemin local annoncé : aucun diagnostic visuel n’est revendiqué. Les défauts de relance sont confirmés par le code et les tests.

## Limites et recette

Le navigateur conserve la maîtrise de son interface native d’installation ; cette correction contrôle les invitations affichées par le site. Les alertes de réseau et les propositions distinctes de mise à jour ne sont pas des invitations à installer.
Pas de changement métier, de migration ou de déploiement serveur dans cette correction.

Après mise à jour du serveur : parcourir trois pages sur téléphone, fermer par « Plus tard », naviguer et recharger ; vérifier l’absence de relance. Essayer un ordinateur et un ordinateur tactile avec fenêtre étroite : aucune invitation d’installation. Sur Safari iPhone, « Installer » affiche l’aide ; sur Android compatible, il ouvre le dialogue natif. Reporter une mise à jour puis naviguer : le bandeau reste fermé pendant cette session.
