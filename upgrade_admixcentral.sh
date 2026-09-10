#!/usr/bin/env bash
# AdmixCentral environment preparation and host-readiness wizard.
# Application releases continue to be managed by the built-in GitHub updater.
set -Eeuo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

# ---------------------------------------------------------------------------
# Run pending database migrations before host preparation.
# This ensures new columns (e.g. tls_public_key_pin, ssh_host_key_fingerprint)
# are applied automatically whenever the upgrade script is executed.
# ---------------------------------------------------------------------------
APP_DIR="${INSTALL_DIR:-/var/www/admixcentral}"
PHP_BIN="${PHP_BIN:-$(command -v php 2>/dev/null || true)}"

if [ -z "$PHP_BIN" ]; then
    echo 'WARNING: php not found in PATH; skipping database migrations.' >&2
elif [ ! -f "${APP_DIR}/artisan" ]; then
    echo "WARNING: ${APP_DIR}/artisan not found; skipping database migrations." >&2
else
    echo "==> Running pending database migrations..."
    "$PHP_BIN" "${APP_DIR}/artisan" migrate --force
    echo "    Migrations complete."
fi

# ---------------------------------------------------------------------------
# Delegate to the host-readiness wizard for the remaining preparation steps.
# ---------------------------------------------------------------------------
command -v python3 >/dev/null || {
    echo 'Python 3.9+ is required: apt-get install python3 / dnf install python3 / pacman -S python' >&2
    exit 1
}
exec python3 -B "${script_dir}/scripts/security_upgrade/wizard.py" "$@"
