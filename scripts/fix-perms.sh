#!/bin/bash

PROJECT_DIR="${INSTALL_DIR:-/var/www/admixcentral}"

# Auto-detect OS defaults for web user
DEFAULT_USER="www-data"
if [[ -f /etc/os-release ]]; then
  . /etc/os-release
  if [[ "$ID" == "fedora" || "$ID_LIKE" == *"rhel"* || "$ID_LIKE" == *"centos"* || "$ID" == "suse"* || "$ID_LIKE" == *"suse"* ]]; then
    DEFAULT_USER="nginx"
  elif [[ "$ID" == "arch" || "$ID_LIKE" == *"arch"* ]]; then
    DEFAULT_USER="http"
  fi
fi

APP_USER="${WEB_USER:-$DEFAULT_USER}"
WEB_GROUP="${WEB_GROUP:-$DEFAULT_USER}"

echo "Fixing permissions for $PROJECT_DIR"

cd $PROJECT_DIR || exit 1

# 1. Set ownership (user owns, www-data group)
sudo chown -R $APP_USER:$WEB_GROUP .

# 2. Fix directory permissions (exclude node_modules)
sudo find . -path ./node_modules -prune -o -type d -exec chmod 2755 {} \;

# 3. Fix file permissions (exclude node_modules)
sudo find . -path ./node_modules -prune -o -type f -exec chmod 0644 {} \;

# 4. Ensure Laravel writable directories
sudo chown -R $APP_USER:$WEB_GROUP storage bootstrap/cache
sudo chmod -R 2775 storage bootstrap/cache

# 5. Ensure build directory exists and is correct
mkdir -p public/build
sudo chown -R $APP_USER:$WEB_GROUP public/build
sudo chmod -R 2775 public/build

echo "Permissions fixed safely."
