#!/usr/bin/env bash
# GESIMO Séjours — installation complète sur un VPS neuf (Ubuntu 24.04), à lancer UNE SEULE FOIS, en root.
#
#   sudo DOMAINE=gesimo.dgafrique.com EMAIL_CERTIFICAT=vous@exemple.com bash installer.sh
#
# Ce que fait ce script : installe PHP, PostgreSQL, Nginx, crée la base, installe le site,
# active le HTTPS gratuit (Let's Encrypt), la tâche planifiée, le traitement en arrière-plan
# et la sauvegarde quotidienne. Il est « rejouable » : le relancer ne casse rien.
set -euo pipefail

DOMAINE="${DOMAINE:-gesimo.dgafrique.com}"
EMAIL_CERTIFICAT="${EMAIL_CERTIFICAT:-}"
DEPOT="${DEPOT:-https://github.com/zumradeals/gesimo-core.git}"
BRANCHE="${BRANCHE:-main}"
DOSSIER="${DOSSIER:-/var/www/gesimo}"
BASE_NOM="gesimo"
BASE_UTILISATEUR="gesimo"
SECRETS="/root/gesimo-secrets.txt"

[ "$(id -u)" -eq 0 ] || { echo "Lancez ce script en root (sudo)."; exit 1; }
[ -n "$EMAIL_CERTIFICAT" ] || { echo "Indiquez votre e-mail : EMAIL_CERTIFICAT=vous@exemple.com (pour le certificat HTTPS)."; exit 1; }

etape() { printf '\n\033[1;32m== %s\033[0m\n' "$1"; }

etape "1/9 Logiciels du serveur"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx postgresql postgresql-contrib git unzip curl ca-certificates ufw certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
if ! command -v node >/dev/null || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 22 ]; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
fi
if ! command -v composer >/dev/null; then
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

etape "2/9 Réglages PHP (photos de 5 Mo, plusieurs à la fois)"
cat > /etc/php/8.3/fpm/conf.d/90-gesimo.ini <<'INI'
upload_max_filesize = 8M
post_max_size = 40M
memory_limit = 256M
expose_php = Off
opcache.validate_timestamps = 0
INI
systemctl restart php8.3-fpm

etape "3/9 Base de données PostgreSQL"
if [ ! -f "$SECRETS" ]; then
  MOT_DE_PASSE_BASE="$(openssl rand -hex 24)"
  printf 'MOT_DE_PASSE_BASE=%s\n' "$MOT_DE_PASSE_BASE" > "$SECRETS"
  chmod 600 "$SECRETS"
else
  MOT_DE_PASSE_BASE="$(grep '^MOT_DE_PASSE_BASE=' "$SECRETS" | cut -d= -f2)"
fi
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${BASE_UTILISATEUR}') THEN
    CREATE ROLE ${BASE_UTILISATEUR} LOGIN PASSWORD '${MOT_DE_PASSE_BASE}';
  ELSE
    ALTER ROLE ${BASE_UTILISATEUR} PASSWORD '${MOT_DE_PASSE_BASE}';
  END IF;
END \$\$;
SQL
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${BASE_NOM}'" | grep -q 1 \
  || sudo -u postgres createdb -O "${BASE_UTILISATEUR}" "${BASE_NOM}"

etape "4/9 Code du site"
mkdir -p "$(dirname "$DOSSIER")"
if [ -d "$DOSSIER/.git" ]; then
  git -C "$DOSSIER" fetch origin "$BRANCHE" && git -C "$DOSSIER" checkout "$BRANCHE" && git -C "$DOSSIER" reset --hard "origin/$BRANCHE"
else
  git clone --branch "$BRANCHE" "$DEPOT" "$DOSSIER"
fi
cd "$DOSSIER"

etape "5/9 Fichier .env (seulement la base et la clé de chiffrement)"
if [ ! -f .env ]; then
  cat > .env <<ENV
APP_ENV=production
APP_DEBUG=false
APP_KEY=
DB_URL=pgsql://${BASE_UTILISATEUR}:${MOT_DE_PASSE_BASE}@127.0.0.1:5432/${BASE_NOM}
ENV
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan key:generate --force
  # La clé maître est copiée dans un fichier à part : SAUVEGARDEZ-LA hors du serveur.
  grep '^APP_KEY=' .env | sed 's/^/CLE_MAITRE_/' >> "$SECRETS"
else
  composer install --no-dev --optimize-autoloader --no-interaction
fi
chown root:www-data .env && chmod 640 .env

etape "6/9 Construction du site et de la base"
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan storage:link || true
mkdir -p storage/app/private storage/app/public storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
# Les commandes ci-dessus tournent en root : on rend ensuite les dossiers d'écriture au serveur web.
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

etape "7/9 Nginx + HTTPS"
install -d /etc/nginx/snippets
install -m 644 "$DOSSIER/deploy/nginx-securite.conf" /etc/nginx/snippets/gesimo-securite.conf
sed "s#__DOMAINE__#${DOMAINE}#g; s#__DOSSIER__#${DOSSIER}#g" "$DOSSIER/deploy/nginx-gesimo.conf" > /etc/nginx/sites-available/gesimo
ln -sf /etc/nginx/sites-available/gesimo /etc/nginx/sites-enabled/gesimo
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null
if ! certbot certificates 2>/dev/null | grep -q "Domains: .*${DOMAINE}"; then
  certbot --nginx -d "$DOMAINE" --non-interactive --agree-tos -m "$EMAIL_CERTIFICAT" --redirect \
    || echo "ATTENTION : le certificat HTTPS n'a pas pu être créé. Vérifiez que ${DOMAINE} pointe bien vers ce serveur, puis relancez ce script."
fi

etape "8/9 Tâche planifiée et traitement en arrière-plan"
echo "* * * * * www-data cd ${DOSSIER} && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/gesimo
chmod 644 /etc/cron.d/gesimo
sed "s#__DOSSIER__#${DOSSIER}#g" "$DOSSIER/deploy/gesimo-queue.service" > /etc/systemd/system/gesimo-queue.service
systemctl daemon-reload
systemctl enable --now gesimo-queue.service
systemctl restart gesimo-queue.service

etape "9/9 Sauvegarde quotidienne de la base"
install -d -m 700 /var/backups/gesimo
cat > /etc/cron.daily/gesimo-sauvegarde <<'SAUV'
#!/usr/bin/env bash
set -euo pipefail
DEST=/var/backups/gesimo
sudo -u postgres pg_dump --format=custom gesimo > "$DEST/gesimo-$(date +%F).dump"
chmod 600 "$DEST"/*.dump
find "$DEST" -name 'gesimo-*.dump' -mtime +14 -delete
SAUV
chmod 755 /etc/cron.daily/gesimo-sauvegarde
/etc/cron.daily/gesimo-sauvegarde || echo "ATTENTION : la première sauvegarde a échoué."

cat <<FIN

Installation terminée.
  Site        : https://${DOMAINE}
  Secrets     : ${SECRETS}   (contient la CLÉ MAÎTRE : copiez-la dans un endroit sûr, HORS du serveur)

Prochaine étape — l'assistant d'installation :
  1) Générer votre code d'installation (affiché une seule fois) :
       cd ${DOSSIER} && sudo -u www-data php artisan gesimo:installation
  2) Ouvrir https://${DOMAINE}/installation dans votre navigateur et suivre les étapes
     (création de votre compte super-administrateur, puis réglages essentiels).
FIN
