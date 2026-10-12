# Testing

How TravelEasy is tested: the layers, the rules every story follows, the safety nets around the databases, the
accessibility checks, and every command. Owner: `qa-engineer`. Setup, rebuilds, the containers and troubleshooting
are in [the development environment guide (`docs/deploy/dev.md`)](deploy/dev.md).

All commands run **inside the Docker dev containers** (`app` for PHP, `vite` for Node, `e2e` for the browser), so
results match on every machine. Nothing needs PHP, Composer or Node on Windows. Every command below works as-is in
PowerShell.

Numbers in this file were measured on the dev PC on 2026-10-12 (branch `feat/m0-tooling`).

## 1. Layers

| Layer | Tool | Runs in | Lives in | File names | Count today |
|---|---|---|---|---|---|
| Unit | Pest 5.3 (PHPUnit 13) | `app` | `tests/Unit/` | `PascalCaseTest.php` | 50 |
| Feature | Pest + Laravel HTTP testing | `app` | `tests/Feature/` (sub-folders per area, e.g. `Auth/`, `Settings/`) | `PascalCaseTest.php` | 68 |
| Arch | Pest arch expectations + our token scanner | `app` | `tests/Arch/` (rules in `tests/Support/`) | `PascalCaseTest.php` | 302 |
| Component | Vitest 4.1 (via vite-plus `vp test`), jsdom, Testing Library, axe-core 4.13 | `vite` | next to the component in `resources/js/`; harness in `resources/js/test/` | `*.test.tsx` / `*.test.ts` | 10 |
| e2e rules | Vitest (Node environment, no browser) | `vite` | `tests/e2e/` | `*.test.ts` | 5 |
| End-to-end | Playwright 1.64 (Chromium) + @axe-core/playwright 4.13 | `e2e` (one-shot) | `tests/e2e/` | `*.spec.ts` | 9 |

The three Pest suites are declared in `phpunit.xml` and all run by default; none hides behind a group or flag. The
Vitest projects (`components`, `e2e-rules`) are declared in `vite.config.ts` § `test.projects`.

### What goes where

| Put it in … | When you test … | Examples |
|---|---|---|
| **Unit** | a domain rule with no HTTP: pricing, VAT, offers, capacity/overbooking, status transitions, retention; a support class; a file/contract check that needs no app | `PhpFileTest`, `PlaywrightImageVersionSyncTest`, `DockerDevEnvironmentContractTest` |
| **Feature** | an endpoint or anything that needs the booted app and the database: controllers, policies, Form Requests, middleware, config at runtime | `Auth/RegistrationTest`, `CsrfRuntimeCoverageTest`, `ConfigDefaultsTest` |
| **Arch** | a rule about the **source code** itself (what may never be written) | `NoMailTest`, `CsrfTest`, `TestDatabaseGuardContractTest` |
| **Component** | a React component's behaviour (keyboard, focus, states, labels) and its axe check in jsdom | `resources/js/test/harness.test.tsx` |
| **e2e rules** | static checks on the browser-test code | `tests/e2e/allow-usage.test.ts` |
| **End-to-end** | a user journey in a real browser, with axe on every page it visits | `tests/e2e/smoke.spec.ts` |

Notes:
- `tests/Pest.php` binds `Tests\TestCase` **and `RefreshDatabase`** to `tests/Feature` only. A test in another folder
  that boots the app must say so itself with `pest()->extend(TestCase::class);` (see
  `tests/Arch/CloudCredentialsRuntimeTest.php`). The guard contract (§ 4.3) fails if it touches the database without
  it.
- Name tests after the criterion they prove: start the description with the AC id (`'AC7 every state-changing route
  runs the CSRF middleware (runtime)'`) and put the story and source (legacy item or ADR) in the file's header
  comment.
- Test behaviour through public interfaces (HTTP, the rendered UI, a service's public methods), not internals.

## 2. Rules for every story

From `docs/plan.md` § 2 (story DoD) and § 6 (Testing track).

1. **Every acceptance and quality criterion maps to at least one test.** The qa-engineer writes the missing ones.
2. **A feature test per endpoint**, with at least these cases:

   | Case | Expect |
   |---|---|
   | Happy path | the right status, data and side effects |
   | Validation error | 422 / redirect back with errors on the right fields |
   | Unauthenticated | redirect to login (web) or 401 |
   | **Unauthorized** | 403 or 404: customer A can't reach customer B's records; a customer can't reach staff routes |
   | Not found | 404 |

3. **An authorization test for every new route and record type** (deny by default; IDOR test per resource).
4. **Demo mode on and off** (from M1, ADR-0006): anything demo mode changes is tested with `DEMO_MODE` on **and** off.
5. **Unit tests for domain rules**: pricing, VAT (one config value), offers, capacity/overbooking, status
   transitions, retention.
6. **An e2e journey per story journey**, with `expectNoA11yViolations` on every page it visits (§ 5).
7. **Deterministic**, so two runs give the same result and order never matters:
   - frozen clock (`$this->freezeTime()` / `travelTo()`, `Carbon::setTestNow()`);
   - seeded factories; no reliance on row order without `orderBy`;
   - no real external calls: fake them (`Http::fake()` with `Http::preventStrayRequests()`, the fake payment
     provider adapter);
   - there is **no mail to fake**: the app sends no e-mail at all (ADR-0005), and an arch rule forbids it (§ 3);
   - Playwright runs with `locale: 'nl-NL'` and `timezoneId: 'Europe/Amsterdam'` (`tests/e2e/playwright.config.ts`);
   - Vitest restores mocks, stubbed env and stubbed globals after every test (`vite.config.ts`).
8. **A bug fix starts with a failing test.** Write the test that reproduces the bug, see it fail, then fix.
9. **Never weaken or delete an assertion to get green.** If a criterion looks wrong, the story goes back to the
   task-writer.
10. **Obviously fake test data only** (`te002_fake_user`, `*.example.com`), never real personal data.
11. Run the **whole** suite plus lint and types before a verdict (§ 7), not just the new tests.

### e2e data rule (auth and booking journeys, from M1)

From the TE-005 security review (L5 and the e2e data rule on the board):
- **Fake users only**, created by the test or a demo seeder. Never real accounts, never real 2FA secrets.
- **Reports are sensitive, even after green runs.** `playwright-report/` holds page HTML, URLs and the needs-review
  attachments; traces and screenshots in `test-results/` can hold cookies and typed passwords. Both are git-ignored;
  never share them, never upload them to an online trace viewer or a public CI artifact.
- **`storageState` files** (saved logins) go only under `test-results/` or `/tmp` inside the container, never in
  the repo tree elsewhere.

## 3. Arch rules (TE-004)

Each rule is a Pest test in `tests/Arch/` and fails with a message that names the rule and the offending
`file:line`. Most have two layers: a Pest arch expectation over the namespaces `App`, `Database\Factories` and
`Database\Seeders`, and a **file scan**, because Pest only sees namespaced classes and `config/`, `routes/`,
`bootstrap/` and Blade views have none.

**Scanner:** `tests/Support/SourceFile.php` (tokeniser: resolves imports, aliases, fully qualified names, real-time
facades, string class names unescaped exactly as PHP does, heredoc/nowdoc, call arguments by position **and** by
name), `tests/Support/SourceTree.php` (loads files; compiles Blade views to PHP first; always skips
`bootstrap/cache/`) and `tests/Support/ArchRules.php` (the rules). Comments never trigger a rule.

**Proof that each rule bites:** `tests/Arch/ArchRulesTest.php` (violating and clean snippets per rule, plus the
string-unescaping cases) and `tests/Arch/ArchRulesQaBypassTest.php` (QA's 36 plausible non-evasive variants). Add a
case there whenever you find a form a rule misses.

| Rule (test file) | Fails when … | Scope | Prevents |
|---|---|---|---|
| **AC4 No debugging leftovers** (`DebuggingLeftoversTest.php`) | `dd`, `ddd`, `dump`, `ray`, `var_dump`, `var_export`, `print_r`, `printf`, `vprintf`, `exit`, `die` (plus `trap`, `ds`, `debug_zval_dump`, `debug_print_backtrace`, `xdebug_*`, `phpinfo`) in every spelling: bare, fully qualified (`\exit(1)`), construct (`exit;`); the `->dd()`/`->dump()`/`->ddRawSql()` methods; `echo`/`print` outside Blade | `app`, `bootstrap`, `config`, `database`, `routes`, `resources/views` | legacy § 3: debugging leftovers and fake-error switches shipped to production |
| **AC5 `env()` only in config** (`EnvOnlyInConfigTest.php`) | `env()`, `getenv()`, `putenv()`, `Env::get()`, `$_ENV`, `$_SERVER` outside `config/` | same roots except `config/` | `docs/plan.md` § 4 M0; `config:cache` must work; secrets read once, in one reviewed place |
| **AC6 TLS verification stays on** (`TlsVerificationTest.php`) | `withoutVerifying()`; `verify`, `verify_peer`, `verify_peer_name`, `verify_host`, `allow_self_signed` disabled through `=>`, `=`, `??=`, `Arr::set`, `data_set`, `stream_context_set_option`, `stream_context_create`; `RequestOptions::VERIFY`; cURL `CURLOPT_SSL_VERIFY*`. Allowed: `true`, and for `verify` a **literal** CA-bundle path | `app`, `bootstrap`, `config`, `database`, `routes` | legacy § 3 Security: the HTTP client used `withoutVerifying()` |
| **AC7 No CSRF exceptions** (`CsrfTest.php` + runtime `tests/Feature/CsrfRuntimeCoverageTest.php`) | a non-empty `except` on `validateCsrfTokens()` / `preventRequestForgery()`; any `App` class extending the CSRF middleware, directly or indirectly; a redefined `web` group; a group with `StartSession` but no CSRF (`group`, `appendToGroup`, `prependToGroup`, `api(append:/prepend:)`, positional or named arguments); `withoutMiddleware('web'/'csrf'/CSRF class)`; any call on the CSRF class (`::except()`, `::useOriginOnly()`, …). Runtime: every state-changing route runs the CSRF middleware with no excluded paths | `app`, `bootstrap`, `config`, `database`, `routes` | legacy § 3 Security: CSRF disabled for the chatbot endpoint |
| **AC8/AC13 No outgoing e-mail** (`NoMailTest.php`) | the `Mail` facade (all of it), `Illuminate\Mail\*`, `Illuminate\Contracts\Mail\*`, `MailMessage`, `MailChannel`, `Illuminate\Auth\Notifications`, `MustVerifyEmail`, `Symfony\Component\Mailer\*`, PHP `mail()`; the mailer from the container (`app('mailer')`, `'mail.manager'`); `toMail()` / `routeNotificationForMail()`; the `'mail'` channel; `sendResetLink` and friends | Pest arch over the namespaces + file scan over all PHP roots | ADR-0005 (no outgoing e-mail). Runtime pin: `config('mail.default') === 'array'` (`ScaffoldContractTest`, `ConfigDefaultsTest`) |
| **AC9 Demo flag in one place** (`DemoModeFlagTest.php`) | `traveleasy.demo` (or the whole `traveleasy` array) read anywhere except `app/Support/DemoMode.php`, in any form (`config()`, `Config::`, named or later positional arguments, `Arr::get(config()->all(), …)`); `DEMO_MODE` read outside `config/traveleasy.php` | all PHP roots and views | ADR-0006 (demo mode). Passes trivially until M1 creates the files |
| **AC10 Pest presets** (`PresetsTest.php`) | Pest's `php` and `security` presets (e.g. `eval`, `md5`, `shell_exec`, `unserialize`); none excluded | `App`, `Database\Factories`, `Database\Seeders` | legacy § 3 (insecure functions, debugging leftovers) |
| **AC14 No cloud stores** (`CloudCredentialsTest.php`, `CloudCredentialsRuntimeTest.php`) | `AWS_*` / `SQS_*` in `config/` or `.env.example`; `sqs` or `dynamodb` back in config; at runtime a non-local queue/cache/filesystem default; `aws/aws-sdk-php` in `composer.lock` | `config/`, `.env.example`, runtime | TE-001 security review: shrink the env surface; secrets only from env |
| **AC3 Guard contract** (`TestDatabaseGuardContractTest.php`) | see § 4.3 | `tests/` | TE-002 AC17: tests can never wipe the dev database |

## 4. Databases and safety

### 4.1 PostgreSQL only

- Tests run on **PostgreSQL**, database **`traveleasy_test`** on the `db` service (same server as the dev DB
  `traveleasy`). **Never SQLite**: the guard rejects any driver other than `pgsql`, and the isolation test checks that
  no SQLite file appears.
- `traveleasy_test` is created only when the DB volume is first initialised
  (`docker/postgres/initdb/10-create-test-database.sh`). On an older volume, create it once with the command in
  [`dev.md` § Test database](deploy/dev.md#test-database).
- Feature tests use `RefreshDatabase` (bound in `tests/Pest.php`).

### 4.2 The guard (TE-002 AC17)

`tests/TestCase.php::createApplication()` and `tests/Support/TestDatabase.php` pin every run to the test database in
three layers, **before** `RefreshDatabase` migrates anything:

| Step | What it does |
|---|---|
| `forceEnvironment()` | Before boot: sets `DB_CONNECTION=pgsql`, `DB_DATABASE=traveleasy_test` in `$_SERVER`, `$_ENV` and `putenv`; removes `DB_URL`; points `APP_CONFIG_CACHE` at a path that can't exist (`/dev/null/…`), so a cached config is ignored. `phpunit.xml` also forces these with `force="true"`. |
| `forceConfiguration()` | After boot: sets `database.default` and the pgsql connection's database/url again and purges the connection. |
| `checkApplication()` | The guard. Refuses when the config-cache path isn't the forced one, when the configured connection isn't `pgsql`/`traveleasy_test`, or when `select current_database()` on the server (once per process) isn't exactly `traveleasy_test`. |
| `checkParallelWorker()` | Parallel runs only (a `ParallelTesting::setUpTestCase` callback, after Laravel's per-worker switch): allows only `traveleasy_test` or exactly this worker's **`traveleasy_test_test_N`** (`N` = positive integer token), again verified with `current_database()`. |

On a violation the whole run stops with `exit(1)` and `Refusing to run tests: <reason>`. The dev database
`traveleasy` never matches either rule. Proof: `tests/Unit/TestDatabaseIsolationTest.php` runs nested Pest processes
with conflicting env (`DB_DATABASE=traveleasy`, `DB_URL`, sqlite, a cached dev config) and checks that the dev
database's tables, OIDs and row counts are unchanged.

### 4.3 The guard contract (TE-004 AC3)

`tests/Arch/TestDatabaseGuardContractTest.php` makes sure no test can get around the guard:

- `tests/Pest.php` binds only `Tests\TestCase` (with `RefreshDatabase`) and only to `Feature`.
- Every test that references `Illuminate\Foundation\Testing`, `Illuminate\Database`, `DB`, `Schema` or `Artisan`
  outside `tests/Feature` binds `Tests\TestCase` itself.
- No test uses `Illuminate\Foundation\Testing\TestCase` or Orchestra Testbench, overrides `createApplication()`, or
  calls `forceEnvironment()` / `forceConfiguration()` / `setUpTestCase()`.
- In test files **outside `tests/TestCase.php` and `tests/Support/`**, these ways to boot an app without the guard
  fail:
  - the classes `Illuminate\Contracts\Console\Kernel`, `Illuminate\Contracts\Http\Kernel`,
    `Illuminate\Foundation\Application`, `Illuminate\Foundation\Bootstrap\*`,
    `Illuminate\Foundation\Configuration\ApplicationBuilder`, `Illuminate\Foundation\Console\Kernel`,
    `Illuminate\Foundation\Http\Kernel`;
  - the calls `->bootstrapWith(`, `->handleCommand(`, `->handleRequest(`;
  - every `require` / `require_once` / `include` / `include_once`, analysed as a **whole expression** (with or
    without parentheses), unless it is made only of string literals without interpolation, `__DIR__`, `.` and
    parentheses. Variables, function calls (`base_path($f)`, `dirname(__DIR__, 2).$f`) and interpolated strings
    fail. Any piece, or the joined value of the pieces (`'…/bootstrap/'.'app.php'`), that contains
    `bootstrap/app.php` fails.
- `phpunit.xml` keeps all three suites and the forced `pgsql` / `traveleasy_test` env.

**Need to read a PHP data file in a test?** Use `Tests\Support\PhpFile::returnValue($path)`. It loads only existing
`.php` files below `config/` and `storage/framework/testing/` (after `realpath`, so symlinks and `..` can't escape)
and refuses everything else, including `bootstrap/app.php`. **Need the HTTP kernel of the current app?** Use
`Tests\Support\HttpKernel`.

### 4.4 Config cache in tests (TE-004 AC3a)

The isolation test's config-cache case writes its cached dev config to a temp file
`storage/framework/testing/te002-config-cache-*.php` (git-ignored) via `APP_CONFIG_CACHE`, deletes it in `finally`,
and removes leftovers of a killed run at the start of the next one. It never writes `bootstrap/cache/config.php`, so
an interrupted run can't leave a cache the running dev server would load.

### 4.5 Parallel runs

- `composer test:parallel` (= `php artisan test --parallel`) runs 16 processes on the dev PC. Each worker gets its
  own database `traveleasy_test_test_N`, created on first use and kept for the next run.
- **Before `--recreate-databases` or `--drop-databases`, run `php artisan config:clear`** (security F4, TE-003). The
  `artisan test` parent process reads the cached config; clearing it makes sure the databases Laravel creates and
  drops are derived from the test settings, not from a stale cache:

  ```
  docker compose exec app php artisan config:clear
  docker compose exec app php artisan test --parallel --recreate-databases
  ```

- **paratest is pinned to exactly `7.26.0`** in `composer.json` (security L1, TE-003). Why: Pest 5.3.1 constructs
  paratest's `WrapperWorker` with the 7.26.0 signature; 7.26.1 changed it (hardening fix PR #1148, worker files in a
  private per-run directory), which crashes `--parallel` with `ArgumentCountError`. Pest itself requires
  `^7.26.0`, so without the pin Composer would pick 7.26.1. **Move back to `^7.26.1` as soon as a Pest release
  supports it**, and re-run `composer test:parallel`.
- Because of the missing hardening fix: **never run `--parallel` on a shared multi-user host.** It's fine in our
  single-user dev container.
- Serial and parallel runs give the same result (420/420).

## 5. Accessibility checks

axe catches only **part** of WCAG 2.2 AA: many success criteria (meaningful alt text, focus order, error messages,
zoom and reflow, …) need a human.
Green axe is a floor, not a verdict: the `accessibility-auditor` still reviews every UI story by hand (keyboard path,
focus, screen-reader output, zoom/reflow, content).

### 5.1 One rule set, one report format

`resources/js/test/axe-config.ts` is shared by Vitest and Playwright:
- **Tags:** `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa` (`WCAG_AA_TAGS`; there is no `wcag22a` tag in axe).
  Best-practice and AAA rules are not part of the verdict.
- **Report:** per result the rule id, impact, help text and help URL; per node the target selectors, axe's
  `failureSummary` (`summary:`) and an `html:` snippet cut to 200 characters.

### 5.2 Component tests (Vitest, jsdom)

```ts
const { container } = render(<MyComponent />);
await expect(container).toHaveNoA11yViolations();
```

The matcher (`resources/js/test/axe.ts`, registered in `setup.ts`) runs axe-core on the element. **What it does not
check**, because jsdom has no layout engine and Vitest loads no CSS:
- **Disabled rules** (`JSDOM_DISABLED_RULES`): `color-contrast`, `link-in-text-block`, `target-size`,
  `scrollable-region-focusable`. In jsdom they give false passes or never apply.
- **Anything that depends on rendering:** visibility through CSS classes (Tailwind `hidden`, `sr-only` aren't
  applied), size, position, overlap, reflow, zoom, text spacing, focus visibility, focus obscured by other content.

Those are checked in Playwright (real Chromium) and by the auditor. The design-system epic adds token-pair contrast
tests and Playwright target-size checks.

**Incomplete ("needs review") results** are never dropped:
- For `aria-hidden-focus` and `aria-valid-attr-value` (`JSDOM_FAIL_ON_INCOMPLETE`; they can be judged without
  layout) an incomplete result **fails** the matcher, like a violation.
- Every other incomplete rule id is listed: in the failure message when the matcher fails, otherwise as a
  `console.warn` "Needs manual review (axe incomplete in jsdom): …" note in the test output.

### 5.3 Browser tests (Playwright)

```ts
import { expectNoA11yViolations } from './axe';
import { placeholderAllowList } from './a11y-allow-list';

await page.goto('/login');
await expectNoA11yViolations(page, { allow: placeholderAllowList });
```

`tests/e2e/axe.ts` runs @axe-core/playwright with the shared tags and throws on any violation not on the allow-list,
on an invalid or duplicate allow entry, and on an allow entry for this page that no longer matches a violation.
`axe-helper.spec.ts` proves the helper against static fixtures (`tests/e2e/fixtures/`), so a broken axe setup can't
pass silently.

**Needs-review attachment.** Incomplete results don't fail the test. They are attached to it as
**`a11y-needs-review.txt`** (rule id, page URL, selectors, axe's summary), visible in the HTML report and stored in
`test-results/e2e/`. **The accessibility-auditor reviews these attachments in every UI story.**

Wait for React before scanning: the Inertia root `#app` is empty in the server HTML (see `smoke.spec.ts`).

### 5.4 The allow-list (starter-kit placeholders only)

- **One central list.** `tests/e2e/a11y-allow-list.ts` (`placeholderAllowList`) is the **only** way to allow a
  violation. `tests/e2e/allow-usage.test.ts` (Vitest, `e2e-rules` project) fails if any file under `tests/e2e/`
  passes `allow:` with anything else (inline arrays, other lists, `{ allow }` shorthand, non-literal options). The
  single exception is `axe-helper.spec.ts`, which proves the mechanism.
- **One entry per rule id and page**, only for starter-kit placeholder pages.
- **The reason names an epic** from `REMOVAL_EPICS` (`'App shell' | 'Design system'`, in `tests/e2e/a11y-allow.ts`):
  `'starter-kit placeholder, remove in App shell'`. The union type rejects anything else at compile time;
  `assertValidAllowEntries()` rejects an empty or unknown epic, a blank rule or page at runtime.
- **No stale entries.** `tests/e2e/a11y-allow-list.spec.ts` visits every page on the list and fails if the page is
  gone (not 200) or no longer has the allowed violation. The helper also fails on such an entry in any spec.
- **It must end empty in the App shell epic** (`expect(placeholderAllowList).toHaveLength(0)`, after which the
  `allow` option is removed from the helper). No entry may outlive that epic, whichever epic it names.
- **Today:** one entry, `/` `color-contrast` (kit welcome page, grey text below 4.5:1), "remove in App shell".
- Known gap (board, "Follow-ups for the frontend harness"): the usage scan misses computed keys (`['allow']`) and
  `.js`/`.mjs` files. An entry also hides every node of that rule on that page (board, App shell notes).

## 6. Browser test runs (Playwright)

- **The command:** `docker compose run --rm e2e` (no `--profile` needed for `run`; `docker compose --profile e2e run
  --rm e2e` is equivalent). It runs `npm run test:e2e` = `playwright test --config tests/e2e/playwright.config.ts`
  in Chromium, waits for healthy `app` and `vite`, and removes its container afterwards.
- **Custom runs** replace the command. Always pass `--config tests/e2e/playwright.config.ts`; without it Playwright
  falls back to defaults and fails with `EROFS … rmdir '/var/www/html/test-results'`.

  | Want | Command |
  |---|---|
  | One file | `docker compose run --rm e2e npx playwright test --config tests/e2e/playwright.config.ts smoke` |
  | Tests by title | `docker compose run --rm e2e npx playwright test --config tests/e2e/playwright.config.ts -g "login page"` |
  | Keep artefacts apart from the last run | add `--output /tmp/e2e-smoke` (inside the container; gone afterwards) |

  In **Git Bash**, prefix commands that pass `/tmp/…` with `MSYS_NO_PATHCONV=1`, or Git Bash rewrites the path to
  `C:/…` (`ENOENT … mkdir '/var/www/html/C:'`). PowerShell needs nothing.
- **Reports** (bind-mounted, git-ignored, open on Windows):
  - HTML report: **`playwright-report/html/index.html`** (always, including the needs-review attachments);
  - traces, screenshots, `error-context.md` and attachments: **`test-results/e2e/`** (on failure, plus
    attachments on green runs).
- **One e2e run at a time.** Every run shares `vite`'s network namespace (the global setup listens on
  `127.0.0.1:8000` to forward to `app`) and the same report folders, so a second concurrent run fails with
  `EADDRINUSE 127.0.0.1:8000`. `--output /tmp/…` only separates the `test-results` artefacts; it does **not** make
  concurrent runs possible (the HTML report path is fixed by `PLAYWRIGHT_HTML_OUTPUT_DIR` too). Agents and humans
  take turns.
- **Version sync.** The image `mcr.microsoft.com/playwright:v1.64.0-noble` must equal `@playwright/test` (pinned
  exactly, `1.64.0`). `tests/Unit/PlaywrightImageVersionSyncTest.php` fails while they differ. Upgrade steps:
  [`dev.md` § Browser tests](deploy/dev.md#browser-tests-playwright--axe).
- If `vite` is unhealthy (e.g. right after a `vite.config.ts` change), the run stops with
  `dependency failed to start: container traveleasy-remake-vite-1 is unhealthy`. Wait, or
  `docker compose restart vite`, then run again.

## 7. Commands

Run from the repo root. `-T` is only needed when piping output (scripts, agents); interactive use works without it.

### PHP (`app` container)

| Command | Runs | Measured |
|---|---|---|
| `docker compose exec app composer lint` | Pint check mode with diffs: `pint --test -v` (+ `bootstrap config public` with `pint.no-strict-types.json`) | 5 s |
| `docker compose exec app composer format` | Pint, writes fixes (same two configs) | |
| `docker compose exec app composer analyse` | Larastan/PHPStan **level max**, no baseline, over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, `tests/` | 5 s |
| `docker compose exec app composer test` | `php artisan test`: all three Pest suites, serial | 420 tests, 51 s |
| `docker compose exec app composer test:parallel` | `php artisan test --parallel` (16 processes) | 14–22 s |
| `docker compose exec app composer check` | `lint`, then `analyse`, then `test`; stops at the first failure | 40 s |
| `docker compose exec app ./vendor/bin/pest` | the same suite through Pest directly | |
| `docker compose exec app ./vendor/bin/pest --testsuite=Arch` | one suite (`Unit`, `Feature`, `Arch`) | |
| `docker compose exec app php artisan test --filter=Csrf` | tests whose name matches | |

### Frontend (`vite` container)

| Command | Runs | Measured |
|---|---|---|
| `docker compose exec vite npm run check` | `generate`, then `lint`, `format:check`, `types:check`; stops at the first failure. **Doesn't run Vitest.** | 40 s |
| `docker compose exec vite npm run check:fix` | `generate`, then `vp check --fix` (lint fixes + formatting) | |
| `docker compose exec vite npm run lint` | `vp lint`: oxlint, type-aware, 238 rules, warnings fail | 13 s |
| `docker compose exec vite npm run lint:fix` | `vp lint --fix` | |
| `docker compose exec vite npm run format` | `vp fmt`: oxfmt **writes** every file in scope (Tailwind class sorting) | 3 s |
| `docker compose exec vite npm run format:check` | `vp fmt --check` | 3 s |
| `docker compose exec vite npm run types` | `generate` (Wayfinder + TS types), then `types:check` | 25 s |
| `docker compose exec vite npm run types:check` | `tsc --noEmit` for the app and for `tests/e2e` | 8 s |
| `docker compose exec vite npm run test` | `vp test run`: Vitest once, projects `components` + `e2e-rules` | 15 tests, 2.5 s |
| `docker compose exec vite npm run test:watch` | `vp test watch` (polls in the container); quit with `q` | |
| `docker compose exec vite npx vp fmt resources/js/pages/welcome.tsx` | format only the given paths | |

### Browser

| Command | Runs | Measured |
|---|---|---|
| `docker compose run --rm e2e` | the Playwright suite in Chromium (§ 6) | 9 tests, 8 s |

### Formatter output: files, not always lines

- Linters, static analysers and type checkers (oxlint, Larastan, `tsc`) report **file and line**.
- Formatters report the **file**: `composer lint` runs Pint with `-v`, so it also prints `@@` diff hunks with line
  numbers; oxfmt (`npm run format:check`) has no line or diff option and names the file only. Run
  `npm run format` (or `npx vp fmt <path>`) and look at `git diff`.

### Frontend lint: oxlint instead of ESLint, oxfmt instead of Prettier

TE-003 built on vite-plus instead of adding ESLint and Prettier (eslint-plugin-react and jsx-a11y only supported the
deprecated ESLint 9):
- **`lint.rules.ts`** lists every rule explicitly under its oxlint name: typescript-eslint `strict-type-checked`,
  react `recommended` + `jsx-runtime`, react-hooks `recommended`, jsx-a11y `recommended`, plus `no-console` and
  `typescript/no-explicit-any` as errors, with `denyWarnings`. Oxlint refuses to start on an unknown rule, so the list
  doubles as proof that each rule exists and is on.
- **Known gaps** (no oxlint implementation yet; accepted in TE-003): `typescript/no-generated-empty-object-type`,
  `typescript/no-unsafe-enum-assignment`, `react/no-deprecated`, `react/prop-types`, `react/jsx-uses-vars`,
  `react-hooks/config`, `react-hooks/gating`. Reasons are in the file's header comment.
- Formatting rules are never enabled in the linter, so linter and formatter can't fight.
- Ignored by both: the generated folders `resources/js/actions`, `routes`, `wayfinder`, `types/generated`.
  The formatter also skips `docs/`, `.claude/` and root `*.md`.
- Story titles and `docs/plan.md` still say "ESLint, Prettier"; read that as oxlint and oxfmt.

### Manual check: Vite advisories

`vite` in `package.json` is an npm alias for `@voidzero-dev/vite-plus-core@0.3.3`, so **`npm audit` can't see Vite
security advisories** (TE-003 AC13). On every dependency update:
1. `docker compose exec vite npx vp --version` shows the bundled Vite (today `vite v8.3.0`, vite-plus `v0.3.3`).
2. Read the vite-plus / vite-plus-core release notes and the
   [Vite security advisories](https://github.com/vitejs/vite/security/advisories) for that version.
3. Plan: move to vite-plus 1.x with a plain `vite` dependency when the Laravel React starter kit does, then drop this
   check (the note also sits in `package.json` under `"//"`).

## 8. Planned CI gates

**Not wired yet** (CI/CD epic). From `docs/plan.md` § 2, milestone DoD row 2, CI on `main` must be green on:

- [ ] Pint (`composer lint`)
- [ ] Larastan (`composer analyse`)
- [ ] ESLint → in our stack oxlint (`npm run lint`)
- [ ] Prettier → in our stack oxfmt (`npm run format:check`)
- [ ] `tsc --noEmit` (`npm run types`)
- [ ] Pest (`composer test`)
- [ ] Vitest (`npm run test`)
- [ ] Playwright + axe, **against the SSR build**
- [ ] `composer audit`
- [ ] `npm audit` (plus the manual Vite advisory check above)
- [ ] Trivy image scan (no unaccepted High/Critical)

Also in the M0 CI scope (`docs/plan.md` § 4): client + SSR build, `php artisan optimize` (proves `route:cache`
works), an SSR smoke test, the `linux/amd64` image build and push to GHCR. CI blocks the image publish when any gate
fails. Notes for that epic are on the board ("Notes for the CI/CD epic").

## 9. Known limits of the arch scans

The scanner reads source tokens; it doesn't run code. These forms are **not** caught by the file scans. Each says how
it is covered instead and what a later backend hardening pass should do. Add a failing case to
`ArchRulesQaBypassTest.php` when one gets fixed.

| # | Limit | Covered by | Recommendation |
|---|---|---|---|
| 1 | **Names built at runtime**: string concatenation, variables holding class or function names, `call_user_func($name)` | Code review; runtime tests for the high-risk rules (mail pinned to `array`, `CsrfRuntimeCoverageTest`, the DB guard itself) | Accepted: deliberate evasion, not accidental use |
| 2 | **`fprintf`** isn't in the debug list (`printf`/`vprintf` are) | Code review | Add `fprintf` (and consider `vfprintf`) to `DEBUG_FUNCTIONS` |
| 3 | **A closure inside an allowed CSRF range** (the middleware list of a group, where the CSRF class may appear as a class name) runs at request time and can't be judged by the scan | `CsrfRuntimeCoverageTest` (every state-changing route runs CSRF, no excluded paths); review | Flag closures inside group middleware lists |
| 4 | **`Route::group(['excluded_middleware' => …])`** | `CsrfRuntimeCoverageTest` at runtime | Flag `excluded_middleware` naming CSRF in the scan |
| 5 | **`'verify' => storage_path(...)`** (a computed CA path) **fails by design**: only a literal CA-path string is allowed | Deliberate false positive (TE-004 AC6 e) | Accepted. Use a literal path, or ask for a reviewed exception |
| 6 | **CSRF (L-e):** `call_user_func([PreventRequestForgery::class, 'except'], …)`, and a middleware group whose name isn't a literal | `CsrfRuntimeCoverageTest` at runtime | Flag callable arrays on the CSRF class; flag non-literal group names |
| 7 | **TLS (L-f):** `Config::set` / `config()->set` / `config([...])` with a (dotted) TLS key, where the key is argument 0; `Arr::add`; `stream_context_set_option` with PHP's real named parameters (`option_name:`); Symfony `HttpOptions->verifyPeer(false)` | Code review; the security reviewer checks every external call | Extend the TLS rule to these forms |
| 8 | **Global session middleware:** `$middleware->append(StartSession::class)` / `prepend(...)` at global level | `CsrfRuntimeCoverageTest` at runtime | Treat a global `StartSession` like a group without CSRF |
| 9 | **Spawned processes:** a test that starts `Process(['php', 'artisan', …])` (or similar) boots the app outside the guard. Note: `tests/Unit/TestDatabaseIsolationTest.php` does this on purpose (its nested runs go through Pest, so through the guard) | Review | Flag process spawning in test files outside `tests/Support/` (move the isolation helpers there, or exempt that file explicitly) |
| 10 | **Path normalisation (L-h):** the joined-path check for `bootstrap/app.php` doesn't normalise `//`, `/./` or `x/../` | Review; `PhpFile` itself resolves with `realpath` | Cheap: normalise the joined path before matching, or forbid `require`/`include` in test files outside `tests/Support/` entirely |
| 11 | **More guard forms:** `Filesystem->getRequire()` / `File::getRequire()` and `$app->handle($request)` aren't in the AC3 list | Review | Add them to the guard contract |
| 12 | **Blade component bound attributes** (`<x-foo :bar="env('X')">`) aren't compiled by the scanner, so they aren't scanned | Review | Compile them with a booted Blade compiler in the scan |

## 10. Related docs

- [Development environment (`docs/deploy/dev.md`)](deploy/dev.md): setup, daily commands, rebuilds, test database,
  troubleshooting.
- `docs/plan.md` § 2 (definitions of done) and § 6 (cross-cutting tracks).
- `docs/legacy-analysis.md` § 3: the mistakes the arch rules prevent.
- ADR-0005 (no outgoing e-mail), ADR-0006 (demo mode).
- Stories TE-002 to TE-005 (`docs/stories/`): the history behind every rule here.
