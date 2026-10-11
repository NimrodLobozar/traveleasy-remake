#!/bin/sh
# Dev only (mounted by compose.dev.yaml). Runs once, when the DB volume is first initialised.
# Creates the `traveleasy_test` database used by `php artisan test` (phpunit.xml), owned by the app user.
# On an existing volume, create it by hand (see docs/deploy/dev.md):
#   docker compose exec db sh -c 'createdb -U "$POSTGRES_USER" -O "$POSTGRES_USER" traveleasy_test'
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
CREATE DATABASE traveleasy_test OWNER "$POSTGRES_USER";
EOSQL
