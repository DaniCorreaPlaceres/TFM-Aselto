#!/bin/sh
set -eu
cd /var/www/html
previous_hash=''
# Polling also detects edits on Windows bind mounts without inotify events.
while true; do
    current_hash=$(find assets/styles -type f -name '*.scss' -exec sha256sum {} \; | sort | sha256sum)
    if [ "$current_hash" != "$previous_hash" ]; then
        if php bin/console sass:build; then
            previous_hash=$current_hash
        fi
    fi
    sleep 2
done
