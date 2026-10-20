#!/usr/bin/env bash
# GESIMO Séjours — mise à jour du site (à lancer en root, à chaque nouvelle version) :
#   sudo bash /var/www/gesimo/deploy/mise-a-jour.sh
set -euo pipefail

DOSSIER="${DOSSIER:-/var/www/gesimo}"
BRANCHE="${BRANCHE:-main}"
[ "$(id -u)" -eq 0 ] || { echo "Lancez ce script en root (sudo)."; exit 1; }
cd "$DOSSIER"

# Sauvegarde de la base avant toute modification.
/etc/cron.daily/gesimo-sauvegarde

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

git fetch origin "$BRANCHE"
git reset --hard "origin/$BRANCHE"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
php artisan queue:restart
systemctl reload php8.3-fpm

echo "Mise à jour terminée."
