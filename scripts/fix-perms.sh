#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${INSTALL_DIR:-/var/www/admixcentral}"

die() {
  echo "[X] $*"
  exit 1
}

log() {
  echo "[+] $*"
}

[[ -d "$PROJECT_DIR" ]] || die "Project directory not found: $PROJECT_DIR"

detect_php_fpm_conf() {
  local conf=""

  for f in /etc/php-fpm.d/www.conf /etc/php*/php-fpm.d/www.conf; do
    if [[ -f "$f" ]]; then
      conf="$f"
      break
    fi
  done

  echo "$conf"
}

detect_runtime_user_group() {
  local fpm_conf
  local app_user=""
  local app_group=""

  fpm_conf="$(detect_php_fpm_conf)"

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
      die "Could not determine application user"
    fi
  fi

  if [[ -z "$app_group" ]]; then
    app_group="$(id -gn "$app_user" 2>/dev/null || true)"
  fi

  [[ -n "$app_user" ]] || die "Could not determine application user"
  [[ -n "$app_group" ]] || die "Could not determine application group"

  echo "$app_user:$app_group"
}

fix_selinux() {
  if command -v getenforce >/dev/null 2>&1; then
    local mode
    mode="$(getenforce || true)"

    if [[ "$mode" != "Disabled" ]]; then
      log "SELinux detected: $mode"

      if command -v semanage >/dev/null 2>&1; then
        semanage fcontext -a -t httpd_sys_rw_content_t "${PROJECT_DIR}/storage(/.*)?" 2>/dev/null || true
        semanage fcontext -a -t httpd_sys_rw_content_t "${PROJECT_DIR}/bootstrap/cache(/.*)?" 2>/dev/null || true
        semanage fcontext -a -t httpd_sys_rw_content_t "${PROJECT_DIR}/public/build(/.*)?" 2>/dev/null || true
      fi

      restorecon -Rv \
        "${PROJECT_DIR}/storage" \
        "${PROJECT_DIR}/bootstrap/cache" \
        "${PROJECT_DIR}/public/build" 2>/dev/null || true

      setsebool -P httpd_unified 1 2>/dev/null || true
      setsebool -P httpd_can_network_connect 1 2>/dev/null || true
    fi
  fi
}

restart_services() {
  if systemctl list-unit-files | grep -q '^php-fpm\.service'; then
    systemctl restart php-fpm || true
  fi

  if systemctl list-unit-files | grep -q '^nginx\.service'; then
    systemctl restart nginx || true
  fi
}

clear_laravel_cache() {
  local app_user="$1"

  if [[ -f "${PROJECT_DIR}/artisan" ]]; then
    log "Clearing Laravel caches as $app_user"
    sudo -u "$app_user" php "${PROJECT_DIR}/artisan" view:clear || true
    sudo -u "$app_user" php "${PROJECT_DIR}/artisan" cache:clear || true
    sudo -u "$app_user" php "${PROJECT_DIR}/artisan" config:clear || true
    sudo -u "$app_user" php "${PROJECT_DIR}/artisan" route:clear || true
  fi
}

main() {
  local ug
  local app_user
  local app_group

  ug="$(detect_runtime_user_group)"
  app_user="${ug%%:*}"
  app_group="${ug##*:}"

  log "Project directory: $PROJECT_DIR"
  log "Detected app runtime user: $app_user"
  log "Detected app runtime group: $app_group"

  cd "$PROJECT_DIR"

  log "Ensuring Laravel writable directories exist"
  mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache public/build

  log "Setting ownership"
  chown -R "$app_user:$app_group" storage bootstrap/cache public/build

  log "Setting directory permissions"
  find storage -type d -exec chmod 2775 {} \;
  find bootstrap/cache -type d -exec chmod 2775 {} \;
  find public/build -type d -exec chmod 2775 {} \;

  log "Setting file permissions"
  find storage -type f -exec chmod 0664 {} \;
  find bootstrap/cache -type f -exec chmod 0664 {} \;
  find public/build -type f -exec chmod 0664 {} \;

  fix_selinux
  clear_laravel_cache "$app_user"
  restart_services

  log "Done"
  log "Verify with:"
  echo "  curl http://localhost"
  echo "  grep -E '^(user|group)\\s*=' /etc/php-fpm.d/www.conf 2>/dev/null || true"
  echo "  ls -ldZ ${PROJECT_DIR}/storage ${PROJECT_DIR}/storage/framework/views ${PROJECT_DIR}/bootstrap/cache"
}

main "$@"
