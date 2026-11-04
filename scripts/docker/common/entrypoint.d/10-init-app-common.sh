#!/bin/sh

# shellcheck disable=SC2034
script_name="init-app-common"

mkdir -p /var/www/html/storage/app/private
mkdir -p /var/www/html/storage/app/public

# Set default values for Laravel automations
: "${APP_WORKERS_ENABLED:=false}"

if [ "$APP_WORKERS_ENABLED" = "true" ]; then
    touch /etc/s6-overlay/user-bundles.d/user/contents.d/artisan-schedule
    touch /etc/s6-overlay/user-bundles.d/user/contents.d/artisan-queue
fi

return 0
