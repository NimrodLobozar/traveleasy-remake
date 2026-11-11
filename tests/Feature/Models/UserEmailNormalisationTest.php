<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| TE-049: the `email` set mutator, so every Eloquent write path (registration, profile, a future super_admin
| provisioning, seeders, tinker) stores the trimmed lowercase form. PostgreSQL only (TestCase guard).
*/

it('stores the address trimmed and lowercase on create', function () {
    $user = User::factory()->create(['email' => '  Owner@Example.COM ']);

    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('owner@example.com')
        ->and($user->email)->toBe('owner@example.com');
});

it('normalises on update, fill and forceFill', function () {
    $user = User::factory()->create();

    $user->update(['email' => 'Update@Example.com']);
    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('update@example.com');

    $user->fill(['email' => ' Fill@Example.com'])->save();
    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('fill@example.com');

    $user->forceFill(['email' => 'FORCE@EXAMPLE.COM '])->save();
    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('force@example.com');
});

it('AC2 stores the env super_admin address in the lowercase form when the account is provisioned', function () {
    config(['auth.super_admin_email' => 'Owner@Example.com']);
    $configured = config('auth.super_admin_email');
    assert(is_string($configured));

    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => $configured,
        'password' => 'a-long-test-password',
    ]);

    expect(DB::table('users')->where('id', $owner->id)->value('email'))->toBe('owner@example.com');
});
