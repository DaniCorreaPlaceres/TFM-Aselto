#!/bin/sh
set -e
cd /var/www/html
mkdir -p var/cache var/log var/share var/storage
exec "$@"
