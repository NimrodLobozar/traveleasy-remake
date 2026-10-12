<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceFile;

/*
| TE-004 AC11 (permanent part): every file-scan rule is proven against violating snippets, including the
| bypass forms the security review tries (fully qualified names, aliased and grouped imports, real-time
| facades, class names in strings, the global facade alias, `use function ... as`), and against clean snippets
| so the rules don't fire on legitimate code. The temporary-fixture proof per rule is in the TE-004 handoff.
*/

/** @return list<string> */
function scanSnippet(string $rule, string $code, string $path = 'app/Snippet.php'): array
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

test('a rule catches a violating snippet', function (string $rule, string $code, string $path = 'app/Snippet.php') {
    expect(scanSnippet($rule, $code, $path))->not->toBe([]);
})->with([
    // AC4 debugging leftovers
    'debug: dd()' => ['debug', '<?php namespace App; dd($x);'],
    'debug: \dump()' => ['debug', '<?php namespace App; \dump($x);'],
    'debug: ray() in a route file' => ['debug', '<?php Route::get("/", fn () => ray(1));', 'routes/web.php'],
    'debug: var_dump() in config' => ['debug', '<?php return ["x" => var_dump(1)];', 'config/x.php'],
    'debug: print_r()' => ['debug', '<?php print_r($x, true);'],
    'debug: ddd()' => ['debug', '<?php ddd($x);'],
    'debug: exit' => ['debug', '<?php exit(1);'],
    'debug: die' => ['debug', '<?php die("x");'],
    'debug: use function as alias' => ['debug', '<?php namespace App; use function dd as inspect; inspect($x);'],
    'debug: ->dd() on a query' => ['debug', '<?php User::query()->where("a", 1)->dd();'],
    'debug: ->dump() on a collection' => ['debug', '<?php collect()->dump();'],
    'debug: echo' => ['debug', '<?php namespace App; echo $x;'],
    'debug: print' => ['debug', '<?php print $x;'],
    // AC5 env outside config
    'env: env()' => ['env', '<?php namespace App; $x = env("APP_KEY");'],
    'env: \env()' => ['env', '<?php namespace App; $x = \env("APP_KEY");'],
    'env: getenv()' => ['env', '<?php $x = getenv("APP_KEY");'],
    'env: Env::get via import' => ['env', '<?php namespace App; use Illuminate\Support\Env as E; E::get("X");'],
    'env: \Illuminate\Support\Env::get' => ['env', '<?php \Illuminate\Support\Env::get("X");'],
    'env: $_ENV' => ['env', '<?php $x = $_ENV["APP_KEY"];'],
    'env: $_SERVER' => ['env', '<?php $x = $_SERVER["DB_PASSWORD"];'],
    'env: in a route file' => ['env', '<?php Route::get("/", fn () => env("X"));', 'routes/web.php'],
    // AC6 TLS
    'tls: Http::withoutVerifying()' => ['tls', '<?php Http::withoutVerifying()->get("https://x");'],
    'tls: ->withoutVerifying()' => ['tls', '<?php $client->withoutVerifying();'],
    'tls: verify false' => ['tls', '<?php Http::withOptions(["verify" => false]);'],
    'tls: verify 0' => ['tls', '<?php new Client(["verify" => 0]);'],
    'tls: verify from a variable' => ['tls', '<?php new Client(["verify" => $verify]);'],
    'tls: RequestOptions::VERIFY false' => ['tls', '<?php new Client([RequestOptions::VERIFY => false]);'],
    'tls: verify_peer false' => ['tls', '<?php stream_context_create(["ssl" => ["verify_peer" => false]]);'],
    'tls: allow_self_signed true' => ['tls', '<?php stream_context_create(["ssl" => ["allow_self_signed" => true]]);'],
    'tls: CURLOPT_SSL_VERIFYPEER' => ['tls', '<?php curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);'],
    'tls: in config' => ['tls', '<?php return ["guzzle" => ["verify" => false]];', 'config/services.php'],
    // AC7 CSRF
    'csrf: validateCsrfTokens(except:)' => ['csrf', '<?php $middleware->validateCsrfTokens(except: ["chatbot/*"]);', 'bootstrap/app.php'],
    'csrf: preventRequestForgery(except:)' => ['csrf', '<?php $middleware->preventRequestForgery(except: ["chatbot/*"]);', 'bootstrap/app.php'],
    'csrf: preventRequestForgery(originOnly:)' => ['csrf', '<?php $middleware->preventRequestForgery(originOnly: true);', 'bootstrap/app.php'],
    'csrf: withoutMiddleware(class)' => ['csrf', '<?php use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; Route::post("/c", C::class)->withoutMiddleware(PreventRequestForgery::class);', 'routes/web.php'],
    'csrf: withoutMiddleware(FQ ValidateCsrfToken)' => ['csrf', '<?php Route::post("/c", C::class)->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);', 'routes/web.php'],
    'csrf: withoutMiddleware(aliased VerifyCsrfToken)' => ['csrf', '<?php use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as V; Route::withoutMiddleware(V::class)->group(fn () => null);', 'routes/web.php'],
    'csrf: withoutMiddleware(class string)' => ['csrf', '<?php Route::post("/c", C::class)->withoutMiddleware("Illuminate\\\\Foundation\\\\Http\\\\Middleware\\\\PreventRequestForgery");', 'routes/web.php'],
    'csrf: withoutMiddleware("web")' => ['csrf', '<?php Route::post("/c", C::class)->withoutMiddleware("web");', 'routes/web.php'],
    'csrf: web(remove:)' => ['csrf', '<?php use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; $middleware->web(remove: [PreventRequestForgery::class]);', 'bootstrap/app.php'],
    'csrf: static except()' => ['csrf', '<?php \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::except(["chatbot"]);'],
    'csrf: subclass $except' => ['csrf', '<?php namespace App\Http\Middleware; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Base; class Csrf extends Base { protected $except = ["chatbot/*"]; }'],
    'csrf: subclass overrides tokensMatch' => ['csrf', '<?php namespace App\Http\Middleware; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; class Csrf extends PreventRequestForgery { protected function tokensMatch($r) { return true; } }'],
    // AC8 / AC13 mail
    'mail: Mail facade import' => ['mail', '<?php namespace App; use Illuminate\Support\Facades\Mail; Mail::to($u)->send($m);'],
    'mail: aliased Mail facade' => ['mail', '<?php namespace App; use Illuminate\Support\Facades\Mail as M; M::raw("x", fn () => null);'],
    'mail: grouped import' => ['mail', '<?php namespace App; use Illuminate\Support\Facades\{Log, Mail as Post}; Post::send([], [], fn () => null);'],
    'mail: fully qualified facade' => ['mail', '<?php namespace App; \Illuminate\Support\Facades\Mail::mailer("smtp")->raw("x", fn () => null);'],
    'mail: lower-case fully qualified facade' => ['mail', '<?php namespace App; \illuminate\support\facades\mail::raw("x", fn () => null);'],
    'mail: Mail::build on-demand mailer' => ['mail', '<?php \Illuminate\Support\Facades\Mail::build(["transport" => "smtp"])->raw("x", fn () => null);'],
    'mail: global Mail alias' => ['mail', '<?php namespace App; \Mail::raw("x", fn () => null);'],
    'mail: global Mail alias in a route file' => ['mail', '<?php Route::get("/", fn () => Mail::raw("x", fn () => null));', 'routes/web.php'],
    'mail: real-time facade' => ['mail', '<?php namespace App; use Facades\Illuminate\Mail\Mailer; Mailer::raw("x");'],
    'mail: extends Mailable' => ['mail', '<?php namespace App\Mail; use Illuminate\Mail\Mailable; class Welcome extends Mailable {}'],
    'mail: Illuminate\Mail namespace' => ['mail', '<?php namespace App; use Illuminate\Mail\Mailables\Envelope; new Envelope();'],
    'mail: Mailer contract' => ['mail', '<?php namespace App; use Illuminate\Contracts\Mail\Mailer; function f(Mailer $m) {}'],
    'mail: MailMessage' => ['mail', '<?php namespace App; use Illuminate\Notifications\Messages\MailMessage; new MailMessage;'],
    'mail: Symfony Transport' => ['mail', '<?php namespace App; use Symfony\Component\Mailer\Transport; Transport::fromDsn("smtp://x");'],
    'mail: Symfony class in a string' => ['mail', '<?php app("Symfony\\\\Component\\\\Mailer\\\\Mailer");'],
    'mail: toMail()' => ['mail', '<?php namespace App; class N { public function toMail($n) { return null; } }'],
    'mail: via() returns mail' => ['mail', '<?php namespace App; class N { public function via($n) { return ["mail"]; } }'],
    'mail: on-demand route' => ['mail', '<?php Notification::route("mail", "a@b.test")->notify($n);'],
    'mail: container key' => ['mail', '<?php app("mailer")->raw("x", fn () => null);'],
    'mail: mail.manager' => ['mail', '<?php resolve("mail.manager");'],
    'mail: PHP mail()' => ['mail', '<?php mail("a@b.test", "s", "b");'],
    'mail: in config' => ['mail', '<?php return ["x" => \Illuminate\Support\Facades\Mail::class];', 'config/x.php'],
    // AC9 demo flag
    'demo: config()' => ['demo', '<?php namespace App; config("traveleasy.demo.enabled");'],
    'demo: Config::get' => ['demo', '<?php namespace App; use Illuminate\Support\Facades\Config; Config::get("traveleasy.demo.enabled");'],
    'demo: config()->boolean' => ['demo', '<?php config()->boolean("traveleasy.demo.enabled");'],
    'demo: whole demo array' => ['demo', '<?php config("traveleasy.demo")["enabled"];'],
    'demo: whole traveleasy array' => ['demo', '<?php config("traveleasy")["demo"]["enabled"];'],
    'demo: array access' => ['demo', '<?php app("config")["traveleasy.demo.enabled"];'],
    'demo: env(DEMO_MODE)' => ['demo', '<?php env("DEMO_MODE");', 'config/app.php'],
    'demo: getenv(DEMO_MODE)' => ['demo', '<?php getenv("DEMO_MODE");'],
    'demo: $_SERVER[DEMO_MODE]' => ['demo', '<?php $_SERVER["DEMO_MODE"];'],
    'demo: a file called DemoMode.php elsewhere' => ['demo', '<?php config("traveleasy.demo.enabled");', 'app/Http/DemoMode.php'],
    // --- Rework round 1 -------------------------------------------------------------------------------------
    // AC4: every spelling of exit/die and the extended function list (QA bug 1, security L3)
    'debug r1: \exit() in app' => ['debug', '<?php namespace App; \exit(1);'],
    'debug r1: \die() in a route file' => ['debug', '<?php \die("x");', 'routes/web.php'],
    'debug r1: exit; construct' => ['debug', '<?php exit;'],
    'debug r1: die; construct' => ['debug', '<?php die;'],
    'debug r1: var_export()' => ['debug', '<?php namespace App; var_export($x);'],
    'debug r1: printf()' => ['debug', '<?php printf("%s", $x);', 'config/x.php'],
    'debug r1: \vprintf()' => ['debug', '<?php namespace App; \vprintf("%s", [$x]);'],
    // AC6: assignment form (security M2)
    'tls r1: $options[verify] = false' => ['tls', '<?php $options["verify"] = false;'],
    'tls r1: nested verify_peer = false' => ['tls', "<?php \$ctx['ssl']['verify_peer'] = false;"],
    'tls r1: verify_peer_name = 0' => ['tls', "<?php \$ctx['ssl']['verify_peer_name'] = 0;"],
    'tls r1: allow_self_signed = true' => ['tls', "<?php \$ctx['ssl']['allow_self_signed'] = true;"],
    'tls r1: $o[RequestOptions::VERIFY] = false' => ['tls', '<?php $o[RequestOptions::VERIFY] = false;'],
    'tls r1: verify from a variable (assignment)' => ['tls', '<?php $o["verify"] = $flag;'],
    'tls r1: verify_peer with a path is not allowed' => ['tls', '<?php ["verify_peer" => "/etc/ssl/ca.pem"];'],
    // AC7: no subclass, no group without CSRF, every string form (security M1, QA bug 2)
    'csrf r1: subclass with empty $except' => ['csrf', '<?php namespace App\Http\Middleware; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; class Csrf extends PreventRequestForgery { protected $except = []; }'],
    'csrf r1: subclass overriding handle()' => ['csrf', '<?php namespace App\Http\Middleware; class Csrf extends \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken { public function handle($r, $n) { return $n($r); } }'],
    'csrf r1: subclass pushing to except in the constructor' => ['csrf', '<?php namespace App\Http\Middleware; use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken; class Csrf extends ValidateCsrfToken { public function __construct() { array_push($this->except, "chat"); } }'],
    'csrf r1: redefined web group' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->group("web", [StartSession::class]);', 'bootstrap/app.php'],
    'csrf r1: router middlewareGroup(web)' => ['csrf', '<?php namespace App\Providers; $router->middlewareGroup("web", []);', 'app/Providers/RouteServiceProvider.php'],
    'csrf r1: custom session group without CSRF' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->group("portal", [\Illuminate\Cookie\Middleware\EncryptCookies::class, StartSession::class]);', 'bootstrap/app.php'],
    'csrf r1: custom session group as a string without CSRF' => ['csrf', '<?php $middleware->group("portal", ["Illuminate\Session\Middleware\StartSession"]);', 'bootstrap/app.php'],
    'csrf r1: single-quoted class string' => ['csrf', "<?php Route::post('/c', C::class)->withoutMiddleware('Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken');", 'routes/web.php'],
    'csrf r1: heredoc class string' => ['csrf', "<?php Route::post('/c', C::class)->withoutMiddleware(<<<MW\n    Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery\n    MW);", 'routes/web.php'],
    'csrf r1: nowdoc class string' => ['csrf', "<?php Route::post('/c', C::class)->withoutMiddleware(<<<'MW'\nIlluminate\\Foundation\\Http\\Middleware\\PreventRequestForgery\nMW);", 'routes/web.php'],
    // AC8 / AC13 (QA bug 2, security L1)
    'mail r1: Password::sendResetLink' => ['mail', '<?php namespace App; use Illuminate\Support\Facades\Password; Password::sendResetLink(["email" => $e]);'],
    'mail r1: broker()->sendResetLink' => ['mail', '<?php namespace App; Password::broker("users")->sendResetLink($credentials);'],
    'mail r1: routeNotificationForMail()' => ['mail', '<?php namespace App\Models; class User { public function routeNotificationForMail($n) { return $this->email; } }'],
    'mail r1: single-quoted container class' => ['mail', "<?php app('Illuminate\\Contracts\\Mail\\Factory');"],
    'mail r1: resolve() of a mail class string' => ['mail', '<?php resolve("Illuminate\Mail\MailManager");'],
    'mail r1: nowdoc container key' => ['mail', "<?php app(<<<'X'\nmail.manager\nX);"],
    'mail r1: indented heredoc container key' => ['mail', "<?php app(<<<X\n    mailer\n    X);"],
    // AC9: named and positional arguments (security L2)
    'demo r1: Config::get(key:)' => ['demo', '<?php namespace App; Config::get(key: "traveleasy.demo.enabled");'],
    'demo r1: config(key:) in a config file' => ['demo', '<?php return ["x" => config(key: "traveleasy.demo.enabled")];', 'config/app.php'],
    'demo r1: Arr::get(config()->all(), key)' => ['demo', '<?php namespace App; Arr::get(config()->all(), "traveleasy.demo.enabled");'],
    'demo r1: data_get(config()->all(), traveleasy)' => ['demo', '<?php namespace App; data_get(config()->all(), "traveleasy")["demo"];'],
    'demo r1: heredoc key' => ['demo', "<?php namespace App; config(<<<'K'\ntraveleasy.demo.enabled\nK);"],
]);

test('a rule leaves legitimate code alone', function (string $rule, string $code, string $path = 'app/Snippet.php') {
    expect(scanSnippet($rule, $code, $path))->toBe([]);
})->with([
    'debug: a method named dumpData' => ['debug', '<?php $x->dumpData();'],
    'debug: dd in a comment' => ['debug', "<?php // dd(\$x);\n/* exit; */"],
    'debug: echo in a compiled Blade view' => ['debug', '<p><?php echo e($name); ?></p>', 'resources/views/x.blade.php'],
    'env: env() in config' => ['env', '<?php return ["key" => config("app.key")];'],
    'env: @env Blade-style method' => ['env', '<?php app()->environment("local");'],
    'tls: verify true' => ['tls', '<?php Http::withOptions(["verify" => true]);'],
    'tls: verify with a CA bundle' => ['tls', '<?php Http::withOptions(["verify" => "/etc/ssl/certs/ca.pem"]);'],
    'csrf: validateCsrfTokens()' => ['csrf', '<?php $middleware->validateCsrfTokens();', 'bootstrap/app.php'],
    'csrf: empty except' => ['csrf', '<?php $middleware->preventRequestForgery(except: []);', 'bootstrap/app.php'],
    'csrf: withoutMiddleware another middleware' => ['csrf', '<?php Route::get("/", C::class)->withoutMiddleware("throttle");', 'routes/web.php'],
    'mail: an e-mail field' => ['mail', '<?php $request->validate(["email" => "required|email"]);'],
    'mail: an App class named Mailbox' => ['mail', '<?php namespace App; use App\Support\Mailbox; new Mailbox;'],
    'mail: database notification channel' => ['mail', '<?php namespace App; class N { public function via($n) { return ["database"]; } }'],
    'demo: another traveleasy key' => ['demo', '<?php config("traveleasy.vat.rate");'],
    'demo: the DemoMode service' => ['demo', '<?php namespace App\Support; final class DemoMode { public function enabled(): bool { return config()->boolean("traveleasy.demo.enabled"); } }', ArchRules::DEMO_MODE_SERVICE],
    'demo: DEMO_MODE in config/traveleasy.php' => ['demo', '<?php return ["demo" => ["enabled" => (bool) env("DEMO_MODE", false)]];', ArchRules::DEMO_MODE_CONFIG],
    'demo: a database name' => ['demo', '<?php return ["database" => env("DB_DATABASE", "traveleasy")];', 'config/database.php'],
    // --- Rework round 1 -------------------------------------------------------------------------------------
    'debug r1: a method named printf' => ['debug', '<?php $formatter->printf("%s", $x);'],
    'tls r1: $options[verify] = true' => ['tls', '<?php $options["verify"] = true;'],
    'tls r1: verify = CA bundle path' => ['tls', '<?php $options["verify"] = "/etc/ssl/certs/ca.pem";'],
    'tls r1: allow_self_signed = false' => ['tls', "<?php \$ctx['ssl']['allow_self_signed'] = false;"],
    'tls r1: an unrelated array key' => ['tls', '<?php $data["verified"] = false;'],
    'csrf r1: custom session group with CSRF' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; $middleware->group("portal", [StartSession::class, PreventRequestForgery::class]);', 'bootstrap/app.php'],
    'csrf r1: api group without session' => ['csrf', '<?php $middleware->group("api", [Throttle::class]);', 'bootstrap/app.php'],
    'csrf r1: appending to the web group' => ['csrf', '<?php $middleware->appendToGroup("web", HandleInertiaRequests::class);', 'bootstrap/app.php'],
    'csrf r1: web(append:)' => ['csrf', '<?php $middleware->web(append: [HandleAppearance::class]);', 'bootstrap/app.php'],
    'mail r1: an unrelated class string with escaped backslashes' => ['mail', "<?php app('Illuminate\\\\Support\\\\Str');"],
    'demo r1: a demo key as a default in config' => ['demo', '<?php return ["x" => env("X", "traveleasy.demo.example")];', 'config/x.php'],
    'demo r1: an unrelated traveleasy key, positional' => ['demo', '<?php namespace App; Arr::get(config()->all(), "traveleasy.vat.rate");'],
]);

// Rework round 1 (QA bug 2, AC7 c / AC11): string values are unescaped exactly as PHP does. The expected values
// below are PHP literals in this file, so PHP itself computes them; the source text is fed to the scanner.
test('string literals are unescaped exactly as PHP does', function (string $source, string $expected) {
    $file = new SourceFile('app/Snippet.php', "<?php\n\$x = {$source};\n");

    expect(array_column($file->strings, 'value'))->toBe([$expected]);
})->with([
    'double quotes keep unknown escapes' => ['"Illuminate\Contracts\Mail\Factory"', "Illuminate\Contracts\Mail\Factory"],
    'double quotes: \f \F \\\\ \$ \"' => ['"a\f\F\\\\\$\"b"', "a\f\F\\\$\"b"],
    'double quotes: octal, hex, unicode' => ['"\101\x42\u{43}\8\xZ"', "\101\x42\u{43}\8\xZ"],
    'single quotes: only \\\\ and \\\'' => ["'a\\\\b\\'c\\nd\\Me'", 'a\\b\'c\nd\Me'],
    'heredoc: escapes, \" kept, trailing newline trimmed' => ["<<<X\na\\\"b\\tc\\Id\nX", <<<X
a\"b\tc\Id
X],
    'indented heredoc' => ["<<<X\n    one\n      two\n    X", <<<'X'
    one
      two
    X],
    'nowdoc: raw' => ["<<<'X'\na\\tb\\\\c\nX", <<<'X'
a\tb\\c
X],
]);

test('an App class that extends the CSRF middleware through another class is found across files', function () {
    $base = new SourceFile('app/Http/Middleware/BaseCsrf.php', '<?php namespace App\Http\Middleware; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; class BaseCsrf extends PreventRequestForgery {}');
    $child = new SourceFile('app/Http/Middleware/ChatCsrf.php', '<?php namespace App\Http\Middleware; class ChatCsrf extends BaseCsrf { protected $except = ["chat/*"]; }');
    $unrelated = new SourceFile('app/Http/Middleware/Other.php', '<?php namespace App\Http\Middleware; class Other extends \Illuminate\Routing\Controller {}');

    expect(ArchRules::csrfSubclasses([$base, $child, $unrelated]))->toBe([
        'app/Http/Middleware/ChatCsrf.php App\Http\Middleware\ChatCsrf extends the CSRF middleware indirectly (via app\http\middleware\basecsrf)',
    ]);
});

test('the guard contract rule catches ways to boot the app without the guard', function (string $code) {
    expect(ArchRules::guardBypasses(new SourceFile('tests/Unit/X.php', $code)))->not->toBe([]);
})->with([
    'console kernel' => ['<?php use Illuminate\Contracts\Console\Kernel; app(Kernel::class)->bootstrap();'],
    'http kernel' => ['<?php $k = app(\Illuminate\Contracts\Http\Kernel::class);'],
    'new Application' => ['<?php $app = new Illuminate\Foundation\Application(__DIR__);'],
    'Application as a string' => ['<?php app("Illuminate\Foundation\Application");'],
    'require bootstrap/app.php' => ['<?php $app = require __DIR__."/../../bootstrap/app.php";'],
    'require base_path(bootstrap/app.php)' => ['<?php $app = require base_path("bootstrap/app.php");'],
    'include_once' => ['<?php include_once "bootstrap/app.php";'],
]);

test('the guard contract rule leaves a virtual snippet path alone', function () {
    expect(ArchRules::guardBypasses(new SourceFile('tests/Unit/X.php', '<?php $cases = [["debug", "<?php exit;", "bootstrap/app.php"]];')))->toBe([]);
});
