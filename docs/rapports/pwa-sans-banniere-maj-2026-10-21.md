# Suppression définitive de la bannière de mise à jour PWA

La capture du porteur montre « Une nouvelle version est disponible », distinct de l’invitation d’installation. Suppression du générateur de cette bannière, de son rappel périodique et du rechargement déclenché par son bouton. Une règle CSS empêche également un ancien script de la réafficher avec les nouveaux styles.

Le service worker, l’installation et les protections hors connexion restent fonctionnels. La nouvelle version attend la fermeture des anciens onglets selon le cycle normal du navigateur. Aucun rechargement ni activation forcée au milieu d’une réservation, d’un formulaire ou d’un paiement. Les anciens messages d’activation restent reconnus par le worker pour compatibilité avec les anciens clients.

| Critère | Preuve |
| --- | --- |
| Pas de bannière, même avec un worker en attente | Test JavaScript du vrai script sur ordinateur, téléphone et page sensible |
| Pas de rappel | Test d’absence de minuterie |
| Pas d’activation forcée | Test d’absence de message au worker |
| Compatibilité hors connexion | Suite de tests existante |

Validation locale : neuf tests JavaScript réussis (dont la suite worker), syntaxe du parcours PWA et build Vite réussis. CI réussie sur le commit applicatif `825da993ef27d0e495f7f792e7af02b91cbbdd0c` : 409 tests PHP / 2 539 assertions, tests JavaScript, migrations, build et deux parcours Chromium réussis. Le parcours navigateur historique PWA a été adapté à la nouvelle règle et contrôlé syntaxiquement ; il n’est pas exécuté par cette CI.

Preuve : https://github.com/zumradeals/gesimo-core/actions/runs/39665547874. Le commit suivant finalise uniquement ce rapport. Déploiement et nouvelle navigation nécessaires pour qu’un onglet déjà ouvert reçoive les fichiers corrigés. Les onglets contenant encore les anciens fichiers ne peuvent pas être modifiés à distance.

Demandes connexes : proposition de navigation et maquette partenaire préparées pour validation ; aucune suppression du catalogue ni nouvelle règle métier dans ce correctif.
