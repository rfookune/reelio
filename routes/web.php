<?php

use App\Enums\UserMovieAction;
use App\Http\Controllers\AvailabilityLinkController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\MovieCastController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\MovieEntryController;
use App\Http\Controllers\MoviePickerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchRatingsController;
use App\Http\Controllers\UndoController;
use App\Http\Controllers\UserMovieListController;
use App\Http\Controllers\WatchedController;
use App\Http\Controllers\WatchlistController;
use App\Http\Middleware\StorePendingGuestAction;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', DiscoverController::class)->name('home');

Route::get('search', SearchController::class)->middleware('throttle:search')->name('search');
Route::get('search/ratings', SearchRatingsController::class)->middleware('throttle:search-ratings')->name('search.ratings');
Route::get('watchlist', UserMovieListController::class)->middleware('auth')->name('watchlist');
Route::get('watched', UserMovieListController::class)->middleware('auth')->name('watched');
Route::get('watchlist/pick', MoviePickerController::class)->middleware(['auth', 'throttle:movie-picker'])->name('watchlist.pick');

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
Route::get('movies/{slug}-{id}/casts', MovieCastController::class)->where('slug', '[^/]+')->whereNumber('id')->name('movies.cast');

Route::put('country', CountryController::class)->name('country.update');

Route::post('csp-report', CspReportController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:csp-reports')
    ->name('csp-report');

require __DIR__.'/settings.php';
