<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSecrets;
use App\Support\Console\Terminal;
use App\Support\Console\TerminalQrCode;
use App\Support\Deploy\SecretsGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Tests\TestCase;

/*
| TE-025 secrets helper: `php artisan traveleasy:generate-secrets` (ADR-0002 D5: five secrets).
| AC3: ready-to-paste KEY=value lines: two independent URL-safe DB passwords (>= 32 chars), APP_KEY (base64, 32 bytes),
| and the super_admin password hash (hidden prompt) and TOTP secret (QR + text). AC4: different every run, nothing written
| to disk, nothing logged. AC5: without a terminal it fails clearly; passwords are never arguments.
| Quality: CSPRNG only; refuses CI logs unless --force-ci; output safe for Portainer and compose env files.
| Source: docs/plan.md § 4 M0 "One-file Portainer deploy"; the production-image run is covered by docker/prod/verify.sh.
*/

const TE025_PASSWORD = 'Correct-Horse-9-Battery';

function te025Terminal(bool $interactive): void
{
    app()->instance(Terminal::class, new class($interactive) extends Terminal
    {
        public function __construct(private readonly bool $interactive) {}

        public function hasInteractiveInput(): bool
        {
            return $this->interactive;
        }
    });
}

/**
 * Run the command without prompts and return its KEY=value lines.
 *
 * @param  array<string, mixed>  $options
 * @return array<string, string>
 */
function te025Lines(array $options = ['--without-super-admin' => true]): array
{
    expect(Artisan::call('traveleasy:generate-secrets', $options))->toBe(0);

    $lines = [];

    foreach (explode("\n", Artisan::output()) as $line) {
        if (preg_match('/\A([A-Z_]+)=(.*)\z/', $line, $match) === 1) {
            $lines[$match[1]] = $match[2];
        }
    }

    return $lines;
}

/** @param  array<string, mixed>  $options */
function te025Command(TestCase $case, array $options = []): PendingCommand
{
    $command = $case->artisan('traveleasy:generate-secrets', $options);

    if (! $command instanceof PendingCommand) {
        throw new RuntimeException('Expected a pending command; console output mocking is off.');
    }

    return $command;
}

beforeEach(function () {
    config(['app.ci' => false]);
    te025Terminal(true);
});

test('AC3 without the super_admin part it prints two DB passwords and an APP_KEY', function () {
    $lines = te025Lines();

    expect(array_keys($lines))->toBe(['DB_SUPERUSER_PASSWORD', 'DB_PASSWORD', 'APP_KEY'])
        ->and($lines['DB_SUPERUSER_PASSWORD'])->toMatch('/\A[A-Za-z0-9_-]{43}\z/')
        ->and($lines['DB_PASSWORD'])->toMatch('/\A[A-Za-z0-9_-]{43}\z/')
        ->and($lines['DB_PASSWORD'])->not->toBe($lines['DB_SUPERUSER_PASSWORD'])
        ->and($lines['APP_KEY'])->toStartWith('base64:')
        ->and(strlen((string) base64_decode(substr($lines['APP_KEY'], 7), true)))->toBe(32);
});

test('AC3 --without-super-admin needs no terminal', function () {
    te025Terminal(false);

    expect(te025Lines())->toHaveCount(3);
});

test('AC3 the full run asks for the password hidden and prints all five secrets with a QR code', function () {
    te025Command($this)
        ->expectsQuestion('super_admin password', TE025_PASSWORD)
        ->expectsQuestion('Repeat the password', TE025_PASSWORD)
        ->expectsOutputToContain('Scan this QR code')
        ->expectsOutputToContain("\u{2588}")
        ->expectsOutputToContain('Or enter this secret by hand')
        ->expectsOutputToContain('DB_SUPERUSER_PASSWORD=')
        ->expectsOutputToContain('DB_PASSWORD=')
        ->expectsOutputToContain('APP_KEY=base64:')
        ->expectsOutputToContain('SUPER_ADMIN_PASSWORD_HASH=base64:')
        ->expectsOutputToContain('SUPER_ADMIN_TOTP_SECRET=')
        ->doesntExpectOutputToContain(TE025_PASSWORD)
        ->assertExitCode(0);
});

test('AC4 two runs give different values', function () {
    $first = te025Lines();
    $second = te025Lines();

    foreach ($first as $key => $value) {
        expect($second[$key])->not->toBe($value);
    }
});

test('AC4 nothing is written to disk and nothing is logged', function () {
    Event::fake([MessageLogged::class]);
    $files = Mockery::spy(Filesystem::class);
    app()->instance('files', $files);
    app()->instance(Filesystem::class, $files);
    Storage::fake('local');

    te025Lines();
    te025Command($this)
        ->expectsQuestion('super_admin password', TE025_PASSWORD)
        ->expectsQuestion('Repeat the password', TE025_PASSWORD)
        ->assertExitCode(0)
        ->run();

    foreach (['put', 'append', 'prepend', 'replace', 'copy', 'move', 'makeDirectory', 'ensureDirectoryExists'] as $write) {
        $files->shouldNotHaveReceived($write);
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
    Event::assertNotDispatched(MessageLogged::class);
});

test('AC4 the helper code has no file, log or network calls', function () {
    $forbidden = ['Log::', 'Storage::', 'File::', 'Http::', 'file_put_contents', 'fopen', 'fwrite', 'curl_', 'stream_socket', 'logger(', 'info('];

    foreach ([GenerateSecrets::class, SecretsGenerator::class, TerminalQrCode::class, Terminal::class] as $class) {
        $code = (string) file_get_contents((string) (new ReflectionClass($class))->getFileName());

        foreach ($forbidden as $call) {
            expect($code)->not->toContain($call, "{$class} must not use {$call}");
        }
    }
});

test('AC5 without a terminal it fails clearly and prints no secret', function () {
    te025Terminal(false);

    te025Command($this)
        ->expectsOutputToContain('needs an interactive terminal')
        ->doesntExpectOutputToContain('DB_PASSWORD=')
        ->assertExitCode(1);
});

test('AC5 with --no-interaction it fails the same way', function () {
    te025Command($this, ['--no-interaction' => true])
        ->expectsOutputToContain('needs an interactive terminal')
        ->doesntExpectOutputToContain('APP_KEY=')
        ->assertExitCode(1);
});

test('AC5 a password is never accepted as an argument', function () {
    expect(fn () => Artisan::call('traveleasy:generate-secrets', ['--password' => TE025_PASSWORD]))
        ->toThrow(InvalidOptionException::class);

    $options = array_keys(Artisan::all()['traveleasy:generate-secrets']->getDefinition()->getOptions());
    $own = array_values(array_diff($options, ['help', 'silent', 'quiet', 'verbose', 'version', 'ansi', 'no-interaction', 'env']));

    expect($own)->toBe(['without-super-admin', 'light-terminal', 'force-ci'])
        ->and(array_keys(Artisan::all()['traveleasy:generate-secrets']->getDefinition()->getArguments()))->toBe(['command']);
});

test('AC5 a weak or mismatched password is asked again, and after three failures nothing is printed', function () {
    te025Command($this)
        ->expectsQuestion('super_admin password', 'short')
        ->expectsQuestion('super_admin password', TE025_PASSWORD)
        ->expectsQuestion('Repeat the password', 'something else')
        ->expectsQuestion('super_admin password', 'alllowercase-and-long-1')
        ->expectsOutputToContain('Use at least 12 characters.')
        ->expectsOutputToContain('The two passwords differ.')
        ->expectsOutputToContain('Use upper and lower case letters.')
        ->expectsOutputToContain('nothing was printed')
        ->doesntExpectOutputToContain('DB_PASSWORD=')
        ->assertExitCode(1);
});

test('quality with CI=true it refuses unless --force-ci is given', function () {
    config(['app.ci' => true]);

    te025Command($this, ['--without-super-admin' => true])
        ->expectsOutputToContain('refusing to print secrets into a CI log')
        ->doesntExpectOutputToContain('APP_KEY=')
        ->assertExitCode(1);

    expect(te025Lines(['--without-super-admin' => true, '--force-ci' => true]))->toHaveCount(3);
});

test('quality every value is safe as an unquoted env-file line', function () {
    $generator = app(SecretsGenerator::class);
    $values = [...te025Lines(), 'hash' => $generator->passwordHash(TE025_PASSWORD), 'totp' => $generator->totpSecret()];

    foreach ($values as $key => $value) {
        expect($value)->toMatch('#\A[A-Za-z0-9_+/=:-]+\z#', "{$key} has a character that breaks an env file");
    }
});

test('quality the password hash is the framework hash, base64-wrapped, and verifies', function () {
    $hash = app(SecretsGenerator::class)->passwordHash(TE025_PASSWORD);
    $decoded = (string) base64_decode(substr($hash, strlen('base64:')), true);

    expect($hash)->toStartWith('base64:')
        ->and(Hash::check(TE025_PASSWORD, $decoded))->toBeTrue()
        ->and(Hash::check('wrong', $decoded))->toBeFalse()
        ->and(Hash::info($decoded)['algoName'])->toBe('bcrypt');
});

test('quality the TOTP secret is 160-bit base32 and the URI names no person', function () {
    $generator = app(SecretsGenerator::class);
    $secret = $generator->totpSecret();
    $uri = $generator->totpUri($secret);

    expect($secret)->toMatch('/\A[A-Z2-7]{32}\z/')
        ->and($generator->totpSecret())->not->toBe($secret)
        ->and($uri)->toStartWith('otpauth://totp/')
        ->toContain('secret='.$secret)
        ->toContain('super_admin')
        ->not->toContain('@');
});

test('quality the password rules', function (string $password, ?string $problem) {
    expect(GenerateSecrets::passwordProblem($password))->toBe($problem);
})->with([
    'good' => [TE025_PASSWORD, null],
    'too short' => ['Ab1!short', 'Use at least 12 characters.'],
    'too long' => [str_repeat('Ab1!', 19), 'Use at most 72 bytes (bcrypt ignores the rest).'],
    'no upper case' => ['correct-horse-9', 'Use upper and lower case letters.'],
    'no number' => ['Correct-Horse-Battery', 'Use at least one number.'],
    'no symbol' => ['CorrectHorse9Battery', 'Use at least one symbol.'],
]);

test('quality the terminal QR code is square with a quiet zone, and --light-terminal inverts it', function () {
    $dark = TerminalQrCode::render('otpauth://totp/TravelEasy:super_admin?secret=JBSWY3DPEHPK3PXP');
    $light = TerminalQrCode::render('otpauth://totp/TravelEasy:super_admin?secret=JBSWY3DPEHPK3PXP', forLightBackground: true);
    $width = mb_strlen($dark[0] ?? '');

    expect($width)->toBeGreaterThan(2 * TerminalQrCode::QUIET_ZONE + 20)
        ->and(count($dark))->toBe((int) ceil($width / 2))
        ->and($dark[0] ?? '')->toBe(str_repeat("\u{2588}", $width))
        ->and($light[0] ?? '')->toBe(str_repeat(' ', $width));

    foreach ($dark as $line) {
        expect(mb_strlen($line))->toBe($width)
            ->and($line)->toMatch("/\A[ \u{2580}\u{2584}\u{2588}]+\z/u");
    }
});

test('TE-030 AC2 the TOTP issuer is TravelEasy even when APP_NAME is unset (docker run of the production image)', function () {
    // config/app.php falls back to Laravel's default name when APP_NAME is unset, as under `docker run`.
    config(['app.name' => 'Laravel']);

    $uri = app(SecretsGenerator::class)->totpUri('JBSWY3DPEHPK3PXP');
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

    expect(rawurldecode((string) parse_url($uri, PHP_URL_PATH)))->toBe('/TravelEasy:super_admin')
        ->and($query['issuer'] ?? null)->toBe('TravelEasy')
        ->and($uri)->not->toContain('Laravel');
});
