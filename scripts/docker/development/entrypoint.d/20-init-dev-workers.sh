#!/bin/sh

# shellcheck disable=SC2034
script_name="init-dev-workers"

# Set default values for Laravel automations
: "${APP_WORKERS_ENABLED:=false}"

# Uncomment to enable artisan pail
# if [ "$APP_WORKERS_ENABLED" = "true" ]; then
#     touch /etc/s6-overlay/user-bundles.d/user/contents.d/artisan-pail
# fi

return 0
