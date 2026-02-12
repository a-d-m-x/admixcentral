#!/usr/bin/env bash
set -Eeuo pipefail

LOG_FILE="/root/admixcentral_install_part2.log"
exec > >(tee -a "$LOG_FILE") 2>&1
trap 'echo; echo "[X] FAILED at line $LINENO. See '"$LOG_FILE"'"; exit 1' ERR

log(){ echo -e "\n[+] $*\n"; }
die(){ echo -e "\n[X] $*\n"; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "Run as root: sudo bash $0"

# ---------------- CONFIG (override via env) ----------------
REPO_URL="${REPO_URL:-https://github.com/a-d-m-x/admixcentral.git}"
INSTALL_DIR="${INSTALL_DIR:-/var/www/admixcentral}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

PHP_VER="${PHP_VER:-8.3}"

DB_NAME_DEFAULT="${DB_NAME:-admixcentral}"
DB_HOST_DEFAULT="${DB_HOST_DEFAULT:-127.0.0.1}"
DB_PORT_DEFAULT="${DB_PORT_DEFAULT:-3306}"
# -----------------------------------------------------------

set_env_kv() {
  local file="$1" key="$2" val="$3"
  if grep -qE "^${key}=" "$file"; then
    sed -i "s#^${key}=.*#${key}=${val}#g" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

require_commands() {
  for c in git composer node npm nginx php mysql; do
    command -v "$c" >/dev/null 2>&1 || die "Missing required command: $c (run Part 1 first)"
  done
}

detect_php_fpm_sock() {
  local preferred="/var/run/php/php${PHP_VER}-fpm.sock"
  [[ -S "$preferred" ]] && { echo "$preferred"; return 0; }
  local found
  found="$(ls -1 /var/run/php/php*-fpm.sock 2>/dev/null | head -n 1 || true)"
  [[ -n "$found" ]] || die "No PHP-FPM socket found in /var/run/php"
  echo "$found"
}

prompt_db_creds() {
  echo
  echo "============================================================"
  echo "DATABASE CREDENTIALS (required)"
  echo "Enter the MySQL user/password you created after Part 1."
  echo "DB Username is just the username (e.g. admixcentral), NOT user@host."
  echo "============================================================"
  echo

  read -rp "DB Host [${DB_HOST_DEFAULT}]: " DB_HOST
  DB_HOST="${DB_HOST:-$DB_HOST_DEFAULT}"

  read -rp "DB Port [${DB_PORT_DEFAULT}]: " DB_PORT
  DB_PORT="${DB_PORT:-$DB_PORT_DEFAULT}"

  read -rp "DB Name [${DB_NAME_DEFAULT}]: " DB_NAME
  DB_NAME="${DB_NAME:-$DB_NAME_DEFAULT}"

  read -rp "DB Username: " DB_USER
  [[ -n "$DB_USER" ]] || die "DB Username cannot be empty"

  read -srp "DB Password: " DB_PASS
  echo
  [[ -n "$DB_PASS" ]] || die "DB Password cannot be empty"
}

db_preflight_test() {
  log "Testing MySQL credentials (preflight)"
  MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -e "SELECT 1;" "$DB_NAME" >/dev/null \
    || die "MySQL preflight failed. Wrong creds OR user lacks access to DB '$DB_NAME'."
}

main() {
  require_commands

  prompt_db_creds
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

  log "Writing DB settings into .env (repo defaults to MySQL; we set real creds)"
  set_env_kv .env "DB_HOST" "$DB_HOST"
  set_env_kv .env "DB_PORT" "$DB_PORT"
  set_env_kv .env "DB_DATABASE" "$DB_NAME"
  set_env_kv .env "DB_USERNAME" "$DB_USER"
  set_env_kv .env "DB_PASSWORD" "$DB_PASS"

  log "Ensuring correct ownership"
  chown -R "${WEB_USER}:${WEB_GROUP}" "$INSTALL_DIR"

  log "Composer install (normal; repo is now install-safe)"
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

  log "PART 2 COMPLETE"
  echo
  echo "============================================================"
  echo "AdmixCentral installed at: ${INSTALL_DIR}"
  echo "Browse: http://<server-ip>/"
  echo "Part 2 log: ${LOG_FILE}"
  echo "============================================================"
}

main "$@"
