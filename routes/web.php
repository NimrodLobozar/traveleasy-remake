<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// @phpstan-ignore method.nonObject (inertia-laravel registers the Route::inertia() macro without a return type; it returns the Route.)
Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    // @phpstan-ignore method.nonObject (inertia-laravel registers the Route::inertia() macro without a return type; it returns the Route.)
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
