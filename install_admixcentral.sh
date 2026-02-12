#!/usr/bin/env bash
set -Eeuo pipefail

LOG_FILE="/root/admixcentral_install_allinone.log"
exec > >(tee -a "$LOG_FILE") 2>&1
trap 'echo; echo "[X] FAILED at line $LINENO. See '"$LOG_FILE"'"; exit 1' ERR

log(){ echo -e "\n[+] $*\n"; }
die(){ echo -e "\n[X] $*\n"; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "Run as root: sudo bash $0"

# ---------------- CONFIG (override via env) ----------------
PHP_VER="${PHP_VER:-8.3}"
NODE_MAJOR="${NODE_MAJOR:-20}"

REPO_URL="${REPO_URL:-https://github.com/a-d-m-x/admixcentral.git}"
INSTALL_DIR="${INSTALL_DIR:-/var/www/admixcentral}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

DB_NAME="${DB_NAME:-admixcentral}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# If you export these, script will not prompt:
DB_USER="${DB_USER:-admixcentral}"
DB_PASS="${DB_PASS:-}"
# -----------------------------------------------------------

wait_for_apt_locks() {
  log "Waiting for apt/dpkg locks to clear (unattended-upgrades, etc.)"
  local i=0
  while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 \
     || fuser /var/lib/apt/lists/lock >/dev/null 2>&1 \
     || fuser /var/cache/apt/archives/lock >/dev/null 2>&1; do
    i=$((i+1))
    [[ $i -le 300 ]] || die "apt locks did not clear after ~10 minutes"
    sleep 2
  done
}

apt_install() {
  wait_for_apt_locks
  DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@"
}

install_composer() {
  log "Installing Composer"
  if command -v composer >/dev/null 2>&1; then
    composer --version || true
    return 0
  fi
  php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');"
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
  composer --version
}

install_node() {
  log "Installing Node.js ${NODE_MAJOR}.x"
  if command -v node >/dev/null 2>&1 && node -v | grep -qE "^v${NODE_MAJOR}\."; then
    node -v
    npm -v || true
    return 0
  fi
  curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
  apt_install nodejs
  node -v
  npm -v || true
}

wait_for_mysql() {
  log "Waiting for MySQL to be ready"
  systemctl enable --now mysql
  local i=0
  until mysqladmin ping --silent >/dev/null 2>&1; do
    i=$((i+1))
    if [[ $i -gt 120 ]]; then
      systemctl status mysql --no-pager || true
      journalctl -u mysql -n 200 --no-pager || true
      die "MySQL startup failed"
    fi
    sleep 2
  done
}

create_database() {
  log "Creating MySQL database: ${DB_NAME}"
  sudo mysql --protocol=socket -e \
    "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

prompt_db_creds_once() {
  # DB_USER can be overridden by env; only prompt if DB_PASS is empty
  echo
  echo "============================================================"
  echo "DATABASE SETUP"
  echo "This script will create/update a MySQL user and grant access."
  echo "Host: ${DB_HOST}  Port: ${DB_PORT}  DB: ${DB_NAME}"
  echo "============================================================"
  echo

  # Allow changing DB_USER interactively unless already set via env explicitly
  # (If you want it fully non-interactive, export DB_USER and DB_PASS.)
  read -rp "DB Username [${DB_USER}]: " _u || true
  DB_USER="${_u:-$DB_USER}"
  [[ -n "$DB_USER" ]] || die "DB Username cannot be empty"

  if [[ -n "${DB_PASS}" ]]; then
    echo "DB password provided via environment. Skipping password prompt."
    return 0
  fi

  local p1 p2
  while true; do
    read -r -s -p "Enter password to set for ${DB_USER}@localhost: " p1
    echo
    [[ -n "${p1}" ]] || { echo "Password cannot be empty. Try again."; continue; }
    read -r -s -p "Confirm password: " p2
    echo
    [[ "${p1}" == "${p2}" ]] || { echo "Passwords do not match. Try again."; p1=""; p2=""; continue; }
    DB_PASS="${p1}"
    unset p1 p2
    break
  done
}

setup_mysql_app_user() {
  log "Creating/updating MySQL user '${DB_USER}'@'localhost' + grants on '${DB_NAME}'"

  # Use socket auth as root (Ubuntu default). No MySQL root password needed.
  sudo mysql --protocol=socket <<SQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

db_preflight_test() {
  log "Testing MySQL credentials (TCP preflight)"
  MYSQL_PWD="$DB_PASS" mysql --protocol=tcp -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -e "SELECT 1;" "$DB_NAME" >/dev/null \
    || die "MySQL preflight failed. Wrong creds OR user lacks access to DB '$DB_NAME'."
}

detect_php_fpm_sock() {
  local preferred="/var/run/php/php${PHP_VER}-fpm.sock"
  [[ -S "$preferred" ]] && { echo "$preferred"; return 0; }
  local found
  found="$(ls -1 /var/run/php/php*-fpm.sock 2>/dev/null | head -n 1 || true)"
  [[ -n "$found" ]] || die "No PHP-FPM socket found in /var/run/php"
  echo "$found"
}

set_env_kv() {
  local file="$1" key="$2" val="$3"
  if grep -qE "^${key}=" "$file"; then
    sed -i "s#^${key}=.*#${key}=${val}#g" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

main() {
  log "Updating apt"
  wait_for_apt_locks
  apt-get update -y

  log "Installing base packages"
  apt_install ca-certificates curl gnupg git unzip lsb-release apt-transport-https

  log "Installing Nginx + MySQL + Supervisor"
  apt_install nginx mysql-server supervisor

  log "Installing PHP ${PHP_VER} + extensions"
  apt_install \
    php${PHP_VER}-cli php${PHP_VER}-fpm \
    php${PHP_VER}-mysql php${PHP_VER}-mbstring php${PHP_VER}-xml php${PHP_VER}-curl \
    php${PHP_VER}-zip php${PHP_VER}-gd php${PHP_VER}-bcmath php${PHP_VER}-intl

  install_composer
  install_node

  log "Enabling services"
  systemctl enable --now nginx
  systemctl enable --now php${PHP_VER}-fpm

  wait_for_mysql
  create_database

  prompt_db_creds_once
  setup_mysql_app_user
  db_preflight_test

  log "Cloning repo to ${INSTALL_DIR}"
  rm -rf "$INSTALL_DIR"
  git clone "$REPO_URL" "$INSTALL_DIR"
  chown -R "${WEB_USER}:${WEB_GROUP}" "$INSTALL_DIR"

  cd "$INSTALL_DIR"
  [[ -f artisan && -d public ]] || die "Invalid repo layout (expected artisan + public/)"

  log "Ensuring .env exists"
  if [[ ! -f .env ]]; then
    cp .env.example .env
    chown "${WEB_USER}:${WEB_GROUP}" .env
  fi

  log "Writing DB settings into .env"
  set_env_kv .env "DB_HOST" "$DB_HOST"
  set_env_kv .env "DB_PORT" "$DB_PORT"
  set_env_kv .env "DB_DATABASE" "$DB_NAME"
  set_env_kv .env "DB_USERNAME" "$DB_USER"
  set_env_kv .env "DB_PASSWORD" "$DB_PASS"

  log "Writing Reverb settings into .env"
  # Defaults for Reverb (can be overridden by user in .env later)
  set_env_kv .env "REVERB_APP_ID" "admixcentral"
  set_env_kv .env "REVERB_APP_KEY" "admixcentral-key"
  set_env_kv .env "REVERB_APP_SECRET" "admixcentral-secret"
  set_env_kv .env "REVERB_HOST" "localhost"
  set_env_kv .env "REVERB_PORT" "8080"
  set_env_kv .env "REVERB_SCHEME" "http"

  # Explicitly set VITE_ vars to match (essential for frontend build)
  set_env_kv .env "VITE_REVERB_APP_KEY" "admixcentral-key"
  set_env_kv .env "VITE_REVERB_HOST" "localhost"
  set_env_kv .env "VITE_REVERB_PORT" "8080"
  set_env_kv .env "VITE_REVERB_SCHEME" "http"

  log "Ensuring correct ownership"
  chown -R "${WEB_USER}:${WEB_GROUP}" "$INSTALL_DIR"

  log "Composer install"
  sudo -u "${WEB_USER}" -H bash -lc '
    cd "'"$INSTALL_DIR"'"
    COMPOSER_NO_INTERACTION=1 composer install --no-dev --prefer-dist --no-progress
  '

  log "Running AdmixCentral install wizard (interactive): php artisan install"
  sudo -u "${WEB_USER}" -H bash -lc '
    cd "'"$INSTALL_DIR"'"
    php artisan install
  '

  log "Fixing npm cache ownership + ensuring clean frontend install"
  mkdir -p /var/www/.npm
  chown -R "${WEB_USER}:${WEB_GROUP}" /var/www/.npm
  rm -rf /root/.npm || true
  rm -rf "${INSTALL_DIR}/node_modules" || true

  log "Installing frontend deps + building (as ${WEB_USER})"
  sudo -u "${WEB_USER}" -H bash -lc '
    set -e
    cd "'"$INSTALL_DIR"'"
    npm config set cache "$HOME/.npm" --global >/dev/null 2>&1 || true
    npm cache clean --force || true
    npm cache verify || true

    if [[ -f package-lock.json ]]; then
      npm ci
    else
      npm install
    fi

    npm run build
  '

  log "Laravel post-setup"
  sudo -u "${WEB_USER}" -H bash -lc '
    cd "'"$INSTALL_DIR"'"
    php artisan storage:link || true
    php artisan config:clear || true
    php artisan cache:clear || true
  '

  log "Configuring Nginx (IP-safe server_name _)"
  rm -f /etc/nginx/sites-enabled/default || true
  php_sock="$(detect_php_fpm_sock)"

  cat >/etc/nginx/sites-available/admixcentral <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root ${INSTALL_DIR}/public;

    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

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

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:${php_sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF

  ln -sf /etc/nginx/sites-available/admixcentral /etc/nginx/sites-enabled/admixcentral
  nginx -t
  systemctl reload nginx

  log "Configuring Supervisor (Worker + Reverb)"
  
  # Queue Worker
  cat >/etc/supervisor/conf.d/admix-worker.conf <<EOF
[program:admix-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${INSTALL_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=${WEB_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/worker.log
stopwaitsecs=3600
EOF

  # Reverb Server
  cat >/etc/supervisor/conf.d/admix-reverb.conf <<EOF
[program:admix-reverb]
command=php ${INSTALL_DIR}/artisan reverb:start
autostart=true
autorestart=true
user=${WEB_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/reverb.log
EOF

  # Ensure logs directory exists
  mkdir -p "${INSTALL_DIR}/storage/logs"
  chown -R "${WEB_USER}:${WEB_GROUP}" "${INSTALL_DIR}/storage"
  chmod -R ug+rwX "${INSTALL_DIR}/storage"

  log "Starting Supervisor services..."
  supervisorctl reread
  supervisorctl update
  supervisorctl start all || true

  # Reduce exposure: don't keep password in shell env longer than needed
  unset DB_PASS

  log "INSTALL COMPLETE"
  echo
  echo "============================================================"
  echo "AdmixCentral installed at: ${INSTALL_DIR}"
  echo "Browse: http://<server-ip>/"
  echo "Log: ${LOG_FILE}"
  echo "============================================================"
}

main "$@"
