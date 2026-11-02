#!/usr/bin/env bash
# Brings the PRODUCTION compose file up with throwaway secrets and checks it (TE-024 quality criteria).
#
#   scripts/ci/verify-production-stack.sh <image-repo:tag>     e.g. te024-test/traveleasy:v0.0.0-test
#
# Needs a built production image (docker build --target production) and Docker Compose. Everything it creates is named
# `te024verify-<pid>` (project, network, volumes) and removed at the end, also on failure; it never prunes and never
# touches `npm_shared`: a throwaway network stands in for it (a copy of compose.yaml points at that network).
# TE-014 calls it in CI against the freshly built image.
set -uo pipefail
cd "$(dirname "$0")/../.."

IMAGE="${1:?usage: verify-production-stack.sh <image:tag>}"
REPO="${IMAGE%:*}"
TAG="${IMAGE##*:}"
RUN="te024verify-$$"
NET="${RUN}_npm"
WORK="$(mktemp -d)"
# Git Bash on Windows hands native paths to docker.exe; on Linux both are the same.
NATIVE="$WORK"
if command -v cygpath >/dev/null 2>&1; then NATIVE="$(cygpath -m "$WORK")"; export MSYS_NO_PATHCONV=1; fi
PASS=0
FAIL=0

dc() { docker compose -p "$RUN" -f "$NATIVE/compose.yaml" --env-file deploy/portainer.env.example "$@"; }
pass() { PASS=$((PASS + 1)); echo "  ok   $1"; }
fail() { FAIL=$((FAIL + 1)); echo "  FAIL $1"; }
check() { local d="$1"; shift; if "$@" >/dev/null 2>&1; then pass "$d"; else fail "$d"; fi; }

cleanup() {
    dc down -v --remove-orphans >/dev/null 2>&1 || true
    docker network rm "$NET" >/dev/null 2>&1 || true
    rm -rf "$WORK"
}
trap cleanup EXIT

sed -e "s#ghcr.io/nimrodlobozar/traveleasy#${REPO}#" -e "s#name: npm_shared#name: ${NET}#" compose.yaml > "$WORK/compose.yaml"
docker network create "$NET" >/dev/null || { echo "cannot create the throwaway network"; exit 1; }
CIDR="$(docker network inspect "$NET" --format '{{(index .IPAM.Config 0).Subnet}}')"

export APP_IMAGE_TAG="$TAG" APP_URL="https://te024.test" TRUSTED_PROXIES="$CIDR"
export DB_SUPERUSER_PASSWORD="su-$(openssl rand -hex 12)" DB_PASSWORD="ap-$(openssl rand -hex 12)"
export APP_KEY="base64:$(openssl rand -base64 32)"

echo "== start (waits for healthy)"
dc up -d --wait --wait-timeout 240 || { dc ps -a; dc logs --tail 60; echo "stack did not become healthy"; exit 1; }
APP="${RUN}-app-1"; SSR="${RUN}-ssr-1"; DB="${RUN}-db-1"; INIT="${RUN}-db-init-1"

echo "== services and hardening"
check "db-init exited 0" test "$(docker inspect -f '{{.State.ExitCode}}' "$INIT")" = 0
for s in "$APP" "$SSR" "$DB"; do
    check "$s healthy" test "$(docker inspect -f '{{.State.Health.Status}}' "$s")" = healthy
    check "$s read-only, cap_drop ALL, no-new-privileges, no swap" bash -c \
        "docker inspect $s --format '{{.HostConfig.ReadonlyRootfs}} {{.HostConfig.CapDrop}} {{.HostConfig.SecurityOpt}} {{.HostConfig.Memory}} {{.HostConfig.MemorySwap}}' | grep -Eq '^true \[ALL\] \[no-new-privileges:true\] ([0-9]+) \1$'"
done
check "no container publishes a host port" bash -c "! docker ps --filter name=$RUN --format '{{.Ports}}' | grep -q -- '->'"
check "only app is on the npm network" bash -c "[ \"\$(docker network inspect $NET --format '{{len .Containers}}')\" = 1 ] && docker network inspect $NET --format '{{range .Containers}}{{.Name}}{{end}}' | grep -q '^$APP\$'"
check "app has the alias traveleasy-app" bash -c "docker inspect $APP --format '{{json .NetworkSettings.Networks}}' | grep -q traveleasy-app"

echo "== database roles"
psql_app() { docker exec -e PGPASSWORD="$DB_PASSWORD" "$DB" psql -h db -U traveleasy_app "$@"; }
check "app role is restricted" bash -c "[ \"\$(docker exec -e PGPASSWORD='$DB_SUPERUSER_PASSWORD' $DB psql -h db -U postgres -d postgres -Atc \"select rolsuper or rolcreatedb or rolcreaterole or rolreplication or rolbypassrls from pg_roles where rolname='traveleasy_app'\")\" = f ]"
check "app role cannot CREATE ROLE" bash -c "! docker exec -e PGPASSWORD='$DB_PASSWORD' $DB psql -h db -U traveleasy_app -d traveleasy -c 'create role evil'"
check "app role cannot DROP DATABASE" bash -c "! docker exec -e PGPASSWORD='$DB_PASSWORD' $DB psql -h db -U traveleasy_app -d postgres -c 'drop database traveleasy'"
check "app role migrated (tables exist and it can create and drop one)" bash -c "docker exec -e PGPASSWORD='$DB_PASSWORD' $DB psql -h db -U traveleasy_app -d traveleasy -c 'create table verify_t(i int); drop table verify_t' && [ \"\$(docker exec -e PGPASSWORD='$DB_PASSWORD' $DB psql -h db -U traveleasy_app -d traveleasy -Atc 'select count(*) from migrations')\" -gt 0 ]"
check "the superuser password is not in app or ssr" bash -c "! docker inspect $APP $SSR | grep -qF '$DB_SUPERUSER_PASSWORD'"

echo "== secrets never reach logs (db-init failing on purpose)"
SENTINEL="sentinel-$(openssl rand -hex 8)"
DB_USERNAME=postgres DB_PASSWORD="$SENTINEL" dc run --rm db-init >"$WORK/init-fail.log" 2>&1
check "db-init failed on purpose (exit != 0)" test "$?" -ne 0
check "the password is in neither the db-init output nor the db logs" bash -c "! grep -qF '$SENTINEL' '$WORK/init-fail.log' && ! docker logs $DB 2>&1 | grep -qF '$SENTINEL'"
check "no secret in any service log" bash -c "! (docker logs $DB; docker logs $INIT; docker logs $APP; docker logs $SSR) 2>&1 | grep -qF -e '$DB_PASSWORD' -e '$DB_SUPERUSER_PASSWORD' -e '${APP_KEY#base64:}'"

echo "== network: no egress for db and ssr"
check "ssr cannot reach the internet" bash -c "docker exec $SSR node -e \"fetch('https://example.com',{signal:AbortSignal.timeout(5000)}).then(()=>process.exit(1),()=>process.exit(0))\""
check "db cannot reach the internet" bash -c "! docker exec $DB wget -q -T 4 -O- http://1.1.1.1"

echo "== web behaviour through the proxy network"
cat > "$WORK/probe.php" <<'PHP'
<?php
$ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10,
    'header' => "Host: te024.test\r\nX-Forwarded-Proto: https\r\nX-Forwarded-For: 203.0.113.77\r\n"]]);
$body = (string) @file_get_contents('http://traveleasy-app:8080'.$argv[1], false, $ctx);
echo implode("\n", $http_response_header ?? ['no response']), "\n";
echo str_contains($body, 'data-server-rendered') ? "SSR=yes\n" : "SSR=no\n";
PHP
probe() { docker run --rm --network "$NET" -v "$NATIVE/probe.php:/probe.php:ro" "$IMAGE" php /probe.php "$1" 2>&1; }
probe / > "$WORK/home.txt"
check "home page: 200 and server-rendered" bash -c "grep -q '^HTTP/1.1 200' '$WORK/home.txt' && grep -q 'SSR=yes' '$WORK/home.txt'"
check "session cookie is Secure and HttpOnly" bash -c "grep -i '^Set-Cookie: .*session' '$WORK/home.txt' | grep -qi 'secure' && grep -i '^Set-Cookie: .*session' '$WORK/home.txt' | grep -qi 'httponly'"
check "HSTS, CSP and nosniff headers are present" bash -c "grep -qi '^Strict-Transport-Security' '$WORK/home.txt' && grep -qi '^Content-Security-Policy' '$WORK/home.txt' && grep -qi '^X-Content-Type-Options: nosniff' '$WORK/home.txt'"
probe /_inertia/devtools > "$WORK/devtools.txt"
check "the Inertia devtools URL is 404" grep -q '^HTTP/1.1 404' "$WORK/devtools.txt"
check "the client IP is not in the app log" bash -c "! docker logs $APP 2>&1 | grep -q 203.0.113.77"

echo "== ssr down: the app still serves"
docker stop "$SSR" >/dev/null
probe / > "$WORK/fallback.txt"
check "home page still answers 200 without ssr (client-side rendering)" bash -c "grep -q '^HTTP/1.1 200' '$WORK/fallback.txt' && grep -q 'SSR=no' '$WORK/fallback.txt'"
check "app stays healthy while ssr is down" test "$(docker inspect -f '{{.State.Health.Status}}' "$APP")" = healthy
docker start "$SSR" >/dev/null

echo "== a missing secret fails naming the variable"
for var in DB_SUPERUSER_PASSWORD DB_PASSWORD APP_KEY TRUSTED_PROXIES; do
    check "empty $var is refused and named" bash -c "env '$var=' docker compose -p $RUN -f '$NATIVE/compose.yaml' --env-file deploy/portainer.env.example config --quiet 2>&1 | grep -q '$var'"
done

echo
echo "passed: $PASS, failed: $FAIL"
[ "$FAIL" -eq 0 ]
