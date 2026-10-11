<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests get a null auth.user prop', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

test('auth.user exposes only id, name and email', function () {
    $user = User::factory()->withTwoFactor()->create([
        'name' => 'Ada Traveller',
        'email' => 'ada@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => 'Ada Traveller',
                'email' => 'ada@example.com',
            ]));
});

test('secrets never appear in the inertia payload', function () {
    $user = User::factory()->withTwoFactor()->create();

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $field) {
        expect($html)->not->toContain('"'.$field.'"');
    }

    expect($html)->not->toContain($user->password);
});
