# Rapport de validation — Lot 5 : Paramètres web et mise en avant payante

Date : 20 octobre 2026 · Branche : `claude/sleepy-brahmagupta-t7j8ta`

## 1. Ce qui a été fait

- **Maquettes** (16 écrans, `docs/maquettes/lot-5/index.html`) validées par le porteur du projet, avec mes recommandations.
- **Assistant d'installation** (`/installation`) : code d'installation affiché une seule fois sur le serveur (`gesimo:installation`, seule son empreinte est gardée), création du super-administrateur, puis général, e-mail, paiement, fin. Il devient « introuvable » une fois terminé.
- **Espace Paramètres** (`/admin/reglages`), réservé au super-administrateur, construit à partir d'un **catalogue unique** de réglages : Général (nom, logo, coordonnées, textes légaux), Réservation, Paiement, Notifications (serveur e-mail, SMS désactivé par défaut, modèles), Mise en avant, Sécurité ; plus « Tests de connexion » et « Historique ».
- **Secrets** : chiffrés en base, affichés « •••• Enregistré », jamais rechargés dans la page, jamais dans l'historique, le journal d'audit ni le journal du serveur.
- **Tracé** : chaque modification (qui, quand, avant, après ; pour un secret seulement « modifié ») ; l'historique est verrouillé dans la base. Les réglages sensibles (paiement, commission, sécurité) exigent de ressaisir le mot de passe (5 essais/heure).
- **Validation** : valeurs impossibles refusées avec un message clair, et **rien n'est enregistré** si une seule valeur est refusée.
- **Application immédiate, sans redémarrage** ; chaque réservation **garde les conditions du jour de sa création** : commission, délai d'annulation gratuite, heure d'arrivée, durée de blocage, acompte.
- **Tests de connexion** (e-mail, paiement) : « Réussi » ou « Échec » expliqué, sans jamais montrer de secret ; limités à 10 par heure.
- **Mise en avant** : le gérant choisit une offre (durée et prix des Paramètres), paie via GeniusPay (même circuit sécurisé que les réservations), l'annonce passe en tête de recherche avec « Sponsorisé » pendant la période puis redevient normale toute seule ; l'admin peut suspendre et reprendre.
- Écran Paramètres de l'accueil, textes légaux et nom/logo modifiables ; onglet « Réglages » dans le menu du super-administrateur.

## 2. Critères de validation

| Critère | Résultat | Preuve |
|---|---|---|
| Aucun réglage fonctionnel ne dépend d'un `.env` en dehors de la base et de la clé maître | Réussi | test de lecture du code : **0** appel à `env()`/`getenv()` dans `app/`, `routes/`, `database/seeders` ; `.env.example` = `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `DB_URL` seulement (résultat de la recherche ci-dessous) |
| Changer la commission n'affecte pas les réservations existantes, s'applique aux nouvelles | Réussi | `ParametresTest` (réservation confirmée, réservation en attente payée après le changement, nouvelle réservation) |
| Un secret n'est jamais renvoyé en clair ni écrit dans les journaux | Réussi | `ParametresTest` : page HTML, état envoyé au navigateur, historique, journal d'audit, journal du serveur |
| Seul le super-administrateur accède aux Paramètres ; les autres rôles sont refusés côté serveur | Réussi | `ParametresTest` : client, gérant, personnel, admin → 403 sur toutes les rubriques |
| L'historique est complet et non modifiable | Réussi | `ParametresTest` : avant/après/qui/quand ; modification, suppression et vidage refusés par la base |
| Une mise en avant expire automatiquement et l'annonce redevient normale | Réussi | `MiseEnAvantTest` : à J+8 plus « Sponsorisé » (même avant le passage de la tâche), la tâche la range « Terminée » ; dernière journée encore en avant, lendemain non |
| Valeurs impossibles refusées (commission négative, acompte sous le plancher, blocage nul…) | Réussi | `ParametresTest` |
| Assistant non relançable | Réussi | `InstallationTest` |
| Migration propre et réversible | Réussi | `migrate:fresh`, `rollback`, `migrate` (test existant) |
| Écran lisible à 360 px, sans débordement | Réussi | 12 pages parcourues (réglages ×8, mises en avant ×3, recherche) : 0 débordement ; menu à 6 onglets tient |
| **Essais réels GeniusPay (test de connexion, achat de mise en avant)** | **Non testé** | l'environnement de développement ne peut pas joindre geniuspay.ci : à faire après le déploiement |

### Recherche « .env » (résultat montré)

```
grep -rnE "\b(env|getenv)\(" app routes database resources bootstrap
→ resources/views/layouts/app.blade.php:56 : env(safe-area-inset-bottom)   (c'est du CSS, pas le fichier .env)
→ 0 autre occurrence
```
Les fichiers `config/*.php` du framework contiennent des `env('…')` avec valeurs par défaut (cache, base, session, mail…) ; seuls ceux qui ont un effet fonctionnel sont repris dans les Paramètres : e-mail (`Courriel`), durée de session (`AppliquerReglages`). `.env.example` ne contient que `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `DB_URL`.

## 3. Tests lancés

`vendor/bin/pest` : **tous réussis, 0 échec** (dont 42 nouveaux tests dans `tests/Feature/Lot5`, 318 au total) · `vendor/bin/pint --test` : conforme · `npm run build` : OK.

## 4. Décisions prises

- **Conditions figées à la création** (votre énoncé) : au Lot 3 la commission était figée à la confirmation ; elle l'est maintenant à la création. J'ai adapté le test du Lot 3 sur le délai d'annulation en conséquence.
- Pause après trop d'essais de connexion : **5 minutes** par défaut (comme avant) ; la maquette disait 15, la valeur reste réglable (1 à 120).
- Mise en avant : par résidence vérifiée ; commence au paiement confirmé ou juste après la période en cours ; non remboursable ; revenu entièrement pour GESIMO (écriture « mise en avant », ni commission ni part de gérant) ; suspension sans remboursement automatique ni prolongation.
- Le prix et la résidence d'une mise en avant sont figés par la base ; ses dates ne se fixent qu'au moment du paiement confirmé.
- Un second paiement pour la même mise en avant : alerte à l'administrateur (remboursement à faire à la main, hors plateforme).

## 5. Décisions en attente / ce qui reste à vous

1. **Taux de commission réel**, **offres de mise en avant** (durées, prix) : à saisir dans Réglages (aucune valeur par défaut).
2. **Remboursement d'une mise en avant** (suspension abusive, double paiement) : votre politique.
3. Texte « À propos » et textes légaux validés : à coller dans Réglages ▸ Général.

## 6. Risques et limites

- Le test de connexion « paiement » utilise `GET /account/balance` de GeniusPay (lecture seule, documentation officielle) : jamais exécuté pour de vrai depuis ici.
- Les journaux du serveur sont vérifiés par test, mais je n'ai pas audité les journaux d'Nginx/PHP-FPM du VPS.
- Le SMS ne peut pas être activé tant qu'aucun fournisseur SMS n'est branché (volontaire).
- Un remboursement automatique n'existe pas pour les mises en avant (aucune règle de remboursement n'a été définie).

## 7. Comment vérifier depuis un navigateur

Sur le serveur : `php artisan gesimo:installation`, puis ouvrir `/installation`. Ensuite, **Réglages** : changer la durée de blocage, enregistrer, regarder l'historique ; essayer une commission négative (refusée) ; remplacer une clé secrète (jamais réaffichée) ; « Tester la connexion ». Gérant : **Compte ▸ Mises en avant** : acheter une offre ; chercher un logement (« Sponsorisé » en tête) ; Admin ▸ Mises en avant : suspendre.
