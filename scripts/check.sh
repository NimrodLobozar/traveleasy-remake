#!/usr/bin/env bash
# Quiet test/check runner for agents (and humans). Runs the usual Docker commands, writes the FULL log to
# storage/logs/check/, and prints only a summary, plus the failures when something fails.
# Usage:
#   bash scripts/check.sh php [pest args]     e.g. php --filter=BookingTest | php tests/Feature/Auth
#   bash scripts/check.sh front [vitest args] e.g. front resources/js/components/button.test.tsx
#   bash scripts/check.sh e2e [specs]         e.g. e2e base-components   (one e2e run at a time)
#   bash scripts/check.sh static              Pint, Larastan, generate, oxlint, oxfmt, tsc, i18n
#   bash scripts/check.sh all                 static + php (parallel) + front + e2e: the full gate
# Need more detail? grep the log file it names; don't rerun the suite unfiltered.
set -u
cd "$(dirname "$0")/.."
LOG_DIR=storage/logs/check
mkdir -p "$LOG_DIR"
MAX_FAIL_LINES=${MAX_FAIL_LINES:-60}
overall=0

run_step() { # name, command...
  local name=$1; shift
  local log="$LOG_DIR/$name-$(date +%Y%m%d-%H%M%S).log"
  local start=$SECONDS
  "$@" >"$log" 2>&1
  local code=$?
  local secs=$((SECONDS - start))
  # Strip ANSI colours so the summary is readable.
  sed -i 's/\x1b\[[0-9;]*[A-Za-z]//g' "$log" 2>/dev/null
  local summary
  summary=$(grep -E '^\s*(Tests:|Test Files|Duration|Assertions|[0-9]+ (passed|failed|skipped|flaky)|\[OK\]|Found [0-9]+ error|No errors)' "$log" | tail -n 6)
  if [ $code -eq 0 ]; then
    echo "PASS $name (${secs}s)"
    [ -n "$summary" ] && echo "$summary" | sed 's/^/  /'
  else
    overall=1
    echo "FAIL $name (exit $code, ${secs}s) - full log: $log"
    grep -nE '(FAIL|FAILED|Failed asserting|Error:|ERROR|error TS[0-9]+|✗|×|⨯|^\s+[0-9]+\) |Expected|Received|at .*:[0-9]+|  ✘|^\s+x [a-z-]+\(|,-\[)' "$log" \
      | head -n "$MAX_FAIL_LINES" | cut -c1-240 | sed 's/^/  /'
    echo "  --- last lines ---"
    tail -n 12 "$log" | cut -c1-240 | sed 's/^/  /'
  fi
}

cmd=${1:-}; [ $# -gt 0 ] && shift
case "$cmd" in
  php)    run_step php docker compose exec -T app php artisan test --compact "$@" ;;
  front)  run_step front docker compose exec -T vite npx vp test run "$@" ;;
  e2e)    run_step e2e docker compose run --rm e2e npx playwright test --config tests/e2e/playwright.config.ts --reporter=line "$@" ;;
  static)
    run_step pint docker compose exec -T app composer lint
    run_step larastan docker compose exec -T app composer analyse
    run_step front-static docker compose exec -T vite npm run check ;;
  all)
    run_step pint docker compose exec -T app composer lint
    run_step larastan docker compose exec -T app composer analyse
    run_step front-static docker compose exec -T vite npm run check
    run_step php docker compose exec -T app php artisan test --parallel --compact
    run_step front docker compose exec -T vite npx vp test run
    run_step e2e docker compose run --rm e2e npx playwright test --config tests/e2e/playwright.config.ts --reporter=line ;;
  *) sed -n '2,11p' "$0"; exit 2 ;;
esac
exit $overall
