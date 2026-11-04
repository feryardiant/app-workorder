# Add user/group
ARG PHP_VERSION=8.4
ARG BASE_OS=alpine
ARG BASE_VERSION=3.24

############################################
# Base Image
############################################
FROM serversideup/php:${PHP_VERSION}-fpm-nginx-${BASE_OS}${BASE_VERSION} AS base

USER root

ENV APP_WORKERS_ENABLED=false \
    AUTORUN_ENABLED=true AUTORUN_LARAVEL_STORAGE_LINK=false \
    HEALTHCHECK_PATH=/up PHP_OPCACHE_ENABLE=1

# Install additional php extensions
# https://github.com/mlocati/docker-php-extension-installer?tab=readme-ov-file
RUN install-php-extensions excimer gd intl && \
    chown www-data:www-data /etc/s6-overlay/user-bundles.d/*/contents.d && \
    mv /usr/local/etc/php/conf.d/serversideup-docker-php.ini /usr/local/etc/php/php.ini

COPY --chmod=755 scripts/docker/common/entrypoint.d/ /etc/entrypoint.d/
# COPY --chmod=755 scripts/docker/staging/entrypoint.d/ /etc/entrypoint.d/
COPY --chmod=755 scripts/docker/common/s6-overlay/ scripts/docker/staging/s6-overlay/ /etc/s6-overlay/s6-rc.d/
COPY --chown=www-data:www-data scripts/docker/common/nginx/*.conf /etc/nginx/server-opts.d/
# COPY --chown=www-data:www-data scripts/docker/staging/nginx/*.conf /etc/nginx/server-opts.d/

############################################
# Deployment Image
############################################
FROM base AS deploy

# Copy our project files into the container
COPY --chown=www-data:www-data . .

USER www-data

# Install composer dependencies
RUN --mount=type=cache,target=/composer/cache \
    composer install --optimize-autoloader --no-interaction --no-progress --no-dev

# Clean up
RUN rm -rf scripts/docker && find vendor \( -name 'docs' -or -name '.*' \) -exec rm -rf {} + && \
    find vendor -type f \( -name '*.md' -and -not -name 'LICENSE.md' \) -exec rm {} +

VOLUME ["${APP_BASE_DIR}/storage/app"]
