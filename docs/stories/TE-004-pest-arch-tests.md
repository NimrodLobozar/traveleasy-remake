---
id: TE-004
title: Pest on PostgreSQL with arch tests that forbid the legacy anti-patterns
status: in-test
priority: must
epic: M0 Project scaffold and tooling
agents: [backend-developer]
testers: [qa-engineer, security-privacy-reviewer]
design: not-needed
rework_rounds: 3
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
      *Clarified in rework round 1 (security L4):* the guard contract also fails if a test file **outside**
      `tests/TestCase.php` and `tests/Support/` references any of these, because they are ways to boot the app
      without the guard:
      - `Illuminate\Contracts\Console\Kernel`;
      - `Illuminate\Contracts\Http\Kernel`;
      - `Illuminate\Foundation\Application`;
      - **loading** `bootstrap/app.php` (e.g. `require`/`include` of it). *Reworded in round 2 to match the accepted
        "loaded" reading:* a mere mention of the path in a comment or string doesn't fail.

      *Extended in rework round 2 (security L-c):* the guard-bypass list also includes:
      - `Illuminate\Foundation\Bootstrap\*`;
      - `Illuminate\Foundation\Configuration\ApplicationBuilder`;
      - the concrete `Illuminate\Foundation\Console\Kernel` and `Illuminate\Foundation\Http\Kernel`;
      - any `->bootstrapWith(` call;
      - any `require`/`include` (and `_once`) of a **non-literal** path.

      All of these are flagged in test files outside `tests/TestCase.php` / `tests/Support/`, and each is proven
      failing (AC11).

      *Clarified in rework round 3 (QA):*
      - **(a) Every `require`/`include`.** The rule analyses the **whole expression** up to the end of the statement,
        for `require`, `require_once`, `include` and `include_once`, with or without parentheses (`require($path)`,
        `require ($path)`).
      - **(b) What counts as non-literal.** An expression is **literal** only if it is made solely of string literals
        (without interpolation), `__DIR__`, the `.` operator and parentheses. Anything else is non-literal and fails.
        That includes:
        - variables (`$path`, `__DIR__.$file`);
        - function calls (`dirname(__DIR__, 2).$f`, `base_path($file)`, `config_path($file)`);
        - interpolated strings (`"{$root}/bootstrap/app.php"`).

        Function-call paths have **no** exemption. The one legitimate use today, `tests/Feature/ConfigDefaultsTest.php:34`,
        moves to `PhpFile`.
      - **(c) `bootstrap/app.php` in any piece.** An expression fails if any string piece contains `bootstrap/app.php`,
        including inside an interpolated string. Scanning backwards may not stop at the interpolation `}`.
        The check also applies to the joined value of the literal pieces (e.g. `'…/bootstrap/'.'app.php'`), so
        `require __DIR__.'/../../bootstrap/'.'app.php'` fails. *(Added in round 3 from security finding L-g.)*
      - **(d) The sanctioned loader is fenced.** `Tests\Support\PhpFile::returnValue()` refuses (throws) when
        `realpath($path)` is the application's `bootstrap/app.php`. Alternatively it allows only paths under
        `config/` and `storage/framework/testing/`. The developer decides, documents the choice in the class, and a
        test proves the refusal.
      - **(e) Kernel entry points.** In test files outside `tests/TestCase.php` / `tests/Support/`, the method calls
        `->handleCommand(` and `->handleRequest(` are flagged, because they bootstrap a kernel internally.
      - **(f) Pass bar.** All 36 cases in `tests/Arch/ArchRulesQaBypassTest.php` pass, including QA's 5 new round-3
        cases, and each new form is proven failing (AC11).
- [ ] AC3a (config-cache case without a real cache file; security Low from TE-002, owners qa-engineer +
      backend-developer): the config-cache case of the isolation test (TE-002 AC17) no longer writes
      `bootstrap/cache/config.php`. It points `APP_CONFIG_CACHE` at a temp file under `storage/framework/testing/` and
      deletes it afterwards, so a run that is killed mid-test can't leave a cache that the running dev server would
      load. After a full run, and after a run interrupted with Ctrl+C during that case, `bootstrap/cache/config.php`
      does not exist.
- [ ] AC4 (no debugging leftovers): an arch test fails if `dd`, `dump`, `ddd`, `ray`, `var_dump`, `print_r` or
      `die`/`exit` are used in `App`, `Database`, `config/` or `routes/`.
      *Clarified in rework round 1 (QA bug 1, security L3):*
      - **Function list.** The forbidden list is `dd`, `ddd`, `dump`, `ray`, `var_dump`, `var_export`, `print_r`,
        `printf`, `vprintf`, `exit` and `die`.
      - **Every spelling.** Each is caught in every form: the bare call, the fully qualified call (`\exit(1)`,
        `\die("x")`, `\dd()`), and the language-construct form (`exit;`, `die;`). Since PHP 8.4, `exit`/`die` are real
        functions, so the fully qualified form is a name token, not `T_EXIT`.
      - **Scope.** The **file scan** covers `app/` as well as `database/`, `config/` and `routes/`, so a violation
        inside `App` also produces a message naming the file (AC11). The Pest arch expectation may stay as an extra
        layer.
      - **Proof.** QA's `routes/qa-probe-exit.php` case and the matching `ArchRulesQaBypassTest` cases pass.
- [ ] AC5 (`env()` only in config): a test fails if `env(` is called anywhere outside `config/` (covers `app/`,
      `database/`, `routes/`, `bootstrap/` except `bootstrap/app.php` framework boilerplate if needed, and
      `resources/views`).
      *Judgement call recorded in rework round 1 (endorsed by security):* direct `$_SERVER` / `$_ENV` reads are
      forbidden outside `config/` as well.
- [ ] AC6 (TLS verification): a test fails if `withoutVerifying(` or a Guzzle/HTTP-client option `'verify' => false`
      appears in `app/`, `config/`, `routes/` or `database/`.
      *Clarified in rework round 1 (security M2):* the rule covers the TLS keys `verify`, `verify_peer`,
      `verify_peer_name` and `allow_self_signed`, in both forms:
      - **Array-literal form:** `'verify' => false`.
      - **Assignment form:** `$options['verify'] = false`, `$ctx['ssl']['verify_peer'] = false`, and so on.

      What fails and what's allowed:
      - Any value other than literal `true` fails for `verify_peer` and `verify_peer_name`.
      - For `allow_self_signed`, any value other than literal `false` fails.
      - For `verify`, `true` or a CA-bundle path string is allowed; anything else fails.

      Each form is proven failing (AC11).

      *Extended in rework round 2 (QA failure 1, security L-b):*
      - **(a) More keys.** The key list also includes `verify_host` (Symfony HttpClient), with the same rule as
        `verify_peer`: anything other than literal `true` fails.
      - **(b) Null-coalescing assignment.** `??=` counts as an assignment (`T_COALESCE_EQUAL`), so
        `$options['verify'] ??= false;` fails.
      - **(c) Setter helpers.** `Arr::set($o, 'verify', false)` and `data_set($o, 'verify', false)` fail, with the TLS
        key as the second argument, including dotted paths that end in a TLS key (e.g. `'ssl.verify_peer'`).
      - **(d) Stream contexts.** `stream_context_set_option($ctx, 'ssl', 'verify_peer', false)` fails, and so do the
        array form of `stream_context_set_option` and `stream_context_create([...])` with a TLS key.
      - **(e) Known false positive.** `'verify' => storage_path(...)` (a computed CA path) fails **by design**. Only a
        literal CA path string is allowed. This is documented in `docs/testing.md` (TE-006).
- [ ] AC7 (CSRF): a test fails if any CSRF exception is configured: a non-empty `except` passed to
      `validateCsrfTokens()` in `bootstrap/app.php`, a non-empty `$except` on a CSRF middleware subclass, or
      `withoutMiddleware(` naming a CSRF middleware in `routes/` or `app/`.
      *Clarified in rework round 1 (security M1, QA bug 2; the stricter reading is endorsed by security):*
      - **(a) No subclasses.** No class in `App` may extend Laravel's CSRF middleware (`PreventRequestForgery`, or its
        alias `ValidateCsrfToken` / `VerifyCsrfToken`), directly **or indirectly** (through an intermediate class).
        Laravel 13 needs no subclass, so this also covers `$except`, overridden `handle()` /
        `getTokenFromRequest()` / `inExceptArray()`, and `array_push($this->except, …)` in a constructor.
      - **(b) No group without CSRF.** `bootstrap/app.php` and `routes/` may not redefine the `web` group, or define a
        custom middleware group that includes `StartSession` but not the CSRF middleware. That covers
        `$middleware->group('web', [...])`, `->web(remove: [...CSRF...])` and `->web(replace: ...)` that drops it.
        *Extended in rework round 2 (QA failure 2, QA recommendation, security L-a):*
        - **Named arguments.** The rule reads arguments by position **and** by name.
          `$middleware->group(name: 'web', middleware: [...])` and
          `$middleware->group(name: 'chat', middleware: [StartSession::class])` fail just like the positional
          form. `bootstrap/app.php` uses named arguments everywhere.
        - **Session added to an existing group.** Adding `StartSession` to an existing group through
          `appendToGroup(...)`, `prependToGroup(...)`, `web(append:|prepend:)` or `api(append:|prepend:)` fails,
          unless that group also contains the CSRF middleware after the change.
        - **`::class`-only exemption.** Inside the arguments of `appendToGroup` / `prependToGroup` / a non-web
          `group(...)`, a reference to the CSRF middleware is exempt **only** as a plain `::class` constant (adding
          CSRF is fine). A static call on it, such as `PreventRequestForgery::except(...)`, `::useOriginOnly()` or
          `::allowSameSite()`, still fails.
        - **Proof.** QA's 3 new cases in `tests/Arch/ArchRulesQaBypassTest.php` (31 cases in total) pass, and each
          new form is proven failing (AC11).
      - **(c) Every string form.** Class names given as strings are matched in every PHP string form, unescaped
        exactly as PHP does: single-quoted, double-quoted with single or double backslashes (e.g.
        `"Illuminate\Foundation\Http\Middleware\ValidateCsrfToken"`), heredoc/nowdoc, and `::class`. (Today
        `SourceFile::unquote()` uses `stripcslashes`, which wrongly drops `\I`, `\C`, `\M` and `\F` and turns `\f`
        into a form feed.)
      - **(d) Runtime check.** QA's `tests/Feature/CsrfRuntimeCoverageTest.php` keeps passing: every state-changing
        route runs the CSRF middleware with no excluded paths.
- [ ] AC8 (no mail, ADR-0005): an arch test fails if code in `App` uses `Illuminate\Support\Facades\Mail`,
      `Illuminate\Mail\Mailable` (or extends it), `Illuminate\Notifications\Messages\MailMessage` or
      `Illuminate\Contracts\Mail\Mailer`; and a test fails if any notification declares a `toMail` method or returns
      `'mail'` from `via()`. A config test asserts `config('mail.default') === 'array'` even when `MAIL_MAILER=smtp`
      is set in the environment (TE-001 AC10).
- [ ] AC9 (demo flag): a test fails if `traveleasy.demo` is read through `config(`, `Config::` or `config()->` anywhere
      except `App\Support\DemoMode`, or if `env('DEMO_MODE'` appears outside `config/traveleasy.php`.
      *Clarified in rework round 1 (security L2):* the rule also catches reads through named arguments
      (`Config::get(key: 'traveleasy.demo.enabled')`, where the token before the key is `:`). Outside `config/`, it
      also catches the key as a later positional argument (where the token before is `,`), e.g.
      `Arr::get(config()->all(), 'traveleasy.demo.enabled')` and `data_get(config()->all(), 'traveleasy…')`. Any
      string starting with `traveleasy.demo` (or exactly `traveleasy`) read outside `DemoMode` and `config/` fails.
      Each form is proven failing (AC11).
- [ ] AC10 (presets): Pest's `php` and `security` arch presets run over `App` and pass (or each excluded expectation is
      listed with a reason in the test file).
- [ ] AC11 (every rule proven): for **each** rule in AC4–AC10, the handoff shows the test failing against a temporary
      violating file (then removed), and the failure message names the rule and the offending file. Each rule's test
      has a comment linking its source (legacy § 3 item or ADR).
      *Clarified in rework round 1:*
      - **App code too.** "Names the offending file" applies to violations inside `App` as well. Where a rule is
        enforced only by a Pest arch expectation that doesn't name the file, a file scan over `app/` is added, at
        least for `exit`/`die` and the other function rules in AC4.
      - **QA's bypass suite.** `tests/Arch/ArchRulesQaBypassTest.php` (28 cases) is part of the proof and must pass
        in full.
      - **String forms.** All file scans match string forms, unescaped exactly as PHP does (see AC7 c). Heredoc and
        nowdoc bodies have their trailing newline trimmed, so `app(<<<'X'\nmail.manager\nX)` is caught.
- [ ] AC12: the arch and file-scan tests live together (e.g. `tests/Arch/`) and run in the default suite, not behind a
      group or flag.
- [ ] AC13 (mail rule covers everything; added after the TE-001 review): the AC8 rule forbids the **whole** `Mail`
      facade, not just named mailers. That includes `Mail::build(...)`, `Mail::raw`, `Mail::send`, `Mail::to` and
      `Mail::mailer`. It also forbids any use of the `Illuminate\Mail\*` and `Symfony\Component\Mailer\*` namespaces
      in `App`, `Database`, `config/` and `routes/`. Each part is proven failing as in AC11, including a
      `Mail::build([...])` on-demand mailer and a direct `Symfony\Component\Mailer\Transport` use. (The runtime pin
      from TE-001 stays as a second line of defence.)
      *Clarified in rework round 1 (QA bug 2, security L1):* AC8 and AC13 also cover the following.
      - **(a) Class names as strings.** Container and string forms are matched in every PHP string form, unescaped
        exactly as PHP does (AC7 c). Proven with `app("Illuminate\Contracts\Mail\Factory")`,
        `app('mail.manager')`, `resolve(...)` and a heredoc.
      - **(b) Password-reset mail.** `Password::sendResetLink(` and `->broker(...)->sendResetLink(` are forbidden
        mail methods.
      - **(c) Mail routing.** A declared `routeNotificationForMail()` method is treated like `toMail` and fails.
- [ ] AC14 (unused third-party stores; added after the TE-001 review): the framework-default `AWS_*` and `SQS_*` env
      reads, and the stores and connections that use them, are removed from `config/queue.php` and
      `config/cache.php`:
      - the `sqs` queue connection;
      - the `dynamodb` cache store;
      - any other store that would need cloud credentials, unless it is used.

      The database queue and cache drivers stay. A grep for `AWS_` and `SQS_` over `config/` returns nothing, and the
      suite still passes. (The backend-developer does this here because it shrinks the env surface the arch rules
      protect. It's small and has no runtime effect.)

      *Clarified in rework round 1 (security L5):*
      - **Comments.** The comments in `config/queue.php` and `config/cache.php` are corrected. They may not claim that
        "no AWS credentials are ever read", because Laravel merges the framework defaults back in at runtime. They
        state what was removed and why.
      - **Runtime test.** A test asserts all of these:
        - `config('queue.default')` ∈ {`database`, `sync`};
        - `config('cache.default')` ∈ {`database`, `array`};
        - `config('filesystems.default')` ∈ {`local`, `public`};
        - `composer.lock` contains no `aws/aws-sdk-php`.

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

### Round 1 (2026-10-12)
- **Verdicts:** qa-engineer **FAIL**; security-privacy-reviewer **FAIL** (two Medium findings). The quality budget is
  met (30–42 s; the outliers happened while other agents were using the containers). A failing
  `DockerDevEnvironmentContractTest` case belongs to the TE-005 rework, not here.
- **Failed criteria and evidence:**
  - **AC4 + AC11 (QA bug 1):** `\exit(1)` and `\die("x")` aren't caught outside `App`. Since PHP 8.4 they are real
    functions, so the token is a fully qualified name, not `T_EXIT`, and `ArchRules::DEBUG_FUNCTIONS`
    (`tests/Support/ArchRules.php:547`) lacks `exit`/`die`. Inside `App` only the Pest arch layer catches them, and
    its message doesn't name the file. Proven with `routes/qa-probe-exit.php`.
  - **AC7, AC8 and AC13 (QA bug 2):** class names in double-quoted strings with single backslashes are missed.
    `SourceFile::unquote()` (`tests/Support/SourceFile.php:133-146`) uses `stripcslashes`, which drops `\I`, `\C`,
    `\M` and `\F` and turns `\f` into a form feed. Proven with:
    - `app("Illuminate\Contracts\Mail\Factory")`
    - `$middleware->web(remove: ["Illuminate\Foundation\Http\Middleware\ValidateCsrfToken"])`
  - **AC7 (security M1):** the rule misses:
    - a `web` or custom group with a session but no CSRF;
    - CSRF subclass overrides of `handle()` / `getTokenFromRequest()`;
    - `array_push($this->except, …)` in a constructor;
    - indirect subclasses.
  - **AC6 (security M2):** `$options['verify'] = false` (and `verify_peer`, `allow_self_signed`) is missed, because
    only `=>` is checked (`ArchRules.php:171-194`).
  - **Lows L1–L5 and QA's heredoc note:** see the expected fix.
- **QA's new tests (committed):**
  - `tests/Arch/ArchRulesQaBypassTest.php`: 28 cases, 7 fail until this round is fixed.
  - `tests/Feature/CsrfRuntimeCoverageTest.php`: a runtime CSRF coverage check that passes today.
- **Expected fix (backend-developer):**
  - Unescape strings exactly as PHP does; trim the trailing newline of heredoc/nowdoc bodies.
  - Add `exit`, `die`, `var_export`, `printf` and `vprintf` to the function list, match fully qualified calls, and
    file-scan `app/` too.
  - Forbid any `App` class extending the CSRF middleware (direct or indirect), and any session group without CSRF.
  - Catch the TLS assignment form.
  - Add the `sendResetLink` / `routeNotificationForMail` mail cases.
  - Catch demo-flag reads through named and positional arguments.
  - Add the guard-bypass references to the guard contract.
  - Correct the AWS comments, and add the config-defaults runtime test.
  - Make all 28 bypass cases pass.
- **Criteria clarified:** AC3 (L4), AC4, AC5 (`$_SERVER`/`$_ENV` judgement call recorded), AC6, AC7 (a–d),
  AC8/AC13 (a–c), AC9, AC11 (`App` files named; bypass suite; string forms), AC14. Security endorses both judgement
  calls: the stricter AC7, and `$_SERVER` forbidden outside `config/`.
- **Follow-ups filed elsewhere (not this rework):**
  - L6, Vite `server.fs.deny`: TE-006, for the devops-engineer.
  - The `serve => true` signed storage routes observation: added to the existing M1 item on the board.

### Round 2 (2026-10-12)
- **Verdicts:**
  - **security-privacy-reviewer: PASS.** M1, M2 and L1–L5 are closed; new Lows L-a…L-d.
  - **qa-engineer: FAIL, narrowly.** Everything else passes: 323/323 tests, deterministic, identical serial and
    parallel; all 28 original bypass cases; AC3a re-done. QA accepted both developer decisions:
    - the L4 "loaded" reading for `bootstrap/app.php`;
    - the `CsrfRuntimeCoverageTest` helper change.
- **Failed criteria and evidence:**
  - **AC6 / AC11:** `$options['verify'] ??= false;` is missed. The assignment rule only looks at `=`, not
    `T_COALESCE_EQUAL`.
  - **AC7(b) / AC11:** named-argument groups are missed:
    - `$middleware->group(name: 'web', middleware: [...])`;
    - `group(name: 'chat', middleware: [StartSession::class])`.

    `firstStringArgument()` only accepts a literal right after `(`. This is plausible in real code, because
    `bootstrap/app.php` uses named arguments everywhere. The runtime test still catches the harm, but the file-scan
    rule doesn't.
  - **QA's new cases:** 3 failing cases added to `tests/Arch/ArchRulesQaBypassTest.php`, now 31 cases (committed).
- **Cause:** both scanners only handle the most common token shape (`=` and a positional first argument).
- **Expected fix (backend-developer):**
  - Treat `??=` as an assignment.
  - Resolve call arguments by name as well as by position.
  - In the same round, cheap and in the same code areas:
    - **L-a:** the `::class`-only CSRF exemption.
    - **L-b:** `Arr::set` / `data_set`, `stream_context_set_option`, `verify_host`.
    - **L-c:** extra guard-bypass classes, `->bootstrapWith(`, `require`/`include` of a non-literal path.
    - **QA recommendation:** `StartSession` appended or prepended to an existing group without CSRF fails.
    - **Nit:** the comments in `config/queue.php` and `config/cache.php` must reference `CloudCredentialsRuntimeTest.php`,
      not `CloudCredentialsTest.php`.
  - All 31 bypass cases pass.
- **Criteria clarified:**
  - AC3: reworded to "loading `bootstrap/app.php`", and the L-c additions.
  - AC6: (a)–(e): `verify_host`, `??=`, `Arr::set`/`data_set`, stream contexts, and the documented
    `storage_path(...)` false positive.
  - AC7(b): named arguments, append/prepend of `StartSession`, and the `::class`-only exemption.
- **Follow-ups filed elsewhere (not this round):**
  - L-d: on the board under "Follow-ups for M1".
  - The documented scanner limits: TE-006 AC13e.

### Round 3 (2026-10-12): final round before escalation
> **This is rework round 3, the last one.** Per `CLAUDE.md` § How work flows, if the next re-test fails, the
> orchestrator stops and asks the owner how to proceed. Possible options: accept the remaining gaps as documented
> limits, narrow AC3, or split it out.

- **Verdicts:**
  - **security-privacy-reviewer: PASS.** L-a…L-c are closed; new Lows L-e, L-f and L-g are filed as follow-ups.
  - **qa-engineer: FAIL on AC3 only.** AC6 and AC7 are now complete. The suite is green and deterministic (serial
    about 28 s, parallel about 12 s).
- **Failed criterion:** AC3, the guard-bypass rule for `require`/`include` and kernel entry points.
- **Evidence:** the rule checks only the first token after `require`, so it misses:
  1. parenthesised forms: `require($path)`, `require_once($path)`, `require ($path)`;
  2. `require __DIR__.$file` and `dirname(__DIR__, 2).$f`;
  3. `require "{$root}/bootstrap/app.php"` (the backward scan stops at the interpolation `}`);
  4. `\Tests\Support\PhpFile::returnValue(__DIR__.'/../../bootstrap/app.php')`, because the sanctioned loader can
     load the app;
  5. `require base_path($file)` / `require config_path($file)`. **QA rejected the developer's exclusion of
     function-call paths:** AC3 says "any non-literal path".

  Also, `$app->handleCommand(...)` / `handleRequest(...)` aren't flagged, so `$app = require($path);
  $app->handleCommand(...)` would reach the database without the guard.

  QA added the 5 failing cases to `tests/Arch/ArchRulesQaBypassTest.php`, now 36 cases (committed).
- **Cause:**
  - a first-token check instead of a whole-expression check;
  - an exemption for function-call paths that AC3 doesn't allow;
  - `PhpFile` has no path restriction;
  - kernel entry-point methods aren't on the list.
- **Expected fix (backend-developer):**
  - Analyse every `require`/`include`(`_once`) up to the end of the statement. Flag it unless it is only string
    literals, `__DIR__`, `.` and parentheses, and flag it if any piece contains `bootstrap/app.php`, including
    through interpolation.
  - Make `PhpFile::returnValue()` refuse the app's `bootstrap/app.php` (or allow-list `config/` and
    `storage/framework/testing/`), and document the choice.
  - Move `ConfigDefaultsTest` to `PhpFile`.
  - Flag `->handleCommand(` / `->handleRequest(` outside `tests/TestCase.php` / `tests/Support/`.
  - All 36 bypass cases pass.
- **Criterion clarified:** AC3 (a)–(f):
  - (a) whole expression, with or without parentheses;
  - (b) "non-literal" = anything other than string literals, `__DIR__`, `.` and parentheses (function calls count;
    no exemption);
  - (c) `bootstrap/app.php` in any piece, including interpolation;
  - (d) the `PhpFile` restriction;
  - (e) `handleCommand` / `handleRequest`;
  - (f) the 36-case pass bar.
- **Follow-ups filed elsewhere (not this round):**
  - TE-006 AC13e, known limits plus recommendations:
    - L-e: CSRF via `call_user_func` and non-literal group names (covered at runtime);
    - L-f: TLS via `Config::set` / `config()->set` / `config([...])`, `Arr::add`, `stream_context_set_option` named
      parameters, and `HttpOptions->verifyPeer(false)`;
    - global `append`/`prepend(StartSession)` (covered at runtime);
    - `Process(['php', 'artisan', …])` in tests.
  - L-g wasn't specified beyond the list above. If it's a separate item, send it and I'll file it.

## Changelog
(filled when done)
