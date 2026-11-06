<?php

use App\Enums\UserMovieAction;
use App\Http\Controllers\AvailabilityLinkController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\MovieEntryController;
use App\Http\Controllers\UndoController;
use App\Http\Controllers\WatchedController;
use App\Http\Controllers\WatchlistController;
use App\Http\Middleware\StorePendingGuestAction;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Discover')->name('home');

Route::get('movies/tmdb/{tmdbId}', MovieEntryController::class)->whereNumber('tmdbId')->name('movies.entry');

Route::middleware('throttle:user-movie-actions')->group(function () {
    Route::put('movies/tmdb/{tmdbId}/watchlist', [WatchlistController::class, 'update'])->middleware(StorePendingGuestAction::for(UserMovieAction::AddToWatchlist))->whereNumber('tmdbId')->name('movies.watchlist.update');
    Route::delete('movies/tmdb/{tmdbId}/watchlist', [WatchlistController::class, 'destroy'])->middleware(StorePendingGuestAction::for(UserMovieAction::RemoveFromWatchlist))->whereNumber('tmdbId')->name('movies.watchlist.destroy');
    Route::put('movies/tmdb/{tmdbId}/watched', [WatchedController::class, 'update'])->middleware(StorePendingGuestAction::for(UserMovieAction::MarkAsWatched))->whereNumber('tmdbId')->name('movies.watched.update');
    Route::delete('movies/tmdb/{tmdbId}/watched', [WatchedController::class, 'destroy'])->middleware(StorePendingGuestAction::for(UserMovieAction::ClearWatched))->whereNumber('tmdbId')->name('movies.watched.destroy');
    Route::post('movies/tmdb/{tmdbId}/undo', UndoController::class)->middleware('auth')->whereNumber('tmdbId')->name('movies.undo');
});
Route::get('movies/{id}/watch', AvailabilityLinkController::class)->whereNumber('id')->name('movies.watch');
Route::get('movies/{slug}-{id}', [MovieController::class, 'show'])->where('slug', '[^/]+')->whereNumber('id')->name('movies.show');

Route::put('country', CountryController::class)->name('country.update');

Route::post('csp-report', CspReportController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:csp-reports')
    ->name('csp-report');

require __DIR__.'/settings.php';
