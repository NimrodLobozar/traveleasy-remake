---
id: TE-004
title: Pest on PostgreSQL with arch tests that forbid the legacy anti-patterns
status: in-test
priority: must
epic: M0 Project scaffold and tooling
agents: [backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 0
---

## User story
As the developer, I want Pest to run against a separate PostgreSQL test database with architecture tests that fail on
the original's security mistakes, so that those mistakes can't come back unnoticed in any later story.

## Context
- `docs/plan.md` § 4 M0 scope (the list of Pest arch tests), § 6 Testing and Demo mode tracks; ADR-0005 enforcement
  (no `Mail` facade, `Mailable`, `MailMessage`, `mail` channel); ADR-0006 (only `App\Support\DemoMode` may read the
  demo flag).
- Legacy mistakes these rules prevent (`docs/legacy-analysis.md` § 3 Security): CSRF disabled for the chatbot
  endpoint, `withoutVerifying()` on the HTTP client, debugging leftovers.
- **Depends on:** TE-001 (Pest, code), TE-002 (PostgreSQL + `traveleasy_test`). **Can run in parallel with** TE-003.
- `App\Support\DemoMode` and `config/traveleasy.php` don't exist yet (M1). The demo-mode rule is written now so it
  holds from the first line of M1; it passes trivially until then and is proven with a fixture (AC9).
- Pest arch expectations only see namespaced classes. Files without a namespace (`config/`, `routes/`, `bootstrap/`)
  need a file-scan test; that's fine as long as it is a Pest test in the same suite.

## Acceptance criteria
- [ ] AC1: Pest is the test runner (starter tests converted if they weren't); `docker compose exec app php artisan test`
      and `docker compose exec app ./vendor/bin/pest` both run the full suite, including the arch tests, and pass.
- [ ] AC2 (PostgreSQL only): feature tests run against `traveleasy_test` on the `db` service, never SQLite.
- [ ] AC3 (dev DB guard): **built in TE-002 AC17** (rework round 1): forced `pgsql`/`traveleasy_test` connection and a
      guard that aborts before any migration unless the database is exactly `traveleasy_test`. This story doesn't add a
      second guard; it only checks that the TE-002 guard and its contract test still pass with Pest and the arch suite
      in place, and that `tests/Pest.php` / the base `TestCase` don't bypass it.
- [ ] AC3a (config-cache case without a real cache file; security Low from TE-002, owners qa-engineer +
      backend-developer): the config-cache case of the isolation test (TE-002 AC17) no longer writes
      `bootstrap/cache/config.php`. It points `APP_CONFIG_CACHE` at a temp file under `storage/framework/testing/` and
      deletes it afterwards, so a run that is killed mid-test can't leave a cache that the running dev server would
      load. After a full run, and after a run interrupted with Ctrl+C during that case, `bootstrap/cache/config.php`
      does not exist.
- [ ] AC4 (no debugging leftovers): an arch test fails if `dd`, `dump`, `ddd`, `ray`, `var_dump`, `print_r` or
      `die`/`exit` are used in `App`, `Database`, `config/` or `routes/`.
- [ ] AC5 (`env()` only in config): a test fails if `env(` is called anywhere outside `config/` (covers `app/`,
      `database/`, `routes/`, `bootstrap/` except `bootstrap/app.php` framework boilerplate if needed, and
      `resources/views`).
- [ ] AC6 (TLS verification): a test fails if `withoutVerifying(` or a Guzzle/HTTP-client option `'verify' => false`
      appears in `app/`, `config/`, `routes/` or `database/`.
- [ ] AC7 (CSRF): a test fails if any CSRF exception is configured: a non-empty `except` passed to
      `validateCsrfTokens()` in `bootstrap/app.php`, a non-empty `$except` on a CSRF middleware subclass, or
      `withoutMiddleware(` naming a CSRF middleware in `routes/` or `app/`.
- [ ] AC8 (no mail, ADR-0005): an arch test fails if code in `App` uses `Illuminate\Support\Facades\Mail`,
      `Illuminate\Mail\Mailable` (or extends it), `Illuminate\Notifications\Messages\MailMessage` or
      `Illuminate\Contracts\Mail\Mailer`; and a test fails if any notification declares a `toMail` method or returns
      `'mail'` from `via()`. A config test asserts `config('mail.default') === 'array'` even when `MAIL_MAILER=smtp`
      is set in the environment (TE-001 AC10).
- [ ] AC9 (demo flag): a test fails if `traveleasy.demo` is read through `config(`, `Config::` or `config()->` anywhere
      except `App\Support\DemoMode`, or if `env('DEMO_MODE'` appears outside `config/traveleasy.php`.
- [ ] AC10 (presets): Pest's `php` and `security` arch presets run over `App` and pass (or each excluded expectation is
      listed with a reason in the test file).
- [ ] AC11 (every rule proven): for **each** rule in AC4–AC10, the handoff shows the test failing against a temporary
      violating file (then removed), and the failure message names the rule and the offending file. Each rule's test
      has a comment linking its source (legacy § 3 item or ADR).
- [ ] AC12: the arch and file-scan tests live together (e.g. `tests/Arch/`) and run in the default suite, not behind a
      group or flag.
- [ ] AC13 (mail rule covers everything; added after the TE-001 review): the AC8 rule forbids the **whole** `Mail`
      facade, not just named mailers. That includes `Mail::build(...)`, `Mail::raw`, `Mail::send`, `Mail::to` and
      `Mail::mailer`. It also forbids any use of the `Illuminate\Mail\*` and `Symfony\Component\Mailer\*` namespaces
      in `App`, `Database`, `config/` and `routes/`. Each part is proven failing as in AC11, including a
      `Mail::build([...])` on-demand mailer and a direct `Symfony\Component\Mailer\Transport` use. (The runtime pin
      from TE-001 stays as a second line of defence.)
- [ ] AC14 (unused third-party stores; added after the TE-001 review): the framework-default `AWS_*` and `SQS_*` env
      reads, and the stores and connections that use them, are removed from `config/queue.php` and
      `config/cache.php`:
      - the `sqs` queue connection;
      - the `dynamodb` cache store;
      - any other store that would need cloud credentials, unless it is used.

      The database queue and cache drivers stay. A grep for `AWS_` and `SQS_` over `config/` returns nothing, and the
      suite still passes. (The backend-developer does this here because it shrinks the env surface the arch rules
      protect. It's small and has no runtime effect.)

## Quality criteria
- [ ] Tests: the suite is deterministic (two consecutive runs give the same result) and runs in under 60 seconds on the
      dev PC.
- [ ] Accessibility: not applicable (no UI).
- [ ] Security/privacy: `.env.example` has no `AWS_*` or `SQS_*` entries after AC14. The security-privacy-reviewer confirms the rule set matches `docs/plan.md` § 4 M0 and ADR-0005/0006
      and that the file scans can't be bypassed trivially (e.g. a fully qualified `\Illuminate\Support\Facades\Mail::`
      call or an aliased import is also caught).
- [ ] i18n: not applicable.
- [ ] Performance: see the 60-second budget above.
- [ ] Docs updated: the handoff lists the rules and commands for `docs/testing.md` (TE-006).
- [ ] Docker rebuild: only if `composer.json` changes (`docker compose up -d --build -V`).

## Out of scope
- The `DemoMode` service and `config/traveleasy.php` (M1); the "every controller action is authorized" check
  (M1, roles); coverage reporting and running Pest in CI (CI/CD epic); parallel testing.

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
