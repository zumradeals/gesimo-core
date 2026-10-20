# GESIMO Séjours

Plateforme de réservation et de gestion de résidences meublées en Côte d'Ivoire.
Dépôt : `gesimo-core`. Les documents de conception sont dans le dossier [`docs/`](docs/).

**Pile :** PHP 8.3+, Laravel, Livewire, Alpine.js, Tailwind CSS, PostgreSQL.

## Lancer le projet sur son ordinateur

Il faut : PHP 8.3 ou plus (avec l'extension `pdo_pgsql`), Composer, Node.js 22, PostgreSQL 16.

1. **Installer les dépendances**
   ```bash
   composer install
   npm install --ignore-scripts
   ```
2. **Créer la base de données** (une pour le travail, une pour les tests)
   ```bash
   createdb gesimo
   createdb gesimo_test
   ```
   Le rôle PostgreSQL utilisé doit pouvoir créer l'extension `btree_gist` (elle est activée par une migration).
3. **Préparer le fichier `.env`**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Ouvrir `.env` et renseigner `DB_URL=pgsql://utilisateur:motdepasse@127.0.0.1:5432/gesimo`.
   Mettre `APP_ENV=local` et `APP_DEBUG=true` en développement.
   **Seules** ces valeurs vont dans `.env` (voir plus bas).
4. **Créer les tables**
   ```bash
   php artisan migrate
   ```
5. **(Facultatif) Charger les données de démonstration** — tout est marqué « DÉMO »
   ```bash
   php artisan db:seed
   ```
   Le mot de passe des comptes DÉMO est tiré au hasard et affiché une seule fois. Impossible en production.
6. **Construire les fichiers du site, puis démarrer**
   ```bash
   npm run build
   php artisan serve
   ```
   Ouvrir <http://localhost:8000>.

## Lancer les tests

```bash
vendor/bin/pest        # tests (utilisent la base gesimo_test)
vendor/bin/pint --test # vérification du formatage
vendor/bin/pint        # corriger le formatage
```
Les tests se connectent à `pgsql://gesimo:gesimo_test@127.0.0.1:5432/gesimo_test` (voir `phpunit.xml`) :
créer ce rôle et cette base, ou adapter `phpunit.xml`. Cette base ne sert qu'aux tests et se vide à chaque lancement.

## Où vivent les réglages ?

Dans le fichier `.env`, **uniquement** :

| Valeur | Rôle |
|---|---|
| `DB_URL` | adresse de connexion à la base de données |
| `APP_KEY` | clé de chiffrement maître : protège tous les secrets enregistrés en base |
| `APP_ENV`, `APP_DEBUG` | indicateurs techniques (`production` / `false` chez l'hébergeur) |

Tout le reste (clés GeniusPay, e-mail, SMS, commission, durée de blocage, acompte, textes) se règle dans l'espace
Paramètres du site et est enregistré en base ; les secrets y sont chiffrés. **Sauvegarder `APP_KEY` à part, hors du dépôt :**
sans elle, les secrets enregistrés sont perdus.

## Organisation

- `app/Noyau` — comptes, rôles, droits, journal d'audit
- `app/Sejours` — résidences, unités, réservations, calendrier
- `app/Paiement` — paiements, écritures comptables, reversements
- `app/Parametres` — réglages et secrets chiffrés
- `database/migrations` — structure de la base ; les règles critiques y sont gravées
- `tests` — tests automatisés

## Règles gravées dans la base

Elles s'appliquent même si l'on écrit directement en SQL :
- **Pas de chevauchement** : pour une même unité, aucune réservation active ni blocage ne peut recouvrir les mêmes nuits
  (une nuit = de l'arrivée à la veille du départ ; arriver le jour du départ d'un autre client est permis).
- **Pas de paiement compté deux fois** : une référence de paiement est unique.
- **Écritures comptables intouchables** : ni modification, ni suppression. Une correction est une nouvelle écriture.
- **Montants** : entiers (FCFA), strictement positifs là où c'est requis.
- **Hors plateforme** : aucune réservation prise par téléphone ne peut avoir de paiement, d'écriture ou de commission.
