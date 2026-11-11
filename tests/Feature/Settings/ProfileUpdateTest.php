<?php

declare(strict_types=1);

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('the settings root redirects to the profile page on GET only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings')->assertRedirect(route('profile.edit'));
    $this->actingAs($user)->post('/settings')->assertMethodNotAllowed();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('TE-049 AC3 the own address in another case or with spaces saves without a unique error', function () {
    $user = User::factory()->create(['email' => 'jan@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => 'Jan', 'email' => '  Jan@Example.COM '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('jan@example.com');
});

test('TE-049 a new address is stored trimmed and lowercase', function () {
    $user = User::factory()->create(['email' => 'jan@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Jan', 'email' => ' Jan.Nieuw@Example.com'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('jan.nieuw@example.com');
});

test('TE-049 the address of another account in another case gets the e-mail-taken error', function (string $locale) {
    User::factory()->create(['email' => 'piet@example.com']);
    $user = User::factory()->create(['email' => 'jan@example.com']);

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', $locale)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => 'Jan', 'email' => 'Piet@Example.com'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['email' => trans('validation.unique', ['attribute' => 'email'], $locale)])
        ->assertSessionDoesntHaveErrors('name');

    expect($user->refresh()->email)->toBe('jan@example.com');
})->with(['nl', 'en']);

test('TE-049 a customer cannot take the super_admin address in any case', function () {
    config(['auth.super_admin_email' => 'owner@example.com']);
    $user = User::factory()->create(['email' => 'jan@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Jan', 'email' => 'Owner@Example.com'])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->email)->toBe('jan@example.com');
});

test('TE-049 the super_admin keeps its own address in another case', function () {
    config(['auth.super_admin_email' => 'Owner@Example.com']);
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $this->actingAs($owner)
        ->patch(route('profile.update'), ['name' => 'Owner', 'email' => 'OWNER@example.com'])
        ->assertSessionHasNoErrors();

    expect($owner->refresh()->email)->toBe('owner@example.com');
});
