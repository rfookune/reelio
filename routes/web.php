<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

if (app()->isLocal()) {
    // PROTOTYPE, throwaway: Reelio visual language and app shell variants.
    Route::inertia('prototype/shell', 'prototype/ShellPrototype')->name('prototype.shell');

    // PROTOTYPE, throwaway: Movie detail page variants.
    Route::inertia('prototype/movie', 'prototype/MovieDetailPrototype')->name('prototype.movie');
}

require __DIR__.'/settings.php';
