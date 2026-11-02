#!/usr/bin/env bash
# Verifies the production image (TE-012). Usage: docker/prod/verify.sh <image>
#
# Starts a throwaway PostgreSQL, SSR and app container on a throwaway network (all named te012-verify-<pid>),
# runs the app hardened exactly as production will (read-only root, cap_drop ALL, no-new-privileges, tmpfs for
# writable paths), asserts the acceptance criteria and removes everything it created, also on failure. It never
# touches other containers, networks or volumes and never prunes.
#
# Needs: docker, bash, openssl. CI (TE-014) runs it after the build.
set -uo pipefail
export MSYS_NO_PATHCONV=1   # Git Bash on Windows would rewrite /var/... arguments

IMAGE="${1:?usage: verify.sh <image>}"
RUN="te012-verify-$$"
NET="${RUN}-net"
DB="${RUN}-db"
SSR="${RUN}-ssr"
APP="${RUN}-app"
APP_HOST="verify.example.test"
KEY="base64:$(openssl rand -base64 32)"
DB_PASS="$(openssl rand -hex 16)"
FAILED=0

cleanup() {
    docker rm -f "$APP" "$SSR" "$DB" "${RUN}-fail" "${RUN}-boot" "${RUN}-ssr8" "${RUN}-migfail" "${RUN}-static" >/dev/null 2>&1
    docker network rm "$NET" >/dev/null 2>&1
}
trap cleanup EXIT

pass() { printf 'PASS  %s\n' "$1"; }
fail() { printf 'FAIL  %s\n' "$1"; FAILED=1; }
check() { # check <description> <command...>
    local d="$1"; shift
    if "$@" >/dev/null 2>&1; then pass "$d"; else fail "$d"; fi
}
# Runs in a fresh container of the image, bypassing the entrypoint role logic.
in_image() { docker run --rm --platform linux/amd64 --entrypoint "$@"; }
HARDEN=(--read-only --cap-drop ALL --security-opt no-new-privileges:true
    --tmpfs /tmp:uid=10001,mode=1777 --tmpfs /var/www/html/storage/framework:uid=10001
    --tmpfs /data:uid=10001 --tmpfs /config:uid=10001)

echo "== static content of $IMAGE"
check "AC2 runs as uid/gid 10001" test "$(docker run --rm --entrypoint id "$IMAGE" -u):$(docker run --rm --entrypoint id "$IMAGE" -g)" = "10001:10001"
check "AC2 code is owned by root, 0755" test "$(docker run --rm --entrypoint stat "$IMAGE" -c '%U:%a' /var/www/html)" = "root:755"
check "AC2 code is not writable by the app user" docker run --rm --entrypoint sh "$IMAGE" -c '! touch /var/www/html/x 2>/dev/null && ! touch /var/www/html/app/x 2>/dev/null'
check "TE-028 AC1 everything outside storage/app is owned by root" docker run --rm --entrypoint sh "$IMAGE" -c '[ -z "$(find /var/www/html -path /var/www/html/storage/app -prune -o ! -user root -print)" ]'
check "AC2 storage/app is writable by the app user" docker run --rm --entrypoint sh "$IMAGE" -c 'touch /var/www/html/storage/app/x'
for p in tests node_modules .git .env docs vendor/phpunit vendor/pestphp vendor/larastan resources/js; do
    check "AC1 no $p in the image" docker run --rm --entrypoint sh "$IMAGE" -c "! test -e /var/www/html/$p"
done
check "AC1 client and SSR bundles are present" docker run --rm --entrypoint sh "$IMAGE" -c 'test -f public/build/manifest.json && test -f bootstrap/ssr/ssr.js'
check "AC14 frankenphp has no file capability" docker run --rm --entrypoint sh "$IMAGE" -c '[ -z "$(getcap /usr/local/bin/frankenphp)" ]'
check "AC14 only the node binary, no npm or node_modules" docker run --rm --entrypoint sh "$IMAGE" -c 'node --version >/dev/null && ! command -v npm && ! test -d /usr/local/lib/node_modules'
check "AC14 SSR bundle starts without node_modules" docker run --rm --entrypoint sh "$IMAGE" -c '! test -d /var/www/html/node_modules && (timeout 5 node bootstrap/ssr/ssr.js >/tmp/o 2>&1; true) && ! grep -qi "cannot find\|ERR_MODULE_NOT_FOUND" /tmp/o'
check "AC10 OCI labels (source, revision, version, licence)" bash -c "docker inspect $IMAGE --format '{{json .Config.Labels}}' | grep -q 'image.source' && docker inspect $IMAGE --format '{{json .Config.Labels}}' | grep -q 'image.revision' && docker inspect $IMAGE --format '{{json .Config.Labels}}' | grep -q 'image.version' && docker inspect $IMAGE --format '{{json .Config.Labels}}' | grep -q '\"org.opencontainers.image.licenses\":\"MIT\"'"

echo "== configuration"
PHPI="$(docker run --rm --entrypoint php "$IMAGE" -i)"
check "AC4/AC17 expose_php Off" grep -Eq '^expose_php => Off' <<<"$PHPI"
check "AC4 display_errors Off" grep -Eq '^display_errors => Off' <<<"$PHPI"
check "AC4 opcache.validate_timestamps 0" grep -Eq '^opcache.validate_timestamps => (0|Off)' <<<"$PHPI"
check "AC4 the dev ini is not present" docker run --rm --entrypoint sh "$IMAGE" -c '! ls /usr/local/etc/php/conf.d | grep -q dev'
check "AC17 APP_ENV=production, APP_DEBUG=false, devtools off" docker run --rm --entrypoint sh "$IMAGE" -c '[ "$APP_ENV" = production ] && [ "$APP_DEBUG" = false ] && [ "$INERTIA_DEVTOOLS_ENABLED" = false ]'
check "AC1 no secret-looking env baked into the image" bash -c "! docker inspect $IMAGE --format '{{json .Config.Env}}' | grep -Eq 'APP_KEY|PASSWORD|SECRET'"

echo "== runtime (hardened, throwaway network)"
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" --network-alias db \
    -e POSTGRES_DB=traveleasy -e POSTGRES_USER=traveleasy -e POSTGRES_PASSWORD="$DB_PASS" \
    --health-cmd 'pg_isready -q -U traveleasy -d traveleasy' --health-interval 2s --health-retries 30 \
    postgres:18.6-alpine >/dev/null
docker run -d --name "$SSR" --network "$NET" --network-alias ssr --read-only --cap-drop ALL \
    --security-opt no-new-privileges:true -e INERTIA_SSR_HOST=0.0.0.0 "$IMAGE" ssr >/dev/null

wait_for() { # wait_for <container> <healthy|exit> <seconds>
    local c="$1" t="$3" i=0
    while [ $i -lt "$t" ]; do
        local s; s="$(docker inspect -f '{{.State.Health.Status}}' "$c" 2>/dev/null)"
        [ "$s" = healthy ] && return 0
        i=$((i + 1)); sleep 1
    done
    return 1
}
wait_for "$DB" healthy 60 || fail "database did not become healthy"

# AC7: unreachable database makes the entrypoint exit non-zero with a clear message, without env values.
docker run --name "${RUN}-fail" --network "$NET" "${HARDEN[@]}" -e DB_HOST=nowhere -e DB_DATABASE=x -e DB_USERNAME=x \
    -e DB_PASSWORD=sekret-value-123 -e DB_WAIT_TIMEOUT=4 -e APP_KEY="$KEY" "$IMAGE" app >"${TMPDIR:-/tmp}/${RUN}-fail.log" 2>&1
code=$?
check "AC6/AC7 DB wait times out with exit code != 0 and a clear message" bash -c "[ $code -ne 0 ] && grep -q 'not reachable after 4 s' ${TMPDIR:-/tmp}/${RUN}-fail.log"
check "AC7 the failure output does not echo env values" bash -c "! grep -q 'sekret-value-123' ${TMPDIR:-/tmp}/${RUN}-fail.log"
rm -f "${TMPDIR:-/tmp}/${RUN}-fail.log"

# AC8: the boot check refuses to start app/worker/scheduler with APP_DEBUG=true, an empty APP_KEY or a wildcard
# TRUSTED_PROXIES, before the DB wait and with a clear message; never for the ssr role or the pass-through.
BOOT_LOG="${TMPDIR:-/tmp}/${RUN}-boot.log"
boot_refuses() { # boot_refuses <role> <expected message> <env...>
    local role="$1" msg="$2"; shift 2
    docker run --rm --name "${RUN}-boot" --network "$NET" "${HARDEN[@]}" -e DB_HOST=db -e DB_WAIT_TIMEOUT=4 \
        "$@" "$IMAGE" "$role" >"$BOOT_LOG" 2>&1
    local code=$?
    [ "$code" -ne 0 ] && grep -q "Refusing to start: $msg" "$BOOT_LOG" && ! grep -q 'database .* is ready\|migrating' "$BOOT_LOG"
}
check "AC8 app refuses to start with APP_DEBUG=true" boot_refuses app 'APP_DEBUG is true' -e APP_KEY="$KEY" -e APP_DEBUG=true
check "AC8 app refuses to start with an empty APP_KEY" boot_refuses app 'APP_KEY is empty' -e APP_KEY=
check "AC8 app refuses to start with TRUSTED_PROXIES=*" boot_refuses app 'TRUSTED_PROXIES contains \[\*\]' -e APP_KEY="$KEY" -e 'TRUSTED_PROXIES=*'
check "AC8 worker refuses to start with APP_DEBUG=true" boot_refuses worker 'APP_DEBUG is true' -e APP_KEY="$KEY" -e APP_DEBUG=true
check "AC8 scheduler refuses to start with an empty APP_KEY" boot_refuses scheduler 'APP_KEY is empty' -e APP_KEY=
LEAK_KEY='base64:dG9vLXNob3J0LXNla3JldA=='   # 16 bytes: an invalid key for AES-256, so the check must refuse it
docker run --rm --name "${RUN}-boot" "${HARDEN[@]}" -e APP_KEY="$LEAK_KEY" "$IMAGE" app >"$BOOT_LOG" 2>&1
check "AC8 an invalid APP_KEY is refused without printing it" bash -c "grep -q 'Refusing to start: APP_KEY' $BOOT_LOG && ! grep -q 'dG9vLXNob3J0LXNla3JldA' $BOOT_LOG"
check "AC8 the pass-through runs one-off commands without APP_KEY, even with APP_DEBUG=true" \
    docker run --rm --name "${RUN}-boot" "${HARDEN[@]}" -e APP_KEY= -e APP_DEBUG=true "$IMAGE" php artisan --version
docker run -d --name "${RUN}-ssr8" --read-only --cap-drop ALL --security-opt no-new-privileges:true \
    -e APP_KEY= -e APP_DEBUG=true "$IMAGE" ssr >/dev/null
sleep 3
check "AC8 the ssr role starts without APP_KEY, even with APP_DEBUG=true" \
    bash -c "[ \"\$(docker inspect -f '{{.State.Running}}' ${RUN}-ssr8)\" = true ] && ! docker logs ${RUN}-ssr8 2>&1 | grep -q 'Refusing to start'"
docker rm -f "${RUN}-ssr8" >/dev/null 2>&1
rm -f "$BOOT_LOG"

# AC7: a failed migration makes the app role exit non-zero with the migration error, before it serves anything and
# without printing env values. The database `migfail` already has a `users` table, so the first migration fails.
docker exec "$DB" psql -q -U traveleasy -d traveleasy -c 'create database migfail' >/dev/null
docker exec "$DB" psql -q -U traveleasy -d migfail -c 'create table users (id int)' >/dev/null
MIG_LOG="${TMPDIR:-/tmp}/${RUN}-migfail.log"
docker run --name "${RUN}-migfail" --network "$NET" "${HARDEN[@]}" -e APP_KEY="$KEY" \
    -e DB_CONNECTION=pgsql -e DB_HOST=db -e DB_PORT=5432 -e DB_DATABASE=migfail -e DB_USERNAME=traveleasy \
    -e DB_PASSWORD="$DB_PASS" "$IMAGE" app >"$MIG_LOG" 2>&1
code=$?
check "AC7 a failed migration exits non-zero with the migration error" bash -c "[ $code -ne 0 ] && grep -q 'migrating' $MIG_LOG && grep -qi 'already exists' $MIG_LOG"
check "AC7 the app never starts serving after a failed migration" bash -c "! grep -q 'starting FrankenPHP' $MIG_LOG && [ \"\$(docker inspect -f '{{.State.Running}}' ${RUN}-migfail)\" = false ]"
check "AC7 the migration error does not print env values" bash -c "! grep -qF '$DB_PASS' $MIG_LOG && ! grep -qF '${KEY#base64:}' $MIG_LOG"
docker rm -f "${RUN}-migfail" >/dev/null 2>&1
rm -f "$MIG_LOG"

START=$(date +%s)
docker run -d --name "$APP" --network "$NET" --platform linux/amd64 "${HARDEN[@]}" \
    -e APP_URL="https://${APP_HOST}" -e APP_KEY="$KEY" -e TRUSTED_PROXIES=10.255.255.255 \
    -e DB_CONNECTION=pgsql -e DB_HOST=db -e DB_PORT=5432 -e DB_DATABASE=traveleasy -e DB_USERNAME=traveleasy \
    -e DB_PASSWORD="$DB_PASS" -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
    -e INERTIA_SSR_ENABLED=true -e INERTIA_SSR_URL=http://ssr:13714 \
    "$IMAGE" app >/dev/null

if wait_for "$APP" healthy 120; then
    pass "AC3/AC5 the hardened container becomes healthy in $(( $(date +%s) - START )) s"
else
    fail "AC3/AC5 the container did not become healthy"
    docker logs "$APP" 2>&1 | tail -30
fi

get() { # get <path> [extra curl-less args]: runs a PHP one-liner inside the app container
    docker exec "$APP" php -r '
        $c = stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 10,
            "header" => "Host: '"$APP_HOST"'\r\n".($argv[2] ?? "")]]);
        $body = @file_get_contents("http://127.0.0.1:8080".$argv[1], false, $c);
        echo implode("\n", $http_response_header ?? []), "\n\n", $body;' "$@" | tr -d '\0'
}

check "AC6 migrations ran on the empty database" bash -c "docker exec $DB psql -U traveleasy -d traveleasy -tAc 'select count(*) from migrations' | grep -Eq '^[1-9]'"
check "AC6 the kit's test@example.com user was not created" bash -c "[ \"\$(docker exec $DB psql -U traveleasy -d traveleasy -tAc \"select count(*) from users where email='test@example.com'\")\" = 0 ]"
check "AC5 /up with the Host header answers 200" bash -c "docker inspect -f '{{.State.Health.Status}}' $APP | grep -q healthy"
check "AC9 /_inertia/devtools/entries answers 404" bash -c "docker exec $APP php -r '\$c=stream_context_create([\"http\"=>[\"ignore_errors\"=>true,\"header\"=>\"Host: $APP_HOST\\r\\n\"]]); @file_get_contents(\"http://127.0.0.1:8080/_inertia/devtools/entries\",false,\$c); exit(str_contains(\$http_response_header[0], \" 404\") ? 0 : 1);'"

HOME_RESP="$(get /)"
check "AC15 / answers 200" grep -q '^HTTP/[0-9.]* 200' <<<"$HOME_RESP"
check "AC15 / is rendered server-side by the self-contained SSR bundle (no node_modules in the image)" grep -q '<h1' <<<"$HOME_RESP"
check "AC17 the response has no X-Powered-By" bash -c "! grep -qi '^x-powered-by' <<<\"\$1\"" _ "$HOME_RESP"

FAV="$(get /favicon.ico)"
check "AC16 static file has X-Content-Type-Options: nosniff" grep -qi '^x-content-type-options: nosniff' <<<"$FAV"
check "AC16 static file has a CSP with frame-ancestors 'none'" grep -Eqi "^content-security-policy: .*frame-ancestors 'none'" <<<"$FAV"
check "AC16 static file has X-Frame-Options: DENY" grep -qi '^x-frame-options: DENY' <<<"$FAV"
check "AC16 PHP responses keep the app's nonce CSP (not overridden)" grep -Eqi "^content-security-policy: .*nonce-" <<<"$HOME_RESP"

# TE-028 AC2: hidden files are 404 (also the kit's .htaccess), /.well-known/ keeps working. A throwaway root container
# (not hardened) gets a probe file under public/.well-known; only static serving is exercised, so no DB is needed.
docker run -d --name "${RUN}-static" --user 0 --entrypoint sh "$IMAGE" -c 'mkdir -p public/.well-known && echo ok > public/.well-known/probe.txt && echo secret > public/.env && exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile' >/dev/null
sleep 5
status() { docker exec "${RUN}-static" php -r '$c=stream_context_create(["http"=>["ignore_errors"=>true]]); @file_get_contents("http://127.0.0.1:8080".$argv[1],false,$c); echo preg_match("#\s(\d{3})\s#", $http_response_header[0] ?? "", $m) ? $m[1] : "none";' "$1"; }
for hp in /.htaccess /.env /.git/config /.well-known/missing.txt; do
    check "TE-028 AC2 GET $hp answers 404" test "$(status "$hp")" = 404
done
check "TE-028 AC2 GET /.well-known/probe.txt (existing file) answers 200" test "$(status /.well-known/probe.txt)" = 200
check "TE-028 AC2 public/.htaccess is not in the image" docker run --rm --entrypoint sh "$IMAGE" -c '! test -e /var/www/html/public/.htaccess'
check "AC3 the root filesystem is read-only" bash -c "! docker exec $APP sh -c 'touch /var/www/html/x 2>/dev/null || touch /usr/x 2>/dev/null'"
check "AC1 history has no secrets" bash -c "! docker history --no-trunc $IMAGE | grep -Eqi '(password|secret)=[^ ]+'"

# AC5: with the database gone, /up answers 500 at once (no details) and the container turns unhealthy within 60 s.
docker stop "$DB" >/dev/null
DOWN=$(date +%s)
UP_DOWN="$(get /up)"
check "AC5 /up answers 500 when the database is unreachable" grep -q '^HTTP/[0-9.]* 500' <<<"$UP_DOWN"
check "AC5 the failed /up leaks no connection details" bash -c "! grep -Eqi 'SQLSTATE|pgsql|PDO|Connection refused|could not translate|db:5432|$DB_PASS' <<<\"\$1\"" _ "$UP_DOWN"
unhealthy=1
while [ $(( $(date +%s) - DOWN )) -le 60 ]; do
    if [ "$(docker inspect -f '{{.State.Health.Status}}' "$APP")" = unhealthy ]; then unhealthy=0; break; fi
    sleep 2
done
if [ "$unhealthy" -eq 0 ]; then
    pass "AC5 the container is unhealthy $(( $(date +%s) - DOWN )) s after the database went away"
else
    fail "AC5 the container did not turn unhealthy within 60 s of the database going away"
fi

if [ "$FAILED" -eq 0 ]; then echo "ALL CHECKS PASSED"; else echo "SOME CHECKS FAILED"; fi
exit "$FAILED"
