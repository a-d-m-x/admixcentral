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

OS_FAMILY=""
DB_SERVICE_NAME="mysql"
RUNTIME_WEB_USER=""
RUNTIME_WEB_GROUP=""
WEB_HOME=""
PHP_SERVICE=""

os_detect() {
  log "Detecting Operating System"
  if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    local id_like="${ID_LIKE:-}"
    local id="${ID:-}"

    if [[ "$id" == "debian" || "$id" == "ubuntu" || "$id_like" == *"debian"* || "$id_like" == *"ubuntu"* ]]; then
      OS_FAMILY="debian"
      DB_SERVICE_NAME="mysql"
      PHP_SERVICE="php${PHP_VER}-fpm"
    elif [[ "$id" == "fedora" || "$id" == "rhel" || "$id" == "centos" || "$id_like" == *"fedora"* || "$id_like" == *"rhel"* || "$id_like" == *"centos"* ]]; then
      OS_FAMILY="redhat"
      DB_SERVICE_NAME="mariadb"
      PHP_SERVICE="php-fpm"
      if [[ "${WEB_USER}" == "www-data" ]]; then WEB_USER="nginx"; fi
      if [[ "${WEB_GROUP}" == "www-data" ]]; then WEB_GROUP="nginx"; fi
    elif [[ "$id" == "arch" || "$id_like" == *"arch"* ]]; then
      OS_FAMILY="arch"
      DB_SERVICE_NAME="mariadb"
      PHP_SERVICE="php-fpm"
      if [[ "${WEB_USER}" == "www-data" ]]; then WEB_USER="http"; fi
      if [[ "${WEB_GROUP}" == "www-data" ]]; then WEB_GROUP="http"; fi
    elif [[ "$id" == "suse" || "$id" == opensuse* || "$id_like" == *"suse"* ]]; then
      OS_FAMILY="suse"
      DB_SERVICE_NAME="mariadb"
      PHP_SERVICE="php8-fpm"
      if [[ "${WEB_USER}" == "www-data" ]]; then WEB_USER="nginx"; fi
      if [[ "${WEB_GROUP}" == "www-data" ]]; then WEB_GROUP="nginx"; fi
    else
      log "Warning: Unknown OS $id or $id_like, defaulting to debian strategy"
      OS_FAMILY="debian"
      DB_SERVICE_NAME="mysql"
      PHP_SERVICE="php${PHP_VER}-fpm"
    fi
  else
    die "Cannot detect OS: /etc/os-release missing"
  fi
  log "Detected OS Family: $OS_FAMILY"
}

wait_for_pkg_locks() {
  if [[ "$OS_FAMILY" == "debian" ]]; then
    log "Waiting for package manager locks to clear"
    local i=0
    while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 \
       || fuser /var/lib/apt/lists/lock >/dev/null 2>&1 \
       || fuser /var/cache/apt/archives/lock >/dev/null 2>&1; do
      i=$((i+1))
      [[ $i -le 300 ]] || die "apt locks did not clear after ~10 minutes"
      sleep 2
    done
  fi
}

pkg_install() {
  wait_for_pkg_locks
  case "$OS_FAMILY" in
    debian)
      DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@"
      ;;
    redhat)
      dnf install -y "$@"
      ;;
    arch)
      pacman -S --noconfirm --needed "$@"
      ;;
    suse)
      zypper install -y --no-recommends "$@"
      ;;
  esac
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

  if [[ "$OS_FAMILY" == "debian" ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    pkg_install nodejs
  elif [[ "$OS_FAMILY" == "redhat" ]]; then
    curl -fsSL "https://rpm.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    pkg_install nodejs
  else
    pkg_install nodejs npm
  fi

  node -v || true
  npm -v || true
}

wait_for_mysql() {
  log "Waiting for MySQL/MariaDB to be ready"
  systemctl enable --now "$DB_SERVICE_NAME" || true
  local i=0
  until mysqladmin ping --silent >/dev/null 2>&1; do
    i=$((i+1))
    if [[ $i -gt 120 ]]; then
      systemctl status "$DB_SERVICE_NAME" --no-pager || true
      journalctl -u "$DB_SERVICE_NAME" -n 200 --no-pager || true
      die "MySQL/MariaDB startup failed"
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
  echo
  echo "============================================================"
  echo "DATABASE SETUP"
  echo "This script will create/update a MySQL user and grant access."
  echo "Host: ${DB_HOST}  Port: ${DB_PORT}  DB: ${DB_NAME}"
  echo "============================================================"
  echo

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
  found="$(ls -1 /var/run/php/php*-fpm.sock /run/php-fpm/www.sock /run/php-fpm/php-fpm.sock /run/php-fpm.sock /var/run/php-fpm.sock 2>/dev/null | head -n 1 || true)"
  [[ -n "$found" ]] || die "No PHP-FPM socket found in standard directories"
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

configure_arch_php() {
  if [[ "$OS_FAMILY" == "arch" ]]; then
    log "Configuring Arch Linux PHP extensions"
    local ini="/etc/php/php.ini"
    if [[ -f "$ini" ]]; then
      local exts=(pdo_mysql bcmath curl gd intl zip iconv mysqli)
      for ext in "${exts[@]}"; do
        sed -i "s/^;extension=${ext}/extension=${ext}/" "$ini" || true
        sed -i "s/^; extension=${ext}/extension=${ext}/" "$ini" || true
      done
    fi
  fi
}

detect_runtime_web_user_group() {
  local app_user=""
  local app_group=""
  local fpm_conf=""

  for f in /etc/php-fpm.d/www.conf /etc/php*/php-fpm.d/www.conf; do
    if [[ -f "$f" ]]; then
      fpm_conf="$f"
      break
    fi
  done

  if [[ -n "$fpm_conf" ]]; then
    app_user="$(awk -F= '/^[[:space:]]*user[[:space:]]*=/{gsub(/[[:space:]]+/,"",$2); print $2; exit}' "$fpm_conf" || true)"
    app_group="$(awk -F= '/^[[:space:]]*group[[:space:]]*=/{gsub(/[[:space:]]+/,"",$2); print $2; exit}' "$fpm_conf" || true)"
  fi

  if [[ -z "$app_user" ]]; then
    app_user="$(ps -eo user,comm | awk '$2=="php-fpm" && $1!="root"{print $1; exit}' || true)"
  fi

  if [[ -z "$app_group" && -n "$app_user" ]]; then
    app_group="$(id -gn "$app_user" 2>/dev/null || true)"
  fi

  if [[ -z "$app_user" ]]; then
    if id nginx >/dev/null 2>&1; then
      app_user="nginx"
    elif id apache >/dev/null 2>&1; then
      app_user="apache"
    elif id www-data >/dev/null 2>&1; then
      app_user="www-data"
    elif id http >/dev/null 2>&1; then
      app_user="http"
    else
      die "Could not determine PHP-FPM runtime user"
    fi
  fi

  if [[ -z "$app_group" ]]; then
    app_group="$(id -gn "$app_user" 2>/dev/null || true)"
  fi

  [[ -n "$app_user" ]] || die "Could not determine runtime user"
  [[ -n "$app_group" ]] || die "Could not determine runtime group"

  RUNTIME_WEB_USER="$app_user"
  RUNTIME_WEB_GROUP="$app_group"
  WEB_HOME="$(getent passwd "$RUNTIME_WEB_USER" | cut -d: -f6 || true)"

  if [[ -z "$WEB_HOME" ]]; then
    case "$RUNTIME_WEB_USER" in
      http) WEB_HOME="/srv/http" ;;
      nginx) WEB_HOME="/var/lib/nginx" ;;
      apache) WEB_HOME="/usr/share/httpd" ;;
      www-data) WEB_HOME="/var/www" ;;
      *) WEB_HOME="/tmp/${RUNTIME_WEB_USER}" ;;
    esac
  fi

  log "Detected PHP-FPM runtime user/group: ${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}"
  log "Detected runtime home: ${WEB_HOME}"
}

fix_arch_nginx_main_config() {
  [[ "$OS_FAMILY" == "arch" ]] || return 0

  log "Fixing Arch nginx main config to load conf.d and remove default welcome site"

  cat >/etc/nginx/nginx.conf <<'EOF'
#user http;
worker_processes  1;

#error_log  logs/error.log;
#error_log  logs/error.log  notice;
#error_log  logs/error.log  info;

#pid        logs/nginx.pid;

# Load all installed modules
include modules.d/*.conf;

events {
    worker_connections 1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    #log_format  main  '$remote_addr - $remote_user [$time_local] "$request" '
    #                  '$status $body_bytes_sent "$http_referer" '
    #                  '"$http_user_agent" "$http_x_forwarded_for"';

    #access_log  logs/access.log  main;

    sendfile        on;
    #tcp_nopush     on;

    keepalive_timeout 65;

    #gzip  on;

    include /etc/nginx/conf.d/*.conf;
}
EOF
}

configure_nginx_site() {
  log "Configuring Nginx"

  local nginx_sites_avail="/etc/nginx/sites-available"
  local nginx_sites_en="/etc/nginx/sites-enabled"

  if [[ "$OS_FAMILY" == "redhat" || "$OS_FAMILY" == "arch" || "$OS_FAMILY" == "suse" ]]; then
    nginx_sites_avail="/etc/nginx/conf.d"
    nginx_sites_en="/etc/nginx/conf.d"
  fi

  mkdir -p "$nginx_sites_avail"
  mkdir -p "$nginx_sites_en"

  rm -f "${nginx_sites_en}/default" || true
  rm -f "${nginx_sites_en}/default.conf" || true
  rm -f /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/example_ssl.conf 2>/dev/null || true

  if [[ "$OS_FAMILY" == "arch" ]]; then
    fix_arch_nginx_main_config
  fi

  local php_sock
  php_sock="$(detect_php_fpm_sock)"

  cat >"${nginx_sites_avail}/admixcentral.conf" <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root ${INSTALL_DIR}/public;

    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

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

  if [[ "$nginx_sites_avail" != "$nginx_sites_en" ]]; then
    ln -sf "${nginx_sites_avail}/admixcentral.conf" "${nginx_sites_en}/admixcentral.conf" || true
  fi

  nginx -t || die "Nginx config test failed"
  systemctl reload nginx || systemctl restart nginx || true
}

run_as_runtime_user() {
  local cmd="$1"
  sudo -u "${RUNTIME_WEB_USER}" -H env HOME="${WEB_HOME}" npm_config_cache="${WEB_HOME}/.npm" bash -lc "$cmd"
}

install_frontend_clean() {
  log "Fixing npm cache ownership + ensuring clean frontend install"

  mkdir -p "${WEB_HOME}/.npm"
  chown -R "${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}" "${WEB_HOME}" || true
  chown -R "${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}" "${WEB_HOME}/.npm" || true

  rm -rf "${INSTALL_DIR}/node_modules" || true
  chown -R "${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}" "${INSTALL_DIR}" || true

  log "Installing frontend deps + building (as ${RUNTIME_WEB_USER}, HOME=${WEB_HOME})"
  run_as_runtime_user "
    set -Eeuo pipefail
    cd '${INSTALL_DIR}'
    npm cache clean --force || true
    npm cache verify || true
    if [[ -f package-lock.json ]]; then
      npm ci
    else
      npm install
    fi
    npm run build
  "
}

final_fix_permissions() {
  log "Running embedded final permissions fix"

  [[ -n "${RUNTIME_WEB_USER}" ]] || detect_runtime_web_user_group
  [[ -n "${RUNTIME_WEB_GROUP}" ]] || detect_runtime_web_user_group

  mkdir -p "${INSTALL_DIR}/storage/framework/cache" \
           "${INSTALL_DIR}/storage/framework/sessions" \
           "${INSTALL_DIR}/storage/framework/views" \
           "${INSTALL_DIR}/bootstrap/cache" \
           "${INSTALL_DIR}/public/build" \
           "${INSTALL_DIR}/storage/logs"

  chown -R "${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}" \
    "${INSTALL_DIR}/storage" \
    "${INSTALL_DIR}/bootstrap/cache" \
    "${INSTALL_DIR}/public/build"

  find "${INSTALL_DIR}/storage" -type d -exec chmod 2775 {} \;
  find "${INSTALL_DIR}/storage" -type f -exec chmod 0664 {} \;

  find "${INSTALL_DIR}/bootstrap/cache" -type d -exec chmod 2775 {} \;
  find "${INSTALL_DIR}/bootstrap/cache" -type f -exec chmod 0664 {} \;

  find "${INSTALL_DIR}/public/build" -type d -exec chmod 2775 {} \; 2>/dev/null || true
  find "${INSTALL_DIR}/public/build" -type f -exec chmod 0664 {} \; 2>/dev/null || true

  if command -v getenforce >/dev/null 2>&1; then
    if [[ "$(getenforce || true)" != "Disabled" ]]; then
      log "Applying SELinux contexts"
      if ! command -v semanage >/dev/null 2>&1; then
        if [[ "$OS_FAMILY" == "redhat" ]]; then
          dnf install -y policycoreutils-python-utils || true
        fi
      fi

      if command -v semanage >/dev/null 2>&1; then
        semanage fcontext -a -t httpd_sys_rw_content_t "${INSTALL_DIR}/storage(/.*)?" 2>/dev/null || true
        semanage fcontext -a -t httpd_sys_rw_content_t "${INSTALL_DIR}/bootstrap/cache(/.*)?" 2>/dev/null || true
        semanage fcontext -a -t httpd_sys_rw_content_t "${INSTALL_DIR}/public/build(/.*)?" 2>/dev/null || true
      fi

      restorecon -Rv "${INSTALL_DIR}/storage" "${INSTALL_DIR}/bootstrap/cache" "${INSTALL_DIR}/public/build" 2>/dev/null || true
      setsebool -P httpd_unified 1 2>/dev/null || true
      setsebool -P httpd_can_network_connect 1 2>/dev/null || true
    fi
  fi

  if [[ -f "${INSTALL_DIR}/artisan" ]]; then
    run_as_runtime_user "
      cd '${INSTALL_DIR}'
      php artisan view:clear || true
      php artisan cache:clear || true
      php artisan config:clear || true
      php artisan route:clear || true
      php artisan storage:link || true
    "
  fi

  systemctl restart "${PHP_SERVICE}" || true
  systemctl restart nginx || true
}

main() {
  os_detect

  log "Updating package manager"
  if [[ "$OS_FAMILY" == "debian" ]]; then
    wait_for_pkg_locks
    apt-get update -y
  elif [[ "$OS_FAMILY" == "redhat" ]]; then
    dnf makecache
  elif [[ "$OS_FAMILY" == "arch" ]]; then
    pacman -Sy
  elif [[ "$OS_FAMILY" == "suse" ]]; then
    zypper refresh
  fi

  log "Installing base packages"
  if [[ "$OS_FAMILY" == "debian" ]]; then
    pkg_install ca-certificates curl gnupg git unzip lsb-release apt-transport-https
  elif [[ "$OS_FAMILY" == "redhat" ]]; then
    pkg_install ca-certificates curl gnupg2 git unzip
  elif [[ "$OS_FAMILY" == "arch" ]]; then
    pkg_install ca-certificates curl gnupg git unzip lsb-release wget
  elif [[ "$OS_FAMILY" == "suse" ]]; then
    pkg_install ca-certificates curl gnupg2 git unzip lsb-release
  fi

  log "Installing Nginx + Database Server + Supervisor"
  if [[ "$OS_FAMILY" == "debian" ]]; then
    pkg_install nginx mysql-server supervisor certbot python3-certbot-nginx
  elif [[ "$OS_FAMILY" == "redhat" ]]; then
    pkg_install nginx mariadb-server supervisor certbot python3-certbot-nginx
  elif [[ "$OS_FAMILY" == "arch" ]]; then
    pkg_install nginx mariadb supervisor certbot certbot-nginx
    if [[ ! -d "/var/lib/mysql/mysql" ]]; then
      mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql || true
    fi
  elif [[ "$OS_FAMILY" == "suse" ]]; then
    pkg_install nginx mariadb supervisor certbot python3-certbot-nginx
  fi

  log "Installing PHP + extensions"
  if [[ "$OS_FAMILY" == "debian" ]]; then
    pkg_install \
      php${PHP_VER}-cli php${PHP_VER}-fpm \
      php${PHP_VER}-mysql php${PHP_VER}-mbstring php${PHP_VER}-xml php${PHP_VER}-curl \
      php${PHP_VER}-zip php${PHP_VER}-gd php${PHP_VER}-bcmath php${PHP_VER}-intl
  elif [[ "$OS_FAMILY" == "redhat" ]]; then
    pkg_install php-cli php-fpm php-mysqlnd php-mbstring php-xml php-curl php-zip php-gd php-bcmath php-intl || true
  elif [[ "$OS_FAMILY" == "arch" ]]; then
    pkg_install php php-fpm php-gd php-intl php-sqlite
    configure_arch_php
  elif [[ "$OS_FAMILY" == "suse" ]]; then
    pkg_install php8-cli php8-fpm php8-mysql php8-mbstring php8-curl php8-zip php8-gd php8-bcmath php8-intl || true
  fi

  install_composer
  install_node

  log "Enabling services"
  systemctl enable --now nginx || true
  systemctl enable --now "${PHP_SERVICE}" || true

  detect_runtime_web_user_group

  wait_for_mysql
  create_database

  prompt_db_creds_once
  setup_mysql_app_user
  db_preflight_test

  log "Cloning repo to ${INSTALL_DIR}"
  rm -rf "$INSTALL_DIR"
  git clone "$REPO_URL" "$INSTALL_DIR"
  chown -R "${WEB_USER}:${WEB_GROUP}" "$INSTALL_DIR" || log "Warning: initial chown failed, ignoring..."

  cd "$INSTALL_DIR"
  [[ -f artisan && -d public ]] || die "Invalid repo layout (expected artisan + public/)"

  log "Ensuring .env exists"
  if [[ ! -f .env ]]; then
    cp .env.example .env
    chown "${WEB_USER}:${WEB_GROUP}" .env || true
  fi

  log "Writing DB settings into .env"
  set_env_kv .env "DB_HOST" "$DB_HOST"
  set_env_kv .env "DB_PORT" "$DB_PORT"
  set_env_kv .env "DB_DATABASE" "$DB_NAME"
  set_env_kv .env "DB_USERNAME" "$DB_USER"
  set_env_kv .env "DB_PASSWORD" "$DB_PASS"

  log "Writing Reverb settings into .env"
  set_env_kv .env "REVERB_APP_ID" "admixcentral"
  set_env_kv .env "REVERB_APP_KEY" "admixcentral-key"
  set_env_kv .env "REVERB_APP_SECRET" "admixcentral-secret"
  set_env_kv .env "REVERB_HOST" "localhost"
  set_env_kv .env "REVERB_PORT" "8080"
  set_env_kv .env "REVERB_SCHEME" "http"

  set_env_kv .env "VITE_REVERB_APP_KEY" "admixcentral-key"
  set_env_kv .env "VITE_REVERB_HOST" "localhost"
  set_env_kv .env "VITE_REVERB_PORT" "8080"
  set_env_kv .env "VITE_REVERB_SCHEME" "http"

  log "Ensuring correct ownership"
  chown -R "${WEB_USER}:${WEB_GROUP}" "$INSTALL_DIR" || true

  log "Composer install"
  sudo -u "${WEB_USER}" -H env HOME="${WEB_HOME}" bash -lc "
    cd '${INSTALL_DIR}'
    COMPOSER_NO_INTERACTION=1 composer install --no-dev --prefer-dist --no-progress
  "

  log "Running AdmixCentral install wizard (interactive): php artisan install"
  sudo -u "${WEB_USER}" -H env HOME="${WEB_HOME}" bash -lc "
    cd '${INSTALL_DIR}'
    php artisan install
  "

  install_frontend_clean

  log "Laravel post-setup"
  run_as_runtime_user "
    cd '${INSTALL_DIR}'
    php artisan storage:link || true
    php artisan config:clear || true
    php artisan cache:clear || true
  "

  log "Configuring sudoers for SSL management (Certbot)"
  cat >/etc/sudoers.d/admixcentral <<EOF
${RUNTIME_WEB_USER} ALL=(ALL) NOPASSWD: /usr/bin/certbot, /usr/sbin/nginx, /usr/bin/systemctl reload nginx, /usr/bin/tee /etc/nginx/sites-available/admixcentral, /usr/bin/tee /etc/nginx/conf.d/admixcentral.conf
EOF
  chmod 0440 /etc/sudoers.d/admixcentral || true

  configure_nginx_site

  log "Configuring Supervisor (Worker + Reverb)"
  local supv_conf_dir="/etc/supervisor/conf.d"
  if [[ "$OS_FAMILY" == "redhat" || "$OS_FAMILY" == "suse" ]]; then
    supv_conf_dir="/etc/supervisord.d"
  elif [[ "$OS_FAMILY" == "arch" ]]; then
    supv_conf_dir="/etc/supervisor.d"
  fi
  mkdir -p "$supv_conf_dir"

  cat >"${supv_conf_dir}/admix-worker.ini" <<EOF
[program:admix-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${INSTALL_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=${RUNTIME_WEB_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/worker.log
stopwaitsecs=3600
EOF

  cat >"${supv_conf_dir}/admix-reverb.ini" <<EOF
[program:admix-reverb]
command=php ${INSTALL_DIR}/artisan reverb:start
autostart=true
autorestart=true
user=${RUNTIME_WEB_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/reverb.log
EOF

  mkdir -p "${INSTALL_DIR}/storage/logs"
  chown -R "${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}" "${INSTALL_DIR}/storage" || true
  chmod -R ug+rwX "${INSTALL_DIR}/storage" || true

  log "Starting Supervisor services..."
  if systemctl is-active supervisor >/dev/null 2>&1 || systemctl is-enabled supervisor >/dev/null 2>&1; then
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl start all || true
  elif systemctl is-active supervisord >/dev/null 2>&1 || systemctl is-enabled supervisord >/dev/null 2>&1; then
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl start all || true
  fi

  unset DB_PASS

  final_fix_permissions

  log "INSTALL COMPLETE"
  echo
  echo "============================================================"
  echo "AdmixCentral installed at: ${INSTALL_DIR}"
  echo "Browse: http://<server-ip>/"
  echo "Log: ${LOG_FILE}"
  echo "Detected runtime user/group: ${RUNTIME_WEB_USER}:${RUNTIME_WEB_GROUP}"
  echo "Detected runtime home: ${WEB_HOME}"
  echo "============================================================"
}

main "$@"
