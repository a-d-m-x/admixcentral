#!/usr/bin/env bash
# AdmixCentral environment preparation and host-readiness wizard.
# Application releases continue to be managed by the built-in GitHub updater.
set -Eeuo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
command -v python3 >/dev/null || {
    echo 'Python 3.9+ is required: apt-get install python3 / dnf install python3 / pacman -S python' >&2
    exit 1
}
exec python3 -B "${script_dir}/scripts/security_upgrade/wizard.py" "$@"
