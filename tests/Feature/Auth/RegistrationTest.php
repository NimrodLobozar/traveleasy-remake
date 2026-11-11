<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

/** @return array<string, string> */
function te049Registration(string $email): array
{
    return [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
}

test('TE-049 a new address is stored trimmed and lowercase', function () {
    $this->post(route('register.store'), te049Registration('  New.User@Example.COM '))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    expect(User::query()->sole()->email)->toBe('new.user@example.com');
});

test('TE-049 AC1 a case variant of an existing address gets the e-mail-taken error on the e-mail field in nl and en', function (string $locale, string $expected) {
    User::factory()->create(['email' => 'admin@example.com']);

    $this->withUnencryptedCookie('locale', $locale)
        ->from(route('register'))
        ->post(route('register.store'), te049Registration(' Admin@Example.com '))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email' => $expected])
        ->assertSessionDoesntHaveErrors(['name', 'password']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
})->with([
    'nl' => ['nl', 'Dit e-mailadres is al in gebruik.'],
    'en' => ['en', 'This e-mail address is already in use.'],
]);

test('TE-049 AC1 a legacy row in another case still blocks the address', function () {
    $existing = User::factory()->create();
    DB::table('users')->where('id', $existing->id)->update(['email' => 'Admin@Example.com']);

    $this->post(route('register.store'), te049Registration('admin@example.com'))
        ->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(1);
});

test('TE-049 AC2 the super_admin address cannot be registered in any case before the account exists', function (string $email) {
    config(['auth.super_admin_email' => 'Owner@Example.com']);

    $this->withUnencryptedCookie('locale', 'nl')->post(route('register.store'), te049Registration($email))
        ->assertSessionHasErrors(['email' => 'Dit e-mailadres is al in gebruik.']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
})->with(['owner@example.com', 'OWNER@example.com', ' Owner@Example.com ']);

test('TE-049 the e-mail-taken error does not echo the address', function () {
    User::factory()->create(['email' => 'admin@example.com']);
    config(['auth.super_admin_email' => 'owner@example.com']);

    foreach (['Admin@Example.com', 'Owner@Example.com'] as $email) {
        $this->post(route('register.store'), te049Registration($email))->assertSessionHasErrors('email');
        $errors = session()->get('errors');
        assert($errors instanceof ViewErrorBag);

        expect((string) json_encode($errors->getBag('default')->toArray()))->not->toContain('xample.com');
    }
});
