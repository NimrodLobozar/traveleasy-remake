<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/*
| TE-001 scaffold contract: checks added by QA on top of the developers' tests.
| Covers the settings redirect (Route::get instead of Route::redirect), the app-level
| mail lock (AC10), the users table after removing email_verified_at, the exact
| auth.user shape (AC5), the .env.example rules (AC8, security QC) and removed pages (AC11/12).
*/

/** @return array<string, string> KEY => value pairs from .env.example */
function envExampleVariables(): array
{
    $vars = [];

    foreach (fileLines(base_path('.env.example')) as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m) === 1) {
            $vars[$m[1]] = $m[2];
        }
    }

    return $vars;
}

// --- Settings root redirect -------------------------------------------------

test('GET /settings redirects to the profile page with a 302', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertStatus(302)
        ->assertRedirect(route('profile.edit'));
});

test('HEAD /settings also redirects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->call('HEAD', '/settings')
        ->assertStatus(302);
});

test('other verbs on /settings return 405', function (string $method) {
    $user = User::factory()->create();

    $this->actingAs($user)->call($method, '/settings')->assertMethodNotAllowed();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

test('guests opening /settings are sent to the login page', function () {
    $this->get('/settings')->assertRedirect(route('login'));
});

// --- AC10: mail locked at application level ----------------------------------

test('MAIL_MAILER=smtp in the environment still boots the app with the array mailer', function () {
    $keys = ['MAIL_MAILER' => 'smtp', 'MAIL_HOST' => 'smtp.example.test', 'MAIL_PORT' => '2525'];
    $saved = [];

    foreach ($keys as $key => $value) {
        $saved[$key] = getenv($key);
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    try {
        $this->refreshApplication();

        expect(Env::get('MAIL_MAILER'))->toBe('smtp')
            ->and(config('mail.default'))->toBe('array')
            ->and(array_keys(config()->array('mail.mailers')))->toBe(['array']);
    } finally {
        foreach ($saved as $key => $value) {
            $value === false ? putenv($key) : putenv("{$key}={$value}");
            unset($_ENV[$key], $_SERVER[$key]);
        }
        $this->refreshApplication();
    }
});

// --- email_verified_at removal ---------------------------------------------

test('the users table has exactly the expected columns', function () {
    expect(Schema::getColumnListing('users'))->toEqualCanonicalizing([
        'id', 'name', 'email', 'password', 'remember_token', 'created_at', 'updated_at',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ]);
});

test('the user factory and model work without email_verified_at', function () {
    $user = User::factory()->create();

    expect($user->getCasts())->not->toHaveKey('email_verified_at')
        ->and($user->refresh()->getAttributes())->not->toHaveKey('email_verified_at');
});

test('a profile e-mail change is saved without any verification reset', function () {
    $user = User::factory()->create(['email' => 'old@example.test']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.test'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('new@example.test');
});

// --- AC5: exact auth.user shape --------------------------------------------

test('auth.user has exactly three keys: id, name and email', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', 3)
            ->has('auth.user.id')
            ->has('auth.user.name')
            ->has('auth.user.email'));
});

// --- AC11 / AC12: removed pages --------------------------------------------

test('the removed mail-flow pages do not exist and login has no forgot-password link', function () {
    foreach (['forgot-password', 'reset-password', 'verify-email'] as $page) {
        expect(file_exists(resource_path("js/pages/auth/{$page}.tsx")))->toBeFalse();
    }

    $login = fileContents(resource_path('js/pages/auth/login.tsx'));

    expect(strtolower($login))->not->toContain('forgot')
        ->and(strtolower($login))->not->toContain('wachtwoord vergeten')
        ->and($login)->not->toContain('request(');
});

// --- AC8 and security QC: .env.example -------------------------------------

test('.env.example targets PostgreSQL with an empty password', function () {
    expect(envExampleVariables())->toMatchArray([
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => 'db',
        'DB_PORT' => '5432',
        'DB_DATABASE' => 'traveleasy',
        'DB_USERNAME' => 'traveleasy',
        'DB_PASSWORD' => '',
        'APP_KEY' => '',
    ]);

    expect(file_exists(database_path('database.sqlite')))->toBeFalse();
});

test('.env.example has no mail, SMTP or Mailpit variables', function () {
    $content = fileContents(base_path('.env.example'));

    $mailish = array_filter(
        array_keys(envExampleVariables()),
        fn (string $k) => preg_match('/(^|_)(MAIL|SMTP|MAILPIT|MAILGUN|POSTMARK|RESEND|SES)(_|$)/', $k) === 1,
    );

    expect($mailish)->toBeEmpty()
        ->and(stripos($content, 'mailpit'))->toBeFalse()
        ->and(preg_match('/^[^#]*smtp/mi', $content))->toBe(0);
});

test('every variable in .env.example has a comment on the line above', function () {
    $lines = fileLines(base_path('.env.example'));
    $uncommented = [];

    foreach ($lines as $i => $line) {
        if (preg_match('/^[A-Z0-9_]+=/', $line) === 1) {
            $previous = $lines[$i - 1] ?? '';
            if (! str_starts_with(trim($previous), '#')) {
                $uncommented[] = $line;
            }
        }
    }

    expect($uncommented)->toBe([]);
});

test('APP_DEBUG=true is documented as dev-only with production false', function () {
    $lines = fileLines(base_path('.env.example'));
    $index = array_search('APP_DEBUG=true', $lines, true);
    $previous = is_int($index) ? ($lines[$index - 1] ?? '') : null;

    expect($previous)->toBeString()->toContain('APP_DEBUG=false');
});

// --- Security QC: no third-party font host in the HTML ---------------------

test('the root HTML references no external host', function () {
    $html = asString($this->get(route('home'))->assertOk()->getContent());

    preg_match_all('/(?:src|href)="((?:https?:)?\/\/[^"]+)"/i', $html, $matches);
    $foreignHosts = array_values(array_filter(
        array_map(fn (string $url) => parse_url($url, PHP_URL_HOST), $matches[1]),
        fn (string|false|null $host) => $host !== parse_url(config()->string('app.url'), PHP_URL_HOST),
    ));

    expect($html)->not->toContain('fonts.bunny.net')
        ->and($foreignHosts)->toBe([]);
});
