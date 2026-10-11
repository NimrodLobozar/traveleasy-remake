<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

// ADR-0005: no e-mail verification and no mail-based password reset.

test('the mail-based fortify features are disabled', function () {
    expect(Features::enabled(Features::emailVerification()))->toBeFalse()
        ->and(Features::enabled(Features::resetPasswords()))->toBeFalse();
});

test('the user model does not require e-mail verification', function () {
    expect(new User)->not->toBeInstanceOf(MustVerifyEmail::class);
});

test('password reset and e-mail verification routes do not exist', function (string $method, string $uri) {
    $this->call($method, $uri)->assertNotFound();
})->with([
    'forgot password page' => ['GET', '/forgot-password'],
    'forgot password submit' => ['POST', '/forgot-password'],
    'reset password page' => ['GET', '/reset-password/some-token'],
    'reset password submit' => ['POST', '/reset-password'],
    'verify e-mail notice' => ['GET', '/email/verify'],
    'verify e-mail link' => ['GET', '/email/verify/1/some-hash'],
    'resend verification' => ['POST', '/email/verification-notification'],
]);

test('the reset and verification routes are gone for authenticated users too', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/email/verify')->assertNotFound();
    $this->actingAs($user)->get('/forgot-password')->assertNotFound();
});

test('a user who just registered sees the dashboard without a verification step', function () {
    $this->post(route('register.store'), [
        'name' => 'New Traveller',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertOk();
});

test('no route uses the verified middleware', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('verified', $route->gatherMiddleware(), true));

    expect($routes)->toBeEmpty();
});

test('the password reset tokens table and e-mail verification column do not exist', function () {
    expect(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse();
});

test('the login page does not offer a password reset link', function () {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->missing('canResetPassword'));
});
