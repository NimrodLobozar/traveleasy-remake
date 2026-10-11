<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The file-scan rules of TE-004. Each rule takes one SourceFile and returns its violations as
 * "path:line what was found" strings; an empty list means the file is clean. The Pest tests in tests/Arch run
 * them over the project, and tests/Arch/ArchRulesTest.php proves each one against violating snippets.
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

    /** AC4 / legacy § 3 "debugging leftovers". */
    public const DEBUG_FUNCTIONS = [
        'dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'trap', 'ds', 'debug_zval_dump',
        'debug_print_backtrace', 'xdebug_break', 'xdebug_var_dump', 'phpinfo',
    ];

    /** Debug helpers that also exist as methods (collections, query builders, requests, responses). */
    public const DEBUG_METHODS = ['dd', 'ddd', 'dump', 'ddrawsql', 'dumprawsql'];

    /** AC5: ways to read the process environment. */
    public const ENV_FUNCTIONS = ['env', 'getenv', 'putenv'];

    public const ENV_VARIABLES = ['$_ENV', '$_SERVER'];

    /** AC7: Laravel's CSRF middleware and its aliases. */
    public const CSRF_MIDDLEWARE = [
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    ];

    /** AC7: methods of the CSRF middleware that decide what is exempt; overriding them is an exception too. */
    public const CSRF_EXEMPTION_METHODS = ['inexceptarray', 'getexcludedpaths', 'tokensmatch', 'isreading', 'hasvalidorigin', 'runningunittests'];

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

    public const MAIL_METHODS = ['tomailusing', 'sendpasswordresetnotification', 'sendemailverificationnotification'];

    /** AC9 / ADR-0006: the only file that may read traveleasy.demo, and the only one that may read DEMO_MODE. */
    public const DEMO_MODE_SERVICE = 'app/Support/DemoMode.php';

    public const DEMO_MODE_CONFIG = 'config/traveleasy.php';

    /**
     * AC4: no dd/dump/ray/var_dump/print_r/exit/die, echo/print and friends (legacy § 3: debugging leftovers).
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
     * AC6: TLS verification stays on (legacy § 3: the HTTP client used withoutVerifying()).
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
            if (! $token->is(T_DOUBLE_ARROW)) {
                continue;
            }

            $key = self::tlsOptionKey($file, $i);

            if ($key === null) {
                continue;
            }

            $value = $file->token($i + 1);
            $after = $file->token($i + 2)?->text;
            $ends = in_array($after, [',', ']', ')'], true);
            $isTrue = $value !== null && strtolower($value->text) === 'true' && $ends;
            $isCaBundle = $key === 'verify' && $value?->is(T_CONSTANT_ENCAPSED_STRING) === true && $ends;
            $allowed = $key === 'allow_self_signed'
                ? $value !== null && strtolower($value->text) === 'false' && $ends
                : $isTrue || $isCaBundle;

            if (! $allowed) {
                $violations[] = self::at($file, $token->line, "sets the TLS option '{$key}' => ".($value->text ?? '?'));
            }
        }

        return $violations;
    }

    /**
     * AC7: no CSRF exceptions (legacy § 3: CSRF was disabled for the chatbot endpoint).
     *
     * @return list<string>
     */
    public static function csrfExceptions(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->methodCalls as $call) {
            if (in_array($call['name'], ['validatecsrftokens', 'preventrequestforgery'], true)) {
                $open = $call['index'] + 1;
                $arguments = strtolower($file->textBetween($open, $file->closingIndex($open)));

                if (! in_array($arguments, ['', '[]', 'except:[]', 'array()', 'except:array()'], true)) {
                    $violations[] = self::at($file, $call['line'], "configures CSRF exceptions in {$call['name']}({$arguments})");
                }
            }

            if ($call['name'] === 'withoutmiddleware') {
                $open = $call['index'] + 1;

                for ($i = $open + 1, $close = $file->closingIndex($open); $i < $close; $i++) {
                    $token = $file->tokens[$i];

                    if ($token->is(T_CONSTANT_ENCAPSED_STRING) && in_array(strtolower(SourceFile::unquote($token)), ['web', 'csrf'], true)) {
                        $violations[] = self::at($file, $token->line, 'removes the CSRF middleware (withoutMiddleware('.$token->text.'))');
                    }
                }
            }
        }

        $extendsCsrf = false;

        foreach ($file->classReferences as $reference) {
            if (! self::isCsrfMiddleware($reference['name'])) {
                continue;
            }

            if ($reference['kind'] === SourceFile::KIND_EXTENDS) {
                $extendsCsrf = true;
            } elseif ($reference['kind'] !== SourceFile::KIND_IMPORT) {
                $violations[] = self::at($file, $reference['line'], "references the CSRF middleware {$reference['name']} "
                    .'(only subclassing it is allowed; withoutMiddleware/remove/except are not)');
            }
        }

        if ($extendsCsrf) {
            $violations = [...$violations, ...self::csrfSubclassExceptions($file)];
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
            if ($declaration['name'] === 'tomail') {
                $violations[] = self::at($file, $declaration['line'], 'declares a toMail() method');
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
     *
     * @return list<string>
     */
    public static function demoFlag(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->strings as $string) {
            $value = strtolower($string['value']);
            $previous = $file->token($string['index'] - 1)?->text;

            $readsDemo = $value === 'traveleasy.demo' || str_starts_with($value, 'traveleasy.demo.')
                || ($value === 'traveleasy' && in_array($previous, ['(', '['], true));

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

    /** @return list<string> */
    private static function csrfSubclassExceptions(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->tokens as $i => $token) {
            $writesExcept = ($token->is(T_VARIABLE) && $token->text === '$except')
                || ($token->is(T_STRING) && strtolower($token->text) === 'except' && $file->token($i - 1)?->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON]) === true);

            if (! $writesExcept) {
                continue;
            }

            $next = $file->token($i + 1)?->text;

            if ($next === '(') {
                $violations[] = self::at($file, $token->line, 'adds CSRF exceptions with except()');
            } elseif ($next === '[') {
                $violations[] = self::at($file, $token->line, 'appends to the CSRF $except list');
            } elseif ($next === '=') {
                $value = $file->token($i + 2);

                $empty = ($value?->text === '[' && $file->token($i + 3)?->text === ']')
                    || ($value !== null && strtolower($value->text) === 'array' && $file->token($i + 4)?->text === ')' && $file->token($i + 3)?->text === '(');

                if (! $empty) {
                    $violations[] = self::at($file, $token->line, 'sets a non-empty CSRF $except list');
                }
            }
        }

        foreach ($file->functionDeclarations as $declaration) {
            if (in_array($declaration['name'], self::CSRF_EXEMPTION_METHODS, true)) {
                $violations[] = self::at($file, $declaration['line'], "overrides the CSRF exemption method {$declaration['name']}()");
            }
        }

        return $violations;
    }

    private static function tlsOptionKey(SourceFile $file, int $arrow): ?string
    {
        $key = $file->token($arrow - 1);

        if ($key === null) {
            return null;
        }

        if ($key->is(T_CONSTANT_ENCAPSED_STRING)) {
            $name = strtolower(SourceFile::unquote($key));

            return in_array($name, ['verify', 'verify_peer', 'verify_peer_name', 'allow_self_signed'], true) ? $name : null;
        }

        // GuzzleHttp\RequestOptions::VERIFY => false
        if ($key->is(T_STRING) && strtoupper($key->text) === 'VERIFY' && $file->token($arrow - 2)?->is(T_DOUBLE_COLON) === true) {
            return 'verify';
        }

        return null;
    }

    private static function at(SourceFile $file, int $line, string $what): string
    {
        return "{$file->path}:{$line} {$what}";
    }
}
