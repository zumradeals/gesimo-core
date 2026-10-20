# Rapport de validation — Lot 6 : PWA et performance

Date : 20 octobre 2026 · Branche : `claude/sleepy-brahmagupta-t7j8ta`

## 1. Ce qui a été fait

- **Maquettes** (9 écrans, `docs/maquettes/lot-6/index.html`) validées par le porteur du projet, avec mes recommandations.
- **Application installable** : manifeste (nom repris des Réglages), icônes « G » vert (192, 512, adaptée aux formes Android, iPhone), service worker, invitation douce à installer (Android : bouton « Installer » ; iPhone : aide en 3 gestes), jamais pendant un paiement ou une connexion, pas à la première visite, pas plus d'une fois par mois.
- **Mode hors connexion** :
  - les pages publiques déjà consultées restent lisibles ;
  - « Mes réservations » (et le détail) du **client** reste lisible, avec un bandeau « Hors connexion » et l'heure de la dernière version ;
  - une page jamais consultée affiche une page « Vous êtes hors connexion » claire ;
  - payer, réserver, annuler, reçu sont grisés sans réseau ;
  - une action qui échoue faute de réseau affiche « rien envoyé, rien débité », **sans jamais être renvoyée automatiquement** (pas de double paiement possible).
- **Aucune donnée sensible en cache** : le serveur marque lui-même ce qui peut être gardé (visiteur : pages publiques ; client : « Mes réservations ») ; paiement, reçus, pièces d'identité, argent, écrans gérant/admin/personnel ne sont jamais enregistrés. La copie « client » est effacée à la déconnexion (même sans réseau), après 7 jours, et dès que le serveur demande de se reconnecter.
- **Mise à jour** : « Une nouvelle version est disponible » avec un bouton ; jamais imposée, jamais affichée sur un écran de paiement ou de réservation.
- **Paiement et réservation sans doublon** : un seul départ de paiement à la fois par réservation (verrou), le paiement ouvert est repris au rechargement ou au retour en arrière ; un double appui sur « Réserver » reprend la première réservation.
- **Performance** : Livewire compilé avec l'application (ne bloque plus l'affichage), page affichée avant le chargement des scripts, miniatures de photos (480 px au lieu de 1600 px) avec chargement différé, dimensions et textes alternatifs, compression Nginx renforcée.

## 2. Critères de validation

| Critère | Résultat | Preuve |
|---|---|---|
| Rapport Lighthouse (scores réels, mobile) ; PWA installable ; performance et accessibilité > 85 | **Réussi** : performance **93 à 100**, accessibilité **100**, bonnes pratiques **100**, SEO 91 (4G et 3G lente) ; installabilité validée par Chrome | `docs/rapports/lot-6/lighthouse.md` (Lighthouse 13.5.0) + essai de bout en bout. Lighthouse 13 n'a plus de catégorie « PWA » : l'installabilité est vérifiée directement par Chrome (aucune erreur d'installabilité) |
| Réseau coupé : page hors connexion claire, reprise sans perte au retour | **Réussi** (vrai navigateur, réseau réellement coupé) | `tests/E2E/pwa.mjs` ; captures `hors-connexion-*.png`, `connexion-retablie.png` |
| Un paiement en cours n'est jamais dupliqué (rechargement, retour en arrière) | **Réussi** | `PwaTest` : double appui, rechargement, verrou, lien expiré remplacé sans toucher à l'ancien |
| Après déconnexion, aucune donnée personnelle lisible depuis le cache | **Réussi** | essai de bout en bout (vrai Chromium) + 15 essais du service worker en faux navigateur (`tests/Js/sw.test.mjs`) |
| Installation vérifiée sur Android (Chrome) et iPhone (Safari), avec captures | **Partiel — voir §4** | captures de l'interface simulée `android-invitation.png`, `iphone-*.png` ; installation sur de vrais téléphones **non testée** |
| Budget : première page utile < 3 s en 3G simulée | **Réussi** : 3G lente (400 kbit/s, 400 ms) : **2,5 s** ; 4G : **1,0 s** | `lighthouse.md` (une mesure aberrante à 5,3 s, deux re-mesures à 2,5 s) |
| Mise à jour sans casser une réservation en cours | **Réussi** | essai de bout en bout : message, bouton, rechargement ; absence du message sur les écrans de paiement (code et test du service worker) |
| Migrations, formatage | Réussi | `pint` conforme ; aucune nouvelle migration |

## 3. Tests lancés

`vendor/bin/pest` : **332 réussis, 0 échec** (14 nouveaux dans `tests/Feature/Lot6`, dont l'essai du service worker en faux navigateur lancé par Pest) · `node tests/Js/sw.test.mjs` : 17 essais réussis · `node tests/E2E/pwa.mjs` (vrai Chromium à 360 px, réseau réellement coupé, déconnexion, mise à jour) : tous réussis · `npm run build` : OK.

Un défaut trouvé grâce au vrai navigateur et corrigé : la compression du serveur (« Vary: Accept-Encoding ») empêchait de retrouver les pages enregistrées hors connexion ; le service worker l'ignore maintenant.

## 4. Ce qui n'a PAS pu être testé

- **Installation sur un vrai Android et un vrai iPhone** : je n'ai pas d'appareils. Vérifié : Chrome ne signale aucune erreur d'installabilité (seule réserve : mon navigateur d'essai est en navigation privée, où l'installation est interdite). Les captures d'écran de l'invitation Android et de l'aide iPhone sont celles de l'interface dans un navigateur simulant ces téléphones. **À confirmer sur vos téléphones après le déploiement** : liste de contrôle dans `DEPLOIEMENT.md` § 8.
- **Mesures sur le vrai serveur** : Lighthouse a été lancé en local derrière un relais qui compresse comme Nginx, pas sur le VPS. À refaire après le déploiement.
- **Mode hors connexion sur Safari iPhone** : le comportement des service workers y est plus restrictif (cache plus court) ; non vérifié.

## 5. Décisions prises

- Livewire est compilé avec l'application (`config/livewire.php`, `resources/js/`) et chargé après l'affichage de la page : la page apparaît avant que les boutons réagissent (quelques secondes sur réseau très lent).
- Le bouton « Réessayer » du message d'échec ferme le message et invite à retoucher le bouton (rien n'est renvoyé automatiquement) : léger écart avec la maquette, voulu pour empêcher un double paiement.
- La copie hors connexion est réservée au client seul ; un compte gérant, personnel ou admin n'en a jamais.

## 6. Risques et limites

- La première visite sur réseau très lent montre la page mais les actions ne répondent qu'une fois les scripts chargés.
- Une copie « Mes réservations » peut être périmée (bandeau avec l'heure) : aucune action n'y est possible sans réseau.
- Sans HTTPS le service worker ne s'active pas (le déploiement l'impose).

## 7. Comment vérifier depuis un navigateur

Ouvrir le site, naviguer sur 2 ou 3 pages, installer l'application ; se connecter, ouvrir « Mes réservations », passer en mode avion : la liste reste lisible ; se déconnecter, remettre le mode avion : plus rien de personnel. Procédure complète : `DEPLOIEMENT.md` § 8.
