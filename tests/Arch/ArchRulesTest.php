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
    'csrf: subclass with empty $except' => ['csrf', '<?php namespace App\Http\Middleware; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; class Csrf extends PreventRequestForgery { protected $except = []; }'],
    'csrf: withoutMiddleware another middleware' => ['csrf', '<?php Route::get("/", C::class)->withoutMiddleware("throttle");', 'routes/web.php'],
    'mail: an e-mail field' => ['mail', '<?php $request->validate(["email" => "required|email"]);'],
    'mail: an App class named Mailbox' => ['mail', '<?php namespace App; use App\Support\Mailbox; new Mailbox;'],
    'mail: database notification channel' => ['mail', '<?php namespace App; class N { public function via($n) { return ["database"]; } }'],
    'demo: another traveleasy key' => ['demo', '<?php config("traveleasy.vat.rate");'],
    'demo: the DemoMode service' => ['demo', '<?php namespace App\Support; final class DemoMode { public function enabled(): bool { return config()->boolean("traveleasy.demo.enabled"); } }', ArchRules::DEMO_MODE_SERVICE],
    'demo: DEMO_MODE in config/traveleasy.php' => ['demo', '<?php return ["demo" => ["enabled" => (bool) env("DEMO_MODE", false)]];', ArchRules::DEMO_MODE_CONFIG],
    'demo: a database name' => ['demo', '<?php return ["database" => env("DB_DATABASE", "traveleasy")];', 'config/database.php'],
]);
