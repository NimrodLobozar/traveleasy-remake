<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('TE-049 AC4 a differently cased address signs in to the same account only', function (string $typed) {
    $user = User::factory()->create(['email' => 'jan@example.com']);
    User::factory()->create(['email' => 'piet@example.com']);

    $this->post(route('login.store'), ['email' => $typed, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(2);
})->with(['Jan@Example.com', 'JAN@EXAMPLE.COM', '  jan@Example.com ']);

test('TE-049 AC4 a differently cased address reaches the two factor challenge of that account', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create(['email' => 'jan@example.com']);

    $this->post(route('login.store'), ['email' => 'Jan@Example.COM', 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

test('TE-049 AC4 a differently cased address with a wrong password stays a guest', function () {
    User::factory()->create(['email' => 'jan@example.com']);

    $this->post(route('login.store'), ['email' => 'Jan@Example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('TE-049 login canonicalises the username through Fortify', function () {
    expect(config('fortify.lowercase_usernames'))->toBeTrue();
});

test('TE-049 case variants of one address share the login throttle', function () {
    User::factory()->create(['email' => 'jan@example.com']);

    foreach (['jan@example.com', 'Jan@Example.com', 'JAN@example.com', 'jan@EXAMPLE.com', 'Jan@example.COM'] as $variant) {
        $this->post(route('login.store'), ['email' => $variant, 'password' => 'wrong-password']);
    }

    $this->post(route('login.store'), ['email' => 'JAN@EXAMPLE.COM', 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});
