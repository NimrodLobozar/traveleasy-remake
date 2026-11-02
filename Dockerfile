# syntax=docker/dockerfile:1
#
# TravelEasy image definitions.
#
# Targets:
#   dev         Development image used by compose.dev.yaml for both the `app` (php artisan serve) and the
#               `vite` (Vite dev server) services. PHP 8.5 + Node 24 on one glibc (Debian) base, because the
#               Vite dev server and `vp build` call `php artisan wayfinder:generate`.
#   production  Hardened runtime image (ADR-0002, TE-012): FrankenPHP in classic mode on :8080, the `node`
#               binary for the SSR role, non-root uid 10001, root-owned read-only code, no dev dependencies.
#               One image serves the app, ssr, worker and scheduler services (role = first argument of the
#               entrypoint). Build: docker build --platform linux/amd64 --target production .
#
# Base images are official and pinned by tag AND digest (multi-arch index digests). Bump them deliberately:
# change the version ARG and its *_DIGEST together (a stale digest fails the build loudly).

ARG PHP_VERSION=8.5.12
ARG PHP_CLI_DIGEST=sha256:9236fd373d3bcda0659cf94838a744bd79791419292958963f8c6b34a0071554
ARG FRANKENPHP_VERSION=1.13.4
ARG FRANKENPHP_DIGEST=sha256:6f86f003f1478bafa7befe6ec26db5575e703882171f785189de1e222df0e38e
ARG NODE_VERSION=24.21.0
ARG NODE_DIGEST=sha256:173f125896c3b47ddf056734c7ea789d04595a6a08769a8f78e0df642781fb66
ARG COMPOSER_VERSION=2.10.3
ARG COMPOSER_DIGEST=sha256:2cf275428757e5435a667bfc0d038b14520ca02be2b1b70d44a05c8c19e2de48
ARG DEBIAN_RELEASE=trixie
# One list for the dev and production stages, so the two cannot drift (ADR-0002 D1).
ARG PHP_EXTENSIONS="pdo_pgsql intl bcmath pcntl zip"

FROM node:${NODE_VERSION}-${DEBIAN_RELEASE}-slim@${NODE_DIGEST} AS node
FROM composer:${COMPOSER_VERSION}@${COMPOSER_DIGEST} AS composer

# ---------------------------------------------------------------------------------------------------
# base: PHP with the extensions the app needs, plus a non-root user.
# ---------------------------------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli-${DEBIAN_RELEASE}@${PHP_CLI_DIGEST} AS base

ARG PHP_EXTENSIONS

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
    docker-php-ext-install -j"$(nproc)" ${PHP_EXTENSIONS}; \
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

# ---------------------------------------------------------------------------------------------------
# build: compiles the client and SSR bundles (public/build, bootstrap/ssr). Throwaway; nothing from here
# except those outputs reaches the production image. Uses the dev image (PHP + Node + all dependencies),
# because Wayfinder and the TypeScript transformer run `php artisan` during the build.
# ---------------------------------------------------------------------------------------------------
FROM dev AS build

COPY --chown=app:app . .
# A throwaway all-zero key only so artisan can boot; it is not part of the production image.
RUN set -eux; \
    export APP_KEY="base64:$(head -c 32 /dev/zero | base64)"; \
    composer dump-autoload --no-scripts; \
    php artisan package:discover --ansi; \
    npm run generate; \
    npm run build; \
    test -f public/build/manifest.json; \
    test -f bootstrap/ssr/ssr.js

# ---------------------------------------------------------------------------------------------------
# vendor: production PHP dependencies only (no dev packages), with an optimised, authoritative classmap.
# ---------------------------------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION}@${COMPOSER_DIGEST} AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
# Platform requirements are checked at runtime in the production stage, which has the extensions.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --ignore-platform-reqs
COPY --chmod=a+rX,go-w app ./app
COPY --chmod=a+rX,go-w database ./database
RUN composer dump-autoload --no-dev --no-scripts --optimize --classmap-authoritative --ignore-platform-reqs

# ---------------------------------------------------------------------------------------------------
# production
# ---------------------------------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-${DEBIAN_RELEASE}@${FRANKENPHP_DIGEST} AS production

ARG PHP_EXTENSIONS
ARG VERSION=dev
ARG REVISION=unknown
ARG CREATED=unknown

LABEL org.opencontainers.image.title="TravelEasy" \
      org.opencontainers.image.description="TravelEasy (remake): travel-agency web app (production image for app, ssr, worker and scheduler)" \
      org.opencontainers.image.source="https://github.com/NimrodLobozar/traveleasy-remake" \
      org.opencontainers.image.url="https://github.com/NimrodLobozar/traveleasy-remake" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.revision="${REVISION}" \
      org.opencontainers.image.created="${CREATED}"

# PHP extensions (the install helper removes its own build dependencies), then take the file capability off the
# FrankenPHP binary: under `cap_drop: [ALL]` + `no-new-privileges` a binary with file capabilities cannot be
# executed (ADR-0002 measurement). It listens on 8080, so it needs none.
RUN set -eux; \
    install-php-extensions ${PHP_EXTENSIONS}; \
    setcap -r /usr/local/bin/frankenphp; \
    ! getcap /usr/local/bin/frankenphp | grep -q .; \
    rm -rf /var/lib/apt/lists/* /tmp/*

# Only the node binary, for the `ssr` role. No npm, npx or node_modules (ADR-0002 D2).
COPY --from=node /usr/local/bin/node /usr/local/bin/node

# Dedicated uid/gid with no counterpart on any host; the code stays owned by root.
RUN set -eux; \
    groupadd --gid 10001 app; \
    useradd --uid 10001 --gid 10001 --no-create-home --home-dir /nonexistent --shell /usr/sbin/nologin app

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/prod/php.ini "$PHP_INI_DIR/conf.d/zz-traveleasy-prod.ini"
COPY docker/prod/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=0755 docker/prod/entrypoint.sh /usr/local/bin/traveleasy-entrypoint
COPY docker/prod/healthcheck.php /usr/local/lib/traveleasy/healthcheck.php
COPY docker/prod/wait-for-db.php /usr/local/lib/traveleasy/wait-for-db.php

WORKDIR /var/www/html

# Application code: only what runs. No tests, docs, node_modules, .git or .env (see also .dockerignore).
COPY --from=vendor --chmod=a+rX,go-w /app/vendor ./vendor
COPY --chmod=a+rX,go-w artisan composer.json ./
COPY app ./app
COPY --chmod=a+rX,go-w bootstrap ./bootstrap
COPY --chmod=a+rX,go-w config ./config
COPY database ./database
COPY --chmod=a+rX,go-w lang ./lang
COPY --chmod=a+rX,go-w routes ./routes
COPY --chmod=a+rX,go-w resources/views ./resources/views
COPY --from=build --chown=0:0 --chmod=a+rX,go-w /var/www/html/public ./public
COPY --from=build --chown=0:0 --chmod=a+rX,go-w /var/www/html/bootstrap/ssr ./bootstrap/ssr

ENV APP_ENV=production \
    APP_DEBUG=false \
    INERTIA_DEVTOOLS_ENABLED=false \
    LOG_CHANNEL=stderr \
    HOME=/tmp \
    APP_CONFIG_CACHE=/tmp/laravel/config.php \
    APP_ROUTES_CACHE=/tmp/laravel/routes-v7.php \
    APP_EVENTS_CACHE=/tmp/laravel/events.php \
    NODE_ENV=production

# The code is root-owned and read-only for the app user (COPY creates root-owned files; `--chmod=a+rX,go-w`
# fixes the modes without a second layer). Normalise line endings and pre-create the only
# paths that may be written: storage/app (a named volume in compose, copied from here on first mount),
# Caddy's /data and /config (tmpfs in compose). Also prove at build time that package
# discovery and `optimize` (config, route, event and view caches) work. The output goes to /tmp/proof and is
# thrown away: the real caches depend on the runtime environment and are written by the entrypoint.
RUN set -eux; \
    sed -i 's/\r$//' /usr/local/bin/traveleasy-entrypoint; \
    rm -rf public/hot public/storage public/.htaccess; \
    mkdir -p storage/app storage/framework/views storage/logs bootstrap/cache /data /config /tmp/proof; \
    php artisan package:discover --ansi; \
    APP_KEY="base64:$(head -c 32 /dev/zero | base64)" \
    APP_CONFIG_CACHE=/tmp/proof/config.php APP_ROUTES_CACHE=/tmp/proof/routes.php APP_EVENTS_CACHE=/tmp/proof/events.php \
        php artisan optimize --no-interaction; \
    test -s /tmp/proof/routes.php; \
    rm -rf /tmp/proof storage/framework/views/*; \
    chown root:root /var/www/html; \
    chmod 0755 /var/www/html; \
    chown 10001:10001 storage/app /data /config; \
    chmod 0750 storage/app

USER 10001:10001
EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=5s --start-period=90s --retries=3 \
    CMD ["php", "/usr/local/lib/traveleasy/healthcheck.php"]

ENTRYPOINT ["traveleasy-entrypoint"]
CMD ["app"]
