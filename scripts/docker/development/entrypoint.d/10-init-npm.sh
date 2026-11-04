#!/bin/sh

# shellcheck disable=SC2034
script_name="init-npm"

touch /etc/s6-overlay/user-bundles.d/user/contents.d/npm-install
touch /etc/s6-overlay/user-bundles.d/user/contents.d/npm-run-dev

return 0
