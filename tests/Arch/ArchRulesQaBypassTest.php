<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceFile;

/*
| TE-004 AC11 / security QC (added by qa-engineer): extra bypass attempts against the file-scan rules, on top of
| tests/Arch/ArchRulesTest.php. Every case is a form a developer could write without trying to evade the rule;
| deliberate runtime obfuscation (names built by concatenation, variables, call_user_func('dd')) is a documented
| limit of tests/Support/SourceFile.php and is not listed here.
| Sources: docs/legacy-analysis.md § 3 (debugging leftovers, CSRF, TLS), ADR-0005 (no mail), ADR-0006 (demo flag).
*/

/** @return list<string> */
function qaBypassScan(string $rule, string $code, string $path): array
{
    $file = new SourceFile($path, $code);

    return match ($rule) {
        'debug' => ArchRules::debuggingLeftovers($file),
        'env' => ArchRules::envOutsideConfig($file),
        'tls' => ArchRules::tlsVerificationDisabled($file),
        'csrf' => ArchRules::csrfExceptions($file),
        'mail' => ArchRules::mail($file),
        'demo' => ArchRules::demoFlag($file),
        default => throw new InvalidArgumentException("Unknown rule [{$rule}]."),
    };
}

test('a rule catches a plausible non-evasive variant', function (string $rule, string $code, string $path) {
    expect(qaBypassScan($rule, $code, $path))->not->toBe([], "{$rule} rule missed: {$code}");
})->with([
    // AC4: exit/die are functions since PHP 8.4, so the fully qualified form is valid and tokenises as a name.
    'debug: \exit() in a route file' => ['debug', '<?php \exit(1);', 'routes/console.php'],
    'debug: \die() in config' => ['debug', '<?php \die("x");', 'config/x.php'],
    'debug: \exit() in bootstrap' => ['debug', '<?php \exit(1);', 'bootstrap/app.php'],
    'debug: grouped use function' => ['debug', '<?php namespace App; use function {dd, dump}; dump($x);', 'app/X.php'],
    'debug: Benchmark::dd()' => ['debug', '<?php namespace App; \Illuminate\Support\Benchmark::dd(fn () => 1);', 'app/X.php'],
    'debug: nullsafe ?->dump()' => ['debug', '<?php namespace App; $q?->dump();', 'app/X.php'],
    'debug: ->dd() inside a heredoc' => ['debug', "<?php namespace App;\n\$s = <<<EOT\n{\$x->dd()}\nEOT;", 'app/X.php'],
    'debug: dd() after an attribute' => ['debug', '<?php namespace App; function f(#[\SensitiveParameter] $p) { dd($p); }', 'app/X.php'],
    // AC5
    'env: $_ENV inside a heredoc' => ['env', "<?php namespace App;\n\$s = <<<EOT\n{\$_ENV['X']}\nEOT;", 'app/X.php'],
    'env: grouped Env import' => ['env', '<?php namespace App; use Illuminate\Support\{Env}; Env::get("X");', 'app/X.php'],
    // AC6
    'tls: Http::globalOptions verify false' => ['tls', '<?php Http::globalOptions(["verify" => false]);', 'app/Providers/AppServiceProvider.php'],
    'tls: new \GuzzleHttp\Client verify false' => ['tls', '<?php namespace App; new \GuzzleHttp\Client(["verify" => false]);', 'app/X.php'],
    'tls: verify from config()' => ['tls', '<?php namespace App; Http::withOptions(["verify" => config("x.verify")]);', 'app/X.php'],
    // AC7: class names in double-quoted strings keep their backslashes in PHP ("\F", "\H", "\M" are not escapes).
    'csrf: web(remove:) with a double-quoted class string' => ['csrf', '<?php $middleware->web(remove: ["Illuminate\Foundation\Http\Middleware\ValidateCsrfToken"]);', 'bootstrap/app.php'],
    'csrf: withoutMiddleware with a double-quoted class string' => ['csrf', '<?php Route::post("/c", C::class)->withoutMiddleware("Illuminate\Foundation\Http\Middleware\PreventRequestForgery");', 'routes/web.php'],
    'csrf: web(replace:)' => ['csrf', '<?php $middleware->web(replace: [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => Noop::class]);', 'bootstrap/app.php'],
    'csrf: replaceInGroup' => ['csrf', '<?php $middleware->replaceInGroup("web", \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, Noop::class);', 'bootstrap/app.php'],
    'csrf: static except() through a grouped alias' => ['csrf', '<?php use Illuminate\Foundation\Http\Middleware\{PreventRequestForgery as P}; P::except(["chat"]);', 'bootstrap/app.php'],
    // AC8 / AC13
    'mail: container class string, double-quoted' => ['mail', '<?php namespace App; app("Illuminate\Contracts\Mail\Factory");', 'app/X.php'],
    'mail: Symfony class string, double-quoted' => ['mail', '<?php namespace App; app("Symfony\Component\Mailer\MailerInterface");', 'app/X.php'],
    'mail: Notification::route(mail)' => ['mail', '<?php namespace App; \Illuminate\Support\Facades\Notification::route("mail", "a@example.test")->notify($n);', 'app/X.php'],
    'mail: app(mail.manager)' => ['mail', '<?php namespace App; app("mail.manager")->mailer();', 'app/X.php'],
    'mail: Facades\Mail through a namespace import' => ['mail', '<?php namespace App; use Illuminate\Support\Facades; Facades\Mail::raw("x", fn () => null);', 'app/X.php'],
    'mail: toMail() with an attribute on the parameter' => ['mail', '<?php namespace App; class N { public function toMail(#[\SensitiveParameter] $n) { return null; } }', 'app/X.php'],
    // AC9
    'demo: config([...]) write' => ['demo', '<?php namespace App; config(["traveleasy.demo.enabled" => true]);', 'app/X.php'],
    'demo: Config::set' => ['demo', '<?php namespace App; \Illuminate\Support\Facades\Config::set("traveleasy.demo", true);', 'app/X.php'],
    'demo: container config repository' => ['demo', '<?php namespace App; $app["config"]->get("traveleasy");', 'app/X.php'],
    'demo: data_get over all config' => ['demo', '<?php namespace App; data_get(config()->all(), "traveleasy.demo");', 'app/X.php'],
]);
