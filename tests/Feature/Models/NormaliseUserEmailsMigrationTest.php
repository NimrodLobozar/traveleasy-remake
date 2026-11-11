<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function emailMigration(): object
{
    return require database_path('migrations/2026_11_11_100000_normalise_user_emails.php');
}

function rawUser(string $email): void
{
    $user = User::factory()->make();
    DB::table('users')->insert([
        'name' => $user->name,
        'email' => $email,
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('trims and lowercases existing e-mails', function () {
    emailMigration()->down();
    rawUser('  Jan@Example.COM ');
    rawUser('anna@example.com');

    emailMigration()->up();

    expect(DB::table('users')->orderBy('id')->pluck('email')->all())
        ->toBe(['jan@example.com', 'anna@example.com']);
});

it('aborts on a collision without changing anything or naming an address', function () {
    emailMigration()->down();
    rawUser('Jan@Example.com');
    rawUser('jan@example.com ');
    rawUser('Other@Example.com');

    try {
        emailMigration()->up();
        $this->fail('Expected the migration to abort.');
    } catch (RuntimeException $e) {
        expect(strtolower($e->getMessage()))->not->toContain('jan')->not->toContain('example.com');
    }

    expect(DB::table('users')->orderBy('id')->pluck('email')->all())
        ->toBe(['Jan@Example.com', 'jan@example.com ', 'Other@Example.com']);
});

it('rejects a case-variant insert at database level', function () {
    rawUser('jan@example.com');

    expect(fn () => rawUser('Jan@Example.com'))->toThrow(QueryException::class);
});
