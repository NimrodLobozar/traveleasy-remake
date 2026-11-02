#!/usr/bin/env bash
# Dev helper for the SSR check (TE-008). Run from the repo root in Git Bash, WSL or PowerShell (`bash scripts/dev-ssr.sh <cmd>`).
#
#   on     Build the client + SSR bundles, switch off the Vite hot file, start the `ssr` service.
#          `app` then serves the production-style build with server-side rendering (no HMR).
#   smoke  `on`, then the SSR smoke test twice: SSR up (rendered content) and SSR stopped (client-side fallback).
#   off    Stop `ssr` and restart `vite`, which writes public/hot again: back to normal dev with HMR.
#
# Why: while Vite runs, public/hot exists and Laravel skips SSR on purpose (AppServiceProvider). The hot
# file is the only thing that switches the modes, so the Vite container can keep running meanwhile.
set -euo pipefail
cd "$(dirname "$0")/.."

compose() { docker compose --profile ssr "$@"; }

wait_healthy() {
    for _ in $(seq 1 40); do
        if [ -z "$(compose ps --format '{{.Service}} {{.Health}}' | grep -v ' healthy$' || true)" ]; then return 0; fi
        sleep 3
    done
    compose ps
    echo "Services did not become healthy." >&2
    return 1
}

smoke_app() { compose exec -T app node scripts/ssr-smoke.mjs --url http://127.0.0.1:8000 "$@"; }

cmd_on() {
    compose exec -T vite npm run build
    compose exec -T vite rm -f public/hot
    # Recreates `app` only when its configuration changed (INERTIA_SSR_URL), and starts `ssr`.
    compose up -d app ssr
    # The SSR server loads the bundle at start, so a rebuild needs a restart.
    compose restart ssr
    wait_healthy
}

cmd_off() {
    compose stop ssr
    compose rm -f ssr
    compose restart vite
    wait_healthy
    echo "Dev mode again: public/hot is back and HMR works."
}

case "${1:-}" in
    on) cmd_on ;;
    off) cmd_off ;;
    smoke)
        cmd_on
        smoke_app --mode on
        compose stop ssr
        smoke_app --mode down
        compose start ssr
        wait_healthy
        ;;
    *) echo "Usage: bash scripts/dev-ssr.sh on|smoke|off" >&2; exit 2 ;;
esac
