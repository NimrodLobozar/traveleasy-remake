<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// @phpstan-ignore method.nonObject (inertia-laravel registers the Route::inertia() macro without a return type; it returns the Route.)
Route::inertia('/', 'home')->name('home');

// TE-017: the language switch (CSRF via the web group, 30/min per IP).
Route::post(LocaleController::PATH, LocaleController::class)->middleware('throttle:locale')->name('locale.update');

Route::middleware(['auth'])->group(function () {
    // @phpstan-ignore method.nonObject (inertia-laravel registers the Route::inertia() macro without a return type; it returns the Route.)
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
