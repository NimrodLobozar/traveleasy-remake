<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The file-scan rules of TE-004. Each rule takes one SourceFile and returns its violations as
 * "path:line what was found" strings; an empty list means the file is clean. The Pest tests in tests/Arch run
 * them over the project, and tests/Arch/ArchRulesTest.php + ArchRulesQaBypassTest.php prove each one against
 * violating and clean snippets.
 *
 * Sources: docs/legacy-analysis.md § 3, ADR-0005 (no outgoing e-mail), ADR-0006 (demo mode), docs/plan.md § 4 M0.
 */
final class ArchRules
{
    /** Project paths scanned for PHP code (bootstrap/cache is always skipped). */
    public const PHP_ROOTS = ['app', 'bootstrap', 'config', 'database', 'routes'];

    public const VIEW_ROOT = 'resources/views';

    /**
     * Namespaces for Pest arch expectations: one expectation per namespace. Pest silently checks nothing when
     * `expect()` gets a list of targets, and `Database` itself is not an autoload namespace (only its children are).
     */
    public const ARCH_NAMESPACES = ['App', 'Database\Factories', 'Database\Seeders'];

    /**
     * AC4 / legacy § 3 "debugging leftovers" (list clarified in TE-004 rework round 1, plus a few debug-only
     * extras). exit/die are functions since PHP 8.4, so `\exit(1)` is a call and the bare `exit;` is T_EXIT.
     */
    public const DEBUG_FUNCTIONS = [
        'dd', 'ddd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r', 'printf', 'vprintf', 'exit', 'die',
        'trap', 'ds', 'debug_zval_dump', 'debug_print_backtrace', 'xdebug_break', 'xdebug_var_dump', 'phpinfo',
    ];

    /** Debug helpers that also exist as methods (collections, query builders, requests, responses, Benchmark). */
    public const DEBUG_METHODS = ['dd', 'ddd', 'dump', 'ddrawsql', 'dumprawsql'];

    /** AC5: ways to read the process environment. */
    public const ENV_FUNCTIONS = ['env', 'getenv', 'putenv'];

    public const ENV_VARIABLES = ['$_ENV', '$_SERVER'];

    /** AC6: TLS options and the values they may have. */
    public const TLS_KEYS = ['verify', 'verify_peer', 'verify_peer_name', 'allow_self_signed'];

    /** AC7: Laravel's CSRF middleware and its aliases. */
    public const CSRF_MIDDLEWARE = [
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    ];

    public const SESSION_MIDDLEWARE = 'Illuminate\Session\Middleware\StartSession';

    /** AC7: calls that define a whole middleware group (first argument: the group name). */
    public const GROUP_DEFINITIONS = ['group', 'middlewaregroup'];

    /** AC7: calls that only add middleware to a group; naming the CSRF middleware there is harmless. */
    public const GROUP_ADDITIONS = ['appendtogroup', 'prependtogroup'];

    /**
     * AC8 / AC13 / ADR-0005: classes and namespaces that send or build e-mail. `Mail` is the global facade alias.
     */
    public const MAIL_CLASSES = [
        'Mail',
        'Illuminate\Support\Facades\Mail',
        'Illuminate\Mail',
        'Illuminate\Contracts\Mail',
        'Illuminate\Notifications\Messages\MailMessage',
        'Illuminate\Notifications\Channels\MailChannel',
        'Illuminate\Auth\Notifications',
        'Illuminate\Contracts\Auth\MustVerifyEmail',
        'Symfony\Component\Mailer',
    ];

    public const MAIL_FUNCTIONS = ['mail', 'mb_send_mail', 'imap_mail'];

    /** Container bindings of the mailer (app('mailer'), resolve('mail.manager'), ...). */
    public const MAIL_CONTAINER_KEYS = ['mailer', 'mail.manager', 'mailer.markdown'];

    /** Calls that send mail, including the password-reset link (AC13 b). */
    public const MAIL_METHODS = ['tomailusing', 'sendpasswordresetnotification', 'sendemailverificationnotification', 'sendresetlink'];

    /** Declared methods that only exist to send mail (AC8, AC13 c). */
    public const MAIL_DECLARATIONS = ['tomail', 'routenotificationformail'];

    /** AC9 / ADR-0006: the only file that may read traveleasy.demo, and the only one that may read DEMO_MODE. */
    public const DEMO_MODE_SERVICE = 'app/Support/DemoMode.php';

    public const DEMO_MODE_CONFIG = 'config/traveleasy.php';

    /**
     * AC4: no debugging leftovers (legacy § 3): the DEBUG_FUNCTIONS in every spelling, `exit;`/`die;`, the debug
     * methods, and echo/print outside Blade.
     *
     * @return list<string>
     */
    public static function debuggingLeftovers(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->functionCalls as $call) {
            if (in_array($call['name'], self::DEBUG_FUNCTIONS, true)) {
                $violations[] = self::at($file, $call['line'], "calls {$call['name']}()");
            }
        }

        foreach ($file->methodCalls as $call) {
            if (in_array($call['name'], self::DEBUG_METHODS, true)) {
                $violations[] = self::at($file, $call['line'], "calls ->{$call['name']}()");
            }
        }

        foreach ($file->exits as $line) {
            $violations[] = self::at($file, $line, 'uses exit/die');
        }

        // Pest's php preset lists echo/print, but its arch engine can't see the echo statement; compiled Blade
        // views echo by design, so this only applies to plain PHP files.
        if (! str_ends_with($file->path, '.blade.php')) {
            foreach ($file->outputs as $output) {
                $violations[] = self::at($file, $output['line'], "uses {$output['name']}");
            }
        }

        return $violations;
    }

    /**
     * AC5: env() is only read in config/ (so `config:cache` keeps working and env stays in one place).
     *
     * @return list<string>
     */
    public static function envOutsideConfig(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->functionCalls as $call) {
            if (in_array($call['name'], self::ENV_FUNCTIONS, true)) {
                $violations[] = self::at($file, $call['line'], "calls {$call['name']}()");
            }
        }

        foreach ($file->classReferences as $reference) {
            if (SourceFile::isWithin($reference['name'], 'Illuminate\Support\Env') || strtolower($reference['name']) === 'env') {
                $violations[] = self::at($file, $reference['line'], "uses {$reference['name']}");
            }
        }

        foreach ($file->variables as $variable) {
            if (in_array($variable['name'], self::ENV_VARIABLES, true)) {
                $violations[] = self::at($file, $variable['line'], "reads {$variable['name']}");
            }
        }

        return $violations;
    }

    /**
     * AC6: TLS verification stays on (legacy § 3: the HTTP client used withoutVerifying()). Checks the array form
     * (`'verify' => false`) and the assignment form (`$options['verify'] = false`).
     *
     * @return list<string>
     */
    public static function tlsVerificationDisabled(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->methodCalls as $call) {
            if ($call['name'] === 'withoutverifying') {
                $violations[] = self::at($file, $call['line'], 'calls withoutVerifying()');
            }
        }

        foreach ($file->strings as $string) {
            if (strtolower($string['value']) === 'withoutverifying') {
                $violations[] = self::at($file, $string['line'], 'names withoutVerifying as a callable');
            }
        }

        foreach ($file->identifiers as $identifier) {
            if (in_array(strtoupper($identifier['name']), ['CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_SSL_VERIFYSTATUS'], true)) {
                $violations[] = self::at($file, $identifier['line'], "sets the cURL option {$identifier['name']}");
            }
        }

        foreach ($file->tokens as $i => $token) {
            if ($token->is(T_DOUBLE_ARROW)) {
                $key = self::tlsKeyEndingAt($file, $i - 1);
                $form = "'%s' => %s";
            } elseif ($token->text === '=' && $file->token($i - 1)?->text === ']') {
                $key = self::tlsKeyEndingAt($file, $i - 2);
                $form = "['%s'] = %s";
            } else {
                continue;
            }

            if ($key === null) {
                continue;
            }

            $value = $file->token($i + 1);

            if (! self::tlsValueAllowed($file, $key, $i + 1)) {
                $violations[] = self::at($file, $token->line, 'sets the TLS option '.sprintf($form, $key, $value->text ?? '?'));
            }
        }

        return $violations;
    }

    /**
     * AC7: no CSRF exceptions (legacy § 3: CSRF was disabled for the chatbot endpoint):
     *  - no argument to validateCsrfTokens()/preventRequestForgery() other than an empty `except`;
     *  - no reference to the CSRF middleware (no subclass, no withoutMiddleware/remove/replace/except), except as a
     *    member of a non-`web` group definition or an append/prepend to a group;
     *  - no redefined `web` group, and no custom group with StartSession but without CSRF;
     *  - no withoutMiddleware('web') / withoutMiddleware('csrf').
     * Indirect subclasses (through another App class) are found across files by csrfSubclasses().
     *
     * @return list<string>
     */
    public static function csrfExceptions(SourceFile $file): array
    {
        $violations = [];
        $allowedRanges = [];

        foreach ($file->methodCalls as $call) {
            $open = $call['index'] + 1;
            $close = $file->closingIndex($open);

            if (in_array($call['name'], ['validatecsrftokens', 'preventrequestforgery'], true)) {
                $arguments = strtolower($file->textBetween($open, $close));

                if (! in_array($arguments, ['', '[]', 'except:[]', 'array()', 'except:array()'], true)) {
                    $violations[] = self::at($file, $call['line'], "configures CSRF exceptions in {$call['name']}({$arguments})");
                }
            }

            if ($call['name'] === 'withoutmiddleware') {
                foreach ($file->strings as $string) {
                    if ($string['index'] > $open && $string['index'] < $close && in_array(strtolower($string['value']), ['web', 'csrf'], true)) {
                        $violations[] = self::at($file, $string['line'], "removes the CSRF middleware (withoutMiddleware('{$string['value']}'))");
                    }
                }
            }

            if (in_array($call['name'], self::GROUP_ADDITIONS, true)) {
                $allowedRanges[] = [$open, $close];
            }

            if (in_array($call['name'], self::GROUP_DEFINITIONS, true)) {
                $group = self::firstStringArgument($file, $open);

                if ($group === null) {
                    continue;
                }

                if (strtolower($group) === 'web') {
                    $violations[] = self::at($file, $call['line'], "redefines the 'web' middleware group");

                    continue;
                }

                $members = array_filter(
                    $file->classReferences,
                    fn (array $reference): bool => $reference['index'] > $open && $reference['index'] < $close,
                );
                $hasSession = array_filter($members, fn (array $reference): bool => strtolower($reference['name']) === strtolower(self::SESSION_MIDDLEWARE)) !== [];
                $hasCsrf = array_filter($members, fn (array $reference): bool => self::isCsrfMiddleware($reference['name'])) !== [];

                if ($hasSession && ! $hasCsrf) {
                    $violations[] = self::at($file, $call['line'], "defines the middleware group '{$group}' with StartSession but without the CSRF middleware");
                }

                $allowedRanges[] = [$open, $close];
            }
        }

        foreach ($file->classReferences as $reference) {
            if (! self::isCsrfMiddleware($reference['name']) || $reference['kind'] === SourceFile::KIND_IMPORT) {
                continue;
            }

            if ($reference['kind'] === SourceFile::KIND_EXTENDS) {
                $violations[] = self::at($file, $reference['line'], "extends the CSRF middleware {$reference['name']} (no subclass is allowed)");

                continue;
            }

            foreach ($allowedRanges as [$from, $to]) {
                if ($reference['index'] > $from && $reference['index'] < $to) {
                    continue 2;
                }
            }

            $violations[] = self::at($file, $reference['line'], "references the CSRF middleware {$reference['name']} "
                .'(withoutMiddleware, remove, replace and except are not allowed)');
        }

        return $violations;
    }

    /**
     * AC7 (a): classes that extend the CSRF middleware through another class declared in the given files.
     * Direct subclasses are already reported by csrfExceptions().
     *
     * @param  list<SourceFile>  $files
     * @return list<string>
     */
    public static function csrfSubclasses(array $files): array
    {
        $parents = [];
        $declaredIn = [];

        foreach ($files as $file) {
            foreach ($file->classParents as $class => $parent) {
                $parents[strtolower($class)] = $parent;
                $declaredIn[strtolower($class)] = [$class, $file->path];
            }
        }

        $violations = [];

        foreach ($declaredIn as $key => [$class, $path]) {
            $chain = [];
            $current = $parents[$key] ?? null;

            while ($current !== null && ! in_array(strtolower($current), $chain, true)) {
                if (self::isCsrfMiddleware($current)) {
                    if ($chain !== []) {
                        $violations[] = "{$path} {$class} extends the CSRF middleware indirectly (via ".implode(' -> ', $chain).')';
                    }

                    break;
                }

                $chain[] = strtolower($current);
                $current = $parents[strtolower($current)] ?? null;
            }
        }

        return $violations;
    }

    /**
     * AC8 / AC13: no e-mail at all (ADR-0005).
     *
     * @return list<string>
     */
    public static function mail(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->classReferences as $reference) {
            foreach (self::MAIL_CLASSES as $forbidden) {
                if (SourceFile::isWithin($reference['name'], $forbidden)) {
                    $violations[] = self::at($file, $reference['line'], "uses {$reference['name']}");

                    break;
                }
            }
        }

        foreach ($file->functionCalls as $call) {
            if (in_array($call['name'], self::MAIL_FUNCTIONS, true)) {
                $violations[] = self::at($file, $call['line'], "calls {$call['name']}()");
            }
        }

        foreach ($file->methodCalls as $call) {
            if (in_array($call['name'], self::MAIL_METHODS, true)) {
                $violations[] = self::at($file, $call['line'], "calls {$call['name']}()");
            }
        }

        foreach ($file->functionDeclarations as $declaration) {
            if (in_array($declaration['name'], self::MAIL_DECLARATIONS, true)) {
                $violations[] = self::at($file, $declaration['line'], "declares a {$declaration['name']}() method");
            }
        }

        foreach ($file->strings as $string) {
            $value = strtolower($string['value']);

            if ($value === 'mail') {
                $violations[] = self::at($file, $string['line'], "names the 'mail' notification channel");
            } elseif (in_array($value, self::MAIL_CONTAINER_KEYS, true)) {
                $violations[] = self::at($file, $string['line'], "resolves the mailer from the container ('{$string['value']}')");
            }
        }

        return $violations;
    }

    /**
     * AC9 / ADR-0006: only App\Support\DemoMode reads traveleasy.demo; only config/traveleasy.php reads DEMO_MODE.
     *  - Outside config/: any string `traveleasy.demo…`, and `traveleasy` as an argument or array key (after
     *    `(`, `[`, `:` or `,`), fails.
     *  - In config/: the same keys fail when read as a first, named or array-key argument (`(`, `[`, `:`).
     *
     * @return list<string>
     */
    public static function demoFlag(SourceFile $file): array
    {
        $violations = [];
        $inConfig = str_starts_with($file->path, 'config/');

        foreach ($file->strings as $string) {
            $value = strtolower($string['value']);
            $previous = $file->token($string['index'] - 1)?->text;
            $isDemoKey = $value === 'traveleasy.demo' || str_starts_with($value, 'traveleasy.demo.');
            $readContexts = $inConfig ? ['(', '[', ':'] : ['(', '[', ':', ','];
            $isRead = in_array($previous, $readContexts, true);

            $readsDemo = ($isDemoKey && (! $inConfig || $isRead)) || ($value === 'traveleasy' && $isRead);

            if ($readsDemo && $file->path !== self::DEMO_MODE_SERVICE) {
                $violations[] = self::at($file, $string['line'], "reads the demo flag '{$string['value']}' outside App\\Support\\DemoMode");
            }

            if (str_contains(strtoupper($string['value']), 'DEMO_MODE') && $file->path !== self::DEMO_MODE_CONFIG) {
                $violations[] = self::at($file, $string['line'], 'reads DEMO_MODE outside config/traveleasy.php');
            }
        }

        return $violations;
    }

    /**
     * AC3 (security L4): in test files outside tests/TestCase.php and tests/Support/, no way to boot the app
     * without the TE-002 guard: the console/HTTP kernel contracts, Foundation\Application, or loading
     * bootstrap/app.php (a string containing it inside a require/include expression or passed to base_path()).
     * A bare 'bootstrap/app.php' string elsewhere (e.g. a virtual path in a snippet dataset) loads nothing.
     *
     * @return list<string>
     */
    public static function guardBypasses(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->classReferences as $reference) {
            foreach (self::GUARD_BYPASS_CLASSES as $class) {
                if (SourceFile::isWithin($reference['name'], $class)) {
                    $violations[] = self::at($file, $reference['line'], "references {$reference['name']} (boots the app without the guard)");
                }
            }
        }

        foreach ($file->strings as $string) {
            if (str_contains(strtolower(str_replace('\\', '/', $string['value'])), 'bootstrap/app.php') && self::isLoadExpression($file, $string['index'])) {
                $violations[] = self::at($file, $string['line'], 'loads bootstrap/app.php (boots the app without the guard)');
            }
        }

        return $violations;
    }

    /** AC3 (L4): classes that boot or hand out an application outside Tests\TestCase. */
    public const GUARD_BYPASS_CLASSES = [
        'Illuminate\Contracts\Console\Kernel',
        'Illuminate\Contracts\Http\Kernel',
        'Illuminate\Foundation\Application',
    ];

    /**
     * Runs a rule over files and returns all violations.
     *
     * @param  callable(SourceFile): list<string>  $rule
     * @param  list<SourceFile>  $files
     * @return list<string>
     */
    public static function check(callable $rule, array $files): array
    {
        $violations = [];

        foreach ($files as $file) {
            $violations = [...$violations, ...$rule($file)];
        }

        return $violations;
    }

    /** @param  list<string>  $violations */
    public static function report(string $rule, array $violations): string
    {
        return "Arch rule violated: {$rule}\n  ".implode("\n  ", $violations);
    }

    public static function isCsrfMiddleware(string $class): bool
    {
        foreach (self::CSRF_MIDDLEWARE as $middleware) {
            if (strtolower($class) === strtolower($middleware)) {
                return true;
            }
        }

        return false;
    }

    /** True when the token at $index is part of a require/include expression or a base_path()/basePath() call. */
    private static function isLoadExpression(SourceFile $file, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $file->tokens[$i];

            if (in_array($token->text, [';', '{', '}'], true)) {
                return false;
            }

            if ($token->is([T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE])
                || ($token->is(T_STRING) && in_array(strtolower($token->text), ['base_path', 'basepath'], true))) {
                return true;
            }
        }

        return false;
    }

    /** The first argument of the call whose `(` is at $open, when it is a plain string literal. */
    private static function firstStringArgument(SourceFile $file, int $open): ?string
    {
        $first = $file->token($open + 1);
        $after = $file->token($open + 2)?->text;

        if ($first !== null && $first->is(T_CONSTANT_ENCAPSED_STRING) && in_array($after, [',', ')'], true)) {
            return SourceFile::unquote($first);
        }

        return null;
    }

    /** The TLS option whose key ends at token $end (`'verify'` or `RequestOptions::VERIFY`), if any. */
    private static function tlsKeyEndingAt(SourceFile $file, int $end): ?string
    {
        $key = $file->token($end);

        if ($key === null) {
            return null;
        }

        if ($key->is(T_CONSTANT_ENCAPSED_STRING)) {
            $name = strtolower(SourceFile::unquote($key));

            return in_array($name, self::TLS_KEYS, true) ? $name : null;
        }

        // GuzzleHttp\RequestOptions::VERIFY
        if ($key->is(T_STRING) && strtoupper($key->text) === 'VERIFY' && $file->token($end - 1)?->is(T_DOUBLE_COLON) === true) {
            return 'verify';
        }

        return null;
    }

    /**
     * verify_peer / verify_peer_name: only literal true. allow_self_signed: only literal false. verify: true or a
     * CA-bundle path string. The value must be a single token followed by the end of the element or statement.
     */
    private static function tlsValueAllowed(SourceFile $file, string $key, int $at): bool
    {
        $value = $file->token($at);

        if ($value === null || ! in_array($file->token($at + 1)?->text, [',', ']', ')', ';'], true)) {
            return false;
        }

        $literal = strtolower($value->text);

        return match ($key) {
            'allow_self_signed' => $literal === 'false',
            'verify' => $literal === 'true' || $value->is(T_CONSTANT_ENCAPSED_STRING),
            default => $literal === 'true',
        };
    }

    private static function at(SourceFile $file, int $line, string $what): string
    {
        return "{$file->path}:{$line} {$what}";
    }
}
