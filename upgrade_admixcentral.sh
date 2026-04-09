#!/usr/bin/env bash
# =============================================================================
#  AdmixCentral — Production Scaling Upgrade Script
#  Applies Redis + worker/PHP-FPM scaling to an EXISTING deployment.
#  Safe to run multiple times (idempotent). Does NOT wipe or reinstall the app.
#
#  Usage:
#    sudo bash upgrade_admixcentral.sh
#    sudo INSTALL_DIR=/custom/path bash upgrade_admixcentral.sh
# =============================================================================
set -Eeuo pipefail

LOG_FILE="/root/admixcentral_upgrade.log"
exec > >(tee -a "$LOG_FILE") 2>&1
trap 'echo; echo "[X] FAILED at line $LINENO. See $LOG_FILE"; exit 1' ERR

log()  { echo -e "\n[+] $*\n"; }
die()  { echo -e "\n[X] $*\n"; exit 1; }
info() { echo "    $*"; }

[[ "${EUID}" -eq 0 ]] || die "Run as root: sudo bash $0"

# ---------------------------------------------------------------------------
# CONFIG — override via environment variables if needed
# ---------------------------------------------------------------------------
PHP_VER="${PHP_VER:-8.3}"
INSTALL_DIR="${INSTALL_DIR:-/var/www/admixcentral}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
OS_FAMILY=""
PHP_SERVICE=""

os_detect() {
  [[ -f /etc/os-release ]] || die "Cannot detect OS: /etc/os-release missing"
  . /etc/os-release
  local id="${ID:-}" id_like="${ID_LIKE:-}"
  if   [[ "$id" == "debian" || "$id" == "ubuntu" || "$id_like" == *"debian"* ]]; then
    OS_FAMILY="debian";  PHP_SERVICE="php${PHP_VER}-fpm"
  elif [[ "$id" == "fedora" || "$id" == "rhel"   || "$id" == "centos"  || "$id_like" == *"rhel"* ]]; then
    OS_FAMILY="redhat";  PHP_SERVICE="php-fpm"
  elif [[ "$id" == "arch"   || "$id_like" == *"arch"* ]]; then
    OS_FAMILY="arch";    PHP_SERVICE="php-fpm"
  elif [[ "$id" == "suse"   || "$id" == opensuse* || "$id_like" == *"suse"* ]]; then
    OS_FAMILY="suse";    PHP_SERVICE="php8-fpm"
  else
    OS_FAMILY="debian";  PHP_SERVICE="php${PHP_VER}-fpm"
    log "Warning: Unknown OS, assuming Debian-style"
  fi
  log "Detected OS family: $OS_FAMILY"
}

pkg_install() {
  case "$OS_FAMILY" in
    debian) DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@" ;;
    redhat) dnf install -y "$@" ;;
    arch)   pacman -S --noconfirm --needed "$@" ;;
    suse)   zypper install -y --no-recommends "$@" ;;
  esac
}

set_env_kv() {
  local file="$1" key="$2" val="$3"
  if grep -qE "^${key}=" "$file"; then
    sed -i "s#^${key}=.*#${key}=${val}#g" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

artisan() {
  # Run artisan as the web user who owns the install dir
  local owner
  owner="$(stat -c '%U' "${INSTALL_DIR}/artisan" 2>/dev/null || echo "www-data")"
  sudo -u "$owner" php "${INSTALL_DIR}/artisan" "$@"
}

find_supervisor_conf() {
  # Returns the directory where supervisor program configs live
  for d in /etc/supervisor/conf.d /etc/supervisord.d /etc/supervisor.d; do
    [[ -d "$d" ]] && { echo "$d"; return 0; }
  done
  die "Cannot find Supervisor config directory"
}

find_worker_conf() {
  local supv_dir="$1"
  for f in "${supv_dir}/admix-worker.conf" "${supv_dir}/admix-worker.ini"; do
    [[ -f "$f" ]] && { echo "$f"; return 0; }
  done
  die "Cannot find admix-worker config in $supv_dir"
}

find_reverb_conf() {
  local supv_dir="$1"
  for f in "${supv_dir}/admix-reverb.conf" "${supv_dir}/admix-reverb.ini"; do
    [[ -f "$f" ]] && { echo "$f"; return 0; }
  done
  # Not fatal — reverb might not be configured
  echo ""
}

find_fpm_pool_conf() {
  for f in \
    "/etc/php/${PHP_VER}/fpm/pool.d/www.conf" \
    "/etc/php-fpm.d/www.conf" \
    "/etc/php/php-fpm.d/www.conf"; do
    [[ -f "$f" ]] && { echo "$f"; return 0; }
  done
  echo ""
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
  os_detect

  [[ -d "$INSTALL_DIR" ]]       || die "AdmixCentral not found at $INSTALL_DIR"
  [[ -f "$INSTALL_DIR/.env" ]]  || die ".env not found at $INSTALL_DIR/.env"
  [[ -f "$INSTALL_DIR/artisan" ]] || die "artisan not found at $INSTALL_DIR/artisan"

  log "AdmixCentral found at $INSTALL_DIR"

  # ── 1. Install Redis + PHP extension ──────────────────────────────────────
  log "Step 1/6 — Installing Redis and PHP Redis extension"

  case "$OS_FAMILY" in
    debian)
      apt-get update -y -q
      pkg_install redis-server "php${PHP_VER}-redis"
      systemctl enable --now redis-server || true
      ;;
    redhat)
      pkg_install redis php-redis
      systemctl enable --now redis || true
      ;;
    arch)
      pkg_install redis php-redis
      systemctl enable --now redis || true
      ;;
    suse)
      pkg_install redis php8-redis
      systemctl enable --now redis || true
      ;;
  esac

  redis-cli ping | grep -q "PONG" || die "Redis is not responding. Check redis-server status."
  info "Redis: OK (PONG received)"

  # ── 2. Update .env ────────────────────────────────────────────────────────
  log "Step 2/6 — Updating .env"

  local env_file="${INSTALL_DIR}/.env"

  set_env_kv "$env_file" "CACHE_DRIVER"   "redis"
  set_env_kv "$env_file" "SESSION_DRIVER" "redis"
  set_env_kv "$env_file" "QUEUE_CONNECTION" "redis"
  set_env_kv "$env_file" "REDIS_CLIENT"   "phpredis"
  set_env_kv "$env_file" "REDIS_HOST"     "$REDIS_HOST"
  set_env_kv "$env_file" "REDIS_PORT"     "$REDIS_PORT"
  set_env_kv "$env_file" "REDIS_PASSWORD" "null"

  info "CACHE_DRIVER=$(grep '^CACHE_DRIVER=' "$env_file" | cut -d= -f2)"
  info "SESSION_DRIVER=$(grep '^SESSION_DRIVER=' "$env_file" | cut -d= -f2)"
  info "QUEUE_CONNECTION=$(grep '^QUEUE_CONNECTION=' "$env_file" | cut -d= -f2)"

  # ── 3. Clear Laravel caches ───────────────────────────────────────────────
  log "Step 3/6 — Clearing Laravel caches"

  artisan config:clear
  artisan cache:clear
  artisan route:clear
  artisan view:clear
  artisan queue:restart
  info "Caches cleared"

  # ── 4. Scale Supervisor queue workers ─────────────────────────────────────
  log "Step 4/6 — Scaling queue workers"

  local cpu_cores num_workers
  cpu_cores="$(nproc 2>/dev/null || echo 4)"
  num_workers=$(( cpu_cores * 2 ))
  [[ $num_workers -lt 8  ]] && num_workers=8
  [[ $num_workers -gt 16 ]] && num_workers=16
  info "CPU cores: ${cpu_cores} → setting numprocs=${num_workers}"

  local supv_dir worker_conf
  supv_dir="$(find_supervisor_conf)"
  worker_conf="$(find_worker_conf "$supv_dir")"

  # Switch to redis queue and update numprocs
  sed -i "s|queue:work\( --\| database --\| $\)|queue:work redis \1|" "$worker_conf" || true
  sed -i "s|queue:work redis redis|queue:work redis|g"                  "$worker_conf" || true
  sed -i "s/^numprocs=.*/numprocs=${num_workers}/"                      "$worker_conf"

  info "Worker config: $worker_conf"
  grep -E 'numprocs|command' "$worker_conf" | sed 's/^/    /'

  # ── 5. Scale Reverb WebSocket processes ───────────────────────────────────
  log "Step 5/6 — Scaling Reverb WebSocket processes"

  local reverb_conf
  reverb_conf="$(find_reverb_conf "$supv_dir")"

  if [[ -n "$reverb_conf" ]]; then
    if grep -q '^numprocs=' "$reverb_conf"; then
      sed -i "s/^numprocs=.*/numprocs=3/" "$reverb_conf"
    else
      sed -i "/^autostart=true/a numprocs=3" "$reverb_conf"
    fi
    info "Reverb config: $reverb_conf"
    grep 'numprocs' "$reverb_conf" | sed 's/^/    /'
  else
    info "No admix-reverb config found — skipping (Reverb may not be in use)"
  fi

  # ── 6. Tune PHP-FPM ───────────────────────────────────────────────────────
  log "Step 6/6 — Tuning PHP-FPM pool"

  local fpm_pool_conf
  fpm_pool_conf="$(find_fpm_pool_conf)"

  if [[ -n "$fpm_pool_conf" ]]; then
    local total_ram_mb max_children start_servers min_spare max_spare
    total_ram_mb=$(( $(awk '/MemTotal/{print $2}' /proc/meminfo) / 1024 ))
    max_children=$(( total_ram_mb / 50 ))
    [[ $max_children -lt 10 ]] && max_children=10
    [[ $max_children -gt 40 ]] && max_children=40
    start_servers=$(( max_children / 4 ))
    min_spare=$start_servers
    max_spare=$(( max_children / 2 ))

    info "RAM: ${total_ram_mb}MB → pm.max_children=${max_children}"

    sed -i "s/^pm = .*/pm = dynamic/"                                         "$fpm_pool_conf"
    sed -i "s/^pm\.max_children = .*/pm.max_children = ${max_children}/"      "$fpm_pool_conf"
    sed -i "s/^pm\.start_servers = .*/pm.start_servers = ${start_servers}/"   "$fpm_pool_conf"
    sed -i "s/^pm\.min_spare_servers = .*/pm.min_spare_servers = ${min_spare}/" "$fpm_pool_conf"
    sed -i "s/^pm\.max_spare_servers = .*/pm.max_spare_servers = ${max_spare}/" "$fpm_pool_conf"
    sed -i "s/^;*pm\.max_requests = .*/pm.max_requests = 500/"                "$fpm_pool_conf"

    systemctl restart "$PHP_SERVICE" || true
    info "PHP-FPM restarted: $PHP_SERVICE"
  else
    info "PHP-FPM pool config not found — skipping tuning"
  fi

  # ── Reload Supervisor ─────────────────────────────────────────────────────
  log "Reloading Supervisor"

  local svc="supervisor"
  systemctl list-unit-files 2>/dev/null | grep -qx 'supervisord.service' && svc="supervisord"

  supervisorctl reread
  supervisorctl update
  supervisorctl restart admix-worker:* || supervisorctl start admix-worker || true
  [[ -n "${reverb_conf:-}" ]] && { supervisorctl restart admix-reverb:* || true; }

  # ── Summary ───────────────────────────────────────────────────────────────
  echo
  echo "============================================================"
  echo " AdmixCentral Scaling Upgrade — COMPLETE"
  echo "============================================================"
  echo
  supervisorctl status || true
  echo
  info "Redis:          $(redis-cli ping)"
  info "Workers:        ${num_workers} processes"
  info "Reverb:         3 processes"
  info "PHP-FPM:        pm.max_children=${max_children:-skipped}"
  info "Queue driver:   redis"
  info "Cache driver:   redis"
  info "Session driver: redis"
  echo
  info "Log: $LOG_FILE"
  echo "============================================================"
}

main "$@"
