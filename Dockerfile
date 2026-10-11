# syntax=docker/dockerfile:1
#
# TravelEasy image definitions.
#
# Targets:
#   dev   Development image used by compose.dev.yaml for both the `app` (php artisan serve) and the
#         `vite` (Vite dev server) services. PHP 8.5 + Node 24 on one glibc (Debian) base, because the
#         Vite dev server and `vp build` call `php artisan wayfinder:generate`.
#
# The production stages (multi-stage, no dev dependencies, HEALTHCHECK) are added by the CI/CD epic
# (ADR-0002). Keep the `base` stage free of dev-only tooling so production can build on it.
#
# Base images are official and pinned. Bump them deliberately (and rebuild with `up -d --build -V`).

ARG PHP_VERSION=8.5.11
ARG NODE_VERSION=24.21.0
ARG COMPOSER_VERSION=2.10.3
ARG DEBIAN_RELEASE=trixie

FROM node:${NODE_VERSION}-${DEBIAN_RELEASE}-slim AS node
FROM composer:${COMPOSER_VERSION} AS composer

# ---------------------------------------------------------------------------------------------------
# base: PHP with the extensions the app needs, plus a non-root user.
# ---------------------------------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli-${DEBIAN_RELEASE} AS base

# Extensions beyond the ones the official image compiles in:
#   pdo_pgsql  PostgreSQL driver
#   intl       Number/locale formatting (bilingual nl/en UI, money)
#   bcmath     Arbitrary-precision math (money, brick/math used by the WebAuthn/passkeys stack)
#   pcntl      Signal handling for queue workers, `pail` and long-running artisan commands
#   zip        Composer dist archives
# OPcache is built into PHP 8.5; mbstring, openssl, sodium, dom, xml, curl, fileinfo, iconv etc. ship
# with the official image.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql intl bcmath pcntl zip; \
    rm -rf /var/lib/apt/lists/*

# Non-root user. UID/GID 1000 matches the default WSL user, so files created in the container
# (e.g. `php artisan make:model`) are owned sensibly; on Docker Desktop bind mounts from NTFS they
# are always editable from Windows.
ARG APP_UID=1000
ARG APP_GID=1000
RUN set -eux; \
    groupadd --gid "${APP_GID}" app; \
    useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app; \
    mkdir -p /var/www/html; \
    chown app:app /var/www/html

WORKDIR /var/www/html

# ---------------------------------------------------------------------------------------------------
# dev: adds Node, Composer and the dev dependencies. Source code is bind-mounted at runtime, so it is
# NOT copied into this image; only the lockfiles are, to install vendor/ and node_modules/, which
# compose then mounts as anonymous volumes over the bind mount.
# ---------------------------------------------------------------------------------------------------
FROM base AS dev

# Node + npm from the official Node image (same Debian release, glibc).
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN set -eux; \
    ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm; \
    ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx; \
    node --version; npm --version

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# PHP settings for development (php.ini-development + overrides in docker/dev/php.ini).
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/dev/php.ini "$PHP_INI_DIR/conf.d/zz-traveleasy-dev.ini"

COPY --chmod=0755 docker/dev/entrypoint.sh /usr/local/bin/traveleasy-dev-entrypoint
# Defensive: strip CR in case the build context was checked out with CRLF line endings.
RUN sed -i 's/\r$//' /usr/local/bin/traveleasy-dev-entrypoint

USER app
ENV COMPOSER_HOME=/home/app/.composer \
    COMPOSER_NO_INTERACTION=1

# PHP dependencies, reproducible from composer.lock. Scripts are skipped here: they need the app
# source (package:discover), which only exists at runtime; the entrypoint runs package:discover.
COPY --chown=app:app composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress \
 && composer dump-autoload --no-scripts \
 && rm -rf "$COMPOSER_HOME/cache"

# Node dependencies, reproducible from package-lock.json. `.npmrc` sets ignore-scripts=true, so no
# lifecycle scripts run; generated code (`npm run generate`) is produced at container start.
COPY --chown=app:app package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund \
 && npm cache clean --force

ENTRYPOINT ["traveleasy-dev-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
