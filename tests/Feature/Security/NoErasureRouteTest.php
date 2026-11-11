<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
| TE-047 AC7: erasing another user is CLI-only (`users:erase`); no HTTP route exists for it.
*/

test('probing an erasure route returns 404 for guests and signed-in users', function (string $uri) {
    $user = User::factory()->create();
    $victim = User::factory()->create();
    $target = str_replace('{id}', (string) $victim->id, $uri);

    $this->delete($target)->assertNotFound();
    $this->actingAs($user)->delete($target)->assertNotFound();

    expect(User::query()->find($victim->id))->not->toBeNull();
})->with(['/users/{id}', '/admin/users/{id}', '/api/users/{id}']);

test('the only DELETE routes are the self-service profile, passkey and 2FA routes', function () {
    $deleteRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('DELETE', $route->methods(), true))
        ->map(fn (RoutingRoute $route): string => $route->uri())
        ->sort()
        ->values()
        ->all();

    expect($deleteRoutes)->toBe([
        'settings/profile',
        'user/passkeys/{passkey}',
        'user/two-factor-authentication',
    ]);
});

test('no route of any method addresses users by ID', function () {
    $userRoutes = collect(Route::getRoutes()->getRoutes())
        ->map(fn (RoutingRoute $route): string => $route->uri())
        ->filter(fn (string $uri): bool => preg_match('#(^|/)users?/\{#', $uri) === 1)
        ->values()
        ->all();

    expect($userRoutes)->toBe([]);
});
