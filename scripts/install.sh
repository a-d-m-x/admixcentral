#!/usr/bin/env bash
set -euo pipefail

### CONFIG ###
REPO_URL="https://github.com/geekasso/admixcentral.git"
APP_DIR="/var/www/admixcentral"
APP_USER="www-data"
# Detect PHP version or hardcode if preferred
PHP_VERSION="8.3"
PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
NGINX_SITE_NAME="admixcentral"

# MySQL
MYSQL_DB="admixcentral"
MYSQL_USER="admixcentral"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3306"

# Where to store generated DB creds (root-readable only)
CREDS_FILE="/root/admixcentral-mysql.txt"
################

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
  echo "Run this script as root (use sudo)." >&2
  exit 1
fi

log() { echo -e "\n==> $*"; }

set_env () {
  local key="$1"
  local val="$2"
  local file="${3:-.env}"
  if grep -qE "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

log "[1/13] Stop/disable Apache if present..."
systemctl stop apache2 >/dev/null 2>&1 || true
systemctl disable apache2 >/dev/null 2>&1 || true

log "[2/13] Apt update + base packages..."
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y \
  php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-common php${PHP_VERSION}-fpm \
  php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-zip \
  php${PHP_VERSION}-mysql php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl php${PHP_VERSION}-gd \
  unzip git curl ca-certificates \
  build-essential software-properties-common \
  nginx supervisor \
  mysql-server \
  openssl \
  certbot python3-certbot-nginx

log "[3/13] Composer..."
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

log "[4/13] Node.js 20.x..."
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt install -y nodejs
fi

require_cmd git
require_cmd php
require_cmd composer
require_cmd npm
require_cmd mysql
require_cmd openssl

log "[5/13] Ensure MySQL is running + create DB/user..."
systemctl enable --now mysql
systemctl start mysql || true

# Generate password and store it for you (root-only)
MYSQL_PASS="$(openssl rand -base64 32 | tr -d '\n' | tr -d '/+=' | cut -c1-28)"

cat >"$CREDS_FILE" <<EOF
DB_CONNECTION=mysql
DB_HOST=$MYSQL_HOST
DB_PORT=$MYSQL_PORT
DB_DATABASE=$MYSQL_DB
DB_USERNAME=$MYSQL_USER
DB_PASSWORD=$MYSQL_PASS
EOF
chmod 600 "$CREDS_FILE"

# Create DB + user for both localhost + 127.0.0.1 (avoids host-mismatch headaches)
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$MYSQL_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '$MYSQL_USER'@'localhost' IDENTIFIED BY '$MYSQL_PASS';
ALTER USER '$MYSQL_USER'@'localhost' IDENTIFIED BY '$MYSQL_PASS';

CREATE USER IF NOT EXISTS '$MYSQL_USER'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PASS';
ALTER USER '$MYSQL_USER'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PASS';

GRANT ALL PRIVILEGES ON \`$MYSQL_DB\`.* TO '$MYSQL_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$MYSQL_DB\`.* TO '$MYSQL_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

log "[6/13] Prepare /var/www and download code from repo..."
mkdir -p /var/www
chown "$APP_USER":"$APP_USER" /var/www

if [ -d "$APP_DIR/.git" ]; then
  log "Repo already exists in $APP_DIR, pulling latest..."
  cd "$APP_DIR"
  sudo -u "$APP_USER" git fetch --all --prune
  sudo -u "$APP_USER" git pull --ff-only
else
  log "Cloning repo into $APP_DIR..."
  sudo -u "$APP_USER" git clone "$REPO_URL" "$APP_DIR"
  cd "$APP_DIR"
fi

log "[7/13] Create/clean .env (force MySQL, remove sqlite leftovers)..."
cd "$APP_DIR"

if [ ! -f .env ]; then
  sudo -u "$APP_USER" cp .env.example .env
fi

# Remove any duplicate/legacy sqlite lines to avoid dotenv “last one wins” problems
sed -i '/^DB_DATABASE=database\/database\.sqlite$/d' .env
sed -i '/^DB_CONNECTION=sqlite$/d' .env

# Set app name
set_env "APP_NAME" "\"AdmixCentral\"" ".env"
set_env "APP_URL" "http://\$(curl -s ifconfig.me)" ".env"
set_env "APP_ENV" "production" ".env"
set_env "APP_DEBUG" "false" ".env"

# Set DB vars (MySQL)
set_env "DB_CONNECTION" "mysql" ".env"
set_env "DB_HOST" "$MYSQL_HOST" ".env"
set_env "DB_PORT" "$MYSQL_PORT" ".env"
set_env "DB_DATABASE" "$MYSQL_DB" ".env"
set_env "DB_USERNAME" "$MYSQL_USER" ".env"
set_env "DB_PASSWORD" "$MYSQL_PASS" ".env"

# Reverb Configuration (WebSocket)
set_env "BROADCAST_CONNECTION" "reverb" ".env"
set_env "REVERB_APP_ID" "admixcentral" ".env"
set_env "REVERB_APP_KEY" "$(openssl rand -hex 16)" ".env"
set_env "REVERB_APP_SECRET" "$(openssl rand -hex 16)" ".env"
set_env "REVERB_HOST" "localhost" ".env"
set_env "REVERB_PORT" "8080" ".env"
set_env "REVERB_SCHEME" "http" ".env"

set_env "VITE_REVERB_APP_KEY" "\${REVERB_APP_KEY}" ".env"
set_env "VITE_REVERB_HOST" "\${REVERB_HOST}" ".env"
set_env "VITE_REVERB_PORT" "\${REVERB_PORT}" ".env"
set_env "VITE_REVERB_SCHEME" "\${REVERB_SCHEME}" ".env"


# Make troubleshooting less painful: keep cache/sessions off DB unless you explicitly want them there
set_env "CACHE_DRIVER" "file" ".env"
set_env "SESSION_DRIVER" "file" ".env"
set_env "QUEUE_CONNECTION" "database" ".env"

log "[8/13] Laravel permissions (before artisan) ..."
# Ensure Laravel can write logs/cache
chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
mkdir -p "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod +x "$APP_DIR/artisan" || true

log "[9/13] Composer install + key + migrate/seed..."
sudo -u "$APP_USER" composer install --no-interaction --prefer-dist --optimize-autoloader
sudo -u "$APP_USER" php artisan key:generate --force
sudo -u "$APP_USER" php artisan storage:link
sudo -u "$APP_USER" php artisan optimize:clear
sudo -u "$APP_USER" php artisan migrate --force --seed

log "[10/13] npm install + build as www-data..."
# Avoid root-owned caches breaking npm
rm -rf /var/www/.npm || true
chown -R "$APP_USER":"$APP_USER" "$APP_DIR"

sudo -u "$APP_USER" env HOME="$APP_DIR" npm cache clean --force
sudo -u "$APP_USER" env HOME="$APP_DIR" npm install
sudo -u "$APP_USER" env HOME="$APP_DIR" npm run build

log "[10b/13] Configure sudoers for SSL management..."
cat >/etc/sudoers.d/admixcentral <<EOF
$APP_USER ALL=(ALL) NOPASSWD: /usr/bin/certbot, /usr/sbin/nginx, /usr/bin/systemctl reload nginx, /usr/bin/tee /etc/nginx/sites-available/$NGINX_SITE_NAME
EOF
chmod 0440 /etc/sudoers.d/admixcentral

log "[11/13] Nginx site config..."
# Note: Reverb runs on 8080 by default. Nginx puts it behind /app location.
cat >/etc/nginx/sites-available/"$NGINX_SITE_NAME" <<EOF
server {
    listen 80;
    server_name _;

    root $APP_DIR/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Laravel Reverb (WebSockets)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
    
    location /apps {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/"$NGINX_SITE_NAME" /etc/nginx/sites-enabled/"$NGINX_SITE_NAME"
rm -f /etc/nginx/sites-enabled/default || true

log "[12/13] Supervisor worker + restart services..."
# Queue Worker
cat >/etc/supervisor/conf.d/admix-worker.conf <<EOF
[program:admix-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=$APP_USER
numprocs=2
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

# Reverb Server
cat >/etc/supervisor/conf.d/admix-reverb.conf <<EOF
[program:admix-reverb]
command=php $APP_DIR/artisan reverb:start
autostart=true
autorestart=true
user=$APP_USER
numprocs=1
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/reverb.log
EOF

mkdir -p "$APP_DIR/storage/logs"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage"
chmod -R ug+rwX "$APP_DIR/storage"

supervisorctl reread
supervisorctl update
supervisorctl start all || true

nginx -t
systemctl enable --now php${PHP_VERSION}-fpm nginx supervisor mysql
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx

log "[13/13] Cleaning up..."
apt-get autoremove -y

echo
echo "============================================================"
echo " AdmixCentral One-Click Install Complete."
echo " Directory : $APP_DIR"
echo " DB        : MySQL ($MYSQL_DB)"
echo " Reverb    : Running on 8080 (Proxied via Nginx)"
echo " URL       : http://<server-ip>/"
echo " Credentials saved to: $CREDS_FILE (chmod 600)"
echo "============================================================"


