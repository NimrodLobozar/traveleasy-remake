#!/bin/sh
# Dev entrypoint for the `app` and `vite` containers (compose.dev.yaml). Not used in production.
#
# CONTAINER_ROLE=app  : ensure APP_KEY, discover packages, wait for the DB and migrate (if enabled).
# CONTAINER_ROLE=vite : generate Wayfinder routes and TS types.
#
# It never seeds the database: the starter kit's DatabaseSeeder creates a well-known test account.
# Seeding is a manual command (see docs/deploy/dev.md).
set -eu

cd /var/www/html

log() { printf '[entrypoint:%s] %s\n' "${CONTAINER_ROLE:-app}" "$*"; }

if [ ! -f .env ]; then
    log "ERROR: .env not found. Copy .env.example to .env and set DB_PASSWORD." >&2
    exit 1
fi

# Runtime folders must be writable by this (non-root) user. Files left behind by a container that ran
# as root keep root ownership on the bind mount; point at the one-time fix instead of failing later.
unwritable=$(find storage bootstrap/cache ! -writable -print 2>/dev/null | head -n 5 || true)
if [ -n "$unwritable" ]; then
    log "WARNING: not writable by $(id -un) (uid $(id -u)):"
    printf '    %s\n' $unwritable
    log "Fix once with: docker compose run --rm --no-deps --user root --entrypoint chown app -R 1000:1000 storage bootstrap/cache"
fi

case "${CONTAINER_ROLE:-app}" in
    app)
        # APP_KEY: generate once when empty; an existing key is never overwritten.
        if ! grep -Eq '^APP_KEY=.+' .env; then
            if ! grep -q '^APP_KEY=' .env; then
                printf '\nAPP_KEY=\n' >> .env
            fi
            log "APP_KEY is empty: generating one into .env"
            php artisan key:generate --force
        fi

        php artisan package:discover

        if [ "${MIGRATE_ON_START:-true}" = "true" ]; then
            # Retries double as "wait for the database".
            tries=0
            until php artisan migrate --force --no-interaction; do
                tries=$((tries + 1))
                if [ "$tries" -ge 15 ]; then
                    log "WARNING: migrate failed $tries times; starting anyway. Check 'docker compose logs app'."
                    break
                fi
                log "migrate failed (database not ready?), retrying in 2 s ($tries/15)"
                sleep 2
            done
        fi
        ;;
    vite)
        # Wayfinder route helpers and TS types are git-ignored; generate them before Vite starts.
        if ! npm run generate; then
            log "WARNING: 'npm run generate' failed; Vite starts anyway. Run it again with 'docker compose exec vite npm run generate'."
        fi
        ;;
    *)
        log "unknown CONTAINER_ROLE, skipping role setup"
        ;;
esac

exec "$@"
