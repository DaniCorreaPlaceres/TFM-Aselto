#!/bin/sh
set -e
cd /var/www/html
mkdir -p var/cache var/log var/share var/storage

lock_hash=$(sha256sum composer.lock | cut -d ' ' -f 1)
installed_hash=$(cat vendor/.aselto-lock-hash 2>/dev/null || true)
if [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$installed_hash" ]; then
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
    printf '%s\n' "$lock_hash" > vendor/.aselto-lock-hash
fi

# The cache volume is owned by root when Docker creates it.
chown www-data:www-data var/cache
chmod 2775 var/cache
umask 0002
if [ "$1" = "php-fpm" ]; then
    su -s /bin/sh www-data -c 'php bin/console sass:build'
fi
exec "$@"
