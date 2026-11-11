<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| TE-047 AC1/AC4/AC6: self-service deletion goes through the shared AccountEraser and only acts on the signed-in user.
*/

function profileDeletionSession(int $userId, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '192.0.2.5',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);
}

test('self-service deletion erases the user, their sessions and passkeys, and writes an audit row', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    profileDeletionSession($user->id, 'mine-on-another-device');
    profileDeletionSession($other->id, 'someone-else');
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Phone',
        'credential_id' => 'cred-self',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::query()->find($user->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('passkeys')->where('user_id', $user->id)->count())->toBe(0)
        ->and(User::query()->find($other->id))->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'someone-else')->exists())->toBeTrue();

    $row = AuditLog::query()->sole();
    expect($row->only(['event', 'subject_user_id', 'actor']))
        ->toBe(['event' => 'account_erased', 'subject_user_id' => $user->id, 'actor' => 'self']);
});

test('a user with a remember token is not re-created by the logout', function () {
    $user = User::factory()->create(['remember_token' => 'existing-token']);

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect(User::query()->find($user->id))->toBeNull();
});

test('self-service deletion ignores any user ID in the request', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password', 'id' => $victim->id, 'user_id' => $victim->id]);

    expect(User::query()->find($user->id))->toBeNull()
        ->and(User::query()->find($victim->id))->not->toBeNull();
});

test('a wrong password erases nothing and writes no audit row', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(User::query()->find($user->id))->not->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('guests cannot delete an account', function () {
    $user = User::factory()->create();

    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

    expect(User::query()->find($user->id))->not->toBeNull();
});

test('AC4 the protected super_admin cannot delete itself', function () {
    config(['auth.super_admin_email' => 'owner@example.test']);
    $owner = User::factory()->create(['email' => 'owner@example.test']);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    $this->assertAuthenticatedAs($owner);
    expect(User::query()->find($owner->id))->not->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('TE-049 AC8 the super_admin stored in another case cannot delete itself', function () {
    config(['auth.super_admin_email' => 'owner@example.test']);
    $owner = User::factory()->create();
    DB::table('users')->where('id', $owner->id)->update(['email' => 'Owner@Example.test']);
    $owner->refresh();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect(User::query()->find($owner->id))->not->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});
