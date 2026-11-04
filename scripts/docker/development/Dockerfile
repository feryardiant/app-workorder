# Add user/group
ARG PHP_VERSION=8.4
ARG BASE_OS=alpine
ARG BASE_VERSION=3.24

############################################
# Base Image
############################################
FROM serversideup/php:${PHP_VERSION}-fpm-nginx-${BASE_OS}${BASE_VERSION} AS base

USER root

ENV APP_WORKERS_ENABLED=true BOOST_BROWSER_LOGS_WATCHER=false \
    AUTORUN_ENABLED=true AUTORUN_LARAVEL_STORAGE_LINK=false \
    HEALTHCHECK_PATH=/up TRUSTED_PROXY=local

# Install additional php extensions
# https://github.com/mlocati/docker-php-extension-installer?tab=readme-ov-file
RUN install-php-extensions excimer gd intl sockets xdebug && \
    chown www-data:www-data /etc/s6-overlay/user-bundles.d/*/contents.d && \
    mv /usr/local/etc/php/conf.d/serversideup-docker-php.ini /usr/local/etc/php/php.ini

COPY --chmod=755 scripts/docker/common/entrypoint.d/ scripts/docker/development/entrypoint.d/ /etc/entrypoint.d/
COPY --chmod=755 scripts/docker/common/s6-overlay/ scripts/docker/development/s6-overlay/ /etc/s6-overlay/s6-rc.d/
COPY --chown=www-data:www-data scripts/docker/common/nginx/*.conf scripts/docker/development/nginx/*.conf /etc/nginx/server-opts.d/
COPY scripts/docker/development/php/*.ini /usr/local/etc/php/conf.d/

############################################
# Deployment Image
############################################
FROM base AS deploy

# Install node.js and enable corepack
RUN set -eux && apk --update add --no-cache --purge nodejs npm && \
    mkdir -p node_modules && chown www-data:www-data node_modules

VOLUME ["${APP_BASE_DIR}/node_modules"]

USER www-data
