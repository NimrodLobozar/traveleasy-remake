#!/bin/sh
# Production entrypoint (ADR-0002 D3, D4): `traveleasy-entrypoint <role> [args]`.
#
#   app        wait for the DB, migrate, seed reference data (empty tables only), cache, run FrankenPHP. The only role that migrates.
#   ssr        node bootstrap/ssr/ssr.js (no PHP boot, no DB, no secrets)
#   worker     queue worker, never migrates
#   scheduler  schedule:work, never migrates
#   other      exec "$@" unchanged: no DB wait, no boot side effects (e.g. the secrets helper of TE-025)
#
# Env-dependent caches are written here at start, into tmpfs (APP_*_CACHE), never at build time.
# Output never includes environment values.
set -eu

role="${1:-}"
cd /var/www/html

log() { printf '[entrypoint:%s] %s\n' "$role" "$*" >&2; }

prepare_runtime() {
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/framework/testing "$(dirname "$APP_CONFIG_CACHE")"
}

case "$role" in
    app)
        prepare_runtime
        php /usr/local/lib/traveleasy/wait-for-db.php
        log "migrating"
        php artisan migrate --force --no-interaction
        log "seeding reference data (empty tables only)"
        php artisan traveleasy:seed-reference --if-empty --no-interaction
        log "caching config, routes, events and views"
        php artisan optimize --no-interaction
        log "starting FrankenPHP on :8080"
        exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
        ;;
    ssr)
        exec node bootstrap/ssr/ssr.js
        ;;
    worker)
        prepare_runtime
        php /usr/local/lib/traveleasy/wait-for-db.php
        php artisan optimize --no-interaction
        exec php artisan queue:work --tries=3 --backoff=10,60 --max-time=3600 --max-jobs=500 --memory=256
        ;;
    scheduler)
        prepare_runtime
        php /usr/local/lib/traveleasy/wait-for-db.php
        php artisan optimize --no-interaction
        exec php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
