<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountEraser;
use App\Services\Erasure\ErasureOutcome;
use App\Services\Erasure\PersonalDataEraser;
use App\Services\Erasure\ProtectedAccountGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
| TE-047 AC5/AC4/AC6: the shared AccountEraser (eraser registry, rollback, protected guard). Needs PostgreSQL.
*/

uses(TestCase::class, RefreshDatabase::class);

final class RecordingEraser implements PersonalDataEraser
{
    /** @var list<array{user: int, inTransaction: bool, userRowPresent: bool}> */
    public array $calls = [];

    public function __construct(private readonly bool $throw = false) {}

    public function erase(User $user): void
    {
        $this->calls[] = [
            'user' => $user->id,
            'inTransaction' => DB::transactionLevel() > 1, // level 1 is RefreshDatabase's own transaction
            'userRowPresent' => DB::table('users')->where('id', $user->id)->exists(),
        ];

        DB::table('sessions')->where('user_id', $user->id)->update(['user_agent' => 'touched-by-stub']);

        if ($this->throw) {
            throw new RuntimeException('stub eraser failed');
        }
    }
}

function accountEraserSession(int $userId): void
{
    DB::table('sessions')->insert([
        'id' => 'unit-session-'.$userId,
        'user_id' => $userId,
        'ip_address' => '192.0.2.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);
}

it('invokes a registered eraser inside the erasure transaction, before the user row goes', function () {
    $user = User::factory()->create();
    $stub = new RecordingEraser;
    $eraser = new AccountEraser(new ProtectedAccountGuard, [$stub]);

    expect($eraser->eraseById($user->id, 'cli'))->toBe(ErasureOutcome::Erased)
        ->and($stub->calls)->toBe([['user' => $user->id, 'inTransaction' => true, 'userRowPresent' => true]])
        ->and(User::query()->find($user->id))->toBeNull();
});

it('picks up erasers registered with the container tag', function () {
    $user = User::factory()->create();
    $stub = new RecordingEraser;
    app()->instance('test.recording-eraser', $stub);
    app()->tag(['test.recording-eraser'], AccountEraser::ERASER_TAG);

    app(AccountEraser::class)->eraseById($user->id, 'cli');

    expect($stub->calls)->toHaveCount(1);
});

it('rolls back everything for the user when an eraser throws', function () {
    $user = User::factory()->create();
    accountEraserSession($user->id);
    $eraser = new AccountEraser(new ProtectedAccountGuard, [new RecordingEraser(throw: true)]);

    expect(fn () => $eraser->eraseById($user->id, 'cli'))->toThrow(RuntimeException::class);

    expect(User::query()->find($user->id))->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->value('user_agent'))->toBe('Pest')
        ->and(AuditLog::query()->count())->toBe(0);
});

it('refuses a protected account without calling any eraser', function () {
    config(['auth.super_admin_email' => ' owner@example.test ']);
    $owner = User::factory()->create(['email' => 'OWNER@example.test']);
    $stub = new RecordingEraser;
    $eraser = new AccountEraser(new ProtectedAccountGuard, [$stub]);

    expect($eraser->isProtected($owner))->toBeTrue()
        ->and($eraser->erase($owner, 'cli'))->toBe(ErasureOutcome::Refused)
        ->and($stub->calls)->toBe([])
        ->and(User::query()->find($owner->id))->not->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('protects nobody when no super_admin identifier is configured', function (?string $configured) {
    config(['auth.super_admin_email' => $configured]);
    $user = User::factory()->create();

    expect((new ProtectedAccountGuard)->isProtected($user))->toBeFalse();
})->with([null, '', '   ']);

it('returns NotFound for a missing ID without writing an audit row', function () {
    $eraser = new AccountEraser(new ProtectedAccountGuard, []);

    expect($eraser->eraseById(424242, 'cli'))->toBe(ErasureOutcome::NotFound)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('writes the audit row with the given actor and the erased ID only', function () {
    $user = User::factory()->create();
    (new AccountEraser(new ProtectedAccountGuard, []))->erase($user, AccountEraser::ACTOR_SELF);

    $row = AuditLog::query()->sole();
    expect($row->only(['event', 'subject_user_id', 'actor']))
        ->toBe(['event' => 'account_erased', 'subject_user_id' => $user->id, 'actor' => 'self']);
});

it('TE-049 AC7 matches the protected address in the normalised form on both sides', function (string $configured, string $checked) {
    config(['auth.super_admin_email' => $configured]);

    expect((new ProtectedAccountGuard)->isProtectedAddress($checked))->toBeTrue();
})->with([
    'mixed case in env' => ['Owner@Example.test', 'owner@example.test'],
    'mixed case in input' => ['owner@example.test', 'OWNER@example.TEST'],
    'spaces on both sides' => [' owner@example.test ', "\towner@Example.test  "],
]);

it('TE-049 AC7 does not match another address or protect anything without the env value', function (?string $configured, string $checked) {
    config(['auth.super_admin_email' => $configured]);

    expect((new ProtectedAccountGuard)->isProtectedAddress($checked))->toBeFalse();
})->with([
    'other address' => ['owner@example.test', 'owner2@example.test'],
    'unset' => [null, 'owner@example.test'],
    'empty' => ['', ''],
    'blank' => ['   ', '   '],
]);

it('TE-049 AC7 protects a legacy row stored in another case', function () {
    config(['auth.super_admin_email' => 'owner@example.test']);
    $owner = User::factory()->create();
    DB::table('users')->where('id', $owner->id)->update(['email' => ' Owner@Example.TEST']);

    expect((new ProtectedAccountGuard)->isProtected($owner->refresh()))->toBeTrue();
});
