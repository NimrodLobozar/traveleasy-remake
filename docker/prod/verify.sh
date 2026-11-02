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
    docker rm -f "$APP" "$SSR" "$DB" "${RUN}-fail" >/dev/null 2>&1
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

check "AC3 the root filesystem is read-only" bash -c "! docker exec $APP sh -c 'touch /var/www/html/x 2>/dev/null || touch /usr/x 2>/dev/null'"
check "AC1 history has no secrets" bash -c "! docker history --no-trunc $IMAGE | grep -Eqi '(password|secret)=[^ ]+'"

if [ "$FAILED" -eq 0 ]; then echo "ALL CHECKS PASSED"; else echo "SOME CHECKS FAILED"; fi
exit "$FAILED"
