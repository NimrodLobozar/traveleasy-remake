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
    // --- Rework round 2 -------------------------------------------------------------------------------------
    // AC6: ??=, setter calls, verify_host (QA, security L-b)
    'tls r2: ??= false' => ['tls', '<?php $options["verify"] ??= false;'],
    'tls r2: nested ??= on verify_peer' => ['tls', "<?php \$ctx['ssl']['verify_peer'] ??= false;"],
    'tls r2: Arr::set(verify, false)' => ['tls', '<?php namespace App; use Illuminate\Support\Arr; Arr::set($options, "verify", false);'],
    'tls r2: Arr::set(dotted verify_peer, false)' => ['tls', '<?php Arr::set($options, "ssl.verify_peer", false);'],
    'tls r2: data_set(verify, false)' => ['tls', '<?php data_set($options, "verify", false);'],
    'tls r2: data_set with named arguments' => ['tls', '<?php data_set(target: $options, key: "verify", value: false);'],
    'tls r2: stream_context_set_option(verify_peer, false)' => ['tls', '<?php stream_context_set_option($context, "ssl", "verify_peer", false);'],
    'tls r2: \stream_context_set_option(verify_peer_name, 0)' => ['tls', '<?php namespace App; \stream_context_set_option($context, "ssl", "verify_peer_name", 0);'],
    'tls r2: verify_host false' => ['tls', '<?php ["verify_host" => false];'],
    'tls r2: verify_host = 0' => ['tls', '<?php $options["verify_host"] = 0;'],
    // AC7: named-argument groups, sessions added to groups, CSRF calls inside group arguments (QA, security L-a)
    'csrf r2: group(name: web)' => ['csrf', '<?php $middleware->group(name: "web", middleware: []);', 'bootstrap/app.php'],
    'csrf r2: group(group: web)' => ['csrf', '<?php $middleware->group(group: "web", middleware: []);', 'bootstrap/app.php'],
    'csrf r2: middlewareGroup(name: web)' => ['csrf', '<?php $router->middlewareGroup(name: "web", middleware: []);', 'app/Providers/X.php'],
    'csrf r2: session group, arguments swapped by name' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->group(middleware: [StartSession::class], group: "chat");', 'bootstrap/app.php'],
    'csrf r2: appendToGroup(session) without CSRF' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->appendToGroup("api", StartSession::class);', 'bootstrap/app.php'],
    'csrf r2: prependToGroup(session) without CSRF' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->prependToGroup("chat", [StartSession::class]);', 'bootstrap/app.php'],
    'csrf r2: router pushMiddlewareToGroup(session)' => ['csrf', '<?php $router->pushMiddlewareToGroup("chat", \Illuminate\Session\Middleware\StartSession::class);', 'app/Providers/X.php'],
    'csrf r2: api(append: session)' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->api(append: [StartSession::class]);', 'bootstrap/app.php'],
    'csrf r2: api(prepend: session)' => ['csrf', '<?php $middleware->api(prepend: ["Illuminate\Session\Middleware\StartSession"]);', 'bootstrap/app.php'],
    'csrf r2: api(session) positional' => ['csrf', '<?php $middleware->api([\Illuminate\Session\Middleware\StartSession::class]);', 'bootstrap/app.php'],
    'csrf r2: except() inside a group definition' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as P; $middleware->group("chat", [StartSession::class, P::class, P::except(["chat/*"])]);', 'bootstrap/app.php'],
    'csrf r2: useOriginOnly() inside appendToGroup' => ['csrf', '<?php use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as P; $middleware->appendToGroup("chat", [P::class, P::useOriginOnly()]);', 'bootstrap/app.php'],
    'csrf r2: allowSameSite() inside api(append:)' => ['csrf', '<?php use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as P; $middleware->api(append: [P::allowSameSite()]);', 'bootstrap/app.php'],
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
    // --- Rework round 2 -------------------------------------------------------------------------------------
    'tls r2: ??= true' => ['tls', '<?php $options["verify"] ??= true;'],
    'tls r2: Arr::set(verify, true)' => ['tls', '<?php Arr::set($options, "verify", true);'],
    'tls r2: data_set(verify, CA bundle)' => ['tls', '<?php data_set($options, "verify", "/etc/ssl/certs/ca.pem");'],
    'tls r2: stream_context_set_option(verify_peer, true)' => ['tls', '<?php stream_context_set_option($context, "ssl", "verify_peer", true);'],
    'tls r2: an unrelated setter' => ['tls', '<?php $config->set("app.name", false);'],
    'csrf r2: group(name: chat) with session and CSRF' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; $middleware->group(name: "chat", middleware: [StartSession::class, PreventRequestForgery::class]);', 'bootstrap/app.php'],
    'csrf r2: appendToGroup(session + CSRF)' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; use Illuminate\Foundation\Http\Middleware\PreventRequestForgery; $middleware->appendToGroup("chat", [StartSession::class, PreventRequestForgery::class]);', 'bootstrap/app.php'],
    'csrf r2: appendToGroup(web, session)' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->appendToGroup("web", StartSession::class);', 'bootstrap/app.php'],
    'csrf r2: web(append: session)' => ['csrf', '<?php use Illuminate\Session\Middleware\StartSession; $middleware->web(append: [StartSession::class]);', 'bootstrap/app.php'],
    'csrf r2: api(append: throttle)' => ['csrf', '<?php $middleware->api(append: [Throttle::class]);', 'bootstrap/app.php'],
    'csrf r2: CSRF class string inside a group' => ['csrf', '<?php $middleware->group("chat", ["Illuminate\Session\Middleware\StartSession", "Illuminate\Foundation\Http\Middleware\PreventRequestForgery"]);', 'bootstrap/app.php'],
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
    // Rework round 2 (security L-c)
    'Foundation console kernel' => ['<?php $k = app(\Illuminate\Foundation\Console\Kernel::class);'],
    'Foundation http kernel' => ['<?php use Illuminate\Foundation\Http\Kernel; app(Kernel::class);'],
    'a bootstrapper' => ['<?php (new \Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);'],
    'ApplicationBuilder' => ['<?php use Illuminate\Foundation\Configuration\ApplicationBuilder; new ApplicationBuilder($app);'],
    'bootstrapWith()' => ['<?php $app->bootstrapWith([]);'],
    'require of a variable' => ['<?php $app = require $path;'],
    'require_once of a constant' => ['<?php require_once APP_BOOTSTRAP;'],
    'include of a class constant' => ['<?php include self::BOOTSTRAP;'],
]);

test('the guard contract rule leaves a virtual snippet path alone', function () {
    expect(ArchRules::guardBypasses(new SourceFile('tests/Unit/X.php', '<?php $cases = [["debug", "<?php exit;", "bootstrap/app.php"]];')))->toBe([]);
});

// Rework round 3 (QA AC3, security L-g): adversarial require/include forms. Every one either fails, or is
// literal-only (string literals without interpolation, __DIR__, `.`, parentheses) and doesn't name bootstrap/app.php.
test('the guard contract rule catches every non-literal or bootstrap/app.php load', function (string $code) {
    expect(ArchRules::guardBypasses(new SourceFile('tests/Unit/X.php', $code)))->not->toBe([]);
})->with([
    'require($path)' => ['<?php $app = require($path);'],
    'require_once($path)' => ['<?php require_once($path);'],
    'require ($path) with a space' => ['<?php $app = require ($path);'],
    'include_once($path)' => ['<?php include_once($path);'],
    'require __DIR__.$file' => ['<?php $app = require __DIR__.$file;'],
    'require dirname(__DIR__, 2).$f' => ['<?php $app = require dirname(__DIR__, 2).$f;'],
    'require base_path($file)' => ['<?php $app = require base_path($file);'],
    'require config_path($file)' => ['<?php $c = require config_path($file);'],
    'require __FILE__' => ['<?php require __FILE__;'],
    'include $x ?? a.php' => ['<?php include $x ?? "a.php";'],
    'require (fn () => $p)()' => ['<?php $app = require (fn () => $p)();'],
    'require static::$path' => ['<?php $app = require static::$path;'],
    'require self::PATH' => ['<?php $app = require self::PATH;'],
    'require across lines' => ["<?php \$app = require\n    \$path;"],
    '(require $p)[x]' => ['<?php $x = (require $p)["x"];'],
    'require inside an array' => ['<?php $all = ["app" => require $p, "x" => 1];'],
    'interpolated string' => ['<?php $app = require "{$root}/bootstrap/app.php";'],
    'interpolated string, other file' => ['<?php $c = require "{$root}/config/app.php";'],
    'interpolated heredoc' => ["<?php \$app = require <<<P\n{\$root}/config/app.php\nP;"],
    'literal nowdoc to bootstrap/app.php' => ["<?php \$app = require <<<'P'\n/var/www/html/bootstrap/app.php\nP;"],
    'literal __DIR__ path to bootstrap/app.php' => ['<?php $app = require __DIR__."/../../bootstrap/app.php";'],
    'joined literal pieces (L-g)' => ['<?php $app = require __DIR__."/../../bootstrap/"."app.php";'],
    'joined literal pieces, split differently' => ["<?php \$app = require __DIR__.'/../../bootstrap'.'/app'.'.php';"],
    'backslash separators' => ["<?php \$app = require __DIR__.'\\\\..\\\\..\\\\bootstrap\\\\app.php';"],
    'PhpFile::returnValue(bootstrap/app.php)' => ['<?php $app = \Tests\Support\PhpFile::returnValue(__DIR__."/../../bootstrap/app.php");'],
    'PhpFile::returnValue(path: bootstrap/app.php)' => ['<?php use Tests\Support\PhpFile; $app = PhpFile::returnValue(path: __DIR__."/../../bootstrap/"."app.php");'],
    'base_path(bootstrap/app.php) outside require' => ['<?php $p = base_path("bootstrap/app.php");'],
    '->handleCommand()' => ['<?php $app->handleCommand(new ArgvInput);'],
    '->handleRequest()' => ['<?php $app->handleRequest(Request::capture());'],
]);

test('the guard contract rule leaves literal-only paths to other files alone', function (string $code) {
    expect(ArchRules::guardBypasses(new SourceFile('tests/Unit/X.php', $code)))->toBe([]);
})->with([
    'require_once(__DIR__./x.php)' => ["<?php require_once(__DIR__.'/x.php');"],
    'require __DIR__./fixtures/data.php' => ['<?php $d = require __DIR__."/fixtures/data.php";'],
    'include a.php' => ["<?php include 'helpers.php';"],
    'literal nowdoc path' => ["<?php \$d = require <<<'P'\n/tmp/data.php\nP;"],
    'literal in an array, followed by other items' => ["<?php \$all = ['a' => require __DIR__.'/a.php', 'b' => \$x];"],
    '(require literal)[x]' => ["<?php \$x = (require __DIR__.'/a.php')['x'];"],
    'PhpFile::returnValue of a variable (refused at runtime if not allowed)' => ['<?php $c = \Tests\Support\PhpFile::returnValue($path);'],
    'a bootstrap/app.php virtual path in a dataset' => ['<?php $cases = [["debug", "<?php exit;", "bootstrap/app.php"]];'],
]);
