<?php

declare(strict_types=1);

use App\Console\Commands\EraseUsers;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountEraser;
use App\Services\Erasure\PersonalDataEraser;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/*
| TE-047: `users:erase` (AC1-AC4, AC6, AC8). PostgreSQL only (TestCase guard).
*/

/** @param array<string, string> $parameters */
function eraseUsers(TestCase $case, array $parameters): PendingCommand
{
    $command = $case->artisan('users:erase', $parameters);

    if (! $command instanceof PendingCommand) {
        throw new RuntimeException('Expected a pending command; console output mocking is off.');
    }

    return $command;
}

/** @return ArrayObject<int, MessageLogged> every log entry written from now on */
function eraseTestCollectLogs(): ArrayObject
{
    /** @var ArrayObject<int, MessageLogged> $logged */
    $logged = new ArrayObject;
    Log::listen(function (MessageLogged $event) use ($logged): void {
        $logged->append($event);
    });

    return $logged;
}

function eraseTestSession(int $userId, string $sessionId): void
{
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $userId,
        'ip_address' => '192.0.2.10',
        'user_agent' => 'Pest',
        'payload' => base64_encode('a:0:{}'),
        'last_activity' => time(),
    ]);
}

function eraseTestPersonalRows(User $user): void
{
    eraseTestSession($user->id, 'session-'.$user->id);
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Laptop',
        'credential_id' => 'cred-'.$user->id,
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function eraseTestIdFile(string $contents): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'erase-ids-');
    file_put_contents($path, $contents);

    return $path;
}

/**
 * Runs the command with `--from-file=-`, feeding stdin through Symfony's streamable input.
 *
 * @return array{0: int, 1: string}
 */
function eraseTestRunWithStdin(string $stdin): array
{
    $stream = fopen('php://memory', 'r+b');
    assert($stream !== false);
    fwrite($stream, $stdin);
    rewind($stream);

    $command = app(EraseUsers::class);
    $command->setLaravel(app());

    $input = new ArrayInput(['--from-file' => '-']);
    $input->setStream($stream);
    $output = new BufferedOutput;

    $exitCode = $command->run($input, $output);

    return [$exitCode, $output->fetch()];
}

// AC1 names reset tokens, but no `password_reset_tokens` table exists (ADR-0005, NoMailFlowsTest).
it('AC1 erases one user with sessions and passkeys, and prints the ID and ERASED', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    eraseTestPersonalRows($user);
    eraseTestPersonalRows($other);

    eraseUsers($this, ['id' => (string) $user->id])
        ->expectsOutput("{$user->id} ERASED")
        ->assertExitCode(0);

    expect(User::query()->find($user->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('passkeys')->where('user_id', $user->id)->count())->toBe(0)
        // The other user is untouched.
        ->and(User::query()->find($other->id))->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1)
        ->and(DB::table('passkeys')->where('user_id', $other->id)->count())->toBe(1);
});

it('AC2 reports an unknown or already erased ID as NOT FOUND, exits 0 and changes nothing', function () {
    $user = User::factory()->create();
    User::factory()->create();
    eraseUsers($this, ['id' => (string) $user->id])->assertExitCode(0);
    $auditRows = AuditLog::query()->count();
    $users = User::query()->count();

    eraseUsers($this, ['id' => (string) $user->id])
        ->expectsOutput("{$user->id} NOT FOUND (already erased)")
        ->assertExitCode(0);
    eraseUsers($this, ['id' => '999999'])
        ->expectsOutput('999999 NOT FOUND (already erased)')
        ->assertExitCode(0);

    expect(AuditLog::query()->count())->toBe($auditRows)
        ->and(User::query()->count())->toBe($users);
});

it('rejects a non-numeric single ID without touching the database', function () {
    User::factory()->create();

    eraseUsers($this, ['id' => 'abc'])
        ->expectsOutput('FAILED (not a user ID)')
        ->doesntExpectOutputToContain('abc')
        ->assertExitCode(1);

    expect(User::query()->count())->toBe(1)->and(AuditLog::query()->count())->toBe(0);
});

it('needs exactly one of an ID or --from-file', function () {
    eraseUsers($this, [])->assertExitCode(Command::INVALID);
    eraseUsers($this, ['id' => '1', '--from-file' => '-'])->assertExitCode(Command::INVALID);
});

it('fails cleanly on an unreadable ID file', function () {
    eraseUsers($this, ['--from-file' => '/nonexistent/ids.txt'])
        ->expectsOutputToContain('cannot be read')
        ->assertExitCode(Command::INVALID);
});

it('AC3 erases each ID of a file, counts blank and non-numeric lines as failed, and exits non-zero', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $kept = User::factory()->create();
    $file = eraseTestIdFile("{$a->id}\n\nnot-an-id\r\n999999\n {$b->id} \n-3\n");

    eraseUsers($this, ['--from-file' => $file])
        ->expectsOutput("{$a->id} ERASED")
        ->expectsOutput('line 2 FAILED (not a user ID)')
        ->expectsOutput('line 3 FAILED (not a user ID)')
        ->expectsOutput('999999 NOT FOUND (already erased)')
        ->expectsOutput("{$b->id} ERASED")
        ->expectsOutput('line 6 FAILED (not a user ID)')
        ->expectsOutput('Summary: 2 erased, 1 not found, 0 refused, 3 failed.')
        ->doesntExpectOutputToContain('not-an-id')
        ->assertExitCode(1);

    expect(User::query()->pluck('id')->all())->toBe([$kept->id]);
    unlink($file);
});

it('AC3 exits 0 for a clean file and is idempotent within it', function () {
    $a = User::factory()->create();
    $file = eraseTestIdFile("{$a->id}\n{$a->id}\n");

    eraseUsers($this, ['--from-file' => $file])
        ->expectsOutput("{$a->id} ERASED")
        ->expectsOutput("{$a->id} NOT FOUND (already erased)")
        ->expectsOutput('Summary: 1 erased, 1 not found, 0 refused, 0 failed.')
        ->assertExitCode(0);
    unlink($file);
});

it('AC3 a failing ID is rolled back and does not stop the others', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();
    eraseTestSession($b->id, 'session-b');

    app()->bind('test.failing-eraser', fn () => new class($b->id) implements PersonalDataEraser
    {
        public function __construct(private readonly int $failFor) {}

        public function erase(User $user): void
        {
            if ($user->id === $this->failFor) {
                throw new RuntimeException('insert failed for '.$user->email);
            }
        }
    });
    app()->tag(['test.failing-eraser'], AccountEraser::ERASER_TAG);
    $logged = eraseTestCollectLogs();

    $file = eraseTestIdFile("{$a->id}\n{$b->id}\n{$c->id}\n");

    eraseUsers($this, ['--from-file' => $file])
        ->expectsOutput("{$a->id} ERASED")
        ->expectsOutput("{$b->id} FAILED")
        ->expectsOutput("{$c->id} ERASED")
        ->expectsOutput('Summary: 2 erased, 0 not found, 0 refused, 1 failed.')
        ->doesntExpectOutputToContain($b->email)
        ->assertExitCode(1);

    expect(User::query()->find($b->id))->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $b->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('subject_user_id', $b->id)->count())->toBe(0);

    expect($logged)->toHaveCount(1);
    $entry = $logged[0] ?? throw new RuntimeException('No erasure log entry.');
    expect($entry->level)->toBe('error')
        ->and($entry->context)->toBe(['user_id' => $b->id, 'exception' => RuntimeException::class])
        ->and($entry->message)->not->toContain($b->email);
    unlink($file);
});

it('AC3 reads the ID list from stdin with -', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    [$exitCode, $output] = eraseTestRunWithStdin("{$a->id}\n{$b->id}\nx\n");

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("{$a->id} ERASED")
        ->and($output)->toContain("{$b->id} ERASED")
        ->and($output)->toContain('line 3 FAILED (not a user ID)')
        ->and($output)->toContain('Summary: 2 erased, 0 not found, 0 refused, 1 failed.')
        ->and(User::query()->count())->toBe(0);
});

it('AC4 refuses the protected super_admin, single and from a file, and changes nothing', function () {
    config(['auth.super_admin_email' => 'Owner@Example.test']);
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    eraseTestSession($owner->id, 'session-owner');
    $other = User::factory()->create();

    eraseUsers($this, ['id' => (string) $owner->id])
        ->expectsOutput("{$owner->id} REFUSED (protected account)")
        ->assertExitCode(1);

    $file = eraseTestIdFile("{$owner->id}\n{$other->id}\n");
    eraseUsers($this, ['--from-file' => $file])
        ->expectsOutput("{$owner->id} REFUSED (protected account)")
        ->expectsOutput("{$other->id} ERASED")
        ->expectsOutput('Summary: 1 erased, 0 not found, 1 refused, 0 failed.')
        ->assertExitCode(1);

    expect(User::query()->find($owner->id))->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('subject_user_id', $owner->id)->count())->toBe(0);
    unlink($file);
});

it('AC6 writes one account_erased audit row with ID and actor only, and no personal data anywhere', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $logged = eraseTestCollectLogs();

    eraseUsers($this, ['id' => (string) $user->id])
        ->doesntExpectOutputToContain('Ada')
        ->doesntExpectOutputToContain('ada@example.test')
        ->assertExitCode(0);

    $rows = AuditLog::query()->get();
    expect($rows)->toHaveCount(1);

    $row = $rows->firstOrFail();
    $stored = (string) json_encode($row->getAttributes());
    expect($row->event)->toBe('account_erased')
        ->and($row->subject_user_id)->toBe($user->id)
        ->and($row->actor)->toBe('cli')
        ->and($row->created_at)->not->toBeNull()
        ->and($stored)->not->toContain('Ada')
        ->and($stored)->not->toContain('ada@example.test');

    foreach ($logged as $entry) {
        expect($entry->message.json_encode($entry->context))
            ->not->toContain('Ada')
            ->not->toContain('ada@example.test');
    }
});

it('AC8 re-erases accounts that a restore brought back, sessions included', function () {
    $erased = User::factory()->create();
    $kept = User::factory()->create();
    eraseTestSession($erased->id, 'session-erased');
    $userRow = (array) DB::table('users')->where('id', $erased->id)->first();
    $sessionRow = (array) DB::table('sessions')->where('id', 'session-erased')->first();

    eraseUsers($this, ['id' => (string) $erased->id])->assertExitCode(0);
    expect(User::query()->find($erased->id))->toBeNull();

    // Simulated restore of a dump taken before the erasure: the rows come back with the same IDs.
    DB::table('users')->insert($userRow);
    DB::table('sessions')->insert($sessionRow);
    expect(User::query()->find($erased->id))->not->toBeNull();

    $file = eraseTestIdFile("{$erased->id}\n");
    eraseUsers($this, ['--from-file' => $file])
        ->expectsOutput("{$erased->id} ERASED")
        ->expectsOutput('Summary: 1 erased, 0 not found, 0 refused, 0 failed.')
        ->assertExitCode(0);

    expect(User::query()->find($erased->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $erased->id)->count())->toBe(0)
        ->and(User::query()->find($kept->id))->not->toBeNull()
        ->and(AuditLog::query()->where('subject_user_id', $erased->id)->count())->toBe(2);
    unlink($file);
});
