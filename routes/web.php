<?php

use App\Http\Controllers\AvailabilityLinkController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\MovieEntryController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Discover')->name('home');

Route::get('movies/tmdb/{tmdbId}', MovieEntryController::class)->whereNumber('tmdbId')->name('movies.entry');
Route::get('movies/{id}/watch', AvailabilityLinkController::class)->whereNumber('id')->name('movies.watch');
Route::get('movies/{slug}-{id}', [MovieController::class, 'show'])->where('slug', '[^/]+')->whereNumber('id')->name('movies.show');

Route::post('csp-report', CspReportController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:csp-reports')
    ->name('csp-report');

require __DIR__.'/settings.php';
