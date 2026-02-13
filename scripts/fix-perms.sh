#!/bin/bash

PROJECT_DIR="${INSTALL_DIR:-/var/www/admixcentral}"
APP_USER="${WEB_USER:-administrator}"
WEB_GROUP="${WEB_GROUP:-www-data}"

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
